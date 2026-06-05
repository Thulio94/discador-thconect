<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
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
        $this->assertContains('integrations.read', PermissionMatrix::keys());
        $this->assertDatabaseHas('permissions', [
            'key' => 'users.create',
            'module' => 'users',
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
        $this->assertFalse(Gate::forUser($user)->allows('tenant.users.delete'));
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

        $this->assertSame(2, $company->license->max_calls_per_available_agent);
        $this->assertSame(4, $company->users()->count());
        $this->assertSame(1, $company->teams()->count());
        $this->assertTrue($company->roles()->where('slug', 'administrador')->sole()->is_admin);
        $this->assertSame(2, $company->teams()->sole()->agents()->count());
        $this->assertSame(1, $company->teams()->sole()->supervisors()->count());
    }
}
