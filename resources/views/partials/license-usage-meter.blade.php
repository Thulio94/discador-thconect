@php
    $metric ??= ['current' => 0, 'limit' => null, 'percent' => null, 'status' => 'unlimited'];
    $label ??= 'Uso';
    $limitLabel = $metric['limit'] === null ? 'sem teto' : $metric['limit'];
    $percent = $metric['percent'] ?? 0;
    $barWidth = min(100, $percent);
    $tone = match ($metric['status']) {
        'exceeded' => 'var(--status-danger)',
        'warning' => 'var(--status-warning)',
        default => 'var(--primary)',
    };
@endphp

<div class="grid gap-2 text-sm">
    <div class="flex items-center justify-between gap-3">
        <span class="font-semibold">{{ $label }}</span>
        <span style="color: var(--fg-muted);">{{ $metric['current'] }} / {{ $limitLabel }}</span>
    </div>
    <div class="h-2 overflow-hidden rounded-full" style="background: color-mix(in srgb, var(--border) 70%, transparent);">
        <div class="h-2 rounded-full" style="width: {{ $barWidth }}%; background: {{ $tone }};"></div>
    </div>
    <div class="flex items-center justify-between gap-3 text-xs" style="color: var(--fg-muted);">
        <span>
            @if ($metric['status'] === 'exceeded')
                Limite excedido
            @elseif ($metric['status'] === 'warning')
                Proximo do limite
            @elseif ($metric['status'] === 'unlimited')
                Sem limite contratado
            @else
                Dentro do contratado
            @endif
        </span>
        <span>{{ $metric['percent'] === null ? '-' : $metric['percent'].'%' }}</span>
    </div>
</div>
