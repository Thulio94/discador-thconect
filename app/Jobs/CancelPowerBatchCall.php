<?php

namespace App\Jobs;

use App\Models\Call;
use App\Services\Telephony\AsteriskBridgeClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CancelPowerBatchCall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $callId) {}

    public function handle(AsteriskBridgeClient $bridge): void
    {
        $call = Call::query()->with('telephonyRoute.asteriskNode')->find($this->callId);
        if (! $call || $call->end_reason !== 'power_batch_winner' || $call->channel !== 'asterisk_ami') {
            return;
        }

        $bridge->hangup([
            'company_id' => $call->company_id,
            'route_id' => $call->telephony_route_id,
            'call_id' => $call->id,
            'channel' => $call->telephony_channel,
            'uniqueid' => $call->telephony_uniqueid,
        ], $call->telephonyRoute?->asteriskNode);
    }
}
