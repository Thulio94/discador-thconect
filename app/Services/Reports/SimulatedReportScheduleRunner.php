<?php

namespace App\Services\Reports;

use App\Models\Call;
use App\Models\ReportExport;
use App\Models\ReportSchedule;
use App\Models\User;

final class SimulatedReportScheduleRunner
{
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

        ReportExport::create([
            'company_id' => $schedule->company_id,
            'user_id' => $actor->id,
            'format' => $schedule->format,
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
}
