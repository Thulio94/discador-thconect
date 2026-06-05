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
            <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'team-create')">Nova equipe</x-ui.button>
        </div>

        <div class="mt-5 grid gap-3">
            @forelse ($teams as $team)
                <div class="rounded-lg border p-4" style="border-color: var(--border);">
                    <p class="font-semibold">{{ $team->name }}</p>
                    <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                        {{ $team->agents->count() }} agentes | {{ $team->supervisors->count() }} supervisores
                    </p>
                </div>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhuma equipe cadastrada.</p>
            @endforelse
        </div>
    </x-ui.card>

    <x-ui.modal name="team-create" title="Nova equipe">
        <form method="POST" action="{{ route('tenant.teams.store') }}">
            @csrf
            <div class="grid gap-4">
                <label class="grid gap-1 text-sm">
                    <span class="font-semibold">Nome</span>
                    <input name="name" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
                </label>
                <input type="hidden" name="status" value="active">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="mb-2 text-sm font-semibold">Agentes</p>
                        @foreach ($agents as $agent)
                            <label class="mb-2 flex items-center gap-2 rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                                <input type="checkbox" name="agent_ids[]" value="{{ $agent->id }}">
                                <span>{{ $agent->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-semibold">Supervisores</p>
                        @foreach ($supervisors as $supervisor)
                            <label class="mb-2 flex items-center gap-2 rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                                <input type="checkbox" name="supervisor_ids[]" value="{{ $supervisor->id }}">
                                <span>{{ $supervisor->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="mt-5 flex justify-end">
                <x-ui.button type="submit">Criar equipe</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-app-layout>
