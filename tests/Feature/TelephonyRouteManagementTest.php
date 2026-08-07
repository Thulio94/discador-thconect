<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\TelephonyRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
