<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TelephonyController extends Controller
{
    public function index(Request $request): View
    {
        return view('tenant.telephony.index', [
            'routes' => $request->user()->company?->telephonyRoutes()->orderBy('name')->get() ?? collect(),
        ]);
    }
}
