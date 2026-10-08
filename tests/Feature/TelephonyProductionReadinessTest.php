<?php

namespace Tests\Feature;

use App\Contracts\Dialer\DialerGateway;
use App\Jobs\CancelPowerBatchCall;
use App\Jobs\RunPowerCampaign;
use App\Models\AgentStatus;
use App\Models\AgentWebRtcPresence;
use App\Models\AsteriskNode;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\CampaignPhoneState;
use App\Models\ChannelReservation;
use App\Models\Company;
use App\Models\ContactList;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\TelephonyRoute;
use App\Models\User;
use App\Services\Dialer\CallWrapUpService;
use App\Services\Dialer\DialerCallRequest;
use App\Services\Dialer\SimulatedDialerService;
use App\Services\Telephony\AgentWebRtcPresenceService;
use App\Services\Telephony\AsteriskBridgeClient;
use App\Services\Telephony\AsteriskCallEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelephonyProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_asterisk_loads_the_websocket_modules_required_by_browser_agents(): void
    {
        $modules = file_get_contents(base_path('.docker/asterisk/config/modules.conf'));
        $dockerfile = file_get_contents(base_path('.docker/asterisk/Dockerfile'));
        $productionCompose = file_get_contents(base_path('docker-compose.production.yml'));

        $this->assertStringContainsString('load => res_http_websocket.so', $modules);
        $this->assertStringContainsString('load => res_pjsip_transport_websocket.so', $modules);
        $this->assertMatchesRegularExpression('/--enable res_http_websocket(?:\\s|\\\\)/', $dockerfile);
        $this->assertMatchesRegularExpression('/--enable res_pjsip_transport_websocket(?:\\s|\\\\)/', $dockerfile);
        $this->assertStringContainsString('test -f /opt/asterisk/usr/lib/asterisk/modules/res_http_websocket.so', $dockerfile);
        $this->assertStringContainsString('test -f /opt/asterisk/usr/lib/asterisk/modules/res_pjsip_transport_websocket.so', $dockerfile);
        $this->assertStringContainsString('image: discador-piloto-asterisk:22.11.0-webrtc-r3', $productionCompose);
        $this->assertStringContainsString('ASTERISK_BUILD_REVISION: webrtc-r3', $productionCompose);
        $this->assertStringContainsString('module show like res_http_websocket', $productionCompose);
        $this->assertStringContainsString('module show like res_pjsip_transport_websocket', $productionCompose);
        $this->assertMatchesRegularExpression('/asterisk:.*?networks:.*?easypanel:/s', $productionCompose);
    }

    public function test_production_compose_processes_media_jobs_emitted_after_recordings_are_ready(): void
    {
        $productionCompose = file_get_contents(base_path('docker-compose.production.yml'));

        $this->assertMatchesRegularExpression('/^  queue-media:\R/m', $productionCompose);
        $this->assertStringContainsString('queue:work --queue=media', $productionCompose);
    }

    public function test_production_image_contains_database_backup_tools(): void
    {
        $dockerfile = file_get_contents(base_path('.docker/app/Dockerfile.production'));

        $this->assertStringContainsString('mariadb-client', $dockerfile);
        $this->assertStringContainsString('gzip', $dockerfile);
    }

    public function test_pilot_sip_websocket_proxy_uses_its_isolated_asterisk_alias(): void
    {
        $nginx = file_get_contents(base_path('.docker/nginx/default.conf'));
        $compose = file_get_contents(base_path('docker-compose.production.yml'));

        $this->assertStringContainsString('set $asterisk_backend pilot-asterisk:8088;', $nginx);
        $this->assertMatchesRegularExpression('/^  asterisk:.*?aliases:\R\s+- pilot-asterisk/ms', $compose);
    }

    public function test_manual_call_uses_the_company_real_route_and_agent_extension(): void
    {
        config()->set('services.asterisk_bridge.token', 'bridge-test-token');
        config()->set('services.asterisk_bridge.webhook_secret', 'webhook-test-secret');

        $company = Company::create(['name' => 'Empresa Manual']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => today()->subDay(),
            'ends_at' => today()->addMonth(),
            'max_concurrent_calls' => 25,
        ]);
        $company->resourceLimit()->create([]);
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'agent',
            'extension' => '1301',
            'status' => 'active',
        ]);
        $node = AsteriskNode::create([
            'name' => 'Asterisk teste',
            'bridge_url' => 'http://asterisk-bridge.test',
            'max_channels' => 30,
            'status' => 'online',
        ]);
        $route = TelephonyRoute::create([
            'asterisk_node_id' => $node->id,
            'name' => 'Rota manual real',
            'tech' => '5001',
            'host' => 'sip.example.test',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw',
            'enabled' => true,
            'connection_status' => 'pending_test',
        ]);
        $company->telephonyRoutes()->attach($route);

        Http::fake(['http://asterisk-bridge.test/*' => Http::response(['accepted' => true], 202)]);

        $call = app(DialerGateway::class)->originate(new DialerCallRequest(
            companyId: $company->id,
            userId: $agent->id,
            campaignId: null,
            contactId: null,
            contactPhoneId: null,
            phone: '81982143238',
            channel: 'manual',
        ));

        $this->assertSame('asterisk_ami', $call->channel);
        $this->assertSame($route->id, $call->telephony_route_id);
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://asterisk-bridge.test/v1/calls/originate'
            && $request['route_id'] === $route->id
            && $request['company_id'] === $company->id
            && $request['agent_extension'] === '1301'
            && $request['agent_endpoint'] === "t{$company->id}-e1301"
            && $request['phone'] === '50015581982143238'
        );
    }

    public function test_failed_agent_delivery_is_reported_and_agent_is_taken_out_of_rotation(): void
    {
        $company = Company::create(['name' => 'Empresa entrega falha']);
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'agent',
            'extension' => '1308',
            'status' => 'active',
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);
        AgentWebRtcPresence::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'connection_id' => (string) Str::uuid(),
            'registered' => true,
            'last_seen_at' => now(),
        ]);
        $call = Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'ringing',
            'channel' => 'asterisk_ami',
            'phone' => '31999990008',
            'started_at' => now(),
        ]);

        app(AsteriskCallEventService::class)->apply([
            'company_id' => $company->id,
            'call_id' => $call->id,
            'event' => 'agent_delivery_failed',
            'cause' => 'CHANUNAVAIL (3)',
        ]);

        $this->assertSame('cancelled', $call->fresh()->status);
        $this->assertSame('agent_unreachable', $call->fresh()->end_reason);
        $this->assertSame('offline', $agent->fresh()->currentAgentStatus->status);
        $this->assertFalse(app(AgentWebRtcPresenceService::class)->isRegistered($agent));
    }

    public function test_call_release_keeps_an_agent_offline_without_a_fresh_webrtc_presence(): void
    {
        $company = Company::create(['name' => 'Empresa sem presenca WebRTC']);
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'agent',
            'extension' => '1311',
            'status' => 'active',
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'dialing',
            'started_at' => now(),
        ]);
        $call = Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'direction' => 'outbound',
            'status' => 'cancelled',
            'channel' => 'asterisk_ami',
            'phone' => '81982143238',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        app(CallWrapUpService::class)->releaseAgent($agent, $call);

        $this->assertSame('offline', $agent->fresh()->currentAgentStatus->status);
    }

    public function test_real_route_forwards_the_power_batch_id_to_the_asterisk_bridge(): void
    {
        config()->set('services.asterisk_bridge.token', 'bridge-test-token');

        $company = Company::create(['name' => 'Empresa lote Power']);
        $company->licenses()->create([
            'status' => 'active', 'starts_at' => today()->subDay(), 'ends_at' => today()->addMonth(), 'max_concurrent_calls' => 25,
        ]);
        $company->resourceLimit()->create([]);
        $agent = User::factory()->create([
            'company_id' => $company->id, 'user_type' => 'agent', 'extension' => '1304', 'status' => 'active',
        ]);
        $node = AsteriskNode::create([
            'name' => 'Asterisk lote Power', 'bridge_url' => 'http://asterisk-bridge.test', 'max_channels' => 30, 'status' => 'online',
        ]);
        $route = TelephonyRoute::create([
            'asterisk_node_id' => $node->id,
            'name' => 'Rota lote Power',
            'tech' => '5006',
            'host' => 'sip.example.test',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw',
            'enabled' => true,
            'connection_status' => 'online',
        ]);
        $company->telephonyRoutes()->attach($route);
        Http::fake(['http://asterisk-bridge.test/*' => Http::response(['accepted' => true], 202)]);

        $batchId = 'power-batch-'.fake()->uuid();
        $call = app(DialerGateway::class)->originate(new DialerCallRequest(
            companyId: $company->id,
            userId: $agent->id,
            campaignId: null,
            contactId: null,
            contactPhoneId: null,
            phone: '31999990004',
            channel: 'asterisk_ami',
            batchId: $batchId,
        ));

        $this->assertSame($batchId, $call->dial_batch_id);
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://asterisk-bridge.test/v1/calls/originate'
            && $request['call_id'] === $call->id
            && $request['batch_id'] === $batchId
        );
    }

    public function test_bridge_origination_waits_until_the_power_batch_transaction_is_committed(): void
    {
        config()->set('services.asterisk_bridge.token', 'bridge-test-token');

        $company = Company::create(['name' => 'Empresa transacao Power']);
        $company->licenses()->create([
            'status' => 'active', 'starts_at' => today()->subDay(), 'ends_at' => today()->addMonth(), 'max_concurrent_calls' => 25,
        ]);
        $company->resourceLimit()->create([]);
        $agent = User::factory()->create([
            'company_id' => $company->id, 'user_type' => 'agent', 'extension' => '1305', 'status' => 'active',
        ]);
        $node = AsteriskNode::create([
            'name' => 'Asterisk transacao Power', 'bridge_url' => 'http://asterisk-bridge.test', 'max_channels' => 30, 'status' => 'online',
        ]);
        $route = TelephonyRoute::create([
            'asterisk_node_id' => $node->id,
            'name' => 'Rota transacao Power',
            'tech' => '5006',
            'host' => 'sip.example.test',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw',
            'enabled' => true,
            'connection_status' => 'online',
        ]);
        $company->telephonyRoutes()->attach($route);
        Http::fake(['http://asterisk-bridge.test/*' => Http::response(['accepted' => true], 202)]);

        DB::transaction(function () use ($company, $agent): void {
            app(DialerGateway::class)->originate(new DialerCallRequest(
                companyId: $company->id,
                userId: $agent->id,
                campaignId: null,
                contactId: null,
                contactPhoneId: null,
                phone: '31999990005',
                channel: 'asterisk_ami',
                batchId: 'power-batch-after-commit',
            ));

            Http::assertNothingSent();
        });

        Http::assertSentCount(1);
    }

    public function test_outbound_dialplan_blocks_duplicate_power_batch_and_agent_deliveries(): void
    {
        $dialplan = file_get_contents(base_path('.docker/asterisk/config/extensions.conf'));

        $this->assertStringContainsString('GROUP(thconect-power-batch)', $dialplan);
        $this->assertStringContainsString('GROUP(thconect-agent)', $dialplan);
        $this->assertStringContainsString('GROUP_COUNT(${THCONECT_BATCH_ID}@thconect-power-batch)', $dialplan);
        $this->assertStringContainsString('GROUP_COUNT(${THCONECT_AGENT_ENDPOINT}@thconect-agent)', $dialplan);
        $this->assertMatchesRegularExpression('/GROUP\(thconect-agent\).*?Dial\(PJSIP\/\$\{THCONECT_AGENT_ENDPOINT\}/s', $dialplan);
    }

    public function test_initial_three_agent_power_operation_originates_five_route_attempts_per_available_agent(): void
    {
        config()->set('services.asterisk_bridge.token', 'bridge-test-token');

        $company = Company::create(['name' => 'Empresa carga Power']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => today()->subDay(),
            'ends_at' => today()->addMonth(),
            'max_calls_per_available_agent' => 5,
            'max_concurrent_calls' => 15,
        ]);
        $company->resourceLimit()->create([]);
        $node = AsteriskNode::create([
            'name' => 'Asterisk carga Power', 'bridge_url' => 'http://asterisk-bridge.test', 'max_channels' => 30, 'status' => 'online',
        ]);
        $route = TelephonyRoute::create([
            'asterisk_node_id' => $node->id,
            'name' => 'Rota carga Power',
            'tech' => '5006',
            'host' => 'sip.example.test',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw',
            'enabled' => true,
            'connection_status' => 'online',
        ]);
        $company->telephonyRoutes()->attach($route);
        $team = Team::create([
            'company_id' => $company->id,
            'name' => 'Equipe carga Power',
            'slug' => 'equipe-carga-power',
            'status' => 'active',
        ]);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha carga Power',
            'dialing_mode' => 'power',
            'status' => 'active',
            'aggressiveness' => 5,
            'fixed_telephony_route_id' => $route->id,
            'mobile_telephony_route_id' => $route->id,
        ]);
        $campaign->teams()->attach($team, ['company_id' => $company->id]);
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Lista carga Power',
            'status' => 'active',
        ]);
        $campaign->contactLists()->attach($list, ['company_id' => $company->id]);

        foreach (range(1, 3) as $index) {
            $agent = User::factory()->create([
                'company_id' => $company->id,
                'user_type' => 'agent',
                'extension' => '14'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'status' => 'active',
            ]);
            AgentWebRtcPresence::create([
                'company_id' => $company->id,
                'user_id' => $agent->id,
                'connection_id' => (string) Str::uuid(),
                'registered' => true,
                'last_seen_at' => now(),
            ]);
            $team->users()->attach($agent, ['company_id' => $company->id, 'membership_type' => 'agent']);
            AgentStatus::create([
                'company_id' => $company->id,
                'user_id' => $agent->id,
                'status' => 'available',
                'started_at' => now(),
            ]);
        }

        foreach (range(1, 15) as $index) {
            $list->contacts()->create([
                'company_id' => $company->id,
                'name' => 'Lead carga Power '.$index,
                'phone' => '3199999'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'status' => 'pending',
            ]);
        }

        Http::fake(['http://asterisk-bridge.test/*' => Http::response(['accepted' => true], 202)]);

        $this->assertSame(15, app(SimulatedDialerService::class)->runCampaign($campaign));

        $calls = Call::query()->where('campaign_id', $campaign->id)->get();
        $this->assertCount(15, $calls);
        $this->assertTrue($calls->every(fn (Call $call) => $call->status === 'dialing'));
        $this->assertCount(3, $calls->pluck('dial_batch_id')->unique()->filter());
        $this->assertTrue($calls->groupBy('dial_batch_id')->every(fn ($batch) => $batch->count() === 5));
        Http::assertSentCount(15);
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://asterisk-bridge.test/v1/calls/originate'
            && $request['route_id'] === $route->id
            && filled($request['batch_id'])
        );
    }

    public function test_manual_call_without_tech_uses_the_national_prefix_for_an_ip_route(): void
    {
        config()->set('services.asterisk_bridge.token', 'bridge-test-token');
        config()->set('services.asterisk_bridge.webhook_secret', 'webhook-test-secret');

        $company = Company::create(['name' => 'Empresa rota por IP']);
        $company->licenses()->create([
            'status' => 'active', 'starts_at' => today()->subDay(), 'ends_at' => today()->addMonth(), 'max_concurrent_calls' => 25,
        ]);
        $company->resourceLimit()->create([]);
        $agent = User::factory()->create([
            'company_id' => $company->id, 'user_type' => 'agent', 'extension' => '1303', 'status' => 'active',
        ]);
        $node = AsteriskNode::create([
            'name' => 'Asterisk rota por IP', 'bridge_url' => 'http://asterisk-bridge.test', 'max_channels' => 30, 'status' => 'online',
        ]);
        $route = TelephonyRoute::create([
            'asterisk_node_id' => $node->id,
            'name' => 'Rota IP sem Tech',
            'tech' => null,
            'dial_prefix' => '0',
            'host' => '165.227.198.187',
            'sip_port' => 5060,
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'g729,alaw,ulaw',
            'enabled' => true,
            'connection_status' => 'online',
        ]);
        $company->telephonyRoutes()->attach($route);

        Http::fake(['http://asterisk-bridge.test/*' => Http::response(['accepted' => true], 202)]);

        app(DialerGateway::class)->originate(new DialerCallRequest(
            companyId: $company->id, userId: $agent->id, campaignId: null, contactId: null, contactPhoneId: null,
            phone: '5531999990001', channel: 'manual',
        ));

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://asterisk-bridge.test/v1/calls/originate'
            && $request['phone'] === '031999990001'
            && $request['channel'] === 'PJSIP/031999990001@thconect-route-'.$route->id
        );
    }

    public function test_agent_can_create_only_its_own_encrypted_webrtc_session(): void
    {
        config()->set('services.asterisk_bridge.token', 'bridge-test-token');

        [$agent, $node] = $this->agentWithRoute();
        Http::fake(['http://asterisk-bridge.test/*' => Http::response(['synchronized' => true])]);

        $response = $this->actingAs($agent)
            ->postJson(route('tenant.agent.softphone.session'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('endpoint', "t{$agent->company_id}-e{$agent->extension}")
            ->assertJsonPath('websocket_url', 'ws://localhost:8087/sip-ws');

        $secret = $response->json('password');
        $this->assertIsString($secret);
        $this->assertGreaterThanOrEqual(40, strlen($secret));
        $this->assertNotSame($secret, DB::table('users')->where('id', $agent->id)->value('sip_secret'));
        $this->assertArrayNotHasKey('sip_secret', $agent->fresh()->toArray());

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://asterisk-bridge.test/v1/agents/'.$agent->id
            && $request->method() === 'PUT'
            && $request['company_id'] === $agent->company_id
            && $request['extension'] === $agent->extension
            && $request['password'] === $secret
        );
        $this->assertSame('http://asterisk-bridge.test', $node->bridge_url);
    }

    public function test_agent_session_prefers_the_dedicated_webrtc_websocket_url(): void
    {
        config()->set('services.asterisk_bridge.token', 'bridge-test-token');
        config()->set('services.asterisk_bridge.webrtc_ws_url', 'wss://ws.discador.test/ws');

        [$agent] = $this->agentWithRoute();
        Http::fake(['http://asterisk-bridge.test/*' => Http::response(['synchronized' => true])]);

        $this->actingAs($agent)
            ->postJson(route('tenant.agent.softphone.session'))
            ->assertOk()
            ->assertJsonPath('websocket_url', 'wss://ws.discador.test/ws');
    }

    public function test_inactive_user_cannot_create_a_webrtc_session(): void
    {
        [$agent] = $this->agentWithRoute();
        $agent->update(['status' => 'inactive']);

        $this->actingAs($agent)
            ->postJson(route('tenant.agent.softphone.session'))
            ->assertForbidden();
    }

    public function test_route_input_rejects_pjsip_configuration_injection(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('super-admin.telephony-routes.store'), $this->routePayload([
                'codecs' => "alaw\n[attacker]\ntype=endpoint",
                'dialplan_context' => "thconect-outbound\nallow=all",
            ]))
            ->assertSessionHasErrors(['codecs', 'dialplan_context']);

        $this->assertDatabaseCount('telephony_routes', 0);
    }

    public function test_route_accepts_tls_and_digest_authentication_profile(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('super-admin.telephony-routes.store'), $this->routePayload([
                'transport' => 'tls',
                'authentication_mode' => 'digest',
                'sip_port' => 5061,
                'sip_username' => 'conta-sip',
                'sip_password' => 'segredo-de-teste',
                'from_user' => '551130000000',
                'contact_user' => '551130000000',
                'dtmf_mode' => 'rfc4733',
                'caller_id_header' => 'pai',
            ]))
            ->assertRedirect(route('super-admin.telephony-routes.index'));

        $route = TelephonyRoute::query()->where('name', 'Rota segura')->sole();
        $this->assertSame('tls', $route->transport);
        $this->assertSame('digest', $route->authentication_mode);
        $this->assertSame(5061, $route->sip_port);
        $this->assertSame('pai', $route->caller_id_header);
    }

    private function freshPowerRegressionScenario(): Campaign
    {
        $this->test_initial_three_agent_power_operation_originates_five_route_attempts_per_available_agent();
        $campaign = Campaign::query()->firstOrFail();
        Call::query()->delete();
        ChannelReservation::query()->delete();
        CampaignPhoneState::query()->update(['status' => 'pending', 'attempts_count' => 0, 'next_attempt_at' => null]);
        AgentStatus::query()->update(['status' => 'available']);

        return $campaign;
    }

    public function test_power_waits_for_route_validation_without_creating_failed_calls_or_marking_a_dispatch(): void
    {
        $campaign = $this->freshPowerRegressionScenario();
        config()->set('app.force_real_telephony', true);
        config()->set('services.asterisk_bridge.token', 'bridge-test-token');
        config()->set('services.asterisk_bridge.webhook_secret', 'webhook-test-secret');
        $campaign->fixedTelephonyRoute->update(['connection_status' => 'pending_test']);
        $campaign->mobileTelephonyRoute->update(['connection_status' => 'pending_test']);

        (new RunPowerCampaign($campaign->id))->handle(app(SimulatedDialerService::class));

        $this->assertDatabaseCount('calls', 0);
        $this->assertSame(0, (int) CampaignPhoneState::query()->sum('attempts_count'));
        $this->assertNull($campaign->fresh()->last_dispatched_at);
    }

    public function test_power_can_originate_after_successful_options_preflight_while_tech_is_pending_real_validation(): void
    {
        $campaign = $this->freshPowerRegressionScenario();
        config()->set('app.force_real_telephony', true);
        config()->set('services.asterisk_bridge.token', 'bridge-test-token');
        config()->set('services.asterisk_bridge.webhook_secret', 'webhook-test-secret');
        $route = $campaign->fixedTelephonyRoute;
        $route->update(['connection_status' => 'pending_test']);
        $route->forceFill([
            'connection_status' => 'pending_test',
            'last_checked_at' => now()->addSecond(),
            'last_error' => null,
            'preflight_config_hash' => null,
        ])->saveQuietly();
        Http::fake(['http://asterisk-bridge.test/*' => Http::response(['accepted' => true], 202)]);

        (new RunPowerCampaign($campaign->id))->handle(app(SimulatedDialerService::class));

        $calls = Call::query()->where('campaign_id', $campaign->id)->get();
        $this->assertCount(15, $calls);
        $this->assertTrue($calls->every(fn (Call $call): bool => $call->status === 'dialing'));
        $this->assertSame(15, (int) CampaignPhoneState::query()->sum('attempts_count'));
        $this->assertNotNull($campaign->fresh()->last_dispatched_at);
        Http::assertSentCount(15);
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://asterisk-bridge.test/v1/calls/originate'
            && $request['route_id'] === $campaign->fixed_telephony_route_id);
    }

    public function test_power_does_not_consume_mailing_attempts_when_route_capacity_is_exhausted(): void
    {
        $campaign = $this->freshPowerRegressionScenario();
        $campaign->fixedTelephonyRoute->update(['max_concurrent_channels' => 2]);
        app(SimulatedDialerService::class)->runCampaign($campaign);

        $this->assertSame(2, Call::query()->where('status', 'dialing')->count());
        $this->assertSame(2, (int) CampaignPhoneState::query()->sum('attempts_count'));
    }

    public function test_power_does_not_originate_with_expired_license(): void
    {
        $campaign = $this->freshPowerRegressionScenario();
        $campaign->company->licenses()->update(['ends_at' => today()->subDay()]);

        $this->assertSame(0, app(SimulatedDialerService::class)->runCampaign($campaign));
    }

    public function test_power_cancels_other_calls_when_a_batch_has_a_winner(): void
    {
        $this->test_initial_three_agent_power_operation_originates_five_route_attempts_per_available_agent();
        $winner = Call::query()->firstOrFail();
        app(AsteriskCallEventService::class)->apply([
            'company_id' => $winner->company_id, 'call_id' => $winner->id,
            'event' => 'answered', 'uniqueid' => 'batch-winner',
        ]);

        $this->assertSame('in_progress', $winner->fresh()->status);
        $this->assertSame(0, Call::query()->where('dial_batch_id', $winner->dial_batch_id)
            ->whereKeyNot($winner->id)->whereIn('status', Call::ACTIVE_STATUSES)->count());
        $this->assertSame(4, Call::query()->where('dial_batch_id', $winner->dial_batch_id)
            ->where('end_reason', 'power_batch_winner')->count());

        $loser = Call::query()->where('end_reason', 'power_batch_winner')->firstOrFail();
        $this->assertSame('active', $loser->channelReservation->fresh()->status);
        app(AsteriskCallEventService::class)->apply([
            'company_id' => $loser->company_id, 'call_id' => $loser->id,
            'event' => 'answered', 'uniqueid' => 'late-loser',
        ]);
        $this->assertSame('cancelled', $loser->fresh()->status);
        (new CancelPowerBatchCall($loser->id))->handle(app(AsteriskBridgeClient::class));
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://asterisk-bridge.test/v1/calls/hangup'
            && (int) $request['call_id'] === $loser->id);
        app(AsteriskCallEventService::class)->apply([
            'company_id' => $loser->company_id, 'call_id' => $loser->id,
            'event' => 'hangup', 'uniqueid' => 'cancelled-loser',
        ]);
        $this->assertSame('released', $loser->channelReservation->fresh()->status);
    }

    public function test_power_does_not_start_campaign_before_start_date(): void
    {
        $campaign = $this->freshPowerRegressionScenario();
        $campaign->update(['starts_at' => now()->addDay()]);

        $this->assertSame(0, app(SimulatedDialerService::class)->runCampaign($campaign));
    }

    public function test_late_originate_event_does_not_regress_answered_call(): void
    {
        $this->test_initial_three_agent_power_operation_originates_five_route_attempts_per_available_agent();
        $call = Call::query()->firstOrFail();
        $events = app(AsteriskCallEventService::class);
        $payload = ['company_id' => $call->company_id, 'call_id' => $call->id, 'uniqueid' => 'late-originate'];
        $events->apply($payload + ['event' => 'answered']);
        $events->apply($payload + ['event' => 'originate_succeeded']);

        $this->assertSame('in_progress', $call->fresh()->status);
    }

    public function test_answered_call_moves_the_mailing_phone_to_delivered(): void
    {
        $this->test_initial_three_agent_power_operation_originates_five_route_attempts_per_available_agent();
        $call = Call::query()->firstOrFail();
        app(AsteriskCallEventService::class)->apply([
            'company_id' => $call->company_id, 'call_id' => $call->id,
            'event' => 'answered', 'uniqueid' => 'mailing-delivery',
        ]);

        $state = CampaignPhoneState::query()->where('campaign_id', $call->campaign_id)
            ->where('contact_phone_id', $call->contact_phone_id)->firstOrFail();
        $this->assertSame('delivered', $state->status);
    }

    private function routePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rota segura',
            'provider' => 'Operadora teste',
            'tech' => '5006',
            'host' => 'sip.example.test',
            'sip_port' => 5060,
            'from_domain' => 'sip.example.test',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'caller_id' => '551130000000',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw,ulaw',
            'dtmf_mode' => 'rfc4733',
            'caller_id_header' => 'none',
            'qualify_frequency' => 15,
            'registration_expiration' => 3600,
            'enabled' => '1',
        ], $overrides);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
        ]);
    }

    private function agentWithRoute(): array
    {
        $company = Company::create(['name' => 'Empresa WebRTC']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => today()->subDay(),
            'ends_at' => today()->addMonth(),
            'max_concurrent_calls' => 25,
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Agente WebRTC',
            'slug' => 'agente-webrtc',
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['key' => 'agent.access'],
            ['name' => 'Acessar agente', 'module' => 'agent'],
        );
        $role->permissions()->attach($permission);
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'agent',
            'extension' => '1302',
            'status' => 'active',
        ]);
        $node = AsteriskNode::create([
            'name' => 'Asterisk WebRTC',
            'bridge_url' => 'http://asterisk-bridge.test',
            'max_channels' => 30,
            'status' => 'online',
        ]);
        $route = TelephonyRoute::create([
            'asterisk_node_id' => $node->id,
            'name' => 'Rota WebRTC',
            'tech' => '5006',
            'host' => 'sip.example.test',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw,ulaw',
            'enabled' => true,
            'connection_status' => 'online',
        ]);
        $company->telephonyRoutes()->attach($route);

        return [$agent, $node];
    }
}
