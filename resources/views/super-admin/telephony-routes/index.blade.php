<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Super admin</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Rotas de telefonia</h1>
        </div>
    </x-slot>

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Rotas centralizadas</h2>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Somente o SuperAdmin configura operadora, tech e credenciais. Cada empresa recebe uma rota selecionada no seu cadastro.</p>
            </div>
            <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'telephony-route-create')">Nova rota</x-ui.button>
        </div>

        <div class="mt-5 grid gap-3">
            @forelse ($routes as $route)
                @php($status = ['online' => 'Conectada', 'pending_test' => 'Aguardando teste', 'error' => 'Com falha', 'not_configured' => 'Desativada'][$route->connection_status] ?? 'Nao configurada')
                <div class="rounded-lg border p-4" style="border-color: var(--border); background: var(--surface);">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold">{{ $route->name }}</h3>
                                <span class="rounded-full px-2 py-1 text-xs font-semibold" style="background: {{ $route->connection_status === 'online' ? 'color-mix(in srgb, var(--status-success) 14%, var(--surface))' : ($route->connection_status === 'error' ? 'color-mix(in srgb, var(--status-danger) 12%, var(--surface))' : 'var(--surface-muted)') }}; color: var(--fg);">{{ $status }}</span>
                                @if (! $route->enabled)<span class="rounded-full px-2 py-1 text-xs font-semibold" style="background: var(--surface-muted); color: var(--fg-muted);">Desativada</span>@endif
                            </div>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">{{ $route->provider ?: 'Operadora nao informada' }} · {{ filled($route->tech) ? 'Tech '.$route->tech : 'Sem Tech' }} · {{ $route->host }} · {{ $route->companies_count }} empresa{{ $route->companies_count === 1 ? '' : 's' }} vinculada{{ $route->companies_count === 1 ? '' : 's' }}</p>
                            <p class="mt-2 text-xs" style="color: var(--fg-muted);">Discagem: {{ filled($route->tech) ? ((filled($route->dial_prefix) ? $route->dial_prefix : $route->tech).' + 55 + DDD + telefone') : ($route->dial_prefix.' + DDD + telefone') }} · {{ strtoupper($route->transport) }} · {{ strtoupper($route->authentication_mode === 'ip' ? 'liberacao por IP' : 'registro SIP') }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <x-ui.action icon="edit" label="Editar rota" x-data x-on:click="$dispatch('open-modal', 'telephony-route-edit-{{ $route->id }}')" />
                            <x-ui.action icon="status" label="Testar Asterisk" x-data x-on:click="$dispatch('open-modal', 'telephony-route-test-{{ $route->id }}')" />
                            @if ($route->companies_count === 0)
                                <x-ui.action icon="delete" variant="danger" label="Excluir rota" x-data x-on:click="$dispatch('open-modal', 'telephony-route-delete-{{ $route->id }}')" />
                            @endif
                        </div>
                    </div>
                    @if ($route->last_checked_at)
                        <p class="mt-3 text-xs" style="color: {{ $route->connection_status === 'error' ? 'var(--status-danger)' : 'var(--fg-muted)' }};">Ultima verificacao: {{ $route->last_checked_at->format('d/m/Y H:i') }}{{ $route->last_error ? ' · '.$route->last_error : '' }}</p>
                    @endif
                </div>

                <x-ui.modal name="telephony-route-edit-{{ $route->id }}" title="Editar rota de telefonia" max-width="2xl">
                    <form method="POST" action="{{ route('super-admin.telephony-routes.update', $route) }}">
                        @csrf
                        @method('PUT')
                        @include('super-admin.telephony-routes._form', ['route' => $route])
                        <div class="mt-5 flex justify-end"><x-ui.button type="submit">Salvar rota</x-ui.button></div>
                    </form>
                </x-ui.modal>
                <x-ui.modal name="telephony-route-test-{{ $route->id }}" title="Testar rota" max-width="lg">
                    <p class="text-sm" style="color: var(--fg-muted);">Este teste valida o Asterisk e prepara a rota SIP. Ele nao realiza uma chamada para numero externo.</p>
                    <form method="POST" action="{{ route('super-admin.telephony-routes.test', $route) }}" class="mt-5 flex justify-end gap-2">
                        @csrf
                        <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'telephony-route-test-{{ $route->id }}')">Cancelar</x-ui.button>
                        <x-ui.button type="submit" :disabled="! $route->isReady()">Testar rota</x-ui.button>
                    </form>
                </x-ui.modal>
                <x-ui.modal name="telephony-route-delete-{{ $route->id }}" title="Excluir rota" max-width="lg">
                    <form method="POST" action="{{ route('super-admin.telephony-routes.destroy', $route) }}">
                        @csrf
                        @method('DELETE')
                        <p class="text-sm" style="color: var(--fg-muted);">A rota sera removida. Esta acao so esta disponivel enquanto nenhuma empresa estiver vinculada.</p>
                        <div class="mt-5 flex justify-end"><x-ui.button type="submit" variant="danger">Excluir rota</x-ui.button></div>
                    </form>
                </x-ui.modal>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhuma rota cadastrada.</p>
            @endforelse
        </div>
    </x-ui.card>

    <x-ui.modal name="telephony-route-create" title="Nova rota de telefonia" max-width="2xl">
        <form method="POST" action="{{ route('super-admin.telephony-routes.store') }}">
            @csrf
            @include('super-admin.telephony-routes._form')
            <div class="mt-5 flex justify-end"><x-ui.button type="submit">Criar rota</x-ui.button></div>
        </form>
    </x-ui.modal>
</x-app-layout>
