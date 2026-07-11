@php
    $colors = [
        'operational' => 'var(--status-success)',
        'degraded' => 'var(--status-warning)',
        'outage' => 'var(--status-danger)',
    ];
@endphp

<section id="system-status-components" class="grid gap-3 lg:grid-cols-2">
    @foreach ($components as $component)
        @php($color = $colors[$component['state']])
        <article class="border p-4" style="border-color: var(--border); background: var(--surface);">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $color }};"></span>
                        <h2 class="font-semibold">{{ $component['name'] }}</h2>
                    </div>
                    <p class="mt-1 text-sm" style="color: var(--fg-muted);">{{ $component['description'] }}</p>
                </div>
                <span class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold" style="background: color-mix(in srgb, {{ $color }} 13%, var(--surface)); color: {{ $color }};">{{ $component['label'] }}</span>
            </div>

            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs" style="color: var(--fg-muted);">
                <span>{{ $component['latency_ms'] }} ms</span>
                @if ($component['heartbeat_at'])
                    <span>Sinal {{ $component['heartbeat_at']->diffForHumans() }}</span>
                @endif
                @if ($component['error'])
                    <span style="color: var(--status-danger);">{{ \Illuminate\Support\Str::limit($component['error'], 110) }}</span>
                @endif
            </div>
        </article>
    @endforeach
</section>
