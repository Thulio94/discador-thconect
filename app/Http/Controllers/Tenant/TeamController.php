<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'member_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
        ]);

        return view('tenant.teams.index', [
            'teams' => Team::query()
                ->where('company_id', $companyId)
                ->with('members')
                ->addSelect([
                    'agents_count' => DB::table('team_user')
                        ->selectRaw('count(*)')
                        ->whereColumn('team_user.team_id', 'teams.id')
                        ->where('team_user.company_id', $companyId)
                        ->where('team_user.membership_type', 'agent'),
                    'supervisors_count' => DB::table('team_user')
                        ->selectRaw('count(*)')
                        ->whereColumn('team_user.team_id', 'teams.id')
                        ->where('team_user.company_id', $companyId)
                        ->where('team_user.membership_type', 'supervisor'),
                ])
                ->when($filters['q'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['member_id'] ?? null, fn ($query, $memberId) => $query->whereIn('teams.id', function ($memberQuery) use ($companyId, $memberId): void {
                    $memberQuery
                        ->select('team_user.team_id')
                        ->from('team_user')
                        ->where('team_user.company_id', $companyId)
                        ->where('team_user.user_id', $memberId);
                }))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
            'agents' => User::query()->where('company_id', $companyId)->where('user_type', 'agent')->orderBy('name')->get(),
            'supervisors' => User::query()->where('company_id', $companyId)->where('user_type', 'supervisor')->orderBy('name')->get(),
            'members' => User::query()->where('company_id', $companyId)->whereIn('user_type', ['agent', 'supervisor'])->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate($this->rules($companyId));

        $team = Team::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'status' => $data['status'],
        ]);

        $this->syncMembers($team, $data['agent_ids'] ?? [], $data['supervisor_ids'] ?? []);

        return redirect()->route('tenant.teams.index')->with('status', 'Equipe criada com sucesso.');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        abort_unless($team->company_id === $request->user()->company_id, 404);

        $data = $request->validate($this->rules($team->company_id, $team));

        $team->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'status' => $data['status'],
        ]);

        $this->syncMembers($team, $data['agent_ids'] ?? [], $data['supervisor_ids'] ?? []);

        return redirect()->route('tenant.teams.index')->with('status', 'Equipe atualizada com sucesso.');
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        abort_unless($team->company_id === $request->user()->company_id, 404);

        $team->update(['status' => 'inactive']);

        return redirect()->route('tenant.teams.index')->with('status', 'Equipe inativada com sucesso.');
    }

    private function rules(int $companyId, ?Team $team = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('teams')->where('company_id', $companyId)->ignore($team)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'agent_ids' => ['array'],
            'agent_ids.*' => [Rule::exists('users', 'id')->where('company_id', $companyId)->where('user_type', 'agent')],
            'supervisor_ids' => ['array'],
            'supervisor_ids.*' => [Rule::exists('users', 'id')->where('company_id', $companyId)->where('user_type', 'supervisor')],
        ];
    }

    private function syncMembers(Team $team, array $agentIds, array $supervisorIds): void
    {
        $sync = [];

        foreach ($agentIds as $id) {
            $sync[(int) $id] = ['membership_type' => 'agent'];
        }

        foreach ($supervisorIds as $id) {
            $sync[(int) $id] = ['membership_type' => 'supervisor'];
        }

        DB::transaction(function () use ($team, $agentIds, $sync): void {
            // An agent has one operational team. Supervisors may belong to several teams.
            if ($agentIds !== []) {
                DB::table('team_user')
                    ->where('company_id', $team->company_id)
                    ->whereIn('user_id', $agentIds)
                    ->where('team_id', '!=', $team->id)
                    ->delete();
            }

            $team->users()->sync($sync);
        });
    }
}
