<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Operacao</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Dashboard</h1>
        </div>
    </x-slot>

    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.card title="Empresas" tone="violet">
            <p class="text-3xl font-bold">{{ $companiesCount ?? '-' }}</p>
            <p class="mt-2 text-sm" style="color: var(--fg-muted);">Contratos SaaS ativos.</p>
        </x-ui.card>

        <x-ui.card title="Operadores" tone="cyan">
            <p class="text-3xl font-bold">{{ $usersCount }}</p>
            <p class="mt-2 text-sm" style="color: var(--fg-muted);">Usuarios disponiveis para operacao.</p>
        </x-ui.card>

        <x-ui.card title="Chamadas" tone="emerald">
            <p class="text-3xl font-bold">{{ $activeCallsCount }} chamadas ativas</p>
            <p class="mt-2 text-sm" style="color: var(--fg-muted);">
                Limite por operador: {{ $license?->max_calls_per_available_agent ?? '-' }}
                <span aria-hidden="true"> · </span>
                Teto da empresa: {{ $license?->max_concurrent_calls ?? 'sem teto' }}
            </p>
        </x-ui.card>
    </div>

    @if ($licenseUsage)
        <div class="mt-4 grid gap-4 lg:grid-cols-[1.1fr_1fr]">
            <x-ui.card tone="violet">
                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Plano contratado</p>
                        <h2 class="mt-1 text-lg font-semibold">Uso atual da empresa</h2>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" style="background: var(--primary-soft); color: var(--fg);">
                        {{ $licenseUsage['status'] === 'exceeded' ? 'Limite excedido' : ($licenseUsage['status'] === 'warning' ? 'Atencao ao limite' : 'Operacao normal') }}
                    </span>
                </div>

                <div class="mt-5 grid gap-4">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="font-semibold">Agentes disponiveis</span>
                        <span class="text-lg font-bold">{{ $licenseUsage['available_agents'] }}</span>
                    </div>
                    @include('partials.license-usage-meter', ['label' => 'Chamadas simultaneas', 'metric' => $licenseUsage['active_calls']])
                </div>
            </x-ui.card>

            <x-ui.card tone="amber">
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Capacidade operacional</p>
                <div class="mt-4 grid gap-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--fg-muted);">Chamadas por agente disponivel</span>
                        <strong>{{ $licenseUsage['calls_per_available_agent'] }}</strong>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--fg-muted);">Capacidade pelos agentes disponiveis</span>
                        <strong>{{ $licenseUsage['agent_call_capacity'] }}</strong>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--fg-muted);">Capacidade efetiva agora</span>
                        <strong>{{ $licenseUsage['effective_call_limit'] }}</strong>
                    </div>
                </div>
            </x-ui.card>
        </div>
    @elseif ($licenseOverview)
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <x-ui.card title="Uso de agentes" tone="cyan">
                <p class="text-3xl font-bold">{{ $licenseOverview['available_agents'] }}</p>
                <p class="mt-2 text-sm" style="color: var(--fg-muted);">Agentes em status disponivel.</p>
            </x-ui.card>
            <x-ui.card title="Uso de chamadas" tone="emerald">
                @include('partials.license-usage-meter', ['label' => 'Chamadas ativas', 'metric' => $licenseOverview['active_calls']])
            </x-ui.card>
        </div>
    @endif
</x-app-layout>
