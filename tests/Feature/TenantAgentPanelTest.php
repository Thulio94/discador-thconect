<?php

namespace Tests\Feature;

use App\Models\AgentPause;
use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\ContactList;
use App\Models\Disposition;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Dialer\AgentCapacityService;
use App\Services\Dialer\SimulatedDialerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $service = app(AgentCapacityService::class);

        $this->assertSame(2, $service->availableAgentsForCampaign($campaign));
        $this->assertSame(4, $service->allowedCallsForCampaign($campaign));
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
            ->assertSee('Chamada simulada')
            ->assertSee('agent-pause')
            ->assertSee('Venda concluida')
            ->assertSee('call-disposition')
            ->assertSee('Retorno')
            ->assertSee('Campos do atendimento')
            ->assertDontSee('Modulo em breve');
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
            ->assertSee('Ficha do contato')
            ->assertSee('Cliente Ficha')
            ->assertSee('31999990030')
            ->assertSee('Campanha Ficha')
            ->assertSee('Lista Ouro')
            ->assertSee('Tentativas')
            ->assertSee('2')
            ->assertSee('Historico do contato')
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
            ->assertSee('Ultimas chamadas')
            ->assertSee('31999990088')
            ->assertSee('Retorno marcado')
            ->assertSee('Retorno publico')
            ->assertSee('31999990077')
            ->assertSee('Venda fechada')
            ->assertDontSee('31999990099');
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
            ->assertSee('Chamada manual simulada');

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

        $this->assertSame('offline', $supervisor->currentAgentStatus->status);
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
