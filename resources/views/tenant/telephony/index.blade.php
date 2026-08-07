<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Telefonia</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Rotas liberadas</h1>
        </div>
    </x-slot>

    <x-ui.card>
        <h2 class="text-lg font-semibold">Rotas disponíveis para campanhas</h2>
        <p class="mt-1 text-sm" style="color: var(--fg-muted);">Escolha estas rotas somente no cadastro ou na edição das campanhas. Configurações técnicas e credenciais são exclusivas do SuperAdmin.</p>

        <div class="mt-5 grid gap-3 md:grid-cols-2">
            @forelse ($routes as $route)
                <div class="rounded-lg border p-4" style="border-color: var(--border); background: var(--surface-alt);">
                    <p class="font-semibold">{{ $route->name }}</p>
                    <p class="mt-1 text-sm" style="color: var(--fg-muted);">Tech {{ $route->tech }} · {{ $route->provider ?: 'Operadora não informada' }}</p>
                </div>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhuma rota foi liberada para esta empresa.</p>
            @endforelse
        </div>
    </x-ui.card>
</x-app-layout>
