<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Company;
use App\Models\CompanyTelephonyConfiguration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantTelephonyConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_configure_telephony_and_credentials_are_encrypted(): void
    {
        $admin = $this->tenantAdmin();

        $this->actingAs($admin)
            ->get(route('tenant.telephony.index'))
            ->assertOk()
            ->assertSee('Rota e Asterisk AMI')
            ->assertSee('Simulador ativo');

        $this->actingAs($admin)
            ->put(route('tenant.telephony.update'), [
                'driver' => 'asterisk_ami',
                'trunk_label' => 'SIP Principal',
                'outbound_route_label' => 'Saida Comercial',
                'caller_id' => '3133334444',
                'ami_host' => 'pbx.empresa.test',
                'ami_port' => 5038,
                'ami_username' => 'discador',
                'ami_secret' => 'segredo-da-rota',
                'outbound_channel_template' => 'PJSIP/{phone}@principal',
                'supervisor_channel_template' => 'PJSIP/{extension}',
                'dialplan_context' => 'discador-outbound',
                'dialplan_extension' => 's',
                'bridge_enabled' => '1',
            ])
            ->assertRedirect(route('tenant.telephony.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('company_telephony_configurations', [
            'company_id' => $admin->company_id,
            'trunk_label' => 'SIP Principal',
            'outbound_route_label' => 'Saida Comercial',
            'caller_id' => '3133334444',
            'driver' => 'asterisk_ami',
            'ami_host' => 'pbx.empresa.test',
        ]);

        $configuration = CompanyTelephonyConfiguration::query()->firstOrFail();
        $this->assertSame('discador', $configuration->ami_username);
        $this->assertSame('segredo-da-rota', $configuration->ami_secret);
        $this->assertSame('PJSIP/{extension}', $configuration->monitoringChannelTemplate());
        $this->assertStringNotContainsString('segredo-da-rota', (string) DB::table('company_telephony_configurations')->value('ami_secret'));
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

    public function test_signed_asterisk_event_updates_only_the_matching_company_call(): void
    {
        config()->set('services.asterisk_bridge.webhook_secret', 'webhook-test-secret');
        $admin = $this->tenantAdmin();
        $call = Call::create([
            'company_id' => $admin->company_id,
            'user_id' => $admin->id,
            'direction' => 'outbound',
            'status' => 'dialing',
            'channel' => 'asterisk_ami',
            'phone' => '31999990001',
            'started_at' => now(),
        ]);
        $payload = [
            'company_id' => $admin->company_id,
            'call_id' => $call->id,
            'event' => 'answered',
            'channel' => 'PJSIP/rota-0001',
            'uniqueid' => '1720000000.1',
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->postJson(route('bridge.events'), $payload, [
            'X-THCONECT-Signature' => hash_hmac('sha256', $json, 'webhook-test-secret'),
        ])->assertOk();

        $call->refresh();
        $this->assertSame('in_progress', $call->status);
        $this->assertSame('PJSIP/rota-0001', $call->telephony_channel);
        $this->assertNotNull($call->answered_at);
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
