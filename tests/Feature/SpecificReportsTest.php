<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportExport;
use App\Models\Call;
use App\Models\Company;
use App\Models\Permission;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\User;
use App\Services\Reports\ReportDataService;
use App\Services\Reports\SimplePdfReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SpecificReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_report_has_an_independent_tenant_page(): void
    {
        [$user] = $this->tenantUser();

        foreach (['calls', 'agents', 'campaigns', 'lists', 'qualifications', 'teams', 'utilization', 'callbacks', 'exports'] as $report) {
            $this->actingAs($user)
                ->get(route('tenant.reports.index', ['report' => $report]))
                ->assertOk();
        }
    }

    public function test_export_history_initial_tab_is_rendered_as_valid_alpine_data(): void
    {
        [$user] = $this->tenantUser();

        $this->actingAs($user)
            ->get(route('tenant.reports.index', ['report' => 'exports']))
            ->assertOk()
            ->assertSee("x-data=\"{ tab: 'files' }\"", false)
            ->assertDontSee('@js($initialTab)', false);

        $this->actingAs($user)
            ->get(route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules']))
            ->assertOk()
            ->assertSee("x-data=\"{ tab: 'schedules' }\"", false)
            ->assertDontSee('@js($initialTab)', false);
    }

    public function test_call_report_is_scoped_to_company_and_paginated_at_twenty_five_rows(): void
    {
        [$user, $company] = $this->tenantUser();
        [$otherUser, $otherCompany] = $this->tenantUser('Outra empresa');

        foreach (range(1, 26) as $index) {
            Call::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'direction' => 'outbound',
                'status' => 'ended',
                'channel' => 'simulated',
                'phone' => '3199999'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'started_at' => now()->subMinutes($index),
                'ended_at' => now()->subMinutes($index),
            ]);
        }
        Call::create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31988889999',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('tenant.reports.index', ['report' => 'calls']));
        $response->assertOk()->assertDontSee('31988889999');
        $response->assertViewHas('reportData', fn (array $data) => $data['rows']->count() === 25 && $data['rows']->total() === 26);
    }

    public function test_export_is_queued_and_download_never_crosses_company_boundary(): void
    {
        Queue::fake();
        [$user, $company] = $this->tenantUser();
        [$otherUser, $otherCompany] = $this->tenantUser('Empresa isolada');

        $this->actingAs($user)
            ->post(route('tenant.reports.exports.queue'), ['report_type' => 'calls', 'format' => 'csv'])
            ->assertRedirect(route('tenant.reports.index', ['report' => 'exports']));

        $this->assertDatabaseHas('report_exports', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'report_type' => 'calls',
            'format' => 'csv',
            'status' => 'queued',
        ]);
        Queue::assertPushed(GenerateReportExport::class);

        $foreignExport = ReportExport::create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'report_type' => 'calls',
            'format' => 'csv',
            'status' => 'completed',
            'progress' => 100,
            'file_path' => 'report-exports/foreign.csv',
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('tenant.reports.exports.download', $foreignExport))
            ->assertNotFound();
    }

    public function test_csv_export_uses_all_filtered_rows_not_the_screen_page(): void
    {
        Storage::fake('local');
        [$user, $company] = $this->tenantUser();
        foreach (range(1, 26) as $index) {
            Call::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'direction' => 'outbound',
                'status' => 'ended',
                'channel' => 'simulated',
                'phone' => '3197777'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'started_at' => now(),
                'ended_at' => now(),
            ]);
        }
        $export = ReportExport::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'report_type' => 'calls',
            'format' => 'csv',
            'status' => 'queued',
            'progress' => 0,
            'filters' => [],
        ]);

        (new GenerateReportExport($export->id))->handle(app(ReportDataService::class), app(SimplePdfReport::class));

        $export->refresh();
        $this->assertSame('completed', $export->status);
        $this->assertSame(26, $export->records_count);
        Storage::disk('local')->assertExists($export->file_path);
    }

    private function tenantUser(?string $companyName = null): array
    {
        $company = Company::create(['name' => $companyName ?? 'Empresa '.fake()->unique()->numerify('###'), 'status' => 'active']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_calls_per_available_agent' => 1,
            'max_concurrent_calls' => 10,
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Admin '.fake()->unique()->numerify('###'),
            'slug' => 'admin-'.fake()->unique()->numerify('###'),
            'is_admin' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'company_admin',
            'extension' => fake()->unique()->numerify('####'),
            'status' => 'active',
        ]);
        $permissions = collect(['reports.read', 'reports.export'])->map(fn (string $key) => Permission::query()->firstOrCreate(
            ['key' => $key],
            ['name' => $key, 'module' => 'reports'],
        ));
        $role->permissions()->sync($permissions->pluck('id'));

        return [$user, $company];
    }
}
