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
            <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'company-create')">Nova empresa</x-ui.button>
        </div>

        <div class="mt-5 grid gap-3">
            @forelse ($companies as $company)
                <div class="rounded-lg border p-4" style="border-color: var(--border);">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold">{{ $company->name }}</p>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                                Chamadas por operador disponivel: {{ $company->license?->max_calls_per_available_agent ?? '-' }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <x-ui.action icon="edit" label="Editar" x-data x-on:click="$dispatch('open-modal', 'company-edit-{{ $company->id }}')" />
                            <x-ui.action icon="delete" label="Inativar" variant="danger" x-data x-on:click="$dispatch('open-modal', 'company-delete-{{ $company->id }}')" />
                        </div>
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
