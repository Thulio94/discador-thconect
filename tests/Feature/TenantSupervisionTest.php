<?php

namespace Tests\Feature;

use App\Models\AgentStatus;
use App\Models\AuditLog;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\CompanyTelephonyConfiguration;
use App\Models\OperationalParameter;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TenantSupervisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervision_route_loads_real_panel_and_keeps_tenant_isolation(): void
    {
        [$supervisor, $company] = $this->tenantUser('supervisor');
        [$agent] = $this->tenantUser('agent', $company, 'Agente Tenant');
        [$otherAgent] = $this->tenantUser('agent', null, 'Agente Outra Empresa');

        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);
        AgentStatus::create([
            'company_id' => $otherAgent->company_id,
            'user_id' => $otherAgent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.index'))
            ->assertOk()
            ->assertSee('Supervisao')
            ->assertSee('Agente Tenant')
            ->assertSee('Agentes em operacao')
            ->assertSee('supervision-agent-'.$agent->id, false)
            ->assertDontSee('Agente Outra Empresa')
            ->assertDontSee('Ouvir (em breve)')
            ->assertDontSee('Sussurrar (em breve)')
            ->assertDontSee('Intervir (em breve)');
    }

    public function test_supervision_metrics_filters_and_active_calls(): void
    {
        [$supervisor, $company] = $this->tenantUser('supervisor');
        [$available] = $this->tenantUser('agent', $company, 'Agente Disponivel');
        [$paused] = $this->tenantUser('agent', $company, 'Agente Pausado');
        [$inCall] = $this->tenantUser('agent', $company, 'Agente Em Chamada');
        [$wrapUp] = $this->tenantUser('agent', $company, 'Agente Tabulando');
        [$offline] = $this->tenantUser('agent', $company, 'Agente Offline');
        $team = Team::create(['company_id' => $company->id, 'name' => 'Equipe Filtro', 'slug' => 'equipe-filtro']);
        $otherTeam = Team::create(['company_id' => $company->id, 'name' => 'Equipe Outra', 'slug' => 'equipe-outra']);

        $team->users()->attach($available, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $team->users()->attach($paused, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $otherTeam->users()->attach($inCall, ['company_id' => $company->id, 'membership_type' => 'agent']);

        AgentStatus::create(['company_id' => $company->id, 'user_id' => $available->id, 'status' => 'available', 'started_at' => now()]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $paused->id, 'status' => 'paused', 'started_at' => now()]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $inCall->id, 'status' => 'in_call', 'started_at' => now()]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $wrapUp->id, 'status' => 'wrap_up', 'started_at' => now()]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $inCall->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => '31999990001',
            'started_at' => now(),
        ]);

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.index'))
            ->assertOk()
            ->assertSee('Disponiveis')
            ->assertSee('Em pausa')
            ->assertSee('Em chamada')
            ->assertSee('Em tabulacao')
            ->assertSee('Offline')
            ->assertSee('Chamadas ativas')
            ->assertSee('31999990001')
            ->assertSee('Disponivel')
            ->assertSee('1')
            ->assertSee('em pausa')
            ->assertSee('em chamada')
            ->assertSee('offline');

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.index', ['status' => 'paused']))
            ->assertOk()
            ->assertSee('Agente Pausado')
            ->assertDontSee('Agente Disponivel')
            ->assertDontSee('Agente Em Chamada');

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.index', ['team_id' => $team->id]))
            ->assertOk()
            ->assertSee('Agente Disponivel')
            ->assertSee('Agente Pausado')
            ->assertDontSee('Agente Em Chamada');
    }

    public function test_supervision_shows_operational_times_current_campaign_and_alerts(): void
    {
        [$supervisor, $company] = $this->tenantUser('supervisor');
        [$paused] = $this->tenantUser('agent', $company, 'Agente Pausa Longa');
        [$inCall] = $this->tenantUser('agent', $company, 'Agente Chamada Longa');
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Monitorada',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);

        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $paused->id,
            'status' => 'paused',
            'started_at' => now()->subMinutes(25),
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $inCall->id,
            'status' => 'in_call',
            'started_at' => now()->subMinutes(18),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $inCall->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => '31999990033',
            'started_at' => now()->subMinutes(18),
        ]);

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.index'))
            ->assertOk()
            ->assertSee('Tempo no status')
            ->assertSee('25 min')
            ->assertSee('Pausa longa')
            ->assertSee('Tempo em chamada')
            ->assertSee('18 min')
            ->assertSee('Chamada longa')
            ->assertSee('Campanha atual')
            ->assertSee('Campanha Monitorada')
            ->assertSee('31999990033');
    }

    public function test_supervision_uses_company_operational_parameter_thresholds(): void
    {
        [$supervisor, $company] = $this->tenantUser('supervisor');
        [$paused] = $this->tenantUser('agent', $company, 'Agente Limite Pausa');
        [$inCall] = $this->tenantUser('agent', $company, 'Agente Limite Chamada');

        OperationalParameter::create([
            'company_id' => $company->id,
            'min_pause_minutes' => 0,
            'max_pause_minutes' => 20,
            'pause_alert_minutes' => 30,
            'call_alert_minutes' => 10,
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $paused->id,
            'status' => 'paused',
            'started_at' => now()->subMinutes(25),
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $inCall->id,
            'status' => 'in_call',
            'started_at' => now()->subMinutes(12),
        ]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $inCall->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => '31999990044',
            'started_at' => now()->subMinutes(12),
        ]);

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.index'))
            ->assertOk()
            ->assertSee('Pausa excedida')
            ->assertSee('Chamada longa')
            ->assertDontSee('Pausa longa');
    }

    public function test_supervision_poll_returns_filtered_tenant_fragments_with_no_store_headers(): void
    {
        [$supervisor, $company] = $this->tenantUser('supervisor');
        [$paused] = $this->tenantUser('agent', $company, 'Agente Polling');
        [$otherAgent] = $this->tenantUser('agent', null, 'Agente Externo Polling');
        $team = Team::create([
            'company_id' => $company->id,
            'name' => 'Equipe Polling',
            'slug' => 'equipe-polling',
            'status' => 'active',
        ]);
        $team->users()->attach($paused, [
            'company_id' => $company->id,
            'membership_type' => 'agent',
        ]);

        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $paused->id,
            'status' => 'paused',
            'started_at' => now()->subMinutes(25),
        ]);
        AgentStatus::create([
            'company_id' => $otherAgent->company_id,
            'user_id' => $otherAgent->id,
            'status' => 'paused',
            'started_at' => now(),
        ]);

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.poll', [
                'status' => 'paused',
                'team_id' => $team->id,
            ]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Poll-Interval', '10')
            ->assertSee('id="supervision-metrics"', false)
            ->assertSee('id="supervision-agents"', false)
            ->assertSee('Agente Polling')
            ->assertSee('Pausa longa')
            ->assertDontSee('Agente Externo Polling')
            ->assertDontSee('<html', false);
    }

    public function test_supervision_poll_rejects_team_from_another_company(): void
    {
        [$supervisor] = $this->tenantUser('supervisor');
        [, $otherCompany] = $this->tenantUser('agent');
        $otherTeam = Team::create([
            'company_id' => $otherCompany->id,
            'name' => 'Equipe Externa',
            'slug' => 'equipe-externa',
            'status' => 'active',
        ]);

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.poll', ['team_id' => $otherTeam->id]))
            ->assertNotFound();
    }

    public function test_supervisor_can_start_asterisk_monitoring_for_an_active_agent_call(): void
    {
        config()->set('services.asterisk_bridge.token', 'bridge-test-token');
        config()->set('services.asterisk_bridge.webhook_secret', 'bridge-webhook-secret');
        config()->set('services.asterisk_bridge.url', 'http://asterisk-bridge.test');

        [$supervisor, $company] = $this->tenantUser('supervisor');
        $supervisor->role->permissions()->syncWithoutDetaching(
            Permission::query()->where('key', 'supervision.operate')->pluck('id')
        );
        [$agent] = $this->tenantUser('agent', $company, 'Agente Monitorado');

        $call = Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'asterisk_ami',
            'phone' => '31999990001',
            'telephony_channel' => 'PJSIP/1301-0000001a',
            'started_at' => now(),
        ]);
        CompanyTelephonyConfiguration::create([
            'company_id' => $company->id,
            'driver' => 'asterisk_ami',
            'ami_host' => 'pbx.empresa.test',
            'ami_port' => 5038,
            'ami_username' => 'discador',
            'ami_secret' => 'segredo',
            'outbound_channel_template' => 'PJSIP/{phone}@tronco',
            'supervisor_channel_template' => 'PJSIP/{extension}',
            'dialplan_context' => 'discador',
            'dialplan_extension' => 's',
            'bridge_enabled' => true,
            'connection_status' => 'online',
        ]);
        Http::fake([
            'http://asterisk-bridge.test/*' => Http::response(['accepted' => true], 202),
        ]);

        $this->actingAs($supervisor)
            ->post(route('tenant.supervision.monitor', $agent), ['mode' => 'whisper'])
            ->assertRedirect(route('tenant.supervision.index'))
            ->assertSessionHas('status', 'Sussurrar solicitado para a chamada de Agente Monitorado.');

        Http::assertSent(function (ClientRequest $request) use ($company, $call, $supervisor): bool {
            return $request->url() === 'http://asterisk-bridge.test/v1/calls/supervise'
                && $request->method() === 'POST'
                && $request['company_id'] === $company->id
                && $request['call_id'] === $call->id
                && $request['agent_channel'] === 'PJSIP/1301-0000001a'
                && $request['supervisor_extension'] === $supervisor->extension
                && $request['mode'] === 'whisper';
        });
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'actor_user_id' => $supervisor->id,
            'event' => 'supervision.monitoring_started',
            'action' => 'whisper',
            'subject_type' => $agent->getMorphClass(),
            'subject_id' => $agent->id,
        ]);
        $this->assertSame(
            'whisper',
            AuditLog::query()->where('event', 'supervision.monitoring_started')->sole()->metadata['mode']
        );
    }

    public function test_supervisor_cannot_monitor_simulated_or_unconfigured_calls(): void
    {
        [$supervisor, $company] = $this->tenantUser('supervisor');
        $supervisor->role->permissions()->syncWithoutDetaching(
            Permission::query()->where('key', 'supervision.operate')->pluck('id')
        );
        [$agent] = $this->tenantUser('agent', $company, 'Agente Sem AMI');
        Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => '31999990002',
            'started_at' => now(),
        ]);
        Http::fake();

        $this->actingAs($supervisor)
            ->from(route('tenant.supervision.index'))
            ->post(route('tenant.supervision.monitor', $agent), ['mode' => 'listen'])
            ->assertRedirect(route('tenant.supervision.index'))
            ->assertSessionHasErrors('monitoring');

        Http::assertNothingSent();
    }

    private function tenantUser(string $type, ?Company $company = null, ?string $name = null): array
    {
        $company ??= Company::create(['name' => 'Tenant Supervisao '.fake()->unique()->numberBetween(1000, 9999)]);

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
            ['key' => 'supervision.read'],
            ['name' => 'Ler', 'module' => 'supervision'],
        ));

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => $name ?? fake()->name(),
            'user_type' => $type,
            'extension' => fake()->unique()->numerify('3###'),
            'status' => 'active',
        ]);

        return [$user, $company];
    }
}
