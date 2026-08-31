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
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(self), geolocation=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
    }

    public function test_production_csp_keeps_alpine_interactions_available(): void
    {
        config()->set('app.env', 'production');

        Route::get('/_test/production-csp', fn () => 'ok');

        $this->get('/_test/production-csp')
            ->assertOk()
            ->assertHeader(
                'Content-Security-Policy',
                "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; img-src 'self' data:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-eval'; connect-src 'self' wss:"
            );
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
