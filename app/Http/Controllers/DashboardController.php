<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'companiesCount' => $user->is_super_admin ? Company::query()->count() : null,
            'usersCount' => $user->company_id ? User::query()->where('company_id', $user->company_id)->count() : User::query()->count(),
            'teamsCount' => $user->company_id ? Team::query()->where('company_id', $user->company_id)->count() : Team::query()->count(),
            'license' => $user->company?->license,
        ]);
    }
}
