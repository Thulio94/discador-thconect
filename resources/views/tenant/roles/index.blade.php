<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Configuracoes</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Perfis</h1>
        </div>
    </x-slot>

    <x-ui.card>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Perfis</h2>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Permissoes agrupadas por modulo.</p>
            </div>
            @can('tenant.roles.create')
                <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'role-create')">Novo perfil</x-ui.button>
            @endcan
        </div>

        <div class="mt-5 grid gap-3">
            @forelse ($roles as $role)
                <div class="rounded-lg border p-4" style="border-color: var(--border);">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ $role->name }}</p>
                                @if ($role->is_admin)
                                    <span class="rounded-full px-2 py-1 text-xs" style="background: var(--primary-soft); color: var(--primary);">Administrativo</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">{{ $role->is_admin ? 'Acesso total para administradores' : $role->permissions->count().' permissoes' }} | {{ $role->users_count }} usuarios</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <x-ui.action icon="view" label="Visualizar" x-data x-on:click="$dispatch('open-modal', 'role-view-{{ $role->id }}')" />
                            @can('tenant.roles.update')
                                <x-ui.action icon="edit" label="Editar" x-data x-on:click="$dispatch('open-modal', 'role-edit-{{ $role->id }}')" />
                            @endcan
                            @can('tenant.roles.delete')
                                @if ($role->users_count === 0)
                                    <x-ui.action icon="delete" label="Excluir" variant="danger" x-data x-on:click="$dispatch('open-modal', 'role-delete-{{ $role->id }}')" />
                                @else
                                    <x-ui.action icon="delete" label="Excluir indisponivel: perfil possui usuarios" variant="danger" disabled />
                                @endif
                            @endcan
                        </div>
                    </div>
                </div>

                <x-ui.modal name="role-view-{{ $role->id }}" title="Detalhes do perfil" max-width="lg">
                    <div class="grid gap-4 text-sm">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div><p class="font-semibold">Tipo</p><p class="mt-1" style="color: var(--fg-muted);">{{ $role->is_admin ? 'Administrativo' : 'Operacional' }}</p></div>
                            <div><p class="font-semibold">Usuarios</p><p class="mt-1" style="color: var(--fg-muted);">{{ $role->users_count }}</p></div>
                            <div><p class="font-semibold">Permissoes</p><p class="mt-1" style="color: var(--fg-muted);">{{ $role->permissions->count() }}</p></div>
                        </div>
                        <div><p class="font-semibold">Acessos concedidos</p><p class="mt-1" style="color: var(--fg-muted);">{{ $role->is_admin ? 'Administradores associados recebem acesso total aos modulos.' : ($role->permissions->pluck('name')->join(', ') ?: 'Nenhuma permissao') }}</p></div>
                    </div>
                </x-ui.modal>

                @can('tenant.roles.update')
                    <x-ui.modal name="role-edit-{{ $role->id }}" title="Editar perfil" max-width="4xl">
                        <form method="POST" action="{{ route('tenant.roles.update', $role) }}">
                            @csrf
                            @method('PUT')
                            @include('tenant.roles._form', ['role' => $role, 'modules' => $modules])
                            <div class="mt-5 flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'role-edit-{{ $role->id }}')">Cancelar</x-ui.button>
                                <x-ui.button type="submit">Salvar perfil</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>
                @endcan

                @can('tenant.roles.delete')
                    @if ($role->users_count === 0)
                        <x-ui.modal name="role-delete-{{ $role->id }}" title="Excluir perfil" max-width="md">
                            <form method="POST" action="{{ route('tenant.roles.destroy', $role) }}">
                                @csrf
                                @method('DELETE')
                                <p class="text-sm" style="color: var(--fg-muted);">O perfil {{ $role->name }} e suas permissoes serao excluidos permanentemente.</p>
                                <div class="mt-5 flex justify-end gap-2">
                                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'role-delete-{{ $role->id }}')">Cancelar</x-ui.button>
                                    <x-ui.button type="submit" variant="danger">Excluir perfil</x-ui.button>
                                </div>
                            </form>
                        </x-ui.modal>
                    @endif
                @endcan
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhum perfil cadastrado.</p>
            @endforelse
        </div>
    </x-ui.card>

    @can('tenant.roles.create')
    <x-ui.modal name="role-create" title="Novo perfil" max-width="4xl">
        <form method="POST" action="{{ route('tenant.roles.store') }}">
            @csrf
            @include('tenant.roles._form', ['role' => null, 'modules' => $modules])
            <div class="mt-5 flex justify-end">
                <x-ui.button type="submit">Criar perfil</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
    @endcan
</x-app-layout>
