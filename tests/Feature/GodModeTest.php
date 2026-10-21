<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GodModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_enter_and_leave_a_company_without_becoming_a_tenant_user(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
            'company_id' => null,
        ]);
        $company = $this->company('Empresa A');
        $otherCompany = $this->company('Empresa B');
        $role = Role::create(['company_id' => $company->id, 'name' => 'Administrador', 'slug' => 'admin', 'is_admin' => true]);

        User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'name' => 'Usuario Empresa A',
            'extension' => '2001',
        ]);
        User::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Usuario Empresa B',
            'extension' => '3001',
        ]);
        $usersBefore = User::query()->count();

        $this->actingAs($superAdmin)
            ->post(route('super-admin.companies.god-mode.enter', $company))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('god_mode.company_id', $company->id);

        $this->get(route('tenant.users.index'))
            ->assertOk()
            ->assertSee('Usuario Empresa A')
            ->assertDontSee('Usuario Empresa B')
            ->assertSee('Modo Deus ativo')
            ->assertDontSee('href="'.route('tenant.agent.index').'"', false);

        $this->assertSame($usersBefore, User::query()->count());
        $this->assertDatabaseMissing('audit_logs', [
            'company_id' => $company->id,
            'actor_user_id' => $superAdmin->id,
            'event' => 'god_mode.entered',
        ]);

        $this->delete(route('super-admin.god-mode.leave'))
            ->assertRedirect(route('super-admin.companies.index'))
            ->assertSessionMissing('god_mode.company_id');

        $this->assertDatabaseMissing('audit_logs', [
            'company_id' => $company->id,
            'actor_user_id' => $superAdmin->id,
            'event' => 'god_mode.left',
        ]);
    }

    public function test_god_mode_blocks_telephony_actions(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
            'company_id' => null,
        ]);
        $company = $this->company('Empresa Telefonia');

        $this->actingAs($superAdmin)
            ->post(route('super-admin.companies.god-mode.enter', $company));

        $this->get(route('tenant.agent.index'))->assertForbidden();
    }

    public function test_super_admin_can_open_each_company_module_in_god_mode(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
            'company_id' => null,
        ]);
        $company = $this->company('Empresa Acompanhada');

        $this->actingAs($superAdmin)
            ->post(route('super-admin.companies.god-mode.enter', $company))
            ->assertRedirect(route('dashboard'));

        foreach ([
            route('dashboard'),
            route('tenant.users.index'),
            route('tenant.teams.index'),
            route('tenant.roles.index'),
            route('tenant.lists.index'),
            route('tenant.campaigns.index'),
            route('tenant.supervision.index'),
            route('tenant.reports.index'),
            route('tenant.telephony.index'),
            route('tenant.settings.index'),
            route('tenant.integrations.index'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Modo Deus ativo');
        }

        $this->get(route('tenant.agent.index'))->assertForbidden();
    }

    private function company(string $name): Company
    {
        $company = Company::create(['name' => $name]);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        return $company;
    }
}
