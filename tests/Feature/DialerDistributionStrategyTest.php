<?php

namespace Tests\Feature;

use App\Models\AgentCampaignSession;
use App\Models\AgentStatus;
use App\Models\AgentWebRtcPresence;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\ContactList;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Dialer\AgentCapacityService;
use App\Services\Dialer\SimulatedDialerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DialerDistributionStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_strategy_assigns_the_oldest_available_agent(): void
    {
        [$campaign, $agents] = $this->campaignWithAgents();

        $this->assertSame('least_idle', $campaign->agent_distribution_strategy);
        $this->assertSame(1, app(SimulatedDialerService::class)->runCampaign($campaign));

        $this->assertSame($agents[0]->id, $this->winnerFor($campaign)->user_id);
    }

    public function test_balanced_strategy_assigns_the_agent_with_fewer_campaign_calls_today(): void
    {
        [$campaign, $agents] = $this->campaignWithAgents(['agent_distribution_strategy' => 'balanced']);

        Call::create([
            'company_id' => $campaign->company_id,
            'user_id' => $agents[0]->id,
            'campaign_id' => $campaign->id,
            'direction' => 'outbound',
            'status' => 'ended',
            'channel' => 'simulated',
            'phone' => '31999990111',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $this->assertSame(1, app(SimulatedDialerService::class)->runCampaign($campaign));

        $this->assertSame($agents[1]->id, $this->winnerFor($campaign)->user_id);
    }

    public function test_explicit_campaign_assignment_limits_eligible_agents_without_changing_team_membership(): void
    {
        [$campaign, $agents] = $this->campaignWithAgents();
        $campaign->users()->attach($agents[1], ['company_id' => $campaign->company_id]);

        $this->assertSame(1, app(SimulatedDialerService::class)->runCampaign($campaign));

        $this->assertSame($agents[1]->id, $this->winnerFor($campaign)->user_id);
    }

    public function test_power_dialer_respects_the_campaign_selected_across_multiple_teams(): void
    {
        [$campaignA, $agents] = $this->campaignWithAgents();
        $agent = $agents[0];
        $otherAgent = $agents[1];
        $otherAgent->agentStatuses()->whereNull('ended_at')->update(['ended_at' => now()]);
        AgentStatus::create([
            'company_id' => $otherAgent->company_id,
            'user_id' => $otherAgent->id,
            'status' => 'paused',
            'started_at' => now(),
        ]);
        $company = $campaignA->company;
        $teamB = Team::create([
            'company_id' => $company->id,
            'name' => 'Equipe B',
            'slug' => 'equipe-b',
            'status' => 'active',
            'operational_type' => 'power',
            'allow_agent_campaign_selection' => true,
        ]);
        $teamB->users()->attach($agent, ['company_id' => $company->id, 'membership_type' => 'agent']);

        $listB = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Lista B',
            'status' => 'active',
        ]);
        $campaignB = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha B',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        $campaignB->teams()->attach($teamB, ['company_id' => $company->id]);
        $campaignB->contactLists()->attach($listB, ['company_id' => $company->id, 'weight' => 1]);
        $listB->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead B',
            'phone' => '31999990002',
            'status' => 'pending',
        ]);

        $campaignC = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Campanha C',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        $campaignC->teams()->attach($teamB, ['company_id' => $company->id]);

        AgentCampaignSession::create([
            'company_id' => $company->id,
            'user_id' => $agent->id,
            'campaign_id' => $campaignB->id,
            'started_at' => now(),
        ]);

        $this->assertSame(0, app(AgentCapacityService::class)->availableAgentsForCampaign($campaignA));
        $this->assertSame(0, app(SimulatedDialerService::class)->runCampaign($campaignA));
        $this->assertSame(1, app(AgentCapacityService::class)->availableAgentsForCampaign($campaignB));
        $this->assertSame(1, app(SimulatedDialerService::class)->runCampaign($campaignB));
        $this->assertSame($agent->id, $this->winnerFor($campaignB)->user_id);
    }

    /** @return array{Campaign, array<int, User>} */
    private function campaignWithAgents(array $attributes = []): array
    {
        $company = Company::create(['name' => 'Empresa distribuicao', 'status' => 'active']);
        $company->licenses()->create([
            'status' => 'active',
            'starts_at' => today()->subDay(),
            'ends_at' => today()->addDay(),
            'max_calls_per_available_agent' => 1,
            'max_concurrent_agents' => 2,
            'max_concurrent_calls' => 1,
        ]);
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Agente',
            'slug' => 'agente-distribuicao',
        ]);
        $team = Team::create([
            'company_id' => $company->id,
            'name' => 'Equipe distribuicao',
            'slug' => 'equipe-distribuicao',
            'status' => 'active',
        ]);
        $campaign = Campaign::create(array_merge([
            'company_id' => $company->id,
            'name' => 'Campanha distribuicao',
            'dialing_mode' => 'power',
            'status' => 'active',
        ], $attributes));
        $list = ContactList::create([
            'company_id' => $company->id,
            'name' => 'Lista distribuicao',
            'status' => 'active',
        ]);
        $campaign->teams()->attach($team, ['company_id' => $company->id]);
        $campaign->contactLists()->attach($list, ['company_id' => $company->id, 'weight' => 1]);

        $agents = collect([30, 10])->map(function (int $minutes, int $index) use ($company, $role, $team): User {
            $agent = User::factory()->create([
                'company_id' => $company->id,
                'role_id' => $role->id,
                'user_type' => 'agent',
                'status' => 'active',
                'extension' => (string) (7100 + $index),
            ]);
            $team->users()->attach($agent, ['company_id' => $company->id, 'membership_type' => 'agent']);
            AgentWebRtcPresence::create([
                'company_id' => $company->id,
                'user_id' => $agent->id,
                'connection_id' => (string) Str::uuid(),
                'registered' => true,
                'last_seen_at' => now(),
            ]);
            AgentStatus::create([
                'company_id' => $company->id,
                'user_id' => $agent->id,
                'status' => 'available',
                'started_at' => now()->subMinutes($minutes),
            ]);

            return $agent;
        })->all();

        $list->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Lead distribuicao',
            'phone' => '31999990001',
            'status' => 'pending',
        ]);

        return [$campaign, $agents];
    }

    private function winnerFor(Campaign $campaign): Call
    {
        return Call::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'in_progress')
            ->sole();
    }
}
