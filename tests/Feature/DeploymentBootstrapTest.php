<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DeploymentBootstrapTest extends TestCase
{
    public function test_bootstrap_endpoint_is_hidden_without_an_installation_token(): void
    {
        config()->set('deployment.bootstrap_token', null);

        $this->postJson('/api/internal/deployment-bootstrap')
            ->assertNotFound();
    }

    public function test_bootstrap_endpoint_rejects_an_invalid_installation_token(): void
    {
        config()->set('deployment.bootstrap_token', 'expected-token');

        $this->postJson('/api/internal/deployment-bootstrap', [], [
            'X-Deployment-Bootstrap-Token' => 'wrong-token',
        ])->assertNotFound();
    }

    public function test_bootstrap_endpoint_runs_the_schema_and_seed_steps_with_the_installation_token(): void
    {
        config()->set('deployment.bootstrap_token', 'expected-token');

        Artisan::shouldReceive('call')
            ->times(3)
            ->andReturn(0);

        $this->postJson('/api/internal/deployment-bootstrap', [], [
            'X-Deployment-Bootstrap-Token' => 'expected-token',
        ])->assertOk()
            ->assertJson(['message' => 'Bootstrap do banco concluido.']);
    }
}
