<?php

namespace Tests\Feature;

use App\Exceptions\CapacityExceededException;
use App\Models\AsteriskNode;
use App\Models\Company;
use App\Models\SystemCapacitySetting;
use App\Models\TelephonyRoute;
use App\Models\User;
use App\Services\Capacity\ChannelCapacityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaaSCapacityProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_open_capacity_dashboard(): void
    {
        $company = Company::create(['name' => 'Empresa']);
        $tenant = User::factory()->create(['company_id' => $company->id, 'is_super_admin' => false, 'user_type' => 'company_admin']);
        $superAdmin = User::factory()->create(['is_super_admin' => true, 'user_type' => 'super_admin']);

        $this->actingAs($tenant)->get(route('super-admin.capacity.index'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('super-admin.capacity.index'))->assertOk()->assertSee('Capacidade e protecao');
    }

    public function test_last_global_channel_is_reserved_atomically_and_released(): void
    {
        [$company, $route] = $this->capacityFixture();
        SystemCapacitySetting::current()->update(['max_channels' => 1]);
        $capacity = app(ChannelCapacityService::class);

        $reservation = $capacity->reserve($company, $route);

        try {
            $capacity->reserve($company, $route);
            $this->fail('Uma segunda reserva nao poderia ultrapassar o teto global.');
        } catch (CapacityExceededException $exception) {
            $this->assertSame('global_channels', $exception->limitKey);
        }

        $capacity->release($reservation, 'test');
        $this->assertNotNull($capacity->reserve($company, $route));
    }

    public function test_route_and_node_limits_are_never_exceeded(): void
    {
        [$company, $route] = $this->capacityFixture(routeLimit: 1, nodeLimit: 2);
        $capacity = app(ChannelCapacityService::class);
        $capacity->reserve($company, $route);

        $this->expectException(CapacityExceededException::class);
        $capacity->reserve($company, $route);
    }

    public function test_company_limit_does_not_end_existing_reservation(): void
    {
        [$company, $route] = $this->capacityFixture(companyLimit: 1);
        $capacity = app(ChannelCapacityService::class);
        $reservation = $capacity->reserve($company, $route);

        try {
            $capacity->reserve($company, $route);
        } catch (CapacityExceededException $exception) {
            $this->assertSame('company_channels', $exception->limitKey);
        }

        $this->assertDatabaseHas('channel_reservations', ['id' => $reservation->id, 'status' => 'reserved']);
    }

    private function capacityFixture(?int $routeLimit = 10, int $nodeLimit = 10, ?int $companyLimit = 10): array
    {
        $company = Company::create(['name' => 'Empresa capacidade', 'status' => 'active']);
        $company->licenses()->create([
            'status' => 'active', 'starts_at' => today()->subDay(), 'ends_at' => today()->addYear(),
            'max_calls_per_available_agent' => 10, 'max_concurrent_calls' => $companyLimit,
        ]);
        $company->resourceLimit()->create([]);
        $node = AsteriskNode::create(['name' => 'No teste', 'bridge_url' => 'http://asterisk.test', 'max_channels' => $nodeLimit, 'status' => 'online']);
        $route = TelephonyRoute::create([
            'asterisk_node_id' => $node->id, 'name' => 'Rota teste', 'tech' => '5006', 'host' => 'sip.test',
            'dialplan_context' => 'outbound', 'dialplan_extension' => 's', 'codecs' => 'alaw',
            'max_concurrent_channels' => $routeLimit,
        ]);

        return [$company->fresh(['license', 'resourceLimit']), $route->fresh('asteriskNode')];
    }
}
