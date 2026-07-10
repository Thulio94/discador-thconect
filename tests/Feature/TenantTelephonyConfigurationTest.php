<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyTelephonyConfiguration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTelephonyConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_prepare_telephony_labels_without_external_connection(): void
    {
        $admin = $this->tenantAdmin();

        $this->actingAs($admin)
            ->get(route('tenant.telephony.index'))
            ->assertOk()
            ->assertSee('Preparacao de telefonia')
            ->assertSee('Asterisk e AMI permanece em breve');

        $this->actingAs($admin)
            ->put(route('tenant.telephony.update'), [
                'trunk_label' => 'SIP Principal',
                'outbound_route_label' => 'Saida Comercial',
                'caller_id' => '3133334444',
            ])
            ->assertRedirect(route('tenant.telephony.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('company_telephony_configurations', [
            'company_id' => $admin->company_id,
            'trunk_label' => 'SIP Principal',
            'outbound_route_label' => 'Saida Comercial',
            'caller_id' => '3133334444',
        ]);
    }

    public function test_telephony_preparation_is_isolated_by_company(): void
    {
        $admin = $this->tenantAdmin();
        $otherCompany = Company::create(['name' => 'Outra Empresa']);
        CompanyTelephonyConfiguration::create([
            'company_id' => $otherCompany->id,
            'trunk_label' => 'Tronco da Outra Empresa',
        ]);

        $this->actingAs($admin)
            ->get(route('tenant.telephony.index'))
            ->assertOk()
            ->assertDontSee('Tronco da Outra Empresa');
    }

    private function tenantAdmin(): User
    {
        $company = Company::create(['name' => 'Empresa Telefonia']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Administrador',
            'slug' => 'administrador-telefonia',
            'is_admin' => true,
        ]);
        $role->permissions()->sync(
            collect(['telephony.read', 'telephony.update'])
                ->map(fn (string $key) => Permission::firstOrCreate([
                    'key' => $key,
                ], [
                    'name' => $key === 'telephony.read' ? 'Ler' : 'Preparar rota',
                    'module' => 'telephony',
                ])->id)
                ->all(),
        );

        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'company_admin',
            'extension' => '1000',
            'status' => 'active',
        ]);
    }
}
