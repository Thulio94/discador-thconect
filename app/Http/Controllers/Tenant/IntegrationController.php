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

class IntegrationController extends Controller
{
    public function index(Request $request): View
    {
        return view('tenant.integrations.index', [
            'webhooks' => CompanyWebhook::query()->where('company_id', $request->user()->company_id)->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['company_id'] = $request->user()->company_id;
        $data['secret'] = Str::random(48);
        CompanyWebhook::create($data);

        return redirect()->route('tenant.integrations.index')->with('status', 'Webhook criado. Use Testar para validar o endpoint.');
    }

    public function update(Request $request, CompanyWebhook $webhook): RedirectResponse
    {
        $this->authorizeTenant($request, $webhook);
        $webhook->update($this->validated($request));

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
            $response = Http::acceptJson()->asJson()->timeout(8)->withHeaders([
                'X-THCONECT-Signature' => hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $webhook->secret),
            ])->post($webhook->endpoint, $payload);
            $webhook->update(['last_tested_at' => now(), 'last_response_status' => $response->status(), 'last_error' => $response->successful() ? null : 'Endpoint retornou HTTP '.$response->status()]);

            return redirect()->route('tenant.integrations.index')->with($response->successful() ? 'status' : 'error', $response->successful() ? 'Teste entregue ao endpoint.' : 'O endpoint respondeu com erro.');
        } catch (Throwable $exception) {
            report($exception);
            $webhook->update(['last_tested_at' => now(), 'last_error' => mb_substr($exception->getMessage(), 0, 1000)]);

            return redirect()->route('tenant.integrations.index')->withErrors(['webhook' => 'Nao foi possivel conectar ao endpoint.']);
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'endpoint' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(['call.completed', 'agent.status_changed'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function authorizeTenant(Request $request, CompanyWebhook $webhook): void
    {
        abort_unless($webhook->company_id === $request->user()->company_id, 404);
    }
}
