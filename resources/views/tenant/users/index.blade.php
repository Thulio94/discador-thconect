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

        <form method="GET" action="{{ route('tenant.users.index') }}" class="mt-5 grid gap-3 lg:grid-cols-[minmax(0,1.4fr)_180px_180px_220px_220px_auto]">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar nome, e-mail ou ramal" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            <select name="status" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todos status</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativo</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativo</option>
            </select>
            <select name="user_type" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todos tipos</option>
                @foreach (['agent' => 'Agente', 'supervisor' => 'Supervisor', 'company_admin' => 'Administrador'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['user_type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="role_id" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todos perfis</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((int) ($filters['role_id'] ?? 0) === $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            <select name="team_id" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todas equipes</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected((int) ($filters['team_id'] ?? 0) === $team->id)>{{ $team->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <x-ui.button type="submit">Filtrar</x-ui.button>
                <a href="{{ route('tenant.users.index') }}" class="th-focus inline-flex items-center justify-center px-4 py-2 text-sm font-semibold" style="border: 1px solid var(--border); background: var(--surface-alt); color: var(--fg); border-radius: var(--radius-md);">Limpar</a>
            </div>
        </form>

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
        <div class="mt-5">{{ $users->links() }}</div>
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
