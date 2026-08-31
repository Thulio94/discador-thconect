<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Operacao</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Dashboard</h1>
        </div>
    </x-slot>

    <div
        x-data="pollingRegion({ url: @js(route('dashboard.poll')), selectors: ['#operational-dashboard'], intervalSeconds: 10, heartbeat: true })"
        x-init="start(); subscribeToSupervision(@js($realtimeCompanyId), () => poll(true))"
        x-on:visibilitychange.document="handleVisibilityChange()"
    >
    @fragment('operational-dashboard')
    <section id="operational-dashboard">
    @isset($platformOverview)
        <div class="grid gap-4 md:grid-cols-3">
            <x-ui.card title="Empresas ativas" tone="violet"><p class="text-3xl font-bold">{{ $companiesCount }}</p><p class="mt-2 text-sm" style="color:var(--fg-muted);">Empresas em operacao.</p></x-ui.card>
            <x-ui.card title="Agentes disponiveis" tone="cyan"><p class="text-3xl font-bold">{{ $platformOverview['available_agents'] }}</p><p class="mt-2 text-sm" style="color:var(--fg-muted);">Em todas as empresas ativas.</p></x-ui.card>
            <x-ui.card title="Chamadas simultaneas" tone="emerald"><p class="text-3xl font-bold">{{ $platformOverview['active_calls']['current'] }}{{ $platformOverview['active_calls']['limit'] !== null ? ' / '.$platformOverview['active_calls']['limit'] : '' }}</p><p class="mt-2 text-sm" style="color:var(--fg-muted);">Uso consolidado da plataforma.</p></x-ui.card>
        </div>
    @else
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-semibold" style="color: var(--fg-muted);">{{ $scopeLabel }}</p>
            <span class="rounded-full px-3 py-1 text-xs font-semibold" style="background: var(--primary-soft); color: var(--primary);">Atualizacao em tempo real</span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.card title="Chamadas simultaneas" tone="emerald"><p class="text-3xl font-bold">{{ $metrics['active_calls'] }}<span class="text-base font-semibold" style="color:var(--fg-muted);"> / {{ $metrics['effective_capacity'] }}</span></p><p class="mt-2 text-sm" style="color:var(--fg-muted);">Capacidade disponivel agora.</p></x-ui.card>
            <x-ui.card title="Agentes disponiveis" tone="cyan"><p class="text-3xl font-bold">{{ $metrics['available'] }}</p><p class="mt-2 text-sm" style="color:var(--fg-muted);">Prontos para receber chamadas.</p></x-ui.card>
            <x-ui.card title="Em chamada" tone="violet"><p class="text-3xl font-bold">{{ $metrics['in_call'] }}</p><p class="mt-2 text-sm" style="color:var(--fg-muted);">Atendimentos em curso.</p></x-ui.card>
            <x-ui.card title="Em pausa" tone="amber"><p class="text-3xl font-bold">{{ $metrics['paused'] }}</p><p class="mt-2 text-sm" style="color:var(--fg-muted);">Agentes temporariamente indisponiveis.</p></x-ui.card>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <x-ui.card tone="cyan">
                <p class="text-xs font-semibold uppercase" style="color:var(--fg-muted);">Resultado do dia</p>
                <div class="mt-4 flex items-end justify-between gap-3"><div><p class="text-3xl font-bold">{{ $metrics['calls_today'] }}</p><p class="mt-1 text-sm" style="color:var(--fg-muted);">Chamadas hoje</p></div><p class="text-2xl font-bold" style="color:var(--status-success);">{{ $metrics['answer_rate'] }}%</p></div>
                <p class="mt-4 text-sm" style="color:var(--fg-muted);">{{ $metrics['answered_today'] }} atendidas pelo cliente.</p>
            </x-ui.card>
            <x-ui.card tone="violet"><p class="text-xs font-semibold uppercase" style="color:var(--fg-muted);">Capacidade imediata</p><p class="mt-4 text-3xl font-bold">{{ $metrics['effective_capacity'] }}</p><p class="mt-1 text-sm" style="color:var(--fg-muted);">{{ $metrics['available'] }} disponiveis x {{ $metrics['calls_per_available_agent'] }} chamadas por agente.</p></x-ui.card>
            <x-ui.card tone="amber"><p class="text-xs font-semibold uppercase" style="color:var(--fg-muted);">Atencao operacional</p><div class="mt-4 grid gap-3 text-sm"><div class="flex justify-between"><span style="color:var(--fg-muted);">Tabulando</span><strong>{{ $metrics['wrap_up'] }}</strong></div><div class="flex justify-between"><span style="color:var(--fg-muted);">Teto contratado</span><strong>{{ $metrics['company_call_limit'] ?? 'Sem teto' }}</strong></div></div></x-ui.card>
        </div>
    @endisset
    </section>
    @endfragment
    </div>
</x-app-layout>
