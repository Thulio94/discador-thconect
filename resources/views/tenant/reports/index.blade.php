<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Analise</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Relatorios</h1>
        </div>
    </x-slot>

    <div class="grid gap-4">
        <x-ui.card>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <form method="GET" action="{{ route('tenant.reports.index') }}" class="grid gap-2 md:grid-cols-7">
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Inicio</span>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Fim</span>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Agente</span>
                        <select name="user_id" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                            <option value="">Todos</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected($filters['user_id'] === $agent->id)>{{ $agent->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Campanha</span>
                        <select name="campaign_id" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                            <option value="">Todas</option>
                            @foreach ($campaigns as $campaign)
                                <option value="{{ $campaign->id }}" @selected($filters['campaign_id'] === $campaign->id)>{{ $campaign->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Lista</span>
                        <select name="list_id" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                            <option value="">Todas</option>
                            @foreach ($lists as $list)
                                <option value="{{ $list->id }}" @selected($filters['list_id'] === $list->id)>{{ $list->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Equipe</span>
                        <select name="team_id" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                            <option value="">Todas</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}" @selected($filters['team_id'] === $team->id)>{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Resultado</span>
                        <select name="disposition_id" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                            <option value="">Todos</option>
                            @foreach ($dispositions as $disposition)
                                <option value="{{ $disposition->id }}" @selected($filters['disposition_id'] === $disposition->id)>{{ $disposition->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="md:col-span-7">
                        <x-ui.button type="submit" variant="secondary">Filtrar</x-ui.button>
                    </div>
                </form>

                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('tenant.reports.export', request()->only(['date_from', 'date_to', 'user_id', 'campaign_id', 'list_id', 'team_id', 'disposition_id'])) }}"
                        class="th-focus w-fit rounded-lg border px-3 py-2 text-xs font-semibold"
                        style="border-color: var(--border); color: var(--fg);"
                    >
                        Exportar CSV
                    </a>
                    <a
                        href="{{ route('tenant.reports.export-xlsx', request()->only(['date_from', 'date_to', 'user_id', 'campaign_id', 'list_id', 'team_id', 'disposition_id'])) }}"
                        class="th-focus w-fit rounded-lg border px-3 py-2 text-xs font-semibold"
                        style="border-color: var(--border); color: var(--fg);"
                    >
                        Exportar XLSX
                    </a>
                    <a
                        href="{{ route('tenant.reports.export-pdf', request()->only(['date_from', 'date_to', 'user_id', 'campaign_id', 'list_id', 'team_id', 'disposition_id'])) }}"
                        class="th-focus w-fit rounded-lg border px-3 py-2 text-xs font-semibold"
                        style="border-color: var(--border); color: var(--fg);"
                    >
                        Exportar PDF
                    </a>
                </div>
            </div>
        </x-ui.card>

        <div class="grid gap-3 md:grid-cols-5 xl:grid-cols-10">
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Chamadas totais</p><p class="mt-2 text-2xl font-bold">{{ $metrics['total_calls'] }} total</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Em andamento</p><p class="mt-2 text-2xl font-bold">{{ $metrics['in_progress_calls'] }} andamento</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Encerradas</p><p class="mt-2 text-2xl font-bold">{{ $metrics['ended_calls'] }} encerrada</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">TMA</p><p class="mt-2 text-2xl font-bold">{{ $metrics['avg_talk_time_label'] }}</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">TME</p><p class="mt-2 text-2xl font-bold">{{ $metrics['avg_wait_time_label'] }}</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Conversoes</p><p class="mt-2 text-2xl font-bold">{{ $metrics['conversion_count'] }} convertida</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Taxa conversao</p><p class="mt-2 text-2xl font-bold">{{ $metrics['conversion_rate_label'] }}</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Agentes disponiveis</p><p class="mt-2 text-2xl font-bold">{{ $metrics['available_agents'] }} disponivel</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Agentes em pausa</p><p class="mt-2 text-2xl font-bold">{{ $metrics['paused_agents'] }} pausa</p></x-ui.card>
            <x-ui.card><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Agentes em chamada</p><p class="mt-2 text-2xl font-bold">{{ $metrics['in_call_agents'] }} chamada</p></x-ui.card>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <x-ui.card>
                <h2 class="text-lg font-semibold">Performance por agente</h2>
                <div class="mt-4 grid gap-2">
                    @forelse ($agentPerformance as $agent)
                        <div class="flex items-center justify-between rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                            <span>{{ $agent->name }}</span>
                            <span class="text-right font-semibold">{{ $agent->calls_count }} chamadas<br><span class="text-xs" style="color: var(--fg-muted);">TMA {{ $agent->avg_talk_time_label }}</span><br><span class="text-xs" style="color: var(--fg-muted);">Conv {{ $agent->conversion_count }} | {{ $agent->conversion_rate_label }}</span></span>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--fg-muted);">Sem dados de agentes.</p>
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card>
                <h2 class="text-lg font-semibold">Performance por equipe</h2>
                <div class="mt-4 grid gap-2">
                    @forelse ($teamPerformance as $team)
                        <div class="flex items-center justify-between rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                            <span>{{ $team->name }}</span>
                            <span class="text-right font-semibold">{{ $team->calls_count }} chamadas<br><span class="text-xs" style="color: var(--fg-muted);">Conv {{ $team->conversion_count }} | {{ $team->conversion_rate_label }}</span></span>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--fg-muted);">Sem dados de equipes.</p>
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card>
                <h2 class="text-lg font-semibold">Performance por campanha</h2>
                <div class="mt-4 grid gap-2">
                    @forelse ($campaignPerformance as $campaign)
                        <div class="flex items-center justify-between rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                            <span>{{ $campaign->name }}</span>
                            <span class="text-right font-semibold">{{ $campaign->calls_count }} chamadas<br><span class="text-xs" style="color: var(--fg-muted);">TMA {{ $campaign->avg_talk_time_label }}</span><br><span class="text-xs" style="color: var(--fg-muted);">Conv {{ $campaign->conversion_count }} | {{ $campaign->conversion_rate_label }}</span></span>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--fg-muted);">Sem dados de campanhas.</p>
                    @endforelse
                </div>
            </x-ui.card>
        </div>

        <x-ui.card>
            <h2 class="text-lg font-semibold">Resultados por tabulacao</h2>
            <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-4">
                @forelse ($dispositionSummary as $result)
                    <div class="rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                        <p class="font-semibold">{{ $result->disposition_name }}</p>
                        <p class="mt-1 text-xs" style="color: var(--fg-muted);">{{ $result->disposition_code }}</p>
                        <p class="mt-3 text-xl font-bold">{{ $result->calls_count }} chamadas</p>
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--fg-muted);">Sem resultados tabulados.</p>
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-semibold">Historico de exportacoes</h2>
                <span class="text-xs" style="color: var(--fg-muted);">{{ $exportHistory->count() }} recentes</span>
            </div>
            <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-4">
                @forelse ($exportHistory as $export)
                    <div class="rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                        <div class="flex items-start justify-between gap-3">
                            <span class="font-semibold">{{ strtoupper($export->format) }}</span>
                            <span class="text-xs" style="color: var(--fg-muted);">{{ $export->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <p class="mt-2 text-xs" style="color: var(--fg-muted);">{{ $export->user?->name ?? 'Usuario removido' }}</p>
                        <p class="mt-3 font-semibold">{{ $export->records_count }} {{ $export->records_count === 1 ? 'registro' : 'registros' }}</p>
                        <p class="mt-1 text-xs" style="color: var(--fg-muted);">{{ $export->filter_label }}</p>
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--fg-muted);">Nenhuma exportacao registrada.</p>
                @endforelse
            </div>
        </x-ui.card>

        <div class="grid gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
            <x-ui.card>
                <h2 class="text-lg font-semibold">Mailing por lista</h2>
                <p class="mt-1 text-xs" style="color: var(--fg-muted);">Resumo de listas por status do mailing.</p>
                <div class="mt-4 grid gap-2">
                    @forelse ($mailingSummaries as $list)
                        @php($conversion = $list->contacts_count > 0 ? round(($list->answered_contacts_count / $list->contacts_count) * 100) : 0)
                        <div class="grid gap-3 rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                            <div class="flex items-start justify-between gap-3">
                                <span class="font-semibold">{{ $list->name }}</span>
                                <span class="whitespace-nowrap font-semibold">{{ $list->contacts_count }} contatos</span>
                            </div>
                            <div class="grid gap-2 text-xs sm:grid-cols-2">
                                <span class="rounded-lg px-2 py-1" style="background: var(--bg-subtle); color: var(--fg-muted);">Pendentes {{ $list->pending_contacts_count }}</span>
                                <span class="rounded-lg px-2 py-1" style="background: var(--bg-subtle); color: var(--fg-muted);">Discando {{ $list->dialing_contacts_count }}</span>
                                <span class="rounded-lg px-2 py-1" style="background: var(--bg-subtle); color: var(--fg-muted);">Atendidos {{ $list->answered_contacts_count }}</span>
                                <span class="rounded-lg px-2 py-1" style="background: var(--bg-subtle); color: var(--fg-muted);">Impro {{ $list->unproductive_contacts_count }}</span>
                                <span class="rounded-lg px-2 py-1" style="background: var(--bg-subtle); color: var(--fg-muted);">Callback {{ $list->callback_contacts_count }}</span>
                                <span class="rounded-lg px-2 py-1" style="background: var(--bg-subtle); color: var(--fg-muted);">Esgotados {{ $list->exhausted_contacts_count }}</span>
                            </div>
                            <p class="text-xs font-semibold" style="color: var(--fg);">Aproveitamento {{ $conversion }}%</p>
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--fg-muted);">Sem listas cadastradas.</p>
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-semibold">Analitico de chamadas</h2>
                    <span class="text-xs" style="color: var(--fg-muted);">{{ $calls->total() }} registros</span>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead style="color: var(--fg-muted);">
                            <tr class="border-b" style="border-color: var(--border);">
                                <th class="px-3 py-2 font-semibold">Telefone</th>
                                <th class="px-3 py-2 font-semibold">Contato</th>
                                <th class="px-3 py-2 font-semibold">Agente</th>
                                <th class="px-3 py-2 font-semibold">Campanha</th>
                                <th class="px-3 py-2 font-semibold">Resultado</th>
                                <th class="px-3 py-2 font-semibold">Status</th>
                                <th class="px-3 py-2 font-semibold">Inicio</th>
                                <th class="px-3 py-2 font-semibold">Duracao</th>
                                <th class="px-3 py-2 font-semibold">Espera</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($calls as $call)
                                <tr class="border-b align-top" style="border-color: var(--border);">
                                    <td class="px-3 py-3 font-semibold">{{ $call->phone ?? 'Sem numero' }}</td>
                                    <td class="px-3 py-3">{{ $call->contact?->name ?? 'Sem contato' }}</td>
                                    <td class="px-3 py-3">{{ $call->user?->name ?? 'Sem agente' }}</td>
                                    <td class="px-3 py-3">{{ $call->campaign?->name ?? 'Sem campanha' }}</td>
                                    <td class="px-3 py-3">{{ $call->disposition_name ?? 'Sem tabulacao' }}</td>
                                    <td class="px-3 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs" style="background: var(--bg-subtle); color: var(--fg-muted);">
                                            {{ $call->status }} | {{ $call->channel }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3">{{ $call->started_at?->format('d/m/Y H:i') ?? 'sem data' }}</td>
                                    <td class="px-3 py-3">{{ $call->talk_time_label }}</td>
                                    <td class="px-3 py-3">{{ $call->wait_time_label }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-3 py-4 text-sm" style="color: var(--fg-muted);">Nenhuma chamada encontrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pagination">
                    {{ $calls->links() }}
                </div>

                <div class="mt-4 grid gap-2">
                    @forelse ($calls as $call)
                        <div class="grid gap-2 rounded-lg border p-3 text-sm md:hidden" style="border-color: var(--border);">
                            <div class="flex items-start justify-between gap-3">
                                <span class="font-semibold">{{ $call->phone ?? 'Sem numero' }}</span>
                                <span style="color: var(--fg-muted);">{{ $call->status }}</span>
                            </div>
                            <span>{{ $call->user?->name ?? 'Sem agente' }} | {{ $call->campaign?->name ?? 'Sem campanha' }}</span>
                            <span style="color: var(--fg-muted);">{{ $call->disposition_name ?? 'Sem tabulacao' }}</span>
                            <span style="color: var(--fg-muted);">Duracao {{ $call->talk_time_label }} | Espera {{ $call->wait_time_label }}</span>
                        </div>
                    @empty
                    @endforelse
                </div>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
