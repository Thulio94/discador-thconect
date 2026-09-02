<?php

namespace Tests\Feature;

use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\CallEvent;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TelephonyRoute;
use App\Models\User;
use App\Services\Telephony\AsteriskCallEventService;
use App\Services\Dialer\CallWrapUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTelephonyConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_view_the_super_admin_assigned_route_without_credentials(): void
    {
        $admin = $this->tenantAdmin();
        $route = TelephonyRoute::create([
            'name' => 'Rota centralizada',
            'tech' => '5006',
            'host' => 'sbc.solutionsvoip.com.br',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw,g729',
            'enabled' => true,
            'connection_status' => 'online',
        ]);
        $admin->company->telephonyRoutes()->attach($route);

        $this->actingAs($admin)
            ->get(route('tenant.telephony.index'))
            ->assertOk()
            ->assertSee('Rota centralizada')
            ->assertSee('Tech')
            ->assertDontSee('Configuracao de telefonia')
            ->assertDontSee('segredo');
    }

    public function test_telephony_preparation_is_isolated_by_company(): void
    {
        $admin = $this->tenantAdmin();
        $otherCompany = Company::create(['name' => 'Outra Empresa']);
        $otherRoute = TelephonyRoute::create([
            'name' => 'Tronco da Outra Empresa',
            'tech' => '7033',
            'host' => 'outra.operadora.test',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw,g729',
        ]);
        $otherCompany->telephonyRoutes()->attach($otherRoute);

        $this->actingAs($admin)
            ->get(route('tenant.telephony.index'))
            ->assertOk()
            ->assertDontSee('Tronco da Outra Empresa');
    }

    public function test_signed_asterisk_event_updates_only_the_matching_company_call(): void
    {
        config()->set('services.asterisk_bridge.webhook_secret', 'webhook-test-secret');
        $admin = $this->tenantAdmin();
        $call = Call::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'direction' => 'outbound',
            'status' => 'dialing',
            'channel' => 'asterisk_ami',
            'phone' => '31999990001',
            'started_at' => now(),
        ]);
        $payload = [
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'answered',
            'channel' => 'PJSIP/rota-0001',
            'uniqueid' => '1720000000.1',
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;

        $this->postJson(route('bridge.events'), $payload, [
            'X-THCONECT-Timestamp' => $timestamp,
            'X-THCONECT-Signature' => hash_hmac('sha256', $timestamp.'.'.$json, 'webhook-test-secret'),
        ])->assertOk();

        $call->refresh();
        $this->assertSame('in_progress', $call->status);
        $this->assertSame('PJSIP/rota-0001', $call->telephony_channel);
        $this->assertNotNull($call->answered_at);
        $this->assertDatabaseHas('call_events', [
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'answered',
        ]);
    }

    public function test_replayed_telephony_event_is_recorded_once(): void
    {
        $admin = $this->tenantAdmin();
        $call = Call::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'direction' => 'outbound',
            'status' => 'dialing',
            'channel' => 'asterisk_ami',
            'phone' => '31999990002',
            'started_at' => now(),
        ]);
        $payload = [
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'originate_succeeded',
            'uniqueid' => '1720000000.2',
        ];

        app(AsteriskCallEventService::class)->apply($payload);
        app(AsteriskCallEventService::class)->apply($payload);

        $this->assertSame(1, CallEvent::query()->where('call_id', $call->id)->count());
        $this->assertSame('ringing', $call->fresh()->status);
    }

    public function test_failed_originate_releases_agent_instead_of_leaving_in_call(): void
    {
        $admin = $this->tenantAdmin();
        $call = Call::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'direction' => 'outbound',
            'status' => 'dialing',
            'channel' => 'asterisk_ami',
            'phone' => '31999990003',
            'started_at' => now(),
        ]);
        AgentStatus::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);

        app(AsteriskCallEventService::class)->apply([
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'originate_failed',
            'cause' => 'network_congestion',
            'uniqueid' => '1720000000.3',
        ]);

        $this->assertSame('cancelled', $call->fresh()->status);
        $this->assertSame('available', $admin->fresh()->currentAgentStatus->status);
    }

    public function test_stale_ami_call_is_cancelled_and_releases_the_agent_when_its_terminal_event_is_missing(): void
    {
        $admin = $this->tenantAdmin();
        $call = Call::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'direction' => 'outbound',
            'status' => 'ringing',
            'channel' => 'asterisk_ami',
            'phone' => '31999990004',
            'started_at' => now()->subSeconds(51),
        ]);
        AgentStatus::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'status' => 'dialing',
            'started_at' => now()->subSeconds(51),
        ]);

        $this->assertSame(1, app(CallWrapUpService::class)->expireDue());

        $this->assertSame('cancelled', $call->fresh()->status);
        $this->assertSame('asterisk_event_timeout', $call->fresh()->end_reason);
        $this->assertSame('available', $admin->fresh()->currentAgentStatus->status);

        app(AsteriskCallEventService::class)->apply([
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'answered',
            'uniqueid' => '1720000000.4',
        ]);

        $this->assertSame('cancelled', $call->fresh()->status);
    }

    public function test_asterisk_event_rejects_expired_or_replayed_payloads(): void
    {
        config()->set('services.asterisk_bridge.webhook_secret', 'webhook-test-secret');
        $payload = ['company_id' => 1, 'call_id' => 1, 'event' => 'answered'];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->subMinutes(6)->timestamp;

        $this->postJson(route('bridge.events'), $payload, [
            'X-THCONECT-Timestamp' => $timestamp,
            'X-THCONECT-Signature' => hash_hmac('sha256', $timestamp.'.'.$json, 'webhook-test-secret'),
        ])->assertUnauthorized();
    }

    private function tenantAdmin(): User
    {
        $company = Company::create(['name' => 'Empresa Telefonia']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Administrador',
            'slug' => 'administrador-telefonia',
            'is_admin' => true,
        ]);
        $role->permissions()->sync(
            collect(['telephony.read', 'telephony.update'])
                ->map(fn (string $key) => Permission::firstOrCreate([
                    'key' => $key,
                ], [
                    'name' => $key === 'telephony.read' ? 'Ler' : 'Preparar rota',
                    'module' => 'telephony',
                ])->id)
                ->all(),
        );

        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'company_admin',
            'extension' => '1000',
            'status' => 'active',
        ]);
    }
}
