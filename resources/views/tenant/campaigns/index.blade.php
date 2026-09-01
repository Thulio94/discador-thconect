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

        <form method="GET" action="{{ route('tenant.campaigns.index') }}" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-[minmax(220px,1.2fr)_160px_160px_220px_220px_auto]">
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar campanha" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            <select name="status" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todos status</option>
                @foreach (['draft' => 'Rascunho', 'active' => 'Ativa', 'paused' => 'Pausada', 'finished' => 'Finalizada'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="dialing_mode" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="">Todos modos</option>
                @foreach (['power' => 'Power', 'preview' => 'Preview'] as $value => $label)
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
                @php
                    $summary = $mailingSummaries[$campaign->id] ?? [];
                @endphp
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
                            <p class="mt-1 text-xs" style="color: var(--fg-muted);">
                                Distribuicao {{ ['least_idle' => 'mais ocioso', 'balanced' => 'equilibrado', 'random' => 'aleatorio'][(string) ($campaign->agent_distribution_strategy ?? 'least_idle')] ?? 'mais ocioso' }} | Tentativas {{ $campaign->max_attempts_per_contact ?: 'pela tabulacao' }} | Tabulacao {{ $campaign->wrap_up_seconds ?? 60 }}s | AMD {{ ['simulated' => 'simulado', 'asterisk' => 'Asterisk'][(string) $campaign->amd_mode] ?? 'desativado' }} | Fixo {{ $campaign->fixedTelephonyRoute?->name ?: '-' }} | Movel {{ $campaign->mobileTelephonyRoute?->name ?: '-' }}
                            </p>
                            @php
                                $totalMailing = array_sum($summary);
                                $mailingLabels = ['pending' => 'Pendentes', 'dialing' => 'Discando', 'delivered' => 'Entregues', 'abandoned' => 'Abandonadas', 'voicemail_pre' => 'Caixa postal pré', 'voicemail_post' => 'Caixa postal pós', 'no_answer' => 'Não atendidas', 'failed' => 'Falhas'];
                                $mailingColors = ['pending' => '#64748b', 'dialing' => '#2563eb', 'delivered' => '#16a34a', 'abandoned' => '#e11d48', 'voicemail_pre' => '#a855f7', 'voicemail_post' => '#d946ef', 'no_answer' => '#f59e0b', 'failed' => '#ef4444'];
                            @endphp
                            <div class="mt-3 flex h-2 overflow-hidden rounded-full" title="Andamento do mailing">
                                @foreach ($summary as $state => $count)
                                    @php
                                        $color = $mailingColors[$state] ?? '#64748b';
                                    @endphp
                                    @if ($totalMailing > 0 && $count > 0)
                                        <span style="width: {{ ($count / $totalMailing) * 100 }}%; background: {{ $color }};"></span>
                                    @endif
                                @endforeach
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs" style="color: var(--fg-muted);">
                                @foreach ($summary as $state => $count)
                                    @php
                                        $color = $mailingColors[$state] ?? '#64748b';
                                    @endphp
                                    <span class="rounded-full border px-2 py-1" style="border-color: {{ $color }}; background: var(--surface-alt); color: {{ $color }};">{{ $mailingLabels[$state] ?? $state }} {{ $count }} ({{ $totalMailing ? number_format(($count / $totalMailing) * 100, 2, ',', '.') : '0,00' }}%)</span>
                                @endforeach
                            </div>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs" style="background: var(--bg-subtle); color: var(--fg-muted);">{{ $campaign->status }}</span>
                    </div>
                    <div class="mt-4 flex flex-wrap justify-end gap-2">
                        <x-ui.action icon="view" label="Visualizar" x-data x-on:click="$dispatch('open-modal', 'campaign-view-{{ $campaign->id }}')" />
                        @can('tenant.campaigns.operate')
                        @if ($campaign->status === 'draft')
                            <x-ui.action icon="play" label="Iniciar campanha" variant="primary" x-data x-on:click="$dispatch('open-modal', 'campaign-start-{{ $campaign->id }}')" />
                        @endif
                        @if ($campaign->status === 'active')
                            <x-ui.action icon="pause" label="Pausar campanha" variant="warning" x-data x-on:click="$dispatch('open-modal', 'campaign-pause-{{ $campaign->id }}')" />
                        @endif
                        @if ($campaign->status === 'paused')
                            <x-ui.action icon="play" label="Retomar campanha" variant="primary" x-data x-on:click="$dispatch('open-modal', 'campaign-restart-{{ $campaign->id }}')" />
                        @endif
                        <x-ui.action icon="refresh" label="Reciclar mailing" x-data x-on:click="$dispatch('open-modal', 'campaign-recycle-{{ $campaign->id }}')" />
                        @endcan
                        @can('tenant.campaigns.create')
                            <x-ui.action icon="add" label="Duplicar campanha" x-data x-on:click="$dispatch('open-modal', 'campaign-duplicate-{{ $campaign->id }}')" />
                        @endcan
                        @can('tenant.campaigns.update')
                            <x-ui.action icon="edit" label="Editar campanha" x-data x-on:click="$dispatch('open-modal', 'campaign-edit-{{ $campaign->id }}')" />
                        @endcan
                        @can('tenant.campaigns.delete')
                        @if ($campaign->status !== 'finished')
                            <x-ui.action icon="delete" label="Finalizar campanha" variant="danger" x-data x-on:click="$dispatch('open-modal', 'campaign-finish-{{ $campaign->id }}')" />
                        @endif
                        @endcan
                    </div>
                </div>

                <x-ui.modal name="campaign-view-{{ $campaign->id }}" title="Detalhes da campanha" max-width="lg">
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt class="font-semibold">Nome</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $campaign->name }}</dd></div>
                        <div><dt class="font-semibold">Status</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $campaign->status }}</dd></div>
                        <div><dt class="font-semibold">Modo de discagem</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $campaign->dialing_mode }}</dd></div>
                        <div><dt class="font-semibold">Distribuicao</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ ['least_idle' => 'Mais ocioso', 'balanced' => 'Equilibrado', 'random' => 'Aleatorio'][(string) ($campaign->agent_distribution_strategy ?? 'least_idle')] ?? 'Mais ocioso' }}</dd></div>
                        <div><dt class="font-semibold">Rota para fixo</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $campaign->fixedTelephonyRoute?->name ?: 'Nao configurada' }}</dd></div>
                        <div><dt class="font-semibold">Rota para móvel</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $campaign->mobileTelephonyRoute?->name ?: 'Nao configurada' }}</dd></div>
                        <div><dt class="font-semibold">Equipes</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $campaign->teams->pluck('name')->join(', ') ?: 'Sem equipe' }}</dd></div>
                        <div><dt class="font-semibold">Listas</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $campaign->contactLists->pluck('name')->join(', ') ?: 'Sem lista' }}</dd></div>
                        <div><dt class="font-semibold">Inicio</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $campaign->starts_at?->format('d/m/Y H:i') ?? 'Nao iniciada' }}</dd></div>
                        <div><dt class="font-semibold">Fim</dt><dd class="mt-1" style="color: var(--fg-muted);">{{ $campaign->ends_at?->format('d/m/Y H:i') ?? 'Em aberto' }}</dd></div>
                    </dl>
                </x-ui.modal>

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
                        <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <p class="text-sm" style="color: var(--fg-muted);">Apenas os telefones selecionados retornam para pendente. Chamadas, tentativas e tabulações anteriores são preservadas.</p>
                        <label class="mt-4 block text-sm font-medium">
                            Lista da campanha
                            <select name="list_id" required class="mt-1 w-full rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface);">
                                @foreach ($campaign->contactLists as $list)
                                    <option value="{{ $list->id }}">{{ $list->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <fieldset class="mt-4">
                            <legend class="text-sm font-medium">Resultados a reciclar</legend>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                                @foreach (['no_answer' => 'Não atendida', 'abandoned' => 'Abandonada', 'voicemail_pre' => 'Caixa postal pré', 'voicemail_post' => 'Caixa postal pós', 'failed' => 'Falha', 'delivered' => 'Finalizada'] as $value => $label)
                                    <label class="flex items-center gap-2"><input type="checkbox" name="statuses[]" value="{{ $value }}" @checked(in_array($value, ['no_answer', 'abandoned', 'failed'], true))> {{ $label }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                        @if ($dispositions->isNotEmpty())
                            <fieldset class="mt-4">
                                <legend class="text-sm font-medium">Tabulações finalizadas (opcional)</legend>
                                <p class="mt-1 text-xs" style="color: var(--fg-muted);">Quando marcada, a reciclagem filtra também por estas tabulações.</p>
                                <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                                    @foreach ($dispositions as $disposition)
                                        <label class="flex items-center gap-2"><input type="checkbox" name="disposition_ids[]" value="{{ $disposition->id }}"> {{ $disposition->name }}</label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif
                        <div class="mt-5 flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'campaign-recycle-{{ $campaign->id }}')">Cancelar</x-ui.button>
                            <x-ui.button type="submit">Reciclar mailing</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>
                @endcan

                @can('tenant.campaigns.update')
                <x-ui.modal name="campaign-edit-{{ $campaign->id }}" title="Editar campanha" max-width="6xl" :show="$errors->any() && old('_campaign_form') === 'edit' && (int) old('_campaign_id') === $campaign->id">
                    <form method="POST" action="{{ route('tenant.campaigns.update', $campaign) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_campaign_form" value="edit">
                        <input type="hidden" name="_campaign_id" value="{{ $campaign->id }}">
                        @include('tenant.campaigns._form', ['campaign' => $campaign, 'teams' => $teams, 'lists' => $lists, 'telephonyRoutes' => $telephonyRoutes])
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
    <x-ui.modal name="campaign-create" title="Nova campanha" max-width="6xl" :show="$errors->any() && old('_campaign_form', 'create') === 'create'">
        <form method="POST" action="{{ route('tenant.campaigns.store') }}">
            @csrf
            <input type="hidden" name="_campaign_form" value="create">
            @if ($errors->any() && old('_campaign_form', 'create') === 'create')
                <div class="mb-4 rounded-lg border p-3 text-sm" role="alert" style="border-color: #ef4444; color: #b91c1c;">
                    <p class="font-semibold">A campanha nao foi salva.</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @include('tenant.campaigns._form', ['teams' => $teams, 'lists' => $lists, 'telephonyRoutes' => $telephonyRoutes])
            <div class="mt-5 flex justify-end">
                <x-ui.button type="submit">Criar campanha</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
    @if ($errors->any() && old('_campaign_form', 'create') === 'create')
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: {
                        title: 'Campanha nao salva',
                        message: @js($errors->first()),
                        severity: 'error',
                    },
                }));
            });
        </script>
    @endif
    @endcan
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let refreshTimer;
            window.subscribeToCampaignMetrics?.({{ auth()->user()->company_id }}, () => {
                window.clearTimeout(refreshTimer);
                refreshTimer = window.setTimeout(() => window.location.reload(), 750);
            });
        });
    </script>
</x-app-layout>
