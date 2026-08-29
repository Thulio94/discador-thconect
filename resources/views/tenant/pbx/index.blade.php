<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Telefonia</p>
            <h1 class="text-2xl font-bold">PABX</h1>
        </div>
    </x-slot>

    <div class="grid gap-4 lg:grid-cols-2">
        @can('tenant.pbx.call')
        <x-ui.card>
            <h2 class="text-lg font-semibold">Nova ligação PABX</h2>
            <form method="POST" action="{{ route('tenant.pbx.calls.store') }}" class="mt-4 flex gap-2">
                @csrf
                <input name="phone" inputmode="tel" autocomplete="tel" placeholder="(31) 99999-0000" class="min-w-0 flex-1 rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface);">
                <x-ui.button type="submit">Ligar</x-ui.button>
            </form>
        </x-ui.card>
        @endcan
        <x-ui.card>
            <h2 class="text-lg font-semibold">Grupos PABX</h2>
            <div class="mt-4 space-y-3">
                @forelse ($teams as $team)
                    <div class="rounded-lg border p-3" style="border-color: var(--border);">
                        <p class="font-semibold">{{ $team->name }}</p>
                        <p class="mt-1 text-sm" style="color: var(--fg-muted);">{{ $team->members->count() }} ramais ativos</p>
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--fg-muted);">Nenhum grupo PABX configurado.</p>
                @endforelse
            </div>
        </x-ui.card>
        <x-ui.card>
            <h2 class="text-lg font-semibold">Ramais PABX</h2>
            <div class="mt-4 space-y-2">
                @forelse ($agents as $agent)
                    <div class="flex items-center justify-between border-b py-2 text-sm" style="border-color: var(--border);">
                        <span>{{ $agent->name }}</span><span style="color: var(--fg-muted);">Ramal {{ $agent->extension }}</span>
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--fg-muted);">Nenhum ramal vinculado a um grupo PABX.</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>

    <x-ui.card class="mt-4">
        <h2 class="text-lg font-semibold">Últimas ligações PABX</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm"><thead><tr class="border-b text-left" style="border-color: var(--border);"><th class="p-2">Ramal</th><th class="p-2">Telefone</th><th class="p-2">Status</th><th class="p-2">Início</th></tr></thead><tbody>
                @forelse ($calls as $call)
                    <tr class="border-b" style="border-color: var(--border);"><td class="p-2">{{ $call->user?->extension ?: '-' }}</td><td class="p-2">{{ $call->phone }}</td><td class="p-2">{{ $call->status }}</td><td class="p-2">{{ $call->started_at?->format('d/m/Y H:i:s') }}</td></tr>
                @empty
                    <tr><td colspan="4" class="p-3" style="color: var(--fg-muted);">Nenhuma ligação PABX registrada.</td></tr>
                @endforelse
            </tbody></table>
        </div>
    </x-ui.card>
</x-app-layout>
