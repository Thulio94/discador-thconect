<?php

namespace Tests\Feature;

use App\Models\AgentPause;
use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\ContactList;
use App\Models\Disposition;
use App\Models\OperationalParameter;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Dialer\AgentCapacityService;
use App\Services\Dialer\MailingQueueService;
use App\Services\Dialer\SimulatedDialerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TenantAgentPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_state_models_belong_to_tenant_user(): void
    {
        [$agent] = $this->tenantUser('agent');

        $status = AgentStatus::create([
            'company_id' => $agent->company_id,
            'user_id' => $agent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);
        $pause = AgentPause::create([
            'company_id' => $agent->company_id,
            'user_id' => $agent->id,
            'reason' => 'Almoco',
            'started_at' => now(),
        ]);
        $call = Call::create([
            'company_id' => $agent->company_id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'started_at' => now(),
        ]);

        $this->assertTrue($agent->agentStatuses->contains($status));
        $this->assertTrue($agent->agentPauses->contains($pause));
        $this->assertTrue($agent->calls->contains($call));
        $this->assertTrue($agent->company->agentStatuses->contains($status));
        $this->assertSame('available', $agent->currentAgentStatus->status);
        $this->assertMatchesRegularExpression('/^'.now()->format('ymd').'\\d{6}$/', $call->protocol);
    }

    public function test_only_available_active_agents_count_for_company_capacity(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        [$pausedAgent] = $this->tenantUser('agent', $company);
        [$supervisor] = $this->tenantUser('supervisor', $company);
        [$admin] = $this->tenantUser('company_admin', $company);

        AgentStatus::create(['company_id' => $company->id, 'user_id' => $agent->id, 'status' => 'available', 'started_at' => now()]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $pausedAgent->id, 'status' => 'paused', 'started_at' => now()]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $supervisor->id, 'status' => 'available', 'started_at' => now()]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $admin->id, 'status' => 'available', 'started_at' => now()]);

        $this->assertSame(1, app(AgentCapacityService::class)->availableAgentsForCompany($company->id));
    }

    public function test_campaign_capacity_counts_unique_available_agents_and_license_limits(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        [$secondAgent] = $this->tenantUser('agent', $company);
        [$supervisor] = $this->tenantUser('supervisor', $company);
        $teamA = Team::create(['company_id' => $company->id, 'name' => 'Vendas', 'slug' => 'vendas']);
        $teamB = Team::create(['company_id' => $company->id, 'name' => 'Retencao', 'slug' => 'retencao']);
        $campaign = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha Capacidade', 'dialing_mode' => 'power', 'status' => 'active']);

        $teamA->users()->attach($agent, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $teamB->users()->attach($agent, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $teamB->users()->attach($secondAgent, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $teamB->users()->attach($supervisor, ['company_id' => $company->id, 'membership_type' => 'supervisor']);
        $campaign->teams()->sync([
            $teamA->id => ['company_id' => $company->id],
            $teamB->id => ['company_id' => $company->id],
        ]);

        AgentStatus::create(['company_id' => $company->id, 'user_id' => $agent->id, 'status' => 'available', 'started_at' => now()]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $secondAgent->id, 'status' => 'available', 'started_at' => now()]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $supervisor->id, 'status' => 'available', 'started_at' => now()]);
        $company->licenses()->firstOrFail()->update(['max_concurrent_calls' => 1]);

        $service = app(AgentCapacityService::class);

        $this->assertSame(2, $service->availableAgentsForCampaign($campaign));
        $this->assertSame(4, $service->allowedCallsForCampaign($campaign));
    }

    public function test_remaining_campaign_capacity_respects_company_active_calls_and_global_limit(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        [$secondAgent] = $this->tenantUser('agent', $company);
        $company->licenses()->firstOrFail()->update([
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 5,
        ]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent, $secondAgent]);

        foreach (range(1, 2) as $index) {
            Call::create([
                'company_id' => $company->id,
                'campaign_id' => $campaign->id,
                'direction' => 'outbound',
                'status' => 'in_progress',
                'channel' => 'simulated',
                'phone' => '3199999070'.$index,
                'started_at' => now(),
            ]);
        }
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'started_at' => now()->subMinute(),
            'ended_at' => now(),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'manual',
            'phone' => '31999990099',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $service = app(AgentCapacityService::class);

        $this->assertSame(2, $service->activeCallsForCompany($company->id));
        $this->assertSame(3, $service->remainingConcurrentCallsForCampaign($campaign));
    }

    public function test_remaining_campaign_capacity_ignores_legacy_campaign_concurrent_limit(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        [$secondAgent] = $this->tenantUser('agent', $company);
        $company->licenses()->firstOrFail()->update([
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 10,
        ]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent, $secondAgent]);
        $campaign->update(['max_concurrent_calls' => 3]);
        Call::create([
            'company_id' => $company->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'started_at' => now(),
        ]);

        $this->assertSame(
            6,
            app(AgentCapacityService::class)->remainingConcurrentCallsForCampaign($campaign),
        );
    }

    public function test_active_call_from_another_company_does_not_reduce_campaign_limit(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        [$secondAgent] = $this->tenantUser('agent', $company);
        [$otherAgent] = $this->tenantUser('agent');
        $company->licenses()->firstOrFail()->update([
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 5,
        ]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent, $secondAgent]);
        Call::create([
            'company_id' => $otherAgent->company_id,
            'user_id' => $otherAgent->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'started_at' => now(),
        ]);

        $this->assertSame(
            5,
            app(AgentCapacityService::class)->remainingConcurrentCallsForCampaign($campaign),
        );
    }

    public function test_campaign_capacity_has_no_global_limit_when_max_concurrent_calls_is_null(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        [$secondAgent] = $this->tenantUser('agent', $company);
        $company->licenses()->firstOrFail()->update([
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => null,
        ]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent, $secondAgent]);

        $this->assertSame(
            6,
            app(AgentCapacityService::class)->remainingConcurrentCallsForCampaign($campaign),
        );
    }

    public function test_remaining_campaign_capacity_is_zero_when_active_calls_reach_global_limit(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        [$secondAgent] = $this->tenantUser('agent', $company);
        $company->licenses()->firstOrFail()->update([
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 2,
        ]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent, $secondAgent]);

        foreach ([$agent, $secondAgent] as $activeAgent) {
            Call::create([
                'company_id' => $company->id,
                'user_id' => $activeAgent->id,
                'campaign_id' => $campaign->id,
                'direction' => 'outbound',
                'status' => 'in_progress',
                'channel' => 'simulated',
                'started_at' => now(),
            ]);
        }

        $this->assertSame(
            0,
            app(AgentCapacityService::class)->remainingConcurrentCallsForCampaign($campaign),
        );
    }

    public function test_remaining_campaign_capacity_returns_capacity_when_below_global_vacancies(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $company->licenses()->firstOrFail()->update([
            'max_calls_per_available_agent' => 2,
            'max_concurrent_calls' => 5,
        ]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        Call::create([
            'company_id' => $company->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'started_at' => now(),
        ]);

        $this->assertSame(
            2,
            app(AgentCapacityService::class)->remainingConcurrentCallsForCampaign($campaign),
        );
    }

    public function test_campaign_capacity_excludes_agent_waiting_for_disposition(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'started_at' => now()->subMinute(),
            'ended_at' => now(),
        ]);

        $service = app(AgentCapacityService::class);

        $this->assertSame(0, $service->availableAgentsForCampaign($campaign));
        $this->assertSame(0, $service->allowedCallsForCampaign($campaign));
    }

    public function test_agent_panel_route_loads_real_panel(): void
    {
        [$agent] = $this->tenantUser('agent');
        Disposition::create([
            'company_id' => $agent->company_id,
            'name' => 'Venda concluida',
            'code' => 'VENDA',
            'category' => 'success',
            'status' => 'active',
        ]);
        Call::create([
            'company_id' => $agent->company_id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => '31999990001',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->get(route('tenant.agent.index'))
            ->assertOk()
            ->assertSee('Painel do Agente')
            ->assertSee('Disponivel')
            ->assertSee('Pausa')
            ->assertSee('Offline')
            ->assertSee('Atendimento atual')
            ->assertSee('Dados do mailing')
            ->assertSee('Historico de ligacoes')
            ->assertSee('agent-pause')
            ->assertSee('Venda concluida')
            ->assertSee('call-disposition')
            ->assertSee('Agendamento de retorno')
            ->assertSee('Campos do atendimento')
            ->assertDontSee('Agentes disponiveis nesta empresa.')
            ->assertDontSee('Modulo em breve');
    }

    public function test_agent_dashboard_releases_available_license_and_limits_history_to_selected_date(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $agent->role->permissions()->syncWithoutDetaching([
            Permission::query()->firstOrCreate(
                ['key' => 'dashboard.read'],
                ['name' => 'Ler', 'module' => 'dashboard'],
            )->id,
        ]);

        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990001',
            'started_at' => now(),
            'answered_at' => now(),
            'ended_at' => now(),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990002',
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay(),
        ]);

        $this->actingAs($agent)
            ->get(route('dashboard', ['date' => now()->toDateString(), 'phone' => '(31) 99999-0001']))
            ->assertOk()
            ->assertSee('Meu dashboard')
            ->assertSee('Voce esta deslogado do painel do agente')
            ->assertSee('31999990001')
            ->assertDontSee('31999990099')
            ->assertDontSee('31999990002');

        $this->assertSame('offline', $agent->fresh()->currentAgentStatus->status);
        $this->assertSame(0, app(AgentCapacityService::class)->availableAgentsForCompany($company->id));
    }

    public function test_agent_dashboard_is_locked_but_does_not_end_an_active_call(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $agent->role->permissions()->syncWithoutDetaching([
            Permission::query()->firstOrCreate(
                ['key' => 'dashboard.read'],
                ['name' => 'Ler', 'module' => 'dashboard'],
            )->id,
        ]);

        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => '31999990003',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Voce esta em chamada')
            ->assertSee('Voltar ao painel do agente');

        $this->assertSame('in_call', $agent->fresh()->currentAgentStatus->status);
        $this->assertDatabaseHas('calls', ['user_id' => $agent->id, 'status' => 'in_progress']);
    }

    public function test_leaving_agent_console_while_paused_closes_pause_and_releases_license(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $agent->role->permissions()->syncWithoutDetaching([
            Permission::query()->firstOrCreate(
                ['key' => 'dashboard.read'],
                ['name' => 'Ler', 'module' => 'dashboard'],
            )->id,
        ]);
        $pause = AgentPause::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'reason' => 'Almoco',
            'started_at' => now(),
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'paused',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Voce esta deslogado do painel do agente');

        $this->assertSame('offline', $agent->fresh()->currentAgentStatus->status);
        $this->assertNotNull($pause->fresh()->ended_at);
    }

    public function test_background_notification_poll_does_not_release_available_agent_presence(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $agent->role->permissions()->syncWithoutDetaching([
            Permission::query()->firstOrCreate(
                ['key' => 'notifications.read'],
                ['name' => 'Ler notificacoes', 'module' => 'notifications'],
            )->id,
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->get(route('tenant.notifications.poll'))
            ->assertOk();

        $this->assertSame('available', $agent->fresh()->currentAgentStatus->status);
    }

    public function test_available_status_consumes_a_license_for_every_tenant_user(): void
    {
        [$firstAgent, $company] = $this->tenantUser('agent');
        [$secondAgent] = $this->tenantUser('agent', $company);
        [$supervisor] = $this->tenantUser('supervisor', $company);
        $company->licenses()->firstOrFail()->update(['max_concurrent_agents' => 1]);

        $this->actingAs($firstAgent)
            ->withSession(['_token' => 'agent-license-token'])
            ->patch(route('tenant.agent.status'), ['status' => 'available', '_token' => 'agent-license-token'])
            ->assertRedirect(route('tenant.agent.index'));

        $this->actingAs($secondAgent)
            ->withSession(['_token' => 'agent-license-token'])
            ->from(route('tenant.agent.index'))
            ->patch(route('tenant.agent.status'), ['status' => 'available', '_token' => 'agent-license-token'])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHasErrors('status');

        $this->actingAs($supervisor)
            ->withSession(['_token' => 'agent-license-token'])
            ->from(route('tenant.agent.index'))
            ->patch(route('tenant.agent.status'), ['status' => 'available', '_token' => 'agent-license-token'])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame(1, app(AgentCapacityService::class)->availableAgentsForCompany($company->id));
        $this->assertSame(1, app(AgentCapacityService::class)->availableLicenseUsersForCompany($company->id));
        $this->assertNull($supervisor->fresh()->currentAgentStatus);
        $this->assertNull($secondAgent->fresh()->currentAgentStatus);
    }

    public function test_agent_panel_shows_current_contact_campaign_and_contact_history(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Lista Ouro',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Cliente Ficha',
            'phone' => '31999990030',
            'status' => 'dialing',
            'attempts_count' => 2,
            'last_result' => 'SEM_RESPOSTA',
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Ficha',
            'service_script' => 'Confirme nome, plano atual e ofereca upgrade com desconto.',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        $disposition = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Sem resposta',
            'code' => 'SEM_RESPOSTA',
            'category' => 'unproductive',
            'status' => 'active',
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'disposition_id' => $disposition->id,
            'disposition_code' => $disposition->code,
            'disposition_name' => $disposition->name,
            'disposition_notes' => 'Nao atendeu ontem',
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => $contact->phone,
            'started_at' => now()->subDay(),
            'ended_at' => now()->subDay()->addMinutes(2),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => $contact->phone,
            'started_at' => now(),
            'answered_at' => now(),
        ]);

        $this->actingAs($agent)
            ->get(route('tenant.agent.index'))
            ->assertOk()
            ->assertSee('Dados do mailing')
            ->assertSee('Cliente Ficha')
            ->assertSee('31999990030')
            ->assertSee('Campanha Ficha')
            ->assertSee('Lista Ouro')
            ->assertSee('tentativa(s)')
            ->assertSee('2')
            ->assertSee('Historico deste contato')
            ->assertSee('Sem resposta')
            ->assertSee('Nao atendeu ontem')
            ->assertSee('Script de atendimento')
            ->assertSee('Confirme nome, plano atual e ofereca upgrade com desconto.');
    }

    public function test_agent_panel_shows_recent_calls_from_current_user_only(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        [$otherAgent] = $this->tenantUser('agent', $company);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Historico Geral',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        $success = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Venda fechada',
            'code' => 'VENDA_OK',
            'category' => 'success',
            'status' => 'active',
        ]);
        $callback = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Retorno marcado',
            'code' => 'RETORNO_OK',
            'category' => 'callback',
            'status' => 'active',
        ]);

        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'disposition_id' => $success->id,
            'disposition_code' => $success->code,
            'disposition_name' => $success->name,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990077',
            'started_at' => now()->subMinutes(12),
            'ended_at' => now()->subMinutes(10),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'disposition_id' => $callback->id,
            'disposition_code' => $callback->code,
            'disposition_name' => $callback->name,
            'callback_type' => 'public',
            'callback_at' => now()->addDay()->setSecond(0)->setMicrosecond(0),
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990088',
            'started_at' => now()->subMinutes(5),
            'ended_at' => now()->subMinutes(4),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $otherAgent->id,
            'campaign_id' => $campaign->id,
            'disposition_id' => $success->id,
            'disposition_code' => $success->code,
            'disposition_name' => $success->name,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990099',
            'started_at' => now()->subMinutes(3),
            'ended_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($agent)
            ->get(route('tenant.agent.index'))
            ->assertOk()
            ->assertSee('Ultimos atendimentos')
            ->assertSee('31999990088')
            ->assertSee('Retorno marcado')
            ->assertSee('Retorno publico')
            ->assertSee('31999990077')
            ->assertSee('Venda fechada')
            ->assertDontSee('31999990099');
    }

    public function test_agent_can_open_recent_call_mailing_with_imported_fields_and_all_phones(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Lista Modal Historico',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Cliente Historico Completo',
            'phone' => '31999991111',
            'status' => 'answered',
            'extra' => [
                'CPF Cliente' => '12345678901',
                'Plano Atual' => 'Fibra 500 MB',
            ],
        ]);
        $contact->phones()->createMany([
            [
                'company_id' => $company->id,
                'phone' => '31999991111',
                'column_name' => 'Telefone principal',
                'position' => 0,
                'is_primary' => true,
                'status' => 'answered',
            ],
            [
                'company_id' => $company->id,
                'phone' => '31988882222',
                'column_name' => 'Telefone alternativo',
                'position' => 1,
                'is_primary' => false,
                'status' => 'pending',
            ],
        ]);
        $call = Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999991111',
            'disposition_name' => 'Cliente atendido',
            'disposition_notes' => 'Solicitou nova proposta',
            'started_at' => now()->subMinutes(3),
            'ended_at' => now()->subMinute(),
        ]);

        $this->actingAs($agent)
            ->get(route('tenant.agent.index'))
            ->assertOk()
            ->assertSee('call-mailing-'.$call->id)
            ->assertSee('Dados do mailing da ligacao')
            ->assertSee('Cliente Historico Completo')
            ->assertSee('Lista Modal Historico')
            ->assertSee('31999991111')
            ->assertSee('31988882222')
            ->assertSee('CPF Cliente')
            ->assertSee('12345678901')
            ->assertSee('Plano Atual')
            ->assertSee('Fibra 500 MB')
            ->assertSee('Solicitou nova proposta');
    }

    public function test_agent_can_change_status_to_available_and_pause(): void
    {
        [$agent] = $this->tenantUser('agent');

        $this->actingAs($agent)
            ->patch(route('tenant.agent.status'), ['status' => 'available'])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $this->assertSame('available', $agent->fresh()->currentAgentStatus->status);

        $this->actingAs($agent)
            ->patch(route('tenant.agent.pause'), ['reason' => 'Pausa almoco'])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $agent->refresh();

        $this->assertSame('paused', $agent->currentAgentStatus->status);
        $this->assertTrue($agent->agentPauses()->whereNull('ended_at')->where('reason', 'Pausa almoco')->exists());
        $this->assertSame(0, app(AgentCapacityService::class)->availableAgentsForCompany($agent->company_id));
    }

    public function test_agent_can_start_and_end_simulated_call(): void
    {
        [$agent] = $this->tenantUser('agent');
        AgentStatus::create([
            'company_id' => $agent->company_id,
            'user_id' => $agent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);
        $disposition = Disposition::create([
            'company_id' => $agent->company_id,
            'name' => 'Cliente comprou',
            'code' => 'VENDA',
            'category' => 'success',
            'status' => 'active',
            'ends_call' => true,
            'is_success' => true,
        ]);

        $this->actingAs($agent)
            ->post(route('tenant.agent.calls.store'), ['phone' => '31999990001'])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $agent->refresh();
        $call = $agent->calls()->where('status', 'in_progress')->sole();

        $this->assertSame('in_call', $agent->currentAgentStatus->status);
        $this->assertSame('simulated', $call->channel);
        $this->assertSame('31999990001', $call->phone);

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
                'disposition_notes' => 'Venda validada no simulador',
            ])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $agent->refresh();
        $call->refresh();

        $this->assertSame('available', $agent->currentAgentStatus->status);
        $this->assertSame('ended', $call->status);
        $this->assertSame($disposition->id, $call->disposition_id);
        $this->assertSame('VENDA', $call->disposition_code);
        $this->assertSame('Cliente comprou', $call->disposition_name);
        $this->assertSame('Venda validada no simulador', $call->disposition_notes);
        $this->assertTrue($call->disposition->is($disposition));
    }

    public function test_agent_enters_campaign_wrap_up_before_saving_a_disposition(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Pos Atendimento',
            'dialing_mode' => 'power',
            'status' => 'active',
            'wrap_up_seconds' => 120,
        ]);
        $disposition = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Cliente interessado',
            'code' => 'INTERESSADO',
            'category' => 'neutral',
            'status' => 'active',
        ]);
        $call = Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => '31999990111',
            'started_at' => now(),
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.hangup'), ['call_id' => $call->id])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $call->refresh();
        $this->assertSame('wrap_up', $call->status);
        $this->assertTrue($call->wrap_up_expires_at->isFuture());
        $this->assertSame('wrap_up', $agent->fresh()->currentAgentStatus->status);

        $this->actingAs($agent)
            ->get(route('tenant.agent.index'))
            ->assertOk()
            ->assertSee('Tabulacao')
            ->assertSee('Resultado do atendimento')
            ->assertSee('Cliente interessado')
            ->assertSee('Tempo restante');

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
            ])
            ->assertRedirect(route('tenant.agent.index'));

        $call->refresh();
        $this->assertSame('ended', $call->status);
        $this->assertSame($disposition->id, $call->disposition_id);
        $this->assertNull($call->wrap_up_expires_at);
        $this->assertSame('available', $agent->fresh()->currentAgentStatus->status);
    }

    public function test_expired_wrap_up_ends_without_disposition_and_releases_agent(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $call = Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'wrap_up',
            'channel' => 'simulated',
            'phone' => '31999990112',
            'started_at' => now()->subMinutes(2),
            'ended_at' => now()->subMinute(),
            'wrap_up_expires_at' => now()->subSecond(),
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'wrap_up',
            'started_at' => now()->subMinute(),
        ]);

        $this->actingAs($agent)
            ->get(route('tenant.agent.index'))
            ->assertOk()
            ->assertDontSee('Tempo restante');

        $call->refresh();
        $this->assertSame('ended', $call->status);
        $this->assertSame('wrap_up_timeout', $call->end_reason);
        $this->assertNull($call->disposition_id);
        $this->assertSame('available', $agent->fresh()->currentAgentStatus->status);
    }

    public function test_pause_minimum_blocks_agent_from_returning_available_too_early(): void
    {
        [$agent] = $this->tenantUser('agent');
        OperationalParameter::create([
            'company_id' => $agent->company_id,
            'min_pause_minutes' => 10,
            'max_pause_minutes' => 120,
            'pause_alert_minutes' => 20,
            'call_alert_minutes' => 15,
        ]);
        AgentPause::create([
            'company_id' => $agent->company_id,
            'user_id' => $agent->id,
            'reason' => 'Almoco',
            'started_at' => now()->subMinutes(4),
        ]);
        AgentStatus::create([
            'company_id' => $agent->company_id,
            'user_id' => $agent->id,
            'status' => 'paused',
            'reason' => 'Almoco',
            'started_at' => now()->subMinutes(4),
        ]);

        $this->actingAs($agent)
            ->from(route('tenant.agent.index'))
            ->patch(route('tenant.agent.status'), ['status' => 'available'])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame('paused', $agent->fresh()->currentAgentStatus->status);
    }

    public function test_operational_parameters_control_manual_call_while_paused_and_post_call_status(): void
    {
        [$agent] = $this->tenantUser('agent');
        $disposition = Disposition::create([
            'company_id' => $agent->company_id,
            'name' => 'Atendido',
            'code' => 'ATENDIDO',
            'category' => 'success',
            'status' => 'active',
            'ends_call' => true,
            'is_success' => true,
        ]);
        OperationalParameter::create([
            'company_id' => $agent->company_id,
            'min_pause_minutes' => 0,
            'max_pause_minutes' => 120,
            'pause_alert_minutes' => 20,
            'call_alert_minutes' => 15,
            'auto_available_after_call' => false,
            'allow_manual_calls_while_paused' => false,
        ]);
        AgentPause::create([
            'company_id' => $agent->company_id,
            'user_id' => $agent->id,
            'reason' => 'Almoco',
            'started_at' => now()->subMinutes(12),
        ]);
        AgentStatus::create([
            'company_id' => $agent->company_id,
            'user_id' => $agent->id,
            'status' => 'paused',
            'reason' => 'Almoco',
            'started_at' => now()->subMinutes(12),
        ]);

        $this->actingAs($agent)
            ->from(route('tenant.agent.index'))
            ->post(route('tenant.agent.calls.store'), ['phone' => '31999990001', 'channel' => 'manual'])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHasErrors('phone');

        OperationalParameter::where('company_id', $agent->company_id)->update(['allow_manual_calls_while_paused' => true]);

        $this->actingAs($agent)
            ->post(route('tenant.agent.calls.store'), ['phone' => '31999990001', 'channel' => 'manual'])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $call = $agent->fresh()->calls()->where('status', 'in_progress')->sole();

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
            ])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $this->assertSame('paused', $agent->fresh()->currentAgentStatus->status);
        $this->assertSame('Almoco', $agent->fresh()->currentAgentStatus->reason);
        $this->assertTrue($agent->agentPauses()->whereNull('ended_at')->where('reason', 'Almoco')->exists());
    }

    public function test_manual_call_requires_valid_ddd_number_and_agent_leave_releases_presence(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->from(route('tenant.agent.index'))
            ->post(route('tenant.agent.calls.store'), ['phone' => '999', 'channel' => 'manual'])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHasErrors('phone');

        $this->actingAs($agent)
            ->post(route('tenant.agent.leave'), ['agent_console_leave' => true])
            ->assertNoContent();

        $this->assertSame('offline', $agent->fresh()->currentAgentStatus->status);

        $this->actingAs($agent)
            ->from(route('tenant.agent.index'))
            ->post(route('tenant.agent.calls.store'), ['phone' => '31999990001', 'channel' => 'manual'])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHasErrors('phone');
    }

    public function test_agent_leave_without_console_marker_preserves_presence(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->post(route('tenant.agent.leave'))
            ->assertNoContent();

        $this->assertSame('available', $agent->fresh()->currentAgentStatus->status);
    }

    public function test_agent_can_schedule_callback_and_store_basic_service_fields_when_ending_call(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Lista Retorno',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Cliente Retorno',
            'phone' => '31999990055',
            'status' => 'dialing',
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Retorno',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        $disposition = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Retornar depois',
            'code' => 'RETORNO',
            'category' => 'callback',
            'status' => 'active',
        ]);
        $call = Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => $contact->phone,
            'started_at' => now(),
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);

        $callbackAt = now()->addDay()->setSecond(0)->setMicrosecond(0);

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
                'disposition_notes' => 'Cliente pediu retorno amanha',
                'callback_type' => 'private',
                'callback_at' => $callbackAt->format('Y-m-d\TH:i'),
                'service_fields' => [
                    'documento' => '12345678900',
                    'protocolo' => 'PROTO-123',
                    'valor' => '199.90',
                    'vazio' => '',
                ],
            ])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $call->refresh();
        $contact->refresh();

        $this->assertSame('ended', $call->status);
        $this->assertSame('RETORNO', $call->disposition_code);
        $this->assertSame('private', $call->callback_type);
        $this->assertTrue($callbackAt->equalTo($call->callback_at));
        $this->assertSame([
            'documento' => '12345678900',
            'protocolo' => 'PROTO-123',
            'valor' => '199.90',
        ], $call->service_fields);
        $this->assertSame('callback', $contact->status);
        $this->assertSame('RETORNO', $contact->last_result);
        $this->assertTrue($callbackAt->equalTo($contact->next_attempt_at));
        $this->assertSame('available', $agent->fresh()->currentAgentStatus->status);
    }

    public function test_agent_panel_uses_campaign_dynamic_service_fields(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Lista Campos Dinamicos',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Cliente Campos',
            'phone' => '31999990058',
            'status' => 'dialing',
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Campos Dinamicos',
            'dialing_mode' => 'power',
            'status' => 'active',
            'service_fields_schema' => [
                ['key' => 'cpf_cliente', 'label' => 'CPF Cliente', 'type' => 'cpf', 'required' => true],
                ['key' => 'cnpj_empresa', 'label' => 'CNPJ Empresa', 'type' => 'cnpj', 'required' => false],
                ['key' => 'telefone', 'label' => 'Telefone', 'type' => 'phone', 'required' => false],
                ['key' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => false],
                ['key' => 'valor_oferta', 'label' => 'Valor Oferta', 'type' => 'currency', 'required' => false],
            ],
        ]);
        $disposition = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Venda validada',
            'code' => 'VENDA_VALIDADA',
            'category' => 'success',
            'status' => 'active',
        ]);
        $call = Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => $contact->phone,
            'started_at' => now(),
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->get(route('tenant.agent.index'))
            ->assertOk()
            ->assertSee('CPF Cliente')
            ->assertSee('CNPJ Empresa')
            ->assertSee('Telefone')
            ->assertSee('E-mail')
            ->assertSee('Valor Oferta')
            ->assertSee('name="service_fields[cpf_cliente]"', false)
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('name="service_fields[telefone]"', false)
            ->assertSee('type="tel"', false)
            ->assertSee('name="service_fields[email]"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('name="service_fields[valor_oferta]"', false)
            ->assertSee("serviceFieldMask('currency')", false)
            ->assertSee('data-service-fields-grid', false)
            ->assertDontSee('Documento');

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
                'service_fields' => [
                    'cpf_cliente' => '111.111.111-11',
                    'cnpj_empresa' => '11.111.111/1111-11',
                    'telefone' => '123',
                    'email' => 'cliente@',
                    'valor_oferta' => '-10,00',
                ],
            ])
            ->assertSessionHasErrors([
                'service_fields.cpf_cliente',
                'service_fields.cnpj_empresa',
                'service_fields.telefone',
                'service_fields.email',
                'service_fields.valor_oferta',
            ]);

        $this->assertSame('in_progress', $call->fresh()->status);
        $this->assertSame('in_call', $agent->fresh()->currentAgentStatus->status);

        $this->followingRedirects()
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
                'service_fields' => [
                    'cpf_cliente' => '111.111.111-11',
                    'cnpj_empresa' => '11.111.111/1111-11',
                    'telefone' => '123',
                    'email' => 'cliente@',
                    'valor_oferta' => '-10,00',
                ],
            ])
            ->assertOk()
            ->assertSee('id="call-disposition"', false)
            ->assertSee('CPF Cliente possui CPF invalido.')
            ->assertSee('111.111.111-11');

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
                'service_fields' => [
                    'cpf_cliente' => '529.982.247-25',
                    'cnpj_empresa' => '04.252.011/0001-10',
                    'telefone' => '(31) 99999-0000',
                    'email' => ' CLIENTE@EXAMPLE.COM ',
                    'valor_oferta' => 'R$ 1.234,56',
                    'campo_fora_schema' => 'ignorado',
                ],
            ])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $this->assertSame([
            'cpf_cliente' => '52998224725',
            'cnpj_empresa' => '04252011000110',
            'telefone' => '31999990000',
            'email' => 'cliente@example.com',
            'valor_oferta' => 123456,
        ], $call->fresh()->service_fields);
    }

    public function test_service_field_validation_uses_only_the_current_calls_campaign_schema(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        [, $otherCompany] = $this->tenantUser('agent');

        Campaign::create([
            'company_id' => $otherCompany->id,
            'name' => 'Campanha Externa',
            'dialing_mode' => 'power',
            'status' => 'active',
            'service_fields_schema' => [
                ['key' => 'documento', 'label' => 'CNPJ Externo', 'type' => 'cnpj', 'required' => true],
            ],
        ]);

        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Lista Isolada',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Cliente Isolado',
            'phone' => '31999990077',
            'status' => 'dialing',
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Isolada',
            'dialing_mode' => 'power',
            'status' => 'active',
            'service_fields_schema' => [
                ['key' => 'documento', 'label' => 'Documento Local', 'type' => 'text', 'required' => false],
            ],
        ]);
        $disposition = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Venda Isolada',
            'code' => 'VENDA_ISOLADA',
            'category' => 'success',
            'status' => 'active',
        ]);
        $call = Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => $contact->phone,
            'started_at' => now(),
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
                'service_fields' => ['documento' => 'ABC-123'],
            ])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $this->assertSame(['documento' => 'ABC-123'], $call->fresh()->service_fields);
    }

    public function test_disposition_retry_rule_returns_contact_to_queue_with_cooldown(): void
    {
        $this->travelTo(now()->setSecond(0)->setMicrosecond(0));

        [$agent, $company] = $this->tenantUser('agent');
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Lista Retry',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Cliente Retry',
            'phone' => '31999990056',
            'status' => 'dialing',
            'attempts_count' => 2,
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Retry',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        $disposition = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Nao atendeu retry',
            'code' => 'NAO_ATENDE_RETRY',
            'category' => 'negative',
            'status' => 'active',
            'retry_enabled' => true,
            'retry_delay_minutes' => 45,
            'max_attempts' => 3,
        ]);
        $call = Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => $contact->phone,
            'started_at' => now(),
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
            ])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $contact->refresh();

        $this->assertSame('pending', $contact->status);
        $this->assertSame('NAO_ATENDE_RETRY', $contact->last_result);
        $this->assertTrue(now()->addMinutes(45)->equalTo($contact->next_attempt_at));
    }

    public function test_disposition_retry_rule_exhausts_contact_at_attempt_limit(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Lista Esgotada',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Cliente Esgotado',
            'phone' => '31999990057',
            'status' => 'dialing',
            'attempts_count' => 3,
            'next_attempt_at' => now()->addHour(),
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Esgotada',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        $disposition = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Nao atendeu final',
            'code' => 'NAO_ATENDE_FINAL',
            'category' => 'negative',
            'status' => 'active',
            'retry_enabled' => true,
            'retry_delay_minutes' => 60,
            'max_attempts' => 3,
        ]);
        $call = Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => $contact->phone,
            'started_at' => now(),
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
            ])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $contact->refresh();

        $this->assertSame('exhausted', $contact->status);
        $this->assertSame('NAO_ATENDE_FINAL', $contact->last_result);
        $this->assertNull($contact->next_attempt_at);
    }

    public function test_campaign_retry_settings_cap_attempts_and_extend_disposition_cooldown(): void
    {
        $this->travelTo(now()->setSecond(0)->setMicrosecond(0));

        [$agent, $company] = $this->tenantUser('agent');
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Lista Regra Campanha',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Cliente Regra Campanha',
            'phone' => '31999990058',
            'status' => 'dialing',
            'attempts_count' => 1,
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Regra Retry',
            'dialing_mode' => 'power',
            'status' => 'active',
            'max_attempts_per_contact' => 2,
            'retry_delay_minutes' => 60,
        ]);
        $disposition = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Retry pela campanha',
            'code' => 'RETRY_CAMPANHA',
            'category' => 'negative',
            'status' => 'active',
            'retry_enabled' => true,
            'retry_delay_minutes' => 15,
            'max_attempts' => 4,
        ]);
        $call = Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => $contact->phone,
            'started_at' => now(),
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
            ])
            ->assertRedirect(route('tenant.agent.index'));

        $contact->refresh();

        $this->assertSame('pending', $contact->status);
        $this->assertTrue(now()->addMinutes(60)->equalTo($contact->next_attempt_at));

        $contact->update(['status' => 'dialing', 'attempts_count' => 2]);
        $secondCall = $call->replicate(['status', 'ended_at', 'disposition_id']);
        $secondCall->status = 'in_progress';
        $secondCall->started_at = now();
        $secondCall->save();
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $secondCall->id,
                'disposition_id' => $disposition->id,
            ]);

        $this->assertSame('exhausted', $contact->fresh()->status);
    }

    public function test_callback_disposition_requires_callback_schedule(): void
    {
        [$agent] = $this->tenantUser('agent');
        $disposition = Disposition::create([
            'company_id' => $agent->company_id,
            'name' => 'Retorno obrigatorio',
            'code' => 'RETORNO_OBR',
            'category' => 'callback',
            'status' => 'active',
        ]);
        $call = Call::create([
            'company_id' => $agent->company_id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
            ])
            ->assertSessionHasErrors(['callback_at', 'callback_type']);

        $this->assertSame('in_progress', $call->fresh()->status);
    }

    public function test_supervisor_can_start_and_end_manual_extension_call_without_becoming_available_agent(): void
    {
        [$supervisor] = $this->tenantUser('supervisor');
        AgentStatus::create([
            'company_id' => $supervisor->company_id,
            'user_id' => $supervisor->id,
            'status' => 'available',
            'started_at' => now(),
        ]);
        $disposition = Disposition::create([
            'company_id' => $supervisor->company_id,
            'name' => 'Ligacao interna encerrada',
            'code' => 'MANUAL_OK',
            'category' => 'neutral',
            'status' => 'active',
        ]);

        $this->actingAs($supervisor)
            ->get(route('tenant.agent.index'))
            ->assertOk()
            ->assertSee('Ramal '.$supervisor->extension)
            ->assertSee('Aguardando chamada');

        $this->actingAs($supervisor)
            ->post(route('tenant.agent.calls.store'), [
                'phone' => '(31) 99999-0044',
                'channel' => 'manual',
            ])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $supervisor->refresh();
        $call = $supervisor->calls()->where('status', 'in_progress')->sole();

        $this->assertSame('in_call', $supervisor->currentAgentStatus->status);
        $this->assertSame('manual', $call->channel);
        $this->assertSame('31999990044', $call->phone);
        $this->assertSame(0, app(AgentCapacityService::class)->availableAgentsForCompany($supervisor->company_id));

        $this->actingAs($supervisor)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
            ])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $supervisor->refresh();
        $call->refresh();

        $this->assertSame('available', $supervisor->currentAgentStatus->status);
        $this->assertSame('ended', $call->status);
        $this->assertSame('MANUAL_OK', $call->disposition_code);
        $this->assertSame(0, app(AgentCapacityService::class)->availableAgentsForCompany($supervisor->company_id));
    }

    public function test_agent_must_use_active_disposition_from_own_company_to_end_call(): void
    {
        [$agent] = $this->tenantUser('agent');
        [$otherAgent] = $this->tenantUser('agent');
        $otherDisposition = Disposition::create([
            'company_id' => $otherAgent->company_id,
            'name' => 'Outra empresa',
            'code' => 'OUTRA',
            'category' => 'neutral',
            'status' => 'active',
        ]);
        $inactiveDisposition = Disposition::create([
            'company_id' => $agent->company_id,
            'name' => 'Inativa',
            'code' => 'INATIVA',
            'category' => 'neutral',
            'status' => 'inactive',
        ]);
        $call = Call::create([
            'company_id' => $agent->company_id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'started_at' => now(),
        ]);

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), ['call_id' => $call->id])
            ->assertSessionHasErrors('disposition_id');

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $otherDisposition->id,
            ])
            ->assertSessionHasErrors('disposition_id');

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $inactiveDisposition->id,
            ])
            ->assertSessionHasErrors('disposition_id');

        $this->assertSame('in_progress', $call->fresh()->status);
    }

    public function test_simulated_dialer_creates_campaign_call_for_available_agent_and_contact(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $team = Team::create(['company_id' => $company->id, 'name' => 'Fila Ativa', 'slug' => 'fila-ativa']);
        $list = ContactList::create(['company_id' => $company->id, 'name' => 'Mailing Ativo', 'status' => 'active']);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Discador',
            'phone' => '31999990010',
            'status' => 'pending',
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Discador',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);

        $team->users()->attach($agent, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $campaign->teams()->sync([$team->id => ['company_id' => $company->id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);
        $status = AgentStatus::create(['company_id' => $company->id, 'user_id' => $agent->id, 'status' => 'available', 'started_at' => now()]);

        $created = app(SimulatedDialerService::class)->runCampaign($campaign);

        $call = Call::where('campaign_id', $campaign->id)->sole();

        $this->assertSame(1, $created);
        $this->assertSame($agent->id, $call->user_id);
        $this->assertSame($contact->id, $call->contact_id);
        $this->assertSame('31999990010', $call->phone);
        $this->assertSame('in_progress', $call->status);
        $this->assertSame('simulated', $call->channel);
        $this->assertSame('in_call', $agent->fresh()->currentAgentStatus->status);
        $this->assertNotNull($status->fresh()->ended_at);
        $this->assertSame('dialing', $contact->fresh()->status);
        $this->assertSame(1, $contact->fresh()->attempts_count);
    }

    public function test_horizontal_strategy_dials_next_phone_from_same_lead_before_next_lead(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $company->licenses()->firstOrFail()->update(['max_calls_per_available_agent' => 1]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        $campaign->update(['lead_phone_strategy' => 'horizontal']);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Horizontal',
            'status' => 'active',
        ]);
        $firstLead = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Horizontal 1',
            'phone' => '31999991001',
            'status' => 'pending',
            'attempts_count' => 1,
        ]);
        $firstPrimary = $firstLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991001',
            'position' => 0,
            'is_primary' => true,
            'attempts_count' => 1,
            'last_attempt_at' => now()->subMinute(),
        ]);
        $firstAlternate = $firstLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991002',
            'position' => 1,
        ]);
        $secondLead = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Horizontal 2',
            'phone' => '31999992001',
            'status' => 'pending',
        ]);
        $secondPrimary = $secondLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999992001',
            'position' => 0,
            'is_primary' => true,
        ]);

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);
        Call::create([
            'company_id' => $company->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $firstLead->id,
            'contact_phone_id' => $firstPrimary->id,
            'direction' => 'outbound',
            'status' => 'cancelled',
            'channel' => 'simulated',
            'phone' => $firstPrimary->phone,
            'started_at' => now()->subMinute(),
            'ended_at' => now()->subMinute(),
        ]);

        $this->assertSame(1, app(SimulatedDialerService::class)->runCampaign($campaign));

        $call = Call::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'in_progress')
            ->sole();

        $this->assertSame($firstLead->id, $call->contact_id);
        $this->assertSame($firstAlternate->id, $call->contact_phone_id);
        $this->assertSame('31999991002', $call->phone);
        $this->assertSame(0, $secondPrimary->fresh()->attempts_count);
    }

    public function test_horizontal_strategy_prefers_lead_with_fewer_phone_attempts_before_oldest_lead_alternate(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $company->licenses()->firstOrFail()->update(['max_calls_per_available_agent' => 1]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        $campaign->update(['lead_phone_strategy' => 'horizontal']);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Horizontal Justo',
            'status' => 'active',
        ]);
        $oldLead = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Antigo',
            'phone' => '31999991401',
            'status' => 'pending',
            'attempts_count' => 2,
        ]);
        $oldLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991401',
            'position' => 0,
            'is_primary' => true,
            'status' => 'exhausted',
            'attempts_count' => 2,
        ]);
        $oldAlternate = $oldLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991402',
            'position' => 1,
            'attempts_count' => 2,
        ]);
        $freshLead = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Novo',
            'phone' => '31999991501',
            'status' => 'pending',
        ]);
        $freshPrimary = $freshLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991501',
            'position' => 0,
            'is_primary' => true,
        ]);

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);

        $this->assertSame(1, app(SimulatedDialerService::class)->runCampaign($campaign));

        $call = Call::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'in_progress')
            ->sole();

        $this->assertSame($freshLead->id, $call->contact_id);
        $this->assertSame($freshPrimary->id, $call->contact_phone_id);
        $this->assertSame(2, $oldAlternate->fresh()->attempts_count);
    }

    public function test_vertical_strategy_dials_next_lead_primary_before_first_lead_alternate(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $company->licenses()->firstOrFail()->update(['max_calls_per_available_agent' => 1]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        $campaign->update(['lead_phone_strategy' => 'vertical']);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Vertical',
            'status' => 'active',
        ]);
        $firstLead = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Vertical 1',
            'phone' => '31999993001',
            'status' => 'pending',
            'attempts_count' => 1,
        ]);
        $firstPrimary = $firstLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999993001',
            'position' => 0,
            'is_primary' => true,
            'attempts_count' => 1,
            'last_attempt_at' => now()->subMinute(),
        ]);
        $firstAlternate = $firstLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999993002',
            'position' => 1,
        ]);
        $secondLead = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Vertical 2',
            'phone' => '31999994001',
            'status' => 'pending',
        ]);
        $secondPrimary = $secondLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999994001',
            'position' => 0,
            'is_primary' => true,
        ]);

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);
        Call::create([
            'company_id' => $company->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $firstLead->id,
            'contact_phone_id' => $firstPrimary->id,
            'direction' => 'outbound',
            'status' => 'cancelled',
            'channel' => 'simulated',
            'phone' => $firstPrimary->phone,
            'started_at' => now()->subMinute(),
            'ended_at' => now()->subMinute(),
        ]);

        $this->assertSame(1, app(SimulatedDialerService::class)->runCampaign($campaign));

        $call = Call::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'in_progress')
            ->sole();

        $this->assertSame($secondLead->id, $call->contact_id);
        $this->assertSame($secondPrimary->id, $call->contact_phone_id);
        $this->assertSame('31999994001', $call->phone);
        $this->assertSame(0, $firstAlternate->fresh()->attempts_count);
    }

    public function test_anti_rechamada_skips_lead_after_any_phone_reached_agent_in_campaign(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        [$answeredAgent] = $this->tenantUser('agent', $company);
        $company->licenses()->firstOrFail()->update(['max_calls_per_available_agent' => 1]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        $campaign->update(['lead_phone_strategy' => 'horizontal']);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Anti Rechamada',
            'status' => 'active',
        ]);
        $answeredLead = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Atendido',
            'phone' => '31999995001',
            'status' => 'pending',
            'attempts_count' => 1,
        ]);
        $answeredPrimary = $answeredLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999995001',
            'position' => 0,
            'is_primary' => true,
            'attempts_count' => 1,
        ]);
        $answeredAlternate = $answeredLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999995002',
            'position' => 1,
        ]);
        $nextLead = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Seguro',
            'phone' => '31999996001',
            'status' => 'pending',
        ]);
        $nextPrimary = $nextLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999996001',
            'position' => 0,
            'is_primary' => true,
        ]);

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $answeredAgent->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $answeredLead->id,
            'contact_phone_id' => $answeredPrimary->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => $answeredPrimary->phone,
            'started_at' => now()->subMinutes(5),
            'answered_at' => now()->subMinutes(5),
            'ended_at' => now()->subMinutes(4),
        ]);

        $this->assertSame(1, app(SimulatedDialerService::class)->runCampaign($campaign));

        $call = Call::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'in_progress')
            ->sole();

        $this->assertSame($nextLead->id, $call->contact_id);
        $this->assertSame($nextPrimary->id, $call->contact_phone_id);
        $this->assertSame(0, $answeredAlternate->fresh()->attempts_count);
    }

    public function test_simulated_dialer_creates_campaign_call_with_selected_contact_phone(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $company->licenses()->firstOrFail()->update(['max_calls_per_available_agent' => 1]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Telefone Selecionado',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Telefone Selecionado',
            'phone' => '31999997001',
            'status' => 'pending',
        ]);
        $primary = $contact->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999997001',
            'position' => 0,
            'is_primary' => true,
        ]);

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);

        $this->assertSame(1, app(SimulatedDialerService::class)->runCampaign($campaign));

        $call = Call::where('campaign_id', $campaign->id)->sole();

        $this->assertSame($contact->id, $call->contact_id);
        $this->assertSame($primary->id, $call->contact_phone_id);
        $this->assertSame('31999997001', $call->phone);
        $this->assertSame(1, $primary->fresh()->attempts_count);
    }

    public function test_selected_contact_phone_tracks_tabulation_and_next_selection_after_retry_cooldown(): void
    {
        $this->travelTo(now()->setSecond(0)->setMicrosecond(0));

        [$agent, $company] = $this->tenantUser('agent');
        $company->licenses()->firstOrFail()->update(['max_calls_per_available_agent' => 1]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        $campaign->update(['lead_phone_strategy' => 'horizontal']);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Tabulacao Telefone',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Tabulacao Telefone',
            'phone' => '31999991601',
            'status' => 'pending',
        ]);
        $primary = $contact->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991601',
            'position' => 0,
            'is_primary' => true,
        ]);
        $alternate = $contact->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991602',
            'position' => 1,
        ]);
        $nextContact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Proximo Lead Tabulacao',
            'phone' => '31999991603',
            'status' => 'pending',
        ]);
        $nextPrimary = $nextContact->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991603',
            'position' => 0,
            'is_primary' => true,
        ]);
        $disposition = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Nao atendeu telefone',
            'code' => 'NAO_ATENDE_FONE',
            'category' => 'negative',
            'status' => 'active',
            'retry_enabled' => true,
            'retry_delay_minutes' => 45,
            'max_attempts' => 3,
        ]);

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);

        $this->assertSame(1, app(SimulatedDialerService::class)->runCampaign($campaign));

        $call = Call::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'in_progress')
            ->sole();

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
            ])
            ->assertRedirect(route('tenant.agent.index'));

        $primary->refresh();

        $this->assertSame('pending', $primary->status);
        $this->assertTrue(now()->addMinutes(45)->equalTo($primary->next_attempt_at));
        $this->assertSame($nextPrimary->id, app(MailingQueueService::class)->nextContactPhoneForCampaign($campaign)?->id);
        $this->assertSame(0, $alternate->fresh()->attempts_count);
    }

    public function test_retry_disposition_releases_answered_lead_after_cooldown(): void
    {
        $this->travelTo(now()->setSecond(0)->setMicrosecond(0));

        [$agent, $company] = $this->tenantUser('agent');
        $company->licenses()->firstOrFail()->update(['max_calls_per_available_agent' => 1]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Retry Completo',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Retry Completo',
            'phone' => '31999991611',
            'status' => 'pending',
        ]);
        $primary = $contact->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991611',
            'position' => 0,
            'is_primary' => true,
        ]);
        $disposition = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Retry autorizado',
            'code' => 'RETRY_AUTORIZADO',
            'category' => 'negative',
            'status' => 'active',
            'retry_enabled' => true,
            'retry_delay_minutes' => 15,
            'max_attempts' => 3,
        ]);

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);

        $this->assertSame(1, app(SimulatedDialerService::class)->runCampaign($campaign));

        $firstCall = Call::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'in_progress')
            ->sole();

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $firstCall->id,
                'disposition_id' => $disposition->id,
            ])
            ->assertRedirect(route('tenant.agent.index'));

        $this->assertSame(0, app(SimulatedDialerService::class)->runCampaign($campaign));

        $this->travel(16)->minutes();

        $this->assertSame(1, app(SimulatedDialerService::class)->runCampaign($campaign));
        $this->assertSame(2, Call::query()->where('campaign_id', $campaign->id)->count());
        $this->assertSame(
            $primary->id,
            Call::query()->where('campaign_id', $campaign->id)->latest('id')->value('contact_phone_id'),
        );
    }

    public function test_recycle_campaign_reopens_contact_phones_with_exhausted_queue_state(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Reciclagem Telefones',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Reciclado',
            'phone' => '31999991621',
            'status' => 'exhausted',
            'next_attempt_at' => now()->addDay(),
        ]);
        $primary = $contact->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991621',
            'position' => 0,
            'is_primary' => true,
            'status' => 'exhausted',
            'next_attempt_at' => now()->addDay(),
        ]);

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);

        $this->assertSame(1, app(MailingQueueService::class)->recycleCampaign($campaign));

        $this->assertSame('pending', $contact->fresh()->status);
        $this->assertNull($contact->fresh()->next_attempt_at);
        $this->assertSame('pending', $primary->fresh()->status);
        $this->assertNull($primary->fresh()->next_attempt_at);
        $this->assertSame($primary->id, app(MailingQueueService::class)->nextContactPhoneForCampaign($campaign)?->id);
    }

    public function test_selected_contact_phone_is_answered_when_tabulation_succeeds(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $company->licenses()->firstOrFail()->update(['max_calls_per_available_agent' => 1]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Sucesso Telefone',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Sucesso Telefone',
            'phone' => '31999991701',
            'status' => 'pending',
        ]);
        $primary = $contact->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991701',
            'position' => 0,
            'is_primary' => true,
        ]);
        $disposition = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Venda telefone',
            'code' => 'VENDA_FONE',
            'category' => 'success',
            'status' => 'active',
            'is_success' => true,
        ]);

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);

        $this->assertSame(1, app(SimulatedDialerService::class)->runCampaign($campaign));

        $call = Call::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'in_progress')
            ->sole();

        $this->actingAs($agent)
            ->patch(route('tenant.agent.calls.end'), [
                'call_id' => $call->id,
                'disposition_id' => $disposition->id,
            ])
            ->assertRedirect(route('tenant.agent.index'));

        $primary->refresh();

        $this->assertSame('answered', $primary->status);
        $this->assertNull($primary->next_attempt_at);
        $this->assertNull(app(MailingQueueService::class)->nextContactPhoneForCampaign($campaign));
    }

    public function test_simulated_dialer_creates_concurrent_batches_with_one_winner_per_agent(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        [$secondAgent] = $this->tenantUser('agent', $company);
        $company->licenses()->firstOrFail()->update([
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 6,
        ]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent, $secondAgent]);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Concorrente',
            'status' => 'active',
        ]);

        foreach (range(1, 6) as $index) {
            $list->contacts()->create([
                'company_id' => $company->id,
                'name' => 'Lead Concorrente '.$index,
                'phone' => '3199999010'.$index,
                'status' => 'pending',
            ]);
        }

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);

        $created = app(SimulatedDialerService::class)->runCampaign($campaign);
        $calls = Call::query()
            ->where('campaign_id', $campaign->id)
            ->orderBy('id')
            ->get();
        $winners = $calls->where('status', 'in_progress');
        $cancelled = $calls->where('status', 'cancelled');

        $this->assertSame(6, $created);
        $this->assertCount(6, $calls);
        $this->assertCount(2, $winners);
        $this->assertCount(4, $cancelled);
        $this->assertSame(4, $cancelled->where('end_reason', 'answered_elsewhere')->count());
        $this->assertSame([$agent->id, $secondAgent->id], $winners->pluck('user_id')->sort()->values()->all());
        $this->assertTrue($cancelled->every(fn (Call $call) => $call->answered_at === null));
        $this->assertTrue($cancelled->every(fn (Call $call) => $call->ended_at !== null));
        $this->assertSame(6, $list->contacts()->where('attempts_count', 1)->count());
        $this->assertSame('in_call', $agent->fresh()->currentAgentStatus->status);
        $this->assertSame('in_call', $secondAgent->fresh()->currentAgentStatus->status);
    }

    public function test_simulated_dialer_cancelled_batch_phone_remains_pending_after_retry_delay(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $company->licenses()->firstOrFail()->update([
            'max_calls_per_available_agent' => 2,
            'max_concurrent_calls' => 2,
        ]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        $campaign->update(['lead_phone_strategy' => 'horizontal']);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Cancelado Sem Atendimento',
            'status' => 'active',
        ]);
        $answeredLead = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Atendido no Batch',
            'phone' => '31999991101',
            'status' => 'pending',
        ]);
        $answeredPrimary = $answeredLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991101',
            'position' => 0,
            'is_primary' => true,
        ]);
        $cancelledLead = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Cancelado no Batch',
            'phone' => '31999991201',
            'status' => 'pending',
        ]);
        $cancelledPrimary = $cancelledLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991201',
            'position' => 0,
            'is_primary' => true,
        ]);
        $cancelledAlternate = $cancelledLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991202',
            'position' => 1,
        ]);
        $nextLead = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Seguinte',
            'phone' => '31999991301',
            'status' => 'pending',
        ]);
        $nextPrimary = $nextLead->phones()->create([
            'company_id' => $company->id,
            'phone' => '31999991301',
            'position' => 0,
            'is_primary' => true,
        ]);

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);

        $this->assertSame(2, app(SimulatedDialerService::class)->runCampaign($campaign));

        $winner = Call::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'in_progress')
            ->sole();
        $cancelled = Call::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'cancelled')
            ->sole();
        $queue = app(MailingQueueService::class);

        $this->assertSame($answeredLead->id, $winner->contact_id);
        $this->assertSame($answeredPrimary->id, $winner->contact_phone_id);
        $this->assertNotNull($winner->answered_at);
        $this->assertSame($cancelledLead->id, $cancelled->contact_id);
        $this->assertSame($cancelledPrimary->id, $cancelled->contact_phone_id);
        $this->assertNull($cancelled->answered_at);
        $this->assertSame('pending', $cancelledLead->fresh()->status);
        $this->assertSame('pending', $cancelledPrimary->fresh()->status);
        $this->assertSame($nextPrimary->id, $queue->nextContactPhoneForCampaign($campaign)?->id);

        $this->travel(31)->minutes();

        $this->assertSame($cancelledAlternate->id, $queue->nextContactPhoneForCampaign($campaign)?->id);
    }

    public function test_simulated_dialer_shares_global_limit_with_another_campaign(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $company->licenses()->firstOrFail()->update([
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 4,
        ]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        $otherCampaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha em andamento',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        Call::create([
            'company_id' => $company->id,
            'campaign_id' => $otherCampaign->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'started_at' => now(),
        ]);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Teto Compartilhado',
            'status' => 'active',
        ]);

        foreach (range(1, 5) as $index) {
            $list->contacts()->create([
                'company_id' => $company->id,
                'name' => 'Lead Teto '.$index,
                'phone' => '3199999030'.$index,
                'status' => 'pending',
            ]);
        }

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);

        $this->assertSame(3, app(SimulatedDialerService::class)->runCampaign($campaign));
        $this->assertSame(4, Call::where('company_id', $company->id)->count());
    }

    public function test_simulated_dialer_does_not_consume_capacity_from_another_company(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        [$otherAgent] = $this->tenantUser('agent');
        $company->licenses()->firstOrFail()->update([
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 3,
        ]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        Call::create([
            'company_id' => $otherAgent->company_id,
            'user_id' => $otherAgent->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'started_at' => now(),
        ]);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Isolado',
            'status' => 'active',
        ]);

        foreach (range(1, 3) as $index) {
            $list->contacts()->create([
                'company_id' => $company->id,
                'name' => 'Lead Isolado '.$index,
                'phone' => '3199999040'.$index,
                'status' => 'pending',
            ]);
        }

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);

        $this->assertSame(3, app(SimulatedDialerService::class)->runCampaign($campaign));
    }

    public function test_simulated_dialer_reduces_batch_when_contacts_are_limited(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $company->licenses()->firstOrFail()->update([
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 3,
        ]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent]);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Curto',
            'status' => 'active',
        ]);

        foreach (range(1, 2) as $index) {
            $list->contacts()->create([
                'company_id' => $company->id,
                'name' => 'Lead Curto '.$index,
                'phone' => '3199999050'.$index,
                'status' => 'pending',
            ]);
        }

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);

        $this->assertSame(2, app(SimulatedDialerService::class)->runCampaign($campaign));
        $this->assertSame(1, Call::where('campaign_id', $campaign->id)->where('status', 'in_progress')->count());
        $this->assertSame(1, Call::where('campaign_id', $campaign->id)->where('status', 'cancelled')->count());
    }

    public function test_simulated_dialer_never_assigns_automatic_batches_to_non_agents(): void
    {
        [$supervisor, $company] = $this->tenantUser('supervisor');
        [$admin] = $this->tenantUser('company_admin', $company);
        $team = Team::create([
            'company_id' => $company->id,
            'name' => 'Equipe Gestores',
            'slug' => 'equipe-gestores',
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Gestores',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Gestores',
            'status' => 'active',
        ]);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Gestores',
            'phone' => '31999990601',
            'status' => 'pending',
        ]);

        $team->users()->attach($supervisor, ['company_id' => $company->id, 'membership_type' => 'supervisor']);
        $team->users()->attach($admin, ['company_id' => $company->id, 'membership_type' => 'supervisor']);
        $campaign->teams()->sync([$team->id => ['company_id' => $company->id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $supervisor->id, 'status' => 'available', 'started_at' => now()]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $admin->id, 'status' => 'available', 'started_at' => now()]);

        $this->assertSame(0, app(SimulatedDialerService::class)->runCampaign($campaign));
        $this->assertSame('pending', $contact->fresh()->status);
        $this->assertSame(0, Call::where('campaign_id', $campaign->id)->count());
    }

    public function test_simulated_dialer_locks_license_first_and_revalidates_agent_before_creating_winner(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $team = Team::create(['company_id' => $company->id, 'name' => 'Fila Lock', 'slug' => 'fila-lock']);
        $list = ContactList::create(['company_id' => $company->id, 'name' => 'Mailing Lock', 'status' => 'active']);
        $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Lock',
            'phone' => '31999990199',
            'status' => 'pending',
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Lock',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);

        $team->users()->attach($agent, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $campaign->teams()->sync([$team->id => ['company_id' => $company->id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = str_replace(['`', '"'], '', strtolower($query->sql));
        });

        app(SimulatedDialerService::class)->runCampaign($campaign);

        $this->assertStringContainsString('from company_licenses', $queries[0]);

        if (DB::getDriverName() === 'mysql') {
            $this->assertStringContainsString('for update', $queries[0]);
        }

        $userLockIndex = collect($queries)->search(
            fn (string $sql): bool => str_contains($sql, 'from users')
                && str_contains($sql, 'users.id = ?')
                && str_contains($sql, 'limit 1')
        );
        $callInsertIndex = collect($queries)->search(
            fn (string $sql): bool => str_contains($sql, 'insert into calls')
        );

        $this->assertNotFalse($userLockIndex);
        $this->assertNotFalse($callInsertIndex);
        $this->assertTrue($userLockIndex < $callInsertIndex);
        $this->assertStringContainsString('status = ?', $queries[$userLockIndex]);
        $this->assertStringContainsString('user_type = ?', $queries[$userLockIndex]);
        $this->assertStringContainsString('agent_statuses', $queries[$userLockIndex]);
        $this->assertStringContainsString('calls', $queries[$userLockIndex]);
        $this->assertStringContainsString('disposition_id is null', $queries[$userLockIndex]);

        if (DB::getDriverName() === 'mysql') {
            $this->assertStringContainsString('for update', $queries[$userLockIndex]);
        }
    }

    public function test_simulated_dialer_serializes_two_mariadb_workers(): void
    {
        if (env('RUN_MARIADB_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Set RUN_MARIADB_CONCURRENCY_TEST=1 to run the MariaDB concurrency test.');
        }

        $this->assertSame('mysql', DB::getDriverName());
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName());
        $this->assertSame(
            'REPEATABLE-READ',
            DB::selectOne('select @@transaction_isolation as isolation_level')->isolation_level,
        );

        [$agent, $company] = $this->tenantUser('agent');
        [$secondAgent] = $this->tenantUser('agent', $company);
        $company->licenses()->firstOrFail()->update([
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 6,
        ]);
        $campaign = $this->campaignWithAvailableAgents($company, [$agent, $secondAgent]);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Mailing Concorrencia MariaDB',
            'status' => 'active',
        ]);

        foreach (range(1, 6) as $index) {
            $list->contacts()->create([
                'company_id' => $company->id,
                'name' => 'Lead MariaDB '.$index,
                'phone' => '3199999020'.$index,
                'status' => 'pending',
            ]);
        }

        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);
        DB::commit();

        $barrier = storage_path('framework/testing/dialer-concurrency-'.uniqid());
        mkdir($barrier, 0777, true);
        $workerCode = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
