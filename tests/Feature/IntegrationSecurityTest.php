<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_webhook_rejects_private_network_targets(): void
    {
        $this->actingAs($this->integrationManager())
            ->from(route('tenant.integrations.index'))
            ->post(route('tenant.integrations.store'), $this->payload('https://127.0.0.1/hook'))
            ->assertRedirect(route('tenant.integrations.index'))
            ->assertSessionHasErrors('endpoint');
    }

    public function test_webhook_rejects_url_credentials_and_non_standard_ports(): void
    {
        $user = $this->integrationManager();

        $this->actingAs($user)
            ->from(route('tenant.integrations.index'))
            ->post(route('tenant.integrations.store'), $this->payload('https://token:secret@example.com/hook'))
            ->assertRedirect(route('tenant.integrations.index'))
            ->assertSessionHasErrors('endpoint');

        $this->actingAs($user)
            ->from(route('tenant.integrations.index'))
            ->post(route('tenant.integrations.store'), $this->payload('https://example.com:8443/hook'))
            ->assertRedirect(route('tenant.integrations.index'))
            ->assertSessionHasErrors('endpoint');
    }

    private function integrationManager(): User
    {
        $this->seed(PermissionSeeder::class);

        $company = Company::create(['name' => fake()->unique()->company()]);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_users' => 20,
        ]);

        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Gerente de Integracoes',
            'slug' => 'gerente-integracoes',
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('key', ['integrations.read', 'integrations.create'])->pluck('id')
        );

        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'company_admin',
            'status' => 'active',
            'extension' => fake()->unique()->numerify('1###'),
            'email_verified_at' => now(),
        ]);
    }

    private function payload(string $endpoint): array
    {
        return [
            'name' => 'Webhook seguro',
            'endpoint' => $endpoint,
            'events' => ['call.completed'],
            'status' => 'active',
        ];
    }
}
