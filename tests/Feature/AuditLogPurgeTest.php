<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogPurgeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_super_admin_purges_one_company_period_and_records_the_purge(): void
    {
        Carbon::setTestNow('2026-06-28 12:00:00');
        $company = Company::create(['name' => 'Tenant A']);
        $other = Company::create(['name' => 'Tenant B']);
        $admin = $this->superAdmin();
        AuditLog::query()->delete();
        $inside = $this->log($company->id, '2026-06-10 10:00:00', 'Remover');
        $outside = $this->log($company->id, '2026-05-10 10:00:00', 'Manter periodo');
        $otherLog = $this->log($other->id, '2026-06-10 10:00:00', 'Manter tenant');

        $this->actingAs($admin)
            ->delete(route('super-admin.audit-logs.purge'), [
                'scope' => 'company',
                'company_id' => $company->id,
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'reason' => 'Solicitacao administrativa aprovada.',
            ])
            ->assertRedirect(route('super-admin.audit-logs.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('audit_logs', ['id' => $inside->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $outside->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $otherLog->id]);
        $purge = AuditLog::where('event', 'audit.purged')->sole();
        $this->assertSame($admin->id, $purge->actor_user_id);
        $this->assertSame($company->id, $purge->company_id);
        $this->assertSame(1, $purge->metadata['deleted_count']);
        $this->assertSame('Solicitacao administrativa aprovada.', $purge->metadata['reason']);
    }

    public function test_super_admin_can_purge_only_global_events(): void
    {
        $company = Company::create(['name' => 'Tenant A']);
        $admin = $this->superAdmin();
        AuditLog::query()->delete();
        $global = $this->log(null, '2026-06-10 10:00:00', 'Global');
        $tenant = $this->log($company->id, '2026-06-10 10:00:00', 'Tenant');

        $this->actingAs($admin)
            ->delete(route('super-admin.audit-logs.purge'), [
                'scope' => 'global',
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'reason' => 'Limpeza global autorizada.',
            ])
            ->assertRedirect(route('super-admin.audit-logs.index'));

        $this->assertDatabaseMissing('audit_logs', ['id' => $global->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $tenant->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'audit.purged', 'company_id' => null]);
    }

    public function test_purge_validates_scope_period_company_and_reason(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->from(route('super-admin.audit-logs.index'))
            ->delete(route('super-admin.audit-logs.purge'), [
                'scope' => 'all',
                'date_from' => '2026-06-30',
                'date_to' => '2026-06-01',
            ])
            ->assertRedirect(route('super-admin.audit-logs.index'))
            ->assertSessionHasErrors(['scope', 'date_to', 'reason']);

        $this->actingAs($admin)
            ->from(route('super-admin.audit-logs.index'))
            ->delete(route('super-admin.audit-logs.purge'), [
                'scope' => 'company',
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'reason' => 'Sem empresa.',
            ])
            ->assertSessionHasErrors('company_id');
    }

    public function test_zero_match_returns_inline_error_and_tenant_admin_is_forbidden(): void
    {
        $company = Company::create(['name' => 'Tenant A']);
        $admin = $this->superAdmin();
        $tenantAdmin = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'company_admin',
            'extension' => '1000',
        ]);
        AuditLog::query()->delete();

        $this->actingAs($admin)
            ->from(route('super-admin.audit-logs.index'))
            ->delete(route('super-admin.audit-logs.purge'), [
                'scope' => 'company',
                'company_id' => $company->id,
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
                'reason' => 'Periodo sem eventos.',
            ])
            ->assertRedirect(route('super-admin.audit-logs.index'))
            ->assertSessionHasErrors('purge');

        $this->assertDatabaseMissing('audit_logs', ['event' => 'audit.purged']);

        $this->actingAs($tenantAdmin)
            ->delete(route('super-admin.audit-logs.purge'), [
                'scope' => 'global',
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
                'reason' => 'Nao permitido.',
            ])
            ->assertForbidden();
    }

    private function log(?int $companyId, string $createdAt, string $description): AuditLog
    {
        return AuditLog::create([
            'company_id' => $companyId,
            'event' => 'test.event',
            'action' => 'updated',
            'description' => $description,
            'created_at' => $createdAt,
        ]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_super_admin' => true,
            'user_type' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
