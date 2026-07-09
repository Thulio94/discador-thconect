<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuditLogRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_records_sanitized_snapshots_and_http_context(): void
    {
        $company = Company::create(['name' => 'Tenant A']);
        $actor = User::factory()->create(['company_id' => $company->id]);
        $request = Request::create('/audit-test', 'PATCH', server: [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_USER_AGENT' => 'Audit Browser',
        ]);
        app()->instance('request', $request);

        $log = app(AuditLogService::class)->record(
            event: 'user.updated',
            action: 'updated',
            description: 'Usuario atualizado.',
            subject: $actor,
            companyId: $company->id,
            actor: $actor,
            oldValues: ['email' => 'old@example.test', 'password' => 'secret'],
            newValues: ['email' => 'new@example.test', 'remember_token' => 'secret'],
            metadata: ['source' => 'feature'],
        );

        $this->assertNotNull($log);
        $this->assertSame(['email' => 'old@example.test'], $log->old_values);
        $this->assertSame(['email' => 'new@example.test'], $log->new_values);
        $this->assertSame($actor->email, $log->actor_email);
        $this->assertSame('feature', $log->metadata['source']);
        $this->assertSame('10.0.0.5', $log->ip_address);
        $this->assertSame('Audit Browser', $log->user_agent);
        $this->assertSame('PATCH', $log->http_method);
    }
}
