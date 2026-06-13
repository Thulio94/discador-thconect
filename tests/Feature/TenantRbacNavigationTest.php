<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ContactList;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantRbacNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_agent_navigation_contains_only_authorized_tenant_modules(): void
    {
        $agent = $this->tenantUser('agent', [
            'dashboard.read',
            'agent.access',
            'notifications.read',
        ]);

        $this->actingAs($agent)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('tenant.agent.index'), false)
            ->assertDontSee(route('tenant.users.index'), false)
            ->assertDontSee(route('tenant.supervision.index'), false)
            ->assertDontSee(route('tenant.settings.index'), false);
    }

    public function test_report_reader_does_not_see_export_or_schedule_commands(): void
    {
        $reader = $this->tenantUser('supervisor', ['reports.read']);

        $this->actingAs($reader)
            ->get(route('tenant.reports.index'))
            ->assertOk()
            ->assertDontSee(route('tenant.reports.export'), false)
            ->assertDontSee('Novo agendamento');
    }

    public function test_list_creator_sees_create_but_not_update_or_delete_commands(): void
    {
        $creator = $this->tenantUser('supervisor', ['lists.read', 'lists.create']);
        ContactList::create([
            'company_id' => $creator->company_id,
            'name' => 'Lista protegida',
            'status' => 'active',
        ]);

        $this->actingAs($creator)
            ->get(route('tenant.lists.index'))
            ->assertOk()
            ->assertSee('Nova lista')
            ->assertDontSee('Editar')
            ->assertDontSee('Inativar');
    }

    public function test_tenant_admin_sees_all_tenant_navigation_items(): void
    {
        $admin = $this->tenantUser('company_admin', isAdmin: true);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('tenant.users.index'), false)
            ->assertSee(route('tenant.campaigns.index'), false)
            ->assertSee(route('tenant.agent.index'), false)
            ->assertSee(route('tenant.supervision.index'), false)
            ->assertSee(route('tenant.settings.index'), false);
    }

    public function test_super_admin_navigation_has_no_tenant_modules(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('super-admin.companies.index'), false)
            ->assertDontSee(route('tenant.users.index'), false)
            ->assertDontSee(route('tenant.agent.index'), false);
    }

    private function tenantUser(
        string $userType,
        array $permissionKeys = [],
        bool $isAdmin = false,
    ): User {
        $company = Company::create(['name' => fake()->unique()->company()]);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        $role = Role::create([
            'company_id' => $company->id,
            'name' => fake()->unique()->jobTitle(),
            'slug' => fake()->unique()->slug(),
            'is_admin' => $isAdmin,
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('key', $permissionKeys)->pluck('id')
        );

        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => $userType,
            'status' => 'active',
            'extension' => fake()->unique()->numerify('1###'),
            'email_verified_at' => now(),
        ]);
    }
}
