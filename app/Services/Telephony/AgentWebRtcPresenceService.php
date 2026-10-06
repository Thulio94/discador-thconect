<?php

namespace App\Services\Telephony;

use App\Models\AgentWebRtcPresence;
use App\Models\User;

class AgentWebRtcPresenceService
{
    private const FRESH_FOR_SECONDS = 50;

    public function report(User $user, string $connectionId, bool $registered): void
    {
        AgentWebRtcPresence::query()
            ->where('company_id', $user->company_id)
            ->where('last_seen_at', '<', now()->subDay())
            ->delete();

        AgentWebRtcPresence::query()->updateOrCreate(
            ['user_id' => $user->id, 'connection_id' => $connectionId],
            [
                'company_id' => $user->company_id,
                'registered' => $registered,
                'last_seen_at' => now(),
            ],
        );
    }

    public function isRegistered(User $user): bool
    {
        return AgentWebRtcPresence::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('registered', true)
            ->where('last_seen_at', '>=', now()->subSeconds(self::FRESH_FOR_SECONDS))
            ->exists();
    }

    public function markDisconnected(User $user): void
    {
        $user->webRtcPresences()
            ->where('company_id', $user->company_id)
            ->where('registered', true)
            ->update(['registered' => false, 'last_seen_at' => now()]);
    }
}
