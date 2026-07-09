<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use App\Services\Licensing\LicenseUsageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(Request $request, LicenseUsageService $usage): View
    {
        $user = $request->user();

        if (! $user->is_super_admin) {
            Gate::authorize('tenant.dashboard.read');
        }

        return view('dashboard', [
            'companiesCount' => $user->is_super_admin ? Company::query()->count() : null,
            'usersCount' => $user->company_id ? User::query()->where('company_id', $user->company_id)->count() : User::query()->count(),
            'teamsCount' => $user->company_id ? Team::query()->where('company_id', $user->company_id)->count() : Team::query()->count(),
            'license' => $user->company?->license,
            'licenseUsage' => $user->company ? $usage->forCompany($user->company) : null,
            'licenseOverview' => $user->is_super_admin ? $usage->overview() : null,
            'activeCallsCount' => Call::query()
                ->when($user->company_id, fn ($query) => $query->where('company_id', $user->company_id))
                ->where('status', 'in_progress')
                ->count(),
        ]);
    }
}
