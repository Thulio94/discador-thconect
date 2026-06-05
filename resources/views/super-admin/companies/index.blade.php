<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Super admin</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Empresas</h1>
        </div>
    </x-slot>

    <x-ui.card>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Contratos SaaS</h2>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Gestao de empresas e licencas sera operada em modal.</p>
            </div>
            <x-ui.button type="button">Nova empresa</x-ui.button>
        </div>

        <div class="mt-5 grid gap-3">
            @forelse ($companies as $company)
                <div class="rounded-lg border p-4" style="border-color: var(--border);">
                    <p class="font-semibold">{{ $company->name }}</p>
                    <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                        Chamadas por operador disponivel: {{ $company->license?->max_calls_per_available_agent ?? '-' }}
                    </p>
                </div>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhuma empresa cadastrada.</p>
            @endforelse
        </div>
    </x-ui.card>
</x-app-layout>
