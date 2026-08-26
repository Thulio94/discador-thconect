<?php

namespace Tests\Feature;

use App\Contracts\Dialer\DialerGateway;
use App\Models\AsteriskNode;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TelephonyRoute;
use App\Models\User;
use App\Services\Dialer\DialerCallRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelephonyProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

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

        $call = app(DialerGateway::class)->originate(new DialerCallRequest(
            companyId: $company->id,
            userId: $agent->id,
            campaignId: null,
            contactId: null,
            contactPhoneId: null,
            phone: '31999990001',
            channel: 'manual',
        ));

        $this->assertSame('asterisk_ami', $call->channel);
        $this->assertSame($route->id, $call->telephony_route_id);
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://asterisk-bridge.test/v1/calls/originate'
            && $request['route_id'] === $route->id
            && $request['company_id'] === $company->id
            && $request['agent_extension'] === '1301'
            && $request['agent_endpoint'] === "t{$company->id}-e1301"
            && $request['phone'] === '50065531999990001'
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
