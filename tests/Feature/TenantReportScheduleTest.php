<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\ReportExport;
use App\Models\ReportSchedule;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
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
            ->assertRedirect(route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules']))
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
            ->assertRedirect(route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules']));

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
            ->assertRedirect(route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules']))
            ->assertSessionHas('status', 'Agendamento atualizado com sucesso.');

        $schedule->refresh();
        $this->assertSame('Resumo atualizado', $schedule->name);
        $this->assertSame('pdf', $schedule->format);
        $this->assertSame(5, $schedule->weekday);

        $this->actingAs($admin)
            ->patch(route('tenant.reports.schedules.status', $schedule))
            ->assertRedirect(route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules']))
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

    public function test_admin_can_delete_own_schedule_but_not_another_company_schedule(): void
    {
        [$admin, $company] = $this->tenantUser();
        [$otherAdmin, $otherCompany] = $this->tenantUser();
        $schedule = $this->schedule($company, $admin);
        $otherSchedule = $this->schedule($otherCompany, $otherAdmin, 'Rotina outra empresa');

        $this->actingAs($admin)
            ->delete(route('tenant.reports.schedules.destroy', $otherSchedule))
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('tenant.reports.schedules.destroy', $schedule))
            ->assertRedirect(route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules']))
            ->assertSessionHas('status', 'Agendamento excluido com sucesso.');

        $this->assertDatabaseMissing('report_schedules', ['id' => $schedule->id]);
        $this->assertDatabaseHas('report_schedules', ['id' => $otherSchedule->id]);
    }

    public function test_active_schedule_run_counts_filtered_calls_and_registers_export_history(): void
    {
        Carbon::setTestNow('2026-06-13 10:00:00');
        [$admin, $company] = $this->tenantUser();
        [$otherAdmin, $otherCompany] = $this->tenantUser();
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Executada',
            'dialing_mode' => 'preview',
            'status' => 'active',
        ]);
        $otherCampaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha Ignorada',
            'dialing_mode' => 'preview',
            'status' => 'active',
        ]);
        $schedule = $this->schedule($company, $admin);
        $schedule->update([
            'filters' => [
                'period_days' => 30,
                'campaign_id' => $campaign->id,
            ],
        ]);

        $this->createCall($company, $admin, $campaign, now()->subDays(5), '31999991001');
        $this->createCall($company, $admin, $campaign, now()->subDays(31), '31999991002');
        $this->createCall($company, $admin, $otherCampaign, now()->subDays(2), '31999991003');
        $otherCompanyCampaign = Campaign::create([
            'company_id' => $otherCompany->id,
            'name' => 'Campanha Outra Empresa',
            'dialing_mode' => 'preview',
            'status' => 'active',
        ]);
        $this->createCall($otherCompany, $otherAdmin, $otherCompanyCampaign, now()->subDay(), '31999991004');

        $this->actingAs($admin)
            ->post(route('tenant.reports.schedules.run', $schedule))
            ->assertRedirect(route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules']))
            ->assertSessionHas('status', 'Agendamento executado. 1 registros encontrados.');

        $schedule->refresh();
        $this->assertSame(1, $schedule->last_records_count);
        $this->assertTrue($schedule->last_run_at->equalTo(now()));

        $this->assertDatabaseHas('report_exports', [
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'format' => 'xlsx',
            'records_count' => 1,
        ]);

        $export = ReportExport::query()->where('company_id', $company->id)->latest()->firstOrFail();
        $this->assertSame($campaign->id, $export->filters['campaign_id']);
        $this->assertSame('2026-05-15', $export->filters['date_from']);
        $this->assertSame('2026-06-13', $export->filters['date_to']);
        $this->assertSame($schedule->id, $export->filters['schedule_id']);
    }

    public function test_inactive_schedule_cannot_run(): void
    {
        [$admin, $company] = $this->tenantUser();
        $schedule = $this->schedule($company, $admin);
        $schedule->update(['status' => 'inactive']);

        $this->actingAs($admin)
            ->post(route('tenant.reports.schedules.run', $schedule))
            ->assertRedirect()
            ->assertSessionHasErrors('schedule');

        $this->assertNull($schedule->refresh()->last_run_at);
        $this->assertDatabaseCount('report_exports', 0);
    }

    public function test_scheduled_reports_command_runs_only_due_active_schedules_once(): void
    {
        Carbon::setTestNow('2026-06-14 10:00:00');
        [$admin, $company] = $this->tenantUser();
        $daily = $this->schedule($company, $admin, 'Diario automatico');
        $weekly = $this->schedule($company, $admin, 'Semanal automatico');
        $weekly->update(['frequency' => 'weekly', 'weekday' => now()->dayOfWeek, 'run_time' => '09:00']);
        $future = $this->schedule($company, $admin, 'Horario futuro');
        $future->update(['run_time' => '11:00']);
        $inactive = $this->schedule($company, $admin, 'Inativo automatico');
        $inactive->update(['status' => 'inactive']);

        $this->artisan('reports:run-scheduled')
            ->expectsOutput('2 agendamento(s) de relatorio executado(s).')
            ->assertExitCode(0);

        $this->assertTrue($daily->fresh()->last_run_at->equalTo(now()));
        $this->assertTrue($weekly->fresh()->last_run_at->equalTo(now()));
        $this->assertNull($future->fresh()->last_run_at);
        $this->assertNull($inactive->fresh()->last_run_at);
        $this->assertDatabaseCount('report_exports', 2);

        $this->artisan('reports:run-scheduled')
            ->expectsOutput('0 agendamento(s) de relatorio executado(s).')
            ->assertExitCode(0);

        $this->assertDatabaseCount('report_exports', 2);
    }

    public function test_reports_panel_shows_only_company_schedules_and_modal_controls(): void
    {
        [$admin, $company] = $this->tenantUser();
        [$otherAdmin, $otherCompany] = $this->tenantUser();
        $schedule = $this->schedule($company, $admin, 'Resumo diario');
        $this->schedule($otherCompany, $otherAdmin, 'Rotina outra empresa');

        $this->actingAs($admin)
            ->get(route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules']))
            ->assertOk()
            ->assertSee('Agendamentos de relatórios')
            ->assertSee('Novo agendamento')
            ->assertSee('Resumo diario')
            ->assertSee('Executar agora')
            ->assertSee('Editar')
            ->assertSee('Visualizar')
            ->assertSee('Excluir')
            ->assertSee('Inativar')
            ->assertSee('name="period_days"', false)
            ->assertSee('name="format"', false)
            ->assertSee('name="frequency"', false)
            ->assertSee('name="run_time"', false)
            ->assertSee('report-schedule-edit-'.$schedule->id)
            ->assertDontSee('Rotina outra empresa')
            ->assertDontSee('window.confirm', false)
            ->assertDontSee('javascript:alert', false);
    }

    private function createCall(Company $company, User $user, Campaign $campaign, Carbon $startedAt, string $phone): Call
    {
        return Call::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => $phone,
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addMinute(),
        ]);
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
