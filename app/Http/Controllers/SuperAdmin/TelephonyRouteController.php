<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\TelephonyRoute;
use App\Models\AsteriskNode;
use App\Services\Telephony\AsteriskBridgeClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Closure;
use Throwable;

class TelephonyRouteController extends Controller
{
    public function index(): View
    {
        return view('super-admin.telephony-routes.index', [
            'routes' => TelephonyRoute::query()
                ->with('asteriskNode')->withCount('companies')
                ->orderBy('name')
                ->get(),
            'asteriskNodes' => AsteriskNode::query()->orderBy('priority')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        TelephonyRoute::query()->create($this->validated($request));

        return redirect()->route('super-admin.telephony-routes.index')
            ->with('status', 'Rota de telefonia criada. Vincule-a a uma empresa antes de ativar chamadas reais.');
    }

    public function update(Request $request, TelephonyRoute $telephonyRoute): RedirectResponse
    {
        $data = $this->validated($request, $telephonyRoute);

        if (blank($data['sip_password'] ?? null)) {
            unset($data['sip_password']);
        }

        $telephonyRoute->update($data);

        return redirect()->route('super-admin.telephony-routes.index')
            ->with('status', 'Rota de telefonia atualizada.');
    }

    public function destroy(TelephonyRoute $telephonyRoute): RedirectResponse
    {
        if ($telephonyRoute->companies()->exists()) {
            return back()->withErrors(['telephony_route' => 'Remova esta rota das empresas vinculadas antes de exclui-la.']);
        }

        $telephonyRoute->delete();

        return redirect()->route('super-admin.telephony-routes.index')
            ->with('status', 'Rota de telefonia excluida.');
    }

    public function test(TelephonyRoute $telephonyRoute, AsteriskBridgeClient $bridge): RedirectResponse
    {
        if (! $telephonyRoute->isReady()) {
            return back()->withErrors(['telephony_route' => 'Habilite a rota e preencha o dominio. Para autenticacao por registro, informe tambem usuario e senha SIP.']);
        }

        try {
            $bridge->testRoute($telephonyRoute);

            $telephonyRoute->companies()
                ->select('companies.id')
                ->each(fn ($company) => $bridge->synchronize($telephonyRoute, $company->id));

            $telephonyRoute->update([
                'connection_status' => 'online',
                'last_checked_at' => now(),
                'last_error' => null,
            ]);

            return redirect()->route('super-admin.telephony-routes.index')
                ->with('status', 'AMI conectado, PJSIP recarregado e endpoint SIP respondeu ao teste sem originar chamada.');
        } catch (Throwable $exception) {
            report($exception);
            $telephonyRoute->update([
                'connection_status' => 'error',
                'last_checked_at' => now(),
                'last_error' => 'Falha de conexao. Consulte os logs tecnicos para diagnostico.',
            ]);

            return back()->withErrors(['telephony_route' => 'Nao foi possivel validar a rota no Asterisk. Revise o dominio, as credenciais e a rede.']);
        }
    }

    private function validated(Request $request, ?TelephonyRoute $route = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('telephony_routes', 'name')->ignore($route)],
            'provider' => ['nullable', 'string', 'max:100'],
            'tech' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'host' => ['required', 'string', 'max:253', 'regex:/^[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?$/'],
            'sip_port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'from_domain' => ['nullable', 'string', 'max:253', 'regex:/^[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?$/'],
            'outbound_proxy' => ['nullable', 'string', 'max:512', 'regex:/^sips?:[A-Za-z0-9._~:@?&=+;,%\-\[\]]+$/'],
            'transport' => ['required', Rule::in(['udp', 'tcp', 'tls'])],
            'authentication_mode' => ['required', Rule::in(['ip', 'registration', 'digest'])],
            'sip_username' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.+@\-]+$/', Rule::requiredIf(fn (): bool => in_array($request->input('authentication_mode'), ['registration', 'digest'], true))],
            'sip_password' => ['nullable', 'string', 'max:255', $this->safePjsipValue(), Rule::requiredIf(fn (): bool => in_array($request->input('authentication_mode'), ['registration', 'digest'], true) && (! $route || blank($route->sip_password)))],
            'from_user' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.+@\-]+$/'],
            'contact_user' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.+@\-]+$/'],
            'caller_id' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+]+$/'],
            'dial_prefix' => ['nullable', 'string', 'max:40', 'regex:/^[0-9]*$/', Rule::requiredIf(fn (): bool => blank($request->input('tech')))],
            'dialplan_context' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.\-]+$/'],
            'dialplan_extension' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_.+*#\-]+$/'],
            'codecs' => ['required', 'string', 'max:100', $this->codecList()],
            'dtmf_mode' => ['sometimes', Rule::in(['rfc4733', 'info', 'inband', 'auto'])],
            'caller_id_header' => ['sometimes', Rule::in(['none', 'pai', 'rpid', 'both'])],
            'qualify_frequency' => ['sometimes', 'integer', 'min:0', 'max:300'],
            'registration_expiration' => ['sometimes', 'integer', 'min:60', 'max:86400'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'asterisk_node_id' => ['sometimes', Rule::exists('asterisk_nodes', 'id')],
            'max_concurrent_channels' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'enabled' => ['boolean'],
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $data['tech'] = filled($data['tech'] ?? null) ? $data['tech'] : null;
        $data['asterisk_node_id'] ??= AsteriskNode::query()->orderBy('priority')->value('id');
        $data['connection_status'] = $data['enabled'] ? 'pending_test' : 'not_configured';
        $data['last_error'] = null;

        return $data;
    }

    private function safePjsipValue(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && preg_match('/[\x00-\x1F\x7F]/', $value)) {
                $fail('O campo contém caracteres de controle inválidos.');
            }
        };
    }

    private function codecList(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $allowed = ['alaw', 'ulaw', 'g722', 'gsm', 'opus', 'g729'];
            $codecs = array_filter(array_map('trim', explode(',', strtolower((string) $value))));

            if ($codecs === [] || count($codecs) !== count(array_unique($codecs)) || array_diff($codecs, $allowed) !== []) {
                $fail('Informe codecs únicos separados por vírgula: alaw, ulaw, g722, gsm, opus ou g729.');
            }
        };
    }
}
