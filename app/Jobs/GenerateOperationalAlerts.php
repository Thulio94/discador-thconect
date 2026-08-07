<?php

namespace App\Jobs;

use App\Models\Call;
use App\Models\Company;
use App\Models\OperationalParameter;
use App\Models\User;
use App\Support\Notifications\AppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateOperationalAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AppNotificationService $notifications): void
    {
        Company::query()->where('status', 'active')->each(function (Company $company) use ($notifications): void {
            $parameters = OperationalParameter::forCompany($company->id);
            User::query()->where('company_id', $company->id)->with('currentAgentStatus')->whereHas('currentAgentStatus', fn ($q) => $q->where('status', 'paused'))->each(function (User $user) use ($parameters, $notifications): void {
                $minutes = (int) ($user->currentAgentStatus?->started_at?->diffInMinutes(now()) ?? 0);
                if ($minutes >= $parameters->pause_alert_minutes) {
                    $notifications->createForCompanyAudience($user->company_id, ['company_admin', 'supervisor'], 'operation', 'warning', 'Pausa longa', "{$user->name} esta em pausa ha {$minutes} min.", route('tenant.supervision.index'), dedupe: true);
                }
            });
            Call::query()->where('company_id', $company->id)->where('status', 'in_progress')->with('user')->each(function (Call $call) use ($parameters, $notifications): void {
                $minutes = (int) ($call->started_at?->diffInMinutes(now()) ?? 0);
                if ($minutes >= $parameters->call_alert_minutes && $call->user) {
                    $notifications->createForCompanyAudience($call->company_id, ['company_admin', 'supervisor'], 'operation', 'warning', 'Chamada sem tabulacao', "{$call->user->name} esta em chamada ha {$minutes} min sem tabulacao.", route('tenant.supervision.index'), dedupe: true);
                }
            });
        });
    }
}
