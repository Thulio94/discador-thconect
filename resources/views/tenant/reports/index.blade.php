<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Analise</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Relatorios</h1>
        </div>
    </x-slot>

    <div class="grid gap-4">
        <x-ui.card>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <form method="GET" action="{{ route('tenant.reports.index') }}" class="grid gap-2 md:grid-cols-4">
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Inicio</span>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Fim</span>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Agente</span>
                        <select name="user_id" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                            <option value="">Todos</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected($filters['user_id'] === $agent->id)>{{ $agent->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Campanha</span>
                        <select name="campaign_id" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                            <option value="">Todas</option>
                            @foreach ($campaigns as $campaign)
                                <option value="{{ $campaign->id }}" @selected($filters['campaign_id'] === $campaign->id)>{{ $campaign->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="md:col-span-4">
                        <x-ui.button type="submit" variant="secondary">Filtrar</x-ui.button>
                    </div>
                </form>

                <a
                    href="{{ route('tenant.reports.export', request()->only(['date_from', 'date_to', 'user_id', 'campaign_id'])) }}"
                    class="th-focus w-fit rounded-lg border px-3 py-2 text-xs font-semibold"
                    style="border-color: var(--border); color: var(--fg);"
                >
                    Exportar CSV
                </a>
            </div>
        </x-ui.card>

        <div class="grid gap-3 md:grid-cols-6">
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Chamadas totais</p><p class="mt-2 text-2xl font-bold">{{ $metrics['total_calls'] }} total</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Em andamento</p><p class="mt-2 text-2xl font-bold">{{ $metrics['in_progress_calls'] }} andamento</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Encerradas</p><p class="mt-2 text-2xl font-bold">{{ $metrics['ended_calls'] }} encerrada</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Agentes disponiveis</p><p class="mt-2 text-2xl font-bold">{{ $metrics['available_agents'] }} disponivel</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Agentes em pausa</p><p class="mt-2 text-2xl font-bold">{{ $metrics['paused_agents'] }} pausa</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Agentes em chamada</p><p class="mt-2 text-2xl font-bold">{{ $metrics['in_call_agents'] }} chamada</p></x-ui.card>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <x-ui.card>
                <h2 class="text-lg font-semibold">Performance por agente</h2>
                <div class="mt-4 grid gap-2">
                    @forelse ($agentPerformance as $agent)
                        <div class="flex items-center justify-between rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                            <span>{{ $agent->name }}</span>
                            <span class="font-semibold">{{ $agent->calls_count }} chamadas</span>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--fg-muted);">Sem dados de agentes.</p>
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card>
                <h2 class="text-lg font-semibold">Performance por campanha</h2>
                <div class="mt-4 grid gap-2">
                    @forelse ($campaignPerformance as $campaign)
                        <div class="flex items-center justify-between rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                            <span>{{ $campaign->name }}</span>
                            <span class="font-semibold">{{ $campaign->calls_count }} chamadas</span>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--fg-muted);">Sem dados de campanhas.</p>
                    @endforelse
                </div>
            </x-ui.card>
        </div>

        <div class="grid gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
            <x-ui.card>
                <h2 class="text-lg font-semibold">Resumo de listas</h2>
                <div class="mt-4 grid gap-2">
                    @forelse ($lists as $list)
                        <div class="flex items-center justify-between rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                            <span>{{ $list->name }}</span>
                            <span class="font-semibold">{{ $list->contacts_count }} contatos</span>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--fg-muted);">Sem listas cadastradas.</p>
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card>
                <h2 class="text-lg font-semibold">Ultimas chamadas</h2>
                <div class="mt-4 grid gap-2">
                    @forelse ($calls as $call)
                        <div class="grid gap-2 rounded-lg border p-3 text-sm md:grid-cols-[140px_minmax(0,1fr)_130px_120px]" style="border-color: var(--border);">
                            <span class="font-semibold">{{ $call->phone ?? 'Sem numero' }}</span>
                            <span>{{ $call->user?->name ?? 'Sem agente' }}</span>
                            <span>{{ $call->campaign?->name ?? 'Sem campanha' }}</span>
                            <span style="color: var(--fg-muted);">{{ $call->status }}</span>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--fg-muted);">Nenhuma chamada encontrada.</p>
                    @endforelse
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
