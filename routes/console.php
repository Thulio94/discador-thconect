<?php

use App\Jobs\RecordQueueHeartbeat;
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

Schedule::call(fn () => app(SystemStatusService::class)->recordSchedulerHeartbeat())
    ->name('system-status:scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::job(new RecordQueueHeartbeat)
    ->name('system-status:queue-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::call(fn () => app(CallWrapUpService::class)->expireDue())
    ->name('dialer:expire-call-wrap-ups')
    ->everyMinute()
    ->withoutOverlapping();
