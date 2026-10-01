<?php

namespace Tests\Feature;

use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\CallEvent;
use App\Models\ChannelReservation;
use App\Models\Company;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TelephonyRoute;
use App\Models\User;
use App\Services\Telephony\AsteriskCallEventService;
use App\Services\Dialer\CallWrapUpService;
use App\Services\Dialer\MailingQueueService;
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
            ->assertSee('Operadora não informada')
            ->assertDontSee('5006')
            ->assertDontSee('sbc.solutionsvoip.com.br')
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

    public function test_provider_rejection_marks_the_route_unavailable_with_a_simple_message(): void
    {
        $admin = $this->tenantAdmin();
        $route = TelephonyRoute::create([
            'name' => 'Rota teste com rejeicao',
            'tech' => '5001',
            'host' => 'sip.example.test',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw,g729',
            'enabled' => true,
            'connection_status' => 'online',
        ]);
        $admin->company->telephonyRoutes()->attach($route);
        $call = Call::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'telephony_route_id' => $route->id,
            'direction' => 'outbound',
            'status' => 'dialing',
            'channel' => 'asterisk_ami',
            'phone' => '81982143238',
            'started_at' => now(),
        ]);

        app(AsteriskCallEventService::class)->apply([
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'originate_failed',
            'cause' => '21',
            'payload' => ['TechCause' => 'SIP/2.0 403 Forbidden'],
        ]);

        $this->assertSame('error', $route->fresh()->connection_status);
        $this->assertSame('A operadora recusou a ligacao. Avise o administrador para revisar a rota.', $route->fresh()->last_error);
        $this->assertSame('provider_route_rejected', $call->fresh()->end_reason);
    }

    public function test_unknown_destination_or_tech_rejection_is_visible_as_a_route_failure(): void
    {
        $admin = $this->tenantAdmin();
        $route = TelephonyRoute::create([
            'name' => 'Rota teste destino recusado',
            'tech' => '5001',
            'host' => 'sip.example.test',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw,g729',
            'enabled' => true,
            'connection_status' => 'pending_test',
        ]);
        $admin->company->telephonyRoutes()->attach($route);
        $call = Call::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'telephony_route_id' => $route->id,
            'direction' => 'outbound',
            'status' => 'dialing',
            'channel' => 'asterisk_ami',
            'phone' => '81982143238',
            'started_at' => now(),
        ]);

        app(AsteriskCallEventService::class)->apply([
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'originate_failed',
            'cause' => '0',
            'payload' => ['TechCause' => 'SIP/2.0 404 Not Found'],
        ]);

        $this->assertSame('error', $route->fresh()->connection_status);
        $this->assertSame('provider_destination_rejected', $call->fresh()->end_reason);
        $this->assertStringContainsString('numero ou a TECH', $route->fresh()->last_error);
    }

    public function test_hangup_tech_cause_still_marks_a_pending_route_after_originate_failure(): void
    {
        $admin = $this->tenantAdmin();
        $route = TelephonyRoute::create([
            'name' => 'Rota rejeitada no evento Hangup',
            'tech' => '5001',
            'host' => 'sip.example.test',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw,g729',
            'enabled' => true,
            'connection_status' => 'pending_test',
        ]);
        $admin->company->telephonyRoutes()->attach($route);
        $call = Call::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'telephony_route_id' => $route->id,
            'direction' => 'outbound',
            'status' => 'dialing',
            'channel' => 'asterisk_ami',
            'phone' => '81982143238',
            'started_at' => now(),
        ]);
        $events = app(AsteriskCallEventService::class);

        $events->apply([
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'originate_failed',
            'cause' => '3',
            'uniqueid' => 'route-rejected-uniqueid',
        ]);
        $events->apply([
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'hangup',
            'cause' => 'No route to destination',
            'payload' => ['TechCause' => 'SIP/2.0 403 Forbidden'],
            'uniqueid' => 'route-rejected-uniqueid',
        ]);

        $this->assertSame('error', $route->fresh()->connection_status);
        $this->assertSame('A operadora recusou a ligacao. Avise o administrador para revisar a rota.', $route->fresh()->last_error);
        $this->assertSame('cancelled', $call->fresh()->status);
    }

    public function test_successful_outbound_call_validates_the_route(): void
    {
        $admin = $this->tenantAdmin();
        $route = TelephonyRoute::create([
            'name' => 'Rota pendente validada em chamada',
            'tech' => '5001',
            'host' => 'sip.example.test',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw,g729',
            'enabled' => true,
            'connection_status' => 'pending_test',
            'last_error' => 'Teste de saida pendente',
        ]);
        $admin->company->telephonyRoutes()->attach($route);
        $call = Call::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'telephony_route_id' => $route->id,
            'direction' => 'outbound',
            'status' => 'dialing',
            'channel' => 'asterisk_ami',
            'phone' => '81982143238',
            'started_at' => now(),
        ]);

        app(AsteriskCallEventService::class)->apply([
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'originate_succeeded',
            'action_id' => 'test-route-5001',
        ]);

        $this->assertSame('online', $route->fresh()->connection_status);
        $this->assertNull($route->fresh()->last_error);
        $this->assertSame('ringing', $call->fresh()->status);

        app(AsteriskCallEventService::class)->apply([
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'hangup',
            'cause' => 'User endpoint rejected the call',
            'payload' => ['TechCause' => 'SIP/2.0 403 Forbidden'],
            'uniqueid' => 'agent-leg-rejected',
        ]);

        $this->assertSame('online', $route->fresh()->connection_status);
        $this->assertNull($route->fresh()->last_error);
    }

    public function test_busy_or_no_answer_does_not_mark_the_route_unavailable(): void
    {
        $admin = $this->tenantAdmin();
        $route = TelephonyRoute::create([
            'name' => 'Rota teste destino ocupado',
            'tech' => '5001',
            'host' => 'sip.example.test',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw,g729',
            'enabled' => true,
            'connection_status' => 'online',
        ]);
        $call = Call::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'telephony_route_id' => $route->id,
            'direction' => 'outbound',
            'status' => 'dialing',
            'channel' => 'asterisk_ami',
            'phone' => '81982143238',
            'started_at' => now(),
        ]);

        app(AsteriskCallEventService::class)->apply([
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'originate_failed',
            'cause' => '19 - No answer',
        ]);

        $this->assertSame('online', $route->fresh()->connection_status);
        $this->assertNull($route->fresh()->last_error);
        $this->assertSame('19 - No answer', $call->fresh()->end_reason);
    }

    public function test_missing_pjsip_auth_ids_marks_the_route_as_failed(): void
    {
        $admin = $this->tenantAdmin();
        $route = TelephonyRoute::create([
            'name' => 'Rota teste sem autenticacao PJSIP',
            'tech' => '5001',
            'host' => 'sip.example.test',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw,g729',
            'enabled' => true,
            'connection_status' => 'online',
        ]);
        $admin->company->telephonyRoutes()->attach($route);
        $call = Call::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'telephony_route_id' => $route->id,
            'direction' => 'outbound',
            'status' => 'dialing',
            'channel' => 'asterisk_ami',
            'phone' => '81982143238',
            'started_at' => now(),
        ]);

        app(AsteriskCallEventService::class)->apply([
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'originate_failed',
            'cause' => 'There were no auth ids available',
        ]);

        $this->assertSame('error', $route->fresh()->connection_status);
        $this->assertSame('A rota apresentou erro de autenticacao. Avise o administrador para revisar a configuracao.', $route->fresh()->last_error);
        $this->assertSame('route_auth_configuration_error', $call->fresh()->end_reason);
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
            'cause' => 'SIP/2.0 407 Proxy Authentication Required',
            'uniqueid' => '1720000000.3',
        ]);

        $this->assertSame('cancelled', $call->fresh()->status);
        $this->assertSame('provider_authentication_required', $call->fresh()->end_reason);
        $this->assertSame('available', $admin->fresh()->currentAgentStatus->status);
    }

    public function test_failed_power_leg_keeps_agent_reserved_for_another_live_leg_and_requeues_its_phone(): void
    {
        $agent = $this->tenantAdmin();
        $company = $agent->company;
        $list = ContactList::create(['company_id' => $company->id, 'name' => 'Fila', 'status' => 'active']);
        $contact = $list->contacts()->create(['company_id' => $company->id, 'name' => 'Lead', 'phone' => '31999990003', 'status' => 'pending']);
        $phone = $contact->phones()->create(['company_id' => $company->id, 'phone' => '31999990003', 'status' => 'pending', 'is_primary' => true]);
        $campaign = Campaign::create(['company_id' => $company->id, 'name' => 'Power', 'status' => 'active', 'max_attempts_per_contact' => 2, 'retry_delay_minutes' => 5]);
        $campaign->contactLists()->attach($list, ['company_id' => $company->id, 'weight' => 1]);
        $queue = app(MailingQueueService::class);
        $queue->markAttempt($contact, now()->addMinutes(30), 'dialing', $phone, $campaign);
        $failed = Call::create(['company_id' => $company->id, 'user_id' => $agent->id, 'campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'contact_phone_id' => $phone->id, 'dial_batch_id' => 'batch-a', 'direction' => 'outbound', 'status' => 'dialing', 'channel' => 'asterisk_ami', 'phone' => $phone->phone, 'started_at' => now()]);
        Call::create(['company_id' => $company->id, 'user_id' => $agent->id, 'campaign_id' => $campaign->id, 'dial_batch_id' => 'batch-a', 'direction' => 'outbound', 'status' => 'ringing', 'channel' => 'asterisk_ami', 'phone' => '31999990004', 'started_at' => now()]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $agent->id, 'status' => 'dialing', 'started_at' => now()]);

        app(AsteriskCallEventService::class)->apply(['company_id' => $company->id, 'call_id' => $failed->id, 'event' => 'originate_failed', 'cause' => 'congestion']);

        $this->assertSame('dialing', $agent->fresh()->currentAgentStatus->status);
        $state = $queue->stateForCampaignPhone($campaign, $phone)->fresh();
        $this->assertSame('failed', $state->status);
        $this->assertNotNull($state->next_attempt_at);
    }

    public function test_stale_ami_call_waits_for_the_route_and_agent_dial_windows_before_releasing_the_agent(): void
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

        $this->assertSame(0, app(CallWrapUpService::class)->expireDue());
        $this->assertSame('ringing', $call->fresh()->status);
        $this->assertSame('dialing', $admin->fresh()->currentAgentStatus->status);

        $this->travel(40)->seconds();

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

    public function test_stale_power_call_releases_capacity_and_requeues_its_phone(): void
    {
        $agent = $this->tenantAdmin();
        $list = ContactList::create(['company_id' => $agent->company_id, 'name' => 'Fila', 'status' => 'active']);
        $contact = $list->contacts()->create(['company_id' => $agent->company_id, 'name' => 'Lead', 'phone' => '31999990005', 'status' => 'pending']);
        $phone = $contact->phones()->create(['company_id' => $agent->company_id, 'phone' => '31999990005', 'status' => 'pending', 'is_primary' => true]);
        $campaign = Campaign::create(['company_id' => $agent->company_id, 'name' => 'Power', 'status' => 'active', 'retry_delay_minutes' => 5]);
        $campaign->contactLists()->attach($list, ['company_id' => $agent->company_id, 'weight' => 1]);
        $queue = app(MailingQueueService::class);
        $queue->markAttempt($contact, now()->addMinutes(30), 'dialing', $phone, $campaign);
        $reservation = ChannelReservation::create(['company_id' => $agent->company_id, 'campaign_id' => $campaign->id, 'user_id' => $agent->id, 'status' => 'active', 'expires_at' => now()->addHour()]);
        Call::create(['company_id' => $agent->company_id, 'user_id' => $agent->id, 'campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'contact_phone_id' => $phone->id, 'channel_reservation_id' => $reservation->id, 'direction' => 'outbound', 'status' => 'dialing', 'channel' => 'asterisk_ami', 'phone' => $phone->phone, 'started_at' => now()->subSeconds(91)]);
        AgentStatus::create(['company_id' => $agent->company_id, 'user_id' => $agent->id, 'status' => 'dialing', 'started_at' => now()->subSeconds(91)]);

        $this->assertSame(1, app(CallWrapUpService::class)->expireDue());
        $this->assertSame('released', $reservation->fresh()->status);
        $this->assertSame('failed', $queue->stateForCampaignPhone($campaign, $phone)->fresh()->status);
        $this->assertNotNull($queue->stateForCampaignPhone($campaign, $phone)->fresh()->next_attempt_at);
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
