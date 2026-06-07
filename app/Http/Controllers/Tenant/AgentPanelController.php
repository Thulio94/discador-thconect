<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AgentPause;
use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Disposition;
use App\Models\PauseReason;
use App\Services\Dialer\AgentCapacityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentPanelController extends Controller
{
    public function index(Request $request, AgentCapacityService $capacity): View
    {
        $user = $request->user();
        $user->loadMissing(['currentAgentStatus', 'agentPauses', 'calls']);
        $currentCall = $user->calls()
            ->where('status', 'in_progress')
            ->with(['campaign', 'contact.contactList'])
            ->latest()
            ->first();

        return view('tenant.agent.index', [
            'currentStatus' => $user->currentAgentStatus?->status ?? 'offline',
            'currentCall' => $currentCall,
            'contactHistory' => $currentCall?->contact_id
                ? Call::query()
                    ->where('company_id', $user->company_id)
                    ->where('contact_id', $currentCall->contact_id)
                    ->where('id', '!=', $currentCall->id)
                    ->with(['campaign', 'disposition'])
                    ->latest('started_at')
                    ->limit(5)
                    ->get()
                : collect(),
            'recentStatuses' => $user->agentStatuses()->latest()->limit(8)->get(),
            'availableAgents' => $capacity->availableAgentsForCompany($user->company_id),
            'pauseReasons' => PauseReason::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'dispositions' => Disposition::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->orderBy('category')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function status(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['available', 'offline'])],
        ]);

        $user = $request->user();

        $this->closeOpenRecords($user->id, $user->company_id);
        $user->agentStatuses()->create([
            'company_id' => $user->company_id,
            'status' => $data['status'],
            'started_at' => now(),
        ]);

        return redirect()->route('tenant.agent.index')->with('status', 'Status atualizado com sucesso.');
    }

    public function pause(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:120'],
        ]);

        $user = $request->user();

        $this->closeOpenRecords($user->id, $user->company_id);
        $user->agentPauses()->create([
            'company_id' => $user->company_id,
            'reason' => $data['reason'],
            'started_at' => now(),
        ]);
        $user->agentStatuses()->create([
            'company_id' => $user->company_id,
            'status' => 'paused',
            'reason' => $data['reason'],
            'started_at' => now(),
        ]);

        return redirect()->route('tenant.agent.index')->with('status', 'Pausa iniciada.');
    }

    public function storeCall(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:32'],
            'channel' => ['nullable', Rule::in(['simulated', 'manual'])],
        ]);

        $user = $request->user();
        $phone = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));
        $channel = $data['channel'] ?? 'simulated';

        $this->closeOpenRecords($user->id, $user->company_id);
        $user->calls()->where('status', 'in_progress')->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);
        $user->calls()->create([
            'company_id' => $user->company_id,
            'direction' => 'outbound',
            'status' => 'in_progress',
            'channel' => $channel,
            'phone' => $phone ?: null,
            'started_at' => now(),
            'answered_at' => now(),
        ]);
        $user->agentStatuses()->create([
            'company_id' => $user->company_id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);

        return redirect()->route('tenant.agent.index')->with('status', 'Chamada simulada iniciada.');
    }

    public function endCall(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'call_id' => ['required', Rule::exists('calls', 'id')->where('company_id', $request->user()->company_id)],
            'disposition_id' => [
                'required',
                Rule::exists('dispositions', 'id')
                    ->where('company_id', $request->user()->company_id)
                    ->where('status', 'active'),
            ],
            'disposition_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $disposition = Disposition::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->findOrFail($data['disposition_id']);

        Call::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('id', $data['call_id'])
            ->update([
                'status' => 'ended',
                'disposition_id' => $disposition->id,
                'disposition_code' => $disposition->code,
                'disposition_name' => $disposition->name,
                'disposition_notes' => $data['disposition_notes'] ?? null,
                'ended_at' => now(),
            ]);

        $this->closeOpenRecords($user->id, $user->company_id);
        $user->agentStatuses()->create([
            'company_id' => $user->company_id,
            'status' => $user->user_type === 'agent' ? 'available' : 'offline',
            'started_at' => now(),
        ]);

        return redirect()->route('tenant.agent.index')->with('status', 'Chamada simulada encerrada.');
    }

    private function closeOpenRecords(int $userId, int $companyId): void
    {
        AgentStatus::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        AgentPause::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);
    }
}
