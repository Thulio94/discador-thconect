<?php

namespace App\Services\Dialer;

use App\Contracts\Dialer\DialerGateway;
use App\Models\Call;

class SimulatedDialerGateway implements DialerGateway
{
    public function originate(DialerCallRequest $request): Call
    {
        return Call::create([
            'company_id' => $request->companyId,
            'user_id' => $request->userId,
            'campaign_id' => $request->campaignId,
            'contact_id' => $request->contactId,
            'contact_phone_id' => $request->contactPhoneId,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => $request->channel,
            'phone' => $request->phone,
            'started_at' => now(),
            'answered_at' => now(),
        ]);
    }

    public function cancel(DialerCallRequest $request, string $reason): Call
    {
        return Call::create([
            'company_id' => $request->companyId,
            'user_id' => $request->userId,
            'campaign_id' => $request->campaignId,
            'contact_id' => $request->contactId,
            'contact_phone_id' => $request->contactPhoneId,
            'direction' => 'outbound',
            'status' => 'cancelled',
            'end_reason' => $reason,
            'channel' => $request->channel,
            'phone' => $request->phone,
            'started_at' => now(),
            'ended_at' => now(),
        ]);
    }

    public function hangup(Call $call): Call
    {
        if ($call->status !== 'in_progress') {
            return $call;
        }

        $call->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        return $call;
    }
}
