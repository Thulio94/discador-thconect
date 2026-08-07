<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseBillingNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_license_calculates_the_next_monthly_billing_date(): void
    {
        Carbon::setTestNow('2026-08-20');

        try {
            $license = $this->company()->licenses()->create([
                'status' => 'active',
                'starts_at' => now()->subMonth()->toDateString(),
                'ends_at' => now()->addYear()->toDateString(),
                'billing_due_day' => 18,
                'max_calls_per_available_agent' => 1,
            ]);

            $notice = $license->billingNotice();

            $this->assertSame('18/09/2026', $notice['due_at']->format('d/m/Y'));
            $this->assertSame(29, $notice['days_until']);
            $this->assertFalse($notice['is_due_soon']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_company_owner_sees_payment_notice_five_days_before_due_date(): void
    {
        Carbon::setTestNow('2026-08-20');

        try {
            $company = $this->company();
            $company->licenses()->create([
                'status' => 'active',
                'starts_at' => now()->subMonth()->toDateString(),
                'ends_at' => now()->addYear()->toDateString(),
                'billing_due_day' => 24,
                'max_calls_per_available_agent' => 1,
            ]);
            $role = Role::create([
                'company_id' => $company->id,
                'name' => 'Administrador',
                'slug' => 'administrador',
                'is_admin' => true,
            ]);
            $owner = User::factory()->create([
                'company_id' => $company->id,
                'role_id' => $role->id,
                'user_type' => 'company_admin',
                'extension' => '1100',
                'status' => 'active',
            ]);

            $this->actingAs($owner)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Pagamento proximo')
                ->assertSee('24/08/2026');
        } finally {
            Carbon::setTestNow();
        }
    }

    private function company(): Company
    {
        return Company::create([
            'name' => 'Empresa de Cobranca',
            'status' => 'active',
        ]);
    }
}
