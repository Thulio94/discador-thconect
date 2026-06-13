<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Cadastros</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Usuarios</h1>
        </div>
    </x-slot>

    <x-ui.card>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Usuarios com ramal</h2>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Todos os usuarios possuem ramal. Apenas agentes entram em campanhas.</p>
            </div>
            @can('tenant.users.create')
                <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'user-create')">Novo usuario</x-ui.button>
            @endcan
        </div>

        <div class="mt-5 grid gap-3">
            @forelse ($users as $user)
                <div class="rounded-lg border p-4" style="border-color: var(--border);">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold">{{ $user->name }}</p>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">{{ $user->email }} | Ramal {{ $user->extension }}</p>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs" style="background: var(--bg-subtle); color: var(--fg-muted);">{{ $user->user_type }}</span>
                    </div>
                </div>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhum usuario cadastrado.</p>
            @endforelse
        </div>
    </x-ui.card>

    @can('tenant.users.create')
        <x-ui.modal name="user-create" title="Novo usuario">
            <form method="POST" action="{{ route('tenant.users.store') }}">
                @csrf
                @include('tenant.users._form', ['user' => null, 'roles' => $roles, 'teams' => $teams])
                <div class="mt-5 flex justify-end">
                    <x-ui.button type="submit">Criar usuario</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endcan
</x-app-layout>
