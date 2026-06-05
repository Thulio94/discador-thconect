<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyLicense;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertTrue($agent->hasPermission('campaigns.view'));
        $this->assertFalse($agent->hasPermission('campaigns.delete'));
        $this->assertDatabaseHas('app_notifications', [
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'type' => 'system',
            'severity' => 'info',
        ]);
    }
}
