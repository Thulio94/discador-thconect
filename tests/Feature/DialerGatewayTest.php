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

    public function test_container_binds_the_asterisk_gateway_with_simulated_fallback(): void
    {
        $this->assertInstanceOf(AsteriskDialerGateway::class, app(DialerGateway::class));
    }

    public function test_simulated_gateway_originates_cancels_and_hangs_up_calls(): void
    {
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

        $this->assertSame('in_progress', $call->status);
        $this->assertSame('manual', $call->channel);
        $this->assertNotNull($call->answered_at);

        $gateway->hangup($call);

        $this->assertSame('ended', $call->fresh()->status);
        $this->assertNotNull($call->fresh()->ended_at);

        $cancelled = $gateway->cancel($request, 'answered_elsewhere');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('answered_elsewhere', $cancelled->end_reason);
        $this->assertNull($cancelled->answered_at);
    }
}
