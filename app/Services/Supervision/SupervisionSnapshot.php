<?php

namespace App\Services\Supervision;

use App\Models\Call;
use App\Models\OperationalParameter;
use App\Models\Team;
use App\Models\User;
use App\Services\Dialer\CallWrapUpService;
use App\Support\Notifications\AppNotificationService;
use Illuminate\Support\Facades\DB;

final class SupervisionSnapshot
{
    public function __construct(
        private readonly AppNotificationService $notifications,
        private readonly CallWrapUpService $wrapUps,
    ) {}

    public function forCompany(int $companyId, string $status = '', ?int $teamId = null): array
    {
        $this->wrapUps->expireForCompany($companyId);
        $parameters = OperationalParameter::forCompany($companyId);

        if ($teamId !== null) {
            Team::query()
                ->where('company_id', $companyId)
                ->findOrFail($teamId);
        }

        abort_unless(
            in_array($status, ['', 'available', 'paused', 'in_call', 'wrap_up', 'offline'], true),
            404
        );

        $agents = User::query()
            ->where('company_id', $companyId)
            ->whereIn('user_type', ['agent', 'supervisor'])
            ->with([
                'currentAgentStatus',
                'calls' => fn ($query) => $query
                    ->whereIn('status', ['in_progress', 'wrap_up'])
                    ->with('campaign')
                    ->latest(),
            ])
            ->when($teamId, fn ($query) => $query->whereIn(
                'users.id',
                function ($teamQuery) use ($companyId, $teamId): void {
                    $teamQuery
                        ->select('team_user.user_id')
                        ->from('team_user')
                        ->where('team_user.company_id', $companyId)
                        ->where('team_user.team_id', $teamId);
                }
            ))
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($companyId, $parameters): User {
                $activeCall = $user->calls->first();
                $wrapUpCall = $user->calls->firstWhere('status', 'wrap_up');
                $statusStartedAt = $user->currentAgentStatus?->started_at;
                $statusMinutes = $statusStartedAt
                    ? (int) $statusStartedAt->diffInMinutes(now())
                    : 0;
                $callMinutes = $activeCall?->started_at
                    ? (int) $activeCall->started_at->diffInMinutes(now())
                    : 0;

                $user->setAttribute(
                    'supervision_status',
                    $user->currentAgentStatus?->status ?? 'offline'
                );
                $user->setAttribute(
                    'status_duration_label',
                    $this->durationLabel($statusMinutes)
                );
                $user->setAttribute(
                    'active_call_duration_label',
                    $activeCall ? $this->durationLabel($callMinutes) : null
                );
                $user->setAttribute(
                    'current_campaign_name',
                    $activeCall?->campaign?->name
                );
                $user->setAttribute(
                    'wrap_up_duration_label',
                    $wrapUpCall?->wrap_up_expires_at
                        ? $this->durationLabel(max(0, now()->diffInMinutes($wrapUpCall->wrap_up_expires_at, false)))
                        : null
                );
                $user->setAttribute('operational_alerts', collect([
                    $user->currentAgentStatus?->status === 'paused'
                        && $statusMinutes >= $parameters->pause_alert_minutes ? 'Pausa longa' : null,
                    $user->currentAgentStatus?->status === 'paused'
                        && $statusMinutes >= $parameters->max_pause_minutes ? 'Pausa excedida' : null,
                    $activeCall && $callMinutes >= $parameters->call_alert_minutes
                        ? 'Chamada longa' : null,
                ])->filter()->values()->all());

                $this->notifyOperationalAlerts(
                    $user,
                    $statusMinutes,
                    $callMinutes,
                    $activeCall,
                    $parameters
                );

                $user->setAttribute('team_names', DB::table('team_user')
                    ->join('teams', 'teams.id', '=', 'team_user.team_id')
                    ->where('team_user.company_id', $companyId)
                    ->where('team_user.user_id', $user->id)
                    ->orderBy('teams.name')
                    ->pluck('teams.name')
                    ->all());

                return $user;
            })
            ->when(
                $status !== '',
                fn ($collection) => $collection->filter(
                    fn (User $user): bool => $user->supervision_status === $status
                )
            )
            ->values();

        $statuses = User::query()
            ->where('company_id', $companyId)
            ->whereIn('user_type', ['agent', 'supervisor'])
            ->with('currentAgentStatus')
            ->get()
            ->map(
                fn (User $user): string => $user->currentAgentStatus?->status ?? 'offline'
            );

        return [
            'agents' => $agents,
            'teams' => Team::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(),
            'selectedStatus' => $status,
            'selectedTeamId' => $teamId,
            'metrics' => [
                'available' => $statuses->filter(
                    fn (string $value): bool => $value === 'available'
                )->count(),
                'paused' => $statuses->filter(
                    fn (string $value): bool => $value === 'paused'
                )->count(),
                'in_call' => $statuses->filter(
                    fn (string $value): bool => $value === 'in_call'
                )->count(),
                'wrap_up' => $statuses->filter(
                    fn (string $value): bool => $value === 'wrap_up'
                )->count(),
                'offline' => $statuses->filter(
                    fn (string $value): bool => $value === 'offline'
                )->count(),
                'active_calls' => Call::query()
                    ->where('company_id', $companyId)
                    ->where('status', 'in_progress')
                    ->count(),
            ],
        ];
    }

    private function durationLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $remainingMinutes > 0
            ? $hours.'h '.$remainingMinutes.' min'
            : $hours.'h';
    }

    private function notifyOperationalAlerts(
        User $user,
        int $statusMinutes,
        int $callMinutes,
        ?Call $activeCall,
        OperationalParameter $parameters
    ): void {
        if ($user->currentAgentStatus?->status === 'paused' && $statusMinutes >= $parameters->pause_alert_minutes) {
            $this->notifications->createForCompanyAudience(
                $user->company_id,
                ['company_admin', 'supervisor'],
                'operation',
                'warning',
                'Pausa longa',
                sprintf(
                    '%s esta em pausa ha %s.',
                    $user->name,
                    $this->durationLabel($statusMinutes)
                ),
                route('tenant.supervision.index'),
                dedupe: true
            );
        }

        if ($user->currentAgentStatus?->status === 'paused' && $statusMinutes >= $parameters->max_pause_minutes) {
            $this->notifications->createForCompanyAudience(
                $user->company_id,
                ['company_admin', 'supervisor'],
                'operation',
                'warning',
                'Pausa excedida',
                sprintf(
                    '%s ultrapassou o limite de pausa de %s.',
                    $user->name,
                    $this->durationLabel($parameters->max_pause_minutes)
                ),
                route('tenant.supervision.index'),
                dedupe: true
            );
        }

        if ($activeCall && $callMinutes >= $parameters->call_alert_minutes) {
            $this->notifications->createForCompanyAudience(
                $user->company_id,
                ['company_admin', 'supervisor'],
                'operation',
                'warning',
                'Chamada sem tabulacao',
                sprintf(
                    '%s esta em chamada ha %s sem tabulacao.',
                    $user->name,
                    $this->durationLabel($callMinutes)
                ),
                route('tenant.supervision.index'),
                dedupe: true
            );
        }
    }
}
