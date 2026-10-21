<?php

namespace Tests\Feature;

use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalDashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_administrator_sees_all_company_operational_metrics(): void
    {
        [$administrator, $company] = $this->user('company_admin');
        [$firstAgent] = $this->user('agent', $company, 'Agente Um');
        [$secondAgent] = $this->user('agent', $company, 'Agente Dois');

        $this->createAgentStatus($firstAgent, 'available');
        $this->createAgentStatus($secondAgent, 'in_call');
        $this->createCall($firstAgent, 'ended', true);
        $this->createCall($secondAgent, 'in_progress');

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Agentes disponiveis')
            ->assertSee('Em chamada')
            ->assertSee('Chamadas hoje')
            ->assertSee('2')
            ->assertSee('1');
    }

    public function test_supervisor_dashboard_and_supervision_are_limited_to_assigned_teams(): void
    {
        [$supervisor, $company] = $this->user('supervisor', null, 'Supervisora');
        [$visibleAgent] = $this->user('agent', $company, 'Agente da Equipe');
        [$hiddenAgent] = $this->user('agent', $company, 'Agente de Outra Equipe');
        $visibleTeam = Team::create(['company_id' => $company->id, 'name' => 'Equipe Visivel', 'slug' => 'equipe-visivel']);
        $hiddenTeam = Team::create(['company_id' => $company->id, 'name' => 'Equipe Oculta', 'slug' => 'equipe-oculta']);

        $visibleTeam->users()->attach($supervisor, ['company_id' => $company->id, 'membership_type' => 'supervisor']);
        $visibleTeam->users()->attach($visibleAgent, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $hiddenTeam->users()->attach($hiddenAgent, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $this->createAgentStatus($visibleAgent, 'available');
        $this->createAgentStatus($hiddenAgent, 'in_call');
        $this->createCall($visibleAgent, 'ended', true);
        $this->createCall($hiddenAgent, 'in_progress');

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Escopo: Equipe Visivel')
            ->assertSee('Agentes disponiveis')
            ->assertSee('1')
            ->assertDontSee('Agente de Outra Equipe');

        $this->actingAs($supervisor)
            ->get(route('dashboard.poll'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('id="operational-dashboard"', false)
            ->assertSee('Escopo: Equipe Visivel');

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.index'))
            ->assertOk()
            ->assertSee('Agente da Equipe')
            ->assertDontSee('Agente de Outra Equipe')
            ->assertDontSee('Equipe Oculta');

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.index', ['team_id' => $hiddenTeam->id]))
            ->assertNotFound();
    }

    public function test_super_admin_receives_platform_wide_operational_summary(): void
    {
        [, $firstCompany] = $this->user('company_admin');
        [, $secondCompany] = $this->user('company_admin');
        [$firstAgent] = $this->user('agent', $firstCompany);
        [$secondAgent] = $this->user('agent', $secondCompany);
        $this->createAgentStatus($firstAgent, 'available');
        $this->createAgentStatus($secondAgent, 'available');
        $this->createCall($firstAgent, 'in_progress');
        $this->createCall($secondAgent, 'in_progress');
        $superAdmin = User::factory()->create(['is_super_admin' => true, 'user_type' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Empresas ativas')
            ->assertSee('Uso consolidado da plataforma.')
            ->assertSee('2');
    }

    public function test_super_admin_god_mode_dashboard_uses_the_company_being_observed(): void
    {
        [$administrator, $company] = $this->user('company_admin');
        [$agent] = $this->user('agent', $company, 'Agente observado');
        $this->createAgentStatus($agent, 'available');
        $this->createCall($agent, 'in_progress');
        $superAdmin = User::factory()->create(['is_super_admin' => true, 'user_type' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->withSession(['god_mode.company_id' => $company->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('scopeLabel', 'Escopo: Toda a empresa')
            ->assertViewHas('metrics.available', 1)
            ->assertViewHas('metrics.active_calls', 1)
            ->assertViewHas('metrics.agent_capacity', 3);
    }

    private function user(string $type, ?Company $company = null, ?string $name = null): array
    {
        $company ??= Company::create(['name' => 'Empresa Dashboard '.fake()->unique()->numerify('###')]);
        $company->licenses()->firstOrCreate([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ], [
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 10,
        ]);
        $role = Role::firstOrCreate([
            'company_id' => $company->id,
            'slug' => $type.'-dashboard',
        ], [
            'name' => $type,
            'is_admin' => true,
        ]);
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['key' => 'dashboard.read'], ['name' => 'Ler', 'module' => 'dashboard'])->id,
            Permission::firstOrCreate(['key' => 'supervision.read'], ['name' => 'Ler', 'module' => 'supervision'])->id,
        ]);

        return [User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => $type,
            'name' => $name ?? fake()->name(),
            'status' => 'active',
            'extension' => fake()->unique()->numerify('1###'),
        ]), $company];
    }

    private function createAgentStatus(User $user, string $status): void
    {
        AgentStatus::create(['company_id' => $user->company_id, 'user_id' => $user->id, 'status' => $status, 'started_at' => now()]);
    }

    private function createCall(User $user, string $status, bool $answered = false): void
    {
        Call::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'direction' => 'outbound',
            'status' => $status,
            'channel' => 'simulated',
            'phone' => '3199999'.fake()->unique()->numerify('####'),
            'started_at' => now(),
            'answered_at' => $answered ? now() : null,
            'ended_at' => $status === 'ended' ? now() : null,
        ]);
    }
}
