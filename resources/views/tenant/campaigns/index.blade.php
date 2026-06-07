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
                @php($summary = $mailingSummaries[$campaign->id] ?? [])
                <div class="rounded-lg border p-4" style="border-color: var(--border);">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold">{{ $campaign->name }}</p>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                                {{ $campaign->teams->count() }} equipes | {{ $campaign->contactLists->count() }} listas | Modo {{ $campaign->dialing_mode }}
                            </p>
                            <p class="mt-1 text-xs" style="color: var(--fg-muted);">
                                Inicio {{ $campaign->starts_at?->format('d/m/Y H:i') ?? 'nao iniciado' }} | Fim {{ $campaign->ends_at?->format('d/m/Y H:i') ?? 'em aberto' }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs" style="color: var(--fg-muted);">
                                <span class="rounded-full px-2 py-1" style="background: var(--bg-subtle);">Pendentes {{ $summary['pending'] ?? 0 }}</span>
                                <span class="rounded-full px-2 py-1" style="background: var(--bg-subtle);">Discando {{ $summary['dialing'] ?? 0 }}</span>
                                <span class="rounded-full px-2 py-1" style="background: var(--bg-subtle);">Atendidos {{ $summary['answered'] ?? 0 }}</span>
                                <span class="rounded-full px-2 py-1" style="background: var(--bg-subtle);">Impro {{ $summary['unproductive'] ?? 0 }}</span>
                                <span class="rounded-full px-2 py-1" style="background: var(--bg-subtle);">Callback {{ $summary['callback'] ?? 0 }}</span>
                                <span class="rounded-full px-2 py-1" style="background: var(--bg-subtle);">Esgotados {{ $summary['exhausted'] ?? 0 }}</span>
                            </div>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs" style="background: var(--bg-subtle); color: var(--fg-muted);">{{ $campaign->status }}</span>
                    </div>
                    <div class="mt-4 flex flex-wrap justify-end gap-2">
                        @if ($campaign->status === 'draft')
                            <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'campaign-start-{{ $campaign->id }}')">Iniciar</x-ui.button>
                        @endif
                        @if ($campaign->status === 'active')
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'campaign-pause-{{ $campaign->id }}')">Pausar</x-ui.button>
                        @endif
                        @if ($campaign->status === 'paused')
                            <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'campaign-restart-{{ $campaign->id }}')">Reiniciar</x-ui.button>
                        @endif
                        <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'campaign-recycle-{{ $campaign->id }}')">Reciclar mailing</x-ui.button>
                        <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'campaign-duplicate-{{ $campaign->id }}')">Duplicar</x-ui.button>
                        <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'campaign-edit-{{ $campaign->id }}')">Editar</x-ui.button>
                        @if ($campaign->status !== 'finished')
                            <x-ui.button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'campaign-finish-{{ $campaign->id }}')">Finalizar</x-ui.button>
                        @endif
                    </div>
                </div>

                <x-ui.modal name="campaign-start-{{ $campaign->id }}" title="Iniciar campanha" max-width="md">
                    <form method="POST" action="{{ route('tenant.campaigns.start', $campaign) }}">
                        @csrf
                        @method('PATCH')
                        <p class="text-sm" style="color: var(--fg-muted);">A campanha ficara ativa para operacao simulada.</p>
                        <div class="mt-5 flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'campaign-start-{{ $campaign->id }}')">Cancelar</x-ui.button>
                            <x-ui.button type="submit">Iniciar campanha</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>

                <x-ui.modal name="campaign-pause-{{ $campaign->id }}" title="Pausar campanha" max-width="md">
                    <form method="POST" action="{{ route('tenant.campaigns.pause', $campaign) }}">
                        @csrf
                        @method('PATCH')
                        <p class="text-sm" style="color: var(--fg-muted);">A campanha sera pausada sem perder listas e equipes vinculadas.</p>
                        <div class="mt-5 flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'campaign-pause-{{ $campaign->id }}')">Cancelar</x-ui.button>
                            <x-ui.button type="submit" variant="secondary">Pausar campanha</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>

                <x-ui.modal name="campaign-restart-{{ $campaign->id }}" title="Reiniciar campanha" max-width="md">
                    <form method="POST" action="{{ route('tenant.campaigns.restart', $campaign) }}">
                        @csrf
                        @method('PATCH')
                        <p class="text-sm" style="color: var(--fg-muted);">A campanha voltara para ativa na operacao simulada.</p>
                        <div class="mt-5 flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'campaign-restart-{{ $campaign->id }}')">Cancelar</x-ui.button>
                            <x-ui.button type="submit">Reiniciar campanha</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>

                <x-ui.modal name="campaign-duplicate-{{ $campaign->id }}" title="Duplicar campanha" max-width="md">
                    <form method="POST" action="{{ route('tenant.campaigns.duplicate', $campaign) }}">
                        @csrf
                        <p class="text-sm" style="color: var(--fg-muted);">Sera criada uma copia em rascunho com as mesmas equipes e listas.</p>
                        <div class="mt-5 flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'campaign-duplicate-{{ $campaign->id }}')">Cancelar</x-ui.button>
                            <x-ui.button type="submit">Duplicar campanha</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>

                <x-ui.modal name="campaign-recycle-{{ $campaign->id }}" title="Reciclar mailing" max-width="md">
                    <form method="POST" action="{{ route('tenant.campaigns.recycle', $campaign) }}">
                        @csrf
                        @method('PATCH')
                        <p class="text-sm" style="color: var(--fg-muted);">Contatos improdutivos, callbacks e esgotados voltarao para pendente, sem zerar o historico de tentativas.</p>
                        <div class="mt-5 flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'campaign-recycle-{{ $campaign->id }}')">Cancelar</x-ui.button>
                            <x-ui.button type="submit">Reciclar mailing</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>

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
