<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Telefonia</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Preparacao de telefonia</h1>
        </div>
    </x-slot>

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Rota de saida</h2>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Configuracao referencial por empresa.</p>
            </div>
            @can('tenant.telephony.update')
                <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'telephony-configuration')">Editar preparacao</x-ui.button>
            @endcan
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border p-4" style="border-color: var(--border); background: var(--surface-alt);">
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Tronco</p>
                <p class="mt-2 font-semibold">{{ $configuration->trunk_label ?: 'Nao definido' }}</p>
            </div>
            <div class="rounded-lg border p-4" style="border-color: var(--border); background: var(--surface-alt);">
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Rota</p>
                <p class="mt-2 font-semibold">{{ $configuration->outbound_route_label ?: 'Nao definida' }}</p>
            </div>
            <div class="rounded-lg border p-4" style="border-color: var(--border); background: var(--surface-alt);">
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Caller ID</p>
                <p class="mt-2 font-semibold">{{ $configuration->caller_id ?: 'Nao definido' }}</p>
            </div>
        </div>

        <div class="mt-5 rounded-lg border p-4 text-sm" style="border-color: var(--border); background: var(--bg-subtle); color: var(--fg-muted);">
            A conexao com tronco SIP, Asterisk e AMI permanece em breve. O ambiente atual continua operando pelo gateway simulado.
        </div>
    </x-ui.card>

    @can('tenant.telephony.update')
        <x-ui.modal name="telephony-configuration" title="Preparacao de telefonia" max-width="lg">
            <form method="POST" action="{{ route('tenant.telephony.update') }}" class="grid gap-4">
                @csrf
                @method('PUT')
                <label class="grid gap-1 text-sm">
                    <span class="font-semibold">Nome do tronco</span>
                    <input name="trunk_label" value="{{ old('trunk_label', $configuration->trunk_label) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-semibold">Nome da rota de saida</span>
                    <input name="outbound_route_label" value="{{ old('outbound_route_label', $configuration->outbound_route_label) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="font-semibold">Caller ID padrao</span>
                    <input name="caller_id" value="{{ old('caller_id', $configuration->caller_id) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                </label>
                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'telephony-configuration')">Cancelar</x-ui.button>
                    <x-ui.button type="submit">Salvar</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endcan
</x-app-layout>
