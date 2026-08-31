<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('Acesse o painel administrativo do discador.');
        $response->assertDontSee('Lembrar acesso');
        $response->assertDontSee('Esqueceu sua senha?');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('tenant.agent.index', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_agent_dashboard_never_requires_administrative_dashboard_permission(): void
    {
        $company = Company::create(['name' => 'Empresa do Agente']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'agent',
            'status' => 'active',
            'extension' => '1001',
        ]);

        $this->actingAs($agent)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Meu dashboard');
    }

    public function test_an_agent_can_open_the_agent_console_without_a_role_permission_record(): void
    {
        $company = $this->activeCompany();
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Agentes sem permissoes herdadas',
            'slug' => 'agentes-sem-permissoes-herdadas',
        ]);
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'agent',
            'status' => 'active',
            'extension' => '1001',
        ]);

        $this->assertFalse($agent->hasPermission('users.read'));
        $this->assertTrue($agent->hasPermission('agent.access'));

        $this->actingAs($agent)
            ->get(route('tenant.agent.index'))
            ->assertOk()
            ->assertSee('Painel do Agente');
    }

    public function test_each_user_type_is_redirected_to_its_authorized_home_after_login(): void
    {
        $company = $this->activeCompany();
        $agentRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Agente',
            'slug' => 'agente-home',
        ]);
        $operatorRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Operacao',
            'slug' => 'operacao-home',
            'is_admin' => true,
        ]);
        $dashboardPermission = Permission::query()->firstOrCreate(
            ['key' => 'dashboard.read'],
            ['name' => 'Ler painel', 'module' => 'dashboard'],
        );
        $operatorRole->permissions()->attach($dashboardPermission);

        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $agentRole->id,
            'user_type' => 'agent',
            'status' => 'active',
            'extension' => '1002',
        ]);
        $supervisor = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $operatorRole->id,
            'user_type' => 'supervisor',
            'status' => 'active',
        ]);
        $administrator = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $operatorRole->id,
            'user_type' => 'company_admin',
            'status' => 'active',
        ]);
        $superAdmin = User::factory()->create([
            'user_type' => 'super_admin',
            'is_super_admin' => true,
            'status' => 'active',
        ]);

        foreach ([
            [$agent, route('tenant.agent.index', absolute: false)],
            [$supervisor, route('dashboard', absolute: false)],
            [$administrator, route('dashboard', absolute: false)],
            [$superAdmin, route('dashboard', absolute: false)],
        ] as [$user, $expectedPath]) {
            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $response->assertRedirect($expectedPath);
            $this->post('/logout')->assertRedirect('/');
        }
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    private function activeCompany(): Company
    {
        $company = Company::create(['name' => 'Empresa de autenticacao '.fake()->unique()->numberBetween(1000, 9999)]);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        return $company;
    }
}
