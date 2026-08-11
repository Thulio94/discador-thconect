@php
    $callStates = [
        'dialing' => ['label' => 'Chamando', 'color' => 'var(--status-info)'],
        'pending' => ['label' => 'Em espera', 'color' => 'var(--status-warning)'],
        'in_progress' => ['label' => 'Falando', 'color' => 'var(--status-success)'],
        'wrap_up' => ['label' => 'Tabulando', 'color' => 'var(--status-purple)'],
        'ended' => ['label' => 'Finalizada', 'color' => 'var(--status-neutral)'],
        'cancelled' => ['label' => 'Nao completada', 'color' => 'var(--status-danger)'],
    ];
    $callMetrics = [
        ['label' => 'Chamando', 'count' => $recentCalls->where('status', 'dialing')->count(), 'color' => 'var(--status-info)'],
        ['label' => 'Falando', 'count' => $recentCalls->where('status', 'in_progress')->count(), 'color' => 'var(--status-success)'],
        ['label' => 'Em espera', 'count' => $recentCalls->where('status', 'pending')->count(), 'color' => 'var(--status-warning)'],
        ['label' => 'AMD / Caixa', 'count' => $recentCalls->filter(fn ($call) => filled($call->amd_result) && ! in_array(strtolower($call->amd_result), ['human', 'humano'], true))->count(), 'color' => 'var(--status-danger)'],
    ];
    $endReasonLabels = [
        'answered_elsewhere' => 'Atendida em outro canal',
        'busy' => 'Ocupado',
        'no_answer' => 'Nao atendeu',
        'failed' => 'Falha na chamada',
        'congestion' => 'Congestionamento',
        'rejected' => 'Rejeitada',
    ];
@endphp

<section id="supervision-calls" class="supervision-panel">
    <header class="flex items-center justify-between gap-4 px-4 pt-4">
        <div>
            <h2 class="text-base font-bold">Ligacoes</h2>
            <p class="mt-0.5 text-[11px]" style="color: var(--fg-muted);">Fluxo recente da operacao</p>
        </div>
        <p class="text-xs" style="color: var(--fg-muted);">Ultimas <strong style="color: var(--primary);">50</strong> chamadas</p>
    </header>

    <div class="grid grid-cols-4 gap-1 px-4 pb-2 pt-4">
        @foreach ($callMetrics as $metric)
            <div class="min-w-0 text-center">
                <p class="text-lg font-bold leading-none" style="color: {{ $metric['color'] }};">{{ $metric['count'] }}</p>
                <p class="mt-1 truncate text-[9px] font-semibold uppercase" style="color: var(--fg-muted);"><span class="mr-1 inline-block h-1.5 w-1.5 rounded-full" style="background: {{ $metric['color'] }};"></span>{{ $metric['label'] }}</p>
            </div>
        @endforeach
    </div>
    <div class="grid h-1 grid-cols-4 overflow-hidden rounded-full mx-4 mb-3">
        @foreach ($callMetrics as $metric)
            <span style="background: {{ $metric['color'] }};"></span>
        @endforeach
    </div>

    <div class="supervision-table-head supervision-call-grid">
        <span>Status</span>
        <span>Telefone</span>
        <span class="text-right">Tempo</span>
    </div>

    <div class="supervision-scroll-region supervision-call-scroll">
        @forelse ($recentCalls as $call)
            @php
                $state = $callStates[$call->status] ?? ['label' => 'Em processamento', 'color' => 'var(--status-neutral)'];
                $startedAt = $call->started_at;
                $finishedAt = $call->ended_at ?? now();
                $seconds = $startedAt ? max(0, (int) $startedAt->diffInSeconds($finishedAt)) : 0;
                $duration = sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
                $startedAtUnix = $startedAt?->timestamp;
                $endedAtUnix = $call->ended_at?->timestamp;
                $callLabel = $call->end_reason ? ($endReasonLabels[$call->end_reason] ?? str_replace('_', ' ', ucfirst($call->end_reason))) : $state['label'];
                if (filled($call->amd_result) && ! in_array(strtolower($call->amd_result), ['human', 'humano'], true)) {
                    $callLabel = 'AMD / Caixa postal';
                    $state = ['label' => $callLabel, 'color' => 'var(--status-danger)'];
                }
            @endphp
            <div class="supervision-table-row supervision-call-grid" style="--row-status: {{ $state['color'] }};">
                <div class="min-w-0">
                    <p class="truncate text-xs font-semibold"><x-ui.icon name="phone" class="mr-1 inline h-3 w-3" style="color: {{ $state['color'] }};" />{{ $callLabel }}</p>
                    <p class="mt-0.5 truncate text-[10px]" style="color: var(--fg-muted);">{{ $call->campaign?->name ?: 'Discagem manual' }}</p>
                </div>
                <p class="truncate font-mono text-xs font-semibold" style="color: var(--accent-cyan);">{{ $call->phone ?: 'Numero nao identificado' }}</p>
                <p class="text-right font-mono text-[10px]" style="color: var(--fg-muted);" x-text="formatElapsed(@js($startedAtUnix), @js($endedAtUnix))">{{ $duration }}</p>
            </div>
        @empty
            <p class="px-4 py-12 text-center text-sm" style="color: var(--fg-muted);">Nenhuma ligacao registrada ainda.</p>
        @endforelse
    </div>
</section>
