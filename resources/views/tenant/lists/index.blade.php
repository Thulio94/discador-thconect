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
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Cada campanha pode usar uma ou varias listas. Blacklist aplicada automaticamente.</p>
            </div>
            @can('tenant.lists.create')
                <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'list-create')">Nova lista</x-ui.button>
            @endcan
        </div>

        <form method="GET" action="{{ route('tenant.lists.index') }}" class="mt-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_260px_auto]">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar lista" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            <select name="status" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todos status</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativa</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativa</option>
            </select>
            <select name="campaign_id" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todas campanhas</option>
                @foreach ($campaigns as $campaign)
                    <option value="{{ $campaign->id }}" @selected((int) ($filters['campaign_id'] ?? 0) === $campaign->id)>{{ $campaign->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <x-ui.button type="submit">Filtrar</x-ui.button>
                <a href="{{ route('tenant.lists.index') }}" class="th-focus inline-flex items-center justify-center px-4 py-2 text-sm font-semibold" style="border: 1px solid var(--border); background: var(--surface-alt); color: var(--fg); border-radius: var(--radius-md);">Limpar</a>
            </div>
        </form>

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
                        @can('tenant.lists.update')
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'list-edit-{{ $list->id }}')">Editar</x-ui.button>
                        @endcan
                        @can('tenant.lists.delete')
                            <x-ui.button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'list-delete-{{ $list->id }}')">Inativar</x-ui.button>
                        @endcan
                    </div>
                </div>

                @can('tenant.lists.update')
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
                @endcan

                @can('tenant.lists.delete')
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
                @endcan
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhuma lista cadastrada.</p>
            @endforelse
        </div>
        <div class="mt-5">{{ $lists->links() }}</div>
    </x-ui.card>

    @can('tenant.lists.create')
    <x-ui.modal name="list-create" title="Nova lista" max-width="4xl">
        <form method="POST" action="{{ route('tenant.lists.store') }}" enctype="multipart/form-data" x-data="listImportPreview()">
            @csrf
            @include('tenant.lists._form', ['list' => null])
            <div class="mt-5 flex flex-wrap items-center justify-end gap-3">
                <span class="text-xs" style="color: var(--fg-muted);" x-show="!canSubmit()">Aguardando coluna de Telefone.</span>
                <x-ui.button type="submit" x-bind:disabled="!canSubmit()">Criar lista</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
    @endcan
</x-app-layout>
