@php
    $agentMetrics = [
        ['key' => 'available', 'label' => 'Disponiveis', 'color' => 'var(--status-success)'],
        ['key' => 'in_call', 'label' => 'Em chamada', 'color' => 'var(--status-info)'],
        ['key' => 'paused', 'label' => 'Em pausa', 'color' => 'var(--status-warning)'],
        ['key' => 'offline', 'label' => 'Offline', 'color' => 'var(--status-neutral)'],
        ['key' => 'wrap_up', 'label' => 'Tabulando', 'color' => 'var(--status-purple)'],
    ];
    $totalAgents = collect($agentMetrics)->sum(fn ($metric) => $metrics[$metric['key']]);
    $sortingAgents = $agents->map(function ($agent): array {
        [$minutes, $seconds] = array_pad(explode(':', (string) $agent->calls_average_label, 2), 2, 0);

        return [
            'id' => $agent->id,
            'name' => mb_strtolower($agent->name),
            'status' => match ($agent->supervision_status) {
                'available' => 'Disponivel',
                'paused' => 'Em pausa',
                'in_call' => 'Em chamada',
                'wrap_up' => 'Tabulando',
                default => 'Offline',
            },
            'calls' => (int) $agent->calls_today_count,
            'average' => ((int) $minutes * 60) + (int) $seconds,
        ];
    })->values();
@endphp

<section
    id="supervision-agents"
    class="supervision-panel"
    x-data="{
        search: '',
        showOffline: {{ $selectedStatus === 'offline' ? 'true' : 'localStorage.getItem(\'thconect-supervision-offline\') === \'1\'' }},
        agents: @js($sortingAgents),
        sortBy: '',
        sortDirection: 'asc',
        toggleSort(column) {
            this.sortDirection = this.sortBy === column && this.sortDirection === 'asc' ? 'desc' : 'asc';
            this.sortBy = column;
        },
        sortOrder(row) {
            if (!this.sortBy) return 0;
            const rows = [...this.agents];
            rows.sort((left, right) => {
                const first = left[this.sortBy];
                const second = right[this.sortBy];
                const comparison = typeof first === 'number' ? first - second : String(first).localeCompare(String(second), 'pt-BR');
                return this.sortDirection === 'asc' ? comparison : -comparison;
            });
            return rows.findIndex(candidate => candidate.id === row.id);
        },
        indicator(column) {
            return this.sortBy !== column ? '' : (this.sortDirection === 'asc' ? '↑' : '↓');
        }
    }"
    x-init="$watch('showOffline', value => localStorage.setItem('thconect-supervision-offline', value ? '1' : '0'))"
