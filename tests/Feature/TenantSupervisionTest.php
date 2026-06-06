<?php

namespace Tests\Feature;

use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Company;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSupervisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervision_route_loads_real_panel_and_keeps_tenant_isolation(): void
    {
        [$supervisor, $company] = $this->tenantUser('supervisor');
        [$agent] = $this->tenantUser('agent', $company, 'Agente Tenant');
        [$otherAgent] = $this->tenantUser('agent', null, 'Agente Outra Empresa');

        AgentStatus::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);
        AgentStatus::create([
            'company_id' => $otherAgent->company_id,
            'user_id' => $otherAgent->id,
            'status' => 'available',
            'started_at' => now(),
        ]);

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.index'))
            ->assertOk()
            ->assertSee('Supervisao')
            ->assertSee('Agente Tenant')
            ->assertSee('Agentes em operacao')
            ->assertSee('supervision-agent-'.$agent->id, false)
            ->assertSee('Ouvir')
            ->assertSee('Sussurrar')
            ->assertSee('Intervir')
            ->assertDontSee('Agente Outra Empresa')
            ->assertDontSee('Modulo em breve');
    }

    public function test_supervision_metrics_filters_and_active_calls(): void
    {
        [$supervisor, $company] = $this->tenantUser('supervisor');
        [$available] = $this->tenantUser('agent', $company, 'Agente Disponivel');
        [$paused] = $this->tenantUser('agent', $company, 'Agente Pausado');
        [$inCall] = $this->tenantUser('agent', $company, 'Agente Em Chamada');
        [$offline] = $this->tenantUser('agent', $company, 'Agente Offline');
        $team = Team::create(['company_id' => $company->id, 'name' => 'Equipe Filtro', 'slug' => 'equipe-filtro']);
        $otherTeam = Team::create(['company_id' => $company->id, 'name' => 'Equipe Outra', 'slug' => 'equipe-outra']);

        $team->users()->attach($available, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $team->users()->attach($paused, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $otherTeam->users()->attach($inCall, ['company_id' => $company->id, 'membership_type' => 'agent']);

        AgentStatus::create(['company_id' => $company->id, 'user_id' => $available->id, 'status' => 'available', 'started_at' => now()]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $paused->id, 'status' => 'paused', 'started_at' => now()]);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $inCall->id, 'status' => 'in_call', 'started_at' => now()]);
        Call::create([
            'company_id' => $company->id,
            'user_id' => $inCall->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => '31999990001',
            'started_at' => now(),
        ]);

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.index'))
            ->assertOk()
            ->assertSee('Disponiveis')
            ->assertSee('Em pausa')
            ->assertSee('Em chamada')
            ->assertSee('Offline')
            ->assertSee('Chamadas ativas')
            ->assertSee('31999990001')
            ->assertSee('1 disponivel')
            ->assertSee('1 pausa')
            ->assertSee('1 chamada')
            ->assertSee('2 offline');

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.index', ['status' => 'paused']))
            ->assertOk()
            ->assertSee('Agente Pausado')
            ->assertDontSee('Agente Disponivel')
            ->assertDontSee('Agente Em Chamada');

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.index', ['team_id' => $team->id]))
            ->assertOk()
            ->assertSee('Agente Disponivel')
            ->assertSee('Agente Pausado')
            ->assertDontSee('Agente Em Chamada');
    }

    private function tenantUser(string $type, ?Company $company = null, ?string $name = null): array
    {
        $company ??= Company::create(['name' => 'Tenant Supervisao '.fake()->unique()->numberBetween(1000, 9999)]);

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
            'name' => 'Operacao '.fake()->unique()->numberBetween(1000, 9999),
            'slug' => 'operacao-'.fake()->unique()->numberBetween(1000, 9999),
            'is_admin' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => $name ?? fake()->name(),
            'user_type' => $type,
            'extension' => fake()->unique()->numerify('3###'),
            'status' => 'active',
        ]);

        return [$user, $company];
    }
}
