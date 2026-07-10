<?php

namespace App\Services\Dialer;

use App\Models\Call;
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
            ->whereDoesntHave('calls', function ($query): void {
                $query->where('status', 'in_progress')
                    ->orWhere(function ($query): void {
                        $query->where('status', 'ended')
                            ->whereNull('disposition_id');
                    });
            })
            ->distinct('users.id')
            ->count('users.id');
    }

    public function allowedCallsForCampaign(Campaign $campaign): int
    {
        $campaign->loadMissing('company.license');

        $license = $campaign->company?->license;
        $callsPerAgent = max(1, (int) ($license?->max_calls_per_available_agent ?? 1));

        return $this->availableAgentsForCampaign($campaign) * $callsPerAgent;
    }

    public function activeCallsForCompany(int $companyId): int
    {
        return Call::query()
            ->where('company_id', $companyId)
            ->where('status', 'in_progress')
            ->count();
    }

    public function remainingConcurrentCallsForCampaign(Campaign $campaign): int
    {
        $campaign->loadMissing('company.license');

        $capacity = $this->allowedCallsForCampaign($campaign);
        $globalLimit = $campaign->company?->license?->max_concurrent_calls;
        $campaignLimit = $campaign->max_concurrent_calls;

        if ($campaignLimit !== null) {
            $activeCampaignCalls = Call::query()
                ->where('company_id', $campaign->company_id)
                ->where('campaign_id', $campaign->id)
                ->where('status', 'in_progress')
                ->count();

            $capacity = min($capacity, max(0, $campaignLimit - $activeCampaignCalls));
        }

        if ($globalLimit === null) {
            return $capacity;
        }

        return max(
            0,
            min($capacity, $globalLimit - $this->activeCallsForCompany($campaign->company_id)),
        );
    }
}
