<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\AgentStatus;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_route_loads_real_panel_and_keeps_tenant_isolation(): void
    {
        [$admin, $company] = $this->tenantUser('company_admin');
        [$agent] = $this->tenantUser('agent', $company, 'Agente Relatorio');
        [$otherAgent] = $this->tenantUser('agent', null, 'Agente Outra Empresa');

        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990001',
            'started_at' => now(),
            'ended_at' => now(),
        ]);
        Call::create([
            'company_id' => $otherAgent->company_id,
            'user_id' => $otherAgent->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990002',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.reports.index'))
            ->assertOk()
            ->assertSee('Relatorios')
            ->assertSee('31999990001')
            ->assertDontSee('31999990002')
            ->assertDontSee('Modulo em breve');
    }

    public function test_reports_metrics_filters_and_summary_blocks(): void
    {
        [$admin, $company] = $this->tenantUser('company_admin');
        [$agentA] = $this->tenantUser('agent', $company, 'Agente A');
        [$agentB] = $this->tenantUser('agent', $company, 'Agente B');
        $campaignA = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha A', 'dialing_mode' => 'preview', 'status' => 'active']);
        $campaignB = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha B', 'dialing_mode' => 'preview', 'status' => 'active']);
        ContactList::create(['company_id' => $company->id, 'name' => 'Lista Relatorio', 'status' => 'active'])
            ->contacts()->create(['company_id' => $company->id, 'name' => 'Lead A', 'phone' => '31999990003']);

        AgentStatus::create(['company_id' => $company->id, 'user_id' => $agentA->id, 'status' => 'available', 'started_at' => now()]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $agentB->id, 'status' => 'paused', 'started_at' => now()]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agentA->id,
            'campaign_id' => $campaignA->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990010',
            'started_at' => now(),
            'ended_at' => now(),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agentB->id,
            'campaign_id' => $campaignB->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => '31999990011',
            'started_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.reports.index'))
            ->assertOk()
            ->assertSee('Chamadas totais')
            ->assertSee('2 total')
            ->assertSee('Em andamento')
            ->assertSee('1 andamento')
            ->assertSee('Encerradas')
            ->assertSee('1 encerrada')
            ->assertSee('Agentes disponiveis')
            ->assertSee('1 disponivel')
            ->assertSee('Agentes em pausa')
            ->assertSee('1 pausa')
            ->assertSee('Performance por agente')
            ->assertSee('Performance por campanha')
            ->assertSee('Resumo de listas')
            ->assertSee('Ultimas chamadas')
            ->assertSee('Exportar - Em breve')
            ->assertSee('31999990010')
            ->assertSee('31999990011');

        $this->actingAs($admin)
            ->get(route('tenant.reports.index', ['user_id' => $agentA->id]))
            ->assertOk()
            ->assertSee('31999990010')
            ->assertDontSee('31999990011');

        $this->actingAs($admin)
            ->get(route('tenant.reports.index', ['campaign_id' => $campaignB->id]))
            ->assertOk()
            ->assertSee('31999990011')
            ->assertDontSee('31999990010');
    }

    private function tenantUser(string $type, ?Company $company = null, ?string $name = null): array
    {
        $company ??= Company::create(['name' => 'Tenant Relatorios '.fake()->unique()->numberBetween(1000, 9999)]);

        if (! $company->licenses()->exists()) {
            $company->licenses()->create([
                'status' => 'active',
                'starts_at' => now()->subDay()->toDateString(),
                'ends_at' => now()->addMonth()->toDateString(),
                'max_calls_per_available_agent' => 2,
                'max_concurrent_calls' => 10,
            ]);
        }

        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Relatorios '.fake()->unique()->numberBetween(1000, 9999),
            'slug' => 'relatorios-'.fake()->unique()->numberBetween(1000, 9999),
            'is_admin' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => $name ?? fake()->name(),
            'user_type' => $type,
            'extension' => fake()->unique()->numerify('4###'),
            'status' => 'active',
        ]);

        return [$user, $company];
    }
}
