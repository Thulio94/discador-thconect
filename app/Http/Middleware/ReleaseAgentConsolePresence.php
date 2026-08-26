<?php

namespace App\Http\Middleware;

use App\Models\AgentPause;
use App\Models\AgentStatus;
use App\Models\Call;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReleaseAgentConsolePresence
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user
            || $user->is_super_admin
            || $request->routeIs('tenant.agent.*')
            || $request->routeIs('tenant.notifications.poll', 'tenant.supervision.poll')
        ) {
            return $next($request);
        }

        $status = $user->currentAgentStatus;

        if (! $status || ! in_array($status->status, ['available', 'paused'], true)) {
            return $next($request);
        }

        $hasActiveCall = Call::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereIn('status', Call::ACTIVE_STATUSES)
            ->exists();

        if ($hasActiveCall) {
            return $next($request);
        }

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
            'reason' => 'Saiu do painel do agente',
            'started_at' => now(),
        ]);

        $request->session()->flash('agent_console_released', true);

        return $next($request);
    }
}
