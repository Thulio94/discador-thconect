<?php

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchPowerCampaigns implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Campaign::query()
            ->where('status', 'active')
            ->where('dialing_mode', 'power')
            ->orderByDesc('priority')
            ->orderBy('last_dispatched_at')
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $id) => RunPowerCampaign::dispatch($id)->onQueue('telephony'));
    }
}
