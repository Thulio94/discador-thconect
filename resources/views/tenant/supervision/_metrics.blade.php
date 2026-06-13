<div id="supervision-metrics" class="grid gap-3 md:grid-cols-5">
    <x-ui.card>
        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Disponiveis</p>
        <p class="mt-2 text-2xl font-bold">{{ $metrics['available'] }} disponivel</p>
    </x-ui.card>
    <x-ui.card>
        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Em pausa</p>
        <p class="mt-2 text-2xl font-bold">{{ $metrics['paused'] }} pausa</p>
    </x-ui.card>
    <x-ui.card>
        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Em chamada</p>
        <p class="mt-2 text-2xl font-bold">{{ $metrics['in_call'] }} chamada</p>
    </x-ui.card>
    <x-ui.card>
        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Offline</p>
        <p class="mt-2 text-2xl font-bold">{{ $metrics['offline'] }} offline</p>
    </x-ui.card>
    <x-ui.card>
        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Chamadas ativas</p>
        <p class="mt-2 text-2xl font-bold">{{ $metrics['active_calls'] }}</p>
    </x-ui.card>
</div>
