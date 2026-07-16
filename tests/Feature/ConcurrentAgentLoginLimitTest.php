<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrentAgentLoginLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_agent_login_is_not_limited_by_available_agent_capacity(): void
    {
        $company = $this->companyWithAvailableAgentLimit(1);
        $loggedAgent = $this->agent($company, 'logged@empresa.test');
        $newAgent = $this->agent($company, 'new@empresa.test');
        $this->activeSession($loggedAgent);

        $this->withSession(['_token' => 'login-test-token'])->post(route('login'), [
            'email' => $newAgent->email,
            'password' => 'password',
            '_token' => 'login-test-token',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($newAgent);
    }

    public function test_existing_agent_session_can_continue_normally(): void
    {
        $company = $this->companyWithAvailableAgentLimit(1);
        $agent = $this->agent($company, 'agent@empresa.test');
        $this->activeSession($agent);

        $this->withSession(['_token' => 'login-test-token'])->post(route('login'), [
            'email' => $agent->email,
            'password' => 'password',
            '_token' => 'login-test-token',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($agent);
    }

    public function test_administrative_users_are_not_affected_by_available_agent_capacity(): void
    {
        $company = $this->companyWithAvailableAgentLimit(1);
        $this->activeSession($this->agent($company, 'agent@empresa.test'));
        $administrator = User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'company_admin',
            'status' => 'active',
            'extension' => '3000',
            'email' => 'admin@empresa.test',
        ]);

        $this->withSession(['_token' => 'login-test-token'])->post(route('login'), [
            'email' => $administrator->email,
            'password' => 'password',
            '_token' => 'login-test-token',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($administrator);
    }

    private function companyWithAvailableAgentLimit(int $limit): Company
    {
        $company = Company::create(['name' => 'Empresa de Sessoes', 'status' => 'active']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'max_concurrent_agents' => $limit,
        ]);

        return $company;
    }

    private function agent(Company $company, string $email): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'user_type' => 'agent',
            'status' => 'active',
            'extension' => (string) random_int(1000, 9999),
            'email' => $email,
        ]);
    }

    private function activeSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => 'session-'.$user->id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ]);
    }
}
