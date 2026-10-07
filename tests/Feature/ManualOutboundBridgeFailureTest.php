<?php

namespace Tests\Feature;

use App\Contracts\Dialer\DialerGateway;
use App\Models\AgentStatus;
use App\Models\AgentWebRtcPresence;
use App\Models\AsteriskNode;
use App\Models\Company;
use App\Models\TelephonyRoute;
use App\Models\User;
use App\Services\Dialer\DialerCallRequest;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualOutboundBridgeFailureTest extends TestCase
{
    use DatabaseMigrations;

    public function test_bridge_rejection_is_returned_to_the_manual_call_panel(): void
    {
        config()->set('services.asterisk_bridge.token', 'bridge-test-token');
        config()->set('services.asterisk_bridge.webhook_secret', 'webhook-test-secret');

        $company = Company::create(['name' => 'Empresa bridge indisponivel']);
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
            'extension' => '1310',
            'status' => 'active',
        ]);
        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'dialing',
            'started_at' => now(),
        ]);
        AgentWebRtcPresence::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'connection_id' => (string) Str::uuid(),
            'registered' => true,
            'last_seen_at' => now(),
        ]);
        $node = AsteriskNode::create([
            'name' => 'Asterisk bridge indisponivel',
            'bridge_url' => 'http://asterisk-bridge.test',
            'max_channels' => 30,
            'status' => 'online',
        ]);
        $route = TelephonyRoute::create([
            'asterisk_node_id' => $node->id,
            'name' => 'Rota bridge indisponivel',
            'tech' => '5001',
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
        Http::fake(['http://asterisk-bridge.test/*' => Http::response(['message' => 'unavailable'], 503)]);

        $call = app(DialerGateway::class)->originate(new DialerCallRequest(
            companyId: $company->id,
            userId: $agent->id,
            campaignId: null,
            contactId: null,
            contactPhoneId: null,
            phone: '81982143238',
            channel: 'manual',
        ));

        $this->assertSame('cancelled', $call->status);
        $this->assertSame('bridge_unavailable', $call->end_reason);
        $this->assertSame('available', $agent->fresh()->currentAgentStatus->status);
        $this->assertSame('released', $call->fresh()->channelReservation->status);
    }
}
