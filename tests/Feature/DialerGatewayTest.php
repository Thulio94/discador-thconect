<?php

namespace Tests\Feature;

use App\Contracts\Dialer\DialerGateway;
use App\Models\Company;
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
}
