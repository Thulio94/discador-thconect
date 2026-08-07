<?php

namespace App\Http\Controllers;

use App\Models\AsteriskNode;
use App\Models\ChannelReservation;
use App\Models\Company;
use App\Models\SystemCapacitySetting;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;

class InternalMetricsController extends Controller
{
    public function __invoke(): Response
    {
        $active = ChannelReservation::query()->whereIn('status', ['reserved', 'active'])->where('expires_at', '>', now())->count();
        $settings = SystemCapacitySetting::current();
        $lines = [
            '# HELP thconect_channels_active Active reserved telephony channels.',
            '# TYPE thconect_channels_active gauge',
            "thconect_channels_active {$active}",
            "thconect_channels_limit {$settings->max_channels}",
            'thconect_companies_total '.Company::query()->count(),
        ];
        foreach (['telephony', 'imports', 'reports', 'media', 'notifications', 'default'] as $queue) {
            $lines[] = sprintf('thconect_queue_jobs{queue="%s"} %d', $queue, Queue::connection()->size($queue));
        }
        foreach (AsteriskNode::query()->get() as $node) {
            $nodeActive = ChannelReservation::query()->where('asterisk_node_id', $node->id)->whereIn('status', ['reserved', 'active'])->where('expires_at', '>', now())->count();
            $name = addslashes($node->name);
            $lines[] = "thconect_asterisk_channels_active{node=\"{$name}\"} {$nodeActive}";
            $lines[] = "thconect_asterisk_channels_limit{node=\"{$name}\"} {$node->max_channels}";
        }
        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }
}
