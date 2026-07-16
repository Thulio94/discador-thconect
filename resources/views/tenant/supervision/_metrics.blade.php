@php
    $metricCards = [
        ['label' => 'Disponiveis', 'count' => $metrics['available'], 'singular' => 'disponivel', 'plural' => 'disponiveis', 'color' => 'var(--status-success)'],
        ['label' => 'Em pausa', 'count' => $metrics['paused'], 'singular' => 'em pausa', 'plural' => 'em pausa', 'color' => 'var(--status-warning)'],
        ['label' => 'Em chamada', 'count' => $metrics['in_call'], 'singular' => 'em chamada', 'plural' => 'em chamada', 'color' => 'var(--status-info)'],
        ['label' => 'Offline', 'count' => $metrics['offline'], 'singular' => 'offline', 'plural' => 'offline', 'color' => 'var(--status-neutral)'],
        ['label' => 'Chamadas ativas', 'count' => $metrics['active_calls'], 'singular' => 'chamada ativa', 'plural' => 'chamadas ativas', 'color' => 'var(--status-purple)'],
    ];
@endphp

<div id="supervision-metrics" class="grid gap-3 md:grid-cols-5">
    @foreach ($metricCards as $metric)
        <x-ui.card>
            <div class="border-l-4 pl-3" style="border-color: {{ $metric['color'] }};">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full" style="background: {{ $metric['color'] }};"></span>
                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">{{ $metric['label'] }}</p>
                </div>
                <p class="mt-2 text-2xl font-bold" style="color: {{ $metric['color'] }};">{{ $metric['count'] }}</p>
                <p class="mt-1 text-xs font-medium" style="color: var(--fg-muted);">{{ $metric['count'] === 1 ? $metric['singular'] : $metric['plural'] }}</p>
            </div>
        </x-ui.card>
    @endforeach
</div>
