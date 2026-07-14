<?php

namespace App\Services\Telephony;

use App\Models\Call;

class AsteriskCallEventService
{
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
        }

        if ($event === 'amd') {
            $updates['amd_result'] = $payload['amd_result'] ?? null;
        }

        if ($event === 'originate_failed') {
            $updates['status'] = 'cancelled';
            $updates['end_reason'] = $payload['cause'] ?? 'originate_failed';
            $updates['ended_at'] = now();
        }

        if ($event === 'hangup') {
            $updates['status'] = 'ended';
            $updates['end_reason'] = $payload['cause'] ?? $call->end_reason;
            $updates['ended_at'] = $call->ended_at ?? now();
        }

        $call->update($updates);
    }
}
