<?php

namespace App\Services\Dialer;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactPhone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MailingQueueService
{
    public const STATUSES = [
        'pending',
        'dialing',
        'answered',
        'unproductive',
        'callback',
        'exhausted',
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

        $candidate = $this->candidatePhoneQuery($campaign, $listIds)->first();

        if (! $candidate) {
            return null;
        }

        return $this->contactPhoneFromCandidate($candidate);
    }

    private function candidatePhoneQuery(Campaign $campaign, array $listIds): Builder
    {
        $now = now();

        $query = DB::table('contacts')
            ->select([
                'contacts.id as contact_id',
                'contact_phones.id as contact_phone_id',
            ])
            ->join('campaign_contact_list', function ($join) use ($campaign): void {
                $join->on('campaign_contact_list.contact_list_id', '=', 'contacts.contact_list_id')
                    ->where('campaign_contact_list.campaign_id', $campaign->id)
                    ->where('campaign_contact_list.company_id', $campaign->company_id);
            })
            ->leftJoin('contact_phones', function ($join): void {
                $join->on('contact_phones.contact_id', '=', 'contacts.id')
                    ->whereColumn('contact_phones.company_id', 'contacts.company_id');
            })
            ->where('contacts.company_id', $campaign->company_id)
            ->whereIn('contacts.contact_list_id', $listIds)
            ->where('contacts.status', 'pending')
            ->where(function ($query) use ($now): void {
                $query->whereNull('contacts.next_attempt_at')
                    ->orWhere('contacts.next_attempt_at', '<=', $now);
            })
            ->whereNotExists(function ($query) use ($campaign): void {
                $query->selectRaw('1')
                    ->from('calls')
                    ->whereColumn('calls.contact_id', 'contacts.id')
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
            })
            ->where(function ($query) use ($now): void {
                $query->where(function ($query): void {
                    $query->whereNull('contact_phones.id')
                        ->whereNotNull('contacts.phone')
                        ->whereRaw("TRIM(contacts.phone) <> ''");
                })->orWhere(function ($query) use ($now): void {
                    $query->whereNotNull('contact_phones.id')
                        ->whereNotNull('contact_phones.phone')
                        ->whereRaw("TRIM(contact_phones.phone) <> ''")
                        ->whereNotIn('contact_phones.status', ['answered', 'exhausted'])
                        ->where(function ($query) use ($now): void {
                            $query->whereNull('contact_phones.next_attempt_at')
                                ->orWhere('contact_phones.next_attempt_at', '<=', $now);
                        });
                });
            });

        $phoneAttempts = 'COALESCE(contact_phones.attempts_count, contacts.attempts_count)';
        $phonePosition = 'COALESCE(contact_phones.position, 0)';
        $phoneId = 'COALESCE(contact_phones.id, 0)';

        if (($campaign->lead_phone_strategy ?? 'vertical') === 'horizontal') {
            return $query
                ->orderByDesc('campaign_contact_list.weight')
                ->orderByRaw($phoneAttempts.' asc')
                ->orderBy('contacts.id')
                ->orderByRaw($phonePosition.' asc')
                ->orderByRaw($phoneId.' asc')
                ->limit(1);
        }

        $primaryRank = 'CASE WHEN contact_phones.id IS NULL OR contact_phones.is_primary = 1 OR contact_phones.position = 0 THEN 0 ELSE 1 END';

        return $query
            ->orderByRaw($phoneAttempts.' asc')
            ->orderByRaw($primaryRank.' asc')
            ->orderByDesc('campaign_contact_list.weight')
            ->orderBy('contacts.id')
            ->orderByRaw($phonePosition.' asc')
            ->orderByRaw($phoneId.' asc')
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
    ): Contact {
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
        }

        return $contact;
    }

    public function recycleCampaign(Campaign $campaign): int
    {
        $listIds = $this->activeListIds($campaign, includeInactiveLists: true);

        if ($listIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($campaign, $listIds): int {
            $contacts = Contact::query()
                ->where('company_id', $campaign->company_id)
                ->whereIn('contact_list_id', $listIds)
                ->whereIn('status', ['unproductive', 'callback', 'exhausted']);
            $contactIds = (clone $contacts)->pluck('id');

            if ($contactIds->isEmpty()) {
                return 0;
            }

            ContactPhone::query()
                ->where('company_id', $campaign->company_id)
                ->whereIn('contact_id', $contactIds)
                ->update([
                    'status' => 'pending',
                    'next_attempt_at' => null,
                    'updated_at' => now(),
                ]);

            return $contacts->update([
                'status' => 'pending',
                'next_attempt_at' => null,
                'updated_at' => now(),
            ]);
        });
    }

    public function summaryForCampaign(Campaign $campaign): array
    {
        $summary = array_fill_keys(self::STATUSES, 0);
        $listIds = $this->activeListIds($campaign, includeInactiveLists: true);

        if ($listIds === []) {
            return $summary;
        }

        Contact::query()
            ->where('company_id', $campaign->company_id)
            ->whereIn('contact_list_id', $listIds)
            ->whereIn('status', self::STATUSES)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->each(function (int $total, string $status) use (&$summary): void {
                $summary[$status] = $total;
            });

        return $summary;
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
