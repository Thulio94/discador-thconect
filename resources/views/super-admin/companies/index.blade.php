<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Super admin</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Empresas</h1>
        </div>
    </x-slot>

    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.card title="Usuarios contratados">
            @include('partials.license-usage-meter', ['label' => 'Usuarios ativos', 'metric' => $licenseOverview['users']])
        </x-ui.card>
        <x-ui.card title="Agentes contratados">
            @include('partials.license-usage-meter', ['label' => 'Agentes disponiveis', 'metric' => $licenseOverview['available_agents']])
        </x-ui.card>
        <x-ui.card title="Chamadas contratadas">
            @include('partials.license-usage-meter', ['label' => 'Chamadas ativas', 'metric' => $licenseOverview['active_calls']])
        </x-ui.card>
    </div>

    <x-ui.card class="mt-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Contratos SaaS</h2>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Uso contratado versus uso atual por empresa.</p>
            </div>
            <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'company-create')">Nova empresa</x-ui.button>
        </div>

        <div class="mt-5 grid gap-3">
            @forelse ($companies as $company)
                @php($usage = $company->license_usage)
                <div class="rounded-lg border p-4" style="border-color: var(--border);">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ $company->name }}</p>
                                <span class="rounded-full px-2 py-1 text-xs font-semibold" style="background: {{ $usage['status'] === 'exceeded' ? 'color-mix(in srgb, var(--status-danger) 12%, var(--surface))' : ($usage['status'] === 'warning' ? 'color-mix(in srgb, var(--status-warning) 14%, var(--surface))' : 'var(--primary-soft)') }}; color: var(--fg);">
                                    {{ $usage['status'] === 'exceeded' ? 'Limite excedido' : ($usage['status'] === 'warning' ? 'Atencao' : 'Normal') }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                                {{ $usage['active_calls']['current'] }} ativa de {{ $usage['active_calls']['limit'] ?? 'sem teto' }}
                                <span aria-hidden="true"> · </span>
                                {{ $usage['calls_per_available_agent'] }} por operador disponivel
                                <span aria-hidden="true"> · </span>
                                capacidade efetiva {{ $usage['effective_call_limit'] }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <x-ui.action icon="view" label="Visualizar" x-data x-on:click="$dispatch('open-modal', 'company-view-{{ $company->id }}')" />
                            <x-ui.action icon="edit" label="Editar" x-data x-on:click="$dispatch('open-modal', 'company-edit-{{ $company->id }}')" />
                            @if ($company->status === 'active')
                                <x-ui.action icon="delete" label="Inativar" variant="danger" x-data x-on:click="$dispatch('open-modal', 'company-delete-{{ $company->id }}')" />
                            @endif
                        </div>
                    </div>

                    <x-ui.modal name="company-view-{{ $company->id }}" title="Detalhes da empresa" max-width="lg">
                        <dl class="grid gap-4 text-sm sm:grid-cols-2">
                            <div><dt class="font-semibold">Empresa</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $company->name }}</dd></div>
                            <div><dt class="font-semibold">Documento</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $company->document ?: 'Nao informado' }}</dd></div>
                            <div><dt class="font-semibold">Status</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $company->status === 'active' ? 'Ativa' : 'Inativa' }}</dd></div>
                            <div><dt class="font-semibold">Licenca</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $company->license?->starts_at?->format('d/m/Y') }} a {{ $company->license?->ends_at?->format('d/m/Y') }}</dd></div>
                            <div><dt class="font-semibold">Usuarios</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $usage['users']['current'] }} / {{ $usage['users']['limit'] ?? 'sem teto' }}</dd></div>
                            <div><dt class="font-semibold">Chamadas simultaneas</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $usage['active_calls']['current'] }} / {{ $usage['active_calls']['limit'] ?? 'sem teto' }}</dd></div>
                        </dl>
                    </x-ui.modal>

                    <div class="mt-4 grid gap-4 lg:grid-cols-3">
                        @include('partials.license-usage-meter', ['label' => 'Usuarios ativos', 'metric' => $usage['users']])
                        @include('partials.license-usage-meter', ['label' => 'Agentes disponiveis', 'metric' => $usage['available_agents']])
                        @include('partials.license-usage-meter', ['label' => 'Chamadas simultaneas', 'metric' => $usage['active_calls']])
                    </div>

                    <x-ui.modal name="company-edit-{{ $company->id }}" title="Editar empresa">
                        <form method="POST" action="{{ route('super-admin.companies.update', $company) }}">
                            @csrf
                            @method('PUT')
                            @include('super-admin.companies._form', ['company' => $company])
                            <div class="mt-5 flex justify-end gap-2">
                                <x-ui.button type="submit">Salvar</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>

                    @if ($company->status === 'active')
                    <x-ui.modal name="company-delete-{{ $company->id }}" title="Inativar empresa">
                        <form method="POST" action="{{ route('super-admin.companies.destroy', $company) }}">
                            @csrf
                            @method('DELETE')
                            <p class="text-sm" style="color: var(--fg-muted);">A empresa sera marcada como inativa.</p>
                            <div class="mt-5 flex justify-end gap-2">
                                <x-ui.button type="submit" variant="danger">Inativar</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>
                    @endif
                </div>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhuma empresa cadastrada.</p>
            @endforelse
        </div>
    </x-ui.card>

    <x-ui.modal name="company-create" title="Nova empresa">
        <form method="POST" action="{{ route('super-admin.companies.store') }}">
            @csrf
            @include('super-admin.companies._form', ['company' => null])
            <div class="mt-5 flex justify-end gap-2">
                <x-ui.button type="submit">Criar empresa</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-app-layout>
