<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Configuracoes</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Integracoes</h1>
        </div>
    </x-slot>

    <x-ui.card>
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div><h2 class="text-lg font-semibold">Webhooks externos</h2><p class="mt-1 text-sm" style="color: var(--fg-muted);">Configure endpoints, eventos previstos e valide a conectividade com uma assinatura HMAC.</p></div>
            @can('tenant.integrations.create')<x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'webhook-create')">Novo webhook</x-ui.button>@endcan
        </div>
        <div class="mt-5 grid gap-3">
            @forelse ($webhooks as $webhook)
                <div class="grid gap-3 rounded-lg border p-4 text-sm lg:grid-cols-[minmax(0,1fr)_180px_auto] lg:items-center" style="border-color: var(--border);">
                    <div><p class="font-semibold">{{ $webhook->name }}</p><p class="mt-1 break-all text-xs" style="color: var(--fg-muted);">{{ $webhook->endpoint }}</p><p class="mt-2 text-xs" style="color: var(--fg-muted);">{{ implode(', ', $webhook->events) }} | {{ $webhook->last_tested_at ? 'Ultimo teste: '.$webhook->last_tested_at->format('d/m/Y H:i') : 'Ainda nao testado' }}</p></div>
                    <div><p class="font-semibold">{{ $webhook->status === 'active' ? 'Ativo' : 'Inativo' }}</p><p class="mt-1 text-xs" style="color: {{ $webhook->last_error ? 'var(--status-danger)' : 'var(--fg-muted)' }};">{{ $webhook->last_error ?: ($webhook->last_response_status ? 'HTTP '.$webhook->last_response_status : 'Sem retorno registrado') }}</p></div>
                    <div class="flex flex-wrap justify-end gap-2">
                        @can('tenant.integrations.update')<form method="POST" action="{{ route('tenant.integrations.test', $webhook) }}">@csrf<x-ui.button type="submit" variant="secondary">Testar</x-ui.button></form><x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'webhook-edit-{{ $webhook->id }}')">Editar</x-ui.button>@endcan
                        @can('tenant.integrations.delete')<form method="POST" action="{{ route('tenant.integrations.destroy', $webhook) }}">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger">Excluir</x-ui.button></form>@endcan
                    </div>
                </div>
            @empty <p class="rounded-lg border p-4 text-sm" style="border-color: var(--border); color: var(--fg-muted);">Nenhum webhook configurado.</p> @endforelse
        </div>
    </x-ui.card>

    @can('tenant.integrations.create')<x-ui.modal name="webhook-create" title="Novo webhook" max-width="lg">@include('tenant.integrations._form', ['action' => route('tenant.integrations.store'), 'webhook' => null])</x-ui.modal>@endcan
    @foreach ($webhooks as $webhook) @can('tenant.integrations.update')<x-ui.modal name="webhook-edit-{{ $webhook->id }}" title="Editar webhook" max-width="lg">@include('tenant.integrations._form', ['action' => route('tenant.integrations.update', $webhook), 'webhook' => $webhook, 'method' => 'PUT'])</x-ui.modal>@endcan @endforeach
</x-app-layout>
