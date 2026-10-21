<?php

namespace App\Services\Dialer;

use App\Events\CampaignMailingMetricsUpdated;
use App\Models\Campaign;
use App\Models\Call;
use App\Models\CampaignPhoneState;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactPhone;
use App\Models\MailingRecycle;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MailingQueueService
{
    public const STATUSES = [
        'pending',
        'dialing',
        'delivered',
        'abandoned',
        'voicemail_pre',
        'voicemail_post',
        'no_answer',
        'failed',
    ];

    public function nextContactForCampaign(Campaign $campaign): ?Contact
    {
        $phone = $this->nextContactPhoneForCampaign($campaign);

        if (! $phone) {
            return null;
        }

        return $phone->contact->setRelation('selectedDialPhone', $phone);
    }

    public function nextContactPhoneForCampaign(Campaign $campaign): ?ContactPhone
    {
        if ($campaign->status !== 'active') {
            return null;
        }

        $listIds = $this->activeListIds($campaign);

        if ($listIds === []) {
            return null;
        }

        $this->ensureCampaignStates($campaign, $listIds);
        $candidate = $this->stateCandidatePhoneQuery($campaign, $listIds)->first();

        if (! $candidate) {
            return null;
        }

        return ContactPhone::query()
            ->where('company_id', $campaign->company_id)
            ->with('contact')
            ->find($candidate->contact_phone_id);
    }

    public function stateForCampaignPhone(Campaign $campaign, ContactPhone $phone): CampaignPhoneState
    {
        $contact = $phone->contact()->firstOrFail();
        $listId = (int) $contact->contact_list_id;

        abort_unless($contact->company_id === $campaign->company_id && $phone->company_id === $campaign->company_id, 404);
        abort_unless($campaign->contactLists()->whereKey($listId)->exists(), 404);

        return CampaignPhoneState::query()->firstOrCreate(
            ['campaign_id' => $campaign->id, 'contact_phone_id' => $phone->id],
            [
                'company_id' => $campaign->company_id,
                'contact_list_id' => $listId,
                'contact_id' => $contact->id,
                'status' => $this->stateStatusFromLegacy((string) ($phone->status ?: $contact->status)),
                // A contact can have several phones. Only legacy contacts without
                // a phone row inherit the contact-level counters.
                'attempts_count' => (int) $phone->attempts_count,
                'last_attempt_at' => $phone->last_attempt_at,
                'next_attempt_at' => $phone->next_attempt_at,
            ],
        );
    }

    private function stateCandidatePhoneQuery(Campaign $campaign, array $listIds): Builder
    {
        $now = now();

        $query = DB::table('campaign_phone_states')
            ->select([
                'campaign_phone_states.contact_phone_id',
            ])
            ->join('campaign_contact_list', function ($join) use ($campaign): void {
                $join->on('campaign_contact_list.contact_list_id', '=', 'campaign_phone_states.contact_list_id')
                    ->where('campaign_contact_list.campaign_id', $campaign->id)
                    ->where('campaign_contact_list.company_id', $campaign->company_id);
            })
            ->join('contact_phones', 'contact_phones.id', '=', 'campaign_phone_states.contact_phone_id')
            ->where('campaign_phone_states.company_id', $campaign->company_id)
            ->where('campaign_phone_states.campaign_id', $campaign->id)
            ->whereIn('campaign_phone_states.contact_list_id', $listIds)
            ->whereIn('campaign_phone_states.status', ['pending', 'no_answer', 'failed'])
            ->when($campaign->max_attempts_per_contact !== null, fn ($query) => $query->where('campaign_phone_states.attempts_count', '<', $campaign->max_attempts_per_contact))
            ->where(function ($query) use ($now): void {
                $query->whereNull('campaign_phone_states.next_attempt_at')
                    ->orWhere('campaign_phone_states.next_attempt_at', '<=', $now);
            })
            // A retry belongs to the lead, not just to the number dialed. While a
            // phone from the lead is cooling down, do not bypass it through a
            // sibling number. This also keeps cancelled Power batch entries from
            // immediately returning through an alternate phone.
            ->whereNotExists(function ($query) use ($campaign, $now): void {
                $query->selectRaw('1')
                    ->from('campaign_phone_states as pending_retry')
                    ->whereColumn('pending_retry.contact_id', 'campaign_phone_states.contact_id')
                    ->where('pending_retry.company_id', $campaign->company_id)
                    ->where('pending_retry.campaign_id', $campaign->id)
                    ->where('pending_retry.status', 'pending')
                    ->where('pending_retry.next_attempt_at', '>', $now);
            })
            ->whereNotExists(function ($query) use ($campaign): void {
                $query->selectRaw('1')
                    ->from('calls')
                    ->whereColumn('calls.contact_id', 'campaign_phone_states.contact_id')
                    ->where('calls.company_id', $campaign->company_id)
                    ->where('calls.campaign_id', $campaign->id)
                    ->whereNotNull('calls.user_id')
                    ->whereNotNull('calls.answered_at')
                    ->where(function ($query): void {
                        $query->whereNull('calls.disposition_id')
                            ->orWhereNotExists(function ($query): void {
                                $query->selectRaw('1')
                                    ->from('dispositions')
                                    ->whereColumn('dispositions.id', 'calls.disposition_id')
                                    ->where(function ($query): void {
                                        $query->where('dispositions.retry_enabled', true)
                                            ->orWhere('dispositions.category', 'callback');
                                    });
                            });
                    });
            });

        if (($campaign->lead_phone_strategy ?? 'vertical') === 'horizontal') {
            return $query
                ->orderByDesc('campaign_contact_list.weight')
                ->orderBy('campaign_phone_states.attempts_count')
                ->orderBy('campaign_phone_states.contact_id')
                ->orderBy('campaign_phone_states.contact_phone_id')
                ->limit(1);
        }

        return $query
            ->orderBy('campaign_phone_states.attempts_count')
            ->orderByRaw('CASE WHEN contact_phones.is_primary = 1 OR contact_phones.position = 0 THEN 0 ELSE 1 END')
            ->orderByDesc('campaign_contact_list.weight')
            ->orderBy('campaign_phone_states.contact_id')
            ->orderBy('contact_phones.position')
            ->orderBy('campaign_phone_states.contact_phone_id')
            ->limit(1);
    }

    private function contactPhoneFromCandidate(object $candidate): ?ContactPhone
    {
        $contact = Contact::query()->find($candidate->contact_id);

        if (! $contact) {
            return null;
        }

        if ($candidate->contact_phone_id === null) {
            $contactPhone = $this->ensurePrimaryPhoneForLegacyContact($contact);
        } else {
            $contactPhone = ContactPhone::query()
                ->whereKey($candidate->contact_phone_id)
                ->first();
        }

        return $contactPhone?->setRelation('contact', $contact);
    }

    public function markAttempt(
        Contact $contact,
        ?Carbon $nextAttemptAt = null,
        string $status = 'dialing',
        ?ContactPhone $contactPhone = null,
        ?Campaign $campaign = null,
    ): Contact {
        if ($campaign) {
            Campaign::query()
                ->where('company_id', $campaign->company_id)
                ->whereHas('contactLists', fn ($query) => $query->whereKey($contact->contact_list_id))
                ->get()
                ->each(fn (Campaign $linkedCampaign) => $this->ensureCampaignStates($linkedCampaign, [(int) $contact->contact_list_id]));
        }

        $contact->forceFill([
            'status' => $status,
            'attempts_count' => $contact->attempts_count + 1,
            'last_attempt_at' => now(),
            'next_attempt_at' => $nextAttemptAt,
        ])->save();

        $contactPhone ??= $contact->relationLoaded('selectedDialPhone')
            ? $contact->getRelation('selectedDialPhone')
            : $this->ensurePrimaryPhoneForLegacyContact($contact, mirrorContactAttempts: false);

        if ($contactPhone) {
            $contactPhone->forceFill([
                'status' => $status,
                'attempts_count' => $contactPhone->attempts_count + 1,
                'last_attempt_at' => now(),
                'next_attempt_at' => $nextAttemptAt,
            ])->save();

            if ($campaign) {
                $this->stateForCampaignPhone($campaign, $contactPhone)->update([
                    'status' => $status,
                    'last_result' => null,
                    'attempts_count' => DB::raw('attempts_count + 1'),
                    'last_attempt_at' => now(),
                    'next_attempt_at' => $nextAttemptAt,
                ]);
                event(new CampaignMailingMetricsUpdated($campaign->company_id, $campaign->id));
            }
        }

        return $contact;
    }

    public function recordUnansweredAttempt(Call $call, string $status): void
    {
        if (! $call->campaign_id || ! $call->contact_phone_id || ! in_array($status, ['no_answer', 'failed'], true)) {
            return;
        }

        $campaign = $call->campaign;
        if (! $campaign || $campaign->company_id !== $call->company_id) {
            return;
        }

        $state = CampaignPhoneState::query()
            ->where('company_id', $call->company_id)
            ->where('campaign_id', $campaign->id)
            ->where('contact_phone_id', $call->contact_phone_id)
            ->first();
        if (! $state || ! in_array($state->status, ['dialing', 'pending'], true)) {
            return;
        }

        $state->update([
            'status' => $status,
            'last_result' => $status,
            'next_attempt_at' => now()->addMinutes(max(0, (int) ($campaign->retry_delay_minutes ?? 30))),
            'completed_at' => now(),
        ]);
        event(new CampaignMailingMetricsUpdated($campaign->company_id, $campaign->id));
    }

    public function recordDelivered(Call $call): void
    {
        if (! $call->campaign_id || ! $call->contact_phone_id) {
            return;
        }

        $changed = CampaignPhoneState::query()
            ->where('company_id', $call->company_id)
            ->where('campaign_id', $call->campaign_id)
            ->where('contact_phone_id', $call->contact_phone_id)
            ->whereIn('status', ['dialing', 'pending'])
            ->update([
                'status' => 'delivered',
                'last_result' => 'delivered',
                'next_attempt_at' => null,
                'completed_at' => now(),
            ]);

        if ($changed) {
            event(new CampaignMailingMetricsUpdated($call->company_id, $call->campaign_id));
        }
    }

    public function recordBatchCancellation(Call $call): void
    {
        if (! $call->campaign_id || ! $call->contact_phone_id) {
            return;
        }

        CampaignPhoneState::query()
            ->where('company_id', $call->company_id)
            ->where('campaign_id', $call->campaign_id)
            ->where('contact_phone_id', $call->contact_phone_id)
            ->where('status', 'dialing')
            ->update([
                'status' => 'pending',
                'last_result' => null,
                'next_attempt_at' => now()->addMinutes(max(0, (int) ($call->campaign?->retry_delay_minutes ?? 30))),
            ]);

        event(new CampaignMailingMetricsUpdated($call->company_id, $call->campaign_id));
    }

    public function recycleCampaign(
        Campaign $campaign,
        ?ContactList $list = null,
        array $statuses = ['no_answer', 'failed', 'abandoned'],
        ?User $user = null,
        ?string $requestKey = null,
        array $dispositionIds = [],
    ): int
    {
        $list ??= $campaign->contactLists()->where('contact_lists.company_id', $campaign->company_id)->first();
        if (! $list) {
            return 0;
        }

        abort_unless($list->company_id === $campaign->company_id && $campaign->contactLists()->whereKey($list->id)->exists(), 404);
        $requestKey ??= hash('sha256', implode(':', [$campaign->id, $list->id, implode(',', $statuses), now()->format('YmdHi')]));

        return DB::transaction(function () use ($campaign, $list, $statuses, $dispositionIds, $user, $requestKey): int {
            $actorId = $user?->id ?? auth()->id();
            $recycle = null;
            if ($actorId) {
                $recycle = MailingRecycle::query()->firstOrCreate(
                    ['request_key' => $requestKey],
                    [
                        'company_id' => $campaign->company_id,
                        'campaign_id' => $campaign->id,
                        'contact_list_id' => $list->id,
                        'user_id' => $actorId,
                        'filters' => [
                            'statuses' => array_values($statuses),
                            'disposition_ids' => array_values(array_map('intval', $dispositionIds)),
                        ],
                    ],
                );

                if (! $recycle->wasRecentlyCreated) {
                    return $recycle->affected_count;
                }
            }

            $this->ensureCampaignStates($campaign, [$list->id]);
            $states = CampaignPhoneState::query()
                ->where('company_id', $campaign->company_id)
                ->where('campaign_id', $campaign->id)
                ->where('contact_list_id', $list->id)
                ->whereIn('status', array_values(array_intersect($statuses, self::STATUSES)));

            if ($dispositionIds !== []) {
                $states->whereIn('last_disposition_id', array_values(array_unique(array_map('intval', $dispositionIds))));
            }
            $phoneIds = $states->pluck('contact_phone_id');
            $count = $phoneIds->count();
            $states->update([
                'status' => 'pending',
                'last_result' => null,
                'attempts_count' => 0,
                'recycle_count' => DB::raw('recycle_count + 1'),
                'next_attempt_at' => null,
                'updated_at' => now(),
            ]);

            // Legacy callers (scheduler/maintenance and the historical test
            // surface) recycle the entire list. Keep their contact queue in
            // sync, while interactive recycling remains campaign-scoped.
            if (! $actorId && $dispositionIds === []) {
                $contacts = ContactPhone::query()
                    ->whereIn('id', $phoneIds)
                    ->get(['id', 'contact_id']);

                ContactPhone::query()
                    ->whereIn('id', $phoneIds)
                    ->update([
                        'status' => 'pending',
                        'next_attempt_at' => null,
                        'updated_at' => now(),
                    ]);

                Contact::query()
                    ->whereIn('id', $contacts->pluck('contact_id')->unique())
                    ->update([
                        'status' => 'pending',
                        'next_attempt_at' => null,
                        'updated_at' => now(),
                    ]);
            }

            $recycle?->update(['affected_count' => $count]);
            event(new CampaignMailingMetricsUpdated($campaign->company_id, $campaign->id));

            return $count;
        });
    }

    public function recordDisposition(Campaign $campaign, ContactPhone $phone, int $dispositionId, string $status, ?Carbon $nextAttemptAt = null): void
    {
        $state = $this->stateForCampaignPhone($campaign, $phone);

        $state->update([
            'status' => in_array($status, self::STATUSES, true) ? $status : 'delivered',
            'last_disposition_id' => $dispositionId,
            'last_result' => (string) $dispositionId,
            'next_attempt_at' => $nextAttemptAt,
            'completed_at' => now(),
        ]);
        event(new CampaignMailingMetricsUpdated($campaign->company_id, $campaign->id));
    }

    public function summaryForCampaign(Campaign $campaign): array
    {
        $summary = array_fill_keys(self::STATUSES, 0);
        $listIds = $this->activeListIds($campaign, includeInactiveLists: true);

        if ($listIds === []) {
            return $summary;
        }

        $stateCounts = CampaignPhoneState::query()
            ->where('company_id', $campaign->company_id)
            ->where('campaign_id', $campaign->id)
            ->whereIn('contact_list_id', $listIds)
            ->whereIn('status', self::STATUSES)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        if ($stateCounts->isNotEmpty()) {
            $stateCounts->each(function (int $total, string $status) use (&$summary): void {
                $summary[$status] = $total;
            });

            return $summary;
        }

        // A dashboard request must remain read-only. Initializing a state for
        // every phone in a large list here can turn opening Campanhas into a
        // long-running write transaction. The dialer and recycling flows
        // materialize isolated phone states when they actually need them.
        Contact::query()
            ->where('company_id', $campaign->company_id)
            ->whereIn('contact_list_id', $listIds)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->each(function (int $total, string $status) use (&$summary): void {
                $summary[$this->stateStatusFromLegacy($status)] += $total;
            });

        return $summary;
    }

    private function ensureCampaignStates(Campaign $campaign, array $listIds): void
    {
        if ($listIds === []) {
            return;
        }

        $now = now();

        // Imports already create one contact_phones row for every normalized
        // number. Create missing rows for legacy contacts in batches too, then
        // materialize the campaign queue with a handful of bulk inserts. The
        // previous Eloquent loop performed multiple queries per number and
        // could exceed the telephony worker timeout before its first call.
        Contact::query()
            ->where('company_id', $campaign->company_id)
            ->whereIn('contact_list_id', $listIds)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('contact_phones')
                    ->whereColumn('contact_phones.contact_id', 'contacts.id');
            })
            ->select(['id', 'company_id', 'phone', 'status', 'attempts_count', 'last_attempt_at', 'next_attempt_at'])
            ->orderBy('id')
            ->chunkById(500, function ($contacts) use ($now): void {
                DB::table('contact_phones')->insertOrIgnore($contacts->map(fn (Contact $contact): array => [
                    'company_id' => $contact->company_id,
                    'contact_id' => $contact->id,
                    'phone' => $contact->phone,
                    'column_name' => 'phone',
                    'position' => 0,
                    'is_primary' => true,
                    'status' => $contact->status,
                    'attempts_count' => $contact->attempts_count,
                    'last_attempt_at' => $contact->last_attempt_at,
                    'next_attempt_at' => $contact->next_attempt_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

        ContactPhone::query()
            ->join('contacts', 'contacts.id', '=', 'contact_phones.contact_id')
            ->where('contact_phones.company_id', $campaign->company_id)
            ->where('contacts.company_id', $campaign->company_id)
            ->whereIn('contacts.contact_list_id', $listIds)
            ->whereNotExists(function ($query) use ($campaign): void {
                $query->selectRaw('1')
                    ->from('campaign_phone_states as existing_state')
                    ->whereColumn('existing_state.contact_phone_id', 'contact_phones.id')
                    ->where('existing_state.company_id', $campaign->company_id)
                    ->where('existing_state.campaign_id', $campaign->id);
            })
            ->select([
                'contact_phones.id as contact_phone_id',
                'contact_phones.contact_id',
                'contact_phones.status as phone_status',
                'contact_phones.attempts_count',
                'contact_phones.last_attempt_at',
                'contact_phones.next_attempt_at',
                'contacts.contact_list_id',
                'contacts.status as contact_status',
            ])
            ->orderBy('contact_phones.id')
            ->chunkById(500, function ($phones) use ($campaign, $now): void {
                DB::table('campaign_phone_states')->insertOrIgnore($phones->map(fn (object $phone): array => [
                    'company_id' => $campaign->company_id,
                    'campaign_id' => $campaign->id,
                    'contact_list_id' => $phone->contact_list_id,
                    'contact_id' => $phone->contact_id,
                    'contact_phone_id' => $phone->contact_phone_id,
                    'status' => $this->stateStatusFromLegacy((string) ($phone->phone_status ?: $phone->contact_status)),
                    'attempts_count' => $phone->attempts_count,
                    'last_attempt_at' => $phone->last_attempt_at,
                    'next_attempt_at' => $phone->next_attempt_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }, 'contact_phones.id', 'contact_phone_id');
    }

    private function stateStatusFromLegacy(string $status): string
    {
        return match ($status) {
            'dialing' => 'dialing',
            'answered', 'callback' => 'delivered',
            'unproductive' => 'no_answer',
            'exhausted' => 'failed',
            default => 'pending',
        };
    }

    private function activeListIds(Campaign $campaign, bool $includeInactiveLists = false): array
    {
        $query = $campaign->contactLists()
            ->where('contact_lists.company_id', $campaign->company_id);

        if (! $includeInactiveLists) {
            $query->where('contact_lists.status', 'active');
        }

        return $query->pluck('contact_lists.id')->all();
    }

    private function ensurePrimaryPhoneForLegacyContact(Contact $contact, bool $mirrorContactAttempts = true): ?ContactPhone
    {
        if (blank($contact->phone)) {
            return null;
        }

        return $contact->phones()->firstOrCreate(
            ['phone' => $contact->phone],
            [
                'company_id' => $contact->company_id,
                'column_name' => 'phone',
                'position' => 0,
                'is_primary' => true,
                'status' => $contact->status,
                'attempts_count' => $mirrorContactAttempts ? $contact->attempts_count : 0,
                'last_attempt_at' => $mirrorContactAttempts ? $contact->last_attempt_at : null,
                'next_attempt_at' => $mirrorContactAttempts ? $contact->next_attempt_at : null,
            ],
        );
    }
}
