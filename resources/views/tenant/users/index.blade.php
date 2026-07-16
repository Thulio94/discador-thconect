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

        <form method="GET" action="{{ route('tenant.users.index') }}" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-[minmax(240px,1.4fr)_180px_180px_220px_220px_auto]">
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
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ $user->name }}</p>
                                <span class="rounded-full px-2 py-1 text-xs" style="background: {{ $user->status === 'active' ? 'color-mix(in srgb, var(--status-success) 12%, var(--surface))' : 'var(--bg-subtle)' }}; color: {{ $user->status === 'active' ? 'var(--status-success)' : 'var(--fg-muted)' }};">
                                    {{ $user->status === 'active' ? 'Ativo' : 'Inativo' }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">{{ $user->email }} | Ramal {{ $user->extension }}</p>
                            <p class="mt-1 text-xs" style="color: var(--fg-muted);">
                                {{ $user->role?->name ?? 'Sem perfil' }} | {{ $user->assignedTeams->pluck('name')->join(', ') ?: 'Sem equipe' }}
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2 lg:justify-end">
                            <x-ui.action icon="view" label="Visualizar" x-data x-on:click="$dispatch('open-modal', 'user-view-{{ $user->id }}')" />
                            @can('tenant.users.update')
                                <x-ui.action icon="edit" label="Editar" x-data x-on:click="$dispatch('open-modal', 'user-edit-{{ $user->id }}')" />
                            @endcan
                            @can('tenant.users.delete')
                                @if ($user->status === 'active' && $user->id !== auth()->id())
                                    <x-ui.action icon="delete" label="Inativar" variant="danger" x-data x-on:click="$dispatch('open-modal', 'user-delete-{{ $user->id }}')" />
                                @endif
                            @endcan
                        </div>
                    </div>
                </div>

                <x-ui.modal name="user-view-{{ $user->id }}" title="Detalhes do usuario" max-width="lg">
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt class="font-semibold">Nome</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $user->name }}</dd></div>
                        <div><dt class="font-semibold">E-mail</dt><dd class="mt-1 break-all" style="color: var(--fg-muted);">{{ $user->email }}</dd></div>
                        <div><dt class="font-semibold">Ramal</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $user->extension }}</dd></div>
                        <div><dt class="font-semibold">Tipo</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $user->user_type }}</dd></div>
                        <div><dt class="font-semibold">Perfil</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $user->role?->name ?? 'Sem perfil' }}</dd></div>
                        <div><dt class="font-semibold">Status</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $user->status === 'active' ? 'Ativo' : 'Inativo' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="font-semibold">Equipes</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $user->assignedTeams->pluck('name')->join(', ') ?: 'Sem equipe' }}</dd></div>
                    </dl>
                </x-ui.modal>

                @can('tenant.users.update')
                    <x-ui.modal name="user-edit-{{ $user->id }}" title="Editar usuario" :show="$errors->any() && (int) old('_user_id') === $user->id">
                        <form method="POST" action="{{ route('tenant.users.update', $user) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_user_id" value="{{ $user->id }}">
                            @include('tenant.users._form', ['user' => $user, 'roles' => $roles, 'teams' => $teams])
                            <div class="mt-5 flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'user-edit-{{ $user->id }}')">Cancelar</x-ui.button>
                                <x-ui.button type="submit">Salvar usuario</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>
                @endcan

                @can('tenant.users.delete')
                    @if ($user->status === 'active' && $user->id !== auth()->id())
                        <x-ui.modal name="user-delete-{{ $user->id }}" title="Inativar usuario" max-width="md">
                            <form method="POST" action="{{ route('tenant.users.destroy', $user) }}">
                                @csrf
                                @method('DELETE')
                                <p class="text-sm" style="color: var(--fg-muted);">{{ $user->name }} perdera o acesso ao sistema, mas permanecera no historico operacional.</p>
                                <div class="mt-5 flex justify-end gap-2">
                                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'user-delete-{{ $user->id }}')">Cancelar</x-ui.button>
                                    <x-ui.button type="submit" variant="danger">Inativar usuario</x-ui.button>
                                </div>
                            </form>
                        </x-ui.modal>
                    @endif
                @endcan
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
