<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PollingMarkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervision_page_configures_resilient_polling_regions(): void
    {
        $supervisor = $this->tenantUser('supervisor');

        $this->actingAs($supervisor)
            ->get(route('tenant.supervision.index'))
            ->assertOk()
            ->assertSee('pollingRegion(', false)
            ->assertSee(route('tenant.supervision.poll'), false)
            ->assertSee("'#supervision-agents'", false)
            ->assertSee("'#supervision-calls'", false)
            ->assertSee('x-init="start(); subscribeToSupervision(', false)
            ->assertSee('() => poll(true)', false)
            ->assertSee(
                'x-on:visibilitychange.document="handleVisibilityChange()"',
                false
            );
    }

    public function test_authenticated_layout_uses_only_the_navigation_sidebar(): void
    {
        $admin = $this->tenantUser('company_admin');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-layout-sidebar', false)
            ->assertDontSee('id="notification-panel"', false)
            ->assertDontSee(route('tenant.notifications.poll'), false);
    }

    public function test_polling_source_uses_abort_and_visibility_without_blocking_browser_apis(): void
    {
        $source = file_get_contents(resource_path('js/polling.js'));

        $this->assertStringContainsString('AbortController', $source);
        $this->assertStringContainsString('visibility', $source);
        $this->assertStringNotContainsString('setInterval(', $source);
        $this->assertStringNotContainsString('alert(', $source);
        $this->assertStringNotContainsString('confirm(', $source);
    }

    private function tenantUser(string $type): User
    {
        $company = Company::create([
            'name' => 'Tenant Polling '.fake()->unique()->numberBetween(1000, 9999),
        ]);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Operacao Polling',
            'slug' => 'operacao-polling-'.fake()->unique()->numberBetween(1000, 9999),
            'is_admin' => true,
        ]);
        if ($type === 'supervisor') {
            $role->permissions()->attach(Permission::query()->firstOrCreate(
                ['key' => 'supervision.read'],
                ['name' => 'Ler', 'module' => 'supervision'],
            ));
        }

        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => $type,
            'extension' => fake()->unique()->numerify('4###'),
            'status' => 'active',
        ]);
    }
}
