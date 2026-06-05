<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminCompanyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_company_with_license_limits(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('super-admin.companies.store'), $this->payload([
                'name' => 'Nova Empresa',
                'max_calls_per_available_agent' => 5,
            ]))
            ->assertRedirect(route('super-admin.companies.index'))
            ->assertSessionHas('status');

        $company = Company::where('name', 'Nova Empresa')->sole();

        $this->assertSame(5, $company->license->max_calls_per_available_agent);
        $this->assertSame('active', $company->status);
    }

    public function test_super_admin_can_update_company_license(): void
    {
        $company = Company::create(['name' => 'Empresa Antiga']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_calls_per_available_agent' => 2,
        ]);

        $this->actingAs($this->superAdmin())
            ->put(route('super-admin.companies.update', $company), $this->payload([
                'name' => 'Empresa Atualizada',
                'max_calls_per_available_agent' => 7,
                'predictive_enabled' => '1',
                'amd_enabled' => '1',
            ]))
            ->assertRedirect(route('super-admin.companies.index'))
            ->assertSessionHas('status');

        $company->refresh();

        $this->assertSame('Empresa Atualizada', $company->name);
        $this->assertSame(7, $company->license->max_calls_per_available_agent);
        $this->assertTrue($company->license->predictive_enabled);
        $this->assertTrue($company->license->amd_enabled);
    }

    public function test_destroy_inactivates_company_instead_of_deleting(): void
    {
        $company = Company::create(['name' => 'Empresa']);

        $this->actingAs($this->superAdmin())
            ->delete(route('super-admin.companies.destroy', $company))
            ->assertRedirect(route('super-admin.companies.index'))
            ->assertSessionHas('status');

        $this->assertSame('inactive', $company->fresh()->status);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Empresa Demo',
            'document' => '12345678000199',
            'status' => 'active',
            'license_starts_at' => now()->subDay()->toDateString(),
            'license_ends_at' => now()->addMonth()->toDateString(),
            'max_users' => 25,
            'max_concurrent_agents' => 10,
            'max_calls_per_available_agent' => 3,
            'max_concurrent_calls' => 30,
            'power_enabled' => '1',
            'preview_enabled' => '1',
        ], $overrides);
    }
}
