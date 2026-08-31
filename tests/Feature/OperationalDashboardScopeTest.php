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

        $this->status($firstAgent, 'available');
        $this->status($secondAgent, 'in_call');
        $this->call($firstAgent, 'ended', true);
        $this->call($secondAgent, 'in_progress');

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
        $this->status($visibleAgent, 'available');
        $this->status($hiddenAgent, 'in_call');
        $this->call($visibleAgent, 'ended', true);
        $this->call($hiddenAgent, 'in_progress');

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
            ->assertForbidden();
    }

    public function test_super_admin_receives_platform_wide_operational_summary(): void
    {
        [, $firstCompany] = $this->user('company_admin');
        [, $secondCompany] = $this->user('company_admin');
        [$firstAgent] = $this->user('agent', $firstCompany);
        [$secondAgent] = $this->user('agent', $secondCompany);
        $this->status($firstAgent, 'available');
        $this->status($secondAgent, 'available');
        $this->call($firstAgent, 'in_progress');
        $this->call($secondAgent, 'in_progress');
        $superAdmin = User::factory()->create(['is_super_admin' => true, 'user_type' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Empresas ativas')
            ->assertSee('Uso consolidado da plataforma.')
            ->assertSee('2');
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

    private function status(User $user, string $status): void
    {
        AgentStatus::create(['company_id' => $user->company_id, 'user_id' => $user->id, 'status' => $status, 'started_at' => now()]);
    }

    private function call(User $user, string $status, bool $answered = false): void
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
