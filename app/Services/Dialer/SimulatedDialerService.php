<?php

namespace App\Services\Dialer;

use App\Contracts\Dialer\DialerGateway;
use App\Models\AgentPause;
use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\CompanyLicense;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        private readonly DialerGateway $gateway,
    ) {}

    public function runCampaign(Campaign $campaign): int
    {
        if ($campaign->status !== 'active' || $campaign->dialing_mode !== 'power') {
            return 0;
        }

        if (! $this->isCampaignInOperationalSchedule($campaign)) {
            return 0;
        }

        return DB::transaction(function () use ($campaign): int {
            $license = CompanyLicense::query()
                ->where('company_id', $campaign->company_id)
                ->where('status', 'active')
                ->where('starts_at', '<=', today())
                ->where('ends_at', '>=', today())
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $company = $campaign->company()->firstOrFail();
            if ($company->status !== 'active' || ! $license) {
                return 0;
            }
            $company->setRelation('license', $license);
            $campaign->setRelation('company', $company);
            $campaign->load('teams');

            $remaining = $this->capacity->remainingConcurrentCallsForCampaign($campaign);
            $callsPerAgent = min(
                max(1, (int) ($license?->max_calls_per_available_agent ?? 1)),
                max(1, (int) ($campaign->aggressiveness ?? $license?->max_calls_per_available_agent ?? 1)),
            );

            if ($remaining < 1) {
                return 0;
            }

            $created = 0;

            foreach ($this->availableAgents($campaign)->get() as $agent) {
                $agent = $this->lockEligibleAgent($campaign, $agent->id);

                if (! $agent) {
                    continue;
                }

                $batchSize = min($callsPerAgent, $remaining);
                $batchCreated = $this->createBatch($campaign, $agent, $batchSize);

                if ($batchCreated === 0) {
                    break;
                }

                $created += $batchCreated;
                $remaining -= $batchCreated;

                if ($remaining < 1) {
                    break;
                }
            }

            return $created;
        }, 3);
    }

    public function isCampaignInOperationalSchedule(Campaign $campaign, ?CarbonInterface $moment = null): bool
    {
        $moment ??= now();
        if (($campaign->starts_at && $campaign->starts_at->greaterThan($moment))
            || ($campaign->ends_at && $campaign->ends_at->lessThan($moment))) {
            return false;
        }

        $schedule = $campaign->operational_schedule ?? [];

        if ($schedule === []) {
            return true;
        }

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
        $assignedAgentIds = $this->assignedAgentIds($campaign);

        $agents = User::query()
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
            ->whereHas('webRtcPresences', fn ($query) => $query
                ->where('registered', true)
                ->where('last_seen_at', '>=', now()->subSeconds(50)))
            ->whereDoesntHave('calls', fn ($query) => $query
                ->whereIn('status', Call::ACTIVE_STATUSES)
                ->orWhere('status', 'wrap_up'))
            ->distinct('users.id');

        if ($assignedAgentIds->isNotEmpty()) {
            $agents->whereIn('users.id', $assignedAgentIds);
        }

        return $this->applyDistributionStrategy($agents, $campaign);
    }

    private function lockEligibleAgent(Campaign $campaign, int $agentId): ?User
    {
        $assignedAgentIds = $this->assignedAgentIds($campaign);

        $agent = User::query()
            ->whereKey($agentId)
            ->where('company_id', $campaign->company_id)
            ->where('status', 'active')
            ->where('user_type', 'agent')
            ->whereHas('currentAgentStatus', fn ($query) => $query->where('status', 'available'))
            ->whereHas('webRtcPresences', fn ($query) => $query
                ->where('registered', true)
                ->where('last_seen_at', '>=', now()->subSeconds(50)))
            ->whereDoesntHave('calls', function ($query): void {
                $query->whereIn('status', Call::ACTIVE_STATUSES)
                    ->orWhere('status', 'wrap_up');
            })
            ->when($assignedAgentIds->isNotEmpty(), fn ($query) => $query->whereIn('users.id', $assignedAgentIds))
            ->lockForUpdate()
            ->first();

        if (! $agent || ! $this->agentCanReceiveCampaign($agent, $campaign)) {
            return null;
        }

        return $agent;
    }

    private function agentCanReceiveCampaign(User $agent, Campaign $campaign): bool
    {
        $team = $agent->teams()
            ->wherePivot('membership_type', 'agent')
            ->where('teams.status', 'active')
            ->first();
        if (! $team || $team->operational_type !== 'power') {
            return false;
        }

        $campaignCount = $team->campaigns()
            ->where('campaigns.status', 'active')
            ->where('campaigns.dialing_mode', 'power')
            ->count();
        if (! $team->allow_agent_campaign_selection || $campaignCount < 2) {
            return true;
        }

        return $agent->currentAgentCampaignSession()
            ->where('campaign_id', $campaign->id)
            ->exists();
    }

    private function createBatch(Campaign $campaign, User $agent, int $batchSize): int
    {
        $created = 0;
        $batchId = (string) Str::uuid();
        $hasActiveCall = false;
        $activeCall = null;

        for ($attempt = 0; $attempt < $batchSize; $attempt++) {
            $contactPhone = $this->queue->nextContactPhoneForCampaign($campaign);

            if (! $contactPhone) {
                break;
            }

            $contact = $contactPhone->contact;
            $request = new DialerCallRequest(
                companyId: $campaign->company_id,
                userId: $agent->id,
                campaignId: $campaign->id,
                contactId: $contact->id,
                contactPhoneId: $contactPhone->id,
                phone: $contactPhone->phone,
                batchId: $batchId,
            );

            $call = $this->gateway->originate($request);
            if ($call->end_reason === 'route_unavailable' || str_starts_with((string) $call->end_reason, 'capacity_')) {
                break;
            }
            $isActive = in_array($call->status, Call::ACTIVE_STATUSES, true);
            if (! $isActive && $call->end_reason !== 'answered_elsewhere') {
                break;
            }
            $hasActiveCall = $hasActiveCall || $isActive;
            $activeCall ??= $isActive ? $call : null;

            $this->queue->markAttempt(
                $contact,
                now()->addMinutes(max(0, (int) ($campaign->retry_delay_minutes ?? 30))),
                status: $isActive ? 'dialing' : 'pending',
                contactPhone: $contactPhone,
                campaign: $campaign,
            );
            $created++;
        }

        if ($created === 0 || ! $hasActiveCall || ! $activeCall) {
            return 0;
        }

        $this->closeOpenAgentRecords($agent);

        $agent->agentStatuses()->create([
            'company_id' => $campaign->company_id,
            'status' => $activeCall->channel === 'asterisk_ami' ? 'dialing' : 'in_call',
            'started_at' => now(),
        ]);

        return $created;
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

    private function assignedAgentIds(Campaign $campaign)
    {
        return $campaign->users()
            ->where('users.company_id', $campaign->company_id)
            ->where('users.user_type', 'agent')
            ->pluck('users.id');
    }

    private function applyDistributionStrategy($agents, Campaign $campaign)
    {
        $availableSince = AgentStatus::query()
            ->select('started_at')
            ->whereColumn('agent_statuses.user_id', 'users.id')
            ->where('agent_statuses.company_id', $campaign->company_id)
            ->where('agent_statuses.status', 'available')
            ->whereNull('agent_statuses.ended_at')
            ->latest('id')
            ->limit(1);

        if (($campaign->agent_distribution_strategy ?? 'least_idle') === 'random') {
            return $agents->orderByRaw('RAND()');
        }

        if ($campaign->agent_distribution_strategy === 'balanced') {
            $callsToday = Call::query()
                ->selectRaw('count(*)')
                ->whereColumn('calls.user_id', 'users.id')
                ->where('calls.company_id', $campaign->company_id)
                ->where('calls.campaign_id', $campaign->id)
                ->whereDate('calls.started_at', today())
                ->where('calls.status', '!=', 'cancelled');

            return $agents
                ->orderBy($callsToday)
                ->orderBy($availableSince)
                ->orderBy('users.id');
        }

        return $agents
            ->orderBy($availableSince)
            ->orderBy('users.id');
    }
}
