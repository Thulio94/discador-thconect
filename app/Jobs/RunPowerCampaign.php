<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\Dialer\SimulatedDialerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RunPowerCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $campaignId) {}

    public function handle(SimulatedDialerService $dialer): void
    {
        $campaign = Campaign::query()->find($this->campaignId);
        if (! $campaign || $campaign->dialing_mode !== 'power' || $campaign->status !== 'active') {
            return;
        }

        $lock = Cache::lock('dialer:campaign:'.$campaign->id, 15);
        if (! $lock->get()) {
            return;
        }

        try {
            if ($dialer->runCampaign($campaign) > 0) {
                $campaign->update(['last_dispatched_at' => now()]);
            }
        } finally {
            $lock->release();
        }
    }
}
