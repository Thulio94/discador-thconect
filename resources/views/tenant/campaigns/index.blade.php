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
            @can('tenant.campaigns.create')
                <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'campaign-create')">Nova campanha</x-ui.button>
            @endcan
        </div>

        <form method="GET" action="{{ route('tenant.campaigns.index') }}" class="mt-5 grid gap-3 xl:grid-cols-[minmax(0,1.2fr)_160px_160px_220px_220px_auto]">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar campanha" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            <select name="status" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todos status</option>
                @foreach (['draft' => 'Rascunho', 'active' => 'Ativa', 'paused' => 'Pausada', 'finished' => 'Finalizada'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="dialing_mode" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todos modos</option>
                @foreach (['preview' => 'Preview', 'manual' => 'Manual', 'power' => 'Power', 'predictive' => 'Preditivo'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['dialing_mode'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="team_id" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todas equipes</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected((int) ($filters['team_id'] ?? 0) === $team->id)>{{ $team->name }}</option>
                @endforeach
            </select>
            <select name="list_id" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todas listas</option>
                @foreach ($lists as $list)
                    <option value="{{ $list->id }}" @selected((int) ($filters['list_id'] ?? 0) === $list->id)>{{ $list->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <x-ui.button type="submit">Filtrar</x-ui.button>
                <a href="{{ route('tenant.campaigns.index') }}" class="th-focus inline-flex items-center justify-center px-4 py-2 text-sm font-semibold" style="border: 1px solid var(--border); background: var(--surface-alt); color: var(--fg); border-radius: var(--radius-md);">Limpar</a>
            </div>
        </form>

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
                            <p class="mt-1 text-xs" style="color: var(--fg-muted);">
                                Agenda {{ count($campaign->operational_schedule ?? []) }} dias configurados
                                @if (count($campaign->operational_schedule ?? []) > 0)
                                    | {{ ($scheduleStates[$campaign->id] ?? true) ? 'Dentro da agenda' : 'Fora da agenda' }}
                                @endif
                            </p>
                            @if (count($campaign->service_fields_schema ?? []) > 0)
                                <p class="mt-1 text-xs" style="color: var(--fg-muted);">
                                    Campos dinamicos: {{ collect($campaign->service_fields_schema)->pluck('label')->join(', ') }}
                                </p>
                            @endif
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
                        @can('tenant.campaigns.operate')
                        @if ($campaign->status === 'draft')
                            <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'campaign-start-{{ $campaign->id }}')">Iniciar</x-ui.button>
                        @endif
                        @if ($campaign->status === 'active')
                            <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'campaign-dial-{{ $campaign->id }}')">Rodar discagem</x-ui.button>
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'campaign-pause-{{ $campaign->id }}')">Pausar</x-ui.button>
                        @endif
                        @if ($campaign->status === 'paused')
                            <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'campaign-restart-{{ $campaign->id }}')">Reiniciar</x-ui.button>
                        @endif
                        <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'campaign-recycle-{{ $campaign->id }}')">Reciclar mailing</x-ui.button>
                        @endcan
                        @can('tenant.campaigns.create')
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'campaign-duplicate-{{ $campaign->id }}')">Duplicar</x-ui.button>
                        @endcan
                        @can('tenant.campaigns.update')
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'campaign-edit-{{ $campaign->id }}')">Editar</x-ui.button>
                        @endcan
                        @can('tenant.campaigns.delete')
                        @if ($campaign->status !== 'finished')
                            <x-ui.button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'campaign-finish-{{ $campaign->id }}')">Finalizar</x-ui.button>
                        @endif
                        @endcan
                    </div>
                </div>

                @can('tenant.campaigns.operate')
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

                <x-ui.modal name="campaign-dial-{{ $campaign->id }}" title="Rodar discagem simulada" max-width="md">
                    <form method="POST" action="{{ route('tenant.campaigns.dial', $campaign) }}">
                        @csrf
                        @method('PATCH')
                        <p class="text-sm" style="color: var(--fg-muted);">A campanha buscara contatos pendentes e agentes disponiveis para criar chamadas simuladas.</p>
                        <div class="mt-5 flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'campaign-dial-{{ $campaign->id }}')">Cancelar</x-ui.button>
                            <x-ui.button type="submit">Rodar discagem</x-ui.button>
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
                @endcan

                @can('tenant.campaigns.create')
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
                @endcan

                @can('tenant.campaigns.operate')
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
                @endcan

                @can('tenant.campaigns.update')
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
                @endcan

                @can('tenant.campaigns.delete')
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
                @endcan
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Nenhuma campanha cadastrada.</p>
            @endforelse
        </div>
        <div class="mt-5">{{ $campaigns->links() }}</div>
    </x-ui.card>

    @can('tenant.campaigns.create')
    <x-ui.modal name="campaign-create" title="Nova campanha">
        <form method="POST" action="{{ route('tenant.campaigns.store') }}">
            @csrf
            @include('tenant.campaigns._form', ['teams' => $teams, 'lists' => $lists])
            <div class="mt-5 flex justify-end">
                <x-ui.button type="submit">Criar campanha</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
    @endcan
</x-app-layout>
