<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_casts_payloads_and_exposes_snapshot_relations(): void
    {
        $company = Company::create(['name' => 'Tenant Auditado']);
        $actor = User::factory()->create(['company_id' => $company->id]);

        $log = AuditLog::create([
            'company_id' => $company->id,
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'event' => 'user.updated',
            'action' => 'updated',
            'subject_type' => User::class,
            'subject_id' => $actor->id,
            'subject_label' => $actor->name,
            'description' => 'Usuario atualizado.',
            'old_values' => ['status' => 'inactive'],
            'new_values' => ['status' => 'active'],
            'metadata' => ['source' => 'test'],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $this->assertSame(['status' => 'inactive'], $log->old_values);
        $this->assertSame(['status' => 'active'], $log->new_values);
        $this->assertTrue($log->company->is($company));
        $this->assertTrue($log->actor->is($actor));
        $this->assertTrue($log->subject->is($actor));
        $this->assertTrue($company->auditLogs->contains($log));
    }
}
