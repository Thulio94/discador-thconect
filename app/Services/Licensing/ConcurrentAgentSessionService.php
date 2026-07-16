<?php

namespace App\Services\Licensing;

use App\Models\CompanyLicense;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConcurrentAgentSessionService
{
    /**
     * @param  iterable<int>  $companyIds
     * @return Collection<int, Collection<int, int>>
     */
    public function activeAgentIdsByCompany(iterable $companyIds): Collection
    {
        $companyIds = collect($companyIds)->filter()->unique()->values();

        if ($companyIds->isEmpty()) {
            return collect();
        }

        $sessionTable = (string) config('session.table', 'sessions');
        $minimumActivity = now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();

        return DB::table($sessionTable)
            ->join('users', 'users.id', '=', $sessionTable.'.user_id')
            ->whereIn('users.company_id', $companyIds)
            ->where('users.user_type', 'agent')
            ->where('users.status', 'active')
            ->where($sessionTable.'.last_activity', '>=', $minimumActivity)
            ->select('users.company_id', $sessionTable.'.user_id')
            ->distinct()
            ->get()
            ->groupBy('company_id')
            ->map(fn (Collection $sessions): Collection => $sessions->pluck('user_id')->map(fn ($id): int => (int) $id));
    }

    public function canStart(User $user): bool
    {
        if ($user->user_type !== 'agent' || ! $user->company_id || $user->status !== 'active') {
            return true;
        }

        $limit = CompanyLicense::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->whereDate('starts_at', '<=', today())
            ->whereDate('ends_at', '>=', today())
            ->latest('starts_at')
            ->value('max_concurrent_logged_agents');

        if ($limit === null) {
            return true;
        }

        $activeAgentIds = $this->activeAgentIdsByCompany([$user->company_id])
            ->get($user->company_id, collect());

        return $activeAgentIds->contains($user->id) || $activeAgentIds->count() < (int) $limit;
    }
}
