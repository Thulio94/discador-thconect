<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Disposition;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

    public function test_immediate_csv_export_contains_the_expanded_call_history(): void
    {
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

        $response = $this->actingAs($admin)->get(route('tenant.reports.export'));
        $response->assertOk()->assertDownload('relatorios-chamadas.csv');
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Telefone,Lead,Lista,Campanha,Agente,Ramal,Rota,Status', $csv);
        $this->assertStringContainsString('31999990100', $csv);
        $this->assertStringContainsString('Campanha Exportacao', $csv);
        $this->assertStringContainsString('Venda realizada', $csv);
    }

    public function test_immediate_xlsx_export_uses_expanded_headings(): void
    {
        [$admin, $company] = $this->tenantUser();
        [$agent] = $this->tenantUser($company, 'Agente XLSX', 'agent');
        $campaign = Campaign::create(['company_id' => $company->id, 'name' => 'Campanha XLSX', 'dialing_mode' => 'preview', 'status' => 'active']);
        $this->makeCall($company, $agent, $campaign, '31999990200', 'ended');

        $response = $this->actingAs($admin)->get(route('tenant.reports.export-xlsx'));
        $response->assertOk()->assertDownload('relatorios-chamadas.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'report-xlsx-');
        file_put_contents($path, $response->streamedContent());
        $headings = IOFactory::load($path)->getActiveSheet()->toArray()[0];
        @unlink($path);

        $this->assertSame(['Telefone', 'Lead', 'Lista', 'Campanha', 'Agente', 'Ramal', 'Rota', 'Status', 'Tabulacao', 'Desligou', 'Inicio', 'Atendimento', 'Termino', 'Duracao', 'Espera', 'Gravacao'], array_map(fn ($heading) => iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $heading), $headings));
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
