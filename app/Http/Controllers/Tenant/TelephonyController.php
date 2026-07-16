<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CompanyTelephonyConfiguration;
use App\Services\Telephony\AsteriskBridgeClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class TelephonyController extends Controller
{
    public function index(Request $request): View
    {
        return view('tenant.telephony.index', [
            'configuration' => CompanyTelephonyConfiguration::query()
                ->firstOrNew(['company_id' => $request->user()->company_id]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $configuration = CompanyTelephonyConfiguration::query()->firstOrNew([
            'company_id' => $request->user()->company_id,
        ]);

        $data = $request->validate([
            'driver' => ['required', 'in:simulated,asterisk_ami'],
            'trunk_label' => ['nullable', 'string', 'max:100'],
            'outbound_route_label' => ['nullable', 'string', 'max:100'],
            'caller_id' => ['nullable', 'string', 'max:40'],
            'ami_host' => ['nullable', 'string', 'max:255', 'required_if:driver,asterisk_ami'],
            'ami_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ami_username' => ['nullable', 'string', 'max:255'],
            'ami_secret' => ['nullable', 'string', 'max:255'],
            'outbound_channel_template' => ['nullable', 'string', 'max:255', 'required_if:driver,asterisk_ami'],
            'supervisor_channel_template' => ['nullable', 'string', 'max:255'],
            'dialplan_context' => ['nullable', 'string', 'max:100', 'required_if:driver,asterisk_ami'],
            'dialplan_extension' => ['nullable', 'string', 'max:80'],
            'bridge_enabled' => ['nullable', 'boolean'],
        ]);

        if ($data['driver'] === 'asterisk_ami' && blank($data['ami_username'] ?? null) && blank($configuration->ami_username)) {
            return back()->withErrors(['ami_username' => 'Informe o usuario AMI para habilitar a rota Asterisk.'])->withInput();
        }

        if ($data['driver'] === 'asterisk_ami' && blank($data['ami_secret'] ?? null) && blank($configuration->ami_secret)) {
            return back()->withErrors(['ami_secret' => 'Informe o segredo AMI para habilitar a rota Asterisk.'])->withInput();
        }

        $data['company_id'] = $request->user()->company_id;
        $data['ami_port'] = $data['ami_port'] ?? 5038;
        $data['dialplan_extension'] = $data['dialplan_extension'] ?? 's';
        $data['supervisor_channel_template'] = $data['supervisor_channel_template'] ?? 'PJSIP/{extension}';

        if ($data['driver'] === 'asterisk_ami'
            && ! str_contains($data['supervisor_channel_template'], '{extension}')) {
            return back()->withErrors([
                'supervisor_channel_template' => 'O template do supervisor deve conter {extension} para receber o ramal do usuario.',
            ])->withInput();
        }

        $data['bridge_enabled'] = $request->boolean('bridge_enabled');
        $data['connection_status'] = $data['driver'] === 'asterisk_ami' && $data['bridge_enabled'] ? 'pending_test' : 'not_configured';
        $data['last_error'] = null;

        if (blank($data['ami_username'] ?? null)) {
            unset($data['ami_username']);
        }

        if (blank($data['ami_secret'] ?? null)) {
            unset($data['ami_secret']);
        }

        $configuration->fill($data);
        $configuration->save();

        return redirect()->route('tenant.telephony.index')->with('status', 'Configuracao de telefonia atualizada. Teste a conexao antes de iniciar chamadas reais.');
    }

    public function test(Request $request, AsteriskBridgeClient $bridge): RedirectResponse
    {
        $configuration = CompanyTelephonyConfiguration::query()
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        if (! $configuration->isReadyForAsterisk()) {
            return back()->withErrors(['telephony' => 'Preencha a rota Asterisk, habilite a ponte e configure os segredos internos antes do teste.']);
        }

        try {
            $bridge->test($configuration);
            $bridge->synchronize($configuration);

            $configuration->update([
                'connection_status' => 'online',
                'last_checked_at' => now(),
                'last_error' => null,
            ]);

            return redirect()->route('tenant.telephony.index')->with('status', 'AMI conectado e rota sincronizada com a ponte de telefonia.');
        } catch (Throwable $exception) {
            report($exception);

            $configuration->update([
                'connection_status' => 'error',
                'last_checked_at' => now(),
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);

            return back()->withErrors(['telephony' => 'Nao foi possivel conectar ao AMI. Revise host, porta, usuario, segredo e acesso de rede.']);
        }
    }
}
