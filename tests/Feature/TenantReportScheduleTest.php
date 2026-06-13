<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantReportScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_and_user_own_report_schedules(): void
    {
        [$admin, $company] = $this->tenantUser();

        $schedule = $company->reportSchedules()->create([
            'user_id' => $admin->id,
            'name' => 'Resumo diario',
            'format' => 'xlsx',
            'frequency' => 'daily',
            'run_time' => '08:30',
            'filters' => ['period_days' => 30],
            'status' => 'active',
        ]);

        $this->assertTrue($admin->reportSchedules->contains($schedule));
        $this->assertSame(30, $schedule->filters['period_days']);
        $this->assertNull($schedule->last_run_at);
    }

    private function tenantUser(string $type = 'company_admin', ?Company $company = null, ?string $name = null): array
    {
        $company ??= Company::create([
            'name' => 'Tenant Agendamentos '.fake()->unique()->numberBetween(1000, 9999),
            'status' => 'active',
        ]);

        if (! $company->licenses()->exists()) {
            $company->licenses()->create([
                'status' => 'active',
                'starts_at' => now()->subDay()->toDateString(),
                'ends_at' => now()->addMonth()->toDateString(),
                'max_calls_per_available_agent' => 2,
                'max_concurrent_calls' => 10,
            ]);
        }

        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Agendamentos '.fake()->unique()->numberBetween(1000, 9999),
            'slug' => 'agendamentos-'.fake()->unique()->numberBetween(1000, 9999),
            'is_admin' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => $name ?? fake()->name(),
            'user_type' => $type,
            'extension' => fake()->unique()->numerify('5###'),
            'status' => 'active',
        ]);

        return [$user, $company];
    }
}
