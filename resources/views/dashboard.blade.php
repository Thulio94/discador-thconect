<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Operacao</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Dashboard</h1>
        </div>
    </x-slot>

    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.card title="Empresas">
            <p class="text-3xl font-bold">--</p>
            <p class="mt-2 text-sm" style="color: var(--fg-muted);">Contratos SaaS ativos.</p>
        </x-ui.card>

        <x-ui.card title="Operadores">
            <p class="text-3xl font-bold">--</p>
            <p class="mt-2 text-sm" style="color: var(--fg-muted);">Usuarios disponiveis para operacao.</p>
        </x-ui.card>

        <x-ui.card title="Chamadas">
            <p class="text-3xl font-bold">--</p>
            <p class="mt-2 text-sm" style="color: var(--fg-muted);">Capacidade sera calculada pelo plano.</p>
        </x-ui.card>
    </div>
</x-app-layout>
