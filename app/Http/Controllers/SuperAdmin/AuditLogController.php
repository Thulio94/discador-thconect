<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogIndexRequest;
use App\Http\Requests\SuperAdmin\PurgeAuditLogsRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Services\Audit\AuditLogQuery;
use App\Services\Audit\AuditPurgeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AuditLogController extends Controller
{
    public function index(
        AuditLogIndexRequest $request,
        AuditLogQuery $query,
        AuditPurgeService $purge,
    ): View {
        $filters = $request->validated();
        $purgeSelection = $this->purgeSelection($filters);

        return view('audit.index', [
            'logs' => $query->paginate($filters, null),
            'filters' => $filters,
            'companies' => Company::query()->orderBy('name')->get(),
            'actors' => User::query()->orderBy('name')->get(),
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'isSuperAdmin' => true,
            'purgeSelection' => $purgeSelection,
            'purgeEstimate' => $purgeSelection ? $purge->estimate($purgeSelection) : null,
        ]);
    }

    public function purge(PurgeAuditLogsRequest $request, AuditPurgeService $purge): RedirectResponse
    {
        $count = $purge->purge($request->validated(), $request->user());

        if ($count === 0) {
            return back()
                ->withErrors(['purge' => 'Nenhum registro encontrado no periodo informado.'])
                ->withInput();
        }

        return redirect()
            ->route('super-admin.audit-logs.index')
            ->with('status', "{$count} registros de auditoria removidos.");
    }

    private function purgeSelection(array $filters): ?array
    {
        if (blank($filters['date_from'] ?? null) || blank($filters['date_to'] ?? null)) {
            return null;
        }

        if (filter_var($filters['global_only'] ?? false, FILTER_VALIDATE_BOOL)) {
            return [
                'scope' => 'global',
                'company_id' => null,
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'reason' => '',
            ];
        }

        if (filled($filters['company_id'] ?? null)) {
            return [
                'scope' => 'company',
                'company_id' => (int) $filters['company_id'],
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'reason' => '',
            ];
        }

        return null;
    }
}
