<?php

namespace App\Http\Controllers\Tenant;

use App\Contracts\Dialer\DialerGateway;
use App\Http\Controllers\Controller;
use App\Models\AgentPause;
use App\Models\AgentStatus;
use App\Models\Call;
use App\Models\CallCallback;
use App\Models\Disposition;
use App\Models\OperationalParameter;
use App\Models\PauseReason;
use App\Models\User;
use App\Services\Campaigns\ServiceFieldValidator;
use App\Services\Dialer\AgentCapacityService;
use App\Services\Dialer\CallWrapUpService;
use App\Services\Dialer\DialerCallRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AgentPanelController extends Controller
{
    public function __construct(
        private readonly ServiceFieldValidator $serviceFieldValidator,
        private readonly CallWrapUpService $wrapUps,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->wrapUps->expireForUser($user);
        $user->loadMissing('currentAgentStatus');
        $activeStatus = $user->currentAgentStatus;
        $currentCall = $user->calls()
            ->where('status', 'in_progress')
            ->with(['campaign', 'contact.contactList', 'contact.phones', 'contactPhone'])
            ->latest()
            ->first();
        $wrapUpCall = $user->calls()
            ->where('status', 'wrap_up')
            ->with(['campaign', 'contact.contactList', 'contact.phones', 'contactPhone'])
            ->latest('ended_at')
            ->first();
        $callContext = $currentCall ?? $wrapUpCall;

        return view('tenant.agent.index', [
            'currentStatus' => $activeStatus?->status ?? 'offline',
            'statusStartedAt' => $activeStatus?->started_at,
            'statusElapsedSeconds' => $activeStatus?->started_at
                ? max(0, (int) $activeStatus->started_at->diffInSeconds(now()))
                : 0,
            'currentCall' => $currentCall,
            'wrapUpCall' => $wrapUpCall,
            'wrapUpSecondsRemaining' => $wrapUpCall?->wrap_up_expires_at
                ? max(0, now()->diffInSeconds($wrapUpCall->wrap_up_expires_at, false))
                : 0,
            'contactHistory' => $callContext?->contact_id
                ? Call::query()
                    ->where('company_id', $user->company_id)
                    ->where('contact_id', $callContext->contact_id)
                    ->where('id', '!=', $callContext->id)
                    ->with(['campaign', 'disposition'])
                    ->latest('started_at')
                    ->limit(5)
                    ->get()
                : collect(),
            'recentCalls' => $user->calls()
                ->where('company_id', $user->company_id)
                ->with([
                    'campaign',
                    'disposition',
                    'contact.contactList',
                    'contact.phones',
                    'contactPhone',
                ])
                ->latest('started_at')
                ->limit(25)
                ->get(),
            'scheduledCallbacks' => CallCallback::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'pending')
                ->where(function ($query) use ($user): void {
                    $query->where('type', 'public')
                        ->orWhere('assigned_user_id', $user->id);
                })
                ->with(['campaign', 'contact.contactList', 'contact.phones'])
                ->orderBy('scheduled_at')
                ->limit(6)
                ->get(),
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

    public function status(Request $request, AgentCapacityService $capacity): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['available', 'offline'])],
        ]);

        $user = $request->user();
        $this->wrapUps->expireForUser($user);

        if ($this->wrapUps->hasPendingForUser($user)) {
            return back()->withErrors(['status' => 'Conclua a tabulacao pendente antes de mudar seu status.']);
        }

        $parameters = OperationalParameter::forCompany($user->company_id);

        if ($data['status'] === 'available' && $message = $this->pauseMinimumMessage($user->id, $user->company_id, $parameters)) {
            return back()->withErrors(['status' => $message]);
        }

        $statusUpdated = DB::transaction(function () use ($capacity, $data, $user): bool {
            if ($data['status'] === 'available' && ! $capacity->canBecomeAvailable($user)) {
                return false;
            }

            $this->closeOpenRecords($user->id, $user->company_id);
            $user->agentStatuses()->create([
                'company_id' => $user->company_id,
                'status' => $data['status'],
                'started_at' => now(),
            ]);

            return true;
        });

        if (! $statusUpdated) {
            return back()->withErrors([
                'status' => 'O limite de usuarios disponiveis desta empresa foi atingido. Peca que outro usuario entre em pausa ou fique offline para liberar uma licenca.',
            ]);
        }

        return redirect()->route('tenant.agent.index')->with('status', 'Status atualizado com sucesso.');
    }

    public function pause(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $this->wrapUps->expireForUser($user);

        if ($this->wrapUps->hasPendingForUser($user)) {
            return back()->withErrors(['reason' => 'Conclua a tabulacao pendente antes de iniciar uma pausa.']);
        }

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

    public function storeCall(Request $request, DialerGateway $gateway): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:32'],
            'channel' => ['nullable', Rule::in(['simulated', 'manual'])],
        ]);

        $user = $request->user();
        $this->wrapUps->expireForUser($user);

        $currentStatus = $user->currentAgentStatus()->value('status') ?? 'offline';

        if (! in_array($currentStatus, ['available', 'paused'], true)) {
            return back()->withErrors([
                'phone' => 'Para iniciar uma chamada, fique disponivel ou inicie uma pausa no painel do agente.',
            ]);
        }

        if ($this->wrapUps->hasPendingForUser($user)) {
            return back()->withErrors(['phone' => 'Conclua a tabulacao pendente antes de iniciar outra chamada.']);
        }

        $phone = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));
        $channel = $data['channel'] ?? 'simulated';
        $parameters = OperationalParameter::forCompany($user->company_id);

        if ($channel === 'manual' && ! preg_match('/^(?:55)?\d{10,11}$/', $phone)) {
            return back()
                ->withErrors(['phone' => 'Informe um telefone valido com DDD e numero.'])
                ->withInput();
        }

        if ($this->isPaused($user->id, $user->company_id) && ! $parameters->allow_manual_calls_while_paused) {
            return back()->withErrors(['phone' => 'Finalize a pausa antes de iniciar chamada manual.']);
        }

        $pauseToResume = $channel === 'manual'
            ? AgentPause::query()
                ->where('company_id', $user->company_id)
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->latest('started_at')
                ->first()
            : null;

        $this->closeOpenRecords($user->id, $user->company_id);
        $user->calls()->where('status', 'in_progress')->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);
        $call = $gateway->originate(new DialerCallRequest(
            companyId: $user->company_id,
            userId: $user->id,
            campaignId: null,
            contactId: null,
            contactPhoneId: null,
            phone: $phone,
            channel: $channel,
        ));

        if ($pauseToResume) {
            $call->update([
                'provider_payload' => array_merge($call->provider_payload ?? [], [
                    'resume_pause' => ['reason' => $pauseToResume->reason],
                ]),
            ]);
        }
        $user->agentStatuses()->create([
            'company_id' => $user->company_id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);

        return redirect()->route('tenant.agent.index')->with('status', 'Chamada iniciada.');
    }

    public function hangupCall(Request $request, DialerGateway $gateway): RedirectResponse
    {
        $data = $request->validate([
            'call_id' => ['required', Rule::exists('calls', 'id')->where('company_id', $request->user()->company_id)],
        ]);

        $user = $request->user();
        $this->wrapUps->expireForUser($user);

        if ($this->wrapUps->hasPendingForUser($user)) {
            return back()->withErrors(['call_id' => 'Ja existe uma tabulacao pendente para este agente.']);
        }

        $call = Call::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('id', $data['call_id'])
            ->where('status', 'in_progress')
            ->with('campaign')
            ->firstOrFail();

        $gateway->hangup($call);

        $seconds = max(10, (int) ($call->campaign?->wrap_up_seconds ?? 60));
        $call->update([
            'status' => 'wrap_up',
            'wrap_up_expires_at' => now()->addSeconds($seconds),
        ]);

        $this->closeOpenRecords($user->id, $user->company_id);
        $user->agentStatuses()->create([
            'company_id' => $user->company_id,
            'status' => 'wrap_up',
            'started_at' => now(),
        ]);

        return redirect()
            ->route('tenant.agent.index')
            ->with('status', sprintf('Chamada encerrada. Escolha uma tabulacao em ate %d segundo(s).', $seconds));
    }

    public function endCall(Request $request, DialerGateway $gateway): RedirectResponse
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
        $this->wrapUps->expireForUser($user);
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
            ->whereIn('status', ['in_progress', 'wrap_up'])
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

        $gateway->hangup($call);

        $call->update([
            'disposition_id' => $disposition->id,
            'disposition_code' => $disposition->code,
            'disposition_name' => $disposition->name,
            'disposition_notes' => $data['disposition_notes'] ?? null,
            'callback_type' => $disposition->category === 'callback' ? $data['callback_type'] : null,
            'callback_at' => $disposition->category === 'callback' ? $callbackAt : null,
            'service_fields' => $serviceFieldResult->values ?: null,
            'status' => 'ended',
            'ended_at' => now(),
            'hangup_party' => $call->hangup_party ?? 'agent',
            'wrap_up_expires_at' => null,
        ]);

        if ($disposition->category === 'callback') {
            CallCallback::updateOrCreate(['source_call_id' => $call->id], [
                'company_id' => $user->company_id,
                'contact_id' => $call->contact_id,
                'contact_phone_id' => $call->contact_phone_id,
                'campaign_id' => $call->campaign_id,
                'assigned_user_id' => $data['callback_type'] === 'private' ? $user->id : null,
                'phone' => $call->phone,
                'type' => $data['callback_type'],
                'status' => 'pending',
                'scheduled_at' => $callbackAt,
            ]);
        }

        if ($call->contact_id) {
            $contactPayload = $this->contactDispositionPayload($call, $disposition, $callbackAt);

            $call->contact()->update($contactPayload);

            if ($call->contact_phone_id) {
                $call->contactPhone()->update($this->contactPhoneDispositionPayload($contactPayload));
            }
        }

        $this->wrapUps->releaseAgent($user, $call);

        return redirect()->route('tenant.agent.index')->with('status', 'Chamada simulada encerrada.');
    }

    public function leave(Request $request)
    {
        // Ignore stale page beacons: only the current agent console explicitly marks a real exit.
        if (! $request->boolean('agent_console_leave')) {
            return response()->noContent();
        }

        $user = $request->user();
        $this->markCurrentCallsAsConsoleClosed($user);
        $this->closeOpenRecords($user->id, $user->company_id);

        $user->agentStatuses()->create([
            'company_id' => $user->company_id,
            'status' => 'offline',
            'reason' => 'Saiu do painel do agente',
            'started_at' => now(),
        ]);

        return response()->noContent();
    }

    private function markCurrentCallsAsConsoleClosed(User $user): void
    {
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
