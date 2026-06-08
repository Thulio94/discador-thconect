<?php

namespace Tests\Feature;

use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\ContactList;
use App\Models\Disposition;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

    public function test_reports_can_filter_and_summarize_by_contact_list(): void
    {
        [$admin, $company] = $this->tenantUser('company_admin');
        [$agent] = $this->tenantUser('agent', $company, 'Agente Mailing');
        $campaign = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha Mailing', 'dialing_mode' => 'preview', 'status' => 'active']);
        $listA = ContactList::create(['company_id' => $company->id, 'name' => 'Lista Mailing A', 'status' => 'active']);
        $listB = ContactList::create(['company_id' => $company->id, 'name' => 'Lista Mailing B', 'status' => 'active']);
        $campaign->contactLists()->sync([
            $listA->id => ['company_id' => $company->id, 'weight' => 1],
            $listB->id => ['company_id' => $company->id, 'weight' => 1],
        ]);
        $pending = $listA->contacts()->create(['company_id' => $company->id, 'name' => 'Pendente A', 'phone' => '31999990600', 'status' => 'pending']);
        $answered = $listA->contacts()->create(['company_id' => $company->id, 'name' => 'Atendido A', 'phone' => '31999990601', 'status' => 'answered']);
        $callback = $listA->contacts()->create(['company_id' => $company->id, 'name' => 'Callback A', 'phone' => '31999990602', 'status' => 'callback']);
        $other = $listB->contacts()->create(['company_id' => $company->id, 'name' => 'Outro B', 'phone' => '31999990699', 'status' => 'pending']);

        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $answered->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => $answered->phone,
            'started_at' => now(),
            'ended_at' => now()->addMinute(),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $other->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => $other->phone,
            'started_at' => now(),
            'ended_at' => now()->addMinute(),
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.reports.index', [
                'campaign_id' => $campaign->id,
                'list_id' => $listA->id,
            ]))
            ->assertOk()
            ->assertSee('Mailing por lista')
            ->assertSee('Lista Mailing A')
            ->assertSee('Pendentes 1')
            ->assertSee('Atendidos 1')
            ->assertSee('Callback 1')
            ->assertSee('Aproveitamento 33%')
            ->assertSee($answered->phone)
            ->assertDontSee($other->phone)
            ->assertSee('list_id='.$listA->id, false);

        $csvResponse = $this->actingAs($admin)
            ->get(route('tenant.reports.export', ['list_id' => $listA->id]))
            ->assertOk()
            ->assertDownload('relatorios-chamadas.csv');

        $csv = $csvResponse->streamedContent();

        $this->assertStringContainsString($answered->phone, $csv);
        $this->assertStringNotContainsString($other->phone, $csv);
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('callback', $callback->fresh()->status);
    }

    public function test_reports_show_tma_tme_and_export_time_metrics(): void
    {
        [$admin, $company] = $this->tenantUser('company_admin');
        [$agent] = $this->tenantUser('agent', $company, 'Agente Tempo');
        $campaign = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha Tempo', 'dialing_mode' => 'preview', 'status' => 'active']);
        $base = now()->setSecond(0);

        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990701',
            'started_at' => $base->copy()->subMinutes(10),
            'answered_at' => $base->copy()->subMinutes(10)->addSeconds(20),
            'ended_at' => $base->copy()->subMinutes(8),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990702',
            'started_at' => $base->copy()->subMinutes(7),
            'answered_at' => $base->copy()->subMinutes(7)->addSeconds(40),
            'ended_at' => $base->copy()->subMinutes(2),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => '31999990703',
            'started_at' => $base->copy()->subMinutes(60),
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.reports.index', ['campaign_id' => $campaign->id]))
            ->assertOk()
            ->assertSee('TMA')
            ->assertSee('3 min 30s')
            ->assertSee('TME')
            ->assertSee('30s')
            ->assertSee('Agente Tempo')
            ->assertSee('TMA 3 min 30s')
            ->assertSee('Campanha Tempo')
            ->assertSee('Duracao')
            ->assertSee('Espera')
            ->assertSee('2 min')
            ->assertSee('20s')
            ->assertSee('5 min')
            ->assertSee('40s');

        $csvResponse = $this->actingAs($admin)
            ->get(route('tenant.reports.export', ['campaign_id' => $campaign->id]))
            ->assertOk()
            ->assertDownload('relatorios-chamadas.csv');

        $csv = $csvResponse->streamedContent();

        $this->assertStringContainsString('duracao', $csv);
        $this->assertStringContainsString('espera', $csv);
        $this->assertStringContainsString('2 min', $csv);
        $this->assertStringContainsString('20s', $csv);
        $this->assertStringContainsString('5 min', $csv);
        $this->assertStringContainsString('40s', $csv);
    }

    public function test_reports_show_conversion_by_campaign_team_and_agent(): void
    {
        [$admin, $company] = $this->tenantUser('company_admin');
        [$agent] = $this->tenantUser('agent', $company, 'Agente Conversao');
        $team = Team::create(['company_id' => $company->id, 'name' => 'Equipe Conversao', 'slug' => 'equipe-conversao', 'status' => 'active']);
        $team->users()->attach($agent->id, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $campaign = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha Conversao', 'dialing_mode' => 'preview', 'status' => 'active']);
        $success = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Venda confirmada',
            'code' => 'VENDA',
            'category' => 'success',
            'status' => 'active',
            'ends_call' => true,
            'is_success' => true,
        ]);
        $neutral = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Sem interesse',
            'code' => 'SEM_INTERESSE',
            'category' => 'neutral',
            'status' => 'active',
            'ends_call' => true,
            'is_success' => false,
        ]);

        foreach ([[$success, '31999990801'], [$neutral, '31999990802']] as [$disposition, $phone]) {
            Call::create([
                'company_id' => $company->id,
                'user_id' => $agent->id,
                'campaign_id' => $campaign->id,
                'disposition_id' => $disposition->id,
                'disposition_code' => $disposition->code,
                'disposition_name' => $disposition->name,
                'direction' => 'outbound',
                'status' => 'ended',
                'channel' => 'simulated',
                'phone' => $phone,
                'started_at' => now()->subMinutes(5),
                'ended_at' => now(),
            ]);
        }
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990803',
            'started_at' => now()->subMinutes(3),
            'ended_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.reports.index', ['campaign_id' => $campaign->id]))
            ->assertOk()
            ->assertSee('Conversoes')
            ->assertSee('1 convertida')
            ->assertSee('Taxa conversao')
            ->assertSee('50%')
            ->assertSee('Agente Conversao')
            ->assertSee('Conv 1 | 50%')
            ->assertSee('Campanha Conversao')
            ->assertSee('Equipe Conversao');

        $csvResponse = $this->actingAs($admin)
            ->get(route('tenant.reports.export', ['campaign_id' => $campaign->id]))
            ->assertOk()
            ->assertDownload('relatorios-chamadas.csv');

        $csv = $csvResponse->streamedContent();

        $this->assertStringContainsString('conversao', $csv);
        $this->assertStringContainsString('sim', $csv);
        $this->assertStringContainsString('nao', $csv);
    }

    public function test_reports_xlsx_export_respects_filters_and_time_columns(): void
    {
        [$admin, $company] = $this->tenantUser('company_admin');
        [$agent] = $this->tenantUser('agent', $company, 'Agente XLSX');
        $campaignA = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha XLSX A', 'dialing_mode' => 'preview', 'status' => 'active']);
        $campaignB = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha XLSX B', 'dialing_mode' => 'preview', 'status' => 'active']);
        $success = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Venda XLSX',
            'code' => 'VENDA_XLSX',
            'category' => 'success',
            'status' => 'active',
            'ends_call' => true,
            'is_success' => true,
        ]);
        $base = now()->setSecond(0);

        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaignA->id,
            'disposition_id' => $success->id,
            'disposition_code' => $success->code,
            'disposition_name' => $success->name,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990901',
            'started_at' => $base->copy()->subMinutes(6),
            'answered_at' => $base->copy()->subMinutes(6)->addSeconds(15),
            'ended_at' => $base->copy()->subMinutes(4),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaignB->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990999',
            'started_at' => $base->copy()->subMinutes(3),
            'ended_at' => $base,
        ]);

        $xlsxResponse = $this->actingAs($admin)
            ->get(route('tenant.reports.export-xlsx', ['campaign_id' => $campaignA->id]))
            ->assertOk()
            ->assertDownload('relatorios-chamadas.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'discador-report-').'.xlsx';
        file_put_contents($path, $xlsxResponse->streamedContent());

        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray();

        @unlink($path);

        $this->assertSame(['data_inicio', 'data_fim', 'duracao', 'espera', 'conversao', 'telefone', 'agente', 'campanha', 'status', 'canal', 'tabulacao_codigo', 'tabulacao_nome', 'observacoes'], $rows[0]);
        $this->assertSame('2 min', $rows[1][2]);
        $this->assertSame('15s', $rows[1][3]);
        $this->assertSame('sim', $rows[1][4]);
        $this->assertSame('31999990901', $rows[1][5]);
        $this->assertSame('Campanha XLSX A', $rows[1][7]);
        $this->assertNull($rows[2][0] ?? null);
        $this->assertNotContains('31999990999', array_column($rows, 5));
    }

    public function test_reports_pdf_export_respects_filters_and_summary(): void
    {
        [$admin, $company] = $this->tenantUser('company_admin');
        [$agent] = $this->tenantUser('agent', $company, 'Agente PDF');
        $campaignA = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha PDF A', 'dialing_mode' => 'preview', 'status' => 'active']);
        $campaignB = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha PDF B', 'dialing_mode' => 'preview', 'status' => 'active']);
        $success = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Venda PDF',
            'code' => 'VENDA_PDF',
            'category' => 'success',
            'status' => 'active',
            'ends_call' => true,
            'is_success' => true,
        ]);

        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaignA->id,
            'disposition_id' => $success->id,
            'disposition_code' => $success->code,
            'disposition_name' => $success->name,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999991001',
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaignB->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999991099',
            'started_at' => now()->subMinutes(3),
            'ended_at' => now(),
        ]);

        $pdfResponse = $this->actingAs($admin)
            ->get(route('tenant.reports.export-pdf', ['campaign_id' => $campaignA->id]))
            ->assertOk()
            ->assertDownload('relatorios-chamadas.pdf');

        $pdf = $pdfResponse->streamedContent();

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('Relatorio de Chamadas', $pdf);
        $this->assertStringContainsString('Resumo executivo', $pdf);
        $this->assertStringContainsString('Campanha PDF A', $pdf);
        $this->assertStringContainsString('31999991001', $pdf);
        $this->assertStringContainsString('Conversoes: 1', $pdf);
        $this->assertStringNotContainsString('Campanha PDF B', $pdf);
        $this->assertStringNotContainsString('31999991099', $pdf);
    }

    public function test_reports_register_and_show_export_history(): void
    {
        [$admin, $company] = $this->tenantUser('company_admin');
        [$agent] = $this->tenantUser('agent', $company, 'Agente Historico');
        [$otherAdmin, $otherCompany] = $this->tenantUser('company_admin', null, 'Admin Outra Hist');
        $campaign = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha Historico', 'dialing_mode' => 'preview', 'status' => 'active']);

        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999991101',
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
        ]);

        ReportExport::create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherAdmin->id,
            'format' => 'pdf',
            'filters' => ['campaign_id' => 999],
            'records_count' => 7,
        ]);

        foreach (['export' => 'csv', 'export-xlsx' => 'xlsx', 'export-pdf' => 'pdf'] as $routeSuffix => $format) {
            $this->actingAs($admin)
                ->get(route('tenant.reports.'.$routeSuffix, ['campaign_id' => $campaign->id]))
                ->assertOk();

            $this->assertDatabaseHas('report_exports', [
                'company_id' => $company->id,
                'user_id' => $admin->id,
                'format' => $format,
                'records_count' => 1,
            ]);
        }

        $export = ReportExport::query()
            ->where('company_id', $company->id)
            ->where('format', 'csv')
            ->firstOrFail();

        $this->assertSame($campaign->id, $export->filters['campaign_id']);

        $this->actingAs($admin)
            ->get(route('tenant.reports.index'))
            ->assertOk()
            ->assertSee('Historico de exportacoes')
            ->assertSee('CSV')
            ->assertSee('XLSX')
            ->assertSee('PDF')
            ->assertSee('1 registro')
            ->assertSee('Campanha Historico')
            ->assertDontSee('7 registros');
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
