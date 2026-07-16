<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenant\PermissionMatrix;
use Database\Seeders\DemoTenantSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RbacSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_matrix_registers_permissions_and_gates(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->assertContains('users.create', PermissionMatrix::keys());
        $this->assertContains('campaigns.operate', PermissionMatrix::keys());
        $this->assertContains('agent.access', PermissionMatrix::keys());
        $this->assertContains('reports.export', PermissionMatrix::keys());
        $this->assertContains('reports.schedules', PermissionMatrix::keys());
        $this->assertContains('audit.read', PermissionMatrix::keys());
        $this->assertContains('settings.delete', PermissionMatrix::keys());
        $this->assertContains('integrations.read', PermissionMatrix::keys());
        $this->assertDatabaseHas('permissions', [
            'key' => 'users.create',
            'module' => 'users',
        ]);
        $this->assertDatabaseHas('permissions', [
            'key' => 'audit.read',
            'module' => 'audit',
        ]);

        $company = Company::create(['name' => 'Tenant A']);
        $role = $company->roles()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_admin' => true,
        ]);
        $role->permissions()->attach(Permission::where('key', 'users.create')->sole());

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'company_admin',
            'extension' => '1100',
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('tenant.users.create'));
        $this->assertTrue(Gate::forUser($user)->allows('tenant.users.delete'));
        $this->assertTrue(Gate::forUser($user)->allows('tenant.settings.delete'));
    }

    public function test_user_types_and_role_tenant_limit_permissions(): void
    {
        $this->seed(PermissionSeeder::class);

        $company = Company::create(['name' => 'Tenant A']);
        $otherCompany = Company::create(['name' => 'Tenant B']);

        $agentRole = $this->role($company, 'Agente', ['users.read', 'agent.access']);
        $supervisorRole = $this->role($company, 'Supervisor', ['agent.access', 'supervision.read', 'supervision.operate']);
        $adminRole = $company->roles()->create([
            'name' => 'Administrador A',
            'slug' => 'administrador-a',
            'is_admin' => true,
        ]);
        $otherAdminRole = $otherCompany->roles()->create([
            'name' => 'Administrador B',
            'slug' => 'administrador-b',
            'is_admin' => true,
        ]);

        $agent = $this->tenantUser($company, $agentRole, 'agent');
        $agentWithAdminRole = $this->tenantUser($company, $adminRole, 'agent');
        $supervisor = $this->tenantUser($company, $supervisorRole, 'supervisor');
        $invalidAdmin = $this->tenantUser($company, $otherAdminRole, 'company_admin');
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
        ]);

        $this->assertFalse(Gate::forUser($agent)->allows('tenant.users.read'));
        $this->assertTrue(Gate::forUser($agent)->allows('tenant.agent.access'));
        $this->assertFalse(Gate::forUser($agentWithAdminRole)->allows('tenant.users.read'));
        $this->assertTrue(Gate::forUser($supervisor)->allows('tenant.agent.access'));
        $this->assertTrue(Gate::forUser($supervisor)->allows('tenant.supervision.read'));
        $this->assertTrue(Gate::forUser($supervisor)->allows('tenant.supervision.operate'));
        $this->assertFalse(Gate::forUser($invalidAdmin)->allows('tenant.users.read'));
        $this->assertFalse(Gate::forUser($superAdmin)->allows('tenant.users.read'));
    }

    public function test_super_admin_seeder_creates_default_admin(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $admin = User::where('email', 'admin@discador.local')->sole();

        $this->assertTrue($admin->is_super_admin);
        $this->assertSame('super_admin', $admin->user_type);
        $this->assertSame('1000', $admin->extension);
        $this->assertSame('active', $admin->status);
        $this->assertTrue(Gate::forUser($admin)->allows('super-admin'));
    }

    public function test_demo_tenant_seeder_creates_license_roles_users_and_team(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(DemoTenantSeeder::class);

        $company = Company::where('name', 'THCONECT Demo')->sole();
        $adminRole = $company->roles()->where('slug', 'administrador')->with('permissions')->sole();
        $supervisorRole = $company->roles()->where('slug', 'supervisor')->with('permissions')->sole();
        $agentRole = $company->roles()->where('slug', 'agente')->with('permissions')->sole();
        $supervisor = $company->users()->where('email', 'supervisor.demo@discador.local')->sole();
        $agent = $company->users()->where('email', 'agente1.demo@discador.local')->sole();

        $this->assertSame(2, $company->license->max_calls_per_available_agent);
        $this->assertSame(4, $company->users()->count());
        $this->assertSame(1, $company->teams()->count());
        $this->assertTrue($adminRole->is_admin);
        $this->assertFalse($supervisorRole->is_admin);
        $this->assertFalse($agentRole->is_admin);
        $this->assertTrue($supervisorRole->permissions->contains('key', 'supervision.read'));
        $this->assertTrue($supervisorRole->permissions->contains('key', 'supervision.operate'));
        $this->assertTrue($supervisorRole->permissions->contains('key', 'agent.access'));
        $this->assertTrue($agentRole->permissions->contains('key', 'agent.access'));
        $this->assertFalse($agentRole->permissions->contains('key', 'users.read'));
        $this->assertSame($supervisorRole->id, $supervisor->role_id);
        $this->assertSame($agentRole->id, $agent->role_id);
        $this->assertSame(2, $company->teams()->sole()->agents()->count());
        $this->assertSame(1, $company->teams()->sole()->supervisors()->count());
    }

    private function role(Company $company, string $name, array $permissionKeys): Role
    {
        $role = $company->roles()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'is_admin' => false,
        ]);

        $role->permissions()->sync(
            Permission::query()->whereIn('key', $permissionKeys)->pluck('id')
        );

        return $role;
    }

    private function tenantUser(Company $company, Role $role, string $userType): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => $userType,
            'extension' => fake()->unique()->numerify('1###'),
        ]);
    }
}
