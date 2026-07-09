<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantUserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_extension_is_required_for_tenant_users(): void
    {
        [$admin, $role] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->post(route('tenant.users.store'), [
                'name' => 'Agente',
                'email' => 'agente@demo.test',
                'user_type' => 'agent',
                'role_id' => $role->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('extension');
    }

    public function test_tenant_admin_can_create_agent_with_team_membership(): void
    {
        [$admin, $role, $team] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->post(route('tenant.users.store'), [
                'name' => 'Agente',
                'email' => 'agente@demo.test',
                'phone' => '31999990000',
                'user_type' => 'agent',
                'extension' => '2001',
                'role_id' => $role->id,
                'status' => 'active',
                'team_ids' => [$team->id],
            ])
            ->assertRedirect(route('tenant.users.index'))
            ->assertSessionHas('status');

        $agent = User::where('email', 'agente@demo.test')->sole();

        $this->assertSame($admin->company_id, $agent->company_id);
        $this->assertSame('2001', $agent->extension);
        $this->assertSame('agent', $agent->teams()->first()->pivot->membership_type);
    }

    public function test_license_max_users_is_enforced_for_active_users(): void
    {
        [$admin, $role] = $this->tenantAdmin(maxUsers: 1);

        $this->actingAs($admin)
            ->post(route('tenant.users.store'), [
                'name' => 'Agente',
                'email' => 'agente@demo.test',
                'user_type' => 'agent',
                'extension' => '2001',
                'role_id' => $role->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_extension_is_unique_inside_company_only(): void
    {
        [$admin, $role] = $this->tenantAdmin();

        User::factory()->create([
            'company_id' => $admin->company_id,
            'role_id' => $role->id,
            'extension' => '2001',
        ]);

        $this->actingAs($admin)
            ->post(route('tenant.users.store'), [
                'name' => 'Agente',
                'email' => 'agente@demo.test',
                'user_type' => 'agent',
                'extension' => '2001',
                'role_id' => $role->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('extension');
    }

    public function test_user_creation_and_deactivation_are_audited_without_noop_events(): void
    {
        [$admin, $role, $team] = $this->tenantAdmin();
        AuditLog::query()->delete();

        $this->actingAs($admin)
            ->post(route('tenant.users.store'), [
                'name' => 'Agente Auditado',
                'email' => 'auditado@demo.test',
                'user_type' => 'agent',
                'extension' => '2099',
                'role_id' => $role->id,
                'status' => 'active',
                'team_ids' => [$team->id],
            ])
            ->assertRedirect(route('tenant.users.index'));

        $agent = User::where('email', 'auditado@demo.test')->sole();
        $created = AuditLog::where('event', 'user.created')->where('subject_id', $agent->id)->sole();

        $this->assertSame($admin->id, $created->actor_user_id);
        $this->assertArrayNotHasKey('password', $created->new_values);

        $this->actingAs($admin)
            ->put(route('tenant.users.update', $agent), [
                'name' => $agent->name,
                'email' => $agent->email,
                'user_type' => $agent->user_type,
                'extension' => $agent->extension,
                'role_id' => $role->id,
                'status' => 'inactive',
                'team_ids' => [$team->id],
            ])
            ->assertRedirect(route('tenant.users.index'));

        $deactivated = AuditLog::where('event', 'user.deactivated')->where('subject_id', $agent->id)->sole();

        $this->assertSame(['status' => 'active'], $deactivated->old_values);
        $this->assertSame(['status' => 'inactive'], $deactivated->new_values);

        $count = AuditLog::count();
        $agent->fresh()->save();

        $this->assertSame($count, AuditLog::count());
    }

    private function tenantAdmin(int $maxUsers = 10): array
    {
        $company = Company::create(['name' => 'Tenant A']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_users' => $maxUsers,
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Administrador',
            'slug' => 'administrador',
            'is_admin' => true,
        ]);
        $team = Team::create([
            'company_id' => $company->id,
            'name' => 'Equipe',
            'slug' => 'equipe',
        ]);
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'company_admin',
            'extension' => '1000',
            'status' => 'active',
        ]);

        return [$admin, $role, $team];
    }
}
