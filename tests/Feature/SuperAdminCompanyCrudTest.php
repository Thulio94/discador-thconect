<?php

namespace Tests\Feature;

use App\Models\AgentStatus;
use App\Models\AuditLog;
use App\Models\Call;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminCompanyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_company_with_license_limits(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('super-admin.companies.store'), $this->payload([
                'name' => 'Nova Empresa',
                'max_calls_per_available_agent' => 5,
            ]))
            ->assertRedirect(route('super-admin.companies.index'))
            ->assertSessionHas('status');

        $company = Company::where('name', 'Nova Empresa')->sole();

        $this->assertSame(5, $company->license->max_calls_per_available_agent);
        $this->assertSame(10, $company->license->max_concurrent_agents);
        $this->assertSame('active', $company->status);
    }

    public function test_super_admin_can_update_company_license(): void
    {
        $company = Company::create(['name' => 'Empresa Antiga']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_calls_per_available_agent' => 2,
        ]);

        $this->actingAs($this->superAdmin())
            ->put(route('super-admin.companies.update', $company), $this->payload([
                'name' => 'Empresa Atualizada',
                'max_calls_per_available_agent' => 7,
                'max_concurrent_agents' => 12,
                'predictive_enabled' => '1',
                'amd_enabled' => '1',
            ]))
            ->assertRedirect(route('super-admin.companies.index'))
            ->assertSessionHas('status');

        $company->refresh();

        $this->assertSame('Empresa Atualizada', $company->name);
        $this->assertSame(7, $company->license->max_calls_per_available_agent);
        $this->assertSame(12, $company->license->max_concurrent_agents);
        $this->assertTrue($company->license->predictive_enabled);
        $this->assertTrue($company->license->amd_enabled);
    }

    public function test_destroy_inactivates_company_instead_of_deleting(): void
    {
        $company = Company::create(['name' => 'Empresa']);

        $this->actingAs($this->superAdmin())
            ->delete(route('super-admin.companies.destroy', $company))
            ->assertRedirect(route('super-admin.companies.index'))
            ->assertSessionHas('status');

        $this->assertSame('inactive', $company->fresh()->status);
    }

    public function test_super_admin_company_list_shows_current_call_usage(): void
    {
        $company = Company::create(['name' => 'Empresa Capacidade']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_users' => 2,
            'max_concurrent_agents' => 1,
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 1,
        ]);
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'agent',
            'status' => 'active',
            'extension' => '2001',
        ]);
        User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'company_admin',
            'status' => 'active',
            'extension' => '2002',
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);
        Call::create([
            'company_id' => $company->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'started_at' => now(),
        ]);
        Call::create([
            'company_id' => $company->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'started_at' => now(),
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('super-admin.companies.index'))
            ->assertOk()
            ->assertDontSee('Usuarios contratados')
            ->assertSee('Licencas em uso')
            ->assertSee('Agentes disponiveis')
            ->assertSee('Chamadas contratadas')
            ->assertSee('2 ativa de 1')
            ->assertSee('3 por operador disponivel')
            ->assertSee('capacidade efetiva 1')
            ->assertSee('Limite excedido')
            ->assertSee('Usuarios disponiveis')
            ->assertSee('Chamadas simultaneas')
            ->assertSee('2 / 1');
    }

    public function test_company_and_license_creation_are_audited_with_super_admin_actor(): void
    {
        $admin = $this->superAdmin();
        AuditLog::query()->delete();

        $this->actingAs($admin)
            ->post(route('super-admin.companies.store'), $this->payload([
                'name' => 'Empresa Auditada',
            ]))
            ->assertRedirect(route('super-admin.companies.index'));

        $company = Company::where('name', 'Empresa Auditada')->sole();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'actor_user_id' => $admin->id,
            'event' => 'company.created',
            'action' => 'created',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'actor_user_id' => $admin->id,
            'event' => 'license.created',
            'action' => 'created',
        ]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Empresa Demo',
            'document' => '12345678000199',
            'status' => 'active',
            'license_starts_at' => now()->subDay()->toDateString(),
            'license_ends_at' => now()->addMonth()->toDateString(),
            'max_users' => 25,
            'max_concurrent_agents' => 10,
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 30,
            'power_enabled' => '1',
            'preview_enabled' => '1',
        ], $overrides);
    }
}
