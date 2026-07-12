<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Tenant\PermissionMatrix;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        return view('tenant.roles.index', [
            'roles' => Role::query()
                ->where('company_id', $companyId)
                ->with('permissions')
                ->withCount('users')
                ->latest()
                ->paginate(12),
            'modules' => PermissionMatrix::modules(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate($this->rules());

        $role = Role::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'is_admin' => (bool) ($data['is_admin'] ?? false),
        ]);

        $this->syncPermissions($role, $data['permissions'] ?? []);

        return redirect()->route('tenant.roles.index')->with('status', 'Perfil criado com sucesso.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($role->company_id === $request->user()->company_id, 403);

        $data = $request->validate($this->rules());

        $role->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'is_admin' => (bool) ($data['is_admin'] ?? false),
        ]);

        $this->syncPermissions($role, $data['permissions'] ?? []);

        return redirect()->route('tenant.roles.index')->with('status', 'Perfil atualizado com sucesso.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_unless($role->company_id === $request->user()->company_id, 403);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Remova os usuarios vinculados antes de excluir o perfil.',
            ]);
        }

        $role->delete();

        return redirect()->route('tenant.roles.index')->with('status', 'Perfil excluido com sucesso.');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_admin' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'key')],
        ];
    }

    private function syncPermissions(Role $role, array $permissionKeys): void
    {
        $role->permissions()->sync(
            Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all()
        );
    }
}
