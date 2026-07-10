<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AgentPause;
use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\Disposition;
use App\Models\OperationalParameter;
use App\Models\PauseReason;
use App\Services\Campaigns\ServiceFieldValidator;
use App\Services\Dialer\AgentCapacityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentPanelController extends Controller
{
    public function __construct(
        private readonly ServiceFieldValidator $serviceFieldValidator,
    ) {}

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
            'recentCalls' => $user->calls()
                ->where('company_id', $user->company_id)
                ->with(['campaign', 'disposition'])
                ->latest('started_at')
                ->limit(8)
                ->get(),
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
        $parameters = OperationalParameter::forCompany($user->company_id);

        if ($data['status'] === 'available' && $message = $this->pauseMinimumMessage($user->id, $user->company_id, $parameters)) {
            return back()->withErrors(['status' => $message]);
        }

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
        $parameters = OperationalParameter::forCompany($user->company_id);

        if ($this->isPaused($user->id, $user->company_id) && ! $parameters->allow_manual_calls_while_paused) {
            return back()->withErrors(['phone' => 'Finalize a pausa antes de iniciar chamada manual.']);
        }

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
            'callback_type' => ['nullable', Rule::in(['public', 'private'])],
            'callback_at' => ['nullable', 'date'],
            'service_fields' => ['nullable', 'array'],
            'service_fields.*' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $parameters = OperationalParameter::forCompany($user->company_id);
        $disposition = Disposition::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->findOrFail($data['disposition_id']);

        if ($disposition->category === 'callback' && (blank($data['callback_at'] ?? null) || blank($data['callback_type'] ?? null))) {
            return back()
                ->withErrors([
                    'callback_at' => 'Informe a data e hora do retorno.',
                    'callback_type' => 'Informe se o retorno sera publico ou privado.',
                ])
                ->withInput();
        }

        $callbackAt = filled($data['callback_at'] ?? null) ? $data['callback_at'] : null;

        $call = Call::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('id', $data['call_id'])
            ->with(['campaign', 'contact'])
            ->firstOrFail();

        $schema = $this->serviceFieldsSchema($call);
        $serviceFieldResult = $this->serviceFieldValidator->validate(
            $schema,
            $data['service_fields'] ?? [],
        );

        if (! $serviceFieldResult->passes()) {
            return back()->withErrors($serviceFieldResult->errors)->withInput();
        }

        $call->update([
            'status' => 'ended',
            'disposition_id' => $disposition->id,
            'disposition_code' => $disposition->code,
            'disposition_name' => $disposition->name,
            'disposition_notes' => $data['disposition_notes'] ?? null,
            'callback_type' => $disposition->category === 'callback' ? $data['callback_type'] : null,
            'callback_at' => $disposition->category === 'callback' ? $callbackAt : null,
            'service_fields' => $serviceFieldResult->values ?: null,
            'ended_at' => now(),
        ]);

        if ($call->contact_id) {
            $contactPayload = $this->contactDispositionPayload($call, $disposition, $callbackAt);

            $call->contact()->update($contactPayload);

            if ($call->contact_phone_id) {
                $call->contactPhone()->update($this->contactPhoneDispositionPayload($contactPayload));
            }
        }

        $this->closeOpenRecords($user->id, $user->company_id);
        $user->agentStatuses()->create([
            'company_id' => $user->company_id,
            'status' => $user->user_type === 'agent' && $parameters->auto_available_after_call ? 'available' : 'offline',
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

    private function isPaused(int $userId, int $companyId): bool
    {
        return AgentStatus::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('status', 'paused')
            ->whereNull('ended_at')
            ->exists();
    }

    private function pauseMinimumMessage(int $userId, int $companyId, OperationalParameter $parameters): ?string
    {
        if ($parameters->min_pause_minutes <= 0) {
            return null;
        }

        $pause = AgentPause::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        if (! $pause || $pause->started_at->diffInMinutes(now()) >= $parameters->min_pause_minutes) {
            return null;
        }

        return sprintf(
            'A pausa minima configurada e de %d minutos.',
            $parameters->min_pause_minutes
        );
    }

    private function contactDispositionPayload(Call $call, Disposition $disposition, mixed $callbackAt): array
    {
        $payload = [
            'last_result' => $disposition->code,
            'next_attempt_at' => null,
        ];

        if ($disposition->category === 'callback') {
            return array_merge($payload, [
                'status' => 'callback',
                'next_attempt_at' => $callbackAt,
            ]);
        }

        if ($disposition->is_success || $disposition->category === 'success') {
            return array_merge($payload, ['status' => 'answered']);
        }

        if ($disposition->retry_enabled) {
            $contact = $call->contact;

            $campaignLimit = $call->campaign?->max_attempts_per_contact;
            $attemptLimit = collect([$disposition->max_attempts, $campaignLimit])
                ->filter(fn ($limit): bool => $limit !== null)
                ->min();

            if ($attemptLimit && $contact && $contact->attempts_count >= $attemptLimit) {
                return array_merge($payload, ['status' => 'exhausted']);
            }

            $campaignDelay = $call->campaign?->retry_delay_minutes ?? 0;

            return array_merge($payload, [
                'status' => 'pending',
                'next_attempt_at' => now()->addMinutes(max($disposition->retry_delay_minutes ?? 0, $campaignDelay)),
            ]);
        }

        return array_merge($payload, [
            'status' => match ($disposition->category) {
                'negative', 'unproductive' => 'unproductive',
                default => 'pending',
            },
        ]);
    }

    private function contactPhoneDispositionPayload(array $contactPayload): array
    {
        return collect($contactPayload)
            ->only(['status', 'next_attempt_at'])
            ->all();
    }

    private function serviceFieldsSchema(Call $call): array
    {
        return $call->campaign?->service_fields_schema ?: [
            ['key' => 'documento', 'label' => 'Documento', 'type' => 'text', 'required' => false],
            ['key' => 'protocolo', 'label' => 'Protocolo', 'type' => 'text', 'required' => false],
            ['key' => 'valor', 'label' => 'Valor', 'type' => 'number', 'required' => false],
        ];
    }
}
