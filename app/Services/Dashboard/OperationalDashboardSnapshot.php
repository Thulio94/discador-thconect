<?php

namespace App\Services\Dashboard;

use App\Models\Call;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class OperationalDashboardSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $company = $user->company;
        $license = $company?->license;
        $teamIds = $this->supervisedTeamIds($user);
        $agentIds = $this->agentIds($user, $teamIds);
        $statuses = $this->agentQuery($user, $teamIds)
            ->with('currentAgentStatus')
            ->get()
            ->map(fn (User $agent): string => $agent->currentAgentStatus?->status ?? 'offline');
        $callsToday = $this->callsQuery($user, $agentIds)
            ->whereDate('started_at', today());
        $totalToday = (clone $callsToday)->count();
        $answeredToday = (clone $callsToday)->whereNotNull('answered_at')->count();
        $available = $statuses->filter(fn (string $status): bool => $status === 'available')->count();
        $callsPerAgent = max(1, (int) ($license?->max_calls_per_available_agent ?? 1));
        $agentCapacity = $available * $callsPerAgent;
        $companyLimit = $license?->max_concurrent_calls;
        $effectiveCapacity = $companyLimit === null ? $agentCapacity : min($agentCapacity, $companyLimit);

        return [
            'scopeLabel' => $user->user_type === 'supervisor'
                ? ($teamIds->isEmpty() ? 'Nenhuma equipe vinculada' : 'Escopo: '.$this->teamLabel($user, $teamIds))
                : 'Escopo: Toda a empresa',
            'isTeamScoped' => $user->user_type === 'supervisor',
            'metrics' => [
                'available' => $available,
                'in_call' => $statuses->filter(fn (string $status): bool => $status === 'in_call')->count(),
                'paused' => $statuses->filter(fn (string $status): bool => $status === 'paused')->count(),
                'wrap_up' => $statuses->filter(fn (string $status): bool => $status === 'wrap_up')->count(),
                'active_calls' => $this->callsQuery($user, $agentIds)->whereIn('status', Call::ACTIVE_STATUSES)->count(),
                'calls_today' => $totalToday,
                'answered_today' => $answeredToday,
                'answer_rate' => $totalToday > 0 ? (int) round(($answeredToday / $totalToday) * 100) : 0,
                'calls_per_available_agent' => $callsPerAgent,
                'agent_capacity' => $agentCapacity,
                'effective_capacity' => $effectiveCapacity,
                'company_call_limit' => $companyLimit,
            ],
        ];
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    public function supervisedTeamIds(User $user)
    {
        if ($user->user_type !== 'supervisor') {
            return collect();
        }

        return $user->assignedTeams()
            ->wherePivot('membership_type', 'supervisor')
            ->where('teams.company_id', $user->company_id)
            ->pluck('teams.id');
    }

    public function canAccessAgent(User $user, User $agent): bool
    {
        if ($agent->company_id !== $user->company_id) {
            return false;
        }

        if ($user->user_type !== 'supervisor') {
            return true;
        }

        $teamIds = $this->supervisedTeamIds($user);

        return $teamIds->isNotEmpty() && $agent->assignedTeams()
            ->whereIn('teams.id', $teamIds)
            ->wherePivot('membership_type', 'agent')
            ->exists();
    }

    private function agentQuery(User $user, $teamIds): Builder
    {
        return User::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->where('user_type', 'agent')
            ->when($user->user_type === 'supervisor', fn (Builder $query) => $query->whereIn('users.id', $this->agentsForTeamsQuery($user->company_id, $teamIds)));
    }

    private function agentIds(User $user, $teamIds)
    {
        return $this->agentQuery($user, $teamIds)->pluck('users.id');
    }

    private function callsQuery(User $user, $agentIds): Builder
    {
        return Call::query()
            ->where('company_id', $user->company_id)
            ->when(
                $user->user_type === 'supervisor',
                fn (Builder $query) => $query->whereIn('user_id', $agentIds)
            );
    }

    private function agentsForTeamsQuery(?int $companyId, $teamIds): Builder
    {
        return User::query()
            ->select('team_user.user_id')
            ->from('team_user')
            ->where('team_user.company_id', $companyId)
            ->where('team_user.membership_type', 'agent')
            ->whereIn('team_user.team_id', $teamIds);
    }

    private function teamLabel(User $user, $teamIds): string
    {
        return $user->assignedTeams()
            ->whereIn('teams.id', $teamIds)
            ->orderBy('teams.name')
            ->pluck('teams.name')
            ->implode(', ');
    }
}
