<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Cadastros</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Listas</h1>
        </div>
    </x-slot>

    <x-ui.card>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Listas de contatos</h2>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Cada campanha pode usar uma ou varias listas.</p>
            </div>
            <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'list-create')">Nova lista</x-ui.button>
        </div>

        <div class="mt-5 grid gap-3">
            @forelse ($lists as $list)
                <div class="rounded-lg border p-4" style="border-color: var(--border);">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold">{{ $list->name }}</p>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                                {{ $list->contacts_count }} contatos | {{ $list->campaigns_count }} campanhas
                            </p>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs" style="background: var(--bg-subtle); color: var(--fg-muted);">{{ $list->status }}</span>
                    </div>
                    <div class="mt-4 flex flex-wrap justify-end gap-2">
                        <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'list-edit-{{ $list->id }}')">Editar</x-ui.button>
                        <x-ui.button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'list-delete-{{ $list->id }}')">Inativar</x-ui.button>
                    </div>
                </div>

                <x-ui.modal name="list-edit-{{ $list->id }}" title="Editar lista">
                    <form method="POST" action="{{ route('tenant.lists.update', $list) }}">
                        @csrf
                        @method('PUT')
                        @include('tenant.lists._form', ['list' => $list])
                        <div class="mt-5 flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'list-edit-{{ $list->id }}')">Cancelar</x-ui.button>
                            <x-ui.button type="submit">Salvar lista</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>

                <x-ui.modal name="list-delete-{{ $list->id }}" title="Inativar lista" max-width="md">
                    <form method="POST" action="{{ route('tenant.lists.destroy', $list) }}">
                        @csrf
                        @method('DELETE')
                        <p class="text-sm" style="color: var(--fg-muted);">A lista ficara inativa e nao sera removida do historico.</p>
                        <div class="mt-5 flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'list-delete-{{ $list->id }}')">Cancelar</x-ui.button>
                            <x-ui.button type="submit" variant="danger">Inativar lista</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhuma lista cadastrada.</p>
            @endforelse
        </div>
    </x-ui.card>

    <x-ui.modal name="list-create" title="Nova lista">
        <form method="POST" action="{{ route('tenant.lists.store') }}">
            @csrf
            @include('tenant.lists._form')
            <div class="mt-5 flex justify-end">
                <x-ui.button type="submit">Criar lista</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-app-layout>
