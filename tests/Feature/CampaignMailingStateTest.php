<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Call;
use App\Models\Company;
use App\Models\ContactList;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Dialer\MailingQueueService;
use App\Services\Campaigns\CampaignOperationalGuard;
use App\Services\Dialer\PreviewLeadService;
use App\Jobs\RunPowerCampaign;
use App\Models\AgentStatus;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Support\Tenant\PermissionMatrix;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CampaignMailingStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_state_and_metrics_are_isolated_per_campaign(): void
    {
        $company = Company::create(['name' => 'Empresa mailing', 'status' => 'active']);
        $team = Team::create(['company_id' => $company->id, 'name' => 'Equipe', 'slug' => 'equipe', 'status' => 'active']);
        $list = ContactList::create(['company_id' => $company->id, 'name' => 'Lista', 'status' => 'active']);
        $contact = $list->contacts()->create(['company_id' => $company->id, 'name' => 'Lead', 'phone' => '31999990001', 'status' => 'pending']);
        $phone = $contact->phones()->create(['company_id' => $company->id, 'phone' => '31999990001', 'status' => 'pending', 'is_primary' => true]);

        $first = Campaign::create(['company_id' => $company->id, 'name' => 'Power A', 'dialing_mode' => 'power', 'status' => 'active']);
        $second = Campaign::create(['company_id' => $company->id, 'name' => 'Power B', 'dialing_mode' => 'power', 'status' => 'active']);
        foreach ([$first, $second] as $campaign) {
            $campaign->teams()->attach($team, ['company_id' => $company->id]);
            $campaign->contactLists()->attach($list, ['company_id' => $company->id, 'weight' => 1]);
        }

        $queue = app(MailingQueueService::class);
        $queue->markAttempt($contact, now()->addMinute(), 'dialing', $phone, $first);

        $this->assertSame('dialing', $queue->stateForCampaignPhone($first, $phone)->status);
        $this->assertSame('pending', $queue->stateForCampaignPhone($second, $phone)->status);
        $this->assertSame(1, $queue->summaryForCampaign($first)['dialing']);
        $this->assertSame(1, $queue->summaryForCampaign($second)['pending']);
    }

    public function test_recycling_selected_results_resets_only_the_operational_cycle_and_keeps_call_history_fields(): void
    {
        $company = Company::create(['name' => 'Empresa reciclagem', 'status' => 'active']);
        $role = Role::create(['company_id' => $company->id, 'name' => 'Admin', 'slug' => 'admin']);
        $user = User::factory()->create(['company_id' => $company->id, 'role_id' => $role->id, 'user_type' => 'company_admin']);
        $team = Team::create(['company_id' => $company->id, 'name' => 'Equipe', 'slug' => 'equipe', 'status' => 'active']);
        $list = ContactList::create(['company_id' => $company->id, 'name' => 'Lista', 'status' => 'active']);
        $contact = $list->contacts()->create(['company_id' => $company->id, 'name' => 'Lead', 'phone' => '31999990002', 'status' => 'exhausted', 'attempts_count' => 9]);
        $phone = $contact->phones()->create(['company_id' => $company->id, 'phone' => '31999990002', 'status' => 'exhausted', 'attempts_count' => 9, 'is_primary' => true]);
        $campaign = Campaign::create(['company_id' => $company->id, 'name' => 'Power', 'dialing_mode' => 'power', 'status' => 'paused']);
        $campaign->teams()->attach($team, ['company_id' => $company->id]);
        $campaign->contactLists()->attach($list, ['company_id' => $company->id, 'weight' => 1]);

        $queue = app(MailingQueueService::class);
        $state = $queue->stateForCampaignPhone($campaign, $phone);
        $state->update(['status' => 'failed', 'attempts_count' => 9, 'last_result' => 'carrier_failure']);

        $count = $queue->recycleCampaign($campaign, $list, ['failed'], $user, 'recycle-test-key');

        $this->assertSame(1, $count);
        $this->assertSame('pending', $state->fresh()->status);
        $this->assertSame(0, $state->fresh()->attempts_count);
        $this->assertSame(1, $state->fresh()->recycle_count);
        $this->assertSame(9, $contact->fresh()->attempts_count);
        $this->assertDatabaseHas('mailing_recycles', ['campaign_id' => $campaign->id, 'contact_list_id' => $list->id, 'affected_count' => 1]);
    }

    public function test_aggressiveness_is_capped_by_license(): void
    {
        $company = Company::create(['name' => 'Empresa proteção', 'status' => 'active']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => today()->subDay(),
            'ends_at' => today()->addDay(),
            'max_calls_per_available_agent' => 5,
        ]);
        $guard = app(CampaignOperationalGuard::class);

        $this->assertSame(5, $guard->effectiveAggressiveness($company->id, 10));
        $this->assertSame(1, $guard->effectiveAggressiveness($company->id, 0));
    }

    public function test_preview_reserves_a_single_lead_for_the_agent_without_creating_a_call(): void
    {
        $company = Company::create(['name' => 'Empresa preview', 'status' => 'active']);
        $role = Role::create(['company_id' => $company->id, 'name' => 'Agente', 'slug' => 'agente-preview']);
        $agent = User::factory()->create(['company_id' => $company->id, 'role_id' => $role->id, 'user_type' => 'agent', 'status' => 'active']);
        $team = Team::create(['company_id' => $company->id, 'name' => 'Preview', 'slug' => 'preview', 'status' => 'active', 'operational_type' => 'preview']);
        $team->users()->attach($agent, ['company_id' => $company->id, 'membership_type' => 'agent']);
        $list = ContactList::create(['company_id' => $company->id, 'name' => 'Lista Preview', 'status' => 'active']);
        $contact = $list->contacts()->create(['company_id' => $company->id, 'name' => 'Lead Preview', 'phone' => '31999990003', 'status' => 'pending']);
        $phone = $contact->phones()->create(['company_id' => $company->id, 'phone' => '31999990003', 'status' => 'pending', 'is_primary' => true]);
        $campaign = Campaign::create(['company_id' => $company->id, 'name' => 'Preview', 'dialing_mode' => 'preview', 'status' => 'active']);
        $campaign->teams()->attach($team, ['company_id' => $company->id]);
        $campaign->contactLists()->attach($list, ['company_id' => $company->id, 'weight' => 1]);

        $reservation = app(PreviewLeadService::class)->reserve($agent, $campaign);

        $this->assertSame($agent->id, $reservation->user_id);
        $this->assertSame($phone->id, $reservation->phoneState->contact_phone_id);
        $this->assertSame('delivered', $reservation->phoneState->status);
        $this->assertDatabaseCount('calls', 0);
    }

    public function test_power_job_ignores_preview_and_caps_attempts_by_campaign_aggressiveness(): void
    {
        $company = Company::create(['name' => 'Empresa power', 'status' => 'active']);
        $company->licenses()->create(['status' => 'active', 'starts_at' => today()->subDay(), 'ends_at' => today()->addDay(), 'max_calls_per_available_agent' => 5, 'max_concurrent_calls' => 5]);
        $role = Role::create(['company_id' => $company->id, 'name' => 'Agente', 'slug' => 'agente-power']);
        $agent = User::factory()->create(['company_id' => $company->id, 'role_id' => $role->id, 'user_type' => 'agent', 'status' => 'active']);
        $team = Team::create(['company_id' => $company->id, 'name' => 'Power', 'slug' => 'power', 'status' => 'active', 'operational_type' => 'power']);
        $team->users()->attach($agent, ['company_id' => $company->id, 'membership_type' => 'agent']);
        AgentStatus::create(['company_id' => $company->id, 'user_id' => $agent->id, 'status' => 'available', 'started_at' => now()]);
        $list = ContactList::create(['company_id' => $company->id, 'name' => 'Lista Power', 'status' => 'active']);
        $list->contacts()->create(['company_id' => $company->id, 'name' => 'Lead', 'phone' => '31999990004', 'status' => 'pending']);
        $power = Campaign::create(['company_id' => $company->id, 'name' => 'Power', 'dialing_mode' => 'power', 'status' => 'active', 'aggressiveness' => 1]);
        $preview = Campaign::create(['company_id' => $company->id, 'name' => 'Preview', 'dialing_mode' => 'preview', 'status' => 'active']);
        foreach ([$power, $preview] as $campaign) {
            $campaign->teams()->attach($team, ['company_id' => $company->id]);
            $campaign->contactLists()->attach($list, ['company_id' => $company->id, 'weight' => 1]);
        }

        (new RunPowerCampaign($preview->id))->handle(app(\App\Services\Dialer\SimulatedDialerService::class));
        $this->assertDatabaseCount('calls', 0);

        (new RunPowerCampaign($power->id))->handle(app(\App\Services\Dialer\SimulatedDialerService::class));
        $this->assertDatabaseHas('calls', ['campaign_id' => $power->id, 'user_id' => $agent->id]);
    }

    public function test_discador_does_not_expose_the_pbx_workspace_or_permissions(): void
    {
        $this->assertFalse(Route::has('tenant.pbx.index'));
        $this->assertArrayNotHasKey('pbx', PermissionMatrix::modules());
        $this->assertNotContains('pbx.read', PermissionMatrix::keys());
        $this->assertNotContains('pbx', Team::OPERATIONAL_TYPES);
    }

    public function test_team_type_rejects_agents_already_assigned_to_an_incompatible_campaign(): void
    {
        $company = Company::create(['name' => 'Empresa de tipos', 'status' => 'active']);
        $role = Role::create(['company_id' => $company->id, 'name' => 'Agente', 'slug' => 'agente-tipos']);
        $agent = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'user_type' => 'agent',
            'status' => 'active',
        ]);
        $powerCampaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Power existente',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        $agent->assignedCampaigns()->attach($powerCampaign, ['company_id' => $company->id]);

        $this->expectException(ValidationException::class);

        app(CampaignOperationalGuard::class)->assertAgentsMatchOperationalType(
            $company->id,
            'preview',
            [$agent->id],
        );
    }
}
