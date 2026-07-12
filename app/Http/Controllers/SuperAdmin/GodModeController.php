<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Audit\AuditLogService;
use App\Support\GodMode\GodModeContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GodModeController extends Controller
{
    public function enter(Request $request, Company $company, GodModeContext $godMode, AuditLogService $audit): RedirectResponse
    {
        $godMode->enter($company);

        $audit->record(
            event: 'god_mode.entered',
            action: 'entered',
            description: 'Super Admin iniciou acompanhamento da empresa.',
            subject: $company,
            companyId: $company->id,
            actor: $request->user(),
            metadata: ['company_name' => $company->name],
        );

        return redirect()
            ->route('dashboard')
            ->with('status', "Modo Deus ativo para {$company->name}.");
    }

    public function leave(Request $request, GodModeContext $godMode, AuditLogService $audit): RedirectResponse
    {
        $company = $godMode->company();

        if ($company !== null) {
            $audit->record(
                event: 'god_mode.left',
                action: 'left',
                description: 'Super Admin encerrou acompanhamento da empresa.',
                subject: $company,
                companyId: $company->id,
                actor: $request->user(),
                metadata: ['company_name' => $company->name],
            );
        }

        $godMode->leave();

        return redirect()
            ->route('super-admin.companies.index')
            ->with('status', 'Modo Deus encerrado.');
    }
}
