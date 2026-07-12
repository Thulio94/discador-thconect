<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Cadastros</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Equipes</h1>
        </div>
    </x-slot>

    <x-ui.card>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Equipes</h2>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Agentes e supervisores podem participar de varias equipes.</p>
            </div>
            @can('tenant.teams.create')
                <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'team-create')">Nova equipe</x-ui.button>
            @endcan
        </div>

        <form method="GET" action="{{ route('tenant.teams.index') }}" class="mt-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_260px_auto]">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar equipe" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            <select name="status" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todos status</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Ativa</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inativa</option>
            </select>
            <select name="member_id" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todos membros</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}" @selected((int) ($filters['member_id'] ?? 0) === $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <x-ui.button type="submit">Filtrar</x-ui.button>
                <a href="{{ route('tenant.teams.index') }}" class="th-focus inline-flex items-center justify-center px-4 py-2 text-sm font-semibold" style="border: 1px solid var(--border); background: var(--surface-alt); color: var(--fg); border-radius: var(--radius-md);">Limpar</a>
            </div>
        </form>

        <div class="mt-5 grid gap-3">
            @forelse ($teams as $team)
                <div class="rounded-lg border p-4" style="border-color: var(--border);">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ $team->name }}</p>
                                <span class="rounded-full px-2 py-1 text-xs" style="background: var(--bg-subtle); color: var(--fg-muted);">{{ $team->status === 'active' ? 'Ativa' : 'Inativa' }}</span>
                            </div>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">{{ $team->agents_count }} agentes | {{ $team->supervisors_count }} supervisores</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <x-ui.action icon="view" label="Visualizar" x-data x-on:click="$dispatch('open-modal', 'team-view-{{ $team->id }}')" />
                            @can('tenant.teams.update')
                                <x-ui.action icon="edit" label="Editar" x-data x-on:click="$dispatch('open-modal', 'team-edit-{{ $team->id }}')" />
                            @endcan
                            @can('tenant.teams.delete')
                                @if ($team->status === 'active')
                                    <x-ui.action icon="delete" label="Inativar" variant="danger" x-data x-on:click="$dispatch('open-modal', 'team-delete-{{ $team->id }}')" />
                                @endif
                            @endcan
                        </div>
                    </div>
                </div>

                <x-ui.modal name="team-view-{{ $team->id }}" title="Detalhes da equipe" max-width="lg">
                    <div class="grid gap-4 text-sm">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div><p class="font-semibold">Status</p><p class="mt-1" style="color: var(--fg-muted);">{{ $team->status === 'active' ? 'Ativa' : 'Inativa' }}</p></div>
                            <div><p class="font-semibold">Agentes</p><p class="mt-1" style="color: var(--fg-muted);">{{ $team->agents_count }}</p></div>
                            <div><p class="font-semibold">Supervisores</p><p class="mt-1" style="color: var(--fg-muted);">{{ $team->supervisors_count }}</p></div>
                        </div>
                        <div><p class="font-semibold">Membros</p><p class="mt-1" style="color: var(--fg-muted);">{{ $team->members->pluck('name')->join(', ') ?: 'Nenhum membro vinculado' }}</p></div>
                    </div>
                </x-ui.modal>

                @can('tenant.teams.update')
                    <x-ui.modal name="team-edit-{{ $team->id }}" title="Editar equipe">
                        <form method="POST" action="{{ route('tenant.teams.update', $team) }}">
                            @csrf
                            @method('PUT')
                            @include('tenant.teams._form', ['team' => $team, 'agents' => $agents, 'supervisors' => $supervisors])
                            <div class="mt-5 flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'team-edit-{{ $team->id }}')">Cancelar</x-ui.button>
                                <x-ui.button type="submit">Salvar equipe</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>
                @endcan

                @can('tenant.teams.delete')
                    @if ($team->status === 'active')
                        <x-ui.modal name="team-delete-{{ $team->id }}" title="Inativar equipe" max-width="md">
                            <form method="POST" action="{{ route('tenant.teams.destroy', $team) }}">
                                @csrf
                                @method('DELETE')
                                <p class="text-sm" style="color: var(--fg-muted);">A equipe ficara inativa e permanecera no historico das campanhas.</p>
                                <div class="mt-5 flex justify-end gap-2">
                                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'team-delete-{{ $team->id }}')">Cancelar</x-ui.button>
                                    <x-ui.button type="submit" variant="danger">Inativar equipe</x-ui.button>
                                </div>
                            </form>
                        </x-ui.modal>
                    @endif
                @endcan
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhuma equipe cadastrada.</p>
            @endforelse
        </div>
        <div class="mt-5">{{ $teams->links() }}</div>
    </x-ui.card>

    @can('tenant.teams.create')
    <x-ui.modal name="team-create" title="Nova equipe">
        <form method="POST" action="{{ route('tenant.teams.store') }}">
            @csrf
            @include('tenant.teams._form', ['team' => null, 'agents' => $agents, 'supervisors' => $supervisors])
            <div class="mt-5 flex justify-end">
                <x-ui.button type="submit">Criar equipe</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
    @endcan
</x-app-layout>
