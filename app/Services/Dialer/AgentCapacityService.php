<?php

namespace App\Services\Dialer;

use App\Models\Campaign;
use App\Models\User;

class AgentCapacityService
{
    public function availableAgentsForCompany(int $companyId): int
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('user_type', 'agent')
            ->whereHas('currentAgentStatus', fn ($query) => $query->where('status', 'available'))
            ->count();
    }

    public function availableAgentsForCampaign(Campaign $campaign): int
    {
        $teamIds = $campaign->teams()->pluck('teams.id');

        if ($teamIds->isEmpty()) {
            return 0;
        }

        return User::query()
            ->where('users.company_id', $campaign->company_id)
            ->where('users.status', 'active')
            ->where('users.user_type', 'agent')
            ->whereIn('users.id', function ($query) use ($campaign, $teamIds): void {
                $query->select('team_user.user_id')
                    ->from('team_user')
                    ->where('team_user.company_id', $campaign->company_id)
                    ->where('team_user.membership_type', 'agent')
                    ->whereIn('team_user.team_id', $teamIds);
            })
            ->whereHas('currentAgentStatus', fn ($query) => $query->where('status', 'available'))
            ->distinct('users.id')
            ->count('users.id');
    }

    public function allowedCallsForCampaign(Campaign $campaign): int
    {
        $campaign->loadMissing('company.license');

        $license = $campaign->company?->license;
        $callsPerAgent = max(1, (int) ($license?->max_calls_per_available_agent ?? 1));
        $allowed = $this->availableAgentsForCampaign($campaign) * $callsPerAgent;

        if ($license?->max_concurrent_calls) {
            return min($allowed, $license->max_concurrent_calls);
        }

        return $allowed;
    }
}
