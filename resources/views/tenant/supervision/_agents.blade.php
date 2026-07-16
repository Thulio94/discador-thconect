<div id="supervision-agents" class="mt-5 grid gap-3">
    @forelse ($agents as $agent)
        @php
            $activeCall = $agent->calls->first();
            $teamsText = count($agent->team_names) ? implode(', ', $agent->team_names) : 'Sem equipe';
            $status = [
                'available' => ['label' => 'Disponivel', 'color' => 'var(--status-success)', 'description' => 'Pronto para receber chamadas'],
                'paused' => ['label' => 'Em pausa', 'color' => 'var(--status-warning)', 'description' => 'Atendimento temporariamente pausado'],
                'in_call' => ['label' => 'Em chamada', 'color' => 'var(--status-info)', 'description' => 'Em atendimento ativo'],
                'wrap_up' => ['label' => 'Em tabulacao', 'color' => 'var(--status-purple)', 'description' => 'Registrando o resultado da chamada'],
                'offline' => ['label' => 'Offline', 'color' => 'var(--status-neutral)', 'description' => 'Sem sessao operacional'],
            ][$agent->supervision_status] ?? ['label' => 'Indisponivel', 'color' => 'var(--status-neutral)', 'description' => 'Status nao identificado'];
            $userType = ['agent' => 'Agente', 'supervisor' => 'Supervisor'][$agent->user_type] ?? 'Usuario';
            $channel = ['manual' => 'Manual', 'simulated' => 'Simulada', 'asterisk_ami' => 'Asterisk'][$activeCall?->channel] ?? $activeCall?->channel;
            $monitoringAvailable = $monitoringReady
                && $activeCall?->status === 'in_progress'
                && $activeCall?->channel === 'asterisk_ami'
                && filled($activeCall?->telephony_channel)
                && $agent->user_type === 'agent';
        @endphp
        <div id="supervision-agent-{{ $agent->id }}" class="rounded-lg border-2 p-4 transition-shadow" style="border-color: color-mix(in srgb, {{ $status['color'] }} 55%, var(--border)); box-shadow: 0 10px 24px color-mix(in srgb, {{ $status['color'] }} 16%, transparent);">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_260px] lg:items-center">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-semibold">{{ $agent->name }}</p>
                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-bold" style="background: color-mix(in srgb, {{ $status['color'] }} 18%, var(--surface)); color: {{ $status['color'] }}; box-shadow: 0 4px 12px color-mix(in srgb, {{ $status['color'] }} 18%, transparent);">
                            <span class="h-2 w-2 rounded-full" style="background: currentColor;"></span>{{ $status['label'] }}
                        </span>
                        @foreach ($agent->operational_alerts as $alert)
                            <span class="rounded-full px-2 py-1 text-xs font-semibold" style="background: color-mix(in srgb, var(--danger) 14%, var(--surface)); color: var(--danger);">{{ $alert }}</span>
                        @endforeach
                    </div>
                    <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                        Ramal {{ $agent->extension }} | {{ $userType }} | {{ $teamsText }}
                    </p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div class="rounded-lg border p-2" style="border-color: color-mix(in srgb, {{ $status['color'] }} 35%, var(--border)); background: color-mix(in srgb, {{ $status['color'] }} 8%, var(--surface-alt));">
                            <p class="text-[11px] font-semibold uppercase" style="color: var(--fg-muted);">Tempo no status</p>
                            <p class="mt-1 text-sm font-semibold">{{ $agent->status_duration_label }}</p>
                        </div>
                        <div class="rounded-lg border p-2" style="border-color: var(--border);">
                            <p class="text-[11px] font-semibold uppercase" style="color: var(--fg-muted);">Campanha atual</p>
                            <p class="mt-1 text-sm font-semibold">{{ $agent->current_campaign_name ?? 'Sem campanha' }}</p>
                        </div>
                    </div>
                </div>

                <div class="text-sm">
                    @if ($agent->supervision_status === 'wrap_up')
                        <p class="font-semibold">Tabulacao pendente</p>
                        <p style="color: var(--fg-muted);">Prazo restante: {{ $agent->wrap_up_duration_label ?? '0 min' }}</p>
                    @elseif ($activeCall)
                        <p class="font-semibold">Chamada ativa</p>
                        <p style="color: var(--fg-muted);">{{ $activeCall->phone ?? 'Sem numero' }} | {{ $channel }}</p>
                        <p class="mt-2 text-xs font-semibold" style="color: var(--fg);">Tempo em chamada</p>
                        <p class="text-xs" style="color: var(--fg-muted);">{{ $agent->active_call_duration_label }}</p>
                    @else
                        <p class="font-semibold">{{ $status['description'] }}</p>
                        <p style="color: var(--fg-muted);">Nenhuma chamada ativa</p>
                    @endif
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'supervision-agent-{{ $agent->id }}')">Detalhes</x-ui.button>
                    @can('tenant.supervision.operate')
                        @if ($monitoringAvailable)
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'supervision-monitor-listen-{{ $agent->id }}')">Ouvir</x-ui.button>
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'supervision-monitor-whisper-{{ $agent->id }}')">Sussurrar</x-ui.button>
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'supervision-monitor-barge-{{ $agent->id }}')">Intervir</x-ui.button>
                        @else
                            <span class="max-w-[250px] rounded-lg border px-3 py-2 text-xs font-medium" style="border-color: var(--border); color: var(--fg-muted);">
                                {{ $monitoringReason ?? 'Monitoria disponivel somente em chamadas Asterisk ativas de agentes.' }}
                            </span>
                        @endif
                    @endcan
                </div>
            </div>
        </div>

        <x-ui.modal name="supervision-agent-{{ $agent->id }}" title="Detalhes do agente" max-width="md">
            <div class="grid gap-3 text-sm">
                <p><span class="font-semibold">Nome:</span> {{ $agent->name }}</p>
                <p><span class="font-semibold">Ramal:</span> {{ $agent->extension }}</p>
                <p><span class="font-semibold">Status:</span> <span style="color: {{ $status['color'] }};">{{ $status['label'] }}</span></p>
                <p><span class="font-semibold">Tempo no status:</span> {{ $agent->status_duration_label }}</p>
                <p><span class="font-semibold">Equipes:</span> {{ $teamsText }}</p>
                <p><span class="font-semibold">Campanha atual:</span> {{ $agent->current_campaign_name ?? 'Sem campanha' }}</p>
                <p><span class="font-semibold">Tempo em chamada:</span> {{ $agent->active_call_duration_label ?? 'Sem chamada' }}</p>
                @if (count($agent->operational_alerts))
                    <p><span class="font-semibold">Alertas:</span> {{ implode(', ', $agent->operational_alerts) }}</p>
                @endif
                <p><span class="font-semibold">Monitoria:</span> {{ $monitoringAvailable ? 'Disponivel para esta chamada Asterisk.' : ($monitoringReason ?? 'Indisponivel para esta chamada.') }}</p>
            </div>
        </x-ui.modal>

        @if ($monitoringAvailable)
            @foreach ([
                'listen' => ['titulo' => 'Ouvir chamada', 'botao' => 'Iniciar escuta', 'descricao' => 'Voce ouvira a conversa sem ser percebido pelo agente ou pelo cliente.'],
                'whisper' => ['titulo' => 'Sussurrar para o agente', 'botao' => 'Iniciar sussurro', 'descricao' => 'Somente o agente ouvira sua voz; o cliente continuara sem ouvir sua intervencao.'],
                'barge' => ['titulo' => 'Intervir na chamada', 'botao' => 'Entrar na chamada', 'descricao' => 'Voce entrara na conversa e podera falar com o agente e o cliente.'],
            ] as $mode => $monitor)
                <x-ui.modal name="supervision-monitor-{{ $mode }}-{{ $agent->id }}" title="{{ $monitor['titulo'] }}" max-width="md">
                    <form method="POST" action="{{ route('tenant.supervision.monitor', $agent) }}" class="grid gap-4">
                        @csrf
                        <input type="hidden" name="mode" value="{{ $mode }}">
                        <p class="text-sm" style="color: var(--fg-muted);">{{ $monitor['descricao'] }}</p>
                        <div class="rounded-lg border p-3 text-sm" style="border-color: var(--border); background: var(--surface-alt);">
                            <p class="font-semibold">{{ $agent->name }}</p>
                            <p class="mt-1" style="color: var(--fg-muted);">Ramal {{ $agent->extension }} | {{ $activeCall->phone ?? 'Numero nao identificado' }}</p>
                        </div>
                        <div class="flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'supervision-monitor-{{ $mode }}-{{ $agent->id }}')">Cancelar</x-ui.button>
                            <x-ui.button type="submit">{{ $monitor['botao'] }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>
            @endforeach
        @endif
    @empty
        <p class="text-sm" style="color: var(--fg-muted);">Nenhum operador encontrado.</p>
    @endforelse
</div>
