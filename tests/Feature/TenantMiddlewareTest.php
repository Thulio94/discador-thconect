<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_tenant_user_can_pass_tenant_middleware(): void
    {
        Route::get('/_test/tenant-active', fn () => 'ok')->middleware('tenant.active');

        $user = $this->tenantUserWithLicense();

        $this->actingAs($user)
            ->get('/_test/tenant-active')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_expired_license_blocks_tenant_middleware(): void
    {
        Route::get('/_test/tenant-blocked', fn () => 'ok')->middleware('tenant.active');

        $company = Company::create(['name' => 'Tenant A']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subMonths(2)->toDateString(),
            'ends_at' => now()->subMonth()->toDateString(),
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'extension' => '1001',
        ]);

        $this->actingAs($user)
            ->get('/_test/tenant-blocked')
            ->assertForbidden();
    }

    public function test_super_admin_middleware_requires_super_admin_flag(): void
    {
        Route::get('/_test/super-admin', fn () => 'ok')->middleware('super.admin');

        $regularUser = User::factory()->create([
            'is_super_admin' => false,
            'user_type' => 'super_admin',
        ]);

        $this->actingAs($regularUser)
            ->get('/_test/super-admin')
            ->assertForbidden();

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
        ]);

        $this->actingAs($superAdmin)
            ->get('/_test/super-admin')
            ->assertOk();
    }

    public function test_security_headers_are_applied(): void
    {
        Route::get('/_test/security-headers', fn () => 'ok');

        $this->get('/_test/security-headers')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }

    private function tenantUserWithLicense(): User
    {
        $company = Company::create(['name' => 'Tenant A']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'extension' => '1001',
        ]);
    }
}
