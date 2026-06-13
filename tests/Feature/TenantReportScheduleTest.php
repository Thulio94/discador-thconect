<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Company;
use App\Models\ReportSchedule;
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

    public function test_admin_can_create_daily_and_weekly_report_schedules(): void
    {
        [$admin, $company] = $this->tenantUser();
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Agendada',
            'dialing_mode' => 'preview',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('tenant.reports.schedules.store'), [
                'name' => 'Resumo diario',
                'format' => 'xlsx',
                'frequency' => 'daily',
                'run_time' => '08:30',
                'period_days' => 30,
                'campaign_id' => $campaign->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('tenant.reports.index'))
            ->assertSessionHas('status', 'Agendamento criado com sucesso.');

        $this->actingAs($admin)
            ->post(route('tenant.reports.schedules.store'), [
                'name' => 'Resumo semanal',
                'format' => 'pdf',
                'frequency' => 'weekly',
                'weekday' => 1,
                'run_time' => '09:15',
                'period_days' => 15,
                'status' => 'inactive',
            ])
            ->assertRedirect(route('tenant.reports.index'));

        $daily = ReportSchedule::query()->where('name', 'Resumo diario')->firstOrFail();
        $weekly = ReportSchedule::query()->where('name', 'Resumo semanal')->firstOrFail();

        $this->assertNull($daily->weekday);
        $this->assertSame([
            'period_days' => 30,
            'campaign_id' => $campaign->id,
        ], $daily->filters);
        $this->assertSame(1, $weekly->weekday);
        $this->assertSame('inactive', $weekly->status);
    }

    public function test_weekly_schedule_requires_weekday_and_filter_ids_from_same_company(): void
    {
        [$admin] = $this->tenantUser();
        [, $otherCompany] = $this->tenantUser();
        $otherCampaign = Campaign::create([
            'company_id' => $otherCompany->id,
            'name' => 'Campanha Externa',
            'dialing_mode' => 'preview',
            'status' => 'active',
        ]);

        $payload = [
            'name' => 'Resumo semanal',
            'format' => 'csv',
            'frequency' => 'weekly',
            'run_time' => '10:00',
            'period_days' => 7,
            'status' => 'active',
        ];

        $this->actingAs($admin)
            ->post(route('tenant.reports.schedules.store'), $payload)
            ->assertSessionHasErrors('weekday');

        $this->actingAs($admin)
            ->post(route('tenant.reports.schedules.store'), $payload + [
                'weekday' => 2,
                'campaign_id' => $otherCampaign->id,
            ])
            ->assertSessionHasErrors('campaign_id');
    }

    public function test_admin_can_update_and_toggle_own_schedule_but_not_another_company_schedule(): void
    {
        [$admin, $company] = $this->tenantUser();
        [$otherAdmin, $otherCompany] = $this->tenantUser();
        $schedule = $this->schedule($company, $admin);
        $otherSchedule = $this->schedule($otherCompany, $otherAdmin, 'Rotina outra empresa');

        $this->actingAs($admin)
            ->put(route('tenant.reports.schedules.update', $schedule), [
                'name' => 'Resumo atualizado',
                'format' => 'pdf',
                'frequency' => 'weekly',
                'weekday' => 5,
                'run_time' => '17:45',
                'period_days' => 60,
                'status' => 'active',
            ])
            ->assertRedirect(route('tenant.reports.index'))
            ->assertSessionHas('status', 'Agendamento atualizado com sucesso.');

        $schedule->refresh();
        $this->assertSame('Resumo atualizado', $schedule->name);
        $this->assertSame('pdf', $schedule->format);
        $this->assertSame(5, $schedule->weekday);

        $this->actingAs($admin)
            ->patch(route('tenant.reports.schedules.status', $schedule))
            ->assertRedirect(route('tenant.reports.index'))
            ->assertSessionHas('status', 'Agendamento inativado com sucesso.');

        $this->assertSame('inactive', $schedule->refresh()->status);

        $this->actingAs($admin)
            ->put(route('tenant.reports.schedules.update', $otherSchedule), [
                'name' => 'Tentativa externa',
                'format' => 'csv',
                'frequency' => 'daily',
                'run_time' => '12:00',
                'period_days' => 30,
                'status' => 'active',
            ])
            ->assertNotFound();
    }

    private function schedule(Company $company, User $user, string $name = 'Resumo diario'): ReportSchedule
    {
        return ReportSchedule::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'name' => $name,
            'format' => 'xlsx',
            'frequency' => 'daily',
            'run_time' => '08:30',
            'filters' => ['period_days' => 30],
            'status' => 'active',
        ]);
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
