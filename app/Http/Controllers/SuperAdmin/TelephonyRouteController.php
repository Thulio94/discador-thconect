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
                ->with('status', 'Asterisk AMI conectado e rota SIP preparada. O provedor ainda pode exigir a liberacao do IP publico.');
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
            'tech' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'host' => ['required', 'string', 'max:255'],
            'from_domain' => ['nullable', 'string', 'max:255'],
            'transport' => ['required', Rule::in(['udp', 'tcp'])],
            'authentication_mode' => ['required', Rule::in(['ip', 'registration'])],
            'sip_username' => ['nullable', 'string', 'max:255', 'required_if:authentication_mode,registration'],
            'sip_password' => ['nullable', 'string', 'max:255', 'required_if:authentication_mode,registration'],
            'caller_id' => ['nullable', 'string', 'max:40'],
            'dial_prefix' => ['nullable', 'string', 'max:40', 'regex:/^[0-9]*$/'],
            'dialplan_context' => ['required', 'string', 'max:100'],
            'dialplan_extension' => ['required', 'string', 'max:80'],
            'codecs' => ['required', 'string', 'max:100'],
            'asterisk_node_id' => ['sometimes', Rule::exists('asterisk_nodes', 'id')],
            'max_concurrent_channels' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'enabled' => ['boolean'],
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $data['asterisk_node_id'] ??= AsteriskNode::query()->orderBy('priority')->value('id');
        $data['connection_status'] = $data['enabled'] ? 'pending_test' : 'not_configured';
        $data['last_error'] = null;

        return $data;
    }
}
