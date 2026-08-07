<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CompanyWebhook;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;
use App\Services\Capacity\ResourceLimitService;

class IntegrationController extends Controller
{
    public function index(Request $request): View
    {
        return view('tenant.integrations.index', [
            'webhooks' => CompanyWebhook::query()->where('company_id', $request->user()->company_id)->latest()->get(),
        ]);
    }

    public function store(Request $request, ResourceLimitService $limits): RedirectResponse
    {
        $data = $this->validated($request);
        if (($data['status'] ?? null) === 'active') $limits->assertWebhook((int) $request->user()->company_id);
        $data['company_id'] = $request->user()->company_id;
        $data['secret'] = Str::random(48);
        CompanyWebhook::create($data);

        return redirect()->route('tenant.integrations.index')->with('status', 'Webhook criado. Use Testar para validar o endpoint.');
    }

    public function update(Request $request, CompanyWebhook $webhook, ResourceLimitService $limits): RedirectResponse
    {
        $this->authorizeTenant($request, $webhook);
        $data = $this->validated($request);
        if (($data['status'] ?? null) === 'active' && $webhook->status !== 'active') $limits->assertWebhook($webhook->company_id);
        $webhook->update($data);

        return redirect()->route('tenant.integrations.index')->with('status', 'Webhook atualizado.');
    }

    public function destroy(Request $request, CompanyWebhook $webhook): RedirectResponse
    {
        $this->authorizeTenant($request, $webhook);
        $webhook->delete();

        return redirect()->route('tenant.integrations.index')->with('status', 'Webhook removido.');
    }

    public function test(Request $request, CompanyWebhook $webhook): RedirectResponse
    {
        $this->authorizeTenant($request, $webhook);
        $payload = ['event' => 'thconect.webhook.test', 'company_id' => $webhook->company_id, 'sent_at' => now()->toIso8601String()];

        try {
            $response = Http::acceptJson()->asJson()->timeout(8)->withoutRedirecting()->withHeaders([
                'X-THCONECT-Signature' => hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $webhook->secret),
            ])->post($webhook->endpoint, $payload);
            $webhook->update(['last_tested_at' => now(), 'last_response_status' => $response->status(), 'last_error' => $response->successful() ? null : 'Endpoint retornou HTTP '.$response->status()]);

            return redirect()->route('tenant.integrations.index')->with($response->successful() ? 'status' : 'error', $response->successful() ? 'Teste entregue ao endpoint.' : 'O endpoint respondeu com erro.');
        } catch (Throwable $exception) {
            report($exception);
            $webhook->update(['last_tested_at' => now(), 'last_error' => 'Falha de conexao com o endpoint. Consulte os logs tecnicos.']);

            return redirect()->route('tenant.integrations.index')->withErrors(['webhook' => 'Nao foi possivel conectar ao endpoint.']);
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'endpoint' => ['required', 'url', 'max:2048', 'starts_with:https://', function (string $attribute, mixed $value, \Closure $fail): void {
                $parts = parse_url((string) $value);
                $host = strtolower((string) ($parts['host'] ?? ''));

                if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
                    $fail('O endpoint deve usar um dominio HTTPS publico.');

                    return;
                }

                if (isset($parts['user'], $parts['pass']) || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
                    $fail('O endpoint deve usar HTTPS na porta padrao e nao pode conter credenciais na URL.');

                    return;
                }

                if (! $this->hasOnlyPublicAddresses($host)) {
                    $fail('O endpoint nao pode apontar para um IP interno ou reservado.');
                }
            }],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(['call.completed', 'agent.status_changed'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function authorizeTenant(Request $request, CompanyWebhook $webhook): void
    {
        abort_unless($webhook->company_id === $request->user()->company_id, 404);
    }

    private function hasOnlyPublicAddresses(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        $records = array_merge(dns_get_record($host, DNS_A) ?: [], dns_get_record($host, DNS_AAAA) ?: []);
        if ($records === []) {
            return false;
        }

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! $address || ! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
