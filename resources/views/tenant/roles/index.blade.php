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
            <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'role-create')">Novo perfil</x-ui.button>
        </div>

        <div class="mt-5 grid gap-3">
            @forelse ($roles as $role)
                <div class="rounded-lg border p-4" style="border-color: var(--border);">
                    <p class="font-semibold">{{ $role->name }}</p>
                    <p class="mt-1 text-sm" style="color: var(--fg-muted);">{{ $role->permissions->count() }} permissoes</p>
                </div>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhum perfil cadastrado.</p>
            @endforelse
        </div>
    </x-ui.card>

    <x-ui.modal name="role-create" title="Novo perfil" max-width="4xl">
        <form method="POST" action="{{ route('tenant.roles.store') }}">
            @csrf
            <div class="grid gap-4">
                <label class="grid gap-1 text-sm">
                    <span class="font-semibold">Nome</span>
                    <input name="name" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
                </label>
                <label class="flex items-center gap-2 rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                    <input type="hidden" name="is_admin" value="0">
                    <input type="checkbox" name="is_admin" value="1">
                    <span>Perfil administrativo</span>
                </label>
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($modules as $module)
                        <div class="rounded-lg border p-3" style="border-color: var(--border);">
                            <p class="mb-2 text-sm font-semibold">{{ $module['label'] }}</p>
                            <div class="grid gap-2">
                                @foreach ($module['permissions'] as $permission)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission['key'] }}">
                                        <span>{{ $permission['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="mt-5 flex justify-end">
                <x-ui.button type="submit">Criar perfil</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-app-layout>
