<?php

namespace App\Services\Reports;

use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\CallCallback;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\Disposition;
use App\Models\ReportExport;
use App\Models\ReportSchedule;
use App\Models\Team;
use App\Models\TelephonyRoute;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ReportDataService
{
    public const PER_PAGE = 25;

    public function catalog(): array
    {
        return [
            'calls' => ['title' => 'Relatório de ligações', 'description' => 'Chamadas, gravações, responsáveis e resultados.', 'icon' => 'phone'],
            'agents' => ['title' => 'Relatório de agentes', 'description' => 'Tempo, chamadas e tabulações por operador.', 'icon' => 'users'],
            'campaigns' => ['title' => 'Relatório de campanhas', 'description' => 'Resultados operacionais por campanha.', 'icon' => 'campaign'],
            'lists' => ['title' => 'Relatório de listas', 'description' => 'Situação do mailing e dos contatos.', 'icon' => 'list'],
            'qualifications' => ['title' => 'Relatório de tabulações', 'description' => 'Resultados e conversões registradas.', 'icon' => 'approve'],
            'teams' => ['title' => 'Relatório de equipes', 'description' => 'Produtividade comparada entre equipes.', 'icon' => 'team'],
            'utilization' => ['title' => 'Relatório de aproveitamento', 'description' => 'Funil de chamadas, entrega e conversão.', 'icon' => 'pulse'],
            'callbacks' => ['title' => 'Agendamentos', 'description' => 'Retornos de clientes pendentes e concluídos.', 'icon' => 'history'],
            'exports' => ['title' => 'Histórico de exportações', 'description' => 'Fila, arquivos e programações de relatórios.', 'icon' => 'download'],
        ];
    }

    public function filters(array $input, int $companyId): array
    {
        $dateFrom = filled($input['date_from'] ?? null)
            ? CarbonImmutable::parse($input['date_from'])->startOfDay()
            : now()->subDays(30)->startOfDay()->toImmutable();
        $dateTo = filled($input['date_to'] ?? null)
            ? CarbonImmutable::parse($input['date_to'])->endOfDay()
            : now()->endOfDay()->toImmutable();

        return [
            'company_id' => $companyId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'user_id' => $this->tenantId($input['user_id'] ?? null, User::class, $companyId),
            'campaign_id' => $this->tenantId($input['campaign_id'] ?? null, Campaign::class, $companyId),
            'team_id' => $this->tenantId($input['team_id'] ?? null, Team::class, $companyId),
            'list_id' => $this->tenantId($input['list_id'] ?? null, ContactList::class, $companyId),
            'disposition_id' => $this->tenantId($input['disposition_id'] ?? null, Disposition::class, $companyId),
            'telephony_route_id' => $this->tenantId($input['telephony_route_id'] ?? null, TelephonyRoute::class, null),
            'phone' => trim((string) ($input['phone'] ?? '')) ?: null,
            'status' => $input['status'] ?? null,
            'hangup_party' => $input['hangup_party'] ?? null,
            'amd_result' => $input['amd_result'] ?? null,
            'direction' => $input['direction'] ?? null,
            'callback_status' => $input['callback_status'] ?? null,
        ];
    }

    public function options(int $companyId): array
    {
        return [
            'agents' => User::query()->where('company_id', $companyId)->whereIn('user_type', ['agent', 'supervisor'])->orderBy('name')->get(['id', 'name', 'extension']),
            'campaigns' => Campaign::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'teams' => Team::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'lists' => ContactList::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'dispositions' => Disposition::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
            'routes' => TelephonyRoute::query()->whereHas('companies', fn ($query) => $query->whereKey($companyId))->orderBy('name')->get(['id', 'name', 'tech']),
        ];
    }

    public function data(string $report, array $filters): array
    {
        return match ($report) {
            'calls' => $this->callsData($filters),
            'agents' => $this->agentsData($filters),
            'campaigns' => $this->campaignsData($filters),
            'lists' => $this->listsData($filters),
            'qualifications' => $this->qualificationsData($filters),
            'teams' => $this->teamsData($filters),
            'utilization' => $this->utilizationData($filters),
            'callbacks' => $this->callbacksData($filters),
            'exports' => $this->exportsData($filters),
            default => abort(404),
        };
    }

    public function callsQuery(array $filters): Builder
    {
        return Call::query()
            ->where('company_id', $filters['company_id'])
            ->whereBetween('started_at', [$filters['date_from'], $filters['date_to']])
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['campaign_id'], fn (Builder $query, int $id) => $query->where('campaign_id', $id))
            ->when($filters['disposition_id'], fn (Builder $query, int $id) => $query->where('disposition_id', $id))
            ->when($filters['telephony_route_id'], fn (Builder $query, int $id) => $query->where('telephony_route_id', $id))
            ->when($filters['phone'], fn (Builder $query, string $phone) => $query->where('phone', 'like', "%{$phone}%"))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['hangup_party'], fn (Builder $query, string $party) => $query->where('hangup_party', $party))
            ->when($filters['amd_result'], fn (Builder $query, string $amd) => $query->where('amd_result', $amd))
            ->when($filters['direction'], fn (Builder $query, string $direction) => $query->where('direction', $direction))
            ->when($filters['team_id'], fn (Builder $query, int $id) => $this->whereCallBelongsToTeam($query, $filters['company_id'], $id))
            ->when($filters['list_id'], fn (Builder $query, int $id) => $this->whereCallBelongsToList($query, $id));
    }

    public function exportRows(string $report, array $filters): array
    {
        $rows = $this->rowsForExport($report, $filters);

        return match ($report) {
            'calls' => [
                ['Telefone', 'Lead', 'Lista', 'Campanha', 'Agente', 'Ramal', 'Rota', 'Status', 'Tabulação', 'Desligou', 'Início', 'Atendimento', 'Término', 'Duração', 'Espera', 'Gravação'],
                $rows->map(fn (Call $call) => [$call->phone, $this->snapshot($call, 'contact_name', 'Sem lead'), $this->snapshot($call, 'list_name', 'Sem lista'), $this->snapshot($call, 'campaign_name', 'Sem campanha'), $this->snapshot($call, 'agent_name', 'Sem agente'), $this->snapshot($call, 'agent_extension', '-'), $call->telephonyRoute?->name ?? '-', $call->status, $call->disposition_name ?? 'Sem tabulação', $this->hangupLabel($call->hangup_party), $call->started_at?->format('d/m/Y H:i:s'), $call->answered_at?->format('d/m/Y H:i:s'), $call->ended_at?->format('d/m/Y H:i:s'), $this->talkSeconds($call), $this->waitSeconds($call), $call->recording_path ? 'Disponível' : 'Indisponível'])->all(),
            ],
            default => [
                ['Relatório', 'Registros'],
                $rows->map(fn ($row) => [$report, is_object($row) ? ($row->name ?? $row->id) : 'Registro'])->all(),
            ],
        };
    }

    /** @return array{0: array<int, string>, 1: \Illuminate\Support\LazyCollection} */
    public function exportStream(string $report, array $filters): array
    {
        if ($report !== 'calls') {
            [$headings, $rows] = $this->exportRows($report, $filters);
            return [$headings, collect($rows)->lazy()];
        }

        $headings = ['Telefone', 'Lead', 'Lista', 'Campanha', 'Agente', 'Ramal', 'Rota', 'Status', 'Tabulacao', 'Desligou', 'Inicio', 'Atendimento', 'Termino', 'Duracao', 'Espera', 'Gravacao'];
        $rows = $this->callsQuery($filters)
            ->with(['user', 'campaign', 'contact.contactList', 'disposition', 'telephonyRoute'])
            ->lazyById(500)
            ->map(fn (Call $call): array => [$call->phone, $this->snapshot($call, 'contact_name', 'Sem lead'), $this->snapshot($call, 'list_name', 'Sem lista'), $this->snapshot($call, 'campaign_name', 'Sem campanha'), $this->snapshot($call, 'agent_name', 'Sem agente'), $this->snapshot($call, 'agent_extension', '-'), $call->telephonyRoute?->name ?? '-', $call->status, $call->disposition_name ?? 'Sem tabulacao', $this->hangupLabel($call->hangup_party), $call->started_at?->format('d/m/Y H:i:s'), $call->answered_at?->format('d/m/Y H:i:s'), $call->ended_at?->format('d/m/Y H:i:s'), $this->talkSeconds($call), $this->waitSeconds($call), $call->recording_path ? 'Disponivel' : 'Indisponivel']);

        return [$headings, $rows];
    }

    /** Exports always query the full filtered set; UI pages remain capped at 25 rows. */
    private function rowsForExport(string $report, array $filters)
    {
        if ($report === 'calls') {
            return $this->callsQuery($filters)
                ->with(['user', 'campaign', 'contact.contactList', 'disposition', 'telephonyRoute'])
                ->latest('started_at')
                ->get();
        }

        return match ($report) {
            'agents' => User::query()->where('company_id', $filters['company_id'])->whereIn('user_type', ['agent', 'supervisor'])
                ->when($filters['user_id'], fn (Builder $query, int $id) => $query->whereKey($id))->orderBy('name')->get(),
            'campaigns' => Campaign::query()->where('company_id', $filters['company_id'])->when($filters['campaign_id'], fn (Builder $query, int $id) => $query->whereKey($id))->orderBy('name')->get(),
            'lists' => ContactList::query()->where('company_id', $filters['company_id'])->when($filters['list_id'], fn (Builder $query, int $id) => $query->whereKey($id))->orderBy('name')->get(),
            'teams' => Team::query()->where('company_id', $filters['company_id'])->when($filters['team_id'], fn (Builder $query, int $id) => $query->whereKey($id))->orderBy('name')->get(),
            'callbacks' => CallCallback::query()->where('company_id', $filters['company_id'])->whereBetween('scheduled_at', [$filters['date_from'], $filters['date_to']])->orderBy('scheduled_at')->get(),
            'exports' => ReportExport::query()->where('company_id', $filters['company_id'])->latest()->get(),
            default => collect(),
        };
    }

    private function callsData(array $filters): array
    {
        $query = $this->callsQuery($filters);
        $metrics = $this->callMetrics(clone $query);
        $rows = (clone $query)->with(['user', 'campaign', 'contact.contactList', 'disposition', 'telephonyRoute'])->latest('started_at')->paginate(self::PER_PAGE)->withQueryString();

        return compact('rows', 'metrics');
    }

    private function agentsData(array $filters): array
    {
        $calls = $this->callsQuery($filters);
        $rows = User::query()->where('company_id', $filters['company_id'])->whereIn('user_type', ['agent', 'supervisor'])
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->whereKey($id))
            ->when($filters['team_id'], fn (Builder $query, int $id) => $query->whereHas('teams', fn (Builder $teamQuery) => $teamQuery->whereKey($id)))
            ->withCount(['calls as calls_count' => fn (Builder $query) => $this->applyCallFiltersToUser($query, $filters)])
            ->orderByDesc('calls_count')->orderBy('name')->paginate(self::PER_PAGE)->withQueryString();

        $rows->getCollection()->each(function (User $user) use ($filters): void {
            $userCalls = (clone $calls)->where('user_id', $user->id);
            $metrics = $this->callMetrics($userCalls);
            $user->setAttribute('report_metrics', $metrics);
            $user->setAttribute('status_seconds', $this->statusSeconds($user->id, $filters));
        });

        return ['rows' => $rows, 'metrics' => $this->callMetrics($calls)];
    }

    private function campaignsData(array $filters): array
    {
        $rows = Campaign::query()->where('company_id', $filters['company_id'])
            ->when($filters['campaign_id'], fn (Builder $query, int $id) => $query->whereKey($id))
            ->withCount(['calls as calls_count' => fn (Builder $query) => $this->applyCallFilters($query, $filters)])
            ->orderByDesc('calls_count')->orderBy('name')->paginate(self::PER_PAGE)->withQueryString();
        $rows->getCollection()->each(function (Campaign $campaign) use ($filters): void {
            $campaign->setAttribute('report_metrics', $this->callMetrics($this->callsQuery($filters)->where('campaign_id', $campaign->id)));
        });

        return ['rows' => $rows, 'metrics' => $this->callMetrics($this->callsQuery($filters))];
    }

    private function listsData(array $filters): array
    {
        $rows = ContactList::query()->where('company_id', $filters['company_id'])
            ->when($filters['list_id'], fn (Builder $query, int $id) => $query->whereKey($id))
            ->withCount(['contacts', 'contacts as pending_count' => fn (Builder $query) => $query->where('status', 'pending'), 'contacts as answered_count' => fn (Builder $query) => $query->where('status', 'answered'), 'contacts as callback_count' => fn (Builder $query) => $query->where('status', 'callback'), 'contacts as exhausted_count' => fn (Builder $query) => $query->where('status', 'exhausted')])
            ->orderBy('name')->paginate(self::PER_PAGE)->withQueryString();
        $rows->getCollection()->each(function (ContactList $list) use ($filters): void {
            $list->setAttribute('calls_count', $this->callsQuery($filters)->whereHas('contact', fn (Builder $query) => $query->where('contact_list_id', $list->id))->count());
        });

        return ['rows' => $rows, 'metrics' => ['total_lists' => $rows->total(), 'total_contacts' => $rows->sum('contacts_count')]];
    }

    private function qualificationsData(array $filters): array
    {
        $query = $this->callsQuery($filters);
        $total = (clone $query)->count();
        $rows = $query->selectRaw("COALESCE(disposition_code, 'SEM_TABULACAO') as code")
            ->selectRaw("COALESCE(disposition_name, 'Sem tabulação') as name")
            ->selectRaw('COUNT(*) as calls_count')
            ->selectRaw('SUM(CASE WHEN disposition_id IS NOT NULL THEN 1 ELSE 0 END) as tabulated_count')
            ->groupBy('disposition_code', 'disposition_name')->orderByDesc('calls_count')->paginate(self::PER_PAGE)->withQueryString();
        $rows->getCollection()->each(fn ($row) => $row->percentage = $total ? round(($row->calls_count / $total) * 100, 1) : 0);

        return ['rows' => $rows, 'metrics' => ['total_calls' => $total, 'tabulated' => (clone $this->callsQuery($filters))->whereNotNull('disposition_id')->count()]];
    }

    private function teamsData(array $filters): array
    {
        $rows = Team::query()->where('company_id', $filters['company_id'])->where('status', 'active')
            ->when($filters['team_id'], fn (Builder $query, int $id) => $query->whereKey($id))
            ->withCount('members as users_count')->orderBy('name')->paginate(self::PER_PAGE)->withQueryString();
        $rows->getCollection()->each(function (Team $team) use ($filters): void {
            $team->setAttribute('report_metrics', $this->callMetrics($this->callsQuery($filters)->whereExists(fn ($query) => $query->from('team_user')->whereColumn('team_user.user_id', 'calls.user_id')->where('team_user.team_id', $team->id))));
        });

        return ['rows' => $rows, 'metrics' => ['total_teams' => $rows->total()]];
    }

    private function utilizationData(array $filters): array
    {
        $query = $this->callsQuery($filters);
        $total = (clone $query)->count();
        $answered = (clone $query)->whereNotNull('answered_at')->count();
        $delivered = (clone $query)->whereNotNull('user_id')->whereNotNull('answered_at')->count();
        $tabulated = (clone $query)->whereNotNull('disposition_id')->count();
        $converted = (clone $query)->whereHas('disposition', fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('is_success', true)->orWhere('category', 'success')))->count();
        $rows = (clone $query)->selectRaw("COALESCE(end_reason, status, 'sem_status') as name")->selectRaw('COUNT(*) as calls_count')->groupBy('end_reason', 'status')->orderByDesc('calls_count')->paginate(self::PER_PAGE)->withQueryString();

        return ['rows' => $rows, 'metrics' => compact('total', 'answered', 'delivered', 'tabulated', 'converted')];
    }

    private function callbacksData(array $filters): array
    {
        $rows = CallCallback::query()->where('company_id', $filters['company_id'])->whereBetween('scheduled_at', [$filters['date_from'], $filters['date_to']])
            ->when($filters['campaign_id'], fn (Builder $query, int $id) => $query->where('campaign_id', $id))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('assigned_user_id', $id))
            ->when($filters['callback_status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->with(['contact.contactList', 'campaign', 'assignedUser', 'sourceCall.disposition', 'completionCall'])->orderBy('scheduled_at')->paginate(self::PER_PAGE)->withQueryString();
        $pending = CallCallback::query()->where('company_id', $filters['company_id'])->where('status', 'pending');

        return ['rows' => $rows, 'metrics' => ['pending' => (clone $pending)->count(), 'overdue' => (clone $pending)->where('scheduled_at', '<', now())->count(), 'completed' => CallCallback::query()->where('company_id', $filters['company_id'])->where('status', 'completed')->count()]];
    }

    private function exportsData(array $filters): array
    {
        $rows = ReportExport::query()->where('company_id', $filters['company_id'])->with('user')->latest()->paginate(self::PER_PAGE, ['*'], 'exports_page')->withQueryString();
        $schedules = ReportSchedule::query()->where('company_id', $filters['company_id'])->with('user')->latest()->paginate(self::PER_PAGE, ['*'], 'schedules_page')->withQueryString();

        return ['rows' => $rows, 'schedules' => $schedules, 'metrics' => ['queued' => ReportExport::query()->where('company_id', $filters['company_id'])->whereIn('status', ['queued', 'processing'])->count(), 'completed' => ReportExport::query()->where('company_id', $filters['company_id'])->where('status', 'completed')->count()]];
    }

    private function callMetrics(Builder $query): array
    {
        $sqlite = DB::getDriverName() === 'sqlite';
        $talk = $sqlite ? "SUM(CASE WHEN answered_at IS NOT NULL AND ended_at IS NOT NULL THEN MAX(0,(julianday(ended_at)-julianday(answered_at))*86400) ELSE 0 END)" : 'SUM(CASE WHEN answered_at IS NOT NULL AND ended_at IS NOT NULL THEN GREATEST(0,TIMESTAMPDIFF(SECOND,answered_at,ended_at)) ELSE 0 END)';
        $wait = $sqlite ? "SUM(CASE WHEN started_at IS NOT NULL AND answered_at IS NOT NULL THEN MAX(0,(julianday(answered_at)-julianday(started_at))*86400) ELSE 0 END)" : 'SUM(CASE WHEN started_at IS NOT NULL AND answered_at IS NOT NULL THEN GREATEST(0,TIMESTAMPDIFF(SECOND,started_at,answered_at)) ELSE 0 END)';
        $metrics = (clone $query)->selectRaw("COUNT(*) total, SUM(answered_at IS NOT NULL) answered, SUM(ended_at IS NOT NULL) ended, SUM(disposition_id IS NOT NULL) tabulated, {$talk} talk_seconds, {$wait} wait_seconds")->first();
        $total = (int) ($metrics?->total ?? 0); $answered = (int) ($metrics?->answered ?? 0);
        return ['total' => $total, 'answered' => $answered, 'ended' => (int) ($metrics?->ended ?? 0), 'tabulated' => (int) ($metrics?->tabulated ?? 0), 'tma' => $answered ? (int) round($metrics->talk_seconds / $answered) : 0, 'tme' => $total ? (int) round($metrics->wait_seconds / $total) : 0];
    }

    private function statusSeconds(int $userId, array $filters): array
    {
        $from = $filters['date_from'];
        $to = $filters['date_to'];
        $records = AgentStatus::query()->where('company_id', $filters['company_id'])->where('user_id', $userId)->where('started_at', '<=', $to)->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $from))->get();
        $result = ['available' => 0, 'in_call' => 0, 'paused' => 0, 'wrap_up' => 0, 'offline' => 0];
        foreach ($records as $record) {
            $start = $record->started_at->greaterThan($from) ? $record->started_at : $from;
            $end = $record->ended_at && $record->ended_at->lessThan($to) ? $record->ended_at : $to;
            $result[$record->status] = ($result[$record->status] ?? 0) + max(0, $start->diffInSeconds($end));
        }

        return $result;
    }

    private function applyCallFiltersToUser(Builder $query, array $filters): void
    {
        $this->applyCallFilters($query, $filters);
    }

    private function applyCallFilters(Builder $query, array $filters): void
    {
        $query->where('company_id', $filters['company_id'])->whereBetween('started_at', [$filters['date_from'], $filters['date_to']])->when($filters['campaign_id'], fn (Builder $q, int $id) => $q->where('campaign_id', $id))->when($filters['disposition_id'], fn (Builder $q, int $id) => $q->where('disposition_id', $id));
    }

    private function whereCallBelongsToTeam(Builder $query, int $companyId, int $teamId): void
    {
        $query->whereExists(fn ($sub) => $sub->from('team_user')->whereColumn('team_user.user_id', 'calls.user_id')->where('team_user.company_id', $companyId)->where('team_user.team_id', $teamId));
    }

    private function whereCallBelongsToList(Builder $query, int $listId): void
    {
        $query->whereExists(fn ($sub) => $sub->from('contacts')->whereColumn('contacts.id', 'calls.contact_id')->where('contacts.contact_list_id', $listId));
    }

    private function tenantId(mixed $id, string $model, ?int $companyId): ?int
    {
        $id = (int) $id;

        return $id && $model::query()->whereKey($id)->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))->exists() ? $id : null;
    }

    private function talkSeconds(Call $call): int
    {
        return $call->answered_at && $call->ended_at ? max(0, $call->answered_at->diffInSeconds($call->ended_at)) : 0;
    }

    private function waitSeconds(Call $call): int
    {
        return $call->started_at && $call->answered_at ? max(0, $call->started_at->diffInSeconds($call->answered_at)) : 0;
    }

    private function snapshot(Call $call, string $key, string $fallback): string
    {
        return (string) ($call->report_snapshot[$key] ?? $fallback);
    }

    public function secondsLabel(int $seconds): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    public function hangupLabel(?string $party): string
    {
        return ['agent' => 'Agente', 'customer' => 'Cliente', 'carrier' => 'Operadora', 'system' => 'Sistema', 'unknown' => 'Desconhecido'][$party ?? 'unknown'];
    }
}
