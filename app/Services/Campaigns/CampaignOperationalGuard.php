<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CompanyLicense;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignOperationalGuard
{
    public function assertTeamsMatchMode(int $companyId, string $mode, array $teamIds): void
    {
        $teams = Team::query()
            ->where('company_id', $companyId)
            ->whereIn('id', collect($teamIds)->map(fn ($id) => (int) $id)->filter())
            ->get();

        if ($teams->count() !== count(array_unique(array_map('intval', $teamIds)))) {
            throw ValidationException::withMessages(['team_ids' => 'Selecione apenas equipes desta empresa.']);
        }

        if ($teams->contains(fn (Team $team) => $team->operational_type !== $mode)) {
            throw ValidationException::withMessages([
                'team_ids' => 'Uma equipe só pode participar de campanhas do seu tipo operacional.',
            ]);
        }
    }

    public function assertCampaignAssignment(User $user, array $campaignIds): void
    {
        if ($user->user_type !== 'agent') {
            return;
        }

        $teams = $user->teams()->wherePivot('membership_type', 'agent')->get();
        $campaigns = Campaign::query()
            ->where('company_id', $user->company_id)
            ->whereIn('id', $campaignIds)
            ->get();
        $types = $teams->pluck('operational_type')->unique();

        if ($campaigns->count() !== count(array_unique(array_map('intval', $campaignIds)))
            || $campaigns->contains(fn (Campaign $campaign) => ! $types->contains($campaign->dialing_mode))) {
            throw ValidationException::withMessages(['campaign_ids' => 'O agente só pode ser vinculado a campanhas compatíveis com sua equipe.']);
        }
    }

    public function assertAgentsMatchOperationalType(int $companyId, string $operationalType, array $agentIds): void
    {
        $agentIds = array_values(array_unique(array_filter(array_map(
            static fn ($id): int => (int) $id,
            $agentIds,
        ))));
        if ($agentIds === []) {
            return;
        }

        $agents = User::query()
            ->where('company_id', $companyId)
            ->where('user_type', 'agent')
            ->whereIn('id', $agentIds)
            ->count();
        if ($agents !== count($agentIds)) {
            throw ValidationException::withMessages(['agent_ids' => 'Selecione apenas agentes desta empresa.']);
        }

        $hasIncompatibleCampaign = DB::table('campaign_user')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_user.campaign_id')
            ->where('campaign_user.company_id', $companyId)
            ->where('campaigns.company_id', $companyId)
            ->whereIn('campaign_user.user_id', $agentIds)
            ->where('campaigns.dialing_mode', '!=', $operationalType)
            ->exists();

        if ($hasIncompatibleCampaign) {
            throw ValidationException::withMessages([
                'agent_ids' => 'Agentes vinculados a campanhas só podem entrar em equipes do mesmo tipo operacional.',
            ]);
        }
    }

    public function effectiveAggressiveness(int $companyId, ?int $requested): int
    {
        $limit = max(1, (int) CompanyLicense::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->latest('id')
            ->value('max_calls_per_available_agent'));

        return min($limit, max(1, (int) ($requested ?: 1)));
    }

    public function assertNoOperationalTypeConflicts(int $companyId): Collection
    {
        return Team::query()
            ->where('company_id', $companyId)
            ->whereHas('campaigns', function ($query): void {
                $query->selectRaw('campaign_team.team_id')
                    ->groupBy('campaign_team.team_id')
                    ->havingRaw('COUNT(DISTINCT campaigns.dialing_mode) > 1');
            })
            ->get();
    }
}
