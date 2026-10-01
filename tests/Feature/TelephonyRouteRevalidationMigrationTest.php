<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Company;
use App\Models\TelephonyRoute;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TelephonyRouteRevalidationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_only_routes_are_pending_but_routes_with_a_successful_originate_stay_online(): void
    {
        $company = Company::create(['name' => 'Empresa revalidacao de rota']);
        $unchecked = $this->route('Rota sem chamada real');
        $validated = $this->route('Rota com chamada real');
        $company->telephonyRoutes()->attach([$unchecked->id, $validated->id]);
        $agent = User::factory()->create(['company_id' => $company->id, 'user_type' => 'agent']);
        $call = Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'telephony_route_id' => $validated->id,
            'direction' => 'outbound',
            'status' => 'ringing',
            'channel' => 'asterisk_ami',
            'phone' => '81982143238',
            'started_at' => now(),
        ]);
        DB::table('call_events')->insert([
            'call_id' => $call->id,
            'company_id' => $company->id,
            'event_key' => hash('sha256', 'successful-route-originate'),
            'event' => 'originate_succeeded',
            'payload' => json_encode(['event' => 'originate_succeeded'], JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
        ]);

        /** @var Migration $migration */
        $migration = require base_path('database/migrations/2026_09_30_000001_revalidate_routes_from_real_calls.php');
        $migration->up();

        $this->assertSame('pending_test', $unchecked->fresh()->connection_status);
        $this->assertSame('online', $validated->fresh()->connection_status);
    }

    private function route(string $name): TelephonyRoute
    {
        return TelephonyRoute::create([
            'name' => $name,
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
    }
}
