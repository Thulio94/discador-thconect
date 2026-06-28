<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Services\Audit\AuditLogQuery;
use Illuminate\Contracts\View\View;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request, AuditLogQuery $query): View
    {
        $filters = $request->validated();

        return view('audit.index', [
            'logs' => $query->paginate($filters, null),
            'filters' => $filters,
            'companies' => Company::query()->orderBy('name')->get(),
            'actors' => User::query()->orderBy('name')->get(),
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'isSuperAdmin' => true,
        ]);
    }
}
