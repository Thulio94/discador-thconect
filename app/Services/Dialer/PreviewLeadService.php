<?php

namespace App\Services\Dialer;

use App\Models\Campaign;
use App\Models\CampaignPhoneState;
use App\Models\PreviewLeadReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreviewLeadService
{
    public function __construct(private readonly MailingQueueService $queue) {}

    public function reserve(User $user, Campaign $campaign): PreviewLeadReservation
    {
        if ($campaign->company_id !== $user->company_id || $campaign->dialing_mode !== 'preview') {
            abort(404);
        }

        return DB::transaction(function () use ($user, $campaign): PreviewLeadReservation {
            $existing = PreviewLeadReservation::query()
                ->where('company_id', $user->company_id)
                ->where('user_id', $user->id)
                ->whereNull('released_at')
                ->with('phoneState.contact')
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $phone = $this->queue->nextContactPhoneForCampaign($campaign);
            if (! $phone) {
                throw ValidationException::withMessages(['campaign' => 'Não há telefones disponíveis para esta campanha Preview.']);
            }

            $state = $this->queue->stateForCampaignPhone($campaign, $phone);
            $state->update(['status' => 'delivered', 'delivered_at' => now()]);

            $reservation = PreviewLeadReservation::create([
                'company_id' => $user->company_id,
                'campaign_id' => $campaign->id,
                'user_id' => $user->id,
                'campaign_phone_state_id' => $state->id,
                'reserved_at' => now(),
            ]);

            return $reservation->load('phoneState');
        }, 3);
    }

    public function releaseForCompletion(int $callId): void
    {
        PreviewLeadReservation::query()
            ->whereNull('released_at')
            ->whereHas('phoneState', fn ($query) => $query->whereHas('contactPhone.calls', fn ($calls) => $calls->whereKey($callId)))
            ->update(['released_at' => now()]);
    }

    public function current(User $user): ?PreviewLeadReservation
    {
        return PreviewLeadReservation::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereNull('released_at')
            ->with(['campaign', 'phoneState.contact.contactList', 'phoneState.contactPhone'])
            ->latest('id')
            ->first();
    }
}
