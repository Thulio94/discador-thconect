<?php

namespace App\Services\Dialer;

use App\Models\AgentPause;
use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\User;
use Carbon\CarbonInterface;

class SimulatedDialerService
{
    private const WEEK_DAYS = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];

    public function __construct(
        private readonly AgentCapacityService $capacity,
        private readonly MailingQueueService $queue,
    ) {}

    public function runCampaign(Campaign $campaign): int
    {
        if ($campaign->status !== 'active') {
            return 0;
        }

        if (! $this->isCampaignInOperationalSchedule($campaign)) {
            return 0;
        }

        $limit = min($this->capacity->allowedCallsForCampaign($campaign), $this->availableAgents($campaign)->count());

        if ($limit < 1) {
            return 0;
        }

        $created = 0;

        foreach ($this->availableAgents($campaign)->limit($limit)->get() as $agent) {
            $contact = $this->queue->nextContactForCampaign($campaign);

            if (! $contact) {
                break;
            }

            $this->createCall($campaign, $agent, $contact);
            $created++;
        }

        return $created;
    }

    public function isCampaignInOperationalSchedule(Campaign $campaign, ?CarbonInterface $moment = null): bool
    {
        $schedule = $campaign->operational_schedule ?? [];

        if ($schedule === []) {
            return true;
        }

        $moment ??= now();
        $day = $schedule[self::WEEK_DAYS[$moment->dayOfWeek] ?? ''] ?? null;

        if (! is_array($day) || empty($day['enabled'])) {
            return false;
        }

        $start = $day['start'] ?? '00:00';
        $end = $day['end'] ?? '23:59';
        $current = $moment->format('H:i');

        return $current >= $start && $current <= $end;
    }

    private function availableAgents(Campaign $campaign)
    {
        $teamIds = $campaign->teams()->pluck('teams.id');

        return User::query()
            ->where('users.company_id', $campaign->company_id)
            ->where('users.status', 'active')
            ->where('users.user_type', 'agent')
            ->whereIn('users.id', function ($query) use ($campaign, $teamIds): void {
                $query->select('team_user.user_id')
                    ->from('team_user')
                    ->where('team_user.company_id', $campaign->company_id)
                    ->where('team_user.membership_type', 'agent')
                    ->whereIn('team_user.team_id', $teamIds);
            })
            ->whereHas('currentAgentStatus', fn ($query) => $query->where('status', 'available'))
            ->whereDoesntHave('calls', fn ($query) => $query->where('status', 'in_progress'))
            ->distinct('users.id')
            ->orderBy('users.id');
    }

    private function createCall(Campaign $campaign, User $agent, Contact $contact): void
    {
        $this->closeOpenAgentRecords($agent);

        Call::create([
            'company_id' => $campaign->company_id,
            'user_id' => $agent->id,
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => 'simulated',
            'phone' => $contact->phone,
            'started_at' => now(),
            'answered_at' => now(),
        ]);

        $this->queue->markAttempt($contact, now()->addMinutes(30));

        $agent->agentStatuses()->create([
            'company_id' => $campaign->company_id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);
    }

    private function closeOpenAgentRecords(User $agent): void
    {
        AgentStatus::query()
            ->where('company_id', $agent->company_id)
            ->where('user_id', $agent->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        AgentPause::query()
            ->where('company_id', $agent->company_id)
            ->where('user_id', $agent->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
    }
}
