<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        return view('tenant.teams.index', [
            'teams' => Team::query()->where('company_id', $companyId)->with(['agents', 'supervisors'])->latest()->paginate(12),
            'agents' => User::query()->where('company_id', $companyId)->where('user_type', 'agent')->orderBy('name')->get(),
            'supervisors' => User::query()->where('company_id', $companyId)->where('user_type', 'supervisor')->orderBy('name')->get(),
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
        abort_unless($team->company_id === $request->user()->company_id, 403);

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
        abort_unless($team->company_id === $request->user()->company_id, 403);

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

        $team->users()->sync($sync);
    }
}
