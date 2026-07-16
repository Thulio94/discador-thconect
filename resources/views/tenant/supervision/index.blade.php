<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Operacao</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Supervisao</h1>
        </div>
    </x-slot>

    <div
        class="grid gap-4"
        x-data="pollingRegion({
            url: @js(route('tenant.supervision.poll', array_filter([
                'status' => $selectedStatus,
                'team_id' => $selectedTeamId,
            ])), JSON_UNESCAPED_SLASHES),
            selectors: ['#supervision-metrics', '#supervision-agents'],
            intervalSeconds: 10,
        })"
        x-init="start()"
        x-on:visibilitychange.document="handleVisibilityChange()"
    >
        @include('tenant.supervision._metrics')

        <x-ui.card>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Agentes em operacao</h2>
                    <p class="mt-1 text-sm" style="color: var(--fg-muted);">Acompanhamento em tempo real de operadores, ramais, equipes e chamadas ativas.</p>
                </div>

                <form method="GET" action="{{ route('tenant.supervision.index') }}" class="grid gap-2 sm:grid-cols-[180px_220px_auto]">
                    <select name="status" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                        <option value="">Todos status</option>
                        @foreach (['available' => 'Disponivel', 'paused' => 'Pausa', 'in_call' => 'Em chamada', 'wrap_up' => 'Em tabulacao', 'offline' => 'Offline'] as $value => $label)
                            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="team_id" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                        <option value="">Todas equipes</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}" @selected($selectedTeamId === $team->id)>{{ $team->name }}</option>
                        @endforeach
                    </select>

                    <x-ui.button type="submit" variant="secondary">Filtrar</x-ui.button>
                </form>
            </div>

            <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 border-y py-3 text-xs font-semibold" style="border-color: var(--border); color: var(--fg-muted);">
                @foreach ([
                    ['Disponivel', 'var(--status-success)'],
                    ['Em pausa', 'var(--status-warning)'],
                    ['Em chamada', 'var(--status-info)'],
                    ['Em tabulacao', 'var(--status-purple)'],
                    ['Offline', 'var(--status-neutral)'],
                ] as [$label, $color])
                    <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full" style="background: {{ $color }};"></span>{{ $label }}</span>
                @endforeach
                <span class="ml-auto text-[11px] font-medium">Atualizacao automatica a cada 10 segundos</span>
            </div>

            @include('tenant.supervision._agents')
        </x-ui.card>
    </div>
</x-app-layout>
