<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        return view('tenant.users.index', [
            'users' => User::query()->where('company_id', $companyId)->with(['role', 'teams'])->latest()->paginate(12),
            'roles' => Role::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'teams' => Team::query()->where('company_id', $companyId)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $companyId = $company->id;

        $data = $request->validate($this->rules($companyId));

        if ($data['status'] === 'active' && $company->users()->where('status', 'active')->count() >= $company->license->max_users) {
            throw ValidationException::withMessages([
                'status' => 'Limite de usuarios da licenca atingido.',
            ]);
        }

        $user = User::query()->create([
            'company_id' => $companyId,
            'role_id' => $data['role_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make('password'),
            'user_type' => $data['user_type'],
            'extension' => $data['extension'],
            'status' => $data['status'],
        ]);

        $this->syncTeams($user, $data['team_ids'] ?? []);

        return redirect()
            ->route('tenant.users.index')
            ->with('status', 'Usuario criado com sucesso.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->company_id === $request->user()->company_id, 403);

        $companyId = $request->user()->company_id;
        $data = $request->validate($this->rules($companyId, $user));

        $user->update([
            'role_id' => $data['role_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'user_type' => $data['user_type'],
            'extension' => $data['extension'],
            'status' => $data['status'],
        ]);

        $this->syncTeams($user, $data['team_ids'] ?? []);

        return redirect()
            ->route('tenant.users.index')
            ->with('status', 'Usuario atualizado com sucesso.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->company_id === $request->user()->company_id, 403);

        $user->update(['status' => 'inactive']);

        return redirect()
            ->route('tenant.users.index')
            ->with('status', 'Usuario inativado com sucesso.');
    }

    private function rules(int $companyId, ?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'user_type' => ['required', Rule::in(['agent', 'supervisor', 'company_admin'])],
            'extension' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'extension')->where('company_id', $companyId)->ignore($user),
            ],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('company_id', $companyId)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'team_ids' => ['array'],
            'team_ids.*' => [Rule::exists('teams', 'id')->where('company_id', $companyId)],
        ];
    }

    private function syncTeams(User $user, array $teamIds): void
    {
        if ($user->user_type === 'company_admin') {
            $user->teams()->detach();

            return;
        }

        $user->teams()->sync(
            collect($teamIds)
                ->mapWithKeys(fn ($teamId): array => [(int) $teamId => ['membership_type' => $user->user_type]])
                ->all()
        );
    }
}
