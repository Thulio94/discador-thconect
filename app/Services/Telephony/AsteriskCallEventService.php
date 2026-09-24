<?php

namespace App\Services\Telephony;

use App\Jobs\CancelPowerBatchCall;
use App\Jobs\StoreCallRecording;
use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\CallEvent;
use App\Services\Capacity\ChannelCapacityService;
use App\Services\Dialer\CallWrapUpService;
use App\Services\Dialer\MailingQueueService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AsteriskCallEventService
{
    public function __construct(
        private readonly ChannelCapacityService $capacity,
        private readonly CallWrapUpService $wrapUps,
    ) {}

    /** @param array<string, mixed> $payload */
    public function apply(array $payload): void
    {
        $cancelIds = DB::transaction(fn (): array => $this->applyLocked($payload), 3);

        foreach ($cancelIds as $callId) {
            CancelPowerBatchCall::dispatch($callId)->onQueue('telephony')->afterCommit();
        }
    }

    /** @param array<string, mixed> $payload
     * @return array<int, int>
     */
    private function applyLocked(array $payload): array
    {
        $event = (string) $payload['event'];
        if ($event === 'answered') {
            $campaignId = Call::query()->whereKey((int) $payload['call_id'])
                ->where('company_id', (int) $payload['company_id'])->value('campaign_id');
            if ($campaignId) {
                DB::table('campaigns')->where('id', $campaignId)->lockForUpdate()->first();
            }
        }

        $call = Call::query()
            ->whereKey((int) $payload['call_id'])
            ->where('company_id', (int) $payload['company_id'])
            ->lockForUpdate()
            ->first();

        if (! $call) {
            return [];
        }

        if ($call->status === 'cancelled' && $call->end_reason === 'power_batch_winner' && $event === 'hangup') {
            $this->capacity->release($call->channel_reservation_id, 'power_batch_hangup');
            return [];
        }

        if (in_array($call->status, ['ended', 'cancelled'], true) && $event !== 'recording_ready') {
            return [];
        }

        $callEvent = CallEvent::query()->firstOrCreate([
            'call_id' => $call->id,
            'event_key' => $this->eventKey($payload),
        ], [
            'company_id' => $call->company_id,
            'event' => $event,
            'telephony_uniqueid' => $payload['uniqueid'] ?? $call->telephony_uniqueid,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);

        if (! $callEvent->wasRecentlyCreated) {
            return [];
        }

        $cancelIds = [];
        $previousStatus = $call->status;
        $updates = [
            'telephony_channel' => $payload['channel'] ?? $call->telephony_channel,
            'telephony_uniqueid' => $payload['uniqueid'] ?? $call->telephony_uniqueid,
            'provider_payload' => $payload,
        ];

        if ($event === 'originate_succeeded' && $call->answered_at === null && in_array($call->status, ['dialing', 'ringing'], true)) {
            $updates['status'] = 'ringing';
        }

        if ($event === 'answered' && $call->answered_at === null) {
            $winnerExists = $call->dial_batch_id && Call::query()
                ->where('company_id', $call->company_id)
                ->where('dial_batch_id', $call->dial_batch_id)
                ->whereKeyNot($call->id)
                ->whereNotNull('answered_at')
                ->exists();

            if ($winnerExists) {
                $this->cancelBatchCall($call);
                return [$call->id];
            }

            $updates['status'] = 'in_progress';
            $updates['answered_at'] = $call->answered_at ?? now();
            $this->capacity->activate($call->channel_reservation_id, $call->id);
            $this->moveAgentTo($call, 'in_call');

            if ($call->dial_batch_id) {
                $siblings = Call::query()
                    ->where('company_id', $call->company_id)
                    ->where('dial_batch_id', $call->dial_batch_id)
                    ->whereKeyNot($call->id)
                    ->whereIn('status', Call::ACTIVE_STATUSES)
                    ->lockForUpdate()
                    ->get();
                foreach ($siblings as $sibling) {
                    $this->cancelBatchCall($sibling);
                    $cancelIds[] = $sibling->id;
                }
            }
        }

        if ($event === 'amd') {
            $updates['amd_result'] = $payload['amd_result'] ?? null;
        }

        if ($event === 'originate_failed' && $call->answered_at === null) {
            $updates['status'] = 'cancelled';
            $updates['end_reason'] = $this->failureReason((string) ($payload['cause'] ?? 'originate_failed'));
            $updates['hangup_party'] = 'carrier';
            $updates['ended_at'] = now();
            $this->capacity->release($call->channel_reservation_id, 'originate_failed');
        }

        if ($event === 'hangup') {
            $answered = $call->answered_at !== null || $previousStatus === 'in_progress';
            $needsDisposition = $answered && $call->user_id && ! in_array($previousStatus, ['ended', 'cancelled', 'wrap_up'], true);
            $updates['status'] = $needsDisposition ? 'wrap_up' : ($answered ? 'ended' : 'cancelled');
            $updates['end_reason'] = $payload['cause'] ?? $call->end_reason;
            $updates['hangup_party'] = $call->hangup_party ?? $this->hangupParty($payload);
            $updates['ended_at'] = $call->ended_at ?? now();
            $updates['wrap_up_expires_at'] = $needsDisposition
                ? now()->addSeconds(max(10, (int) ($call->campaign?->wrap_up_seconds ?? 60)))
                : null;
            $this->capacity->release($call->channel_reservation_id, 'hangup');
        }

        if ($event === 'recording_ready') {
            $updates['recording_path'] = $payload['recording_path'] ?? $call->recording_path;
            $updates['recording_disk'] = 'recordings';
            $updates['recording_format'] = $payload['recording_format'] ?? 'wav';
            $updates['recording_size'] = $payload['recording_size'] ?? null;
            $updates['recording_available_at'] = now();
        }

        $call->update($updates);

        if ($event === 'answered' && $call->status === 'in_progress') {
            app(MailingQueueService::class)->recordDelivered($call);
        }

        if (($event === 'originate_failed' && $call->status === 'cancelled') || ($event === 'hangup' && $call->status === 'cancelled')) {
            app(MailingQueueService::class)->recordUnansweredAttempt($call, $event === 'hangup' ? 'no_answer' : 'failed');
        }

        if ($event === 'originate_failed' && $call->status === 'cancelled') {
            $this->releaseAgent($call);
        } elseif ($event === 'hangup' && $call->status === 'wrap_up') {
            $this->moveAgentTo($call, 'wrap_up');
        } elseif ($event === 'hangup' && $call->status === 'cancelled') {
            $this->releaseAgent($call);
        }

        if ($event === 'recording_ready') {
            StoreCallRecording::dispatch($call->id)->onQueue('media')->afterCommit();
        }

        return $cancelIds;
    }

    private function cancelBatchCall(Call $call): void
    {
        $call->update([
            'status' => 'cancelled',
            'end_reason' => 'power_batch_winner',
            'hangup_party' => 'system',
            'ended_at' => now(),
        ]);
        if ($call->channel_reservation_id) {
            DB::table('channel_reservations')
                ->where('id', $call->channel_reservation_id)
                ->whereIn('status', ['reserved', 'active'])
                ->update(['expires_at' => now()->addSeconds(90)]);
        }
        app(MailingQueueService::class)->recordBatchCancellation($call);
    }

    private function moveAgentTo(Call $call, string $status): void
    {
        if (! $call->user_id || $call->user?->currentAgentStatus?->status === $status) {
            return;
        }

        AgentStatus::query()
            ->where('company_id', $call->company_id)
            ->where('user_id', $call->user_id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        $call->user->agentStatuses()->create([
            'company_id' => $call->company_id,
            'status' => $status,
            'started_at' => now(),
        ]);
    }

    private function releaseAgent(Call $call): void
    {
        if ($call->user) {
            $this->wrapUps->releaseAgent($call->user, $call);
        }
    }

    /** @param array<string, mixed> $payload */
    private function eventKey(array $payload): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (Arr::isAssoc($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }

    private function hangupParty(array $payload): string
    {
        $cause = strtolower((string) ($payload['cause'] ?? ''));
        if (str_contains($cause, 'congestion') || str_contains($cause, 'network')) {
            return 'carrier';
        }
        if (str_contains($cause, 'normal') || str_contains($cause, 'busy')) {
            return 'customer';
        }

        return 'unknown';
    }

    private function failureReason(string $cause): string
    {
        $normalized = strtolower($cause);

        if (str_contains($normalized, 'auth') || str_contains($normalized, '401') || str_contains($normalized, '407') || str_contains($normalized, 'unauthorized')) {
            return 'provider_authentication_required';
        }

        return $cause;
    }
}
