<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTeamRoleCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_create_team_with_agents_and_supervisors(): void
    {
        [$admin, $agent, $supervisor] = $this->tenantUsers();

        $this->actingAs($admin)
            ->post(route('tenant.teams.store'), [
                'name' => 'Equipe Vendas',
                'status' => 'active',
                'agent_ids' => [$agent->id],
                'supervisor_ids' => [$supervisor->id],
            ])
            ->assertRedirect(route('tenant.teams.index'))
            ->assertSessionHas('status');

        $team = Team::where('name', 'Equipe Vendas')->sole();

        $this->assertTrue($team->agents->contains($agent));
        $this->assertTrue($team->supervisors->contains($supervisor));
    }

    public function test_tenant_admin_can_create_role_with_permissions(): void
    {
        [$admin] = $this->tenantUsers();
        $this->seed(PermissionSeeder::class);

        $this->actingAs($admin)
            ->post(route('tenant.roles.store'), [
                'name' => 'Operacao',
                'permissions' => ['users.read', 'teams.read'],
            ])
            ->assertRedirect(route('tenant.roles.index'))
            ->assertSessionHas('status');

        $role = Role::where('company_id', $admin->company_id)->where('slug', 'operacao')->sole();

        $this->assertTrue($role->permissions->contains(fn (Permission $permission): bool => $permission->key === 'users.read'));
        $this->assertTrue($role->permissions->contains(fn (Permission $permission): bool => $permission->key === 'teams.read'));
    }

    private function tenantUsers(): array
    {
        $company = Company::create(['name' => 'Tenant A']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Administrador',
            'slug' => 'administrador',
            'is_admin' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'company_admin',
            'extension' => '1000',
            'status' => 'active',
        ]);
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'agent',
            'extension' => '2001',
            'status' => 'active',
        ]);
        $supervisor = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'supervisor',
            'extension' => '3001',
            'status' => 'active',
        ]);

        return [$admin, $agent, $supervisor];
    }
}
