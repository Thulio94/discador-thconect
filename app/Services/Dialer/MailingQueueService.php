<?php

namespace App\Services\Dialer;

use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Support\Carbon;

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
        if ($campaign->status !== 'active') {
            return null;
        }

        $listIds = $this->activeListIds($campaign);

        if ($listIds === []) {
            return null;
        }

        return Contact::query()
            ->select('contacts.*')
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
            ->orderByDesc('campaign_contact_list.weight')
            ->orderBy('contacts.attempts_count')
            ->orderBy('contacts.id')
            ->first();
    }

    public function markAttempt(Contact $contact, ?Carbon $nextAttemptAt = null, string $status = 'dialing'): Contact
    {
        $contact->forceFill([
            'status' => $status,
            'attempts_count' => $contact->attempts_count + 1,
            'last_attempt_at' => now(),
            'next_attempt_at' => $nextAttemptAt,
        ])->save();

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
}
