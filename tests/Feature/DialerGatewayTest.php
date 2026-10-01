<?php

namespace Tests\Feature;

use App\Contracts\Dialer\DialerGateway;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\TelephonyRoute;
use App\Services\Dialer\DialerCallRequest;
use App\Services\Dialer\AsteriskDialerGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialerGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_container_binds_the_asterisk_gateway(): void
    {
        $this->assertInstanceOf(AsteriskDialerGateway::class, app(DialerGateway::class));
    }

    public function test_unavailable_route_does_not_create_an_answered_call(): void
    {
        config()->set('app.force_real_telephony', true);
        $company = Company::create(['name' => 'Empresa Gateway']);
        $gateway = app(DialerGateway::class);
        $request = new DialerCallRequest(
            companyId: $company->id,
            userId: null,
            campaignId: null,
            contactId: null,
            contactPhoneId: null,
            phone: '31999990001',
            channel: 'manual',
        );

        $call = $gateway->originate($request);

        $this->assertSame('cancelled', $call->status);
        $this->assertSame('route_unavailable', $call->end_reason);
        $this->assertNull($call->answered_at);

        $cancelled = $gateway->cancel($request, 'answered_elsewhere');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('answered_elsewhere', $cancelled->end_reason);
        $this->assertNull($cancelled->answered_at);
    }

    public function test_power_campaign_cannot_dial_until_a_pending_route_passes_a_real_call(): void
    {
        config()->set('app.force_real_telephony', true);
        config()->set('services.asterisk_bridge.token', 'bridge-test-token');
        config()->set('services.asterisk_bridge.webhook_secret', 'webhook-test-secret');
        $company = Company::create(['name' => 'Empresa rota pendente']);
        $route = TelephonyRoute::create([
            'name' => 'Rota aguardando chamada',
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
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha bloqueada ate teste real',
            'dialing_mode' => 'power',
            'status' => 'active',
            'fixed_telephony_route_id' => $route->id,
            'mobile_telephony_route_id' => $route->id,
        ]);

        $call = app(DialerGateway::class)->originate(new DialerCallRequest(
            companyId: $company->id,
            userId: null,
            campaignId: $campaign->id,
            contactId: null,
            contactPhoneId: null,
            phone: '81982143238',
            channel: 'power',
        ));

        $this->assertSame('cancelled', $call->status);
        $this->assertSame('route_unavailable', $call->end_reason);
        $this->assertNull($call->answered_at);
    }
}
