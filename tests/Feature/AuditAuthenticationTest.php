<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_logout_and_failed_login_are_audited_without_passwords(): void
    {
        $company = Company::create(['name' => 'Tenant Auth']);
        $user = User::factory()->create(['company_id' => $company->id]);
        AuditLog::query()->delete();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('tenant.agent.index', absolute: false));

        $this->post('/logout')->assertRedirect('/');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.login',
            'actor_user_id' => $user->id,
            'company_id' => $company->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.logout',
            'actor_user_id' => $user->id,
            'company_id' => $company->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.failed',
            'company_id' => $company->id,
        ]);
        $this->assertStringNotContainsString('wrong-password', AuditLog::query()->get()->toJson());
    }

    public function test_unknown_failed_login_is_global_and_contains_only_attempted_email(): void
    {
        AuditLog::query()->delete();

        $this->post('/login', [
            'email' => 'UNKNOWN@EXAMPLE.TEST',
            'password' => 'never-store-this',
        ]);

        $log = AuditLog::where('event', 'auth.failed')->sole();

        $this->assertNull($log->company_id);
        $this->assertNull($log->actor_user_id);
        $this->assertSame(['attempted_email' => 'unknown@example.test'], $log->metadata);
        $this->assertStringNotContainsString('never-store-this', $log->toJson());
    }
}
