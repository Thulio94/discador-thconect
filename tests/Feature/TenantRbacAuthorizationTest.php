<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantRbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_every_tenant_route_has_the_expected_permission_middleware(): void
    {
        foreach ($this->routePermissions() as $routeName => $permission) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route [{$routeName}] is not registered.");
            $this->assertContains(
                "can:tenant.{$permission}",
                $route->gatherMiddleware(),
                "Route [{$routeName}] is missing permission [{$permission}].",
            );
        }
    }

    public function test_direct_url_access_requires_the_matching_permission(): void
    {
        $agent = $this->tenantUser('agent', ['agent.access']);
        $reader = $this->tenantUser('supervisor', ['teams.read']);

        $this->actingAs($agent)
            ->get(route('tenant.teams.index'))
            ->assertForbidden();

        $this->actingAs($reader)
            ->get(route('tenant.teams.index'))
            ->assertOk();

        $this->actingAs($reader)
            ->post(route('tenant.teams.store'), [])
            ->assertForbidden();
    }

    public function test_tenant_dashboard_requires_permission_but_super_admin_dashboard_remains_available(): void
    {
        $tenantWithoutDashboard = $this->tenantUser('supervisor');
        $tenantWithDashboard = $this->tenantUser('supervisor', ['dashboard.read']);
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
        ]);

        $this->actingAs($tenantWithoutDashboard)
            ->get(route('dashboard'))
            ->assertForbidden();

        $this->actingAs($tenantWithDashboard)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk();
    }

    private function tenantUser(string $userType, array $permissionKeys = []): User
    {
        $this->seed(PermissionSeeder::class);

        $company = Company::create(['name' => fake()->unique()->company()]);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_users' => 20,
        ]);

        $role = Role::create([
            'company_id' => $company->id,
            'name' => fake()->unique()->jobTitle(),
            'slug' => fake()->unique()->slug(),
            'is_admin' => false,
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

    private function routePermissions(): array
    {
        return [
            'tenant.users.index' => 'users.read',
            'tenant.users.store' => 'users.create',
            'tenant.users.update' => 'users.update',
            'tenant.users.destroy' => 'users.delete',
            'tenant.teams.index' => 'teams.read',
            'tenant.teams.store' => 'teams.create',
            'tenant.teams.update' => 'teams.update',
            'tenant.teams.destroy' => 'teams.delete',
            'tenant.roles.index' => 'roles.read',
            'tenant.roles.store' => 'roles.create',
            'tenant.roles.update' => 'roles.update',
            'tenant.roles.destroy' => 'roles.delete',
            'tenant.lists.index' => 'lists.read',
            'tenant.lists.store' => 'lists.create',
            'tenant.lists.update' => 'lists.update',
            'tenant.lists.destroy' => 'lists.delete',
            'tenant.campaigns.index' => 'campaigns.read',
            'tenant.campaigns.store' => 'campaigns.create',
            'tenant.campaigns.update' => 'campaigns.update',
            'tenant.campaigns.destroy' => 'campaigns.delete',
            'tenant.campaigns.duplicate' => 'campaigns.create',
            'tenant.campaigns.start' => 'campaigns.operate',
            'tenant.campaigns.pause' => 'campaigns.operate',
            'tenant.campaigns.restart' => 'campaigns.operate',
            'tenant.campaigns.recycle' => 'campaigns.operate',
            'tenant.campaigns.dial' => 'campaigns.operate',
            'tenant.agent.index' => 'agent.access',
            'tenant.agent.status' => 'agent.access',
            'tenant.agent.pause' => 'agent.access',
            'tenant.agent.calls.store' => 'agent.access',
            'tenant.agent.calls.end' => 'agent.access',
            'tenant.supervision.index' => 'supervision.read',
            'tenant.supervision.poll' => 'supervision.read',
            'tenant.supervision.monitor' => 'supervision.operate',
            'tenant.reports.index' => 'reports.read',
            'tenant.reports.export' => 'reports.export',
            'tenant.reports.export-xlsx' => 'reports.export',
            'tenant.reports.export-pdf' => 'reports.export',
            'tenant.reports.schedules.store' => 'reports.schedules',
            'tenant.reports.schedules.update' => 'reports.schedules',
            'tenant.reports.schedules.destroy' => 'reports.schedules',
            'tenant.reports.schedules.status' => 'reports.schedules',
            'tenant.reports.schedules.run' => 'reports.schedules',
            'tenant.telephony.index' => 'telephony.read',
            'tenant.telephony.update' => 'telephony.update',
            'tenant.settings.index' => 'settings.read',
            'tenant.settings.operational-parameters.update' => 'settings.update',
            'tenant.settings.pause-reasons.store' => 'settings.create',
            'tenant.settings.pause-reasons.update' => 'settings.update',
            'tenant.settings.pause-reasons.destroy' => 'settings.delete',
            'tenant.settings.dispositions.store' => 'settings.create',
            'tenant.settings.dispositions.update' => 'settings.update',
            'tenant.settings.dispositions.destroy' => 'settings.delete',
            'tenant.settings.blacklist.store' => 'settings.create',
            'tenant.settings.blacklist.update' => 'settings.update',
            'tenant.settings.blacklist.destroy' => 'settings.delete',
            'tenant.integrations.index' => 'integrations.read',
            'tenant.notifications.poll' => 'notifications.read',
            'tenant.notifications.preferences.update' => 'notifications.update',
            'tenant.notifications.read-all' => 'notifications.update',
            'tenant.notifications.read' => 'notifications.update',
        ];
    }
}
