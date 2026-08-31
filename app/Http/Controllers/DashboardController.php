<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Call;
use App\Services\Dashboard\OperationalDashboardSnapshot;
use App\Services\Licensing\LicenseUsageService;
use App\Support\GodMode\GodModeContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(Request $request, LicenseUsageService $usage, GodModeContext $godMode, OperationalDashboardSnapshot $snapshot): View
    {
        $user = $request->user();
        $isGodMode = $godMode->isActiveFor($user);

        if ($user->user_type === 'agent' && ! $isGodMode) {
            return $this->agentDashboard($request);
        }

        if (! $user->is_super_admin || $isGodMode) {
            Gate::authorize('tenant.dashboard.read');
        }

        return view('dashboard', $this->dashboardData($user, $isGodMode, $usage, $snapshot));
    }

    public function poll(Request $request, LicenseUsageService $usage, GodModeContext $godMode, OperationalDashboardSnapshot $snapshot): Response
    {
        $user = $request->user();
        $isGodMode = $godMode->isActiveFor($user);

        if (! $user->is_super_admin || $isGodMode) {
            Gate::authorize('tenant.dashboard.read');
        }
        abort_if($user->user_type === 'agent' && ! $isGodMode, 404);

        return response(view('dashboard', $this->dashboardData($user, $isGodMode, $usage, $snapshot))->fragment('operational-dashboard'))
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Poll-Interval', '10');
    }

    private function dashboardData($user, bool $isGodMode, LicenseUsageService $usage, OperationalDashboardSnapshot $snapshot): array
    {
        if ($user->is_super_admin && ! $isGodMode) {
            return [
                'platformOverview' => $usage->overview(),
                'companiesCount' => Company::query()->where('status', '!=', 'inactive')->count(),
                'realtimeCompanyId' => null,
            ];
        }

        return [...$snapshot->forUser($user), 'realtimeCompanyId' => $user->company_id];
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
