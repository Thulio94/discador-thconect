<?php

use App\Jobs\RecordQueueHeartbeat;
use App\Jobs\ReconcileChannelReservations;
use App\Jobs\GenerateOperationalAlerts;
use App\Jobs\DispatchPowerCampaigns;
use App\Services\Dialer\CallWrapUpService;
use App\Services\SystemStatus\SystemStatusService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reports:run-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('reports:purge-expired-assets')
    ->dailyAt('03:20')
    ->withoutOverlapping();

Schedule::command('calls:archive-old')
    ->monthlyOn(2, '02:30')
    ->withoutOverlapping();

Schedule::call(fn () => app(SystemStatusService::class)->recordSchedulerHeartbeat())
    ->name('system-status:scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::job(new RecordQueueHeartbeat)
    ->name('system-status:queue-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::job((new ReconcileChannelReservations)->onQueue('telephony'))
    ->name('capacity:reconcile-channel-reservations')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::job((new GenerateOperationalAlerts)->onQueue('notifications'))
    ->name('supervision:generate-operational-alerts')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::call(fn () => app(CallWrapUpService::class)->expireDue())
    ->name('dialer:expire-call-wrap-ups')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::job((new DispatchPowerCampaigns)->onQueue('telephony'))
    ->name('dialer:dispatch-power-campaigns')
    ->everySecond()
    ->withoutOverlapping();