>
    <header class="flex items-center justify-between gap-4 px-4 pt-4">
        <div>
            <h2 class="text-base font-bold">Agentes</h2>
            <p class="mt-0.5 text-[11px]" style="color: var(--fg-muted);">Agentes em operacao</p>
        </div>
        <p class="text-xs" style="color: var(--fg-muted);">Total <strong style="color: var(--fg);">{{ $totalAgents }}</strong></p>
    </header>

    <div class="grid grid-cols-5 gap-1 px-4 pb-2 pt-4">
        @foreach ($agentMetrics as $metric)
            <div class="min-w-0 text-center">
                <p class="text-lg font-bold leading-none" style="color: {{ $metric['color'] }};">{{ $metrics[$metric['key']] }}</p>
                <p class="mt-1 truncate text-[9px] font-semibold uppercase" style="color: var(--fg-muted);"><span class="mr-1 inline-block h-1.5 w-1.5 rounded-full" style="background: {{ $metric['color'] }};"></span>{{ $metric['label'] }}</p>
            </div>
        @endforeach
    </div>
    <div class="grid h-1 grid-cols-5 overflow-hidden rounded-full mx-4">
        @foreach ($agentMetrics as $metric)
            <span style="background: {{ $metric['color'] }};"></span>
        @endforeach
    </div>

    <div class="px-4 py-3">
        <label class="relative block">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center" style="color: var(--fg-muted);"><x-ui.icon name="search" /></span>
            <input x-model.debounce.150ms="search" type="search" placeholder="Pesquisar agente, ramal ou equipe" class="h-9 w-full rounded-lg border py-2 pl-9 pr-3 text-xs" style="border-color: var(--border); background: var(--surface-alt); color: var(--fg);">
        </label>
    </div>

    <div class="supervision-table-head supervision-agent-grid">
        <button type="button" class="th-focus flex items-center gap-1 text-left" x-on:click="toggleSort('name')">Agente <span x-text="indicator('name')"></span></button>
        <button type="button" class="th-focus flex items-center gap-1 text-left" x-on:click="toggleSort('status')">Status <span x-text="indicator('status')"></span></button>
        <button type="button" class="th-focus ml-auto flex items-center gap-1 text-right" x-on:click="toggleSort('calls')">Lig. hoje <span x-text="indicator('calls')"></span></button>
        <button type="button" class="th-focus ml-auto flex items-center gap-1 text-right" x-on:click="toggleSort('average')">Media <span x-text="indicator('average')"></span></button>
        <span class="text-right">Spy</span>
    </div>

    <div class="supervision-scroll-region flex flex-col">
        @forelse ($agents as $agent)
            @php
                $activeCall = $agent->calls->first();
                $teamsText = count($agent->team_names) ? implode(', ', $agent->team_names) : 'Sem equipe';
                $status = [
                    'available' => ['label' => 'Disponivel', 'color' => 'var(--status-success)'],
                    'paused' => ['label' => 'Em pausa', 'color' => 'var(--status-warning)'],
                    'in_call' => ['label' => 'Em chamada', 'color' => 'var(--status-info)'],
                    'wrap_up' => ['label' => 'Tabulando', 'color' => 'var(--status-purple)'],
                    'offline' => ['label' => 'Offline', 'color' => 'var(--status-neutral)'],
                ][$agent->supervision_status] ?? ['label' => 'Indisponivel', 'color' => 'var(--status-neutral)'];
                $monitoringAvailable = $monitoringReady
                    && in_array($agent->supervision_status, ['available', 'paused', 'in_call', 'wrap_up'], true)
                    && $agent->user_type === 'agent';
                $searchText = mb_strtolower($agent->name.' '.$agent->extension.' '.$teamsText);
                $sortPayload = $sortingAgents->firstWhere('id', $agent->id);
            @endphp

            <div
                class="supervision-table-row supervision-agent-grid"
                x-show="(showOffline || @js($agent->supervision_status) !== 'offline') && (!search || @js($searchText).includes(search.toLowerCase()))"
                x-bind:style="{ '--row-status': @js($status['color']), order: sortOrder(@js($sortPayload)) }"
            >
                <button type="button" class="min-w-0 text-left" x-on:click="$dispatch('open-modal', 'supervision-agent-{{ $agent->id }}')">
                    <p class="truncate text-xs font-bold">{{ $agent->extension }} · {{ $agent->name }}</p>
                    <p class="mt-0.5 truncate text-[10px]" style="color: var(--fg-muted);">{{ $teamsText }}</p>
                </button>
                <div class="min-w-0">
                    <span class="inline-flex max-w-full items-center gap-1 rounded-full px-2 py-1 text-[10px] font-bold" style="border: 1px solid color-mix(in srgb, {{ $status['color'] }} 32%, var(--border)); background: color-mix(in srgb, {{ $status['color'] }} 12%, var(--surface)); color: {{ $status['color'] }};">
                        <span class="truncate">{{ $status['label'] }}</span>
                        <span class="font-mono text-[9px]" x-text="formatElapsed(@js($agent->status_started_at_unix))">{{ $agent->status_duration_label }}</span>
                    </span>
                    @if (count($agent->operational_alerts))
                        <p class="mt-1 truncate text-[9px] font-semibold" style="color: var(--status-danger);">{{ implode(' · ', $agent->operational_alerts) }}</p>
                    @endif
                </div>
                <p class="text-right text-xs font-bold">{{ $agent->calls_today_count }}</p>
                <p class="text-right font-mono text-[10px]" style="color: var(--fg-muted);">{{ $agent->calls_average_label }}</p>
                <div class="flex justify-end">
                    @can('tenant.supervision.operate')
                        <button
                            type="button"
                            class="th-focus inline-flex h-7 items-center gap-1 rounded-full border px-2 text-[10px] font-semibold disabled:cursor-not-allowed disabled:opacity-35"
                            style="border-color: var(--border); background: var(--surface-alt); color: var(--fg);"
                            @disabled(! $monitoringAvailable)
                            @if ($monitoringAvailable)
                                x-on:click="spyAgent = @js([
                                    'name' => $agent->name,
                                    'extension' => $agent->extension,
                                    'phone' => $activeCall?->phone ?: 'Aguardando chamadas',
                                    'url' => route('tenant.supervision.monitor', $agent),
                                ])"
                            @endif
                            title="{{ $monitoringAvailable ? 'Iniciar acompanhamento continuo do ramal' : ($monitoringReason ?? 'Agente offline') }}"
                        ><x-ui.icon name="view" class="h-3 w-3" /> Ouvir</button>
                    @else
                        <span class="text-[10px]" style="color: var(--fg-muted);">—</span>
                    @endcan
                </div>
            </div>

            <x-ui.modal name="supervision-agent-{{ $agent->id }}" title="Detalhes do agente" max-width="md">
                <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <p><span class="font-semibold">Nome:</span> {{ $agent->name }}</p>
                    <p><span class="font-semibold">Ramal:</span> {{ $agent->extension }}</p>
                    <p><span class="font-semibold">Status:</span> <span style="color: {{ $status['color'] }};">{{ $status['label'] }}</span></p>
                    <p><span class="font-semibold">Tempo no status:</span> <span class="font-mono" x-text="formatElapsed(@js($agent->status_started_at_unix))">{{ $agent->status_duration_label }}</span></p>
                    <p class="sm:col-span-2"><span class="font-semibold">Equipes:</span> {{ $teamsText }}</p>
                    <p class="sm:col-span-2"><span class="font-semibold">Campanha atual:</span> {{ $agent->current_campaign_name ?? 'Sem campanha' }}</p>
                    @if ($activeCall)
                        <p class="sm:col-span-2"><span class="font-semibold">Tempo em chamada:</span> <span class="font-mono" x-text="formatElapsed(@js($agent->active_call_started_at_unix))">{{ $agent->active_call_duration_label }}</span> · {{ $activeCall->phone }}</p>
                    @endif
                    @if (count($agent->operational_alerts))
                        <p class="sm:col-span-2" style="color: var(--status-danger);"><span class="font-semibold">Alertas:</span> {{ implode(', ', $agent->operational_alerts) }}</p>
                    @endif
                </div>
            </x-ui.modal>
        @empty
            <p class="px-4 py-12 text-center text-sm" style="color: var(--fg-muted);">Nenhum agente encontrado para estes filtros.</p>
        @endforelse
    </div>

    @if ($metrics['offline'] > 0 && $selectedStatus !== 'offline')
        <div class="border-t px-4 py-3 text-center" style="border-color: var(--border);">
            <button type="button" x-on:click="showOffline = !showOffline" class="th-focus inline-flex rounded-full border px-3 py-1.5 text-[10px] font-semibold" style="border-color: var(--border); background: var(--surface-alt); color: var(--fg-muted);" x-text="showOffline ? 'Ocultar offline' : 'Mostrar {{ $metrics['offline'] }} offline'"></button>
        </div>
    @endif
</section>
