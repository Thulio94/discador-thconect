<?php

namespace App\Services\Dialer;

use App\Contracts\Dialer\DialerGateway;
use App\Models\Call;
use App\Services\Reports\CallReportSnapshot;

class SimulatedDialerGateway implements DialerGateway
{
    public function __construct(private readonly CallReportSnapshot $snapshots) {}

    public function originate(DialerCallRequest $request): Call
    {
        $call = Call::create([
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
        $this->snapshots->capture($call);

        return $call;
    }

    public function cancel(DialerCallRequest $request, string $reason): Call
    {
        $call = Call::create([
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
        $this->snapshots->capture($call);

        return $call;
    }

    public function hangup(Call $call): Call
    {
        if ($call->status !== 'in_progress') {
            return $call;
        }

        $call->update([
            'status' => 'ended',
            'ended_at' => now(),
            'hangup_party' => $call->hangup_party ?? 'agent',
        ]);

        return $call;
    }
}
