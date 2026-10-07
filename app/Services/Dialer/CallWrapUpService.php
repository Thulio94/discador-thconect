<?php

namespace App\Services\Dialer;

use App\Models\AgentPause;
use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\User;
use App\Services\Capacity\ChannelCapacityService;
use App\Services\Telephony\AgentWebRtcPresenceService;
use Illuminate\Support\Facades\DB;

final class CallWrapUpService
{
    // AMI may spend 45 seconds originating the route and Asterisk then rings
    // the browser agent for 40 seconds. Keep the reservation through both legs.
    private const ASTERISK_EVENT_TIMEOUT_SECONDS = 90;

    public function __construct(
        private readonly ChannelCapacityService $capacity,
        private readonly MailingQueueService $queue,
        private readonly AgentWebRtcPresenceService $presence,
    ) {}

    public function expireForUser(User $user): bool
    {
        return $this->expire(
            Call::query()
                ->where('company_id', $user->company_id)
                ->where('user_id', $user->id),
        ) > 0;
    }

    public function expireForCompany(int $companyId): int
    {
        return $this->expire(Call::query()->where('company_id', $companyId));
    }

    public function expireDue(): int
    {
        return $this->expire(Call::query()) + $this->expireStaleAsteriskCalls();
    }

    public function hasPendingForUser(User $user): bool
    {
        return Call::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('status', 'wrap_up')
            ->where('wrap_up_expires_at', '>', now())
            ->exists();
    }

    private function expire($query): int
    {
        $callIds = $query
            ->where('status', 'wrap_up')
            ->whereNotNull('wrap_up_expires_at')
            ->where('wrap_up_expires_at', '<=', now())
            ->pluck('id');

        $expired = 0;

        foreach ($callIds as $callId) {
            $didExpire = DB::transaction(function () use ($callId): bool {
                $call = Call::query()->lockForUpdate()->find($callId);

                if (! $call || $call->status !== 'wrap_up' || ! $call->wrap_up_expires_at?->isPast()) {
                    return false;
                }

                $call->update([
                    'status' => 'ended',
                    'end_reason' => 'wrap_up_timeout',
                    'wrap_up_expires_at' => null,
                ]);

                if ($call->user_id && $user = User::query()->find($call->user_id)) {
                    $this->releaseAgent($user, $call);
                }

                return true;
            });

            $expired += $didExpire ? 1 : 0;
        }

        return $expired;
    }

    private function expireStaleAsteriskCalls(): int
    {
        $callIds = Call::query()
            ->where('channel', 'asterisk_ami')
            ->whereIn('status', ['dialing', 'ringing'])
            ->where('started_at', '<=', now()->subSeconds(self::ASTERISK_EVENT_TIMEOUT_SECONDS))
            ->pluck('id');

        $expired = 0;

        foreach ($callIds as $callId) {
            $didExpire = DB::transaction(function () use ($callId): bool {
                $call = Call::query()->lockForUpdate()->find($callId);

                if (! $call
                    || $call->channel !== 'asterisk_ami'
                    || ! in_array($call->status, ['dialing', 'ringing'], true)
                    || $call->started_at?->greaterThan(now()->subSeconds(self::ASTERISK_EVENT_TIMEOUT_SECONDS))) {
                    return false;
                }

                $call->update([
                    'status' => 'cancelled',
                    'end_reason' => 'asterisk_event_timeout',
                    'hangup_party' => 'system',
                    'ended_at' => now(),
                ]);

                $this->capacity->release($call->channel_reservation_id, 'asterisk_event_timeout');
                $this->queue->recordUnansweredAttempt($call, 'failed');

                if ($call->user_id && $user = User::query()->find($call->user_id)) {
                    $this->releaseAgent($user, $call);
                }

                return true;
            });

            $expired += $didExpire ? 1 : 0;
        }

        return $expired;
    }

    public function releaseAgent(User $user, ?Call $call = null): void
    {
        if (Call::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->when($call, fn ($query) => $query->whereKeyNot($call->id))
            ->whereIn('status', [...Call::ACTIVE_STATUSES, 'wrap_up'])
            ->exists()) {
            return;
        }

        AgentStatus::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        $mustRemainOffline = (bool) data_get($call?->provider_payload, 'agent_console_closed', false);
        $manualPauseReason = ! $mustRemainOffline && $call?->channel === 'manual'
            ? data_get($call->provider_payload, 'resume_pause.reason')
            : null;

        if ($manualPauseReason) {
            $user->agentPauses()->create([
                'company_id' => $user->company_id,
                'reason' => $manualPauseReason,
                'started_at' => now(),
            ]);
        }

        $user->agentStatuses()->create([
            'company_id' => $user->company_id,
            'status' => $mustRemainOffline
                || ($user->user_type === 'agent' && ! $this->presence->isRegistered($user))
                ? 'offline'
                : ($manualPauseReason ? 'paused' : 'available'),
            'reason' => $manualPauseReason,
            'started_at' => now(),
        ]);
    }
}
