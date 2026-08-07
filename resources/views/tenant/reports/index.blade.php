<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            @if ($selectedReport)
                <a href="{{ route('tenant.reports.index') }}" class="th-focus grid h-10 w-10 place-items-center rounded-full border" style="border-color: var(--border);" aria-label="Voltar aos relatórios"><x-ui.icon name="back" /></a>
            @endif
            <div>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Relatórios</p>
                <h1 class="text-2xl font-bold" style="color: var(--fg);">{{ $selectedReport ? $reportCatalog[$selectedReport]['title'] : 'Central de relatórios' }}</h1>
            </div>
        </div>
    </x-slot>

    @if (! $selectedReport)
        <x-ui.card>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($reportCatalog as $key => $report)
                    <a href="{{ route('tenant.reports.index', ['report' => $key]) }}" class="th-focus flex items-center justify-between gap-4 rounded-lg border p-5 transition hover:-translate-y-0.5" style="border-color: var(--border); background: var(--surface);">
                        <span class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-lg" style="background: var(--primary-soft); color: var(--primary);"><x-ui.icon :name="$report['icon']" /></span><span><span class="block font-semibold">{{ $report['title'] }}</span><span class="mt-1 block text-xs" style="color: var(--fg-muted);">{{ $report['description'] }}</span></span></span>
                        <x-ui.icon name="open" />
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    @else
        @include('tenant.reports._filters')
        @include('tenant.reports._'.$selectedReport)
    @endif
</x-app-layout>
