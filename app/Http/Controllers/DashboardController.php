<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use App\Services\Licensing\LicenseUsageService;
use App\Support\GodMode\GodModeContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(Request $request, LicenseUsageService $usage, GodModeContext $godMode): View
    {
        $user = $request->user();
        $isGodMode = $godMode->isActiveFor($user);

        if (! $user->is_super_admin || $isGodMode) {
            Gate::authorize('tenant.dashboard.read');
        }

        if ($user->user_type === 'agent' && ! $isGodMode) {
            return $this->agentDashboard($request);
        }

        return view('dashboard', [
            'companiesCount' => $user->is_super_admin && ! $isGodMode ? Company::query()->where('status', '!=', 'inactive')->count() : null,
            'usersCount' => $user->company_id ? User::query()->where('company_id', $user->company_id)->count() : User::query()->count(),
            'teamsCount' => $user->company_id ? Team::query()->where('company_id', $user->company_id)->count() : Team::query()->count(),
            'license' => $user->company?->license,
            'licenseUsage' => $user->company ? $usage->forCompany($user->company) : null,
            'licenseOverview' => $user->is_super_admin && ! $isGodMode ? $usage->overview() : null,
            'activeCallsCount' => Call::query()
                ->when($user->company_id, fn ($query) => $query->where('company_id', $user->company_id))
                ->whereIn('status', Call::ACTIVE_STATUSES)
                ->count(),
        ]);
    }

    private function agentDashboard(Request $request): View
    {
        $user = $request->user();
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);
        $date = $data['date'] ?? now()->toDateString();
        $phone = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));

        $calls = Call::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('started_at', $date)
            ->when($phone !== '', fn ($query) => $query->where('phone', 'like', "%{$phone}%"));

        $recentCalls = (clone $calls)
            ->with([
                'campaign',
                'disposition',
                'contact.contactList',
                'contact.phones',
                'contactPhone',
            ])
            ->latest('started_at')
            ->paginate(25)
            ->withQueryString();

        $activeCall = $user->calls()
            ->where('company_id', $user->company_id)
            ->whereIn('status', Call::ACTIVE_STATUSES)
            ->latest('started_at')
            ->first();

        return view('tenant.agent.dashboard', [
            'historyDate' => $date,
            'phoneSearch' => $phone,
            'recentCalls' => $recentCalls,
            'activeCall' => $activeCall,
            'callsTodayCount' => (clone $calls)->count(),
            'answeredCallsCount' => (clone $calls)->whereNotNull('answered_at')->count(),
            'completedCallsCount' => (clone $calls)->where('status', 'ended')->count(),
        ]);
    }
}
