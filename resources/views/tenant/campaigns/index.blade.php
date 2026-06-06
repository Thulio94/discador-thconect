<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Operacao</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Campanhas</h1>
        </div>
    </x-slot>

    <x-ui.card>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Campanhas</h2>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Campanhas podem ter varias equipes e varias listas.</p>
            </div>
            <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'campaign-create')">Nova campanha</x-ui.button>
        </div>

        <div class="mt-5 grid gap-3">
            @forelse ($campaigns as $campaign)
                <div class="rounded-lg border p-4" style="border-color: var(--border);">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold">{{ $campaign->name }}</p>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                                {{ $campaign->teams->count() }} equipes | {{ $campaign->contactLists->count() }} listas | Modo {{ $campaign->dialing_mode }}
                            </p>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs" style="background: var(--bg-subtle); color: var(--fg-muted);">{{ $campaign->status }}</span>
                    </div>
                    <div class="mt-4 flex flex-wrap justify-end gap-2">
                        <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'campaign-edit-{{ $campaign->id }}')">Editar</x-ui.button>
                        <x-ui.button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'campaign-finish-{{ $campaign->id }}')">Finalizar</x-ui.button>
                    </div>
                </div>

                <x-ui.modal name="campaign-edit-{{ $campaign->id }}" title="Editar campanha">
                    <form method="POST" action="{{ route('tenant.campaigns.update', $campaign) }}">
                        @csrf
                        @method('PUT')
                        @include('tenant.campaigns._form', ['campaign' => $campaign, 'teams' => $teams, 'lists' => $lists])
                        <div class="mt-5 flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'campaign-edit-{{ $campaign->id }}')">Cancelar</x-ui.button>
                            <x-ui.button type="submit">Salvar campanha</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>

                <x-ui.modal name="campaign-finish-{{ $campaign->id }}" title="Finalizar campanha" max-width="md">
                    <form method="POST" action="{{ route('tenant.campaigns.destroy', $campaign) }}">
                        @csrf
                        @method('DELETE')
                        <p class="text-sm" style="color: var(--fg-muted);">A campanha sera finalizada e ficara disponivel no historico operacional.</p>
                        <div class="mt-5 flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'campaign-finish-{{ $campaign->id }}')">Cancelar</x-ui.button>
                            <x-ui.button type="submit" variant="danger">Finalizar campanha</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhuma campanha cadastrada.</p>
            @endforelse
        </div>
    </x-ui.card>

    <x-ui.modal name="campaign-create" title="Nova campanha">
        <form method="POST" action="{{ route('tenant.campaigns.store') }}">
            @csrf
            @include('tenant.campaigns._form', ['teams' => $teams, 'lists' => $lists])
            <div class="mt-5 flex justify-end">
                <x-ui.button type="submit">Criar campanha</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-app-layout>
