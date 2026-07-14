<?php

namespace App\Services\Dialer;

use App\Contracts\Dialer\DialerGateway;
use App\Models\Call;
use App\Models\CompanyTelephonyConfiguration;
use App\Services\Telephony\AsteriskBridgeClient;
use Illuminate\Support\Str;
use Throwable;

class AsteriskDialerGateway implements DialerGateway
{
    public function __construct(
        private readonly SimulatedDialerGateway $simulated,
        private readonly AsteriskBridgeClient $bridge,
    ) {}

    public function originate(DialerCallRequest $request): Call
    {
        $configuration = CompanyTelephonyConfiguration::query()
            ->where('company_id', $request->companyId)
            ->first();

        if (! $configuration?->isReadyForAsterisk()) {
            return $this->simulated->originate($request);
        }

        $actionId = 'thconect-'.$request->companyId.'-'.Str::uuid()->toString();
        $call = Call::create([
            'company_id' => $request->companyId,
            'user_id' => $request->userId,
            'campaign_id' => $request->campaignId,
            'contact_id' => $request->contactId,
            'contact_phone_id' => $request->contactPhoneId,
            'direction' => 'outbound',
            'status' => 'dialing',
            'channel' => 'asterisk_ami',
            'telephony_action_id' => $actionId,
            'phone' => $request->phone,
            'started_at' => now(),
        ]);

        try {
            $this->bridge->originate([
                'company_id' => $request->companyId,
                'call_id' => $call->id,
                'action_id' => $actionId,
                'phone' => $request->phone,
                'channel' => str_replace('{phone}', $request->phone, (string) $configuration->outbound_channel_template),
                'context' => $configuration->dialplan_context,
                'extension' => $configuration->dialplan_extension,
                'caller_id' => $configuration->caller_id,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $call->update([
                'status' => 'cancelled',
                'end_reason' => 'bridge_unavailable',
                'ended_at' => now(),
            ]);
        }

        return $call;
    }

    public function cancel(DialerCallRequest $request, string $reason): Call
    {
        return $this->simulated->cancel($request, $reason);
    }

    public function hangup(Call $call): Call
    {
        if ($call->channel !== 'asterisk_ami') {
            return $this->simulated->hangup($call);
        }

        if ($call->status === 'ended' || $call->status === 'cancelled') {
            return $call;
        }

        try {
            $this->bridge->hangup([
                'company_id' => $call->company_id,
                'call_id' => $call->id,
                'channel' => $call->telephony_channel,
                'uniqueid' => $call->telephony_uniqueid,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        $call->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        return $call;
    }
}
