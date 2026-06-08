<?php

namespace Tests\Feature;

use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\ContactList;
use App\Models\Disposition;
use App\Models\Role;
use App\Models\Team;
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
            ->assertSee('Analitico de chamadas')
            ->assertSee('Exportar CSV')
            ->assertDontSee('Exportar - Em breve')
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

    public function test_reports_can_filter_and_summarize_by_disposition(): void
    {
        [$admin, $company] = $this->tenantUser('company_admin');
        [$agent] = $this->tenantUser('agent', $company, 'Agente Tabulacao');
        $campaign = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha Tabulacao', 'dialing_mode' => 'preview', 'status' => 'active']);
        $sale = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Venda realizada',
            'code' => 'VENDA',
            'category' => 'success',
            'status' => 'active',
        ]);
        $refusal = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Recusa',
            'code' => 'RECUSA',
            'category' => 'negative',
            'status' => 'active',
        ]);
        $otherCompany = Company::create(['name' => 'Outra Empresa']);
        $otherDisposition = Disposition::create([
            'company_id' => $otherCompany->id,
            'name' => 'Outra tabulacao',
            'code' => 'OUTRA',
            'category' => 'neutral',
            'status' => 'active',
        ]);

        foreach (['31999990200', '31999990201'] as $phone) {
            Call::create([
                'company_id' => $company->id,
                'user_id' => $agent->id,
                'campaign_id' => $campaign->id,
                'disposition_id' => $sale->id,
                'disposition_code' => $sale->code,
                'disposition_name' => $sale->name,
                'direction' => 'outbound',
                'status' => 'ended',
                'channel' => 'simulated',
                'phone' => $phone,
                'started_at' => now(),
                'ended_at' => now(),
            ]);
        }

        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'disposition_id' => $refusal->id,
            'disposition_code' => $refusal->code,
            'disposition_name' => $refusal->name,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990202',
            'started_at' => now(),
            'ended_at' => now(),
        ]);
        Call::create([
            'company_id' => $otherCompany->id,
            'disposition_id' => $otherDisposition->id,
            'disposition_code' => $otherDisposition->code,
            'disposition_name' => $otherDisposition->name,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990203',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.reports.index'))
            ->assertOk()
            ->assertSee('Resultado')
            ->assertSee('Resultados por tabulacao')
            ->assertSee('Venda realizada')
            ->assertSee('2 chamadas')
            ->assertSee('Recusa')
            ->assertSee('1 chamadas')
            ->assertDontSee('Outra tabulacao');

        $response = $this->actingAs($admin)
            ->get(route('tenant.reports.index', ['disposition_id' => $sale->id]))
            ->assertOk()
            ->assertSee('31999990200')
            ->assertSee('31999990201')
            ->assertDontSee('31999990202');

        $response->assertSee('value="'.$sale->id.'" selected', false);

        $csvResponse = $this->actingAs($admin)
            ->get(route('tenant.reports.export', ['disposition_id' => $sale->id]))
            ->assertOk()
            ->assertDownload('relatorios-chamadas.csv');

        $csv = $csvResponse->streamedContent();

        $this->assertStringContainsString('31999990200', $csv);
        $this->assertStringContainsString('31999990201', $csv);
        $this->assertStringNotContainsString('31999990202', $csv);
        $this->assertStringNotContainsString('31999990203', $csv);
    }

    public function test_reports_can_filter_and_summarize_by_team(): void
    {
        [$admin, $company] = $this->tenantUser('company_admin');
        [$agentA] = $this->tenantUser('agent', $company, 'Agente Equipe A');
        [$agentB] = $this->tenantUser('agent', $company, 'Agente Equipe B');
        [$otherAgent] = $this->tenantUser('agent', null, 'Agente Equipe Outra');
        $teamA = Team::create(['company_id' => $company->id, 'name' => 'Equipe Retencao', 'slug' => 'equipe-retencao', 'status' => 'active']);
        $teamB = Team::create(['company_id' => $company->id, 'name' => 'Equipe Vendas', 'slug' => 'equipe-vendas', 'status' => 'active']);
        $otherTeam = Team::create(['company_id' => $otherAgent->company_id, 'name' => 'Equipe Outra Empresa', 'slug' => 'equipe-outra-empresa', 'status' => 'active']);

        $teamA->users()->attach($agentA->id, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $teamB->users()->attach($agentB->id, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $otherTeam->users()->attach($otherAgent->id, ['company_id' => $otherAgent->company_id, 'membership_type' => 'agent']);

        foreach (['31999990300', '31999990301'] as $phone) {
            Call::create([
                'company_id' => $company->id,
                'user_id' => $agentA->id,
                'direction' => 'outbound',
                'status' => 'ended',
                'channel' => 'simulated',
                'phone' => $phone,
                'started_at' => now(),
                'ended_at' => now(),
            ]);
        }

        Call::create([
            'company_id' => $company->id,
            'user_id' => $agentB->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990302',
            'started_at' => now(),
            'ended_at' => now(),
        ]);
        Call::create([
            'company_id' => $otherAgent->company_id,
            'user_id' => $otherAgent->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990303',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.reports.index'))
            ->assertOk()
            ->assertSee('Equipe')
            ->assertSee('Performance por equipe')
            ->assertSee('Equipe Retencao')
            ->assertSee('2 chamadas')
            ->assertSee('Equipe Vendas')
            ->assertSee('1 chamadas')
            ->assertDontSee('Equipe Outra Empresa');

        $response = $this->actingAs($admin)
            ->get(route('tenant.reports.index', ['team_id' => $teamA->id]))
            ->assertOk()
            ->assertSee('31999990300')
            ->assertSee('31999990301')
            ->assertDontSee('31999990302')
            ->assertDontSee('31999990303');

        $response->assertSee('value="'.$teamA->id.'" selected', false);

        $csvResponse = $this->actingAs($admin)
            ->get(route('tenant.reports.export', ['team_id' => $teamA->id]))
            ->assertOk()
            ->assertDownload('relatorios-chamadas.csv');

        $csv = $csvResponse->streamedContent();

        $this->assertStringContainsString('31999990300', $csv);
        $this->assertStringContainsString('31999990301', $csv);
        $this->assertStringNotContainsString('31999990302', $csv);
        $this->assertStringNotContainsString('31999990303', $csv);
    }

    public function test_reports_show_paginated_analytic_calls_with_duration_and_filters(): void
    {
        [$admin, $company] = $this->tenantUser('company_admin');
        [$agentA] = $this->tenantUser('agent', $company, 'Agente Analitico A');
        [$agentB] = $this->tenantUser('agent', $company, 'Agente Analitico B');
        $campaignA = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha Analitica A', 'dialing_mode' => 'preview', 'status' => 'active']);
        $campaignB = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha Analitica B', 'dialing_mode' => 'preview', 'status' => 'active']);
        $sale = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Venda analitica',
            'code' => 'VENDA_ANALITICA',
            'category' => 'success',
            'status' => 'active',
        ]);

        foreach (range(1, 13) as $index) {
            Call::create([
                'company_id' => $company->id,
                'user_id' => $agentA->id,
                'campaign_id' => $campaignA->id,
                'disposition_id' => $sale->id,
                'disposition_code' => $sale->code,
                'disposition_name' => $sale->name,
                'direction' => 'outbound',
                'status' => 'ended',
                'channel' => 'simulated',
                'phone' => '319999904'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'started_at' => now()->subMinutes($index),
                'ended_at' => now()->subMinutes($index)->addMinutes(2),
            ]);
        }

        Call::create([
            'company_id' => $company->id,
            'user_id' => $agentB->id,
            'campaign_id' => $campaignB->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990599',
            'started_at' => now(),
            'ended_at' => now()->addMinute(),
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.reports.index', [
                'user_id' => $agentA->id,
                'campaign_id' => $campaignA->id,
            ]))
            ->assertOk()
            ->assertSee('Analitico de chamadas')
            ->assertSee('13 registros')
            ->assertSee('31999990401')
            ->assertSee('31999990410')
            ->assertSee('2 min')
            ->assertSee('Venda analitica')
            ->assertSee('pagination')
            ->assertDontSee('31999990413')
            ->assertDontSee('31999990599')
            ->assertSee('user_id='.$agentA->id, false)
            ->assertSee('campaign_id='.$campaignA->id, false);

        $this->actingAs($admin)
            ->get(route('tenant.reports.index', [
                'user_id' => $agentA->id,
                'campaign_id' => $campaignA->id,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertSee('31999990413')
            ->assertDontSee('31999990401');
    }

    public function test_reports_csv_export_respects_filters_and_tenant_isolation(): void
    {
        [$admin, $company] = $this->tenantUser('company_admin');
        [$agentA] = $this->tenantUser('agent', $company, 'Agente Export A');
        [$agentB] = $this->tenantUser('agent', $company, 'Agente Export B');
        [$otherAgent] = $this->tenantUser('agent', null, 'Agente Export Outra');
        $campaignA = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha Export A', 'dialing_mode' => 'preview', 'status' => 'active']);
        $campaignB = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha Export B', 'dialing_mode' => 'preview', 'status' => 'active']);
        $disposition = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Venda exportada',
            'code' => 'VENDA_EXPORT',
            'category' => 'success',
            'status' => 'active',
        ]);

        Call::create([
            'company_id' => $company->id,
            'user_id' => $agentA->id,
            'campaign_id' => $campaignA->id,
            'disposition_id' => $disposition->id,
            'disposition_code' => $disposition->code,
            'disposition_name' => $disposition->name,
            'disposition_notes' => 'Observacao CSV',
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990100',
            'started_at' => now(),
            'ended_at' => now(),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agentB->id,
            'campaign_id' => $campaignB->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990101',
            'started_at' => now(),
            'ended_at' => now(),
        ]);
        Call::create([
            'company_id' => $otherAgent->company_id,
            'user_id' => $otherAgent->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990102',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('tenant.reports.export', [
                'user_id' => $agentA->id,
                'campaign_id' => $campaignA->id,
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload('relatorios-chamadas.csv');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('telefone,agente,campanha,status,canal,tabulacao_codigo,tabulacao_nome,observacoes', $csv);
        $this->assertStringContainsString('31999990100', $csv);
        $this->assertStringContainsString('Agente Export A', $csv);
        $this->assertStringContainsString('Campanha Export A', $csv);
        $this->assertStringContainsString('VENDA_EXPORT', $csv);
        $this->assertStringContainsString('Observacao CSV', $csv);
        $this->assertStringNotContainsString('31999990101', $csv);
        $this->assertStringNotContainsString('31999990102', $csv);
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
