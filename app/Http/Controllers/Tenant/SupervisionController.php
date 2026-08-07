<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\User;
use App\Services\Audit\AuditLogService;
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
        private readonly SupervisionSnapshot $snapshot
    ) {}

    public function index(Request $request): View
    {
        return view(
            'tenant.supervision.index',
            $this->snapshot->forCompany(
                $request->user()->company_id,
                $request->string('status')->toString(),
                $request->integer('team_id') ?: null,
            )
        );
    }

    public function poll(Request $request): Response
    {
        $html = view(
            'tenant.supervision.poll',
            $this->snapshot->forCompany(
                $request->user()->company_id,
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
        abort_unless($agent->user_type === 'agent', 422, 'A monitoria opera somente chamadas de agentes.');

        $data = $request->validate([
            'mode' => ['required', Rule::in(['listen', 'whisper', 'barge'])],
        ]);

        $call = Call::query()
            ->where('company_id', $supervisor->company_id)
            ->where('user_id', $agent->id)
            ->where('status', 'in_progress')
            ->where('channel', 'asterisk_ami')
            ->whereNotNull('telephony_channel')
            ->latest('started_at')
            ->first();

        if (! $call) {
            return back()->withErrors(['monitoring' => 'O agente nao possui uma chamada AMI ativa com canal identificado para monitoria.']);
        }

        $monitoringReady = $supervisor->company?->telephonyRoutes()
            ->get()
            ->contains(fn ($route): bool => $route->isReady() && $route->connection_status === 'online');

        if (! $monitoringReady) {
            return back()->withErrors(['monitoring' => 'A rota Asterisk desta empresa precisa estar conectada e sincronizada antes de usar a monitoria.']);
        }

        try {
            $bridge->supervise([
                'company_id' => $supervisor->company_id,
                'call_id' => $call->id,
                'agent_channel' => $call->telephony_channel,
                'supervisor_extension' => $supervisor->extension,
                'mode' => $data['mode'],
            ]);

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
                    'call_id' => $call->id,
                    'mode' => $data['mode'],
                    'supervisor_extension' => $supervisor->extension,
                ],
            );

            return redirect()
                ->route('tenant.supervision.index')
                ->with('status', sprintf('%s solicitado para a chamada de %s.', $label, $agent->name));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['monitoring' => 'A ponte Asterisk nao aceitou a monitoria. Verifique a conexao AMI e o template de canal do supervisor.']);
        }
    }
}
