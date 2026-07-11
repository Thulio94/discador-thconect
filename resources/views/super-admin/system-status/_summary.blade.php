@php
    $colors = [
        'operational' => 'var(--status-success)',
        'degraded' => 'var(--status-warning)',
        'outage' => 'var(--status-danger)',
    ];
    $stateColor = $colors[$state];
@endphp

<section id="system-status-summary" class="border px-5 py-5" style="border-color: {{ $stateColor }}; background: color-mix(in srgb, {{ $stateColor }} 9%, var(--surface));">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-lg" style="background: color-mix(in srgb, {{ $stateColor }} 18%, var(--surface)); color: {{ $stateColor }};">
                <x-ui.icon :name="$state === 'operational' ? 'check' : 'warning'" class="h-5 w-5" />
            </span>
            <div>
                <h2 class="text-lg font-bold">{{ $title }}</h2>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Verificado {{ $checked_at->diffForHumans() }}</p>
            </div>
        </div>

        <button type="button" class="th-focus grid h-10 w-10 place-items-center rounded-lg border" style="border-color: var(--border); color: var(--fg-muted);" title="Atualizar status" aria-label="Atualizar status" x-on:click="poll()">
            <x-ui.icon name="refresh" />
        </button>
    </div>
</section>
