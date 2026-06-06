<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupervisionController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;
        $status = $request->string('status')->toString();
        $teamId = $request->integer('team_id') ?: null;

        $agents = User::query()
            ->where('company_id', $companyId)
            ->whereIn('user_type', ['agent', 'supervisor'])
            ->with(['currentAgentStatus', 'calls' => fn ($query) => $query->where('status', 'in_progress')->latest()])
            ->when($teamId, fn ($query) => $query->whereIn('users.id', function ($teamQuery) use ($companyId, $teamId): void {
                $teamQuery->select('team_user.user_id')
                    ->from('team_user')
                    ->where('team_user.company_id', $companyId)
                    ->where('team_user.team_id', $teamId);
            }))
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($companyId): User {
                $user->setAttribute('supervision_status', $user->currentAgentStatus?->status ?? 'offline');
                $user->setAttribute('team_names', DB::table('team_user')
                    ->join('teams', 'teams.id', '=', 'team_user.team_id')
                    ->where('team_user.company_id', $companyId)
                    ->where('team_user.user_id', $user->id)
                    ->orderBy('teams.name')
                    ->pluck('teams.name')
                    ->all());

                return $user;
            })
            ->when($status !== '', fn ($collection) => $collection->filter(fn (User $user): bool => $user->supervision_status === $status))
            ->values();

        $allAgents = User::query()
            ->where('company_id', $companyId)
            ->whereIn('user_type', ['agent', 'supervisor'])
            ->with('currentAgentStatus')
            ->get()
            ->map(fn (User $user): string => $user->currentAgentStatus?->status ?? 'offline');

        return view('tenant.supervision.index', [
            'agents' => $agents,
            'teams' => Team::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'selectedStatus' => $status,
            'selectedTeamId' => $teamId,
            'metrics' => [
                'available' => $allAgents->filter(fn (string $value): bool => $value === 'available')->count(),
                'paused' => $allAgents->filter(fn (string $value): bool => $value === 'paused')->count(),
                'in_call' => $allAgents->filter(fn (string $value): bool => $value === 'in_call')->count(),
                'offline' => $allAgents->filter(fn (string $value): bool => $value === 'offline')->count(),
                'active_calls' => Call::query()->where('company_id', $companyId)->where('status', 'in_progress')->count(),
            ],
        ]);
    }
}
