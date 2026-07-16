<?php

namespace App\Services\Dialer;

use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\OperationalParameter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CallWrapUpService
{
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
        return $this->expire(Call::query());
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
                    $this->releaseAgent($user);
                }

                return true;
            });

            $expired += $didExpire ? 1 : 0;
        }

        return $expired;
    }

    public function releaseAgent(User $user): void
    {
        AgentStatus::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        $parameters = OperationalParameter::forCompany($user->company_id);
        $user->agentStatuses()->create([
            'company_id' => $user->company_id,
            'status' => $user->user_type === 'agent' && $parameters->auto_available_after_call
                ? 'available'
                : 'offline',
            'started_at' => now(),
        ]);
    }
}
