<?php

namespace App\Services\Supervision;

use App\Jobs\GenerateOperationalAlerts;
use App\Models\Call;
use App\Models\Company;
use App\Models\OperationalParameter;
use App\Models\Team;
use App\Models\User;
use App\Services\Dialer\CallWrapUpService;
use Illuminate\Support\Facades\DB;

final class SupervisionSnapshot
{
    public function __construct(
        private readonly CallWrapUpService $wrapUps,
    ) {}

    public function forCompany(int $companyId, string $status = '', ?int $teamId = null): array
    {
        $this->wrapUps->expireForCompany($companyId);
        if (app()->runningUnitTests()) {
            dispatch_sync(new GenerateOperationalAlerts);
        }

        return $this->build($companyId, $status, $teamId);
    }

    private function build(int $companyId, string $status, ?int $teamId): array
    {
        $parameters = OperationalParameter::forCompany($companyId);
        $monitoringReady = Company::query()
            ->with('telephonyRoutes')
            ->find($companyId)?->telephonyRoutes?->contains(
                fn ($route): bool => $route->isReady() && $route->connection_status === 'online'
            ) ?? false;

        if ($teamId !== null) {
            Team::query()
                ->where('company_id', $companyId)
                ->findOrFail($teamId);
        }

        abort_unless(
            in_array($status, ['', 'available', 'paused', 'in_call', 'wrap_up', 'offline'], true),
            404
        );

        $durationExpression = DB::getDriverName() === 'sqlite'
            ? 'AVG(MAX(0, (julianday(COALESCE(ended_at, CURRENT_TIMESTAMP)) - julianday(started_at)) * 86400))'
            : 'AVG(GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, NOW()))))';
        $todayCallsByUser = Call::query()
            ->where('company_id', $companyId)
            ->whereDate('started_at', today())
            ->whereNotNull('user_id')
            ->select('user_id')
            ->selectRaw('COUNT(*) as calls_count')
            ->selectRaw("{$durationExpression} as average_seconds")
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $agents = User::query()
            ->where('company_id', $companyId)
            ->whereIn('user_type', ['agent', 'supervisor'])
            ->with([
                'currentAgentStatus',
                'assignedTeams:id,name',
                'calls' => fn ($query) => $query
                    ->whereIn('status', [...Call::ACTIVE_STATUSES, 'wrap_up'])
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
            ->map(function (User $user) use ($parameters, $todayCallsByUser): User {
                $activeCall = $user->calls->first();
                $wrapUpCall = $user->calls->firstWhere('status', 'wrap_up');
                $statusStartedAt = $user->currentAgentStatus?->started_at;
                $statusSeconds = $statusStartedAt
                    ? max(0, (int) $statusStartedAt->diffInSeconds(now()))
                    : 0;
                $callSeconds = $activeCall?->started_at
                    ? max(0, (int) $activeCall->started_at->diffInSeconds(now()))
                    : 0;
                $statusMinutes = intdiv($statusSeconds, 60);
                $callMinutes = intdiv($callSeconds, 60);

                $user->setAttribute(
                    'supervision_status',
                    $user->currentAgentStatus?->status ?? 'offline'
                );
                $user->setAttribute(
                    'status_duration_label',
                    $this->durationLabel($statusSeconds)
                );
                $user->setAttribute(
                    'status_started_at_unix',
                    $statusStartedAt?->timestamp
                );
                $user->setAttribute(
                    'active_call_duration_label',
                    $activeCall ? $this->durationLabel($callSeconds) : null
                );
                $user->setAttribute(
                    'active_call_started_at_unix',
                    $activeCall?->started_at?->timestamp
                );
                $user->setAttribute(
                    'current_campaign_name',
                    $activeCall?->campaign?->name
                );
                $user->setAttribute(
                    'wrap_up_duration_label',
                    $wrapUpCall?->wrap_up_expires_at
                        ? $this->durationLabel(max(0, now()->diffInSeconds($wrapUpCall->wrap_up_expires_at, false)))
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

                $todayCalls = $todayCallsByUser->get($user->id);
                $averageSeconds = (int) round($todayCalls?->average_seconds ?? 0);

                $user->setAttribute('calls_today_count', (int) ($todayCalls?->calls_count ?? 0));
                $user->setAttribute('calls_average_label', sprintf(
                    '%02d:%02d',
                    intdiv($averageSeconds, 60),
                    $averageSeconds % 60,
                ));

                $user->setAttribute('team_names', $user->assignedTeams
                    ->sortBy('name')
                    ->pluck('name')
                    ->values()
                    ->all());

                return $user;
            })
            ->when(
                $status !== '',
                fn ($collection) => $collection->filter(
                    fn (User $user): bool => $user->supervision_status === $status
                )
            )
            ->sortBy(fn (User $user): int => $user->supervision_status === 'offline' ? 1 : 0)
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
            'teamOptions' => Team::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Team $team): array => [
                    'id' => $team->id,
                    'name' => $team->name,
                ])
                ->values()
                ->all(),
            'selectedStatus' => $status,
            'selectedTeamId' => $teamId,
            'monitoringReady' => $monitoringReady,
            'monitoringReason' => $monitoringReady
                ? null
                : 'O SuperAdmin precisa atribuir e testar uma rota Asterisk para habilitar a monitoria.',
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
                    ->whereIn('status', Call::ACTIVE_STATUSES)
                    ->count(),
            ],
            'recentCalls' => Call::query()
                ->where('company_id', $companyId)
                ->with(['user:id,name,extension', 'campaign:id,name'])
                ->latest('started_at')
                ->limit(50)
                ->get(),
        ];
    }

    private function durationLabel(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }
}
