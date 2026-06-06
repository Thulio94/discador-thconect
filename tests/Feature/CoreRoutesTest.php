<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guest_to_login(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_loads_for_active_tenant_user(): void
    {
        $user = $this->tenantUser();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_super_admin_companies_route_loads(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('super-admin.companies.index'))
            ->assertOk()
            ->assertSee('Empresas');
    }

    public function test_tenant_reserved_modules_are_coming_soon(): void
    {
        $user = $this->tenantUser();

        $routes = [
            'tenant.campaigns.index' => 'Campanhas',
            'tenant.agent.index' => 'Agente',
            'tenant.supervision.index' => 'Supervisao',
            'tenant.reports.index' => 'Relatorios',
            'tenant.telephony.index' => 'Telefonia',
            'tenant.settings.index' => 'Configuracoes',
            'tenant.integrations.index' => 'Integracoes',
        ];

        foreach ($routes as $route => $title) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertOk()
                ->assertSee($title)
                ->assertSee('Modulo em breve');
        }
    }

    private function tenantUser(): User
    {
        $company = Company::create(['name' => 'Tenant A']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'extension' => '1001',
        ]);
    }
}
