<?php

namespace Tests\Feature;

use App\Models\AgentPause;
use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Dialer\AgentCapacityService;
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

        $this->actingAs($agent)
            ->get(route('tenant.agent.index'))
            ->assertOk()
            ->assertSee('Painel do Agente')
            ->assertSee('Disponivel')
            ->assertSee('Pausa')
            ->assertSee('Offline')
            ->assertSee('Chamada simulada')
            ->assertSee('agent-pause')
            ->assertDontSee('Modulo em breve');
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
            ->patch(route('tenant.agent.calls.end'), ['call_id' => $call->id])
            ->assertRedirect(route('tenant.agent.index'))
            ->assertSessionHas('status');

        $agent->refresh();

        $this->assertSame('available', $agent->currentAgentStatus->status);
        $this->assertSame('ended', $call->fresh()->status);
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
