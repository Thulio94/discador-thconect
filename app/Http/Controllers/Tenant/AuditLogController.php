<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogQuery;
use Illuminate\Contracts\View\View;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request, AuditLogQuery $query): View
    {
        $companyId = (int) $request->user()->company_id;
        $filters = $request->validated();

        return view('audit.index', [
            'logs' => $query->paginate($filters, $companyId),
            'filters' => $filters,
            'companies' => collect(),
            'actors' => User::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'events' => AuditLog::query()
                ->where('company_id', $companyId)
                ->distinct()
                ->orderBy('event')
                ->pluck('event'),
            'isSuperAdmin' => false,
            'purgeSelection' => null,
            'purgeEstimate' => null,
        ]);
    }
}
