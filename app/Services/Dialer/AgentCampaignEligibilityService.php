<?php

namespace App\Services\Dialer;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Collection;

class AgentCampaignEligibilityService
{
    public function eligibleCampaigns(User $agent): Collection
    {
        $teamIds = $agent->teams()
            ->wherePivot('membership_type', 'agent')
            ->where('teams.company_id', $agent->company_id)
            ->where('teams.status', 'active')
            ->pluck('teams.id');

        if ($teamIds->isEmpty()) {
            return collect();
        }

        return Campaign::query()
            ->where('company_id', $agent->company_id)
            ->where('status', 'active')
            ->whereIn('id', function ($query) use ($teamIds, $agent): void {
                $query->select('campaign_team.campaign_id')
                    ->from('campaign_team')
                    ->where('campaign_team.company_id', $agent->company_id)
                    ->whereIn('campaign_team.team_id', $teamIds);
            })
            ->orderByDesc('priority')
            ->orderBy('last_dispatched_at')
            ->get();
    }

    public function requiresSelection(User $agent, ?Collection $campaigns = null): bool
    {
        $campaigns ??= $this->eligibleCampaigns($agent);

        if ($campaigns->count() < 2) {
            return false;
        }

        return $agent->teams()
            ->wherePivot('membership_type', 'agent')
            ->where('teams.company_id', $agent->company_id)
            ->where('teams.status', 'active')
            ->where('teams.allow_agent_campaign_selection', true)
            ->exists();
    }

    public function canReceivePowerCampaign(User $agent, Campaign $campaign): bool
    {
        if ($campaign->status !== 'active'
            || $campaign->dialing_mode !== 'power'
            || $agent->company_id !== $campaign->company_id) {
            return false;
        }

        $campaignTeamIds = $campaign->teams()
            ->wherePivot('company_id', $campaign->company_id)
            ->where('teams.company_id', $campaign->company_id)
            ->where('teams.status', 'active')
            ->where('teams.operational_type', 'power')
            ->pluck('teams.id');

        if ($campaignTeamIds->isEmpty()) {
            return false;
        }

        $belongsToCampaignTeam = $agent->teams()
            ->wherePivot('membership_type', 'agent')
            ->wherePivot('company_id', $campaign->company_id)
            ->where('teams.company_id', $campaign->company_id)
            ->where('teams.status', 'active')
            ->where('teams.operational_type', 'power')
            ->whereIn('teams.id', $campaignTeamIds)
            ->exists();

        if (! $belongsToCampaignTeam) {
            return false;
        }

        if (! $this->requiresSelection($agent)) {
            return true;
        }

        return $agent->currentAgentCampaignSession()
            ->where('company_id', $campaign->company_id)
            ->where('campaign_id', $campaign->id)
            ->exists();
    }
}
