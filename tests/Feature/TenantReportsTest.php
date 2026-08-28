<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportExport;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Disposition;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TenantReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_opens_only_the_selected_specific_report(): void
    {
        [$admin] = $this->tenantUser();

        $this->actingAs($admin)
            ->get(route('tenant.reports.index'))
            ->assertOk()
            ->assertSee('Relatório de ligações')
            ->assertSee('Relatório de agentes')
            ->assertSee('Histórico de exportações');

        $this->actingAs($admin)
            ->get(route('tenant.reports.index', ['report' => 'calls']))
            ->assertOk()
            ->assertSee('Relatório de ligações')
            ->assertDontSee('Relatório de agentes');

        $this->actingAs($admin)
            ->get(route('tenant.reports.index', ['report' => 'invalid']))
            ->assertNotFound();
    }

    public function test_call_report_filters_and_metrics_are_tenant_scoped(): void
    {
        [$admin, $company] = $this->tenantUser();
        [$agent] = $this->tenantUser($company, 'Agente Relatorio', 'agent');
        [$otherAgent, $otherCompany] = $this->tenantUser(null, 'Agente Externo', 'agent');
        $campaign = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha A', 'dialing_mode' => 'preview', 'status' => 'active']);
        $otherCampaign = Campaign::create(['company_id' => $otherCompany->id, 'name' => 'Campanha B', 'dialing_mode' => 'preview', 'status' => 'active']);

        $this->makeCall($company, $agent, $campaign, '31999990001', 'ended');
        $this->makeCall($company, $agent, $campaign, '31999990002', 'in_progress');
        $this->makeCall($otherCompany, $otherAgent, $otherCampaign, '31988889999', 'ended');

        $response = $this->actingAs($admin)->get(route('tenant.reports.index', [
            'report' => 'calls',
            'campaign_id' => $campaign->id,
        ]));

        $response->assertOk()
            ->assertSee('31999990001')
            ->assertSee('31999990002')
            ->assertDontSee('31988889999')
            ->assertViewHas('reportData', fn (array $data) => $data['metrics']['total'] === 2 && $data['rows']->total() === 2);
    }

    public function test_legacy_csv_export_is_queued_instead_of_running_during_the_http_request(): void
    {
        Queue::fake();
        [$admin, $company] = $this->tenantUser();
        [$agent] = $this->tenantUser($company, 'Agente Exportacao', 'agent');
        $campaign = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha Exportacao', 'dialing_mode' => 'preview', 'status' => 'active']);
        $disposition = Disposition::create([
            'company_id' => $company->id,
            'name' => 'Venda realizada',
            'code' => 'VENDA',
            'category' => 'success',
            'status' => 'active',
        ]);
        $call = $this->makeCall($company, $agent, $campaign, '31999990100', 'ended');
        $call->update([
            'disposition_id' => $disposition->id,
            'disposition_code' => $disposition->code,
            'disposition_name' => $disposition->name,
            'report_snapshot' => [
                'agent_name' => $agent->name,
                'campaign_name' => $campaign->name,
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.reports.export'))
            ->assertRedirect(route('tenant.reports.index', ['report' => 'exports']));

        $this->assertDatabaseHas('report_exports', [
            'company_id' => $company->id,
            'report_type' => 'calls',
            'format' => 'csv',
            'status' => 'queued',
        ]);
        Queue::assertPushed(GenerateReportExport::class);
    }

    public function test_legacy_xlsx_export_is_queued_instead_of_running_during_the_http_request(): void
    {
        Queue::fake();
        [$admin, $company] = $this->tenantUser();
        [$agent] = $this->tenantUser($company, 'Agente XLSX', 'agent');
        $campaign = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha XLSX', 'dialing_mode' => 'preview', 'status' => 'active']);
        $this->makeCall($company, $agent, $campaign, '31999990200', 'ended');

        $this->actingAs($admin)
            ->get(route('tenant.reports.export-xlsx'))
            ->assertRedirect(route('tenant.reports.index', ['report' => 'exports']));

        $this->assertDatabaseHas('report_exports', [
            'company_id' => $company->id,
            'report_type' => 'calls',
            'format' => 'xlsx',
            'status' => 'queued',
        ]);
        Queue::assertPushed(GenerateReportExport::class);
    }

    private function makeCall(Company $company, User $agent, Campaign $campaign, string $phone, string $status): Call
    {
        return Call::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => $status,
            'channel' => 'simulated',
            'phone' => $phone,
            'started_at' => now(),
            'ended_at' => $status === 'ended' ? now() : null,
        ]);
    }

    private function tenantUser(?Company $company = null, ?string $name = null, string $type = 'company_admin'): array
    {
        $company ??= Company::create(['name' => 'Empresa '.fake()->unique()->numerify('####'), 'status' => 'active']);
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
            'name' => 'Relatorios '.fake()->unique()->numerify('####'),
            'slug' => 'relatorios-'.fake()->unique()->numerify('####'),
            'is_admin' => $type === 'company_admin',
        ]);
        $permissions = collect(['reports.read', 'reports.export'])->map(fn (string $key) => Permission::query()->firstOrCreate(
            ['key' => $key],
            ['name' => $key, 'module' => 'reports'],
        ));
        $role->permissions()->sync($permissions->pluck('id'));
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => $name ?? fake()->name(),
            'user_type' => $type,
            'extension' => fake()->unique()->numerify('####'),
            'status' => 'active',
        ]);

        return [$user, $company];
    }
}
