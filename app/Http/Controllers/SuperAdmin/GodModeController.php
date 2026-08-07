<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\GodMode\GodModeContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GodModeController extends Controller
{
    public function enter(Request $request, Company $company, GodModeContext $godMode): RedirectResponse
    {
        $godMode->enter($company);

        return redirect()
            ->route('dashboard')
            ->with('status', "Modo Deus ativo para {$company->name}.");
    }

    public function leave(Request $request, GodModeContext $godMode): RedirectResponse
    {
        $godMode->leave();

        return redirect()
            ->route('super-admin.companies.index')
            ->with('status', 'Modo Deus encerrado.');
    }
}
