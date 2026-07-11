<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Super admin</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Status do sistema</h1>
        </div>
    </x-slot>

    <div
        class="grid gap-5"
        x-data="pollingRegion({
            url: @js(route('super-admin.system-status.poll'), JSON_UNESCAPED_SLASHES),
            selectors: ['#system-status-summary', '#system-status-components'],
            intervalSeconds: 30,
        })"
        x-init="start()"
        x-on:visibilitychange.document="handleVisibilityChange()"
    >
        @include('super-admin.system-status._summary')
        @include('super-admin.system-status._components')
    </div>
</x-app-layout>
