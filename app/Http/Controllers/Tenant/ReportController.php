<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;
        $dateFrom = $request->date('date_from')?->startOfDay() ?? now()->subDays(30)->startOfDay();
        $dateTo = $request->date('date_to')?->endOfDay() ?? now()->endOfDay();
        $userId = $request->integer('user_id') ?: null;
        $campaignId = $request->integer('campaign_id') ?: null;

        $callsQuery = Call::query()
            ->where('company_id', $companyId)
            ->whereBetween('started_at', [$dateFrom, $dateTo])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId));

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
            'lists' => ContactList::query()->where('company_id', $companyId)->withCount('contacts')->orderBy('name')->get(),
            'agentPerformance' => $this->agentPerformance($companyId, $dateFrom, $dateTo, $userId, $campaignId),
            'campaignPerformance' => $this->campaignPerformance($companyId, $dateFrom, $dateTo, $userId, $campaignId),
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'user_id' => $userId,
                'campaign_id' => $campaignId,
            ],
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

    private function agentPerformance(int $companyId, $dateFrom, $dateTo, ?int $userId, ?int $campaignId)
    {
        return User::query()
            ->where('company_id', $companyId)
            ->whereIn('user_type', ['agent', 'supervisor'])
            ->withCount(['calls as calls_count' => fn ($query) => $query
                ->where('company_id', $companyId)
                ->whereBetween('started_at', [$dateFrom, $dateTo])
                ->when($campaignId, fn ($callQuery) => $callQuery->where('campaign_id', $campaignId))])
            ->when($userId, fn ($query) => $query->where('id', $userId))
            ->orderByDesc('calls_count')
            ->limit(8)
            ->get();
    }

    private function campaignPerformance(int $companyId, $dateFrom, $dateTo, ?int $userId, ?int $campaignId)
    {
        return Campaign::query()
            ->where('company_id', $companyId)
            ->withCount(['calls as calls_count' => fn ($query) => $query
                ->where('company_id', $companyId)
                ->whereBetween('started_at', [$dateFrom, $dateTo])
                ->when($userId, fn ($callQuery) => $callQuery->where('user_id', $userId))])
            ->when($campaignId, fn ($query) => $query->where('id', $campaignId))
            ->orderByDesc('calls_count')
            ->limit(8)
            ->get();
    }
}
