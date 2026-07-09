<?php

namespace Tests\Feature;

use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Company;
use App\Models\Role;
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

    public function test_tenant_dashboard_shows_current_call_capacity_usage(): void
    {
        $user = $this->tenantUser();
        $user->company->licenses()->firstOrFail()->update([
            'max_users' => 5,
            'max_concurrent_agents' => 2,
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 10,
        ]);
        $agent = User::factory()->create([
            'company_id' => $user->company_id,
            'role_id' => $user->role_id,
            'user_type' => 'agent',
            'status' => 'active',
            'extension' => '1002',
        ]);
        AgentStatus::create([
            'company_id' => $user->company_id,
            'user_id' => $agent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);

        foreach (range(1, 2) as $index) {
            Call::create([
                'company_id' => $user->company_id,
                'direction' => 'outbound',
                'status' => 'in_progress',
                'channel' => 'simulated',
                'phone' => '3199999080'.$index,
                'started_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('2 chamadas ativas')
            ->assertSee('Limite por operador: 3')
            ->assertSee('Teto da empresa: 10')
            ->assertSee('Uso atual da empresa')
            ->assertSee('Usuarios ativos')
            ->assertSee('2 / 5')
            ->assertSee('Agentes disponiveis')
            ->assertSee('1 / 2')
            ->assertSee('Chamadas simultaneas')
            ->assertSee('2 / 10')
            ->assertSee('Capacidade efetiva agora')
            ->assertSee('3');
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
            'tenant.telephony.index' => 'Telefonia',
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

    public function test_tenant_settings_route_loads_real_module(): void
    {
        $user = $this->tenantUser();

        $this->actingAs($user)
            ->get(route('tenant.settings.index'))
            ->assertOk()
            ->assertSee('Configuracoes Operacionais')
            ->assertDontSee('Modulo em breve');
    }

    private function tenantUser(): User
    {
        $company = Company::create(['name' => 'Tenant A']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Administrador',
            'slug' => 'administrador',
            'is_admin' => true,
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'company_admin',
            'status' => 'active',
            'extension' => '1001',
        ]);
    }
}
