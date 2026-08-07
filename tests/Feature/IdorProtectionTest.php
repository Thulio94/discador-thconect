<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\CompanyWebhook;
use App\Models\ContactList;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdorProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_tenant_cannot_access_or_mutate_records_from_another_company_by_changing_the_id(): void
    {
        [$admin] = $this->tenantAdmin('Empresa A', '1001');
        [$foreignUser, $foreignCompany, $foreignRole] = $this->tenantAdmin('Empresa B', '2001');

        $foreignTeam = Team::create([
            'company_id' => $foreignCompany->id,
            'name' => 'Equipe externa',
            'slug' => 'equipe-externa',
            'status' => 'active',
        ]);
        $foreignList = ContactList::create([
            'company_id' => $foreignCompany->id,
            'name' => 'Lista externa',
            'status' => 'active',
        ]);
        $foreignCampaign = Campaign::create([
            'company_id' => $foreignCompany->id,
            'name' => 'Campanha externa',
            'dialing_mode' => 'preview',
            'status' => 'paused',
        ]);
        $foreignWebhook = CompanyWebhook::create([
            'company_id' => $foreignCompany->id,
            'name' => 'Webhook externo',
            'endpoint' => 'https://example.com/hook',
            'secret' => 'secret',
            'events' => ['call.completed'],
            'status' => 'active',
        ]);
        $foreignNotification = AppNotification::create([
            'company_id' => $foreignCompany->id,
            'user_id' => $foreignUser->id,
            'type' => 'system',
            'severity' => 'info',
            'title' => 'Notificacao externa',
            'message' => 'Nao pode ser lida por outra empresa.',
        ]);
        $foreignExport = ReportExport::create([
            'company_id' => $foreignCompany->id,
            'user_id' => $foreignUser->id,
            'report_type' => 'calls',
            'format' => 'csv',
            'status' => 'completed',
            'progress' => 100,
            'file_path' => 'private/exports/external.csv',
        ]);
        $foreignCall = Call::create([
            'company_id' => $foreignCompany->id,
            'user_id' => $foreignUser->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'manual',
            'phone' => '31999990000',
            'recording_path' => 'private/recordings/external.wav',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $this->actingAs($admin);

        $attempts = [
            'editar usuario' => fn () => $this->put(route('tenant.users.update', $foreignUser)),
            'gerar ramal' => fn () => $this->patch(route('tenant.users.extension.regenerate', $foreignUser)),
            'excluir equipe' => fn () => $this->delete(route('tenant.teams.destroy', $foreignTeam)),
            'excluir perfil' => fn () => $this->delete(route('tenant.roles.destroy', $foreignRole)),
            'excluir lista' => fn () => $this->delete(route('tenant.lists.destroy', $foreignList)),
            'iniciar campanha' => fn () => $this->patch(route('tenant.campaigns.start', $foreignCampaign)),
            'monitorar agente' => fn () => $this->post(route('tenant.supervision.monitor', $foreignUser)),
            'excluir webhook' => fn () => $this->delete(route('tenant.integrations.destroy', $foreignWebhook)),
            'marcar notificacao' => fn () => $this->patch(route('tenant.notifications.read', $foreignNotification)),
            'baixar exportacao' => fn () => $this->get(route('tenant.reports.exports.download', $foreignExport)),
            'ouvir gravacao' => fn () => $this->get(route('tenant.reports.calls.recording', $foreignCall)),
        ];

        foreach ($attempts as $action => $attempt) {
            try {
                $attempt()->assertNotFound();
            } catch (\Throwable $exception) {
                throw new \RuntimeException("Falha na protecao IDOR ao tentar {$action}.", 0, $exception);
            }
        }

        $this->assertSame('active', $foreignTeam->fresh()->status);
        $this->assertSame('active', $foreignList->fresh()->status);
        $this->assertSame('paused', $foreignCampaign->fresh()->status);
        $this->assertNull($foreignNotification->fresh()->read_at);
        $this->assertDatabaseHas('company_webhooks', ['id' => $foreignWebhook->id]);
    }

    private function tenantAdmin(string $companyName, string $extension): array
    {
        $company = Company::create(['name' => $companyName]);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Administrador '.$companyName,
            'slug' => 'administrador-'.str($companyName)->slug(),
            'is_admin' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'company_admin',
            'extension' => $extension,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        return [$user, $company, $role];
    }
}
