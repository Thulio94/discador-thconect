<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyLicense;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaaSDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_saas_tenant_relations_license_limits_team_memberships_and_permissions(): void
    {
        $company = Company::create([
            'name' => 'THCONECT Demo',
            'document' => '12345678000199',
        ]);

        $company->licenses()->create([
            'status' => 'expired',
            'starts_at' => now()->subMonths(2)->toDateString(),
            'ends_at' => now()->subMonth()->toDateString(),
        ]);

        $activeLicense = CompanyLicense::create([
            'company_id' => $company->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_calls_per_available_agent' => 3,
            'max_concurrent_agents' => 12,
            'max_concurrent_calls' => 36,
            'predictive_enabled' => true,
        ]);

        $permission = Permission::create([
            'key' => 'campaigns.view',
            'name' => 'View campaigns',
            'module' => 'campaigns',
        ]);

        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Supervisor',
            'slug' => 'supervisor',
        ]);

        $role->permissions()->attach($permission);

        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'agent',
            'extension' => '1001',
        ]);

        $supervisor = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'supervisor',
            'extension' => '2001',
        ]);

        $team = Team::create([
            'company_id' => $company->id,
            'name' => 'Sales',
            'slug' => 'sales',
        ]);

        $team->users()->attach($agent, ['membership_type' => 'agent']);
        $team->users()->attach($supervisor, ['membership_type' => 'supervisor']);

        $company->appNotifications()->create([
            'user_id' => $agent->id,
            'title' => 'Welcome',
            'message' => 'Your tenant is ready.',
        ]);

        $this->assertSame($activeLicense->id, $company->fresh()->license->id);
        $this->assertSame(3, $company->license->max_calls_per_available_agent);
        $this->assertTrue($company->license->predictive_enabled);
        $this->assertTrue($agent->fresh()->teams->contains($team));
        $this->assertSame('agent', $agent->teams->first()->pivot->membership_type);
        $this->assertTrue($team->fresh()->agents->contains($agent));
        $this->assertTrue($team->supervisors->contains($supervisor));
        $this->assertTrue($supervisor->hasPermission('campaigns.view'));
        $this->assertFalse($agent->hasPermission('campaigns.delete'));
        $this->assertDatabaseHas('app_notifications', [
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'type' => 'system',
            'severity' => 'info',
        ]);
    }

    public function test_permissions_do_not_cross_tenant_boundaries(): void
    {
        $company = Company::create(['name' => 'Tenant A']);
        $otherCompany = Company::create(['name' => 'Tenant B']);

        $permission = Permission::create([
            'key' => 'users.delete',
            'name' => 'Delete users',
            'module' => 'users',
        ]);

        $otherRole = Role::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Admin',
            'slug' => 'other-admin',
        ]);

        $otherRole->permissions()->attach($permission);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $otherRole->id,
            'user_type' => 'company_admin',
            'is_super_admin' => false,
        ]);

        $this->assertFalse($user->hasPermission('users.delete'));
    }

    public function test_super_admin_user_type_does_not_bypass_permissions_without_flag(): void
    {
        $user = User::factory()->create([
            'user_type' => 'super_admin',
            'is_super_admin' => false,
        ]);

        $this->assertFalse($user->hasPermission('users.delete'));
    }

    public function test_team_relationships_do_not_expose_users_from_other_companies(): void
    {
        $company = Company::create(['name' => 'Tenant A']);
        $otherCompany = Company::create(['name' => 'Tenant B']);

        $team = Team::create([
            'company_id' => $company->id,
            'name' => 'Sales',
            'slug' => 'sales',
        ]);

        $sameTenantAgent = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'agent',
            'extension' => '1001',
        ]);

        $otherTenantAgent = User::factory()->create([
            'company_id' => $otherCompany->id,
            'user_type' => 'agent',
            'extension' => '1001',
        ]);

        $team->users()->attach($sameTenantAgent, ['membership_type' => 'agent']);

        $this->assertTrue($team->fresh()->agents->contains($sameTenantAgent));

        $this->expectException(QueryException::class);

        $team->users()->attach($otherTenantAgent, ['membership_type' => 'agent']);
    }

    public function test_company_license_returns_current_active_license(): void
    {
        $company = Company::create(['name' => 'Tenant A']);

        $activeLicense = $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_calls_per_available_agent' => 4,
        ]);

        $company->licenses()->create([
            'status' => 'expired',
            'starts_at' => now()->addMonth()->toDateString(),
            'ends_at' => now()->addMonths(2)->toDateString(),
            'max_calls_per_available_agent' => 9,
        ]);

        $this->assertSame($activeLicense->id, $company->fresh()->license->id);
        $this->assertSame(4, $company->license->max_calls_per_available_agent);
    }

    public function test_global_role_slugs_are_unique_even_without_company_id(): void
    {
        Role::create([
            'name' => 'Global Admin',
            'slug' => 'global-admin',
        ]);

        $this->expectException(ValidationException::class);

        Role::create([
            'name' => 'Global Admin Copy',
            'slug' => 'global-admin',
        ]);
    }

    public function test_global_role_slug_uniqueness_is_enforced_by_database(): void
    {
        DB::table('roles')->insert([
            'name' => 'Global Admin',
            'slug' => 'global-admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('roles')->insert([
            'name' => 'Global Admin Copy',
            'slug' => 'global-admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cross_tenant_team_membership_is_rejected_by_database(): void
    {
        $company = Company::create(['name' => 'Tenant A']);
        $otherCompany = Company::create(['name' => 'Tenant B']);

        $team = Team::create([
            'company_id' => $company->id,
            'name' => 'Sales',
            'slug' => 'sales',
        ]);

        $otherTenantAgent = User::factory()->create([
            'company_id' => $otherCompany->id,
            'user_type' => 'agent',
            'extension' => '1001',
        ]);

        $this->expectException(QueryException::class);

        DB::table('team_user')->insert([
            'company_id' => $company->id,
            'team_id' => $team->id,
            'user_id' => $otherTenantAgent->id,
            'membership_type' => 'agent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
