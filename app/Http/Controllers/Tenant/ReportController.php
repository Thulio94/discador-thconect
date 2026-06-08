<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Disposition;
use App\Models\Team;
use App\Models\User;
use App\Services\Reports\SimplePdfReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId, $listId] = $this->filters($request);
        $callsQuery = $this->callsQuery($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId, $listId);
        $timeMetrics = $this->timeMetrics(clone $callsQuery);
        $conversionMetrics = $this->conversionMetrics(clone $callsQuery);

        $analyticCalls = (clone $callsQuery)
            ->with(['user', 'campaign', 'contact', 'disposition'])
            ->latest('started_at')
            ->paginate(10)
            ->withQueryString();
        $analyticCalls->getCollection()->transform(fn (Call $call) => $this->withReportLabels($call));

        return view('tenant.reports.index', [
            'calls' => $analyticCalls,
            'metrics' => [
                'total_calls' => (clone $callsQuery)->count(),
                'in_progress_calls' => (clone $callsQuery)->where('status', 'in_progress')->count(),
                'ended_calls' => (clone $callsQuery)->where('status', 'ended')->count(),
                'avg_talk_time_label' => $timeMetrics['avg_talk_time_label'],
                'avg_wait_time_label' => $timeMetrics['avg_wait_time_label'],
                'conversion_count' => $conversionMetrics['conversion_count'],
                'conversion_rate_label' => $conversionMetrics['conversion_rate_label'],
                'available_agents' => $this->statusCount($companyId, 'available'),
                'paused_agents' => $this->statusCount($companyId, 'paused'),
                'in_call_agents' => $this->statusCount($companyId, 'in_call'),
            ],
            'agents' => User::query()->where('company_id', $companyId)->whereIn('user_type', ['agent', 'supervisor'])->orderBy('name')->get(),
            'campaigns' => Campaign::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'teams' => Team::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(),
            'dispositions' => Disposition::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(),
            'lists' => ContactList::query()->where('company_id', $companyId)->withCount('contacts')->orderBy('name')->get(),
            'mailingSummaries' => $this->mailingSummaries($companyId, $campaignId, $listId),
            'agentPerformance' => $this->agentPerformance($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId, $listId),
            'campaignPerformance' => $this->campaignPerformance($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId, $listId),
            'teamPerformance' => $this->teamPerformance($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId, $listId),
            'dispositionSummary' => $this->dispositionSummary($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId, $listId),
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'user_id' => $userId,
                'campaign_id' => $campaignId,
                'disposition_id' => $dispositionId,
                'team_id' => $teamId,
                'list_id' => $listId,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId, $listId] = $this->filters($request);
        $callsQuery = $this->callsQuery($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId, $listId)
            ->with(['user', 'campaign', 'disposition'])
            ->oldest('started_at');

        return response()->streamDownload(function () use ($callsQuery): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, $this->exportHeadings());

            $callsQuery->chunk(200, function ($calls) use ($output): void {
                foreach ($calls as $call) {
                    fputcsv($output, $this->exportRow($call));
                }
            });

            fclose($output);
        }, 'relatorios-chamadas.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportXlsx(Request $request): StreamedResponse
    {
        [$companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId, $listId] = $this->filters($request);
        $callsQuery = $this->callsQuery($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId, $listId)
            ->with(['user', 'campaign', 'disposition'])
            ->oldest('started_at');

        return response()->streamDownload(function () use ($callsQuery): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Relatorios');
            $sheet->fromArray($this->exportHeadings(), null, 'A1');

            $rowIndex = 2;
            $callsQuery->chunk(200, function ($calls) use ($sheet, &$rowIndex): void {
                foreach ($calls as $call) {
                    $sheet->fromArray($this->exportRow($call), null, 'A'.$rowIndex);
                    $rowIndex++;
                }
            });

            foreach (range('A', 'M') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'relatorios-chamadas.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportPdf(Request $request, SimplePdfReport $pdfReport): StreamedResponse
    {
        [$companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId, $listId] = $this->filters($request);
        $callsQuery = $this->callsQuery($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId, $listId)
            ->with(['user', 'campaign', 'disposition'])
            ->oldest('started_at');
        $timeMetrics = $this->timeMetrics(clone $callsQuery);
        $conversionMetrics = $this->conversionMetrics(clone $callsQuery);
        $totalCalls = (clone $callsQuery)->count();
        $endedCalls = (clone $callsQuery)->where('status', 'ended')->count();
        $sampleCalls = (clone $callsQuery)->limit(20)->get();
        $lines = [
            'Relatorio de Chamadas',
            'Resumo executivo',
            'Periodo: '.$dateFrom->format('d/m/Y').' ate '.$dateTo->format('d/m/Y'),
            'Total chamadas: '.$totalCalls,
            'Encerradas: '.$endedCalls,
            'TMA: '.$timeMetrics['avg_talk_time_label'],
            'TME: '.$timeMetrics['avg_wait_time_label'],
            'Conversoes: '.$conversionMetrics['conversion_count'],
            'Taxa conversao: '.$conversionMetrics['conversion_rate_label'],
            'Filtros: '.$this->pdfFilterLabel($companyId, $userId, $campaignId, $dispositionId, $teamId, $listId),
            'Amostra analitica',
        ];

        foreach ($sampleCalls as $call) {
            $call = $this->withReportLabels($call);
            $lines[] = implode(' | ', [
                $call->phone ?? 'Sem numero',
                $call->user?->name ?? 'Sem agente',
                $call->campaign?->name ?? 'Sem campanha',
                $call->disposition_name ?? 'Sem tabulacao',
                'Duracao '.$call->talk_time_label,
                'Espera '.$call->wait_time_label,
                'Conversao '.$call->conversion_label,
            ]);
        }

        return response()->streamDownload(function () use ($pdfReport, $lines): void {
            echo $pdfReport->render($lines);
        }, 'relatorios-chamadas.pdf', [
            'Content-Type' => 'application/pdf',
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

    private function agentPerformance(int $companyId, $dateFrom, $dateTo, ?int $userId, ?int $campaignId, ?int $dispositionId, ?int $teamId, ?int $listId)
    {
        $agents = User::query()
            ->where('company_id', $companyId)
            ->whereIn('user_type', ['agent', 'supervisor'])
            ->withCount(['calls as calls_count' => fn ($query) => $query
                ->where('company_id', $companyId)
                ->whereBetween('started_at', [$dateFrom, $dateTo])
                ->when($campaignId, fn ($callQuery) => $callQuery->where('campaign_id', $campaignId))
                ->when($dispositionId, fn ($callQuery) => $callQuery->where('disposition_id', $dispositionId))
                ->when($listId, fn ($callQuery) => $this->whereCallBelongsToList($callQuery, $listId))])
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

        return $agents->each(function (User $agent) use ($companyId, $dateFrom, $dateTo, $campaignId, $dispositionId, $teamId, $listId): void {
            $agentCallsQuery = $this->callsQuery($companyId, $dateFrom, $dateTo, $agent->id, $campaignId, $dispositionId, $teamId, $listId);
            $timeMetrics = $this->timeMetrics(clone $agentCallsQuery);
            $conversionMetrics = $this->conversionMetrics(clone $agentCallsQuery);
            $agent->setAttribute('avg_talk_time_label', $timeMetrics['avg_talk_time_label']);
            $agent->setAttribute('conversion_count', $conversionMetrics['conversion_count']);
            $agent->setAttribute('conversion_rate_label', $conversionMetrics['conversion_rate_label']);
        });
    }

    private function campaignPerformance(int $companyId, $dateFrom, $dateTo, ?int $userId, ?int $campaignId, ?int $dispositionId, ?int $teamId, ?int $listId)
    {
        $campaigns = Campaign::query()
            ->where('company_id', $companyId)
            ->withCount(['calls as calls_count' => fn ($query) => $query
                ->where('company_id', $companyId)
                ->whereBetween('started_at', [$dateFrom, $dateTo])
                ->when($userId, fn ($callQuery) => $callQuery->where('user_id', $userId))
                ->when($dispositionId, fn ($callQuery) => $callQuery->where('disposition_id', $dispositionId))
                ->when($teamId, fn ($callQuery) => $this->whereCallBelongsToTeam($callQuery, $companyId, $teamId))
                ->when($listId, fn ($callQuery) => $this->whereCallBelongsToList($callQuery, $listId))])
            ->when($campaignId, fn ($query) => $query->where('id', $campaignId))
            ->orderByDesc('calls_count')
            ->limit(8)
            ->get();

        return $campaigns->each(function (Campaign $campaign) use ($companyId, $dateFrom, $dateTo, $userId, $dispositionId, $teamId, $listId): void {
            $campaignCallsQuery = $this->callsQuery($companyId, $dateFrom, $dateTo, $userId, $campaign->id, $dispositionId, $teamId, $listId);
            $timeMetrics = $this->timeMetrics(clone $campaignCallsQuery);
            $conversionMetrics = $this->conversionMetrics(clone $campaignCallsQuery);
            $campaign->setAttribute('avg_talk_time_label', $timeMetrics['avg_talk_time_label']);
            $campaign->setAttribute('conversion_count', $conversionMetrics['conversion_count']);
            $campaign->setAttribute('conversion_rate_label', $conversionMetrics['conversion_rate_label']);
        });
    }

    private function teamPerformance(int $companyId, $dateFrom, $dateTo, ?int $userId, ?int $campaignId, ?int $dispositionId, ?int $teamId, ?int $listId)
    {
        $teams = Team::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->select('teams.*')
            ->selectSub(function ($query) use ($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $listId): void {
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
                    ->when($listId, fn ($callQuery) => $this->whereCallBelongsToList($callQuery, $listId))
                    ->selectRaw('COUNT(*)');
            }, 'calls_count')
            ->when($teamId, fn ($query) => $query->where('id', $teamId))
            ->orderByDesc('calls_count')
            ->limit(8)
            ->get();

        return $teams->each(function (Team $team) use ($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $listId): void {
            $conversionMetrics = $this->conversionMetrics($this->callsQuery($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $team->id, $listId));
            $team->setAttribute('conversion_count', $conversionMetrics['conversion_count']);
            $team->setAttribute('conversion_rate_label', $conversionMetrics['conversion_rate_label']);
        });
    }

    private function dispositionSummary(int $companyId, $dateFrom, $dateTo, ?int $userId, ?int $campaignId, ?int $dispositionId, ?int $teamId, ?int $listId)
    {
        return $this->callsQuery($companyId, $dateFrom, $dateTo, $userId, $campaignId, $dispositionId, $teamId, $listId)
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
            $request->integer('list_id') ?: null,
        ];
    }

    private function callsQuery(int $companyId, $dateFrom, $dateTo, ?int $userId, ?int $campaignId, ?int $dispositionId, ?int $teamId, ?int $listId)
    {
        return Call::query()
            ->where('company_id', $companyId)
            ->whereBetween('started_at', [$dateFrom, $dateTo])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->when($dispositionId, fn ($query) => $query->where('disposition_id', $dispositionId))
            ->when($teamId, fn ($query) => $this->whereCallBelongsToTeam($query, $companyId, $teamId))
            ->when($listId, fn ($query) => $this->whereCallBelongsToList($query, $listId));
    }

    private function mailingSummaries(int $companyId, ?int $campaignId, ?int $listId)
    {
        return ContactList::query()
            ->where('company_id', $companyId)
            ->when($listId, fn ($query) => $query->where('id', $listId))
            ->when($campaignId, fn ($query) => $query->whereHas('campaigns', fn ($campaignQuery) => $campaignQuery
                ->where('campaigns.id', $campaignId)
                ->where('campaign_contact_list.company_id', $companyId)))
            ->withCount([
                'contacts',
                'contacts as pending_contacts_count' => fn ($query) => $query->where('status', 'pending'),
                'contacts as dialing_contacts_count' => fn ($query) => $query->where('status', 'dialing'),
                'contacts as answered_contacts_count' => fn ($query) => $query->where('status', 'answered'),
                'contacts as unproductive_contacts_count' => fn ($query) => $query->where('status', 'unproductive'),
                'contacts as callback_contacts_count' => fn ($query) => $query->where('status', 'callback'),
                'contacts as exhausted_contacts_count' => fn ($query) => $query->where('status', 'exhausted'),
            ])
            ->orderBy('name')
            ->get();
    }

    private function timeMetrics($query): array
    {
        $calls = (clone $query)
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->get(['started_at', 'answered_at', 'ended_at']);

        $talkTimes = [];
        $waitTimes = [];

        foreach ($calls as $call) {
            $talkTimes[] = $this->secondsBetween($call->started_at, $call->ended_at);
            $waitTimes[] = $call->answered_at ? $this->secondsBetween($call->started_at, $call->answered_at) : 0;
        }

        $avgTalkTime = $this->averageSeconds($talkTimes);
        $avgWaitTime = $this->averageSeconds($waitTimes);

        return [
            'avg_talk_time_seconds' => $avgTalkTime,
            'avg_talk_time_label' => $this->formatSeconds($avgTalkTime),
            'avg_wait_time_seconds' => $avgWaitTime,
            'avg_wait_time_label' => $this->formatSeconds($avgWaitTime),
        ];
    }

    private function conversionMetrics($query): array
    {
        $baseQuery = (clone $query)
            ->where('status', 'ended')
            ->whereNotNull('disposition_id');
        $baseCount = (clone $baseQuery)->count();
        $conversionCount = (clone $baseQuery)
            ->whereHas('disposition', fn ($dispositionQuery) => $dispositionQuery->where('is_success', true))
            ->count();

        return [
            'conversion_count' => $conversionCount,
            'conversion_rate' => $baseCount > 0 ? (int) round(($conversionCount / $baseCount) * 100) : 0,
            'conversion_rate_label' => $baseCount > 0 ? ((int) round(($conversionCount / $baseCount) * 100)).'%' : '0%',
        ];
    }

    private function exportHeadings(): array
    {
        return [
            'data_inicio',
            'data_fim',
            'duracao',
            'espera',
            'conversao',
            'telefone',
            'agente',
            'campanha',
            'status',
            'canal',
            'tabulacao_codigo',
            'tabulacao_nome',
            'observacoes',
        ];
    }

    private function exportRow(Call $call): array
    {
        $call = $this->withReportLabels($call);

        return [
            $call->started_at?->toDateTimeString(),
            $call->ended_at?->toDateTimeString(),
            $call->talk_time_label,
            $call->wait_time_label,
            $call->conversion_label,
            $call->phone,
            $call->user?->name,
            $call->campaign?->name,
            $call->status,
            $call->channel,
            $call->disposition_code,
            $call->disposition_name,
            $call->disposition_notes,
        ];
    }

    private function withReportLabels(Call $call): Call
    {
        $this->withTimeLabels($call);
        $call->setAttribute('conversion_label', $call->disposition?->is_success ? 'sim' : 'nao');

        return $call;
    }

    private function withTimeLabels(Call $call): Call
    {
        $talkTime = $call->started_at && $call->ended_at ? $this->secondsBetween($call->started_at, $call->ended_at) : null;
        $waitTime = $call->started_at && $call->ended_at ? ($call->answered_at ? $this->secondsBetween($call->started_at, $call->answered_at) : 0) : null;

        $call->setAttribute('talk_time_label', $talkTime === null ? 'em andamento' : $this->formatSeconds($talkTime));
        $call->setAttribute('wait_time_label', $waitTime === null ? 'em andamento' : $this->formatSeconds($waitTime));

        return $call;
    }

    private function averageSeconds(array $seconds): int
    {
        if ($seconds === []) {
            return 0;
        }

        return (int) round(array_sum($seconds) / count($seconds));
    }

    private function secondsBetween($start, $end): int
    {
        return max(0, $end->getTimestamp() - $start->getTimestamp());
    }

    private function formatSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.' h';
        }

        if ($minutes > 0) {
            $parts[] = $minutes.' min';
        }

        if ($remainingSeconds > 0) {
            $parts[] = $remainingSeconds.'s';
        }

        return implode(' ', $parts);
    }

    private function pdfFilterLabel(int $companyId, ?int $userId, ?int $campaignId, ?int $dispositionId, ?int $teamId, ?int $listId): string
    {
        $filters = [];

        if ($userId) {
            $filters[] = 'Agente '.User::query()->where('company_id', $companyId)->find($userId)?->name;
        }

        if ($campaignId) {
            $filters[] = 'Campanha '.Campaign::query()->where('company_id', $companyId)->find($campaignId)?->name;
        }

        if ($dispositionId) {
            $filters[] = 'Resultado '.Disposition::query()->where('company_id', $companyId)->find($dispositionId)?->name;
        }

        if ($teamId) {
            $filters[] = 'Equipe '.Team::query()->where('company_id', $companyId)->find($teamId)?->name;
        }

        if ($listId) {
            $filters[] = 'Lista '.ContactList::query()->where('company_id', $companyId)->find($listId)?->name;
        }

        return $filters === [] ? 'Todos' : implode(', ', array_filter($filters));
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

    private function whereCallBelongsToList($query, int $listId)
    {
        return $query->whereExists(fn ($contactQuery) => $contactQuery
            ->from('contacts')
            ->whereColumn('contacts.id', 'calls.contact_id')
            ->where('contacts.contact_list_id', $listId));
    }
}
