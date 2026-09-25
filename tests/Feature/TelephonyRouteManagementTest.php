<?php

namespace Tests\Feature;

use App\Models\AsteriskNode;
use App\Models\Company;
use App\Models\TelephonyRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelephonyRouteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_an_ip_authenticated_route(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('super-admin.telephony-routes.store'), [
                'name' => 'SMP Classificada',
                'provider' => 'Solutions VoIP',
                'tech' => '5006',
                'host' => 'sbc.solutionsvoip.com.br',
                'from_domain' => 'sbc.solutionsvoip.com.br',
                'transport' => 'udp',
                'authentication_mode' => 'ip',
                'caller_id' => '5511999999999',
                'dialplan_context' => 'thconect-outbound',
                'dialplan_extension' => 's',
                'codecs' => 'alaw,g729',
                'enabled' => '1',
            ])
            ->assertRedirect(route('super-admin.telephony-routes.index'));

        $route = TelephonyRoute::where('name', 'SMP Classificada')->sole();

        $this->assertSame('5006551736214392', $route->formattedPhone('(55) 17 3621-4392'));
        $this->assertSame('ip', $route->authentication_mode);
        $this->assertTrue($route->enabled);
    }

    public function test_super_admin_can_create_an_ip_authenticated_route_without_tech_using_a_national_prefix(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('super-admin.telephony-routes.store'), [
                'name' => 'Rota por IP sem Tech',
                'host' => '165.227.198.187',
                'sip_port' => 5060,
                'transport' => 'udp',
                'authentication_mode' => 'ip',
                'dial_prefix' => '0',
                'dialplan_context' => 'thconect-outbound',
                'dialplan_extension' => 's',
                'codecs' => 'g729,alaw,ulaw',
                'enabled' => '1',
            ])
            ->assertRedirect(route('super-admin.telephony-routes.index'));

        $route = TelephonyRoute::where('name', 'Rota por IP sem Tech')->sole();

        $this->assertNull($route->tech);
        $this->assertSame('0', $route->dial_prefix);
        $this->assertSame('031999990001', $route->formattedPhone('5531999990001'));
        $this->assertSame('031999990001', $route->formattedPhone('(31) 99999-0001'));
    }

    public function test_tech_prefix_takes_precedence_over_national_prefix_when_both_are_configured(): void
    {
        $route = TelephonyRoute::create([
            'name' => 'Rota com Tech e prefixo anterior',
            'tech' => '5001',
            'dial_prefix' => '0',
            'host' => '201.23.69.26',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw,g729',
        ]);

        $this->assertSame('50015581982143238', $route->formattedPhone('81982143238'));
    }

    public function test_route_without_tech_requires_a_national_dial_prefix(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('super-admin.telephony-routes.store'), [
                'name' => 'Rota incompleta sem Tech',
                'host' => '165.227.198.187',
                'transport' => 'udp',
                'authentication_mode' => 'ip',
                'dialplan_context' => 'thconect-outbound',
                'dialplan_extension' => 's',
                'codecs' => 'g729,alaw,ulaw',
                'enabled' => '1',
            ])
            ->assertSessionHasErrors('dial_prefix');
    }

    public function test_super_admin_sees_the_safe_sip_rejection_when_a_route_test_fails(): void
    {
        config()->set('services.asterisk_bridge.token', 'bridge-test-token');
        $node = AsteriskNode::create([
            'name' => 'No de teste', 'bridge_url' => 'http://asterisk-bridge.test', 'max_channels' => 30, 'status' => 'online',
        ]);
        $route = TelephonyRoute::create([
            'asterisk_node_id' => $node->id, 'name' => 'Rota com rejeicao SIP', 'tech' => '5006', 'host' => 'sip.example.test',
            'transport' => 'udp', 'authentication_mode' => 'ip', 'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's', 'codecs' => 'alaw,ulaw', 'enabled' => true,
        ]);
        Http::fake([
            'http://asterisk-bridge.test/*' => Http::response([
                'message' => 'SIP endpoint rejected OPTIONS: SIP/2.0 403 Forbidden',
            ], 502),
        ]);

        $this->actingAs($this->superAdmin())
            ->post(route('super-admin.telephony-routes.test', $route))
            ->assertSessionHasErrors('telephony_route');

        $this->assertSame('error', $route->fresh()->connection_status);
        $this->assertSame('SIP endpoint rejected OPTIONS: SIP/2.0 403 Forbidden', $route->fresh()->last_error);
    }

    public function test_company_can_receive_a_super_admin_route_without_tenant_configuration(): void
    {
        $route = TelephonyRoute::create([
            'name' => 'AntiSpam',
            'tech' => '7033',
            'host' => 'sbc.solutionsvoip.com.br',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw,g729',
        ]);

        $this->actingAs($this->superAdmin())
            ->post(route('super-admin.companies.store'), $this->companyPayload([
                'name' => 'Empresa com rota',
                'telephony_route_ids' => [$route->id],
            ]))
            ->assertRedirect(route('super-admin.companies.index'));

        $this->assertDatabaseHas('companies', [
            'name' => 'Empresa com rota',
        ]);
        $company = Company::where('name', 'Empresa com rota')->sole();
        $this->assertTrue($company->telephonyRoutes()->whereKey($route->id)->exists());
    }

    public function test_route_with_companies_cannot_be_deleted(): void
    {
        $route = TelephonyRoute::create([
            'name' => 'Rota vinculada',
            'tech' => '5006',
            'host' => 'sbc.solutionsvoip.com.br',
            'transport' => 'udp',
            'authentication_mode' => 'ip',
            'dialplan_context' => 'thconect-outbound',
            'dialplan_extension' => 's',
            'codecs' => 'alaw,g729',
        ]);
        $company = Company::create(['name' => 'Empresa vinculada']);
        $company->telephonyRoutes()->attach($route);

        $this->actingAs($this->superAdmin())
            ->delete(route('super-admin.telephony-routes.destroy', $route))
            ->assertSessionHasErrors('telephony_route');

        $this->assertDatabaseHas('telephony_routes', ['id' => $route->id]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
        ]);
    }

    private function companyPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Empresa Demo',
            'status' => 'active',
            'billing_due_day' => 12,
            'max_calls_per_available_agent' => 1,
        ], $overrides);
    }
}
