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

    public function test_tenant_admin_can_update_and_inactivate_team(): void
    {
        [$admin, $agent, $supervisor] = $this->tenantUsers();
        $team = Team::create([
            'company_id' => $admin->company_id,
            'name' => 'Equipe Original',
            'slug' => 'equipe-original',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('tenant.teams.update', $team), [
                'name' => 'Equipe Atualizada',
                'status' => 'active',
                'agent_ids' => [$agent->id],
                'supervisor_ids' => [$supervisor->id],
            ])
            ->assertRedirect(route('tenant.teams.index'));

        $this->assertSame('Equipe Atualizada', $team->fresh()->name);
        $this->assertCount(2, $team->fresh()->members);

        $this->actingAs($admin)
            ->delete(route('tenant.teams.destroy', $team))
            ->assertRedirect(route('tenant.teams.index'));

        $this->assertSame('inactive', $team->fresh()->status);
    }

    public function test_tenant_admin_can_update_and_delete_unused_role_but_not_used_role(): void
    {
        [$admin] = $this->tenantUsers();
        $this->seed(PermissionSeeder::class);
        $role = Role::create([
            'company_id' => $admin->company_id,
            'name' => 'Perfil Temporario',
            'slug' => 'perfil-temporario',
        ]);

        $this->actingAs($admin)
            ->put(route('tenant.roles.update', $role), [
                'name' => 'Perfil Revisado',
                'permissions' => ['users.read'],
            ])
            ->assertRedirect(route('tenant.roles.index'));

        $this->assertSame('Perfil Revisado', $role->fresh()->name);

        $this->actingAs($admin)
            ->delete(route('tenant.roles.destroy', $admin->role))
            ->assertSessionHasErrors('role');

        $this->actingAs($admin)
            ->delete(route('tenant.roles.destroy', $role))
            ->assertRedirect(route('tenant.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_team_index_filters_by_search_status_and_member_with_tenant_scope(): void
    {
        [$admin, $agent, $supervisor] = $this->tenantUsers();
        [, $otherAgent] = $this->tenantUsers();
        $matching = Team::create([
            'company_id' => $admin->company_id,
            'name' => 'Equipe Retencao Premium',
            'slug' => 'equipe-retencao-premium',
            'status' => 'active',
        ]);
        $matching->users()->attach($agent, [
            'company_id' => $admin->company_id,
            'membership_type' => 'agent',
        ]);
        Team::create([
            'company_id' => $admin->company_id,
            'name' => 'Equipe Inativa',
            'slug' => 'equipe-inativa',
            'status' => 'inactive',
        ])->users()->attach($supervisor, [
            'company_id' => $admin->company_id,
            'membership_type' => 'supervisor',
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.teams.index', [
                'q' => 'Retencao',
                'status' => 'active',
                'member_id' => $agent->id,
            ]))
            ->assertOk()
            ->assertSee('Equipe Retencao Premium')
            ->assertSee('team-view-'.$matching->id)
            ->assertSee('team-edit-'.$matching->id)
            ->assertSee('team-delete-'.$matching->id)
            ->assertDontSee('Equipe Inativa');

        $this->actingAs($admin)
            ->get(route('tenant.teams.index', ['member_id' => $otherAgent->id]))
            ->assertSessionHasErrors('member_id');
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
