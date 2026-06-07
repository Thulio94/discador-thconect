<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Disposition;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId] = $this->filters($request);
        $callsQuery = $this->callsQuery($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId);

        $recentCalls = (clone $callsQuery)
            ->with(['user', 'campaign'])
            ->latest('started_at')
            ->limit(10)
            ->get();

        return view('tenant.reports.index', [
            'calls' => $recentCalls,
            'metrics' => [
                'total_calls' => (clone $callsQuery)->count(),
                'in_progress_calls' => (clone $callsQuery)->where('status', 'in_progress')->count(),
                'ended_calls' => (clone $callsQuery)->where('status', 'ended')->count(),
                'available_agents' => $this->statusCount($companyId, 'available'),
                'paused_agents' => $this->statusCount($companyId, 'paused'),
                'in_call_agents' => $this->statusCount($companyId, 'in_call'),
            ],
            'agents' => User::query()->where('company_id', $companyId)->whereIn('user_type', ['agent', 'supervisor'])->orderBy('name')->get(),
            'campaigns' => Campaign::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'teams' => Team::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(),
            'dispositions' => Disposition::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(),
            'lists' => ContactList::query()->where('company_id', $companyId)->withCount('contacts')->orderBy('name')->get(),
            'agentPerformance' => $this->agentPerformance($companyId, $dateFrom, $dateTo, $userId, $campaignId, $teamId),
            'campaignPerformance' => $this->campaignPerformance($companyId, $dateFrom, $dateTo, $userId, $campaignId, $teamId),
            'teamPerformance' => $this->teamPerformance($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId),
            'dispositionSummary' => $this->dispositionSummary($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId),
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'user_id' => $userId,
                'campaign_id' => $campaignId,
                'disposition_id' => $dispositionId,
                'team_id' => $teamId,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId] = $this->filters($request);
        $callsQuery = $this->callsQuery($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId)
            ->with(['user', 'campaign'])
            ->oldest('started_at');

        return response()->streamDownload(function () use ($callsQuery): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'data_inicio',
                'data_fim',
                'telefone',
                'agente',
                'campanha',
                'status',
                'canal',
                'tabulacao_codigo',
                'tabulacao_nome',
                'observacoes',
            ]);

            $callsQuery->chunk(200, function ($calls) use ($output): void {
                foreach ($calls as $call) {
                    fputcsv($output, [
                        $call->started_at?->toDateTimeString(),
                        $call->ended_at?->toDateTimeString(),
                        $call->phone,
                        $call->user?->name,
                        $call->campaign?->name,
                        $call->status,
                        $call->channel,
                        $call->disposition_code,
                        $call->disposition_name,
                        $call->disposition_notes,
                    ]);
                }
            });

            fclose($output);
        }, 'relatorios-chamadas.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function statusCount(int $companyId, string $status): int
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereHas('currentAgentStatus', fn ($query) => $query->where('status', $status))
            ->count();
    }

    private function agentPerformance(int $companyId, $dateFrom, $dateTo, ?int $userId, ?int $campaignId, ?int $teamId)
    {
        return User::query()
            ->where('company_id', $companyId)
            ->whereIn('user_type', ['agent', 'supervisor'])
            ->withCount(['calls as calls_count' => fn ($query) => $query
                ->where('company_id', $companyId)
                ->whereBetween('started_at', [$dateFrom, $dateTo])
                ->when($campaignId, fn ($callQuery) => $callQuery->where('campaign_id', $campaignId))])
            ->when($userId, fn ($query) => $query->where('id', $userId))
            ->when($teamId, fn ($query) => $query->whereExists(fn ($teamQuery) => $teamQuery
                ->from('team_user')
                ->whereColumn('team_user.user_id', 'users.id')
                ->whereColumn('team_user.company_id', 'users.company_id')
                ->where('team_user.company_id', $companyId)
                ->where('team_user.team_id', $teamId)))
            ->orderByDesc('calls_count')
            ->limit(8)
            ->get();
    }

    private function campaignPerformance(int $companyId, $dateFrom, $dateTo, ?int $userId, ?int $campaignId, ?int $teamId)
    {
        return Campaign::query()
            ->where('company_id', $companyId)
            ->withCount(['calls as calls_count' => fn ($query) => $query
                ->where('company_id', $companyId)
                ->whereBetween('started_at', [$dateFrom, $dateTo])
                ->when($userId, fn ($callQuery) => $callQuery->where('user_id', $userId))
                ->when($teamId, fn ($callQuery) => $this->whereCallBelongsToTeam($callQuery, $companyId, $teamId))])
            ->when($campaignId, fn ($query) => $query->where('id', $campaignId))
            ->orderByDesc('calls_count')
            ->limit(8)
            ->get();
    }

    private function teamPerformance(int $companyId, $dateFrom, $dateTo, ?int $userId, ?int $campaignId, ?int $dispositionId, ?int $teamId)
    {
        return Team::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->select('teams.*')
            ->selectSub(function ($query) use ($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId): void {
                $query->from('calls')
                    ->join('team_user', function ($join): void {
                        $join->on('team_user.user_id', '=', 'calls.user_id')
                            ->on('team_user.company_id', '=', 'calls.company_id');
                    })
                    ->whereColumn('team_user.team_id', 'teams.id')
                    ->whereColumn('team_user.company_id', 'teams.company_id')
                    ->where('calls.company_id', $companyId)
                    ->whereBetween('calls.started_at', [$dateFrom, $dateTo])
                    ->when($userId, fn ($callQuery) => $callQuery->where('calls.user_id', $userId))
                    ->when($campaignId, fn ($callQuery) => $callQuery->where('calls.campaign_id', $campaignId))
                    ->when($dispositionId, fn ($callQuery) => $callQuery->where('calls.disposition_id', $dispositionId))
                    ->selectRaw('COUNT(*)');
            }, 'calls_count')
            ->when($teamId, fn ($query) => $query->where('id', $teamId))
            ->orderByDesc('calls_count')
            ->limit(8)
            ->get();
    }

    private function dispositionSummary(int $companyId, $dateFrom, $dateTo, ?int $userId, ?int $campaignId, ?int $dispositionId, ?int $teamId)
    {
        return $this->callsQuery($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId)
            ->selectRaw("COALESCE(disposition_code, 'SEM_TABULACAO') as disposition_code")
            ->selectRaw("COALESCE(disposition_name, 'Sem tabulacao') as disposition_name")
            ->selectRaw('COUNT(*) as calls_count')
            ->groupBy('calls.disposition_code', 'calls.disposition_name')
            ->orderByDesc('calls_count')
            ->limit(8)
            ->get();
    }

    private function filters(Request $request): array
    {
        return [
            $request->user()->company_id,
            $request->date('date_from')?->startOfDay() ?? now()->subDays(30)->startOfDay(),
            $request->date('date_to')?->endOfDay() ?? now()->endOfDay(),
            $request->integer('user_id') ?: null,
            $request->integer('campaign_id') ?: null,
            $request->integer('disposition_id') ?: null,
            $request->integer('team_id') ?: null,
        ];
    }

    private function callsQuery(int $companyId, $dateFrom, $dateTo, ?int $userId, ?int $campaignId, ?int $dispositionId, ?int $teamId)
    {
        return Call::query()
            ->where('company_id', $companyId)
            ->whereBetween('started_at', [$dateFrom, $dateTo])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->when($dispositionId, fn ($query) => $query->where('disposition_id', $dispositionId))
            ->when($teamId, fn ($query) => $this->whereCallBelongsToTeam($query, $companyId, $teamId));
    }

    private function whereCallBelongsToTeam($query, int $companyId, int $teamId)
    {
        return $query->whereExists(fn ($teamQuery) => $teamQuery
            ->from('team_user')
            ->whereColumn('team_user.user_id', 'calls.user_id')
            ->whereColumn('team_user.company_id', 'calls.company_id')
            ->where('team_user.company_id', $companyId)
            ->where('team_user.team_id', $teamId));
    }
}
