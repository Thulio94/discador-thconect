<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialerSmokeLoadCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_smoke_load_runs_multiple_scenarios_and_rolls_back_every_fixture(): void
    {
        $this->artisan('dialer:smoke-load', [
            '--agents' => '2,3',
            '--calls-per-agent' => 2,
        ])
            ->expectsOutputToContain('Teste isolado')
            ->expectsOutputToContain('Carga simulada concluida')
            ->assertSuccessful();

        $this->assertSame(0, Company::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Call::query()->count());
    }

    public function test_smoke_load_validates_the_initial_three_agent_operation_at_five_attempts_per_agent(): void
    {
        $this->artisan('dialer:smoke-load', [
            '--agents' => '3',
            '--calls-per-agent' => 5,
        ])
            ->expectsOutputToContain('Teste isolado')
            ->expectsOutputToContain('Carga simulada concluida')
            ->assertSuccessful();

        $this->assertSame(0, Company::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Call::query()->count());
    }

    public function test_smoke_load_rejects_values_above_the_safe_small_test_limit(): void
    {
        $this->artisan('dialer:smoke-load', [
            '--agents' => '11',
            '--calls-per-agent' => 10,
        ])->assertFailed();

        $this->artisan('dialer:smoke-load', [
            '--agents' => '5',
            '--calls-per-agent' => 11,
        ])->assertFailed();
    }
}
