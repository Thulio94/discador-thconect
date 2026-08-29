<?php

namespace App\Http\Controllers\Tenant;

use App\Contracts\Dialer\DialerGateway;
use App\Http\Controllers\Controller;
use App\Models\AgentPause;
use App\Models\AgentStatus;
use App\Models\AgentCampaignSession;
use App\Models\Call;
use App\Models\CallCallback;
use App\Models\Disposition;
use App\Models\OperationalParameter;
use App\Models\PauseReason;
use App\Models\User;
use App\Models\Campaign;
use App\Services\Campaigns\ServiceFieldValidator;
use App\Services\Dialer\AgentCapacityService;
use App\Services\Dialer\CallWrapUpService;
use App\Services\Dialer\DialerCallRequest;
use App\Services\Dialer\MailingQueueService;
use App\Services\Dialer\PreviewLeadService;
use App\Services\Telephony\AgentWebRtcService;
use App\Services\Telephony\BrazilianPhoneNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
            ->whereIn('status', Call::ACTIVE_STATUSES)
            ->with(['campaign', 'contact.contactList', 'contact.phones', 'contactPhone'])
            ->latest()
            ->first();
        $wrapUpCall = $user->calls()
            ->where('status', 'wrap_up')
            ->with(['campaign', 'contact.contactList', 'contact.phones', 'contactPhone'])
            ->latest('ended_at')
            ->first();
        $callContext = $currentCall ?? $wrapUpCall;
        $eligibleCampaigns = $this->eligibleCampaigns($user);
        $canSelectCampaign = $this->campaignSelectionRequired($user, $eligibleCampaigns);
        $selectedCampaign = $user->currentAgentCampaignSession?->campaign
            ?? ($canSelectCampaign ? null : $eligibleCampaigns->first());

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
            'agentStateSignature' => $this->stateSignature($user),
            'selectedCampaign' => $selectedCampaign,
            'eligibleCampaigns' => $eligibleCampaigns,
            'canSelectCampaign' => $canSelectCampaign,
            'previewReservation' => app(PreviewLeadService::class)->current($user),
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        return response()
            ->json(['signature' => $this->stateSignature($request->user())])
            ->header('Cache-Control', 'no-store, private');
    }

    public function softphoneSession(Request $request, AgentWebRtcService $webRtc): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->status === 'active' && $user->user_type === 'agent' && filled($user->extension), 403);

        try {
            $session = $webRtc->synchronize($user);
        } catch (\Throwable $exception) {
            report($exception);

            return response()
                ->json(['message' => 'Nao foi possivel preparar o audio do agente. Tente novamente ou contate o administrador.'], 503)
                ->header('Cache-Control', 'no-store, private');
        }

        $publicUrl = parse_url((string) config('app.url'));
        $host = $publicUrl['host'] ?? $request->getHost();
        $port = isset($publicUrl['port']) ? ':'.$publicUrl['port'] : '';
        $scheme = ($publicUrl['scheme'] ?? 'http') === 'https' ? 'wss' : 'ws';

        return response()->json([
            'endpoint' => $session['endpoint'],
            'extension' => $session['extension'],
            'uri' => 'sip:'.$session['endpoint'].'@'.$host,
            'password' => $session['password'],
            'websocket_url' => $scheme.'://'.$host.$port.'/sip-ws',
            'ice_servers' => filled(config('services.asterisk_bridge.webrtc_stun_url'))
                ? [['urls' => [(string) config('services.asterisk_bridge.webrtc_stun_url')]]]
                : [],
        ])->header('Cache-Control', 'no-store, private');
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

        if ($data['status'] === 'available' && $this->campaignSelectionRequired($user) && ! $user->currentAgentCampaignSession()->exists()) {
            return back()->withErrors(['status' => 'Escolha a campanha antes de ficar disponível.']);
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

            if ($data['status'] === 'offline') {
                $user->currentAgentCampaignSession()->update(['ended_at' => now()]);
            }

            return true;
        });

        if (! $statusUpdated) {
            return back()->withErrors([
                'status' => 'O limite de usuarios disponiveis desta empresa foi atingido. Peca que outro usuario entre em pausa ou fique offline para liberar uma licenca.',
            ]);
        }

        return redirect()->route('tenant.agent.index')->with('status', 'Status atualizado com sucesso.');
    }

    public function selectCampaign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', Rule::exists('campaigns', 'id')->where('company_id', $request->user()->company_id)],
        ]);
        $user = $request->user();
        $this->wrapUps->expireForUser($user);

        abort_unless($this->campaignSelectionRequired($user), 403);

        if ($user->calls()->whereIn('status', [...Call::ACTIVE_STATUSES, 'wrap_up'])->exists()) {
            return back()->withErrors(['campaign_id' => 'Conclua a chamada e a tabulação antes de trocar de campanha.']);
        }

        $campaign = $this->eligibleCampaigns($user)->firstWhere('id', (int) $data['campaign_id']);
        abort_unless($campaign, 404);

        DB::transaction(function () use ($user, $campaign): void {
            $user->currentAgentCampaignSession()->lockForUpdate()->update(['ended_at' => now()]);
            AgentCampaignSession::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'started_at' => now(),
            ]);
        });

        return redirect()->route('tenant.agent.index')->with('status', 'Campanha selecionada.');
    }

    public function reservePreview(Request $request, PreviewLeadService $preview): RedirectResponse
    {
        $user = $request->user();
        $this->wrapUps->expireForUser($user);
        abort_unless($user->currentAgentStatus()->value('status') === 'available', 403);

        $campaign = $user->currentAgentCampaignSession()->with('campaign')->first()?->campaign
            ?? (! $this->campaignSelectionRequired($user) ? $this->eligibleCampaigns($user)->first() : null);
        if (! $campaign || $campaign->dialing_mode !== 'preview') {
            return back()->withErrors(['campaign' => 'Selecione uma campanha Preview para carregar uma ficha.']);
        }

        $preview->reserve($user, $campaign);

        return redirect()->route('tenant.agent.index')->with('status', 'Ficha Preview reservada para atendimento.');
    }

    public function startPreviewCall(Request $request, DialerGateway $gateway, PreviewLeadService $preview, MailingQueueService $queue): RedirectResponse
    {
        $user = $request->user();
        $this->wrapUps->expireForUser($user);
        abort_unless($user->currentAgentStatus()->value('status') === 'available', 403);

        $reservation = $preview->current($user);
        if (! $reservation) {
            return back()->withErrors(['campaign' => 'Carregue uma ficha Preview antes de iniciar a ligação.']);
        }

        $state = $reservation->phoneState()->with(['contact', 'contactPhone'])->firstOrFail();
        $call = $gateway->originate(new DialerCallRequest(
            companyId: $user->company_id,
            userId: $user->id,
            campaignId: $reservation->campaign_id,
            contactId: $state->contact_id,
            contactPhoneId: $state->contact_phone_id,
            phone: $state->contactPhone->phone,
            channel: 'preview',
        ));
        $queue->markAttempt($state->contact, now()->addMinutes(30), 'dialing', $state->contactPhone, $reservation->campaign);

        if (! in_array($call->status, Call::ACTIVE_STATUSES, true)) {
            return back()->withErrors(['campaign' => 'A chamada Preview não foi iniciada. Verifique a rota e tente novamente.']);
        }

        $this->closeOpenRecords($user->id, $user->company_id);
        $user->agentStatuses()->create([
            'company_id' => $user->company_id,
            'status' => 'in_call',
            'started_at' => now(),
        ]);

        return redirect()->route('tenant.agent.index')->with('status', 'Chamada Preview iniciada.');
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

    public function storeCall(Request $request, DialerGateway $gateway, BrazilianPhoneNormalizer $phones): RedirectResponse
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

        $rawPhone = (string) ($data['phone'] ?? '');
        $phone = $phones->normalize($rawPhone);
        $channel = $data['channel'] ?? 'simulated';
        $parameters = OperationalParameter::forCompany($user->company_id);

        if ($channel === 'manual' && $phone === null) {
            return back()
                ->withErrors(['phone' => 'Informe um telefone valido com DDD e numero.'])
                ->withInput();
        }

        $phone ??= preg_replace('/\D+/', '', $rawPhone) ?: '';

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
        $user->calls()->whereIn('status', Call::ACTIVE_STATUSES)->update([
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

        if (! in_array($call->status, Call::ACTIVE_STATUSES, true)) {
            $this->wrapUps->releaseAgent($user, $call);

            return redirect()
                ->route('tenant.agent.index')
                ->withErrors(['phone' => 'A chamada nao foi iniciada. Verifique a rota de telefonia e tente novamente.']);
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
            ->whereIn('status', Call::ACTIVE_STATUSES)
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
            ->whereIn('status', [...Call::ACTIVE_STATUSES, 'wrap_up'])
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

                if ($call->campaign && $call->contactPhone) {
                    app(MailingQueueService::class)->recordDisposition(
                        $call->campaign,
                        $call->contactPhone,
                        $disposition->id,
                        $this->campaignPhoneStateForDisposition($contactPayload),
                        $contactPayload['next_attempt_at'] ?? null,
                    );
                }
            }
        }

        $this->wrapUps->releaseAgent($user, $call);
        app(PreviewLeadService::class)->releaseForCompletion($call->id);

        return redirect()->route('tenant.agent.index')->with('status', 'Chamada simulada encerrada.');
    }

    private function eligibleCampaigns(User $user)
    {
        $teamIds = $user->teams()
            ->wherePivot('membership_type', 'agent')
            ->where('teams.status', 'active')
            ->pluck('teams.id');

        return Campaign::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->whereIn('id', function ($query) use ($teamIds): void {
                $query->select('campaign_team.campaign_id')
                    ->from('campaign_team')
                    ->whereIn('campaign_team.team_id', $teamIds);
            })
            ->orderByDesc('priority')
            ->orderBy('last_dispatched_at')
            ->get();
    }

    private function campaignSelectionRequired(User $user, mixed $campaigns = null): bool
    {
        $campaigns ??= $this->eligibleCampaigns($user);
        if ($campaigns->count() < 2) {
            return false;
        }

        return $user->teams()
            ->wherePivot('membership_type', 'agent')
            ->where('teams.allow_agent_campaign_selection', true)
            ->exists();
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

    private function campaignPhoneStateForDisposition(array $contactPayload): string
    {
        return match ($contactPayload['status'] ?? null) {
            'pending' => 'pending',
            'exhausted' => 'failed',
            'unproductive' => 'no_answer',
            default => 'delivered',
        };
    }

    private function serviceFieldsSchema(Call $call): array
    {
        return $call->campaign?->service_fields_schema ?: [
            ['key' => 'documento', 'label' => 'Documento', 'type' => 'text', 'required' => false],
            ['key' => 'protocolo', 'label' => 'Protocolo', 'type' => 'text', 'required' => false],
            ['key' => 'valor', 'label' => 'Valor', 'type' => 'number', 'required' => false],
        ];
    }

    private function stateSignature(User $user): string
    {
        $status = $user->agentStatuses()->latest('id')->first(['id', 'status', 'updated_at']);
        $call = $user->calls()
            ->whereIn('status', [...Call::ACTIVE_STATUSES, 'wrap_up'])
            ->latest('id')
            ->first(['id', 'status', 'updated_at']);
        $latestCall = $user->calls()->latest('updated_at')->first(['id', 'updated_at']);
        $latestCallback = CallCallback::query()
            ->where('company_id', $user->company_id)
            ->where(function ($query) use ($user): void {
                $query->where('type', 'public')->orWhere('assigned_user_id', $user->id);
            })
            ->latest('updated_at')
            ->first(['id', 'updated_at']);

        return hash('sha256', json_encode([
            'status' => [$status?->id, $status?->status, $status?->updated_at?->getTimestamp()],
            'active_call' => [$call?->id, $call?->status, $call?->updated_at?->getTimestamp()],
            'latest_call' => [$latestCall?->id, $latestCall?->updated_at?->getTimestamp()],
            'callback' => [$latestCallback?->id, $latestCallback?->updated_at?->getTimestamp()],
        ], JSON_THROW_ON_ERROR));
    }
}
