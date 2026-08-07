<?php

namespace App\Services\Reports;

use App\Jobs\GenerateReportExport;
use App\Models\Call;
use App\Models\ReportExport;
use App\Models\ReportSchedule;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class SimulatedReportScheduleRunner
{
    public function runDue(?CarbonInterface $moment = null): int
    {
        $moment ??= now();
        $scheduleIds = ReportSchedule::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->pluck('id');

        return $scheduleIds
            ->filter(function (int $scheduleId) use ($moment): bool {
                return DB::transaction(function () use ($scheduleId, $moment): bool {
                    $schedule = ReportSchedule::query()
                        ->with('user')
                        ->lockForUpdate()
                        ->find($scheduleId);

                    if (! $schedule || ! $schedule->user || ! $this->isDue($schedule, $moment)) {
                        return false;
                    }

                    $this->run($schedule, $schedule->user);

                    return true;
                }, 3);
            })
            ->count();
    }

    public function run(ReportSchedule $schedule, User $actor): int
    {
        $filters = $schedule->filters ?? [];
        $periodDays = (int) ($filters['period_days'] ?? 30);
        $dateTo = now()->endOfDay();
        $dateFrom = now()->subDays($periodDays - 1)->startOfDay();

        $query = Call::query()
            ->where('company_id', $schedule->company_id)
            ->whereBetween('started_at', [$dateFrom, $dateTo])
            ->when($filters['user_id'] ?? null, fn ($callQuery, $id) => $callQuery->where('user_id', $id))
            ->when($filters['campaign_id'] ?? null, fn ($callQuery, $id) => $callQuery->where('campaign_id', $id))
            ->when($filters['disposition_id'] ?? null, fn ($callQuery, $id) => $callQuery->where('disposition_id', $id))
            ->when($filters['team_id'] ?? null, fn ($callQuery, $id) => $this->whereCallBelongsToTeam(
                $callQuery,
                $schedule->company_id,
                $id
            ))
            ->when($filters['list_id'] ?? null, fn ($callQuery, $id) => $this->whereCallBelongsToList($callQuery, $id));

        $recordsCount = $query->count();

        $schedule->update([
            'last_run_at' => now(),
            'last_records_count' => $recordsCount,
        ]);

        $export = ReportExport::create([
            'company_id' => $schedule->company_id,
            'user_id' => $actor->id,
            'report_type' => $schedule->report_type ?? 'calls',
            'format' => $schedule->format,
            'status' => 'queued',
            'progress' => 0,
            'filters' => array_filter([
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'user_id' => $filters['user_id'] ?? null,
                'campaign_id' => $filters['campaign_id'] ?? null,
                'disposition_id' => $filters['disposition_id'] ?? null,
                'team_id' => $filters['team_id'] ?? null,
                'list_id' => $filters['list_id'] ?? null,
                'schedule_id' => $schedule->id,
            ], fn ($value) => $value !== null && $value !== ''),
            'records_count' => $recordsCount,
        ]);
        GenerateReportExport::dispatch($export->id);

        return $recordsCount;
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

    private function isDue(ReportSchedule $schedule, CarbonInterface $moment): bool
    {
        if ($schedule->status !== 'active' || $moment->format('H:i') < $schedule->run_time) {
            return false;
        }

        if ($schedule->last_run_at?->isSameDay($moment)) {
            return false;
        }

        return $schedule->frequency === 'daily'
            || ($schedule->frequency === 'weekly' && $schedule->weekday === $moment->dayOfWeek);
    }
}
