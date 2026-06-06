<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">{{ $module['section'] }}</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">{{ $module['title'] }}</h1>
        </div>
    </x-slot>

    <x-ui.card :title="$module['title']">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold" style="color: var(--fg);">Modulo em breve</p>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                    Esta area ja esta reservada no painel e sera liberada nas proximas fases do discador.
                </p>
            </div>

            <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold uppercase" style="background: var(--primary-soft); color: var(--primary);">
                Em breve
            </span>
        </div>
    </x-ui.card>
</x-app-layout>