touch($argv[2].'/'.getmypid().'.ready');
while (! file_exists($argv[2].'/go')) {
    usleep(10000);
}
$campaign = App\Models\Campaign::findOrFail((int) $argv[1]);
echo app(App\Services\Dialer\SimulatedDialerService::class)->runCampaign($campaign);
PHP;
        $environment = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => DB::connection()->getDatabaseName(),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
        ];
        $workers = [
            new Process([PHP_BINARY, '-r', $workerCode, (string) $campaign->id, $barrier], base_path(), $environment),
            new Process([PHP_BINARY, '-r', $workerCode, (string) $campaign->id, $barrier], base_path(), $environment),
        ];

        try {
            foreach ($workers as $worker) {
                $worker->start();
            }

            $deadline = microtime(true) + 15;

            while (count(glob($barrier.'/*.ready')) < 2 && microtime(true) < $deadline) {
                usleep(10000);
            }

            $this->assertCount(2, glob($barrier.'/*.ready'));
            touch($barrier.'/go');

            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            }

            $this->assertSame(6, array_sum(array_map(
                fn (Process $worker): int => (int) trim($worker->getOutput()),
                $workers,
            )));
            $this->assertSame(6, Call::where('campaign_id', $campaign->id)->count());
            $this->assertSame(2, Call::where('campaign_id', $campaign->id)->where('status', 'in_progress')->count());
            $this->assertSame(4, Call::where('campaign_id', $campaign->id)->where('status', 'cancelled')->count());
            $this->assertSame(6, $list->contacts()->where('attempts_count', 1)->count());
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }

            foreach (glob($barrier.'/*') as $file) {
                unlink($file);
            }

            rmdir($barrier);
            Artisan::call('migrate:fresh', ['--force' => true]);
            DB::beginTransaction();
        }
    }

    public function test_simulated_dialer_rejects_agent_with_ended_call_without_disposition(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $team = Team::create(['company_id' => $company->id, 'name' => 'Fila Sem Tabulacao', 'slug' => 'fila-sem-tabulacao']);
        $list = ContactList::create(['company_id' => $company->id, 'name' => 'Mailing Sem Tabulacao', 'status' => 'active']);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Sem Tabulacao',
            'phone' => '31999990299',
            'status' => 'pending',
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Sem Tabulacao',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);

        $team->users()->attach($agent, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $campaign->teams()->sync([$team->id => ['company_id' => $company->id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $agent->id, 'status' => 'available', 'started_at' => now()]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990298',
            'started_at' => now()->subMinute(),
            'ended_at' => now(),
        ]);

        $created = app(SimulatedDialerService::class)->runCampaign($campaign);

        $this->assertSame(0, $created);
        $this->assertSame('pending', $contact->fresh()->status);
        $this->assertSame(1, Call::where('company_id', $company->id)->count());
        $this->assertSame('available', $agent->fresh()->currentAgentStatus->status);
    }

    public function test_simulated_dialer_does_not_assign_new_call_to_agent_with_untabulated_call(): void
    {
        [$agent, $company] = $this->tenantUser('agent');
        $team = Team::create(['company_id' => $company->id, 'name' => 'Fila Bloqueada', 'slug' => 'fila-bloqueada']);
        $list = ContactList::create(['company_id' => $company->id, 'name' => 'Mailing Bloqueado', 'status' => 'active']);
        $contact = $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead Pendente',
            'phone' => '31999990011',
            'status' => 'pending',
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Bloqueada',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);

        $team->users()->attach($agent, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $campaign->teams()->sync([$team->id => ['company_id' => $company->id]]);
        $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id]]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $agent->id, 'status' => 'available', 'started_at' => now()]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => '31999990000',
            'started_at' => now(),
        ]);

        $created = app(SimulatedDialerService::class)->runCampaign($campaign);

        $this->assertSame(0, $created);
        $this->assertSame('pending', $contact->fresh()->status);
        $this->assertSame(1, Call::where('company_id', $company->id)->count());
    }

    private function campaignWithAvailableAgents(Company $company, array $agents): Campaign
    {
        $team = Team::create([
            'company_id' => $company->id,
            'name' => 'Equipe Capacidade '.fake()->unique()->numberBetween(1000, 9999),
            'slug' => 'equipe-capacidade-'.fake()->unique()->numberBetween(1000, 9999),
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Capacidade Restante',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);

        foreach ($agents as $agent) {
            $team->users()->attach($agent, [
                'company_id' => $company->id,
                'membership_type' => 'agent',
            ]);
            AgentStatus::create([
                'company_id' => $company->id,
                'user_id' => $agent->id,
                'status' => 'available',
                'started_at' => now(),
            ]);
        }

        $campaign->teams()->sync([$team->id => ['company_id' => $company->id]]);

        return $campaign;
    }

    private function tenantUser(string $type, ?Company $company = null): array
    {
        $company ??= Company::create(['name' => 'Tenant Agente']);

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
            'name' => 'Operacao '.fake()->unique()->numberBetween(1000, 9999),
            'slug' => 'operacao-'.fake()->unique()->numberBetween(1000, 9999),
            'is_admin' => true,
        ]);
        $role->permissions()->attach(Permission::query()->firstOrCreate(
            ['key' => 'agent.access'],
            ['name' => 'Acessar', 'module' => 'agent'],
        ));
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => $type,
            'extension' => fake()->unique()->numerify('2###'),
            'status' => 'active',
        ]);

        return [$user, $company];
    }
}
