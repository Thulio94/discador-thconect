<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CompanyTelephonyConfiguration;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TelephonyController extends Controller
{
    public function index(Request $request): View
    {
        return view('tenant.telephony.index', [
            'configuration' => CompanyTelephonyConfiguration::query()
                ->firstOrNew(['company_id' => $request->user()->company_id]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'trunk_label' => ['nullable', 'string', 'max:100'],
            'outbound_route_label' => ['nullable', 'string', 'max:100'],
            'caller_id' => ['nullable', 'string', 'max:40'],
        ]);

        CompanyTelephonyConfiguration::query()->updateOrCreate(
            ['company_id' => $request->user()->company_id],
            $data,
        );

        return redirect()->route('tenant.telephony.index')->with('status', 'Preparacao de telefonia atualizada.');
    }
}
