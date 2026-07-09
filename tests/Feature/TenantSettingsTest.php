<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BlacklistNumber;
use App\Models\Company;
use App\Models\Disposition;
use App\Models\OperationalParameter;
use App\Models\PauseReason;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_route_loads_real_operational_page(): void
    {
        [$admin] = $this->tenantAdmin();

        PauseReason::create([
            'company_id' => $admin->company_id,
            'name' => 'Almoco',
            'status' => 'active',
        ]);
        Disposition::create([
            'company_id' => $admin->company_id,
            'name' => 'Venda concluida',
            'code' => 'VENDA',
            'category' => 'success',
            'status' => 'active',
        ]);
        BlacklistNumber::create([
            'company_id' => $admin->company_id,
            'phone' => '31999990001',
            'reason' => 'Nao ligar',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.settings.index'))
            ->assertOk()
            ->assertSee('Configuracoes Operacionais')
            ->assertSee('Motivos de pausa')
            ->assertSee('Tabulacoes')
            ->assertSee('Blacklist')
            ->assertSee('Parametros gerais')
            ->assertSee('Almoco')
            ->assertSee('Venda concluida')
            ->assertSee('31999990001')
            ->assertSee('create-pause-reason')
            ->assertSee('Telefonia - Em breve')
            ->assertSee('Integracoes - Em breve')
            ->assertDontSee('Modulo em breve')
            ->assertDontSee('javascript.alert')
            ->assertDontSee('confirm(');

        $this->assertFalse(OperationalParameter::query()->where('company_id', $admin->company_id)->exists());
    }

    public function test_tenant_admin_can_update_operational_parameters_with_audit(): void
    {
        [$admin] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->put(route('tenant.settings.operational-parameters.update'), [
                'min_pause_minutes' => '5',
                'max_pause_minutes' => '60',
                'pause_alert_minutes' => '12',
                'call_alert_minutes' => '8',
                'auto_available_after_call' => '0',
                'allow_manual_calls_while_paused' => '1',
            ])
            ->assertRedirect(route('tenant.settings.index'))
            ->assertSessionHas('status');

        $parameters = OperationalParameter::where('company_id', $admin->company_id)->sole();

        $this->assertSame(5, $parameters->min_pause_minutes);
        $this->assertSame(60, $parameters->max_pause_minutes);
        $this->assertSame(12, $parameters->pause_alert_minutes);
        $this->assertSame(8, $parameters->call_alert_minutes);
        $this->assertFalse($parameters->auto_available_after_call);
        $this->assertTrue($parameters->allow_manual_calls_while_paused);

        $this->assertTrue(AuditLog::query()
            ->where('company_id', $admin->company_id)
            ->where('event', 'operational_parameter.created')
            ->where('actor_user_id', $admin->id)
            ->exists());
    }

    public function test_operational_parameters_validate_pause_range(): void
    {
        [$admin] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->from(route('tenant.settings.index'))
            ->put(route('tenant.settings.operational-parameters.update'), [
                'min_pause_minutes' => '30',
                'max_pause_minutes' => '10',
                'pause_alert_minutes' => '12',
                'call_alert_minutes' => '8',
                'auto_available_after_call' => '1',
                'allow_manual_calls_while_paused' => '0',
            ])
            ->assertRedirect(route('tenant.settings.index'))
            ->assertSessionHasErrors('max_pause_minutes');
    }

    public function test_tenant_admin_can_manage_pause_reasons(): void
    {
        [$admin] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->post(route('tenant.settings.pause-reasons.store'), [
                'name' => 'Pausa almoco',
                'description' => 'Intervalo operacional',
                'status' => 'active',
                'is_billable' => '0',
            ])
            ->assertRedirect(route('tenant.settings.index'))
            ->assertSessionHas('status');

        $reason = PauseReason::where('company_id', $admin->company_id)->where('name', 'Pausa almoco')->sole();
        $this->assertFalse($reason->is_billable);

        $this->actingAs($admin)
            ->put(route('tenant.settings.pause-reasons.update', $reason), [
                'name' => 'Almoco atualizado',
                'description' => 'Pausa revisada',
                'status' => 'active',
                'is_billable' => '1',
            ])
            ->assertRedirect(route('tenant.settings.index'))
            ->assertSessionHas('status');

        $this->assertSame('Almoco atualizado', $reason->fresh()->name);
        $this->assertTrue($reason->fresh()->is_billable);

        $this->actingAs($admin)
            ->delete(route('tenant.settings.pause-reasons.destroy', $reason))
            ->assertRedirect(route('tenant.settings.index'))
            ->assertSessionHas('status');

        $this->assertSame('inactive', $reason->fresh()->status);
    }

    public function test_tenant_admin_can_manage_dispositions_and_blacklist(): void
    {
        [$admin] = $this->tenantAdmin();

        $this->actingAs($admin)
            ->post(route('tenant.settings.dispositions.store'), [
                'name' => 'Cliente sem interesse',
                'code' => 'SEM_INTERESSE',
                'category' => 'neutral',
                'status' => 'active',
                'ends_call' => '1',
                'is_success' => '0',
                'retry_enabled' => '1',
                'retry_delay_minutes' => '30',
                'max_attempts' => '4',
            ])
            ->assertRedirect(route('tenant.settings.index'))
            ->assertSessionHas('status');

        $disposition = Disposition::where('company_id', $admin->company_id)->where('code', 'SEM_INTERESSE')->sole();
        $this->assertTrue($disposition->ends_call);
        $this->assertTrue($disposition->retry_enabled);
        $this->assertSame(30, $disposition->retry_delay_minutes);
        $this->assertSame(4, $disposition->max_attempts);

        $this->actingAs($admin)
            ->get(route('tenant.settings.index'))
            ->assertOk()
            ->assertSee('Retry 30 min')
            ->assertSee('Max 4 tentativas');

        $this->actingAs($admin)
            ->post(route('tenant.settings.blacklist.store'), [
                'phone' => '(31) 99999-0002',
                'reason' => 'Bloqueio solicitado',
                'status' => 'active',
            ])
            ->assertRedirect(route('tenant.settings.index'))
            ->assertSessionHas('status');

        $blocked = BlacklistNumber::where('company_id', $admin->company_id)->where('phone', '31999990002')->sole();
        $this->assertSame('Bloqueio solicitado', $blocked->reason);

        $this->actingAs($admin)
            ->delete(route('tenant.settings.blacklist.destroy', $blocked))
            ->assertRedirect(route('tenant.settings.index'))
            ->assertSessionHas('status');

        $this->assertSame('inactive', $blocked->fresh()->status);
    }

    public function test_settings_records_are_isolated_by_company(): void
    {
        [$admin] = $this->tenantAdmin();
        [$otherAdmin] = $this->tenantAdmin('Tenant B');

        $otherReason = PauseReason::create([
            'company_id' => $otherAdmin->company_id,
            'name' => 'Outra pausa',
            'status' => 'active',
        ]);
        $otherDisposition = Disposition::create([
            'company_id' => $otherAdmin->company_id,
            'name' => 'Outra tabulacao',
            'code' => 'OUTRA',
            'category' => 'neutral',
            'status' => 'active',
        ]);
        $otherBlocked = BlacklistNumber::create([
            'company_id' => $otherAdmin->company_id,
            'phone' => '31999990003',
            'reason' => 'Outro tenant',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.settings.index'))
            ->assertOk()
            ->assertDontSee('Outra pausa')
            ->assertDontSee('Outra tabulacao')
            ->assertDontSee('31999990003');

        $this->actingAs($admin)
            ->put(route('tenant.settings.pause-reasons.update', $otherReason), [
                'name' => 'Tentativa',
                'status' => 'active',
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->put(route('tenant.settings.dispositions.update', $otherDisposition), [
                'name' => 'Tentativa',
                'code' => 'TENTATIVA',
                'category' => 'neutral',
                'status' => 'active',
            ])
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('tenant.settings.blacklist.destroy', $otherBlocked))
            ->assertNotFound();
    }

    public function test_agent_pause_modal_uses_configured_pause_reasons(): void
    {
        [$agent] = $this->tenantAdmin('Tenant Agente', 'agent');

        PauseReason::create([
            'company_id' => $agent->company_id,
            'name' => 'Treinamento',
            'status' => 'active',
        ]);

        $this->actingAs($agent)
            ->get(route('tenant.agent.index'))
            ->assertOk()
            ->assertSee('Treinamento');
    }

    private function tenantAdmin(string $companyName = 'Tenant A', string $type = 'company_admin'): array
    {
        $company = Company::create(['name' => $companyName]);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Administrador '.fake()->unique()->numberBetween(1000, 9999),
            'slug' => 'administrador-'.fake()->unique()->numberBetween(1000, 9999),
            'is_admin' => true,
        ]);
        if ($type === 'agent') {
            $role->permissions()->attach(Permission::query()->firstOrCreate(
                ['key' => 'agent.access'],
                ['name' => 'Acessar', 'module' => 'agent'],
            ));
        }
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => $type,
            'extension' => fake()->unique()->numerify('3###'),
            'status' => 'active',
        ]);

        return [$admin, $company];
    }
}
