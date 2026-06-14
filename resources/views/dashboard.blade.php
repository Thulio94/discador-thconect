<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Operacao</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Dashboard</h1>
        </div>
    </x-slot>

    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.card title="Empresas">
            <p class="text-3xl font-bold">{{ $companiesCount ?? '-' }}</p>
            <p class="mt-2 text-sm" style="color: var(--fg-muted);">Contratos SaaS ativos.</p>
        </x-ui.card>

        <x-ui.card title="Operadores">
            <p class="text-3xl font-bold">{{ $usersCount }}</p>
            <p class="mt-2 text-sm" style="color: var(--fg-muted);">Usuarios disponiveis para operacao.</p>
        </x-ui.card>

        <x-ui.card title="Chamadas">
            <p class="text-3xl font-bold">{{ $activeCallsCount }} chamadas ativas</p>
            <p class="mt-2 text-sm" style="color: var(--fg-muted);">
                Limite por operador: {{ $license?->max_calls_per_available_agent ?? '-' }}
                <span aria-hidden="true"> · </span>
                Teto da empresa: {{ $license?->max_concurrent_calls ?? 'sem teto' }}
            </p>
        </x-ui.card>
    </div>
</x-app-layout>
