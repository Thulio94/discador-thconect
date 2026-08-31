<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Dashboard\OperationalDashboardSnapshot;
use App\Services\Supervision\SupervisionSnapshot;
use App\Services\Telephony\AsteriskBridgeClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SupervisionController extends Controller
{
    public function __construct(
        private readonly SupervisionSnapshot $snapshot,
        private readonly OperationalDashboardSnapshot $scope,
    ) {}

    public function index(Request $request): View
    {
        return view(
            'tenant.supervision.index',
            array_merge($this->snapshot->forUser(
                $request->user(),
                $request->string('status')->toString(),
                $request->integer('team_id') ?: null,
            ), ['realtimeCompanyId' => $request->user()->company_id])
        );
    }

    public function poll(Request $request): Response
    {
        $html = view(
            'tenant.supervision.poll',
            $this->snapshot->forUser(
                $request->user(),
                $request->string('status')->toString(),
                $request->integer('team_id') ?: null,
            )
        )->fragments(['supervision-agents', 'supervision-calls']);

        return response($html)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Poll-Interval', '10');
    }

    public function monitor(
        Request $request,
        User $agent,
        AsteriskBridgeClient $bridge,
        AuditLogService $audit,
    ): RedirectResponse {
        $supervisor = $request->user();
        abort_unless($agent->company_id === $supervisor->company_id, 404);
        abort_unless($this->scope->canAccessAgent($supervisor, $agent), 404);
        abort_unless($agent->user_type === 'agent', 422, 'A monitoria opera somente chamadas de agentes.');

        $data = $request->validate([
            'mode' => ['required', Rule::in(['listen', 'whisper', 'barge'])],
        ]);

        $agentStatus = $agent->currentAgentStatus()->value('status') ?? 'offline';

        if (! in_array($agentStatus, ['available', 'paused', 'in_call', 'wrap_up'], true)) {
            return back()->withErrors(['monitoring' => 'O agente precisa estar conectado para iniciar o Modo Spy.']);
        }

        $call = Call::query()
            ->where('company_id', $supervisor->company_id)
            ->where('user_id', $agent->id)
            ->where('status', 'in_progress')
            ->where('channel', 'asterisk_ami')
            ->whereNotNull('telephony_channel')
            ->latest('started_at')
            ->first();

        $route = $supervisor->company?->telephonyRoutes()
            ->with('asteriskNode')
            ->get()
            ->first(fn ($route): bool => $route->isReady() && $route->connection_status === 'online');

        if (! $route) {
            return back()->withErrors(['monitoring' => 'A rota Asterisk desta empresa precisa estar conectada e sincronizada antes de usar a monitoria.']);
        }

        try {
            // Rehydrate the bridge's in-memory route after deploys or restarts
            // before opening the long-lived ChanSpy session.
            $bridge->synchronize($route, $supervisor->company_id);
            $bridge->supervise([
                'company_id' => $supervisor->company_id,
                'route_id' => $route->id,
                'call_id' => $call?->id ?? 0,
                'agent_channel' => $call?->telephony_channel ?? '',
                'agent_extension' => $agent->extension,
                'supervisor_extension' => $supervisor->extension,
                'mode' => $data['mode'],
                'persistent' => true,
            ], $route->asteriskNode);

            $labels = [
                'listen' => 'Ouvir',
                'whisper' => 'Sussurrar',
                'barge' => 'Intervir',
            ];
            $label = $labels[$data['mode']];

            $audit->record(
                event: 'supervision.monitoring_started',
                action: $data['mode'],
                description: sprintf('%s iniciou %s na chamada de %s.', $supervisor->name, strtolower($label), $agent->name),
                subject: $agent,
                companyId: $supervisor->company_id,
                metadata: [
                    'call_id' => $call?->id,
                    'mode' => $data['mode'],
                    'supervisor_extension' => $supervisor->extension,
                    'agent_extension' => $agent->extension,
                    'persistent' => true,
                ],
            );

            return redirect()
                ->route('tenant.supervision.index')
                ->with('status', sprintf('%s continuo iniciado para %s. Atenda o ramal do supervisor para acompanhar as chamadas.', $label, $agent->name));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['monitoring' => 'A ponte Asterisk nao aceitou a monitoria. Verifique a conexao AMI e o template de canal do supervisor.']);
        }
    }
}
