<?php

namespace App\Services\Dialer;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactPhone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

        $contacts = Contact::query()
            ->select('contacts.*')
            ->addSelect('campaign_contact_list.weight as campaign_list_weight')
            ->join('campaign_contact_list', function ($join) use ($campaign): void {
                $join->on('campaign_contact_list.contact_list_id', '=', 'contacts.contact_list_id')
                    ->where('campaign_contact_list.campaign_id', $campaign->id)
                    ->where('campaign_contact_list.company_id', $campaign->company_id);
            })
            ->where('contacts.company_id', $campaign->company_id)
            ->whereIn('contacts.contact_list_id', $listIds)
            ->where('contacts.status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('contacts.next_attempt_at')
                    ->orWhere('contacts.next_attempt_at', '<=', now());
            })
            ->whereNotExists(function ($query) use ($campaign): void {
                $query->selectRaw('1')
                    ->from('calls')
                    ->whereColumn('calls.contact_id', 'contacts.id')
                    ->where('calls.company_id', $campaign->company_id)
                    ->where('calls.campaign_id', $campaign->id)
                    ->whereNotNull('calls.user_id')
                    ->whereNotNull('calls.answered_at');
            })
            ->with('phones')
            ->orderByDesc('campaign_contact_list.weight')
            ->orderBy('contacts.id')
            ->get();

        return $this->selectPhoneForStrategy($campaign, $contacts);
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

        return Contact::query()
            ->where('company_id', $campaign->company_id)
            ->whereIn('contact_list_id', $listIds)
            ->whereIn('status', ['unproductive', 'callback', 'exhausted'])
            ->update([
                'status' => 'pending',
                'next_attempt_at' => null,
                'updated_at' => now(),
            ]);
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

    private function selectPhoneForStrategy(Campaign $campaign, Collection $contacts): ?ContactPhone
    {
        if (($campaign->lead_phone_strategy ?? 'vertical') === 'horizontal') {
            return $contacts
                ->map(fn (Contact $contact): ?ContactPhone => $this->nextPhoneForContact($contact))
                ->filter()
                ->first();
        }

        return $contacts
            ->flatMap(function (Contact $contact): Collection {
                return $this->validPhonesForContact($contact)
                    ->map(function (ContactPhone $phone) use ($contact): array {
                        return [
                            'contact' => $contact,
                            'phone' => $phone,
                            'weight' => (int) $contact->getAttribute('campaign_list_weight'),
                        ];
                    });
            })
            ->sortBy([
                ['phone.attempts_count', 'asc'],
                fn (array $left, array $right): int => $this->phonePrimaryRank($left['phone']) <=> $this->phonePrimaryRank($right['phone']),
                ['weight', 'desc'],
                ['contact.id', 'asc'],
                ['phone.position', 'asc'],
                ['phone.id', 'asc'],
            ])
            ->map(fn (array $target): ContactPhone => $target['phone']->setRelation('contact', $target['contact']))
            ->first();
    }

    private function nextPhoneForContact(Contact $contact): ?ContactPhone
    {
        return $this->validPhonesForContact($contact)
            ->sortBy([
                ['attempts_count', 'asc'],
                ['position', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn (ContactPhone $phone): ContactPhone => $phone->setRelation('contact', $contact))
            ->first();
    }

    private function validPhonesForContact(Contact $contact): Collection
    {
        $phones = $contact->phones;

        if ($phones->isEmpty()) {
            $legacyPhone = $this->ensurePrimaryPhoneForLegacyContact($contact);
            $phones = $legacyPhone ? collect([$legacyPhone]) : collect();
        }

        return $phones->filter(function (ContactPhone $phone): bool {
            if (blank($phone->phone) || in_array($phone->status, ['answered', 'exhausted'], true)) {
                return false;
            }

            return $phone->next_attempt_at === null || $phone->next_attempt_at->lte(now());
        });
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

    private function phonePrimaryRank(ContactPhone $phone): int
    {
        return $phone->is_primary || $phone->position === 0 ? 0 : 1;
    }
}
