<?php

namespace App\Services\Telephony;

use App\Models\Call;
use App\Services\Capacity\ChannelCapacityService;
use App\Jobs\StoreCallRecording;

class AsteriskCallEventService
{
    public function __construct(private readonly ChannelCapacityService $capacity) {}

    /** @param array<string, mixed> $payload */
    public function apply(array $payload): void
    {
        $call = Call::query()
            ->whereKey((int) $payload['call_id'])
            ->where('company_id', (int) $payload['company_id'])
            ->first();

        if (! $call) {
            return;
        }

        $event = (string) $payload['event'];
        $updates = [
            'telephony_channel' => $payload['channel'] ?? $call->telephony_channel,
            'telephony_uniqueid' => $payload['uniqueid'] ?? $call->telephony_uniqueid,
            'provider_payload' => $payload,
        ];

        if ($event === 'originate_succeeded') {
            $updates['status'] = 'ringing';
        }

        if ($event === 'answered') {
            $updates['status'] = 'in_progress';
            $updates['answered_at'] = $call->answered_at ?? now();
            $this->capacity->activate($call->channel_reservation_id, $call->id);
        }

        if ($event === 'amd') {
            $updates['amd_result'] = $payload['amd_result'] ?? null;
        }

        if ($event === 'originate_failed') {
            $updates['status'] = 'cancelled';
            $updates['end_reason'] = $payload['cause'] ?? 'originate_failed';
            $updates['hangup_party'] = 'carrier';
            $updates['ended_at'] = now();
            $this->capacity->release($call->channel_reservation_id, 'originate_failed');
        }

        if ($event === 'hangup') {
            $updates['status'] = 'ended';
            $updates['end_reason'] = $payload['cause'] ?? $call->end_reason;
            $updates['hangup_party'] = $call->hangup_party ?? $this->hangupParty($payload);
            $updates['ended_at'] = $call->ended_at ?? now();
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
        if ($event === 'recording_ready') StoreCallRecording::dispatch($call->id)->onQueue('media');
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
}
