<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AgentPause;
use App\Models\AgentStatus;
use App\Models\Call;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user?->company_id && ! $user->is_super_admin) {
            $user->calls()
                ->whereIn('status', ['in_progress', 'wrap_up'])
                ->get()
                ->each(function (Call $call): void {
                    $call->update([
                        'provider_payload' => array_merge($call->provider_payload ?? [], [
                            'agent_console_closed' => true,
                        ]),
                    ]);
                });
            AgentStatus::query()
                ->where('company_id', $user->company_id)
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);
            AgentPause::query()
                ->where('company_id', $user->company_id)
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);
            $user->agentStatuses()->create([
                'company_id' => $user->company_id,
                'status' => 'offline',
                'reason' => 'Saiu do sistema',
                'started_at' => now(),
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
