<x-app-layout>
    @php
        $statusMeta = [
            'available' => ['label' => 'Disponivel', 'color' => 'var(--status-success)', 'tone' => 'emerald'],
            'dialing' => ['label' => 'Discando', 'color' => 'var(--status-info)', 'tone' => 'cyan'],
            'paused' => ['label' => 'Em pausa', 'color' => 'var(--status-warning)', 'tone' => 'amber'],
            'in_call' => ['label' => 'Em chamada', 'color' => 'var(--primary)', 'tone' => 'cyan'],
            'wrap_up' => ['label' => 'Em tabulacao', 'color' => 'var(--status-purple)', 'tone' => 'violet'],
            'offline' => ['label' => 'Offline', 'color' => 'var(--fg-muted)', 'tone' => null],
        ];
        $currentStatusMeta = $statusMeta[$currentStatus] ?? $statusMeta['offline'];
        $currentCallState = match ($currentCall?->status) {
            'dialing' => ['label' => 'Discando', 'color' => 'var(--status-info)'],
            'ringing' => ['label' => 'Chamando', 'color' => 'var(--status-warning)'],
            'in_progress' => ['label' => 'Em atendimento', 'color' => 'var(--status-success)'],
            default => null,
        };
        $canMakeManualCall = in_array($currentStatus, ['available', 'paused'], true);
        $callForDisposition = $wrapUpCall ?? $currentCall;
        $activeContact = $callForDisposition?->contact;
        $mailingCopy = null;
        if ($activeContact) {
            $mailingLines = [
                'Lead: '.($activeContact->name ?? 'Nao informado'),
                'Telefone atual: '.($callForDisposition?->phone ?? $activeContact->phone ?? 'Nao informado'),
                'Campanha: '.($callForDisposition?->campaign?->name ?? 'Sem campanha'),
                'Lista: '.($activeContact->contactList?->name ?? 'Sem lista'),
            ];
            foreach ($activeContact->phones as $contactPhone) {
                $mailingLines[] = 'Telefone: '.$contactPhone->phone.($contactPhone->is_primary ? ' (Principal)' : '');
            }
            foreach (($activeContact->extra ?? []) as $label => $value) {
                $mailingLines[] = str_replace('_', ' ', $label).': '.(is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE));
            }
            $mailingCopy = implode("\n", $mailingLines);
        }
        $serviceFieldsSchema = $callForDisposition?->campaign?->service_fields_schema ?: [
            ['key' => 'documento', 'label' => 'Documento', 'type' => 'text', 'required' => false],
            ['key' => 'protocolo', 'label' => 'Protocolo', 'type' => 'text', 'required' => false],
            ['key' => 'valor', 'label' => 'Valor', 'type' => 'number', 'required' => false],
        ];
    @endphp

    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Operacao</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Painel do Agente</h1>
        </div>
    </x-slot>

        <div
            x-data="agentConsoleSync({
            url: @js(route('tenant.agent.state')),
            statusUrl: @js(route('tenant.agent.status')),
            csrfToken: @js(csrf_token()),
            signature: @js($agentStateSignature),
            initialState: @js($agentConsoleState),
            intervalSeconds: 5,
            subscribe: (callback) => subscribeToAgentConsole(@js(auth()->user()->company_id), @js(auth()->id()), callback),
        })"
        x-init="start()"
        x-on:visibilitychange.document="handleVisibilityChange()"
        x-on:agent-console:reconcile.window="check(true)"
            x-on:beforeunload.window="destroy()"
        >
        @if ($callFailureMessage)
            <div class="mb-4 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm" style="border-color: color-mix(in srgb, var(--status-danger) 45%, var(--border)); background: color-mix(in srgb, var(--status-danger) 8%, var(--surface)); color: var(--status-danger);" role="alert">
                <x-ui.icon name="warning" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>{{ $callFailureMessage }}</span>
            </div>
        @endif

        <div
            class="mb-4 flex items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm"
            style="border-color: var(--border); background: var(--surface);"
            x-data="agentSoftphone({
                sessionUrl: @js(route('tenant.agent.softphone.session')),
                csrfToken: @js(csrf_token()),
                statusUrl: @js(route('tenant.agent.status')),
                currentStatus: @js($currentStatus),
                shouldMarkOffline: () => ['available', 'paused', 'dialing', 'in_call'].includes(agentStatus),
                acceptIncoming: @js(in_array($currentStatus, ['available', 'paused', 'dialing', 'in_call'], true)),
                acceptIncomingNow: () => ['available', 'paused', 'dialing', 'in_call'].includes(agentStatus),
            })"
            x-init="start()"
            x-on:beforeunload.window="stop()"
        >
            <div class="min-w-0">
                <p class="font-semibold">Audio do ramal</p>
                <p class="text-xs" style="color: var(--fg-muted);" x-text="({ preparing: 'Preparando microfone...', registered: 'Conectado e pronto para chamadas', reconnecting: 'Reconectando o ramal...', failed: 'Ramal desconectado', 'microphone-denied': 'Microfone bloqueado no navegador', unsupported: 'Navegador sem suporte a WebRTC', 'playback-blocked': 'Reproducao de audio bloqueada', offline: 'Audio desconectado' })[state] ?? 'Verificando audio'"></p>
            </div>
            <span class="h-3 w-3 shrink-0 rounded-full" x-bind:style="`background: ${registered ? 'var(--status-success)' : state === 'preparing' || state === 'reconnecting' ? 'var(--status-warning)' : 'var(--status-danger)'}`"></span>
            <audio data-agent-remote-audio autoplay playsinline class="hidden"></audio>
        </div>

        <div x-show="$store.softphone.callActive" x-cloak class="mb-4 flex items-center gap-3 rounded-lg border px-4 py-3 text-sm" style="border-color: color-mix(in srgb, var(--status-info) 45%, var(--border)); background: color-mix(in srgb, var(--status-info) 8%, var(--surface));">
            <x-ui.icon name="phone" class="h-4 w-4 shrink-0" />
            <span x-text="$store.softphone.callStatus === 'dialing' ? 'Ligação em preparação. Aguarde o atendimento.' : 'Ligação em andamento. Aguarde o áudio do ramal.'">Ligação em preparação. Aguarde o atendimento.</span>
        </div>

        <div class="agent-panel grid w-full min-w-0 max-w-full grid-cols-[minmax(0,1fr)] gap-4">
        <x-ui.card x-bind:data-tone="agentStatus === 'available' ? 'emerald' : agentStatus === 'dialing' || agentStatus === 'in_call' ? 'cyan' : agentStatus === 'paused' ? 'amber' : agentStatus === 'wrap_up' ? 'violet' : null">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex min-w-0 items-center gap-4">
                    <span class="h-4 w-4 shrink-0 rounded-full" x-bind:style="`background: ${statusColor()}; box-shadow: 0 0 0 6px color-mix(in srgb, ${statusColor()} 15%, transparent);`"></span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Status atual</p>
                        <div class="mt-1 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                            <h2 class="text-2xl font-bold" x-bind:style="`color: ${statusColor()};`" x-text="statusLabel()">{{ $currentStatusMeta['label'] }}</h2>
                            <span class="font-mono text-lg font-semibold" x-text="formatDuration(statusElapsedSeconds)">00:00:00</span>
                        </div>
                        <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                            Ramal {{ auth()->user()->extension }} | {{ auth()->user()->name }}
                        </p>
                    </div>
                </div>

                <div x-show="call?.status === 'wrap_up'" x-cloak class="rounded-lg border px-4 py-3 text-sm font-semibold" style="border-color: color-mix(in srgb, var(--status-purple) 45%, var(--border)); background: color-mix(in srgb, var(--status-purple) 10%, var(--surface)); color: var(--status-purple);">
                    Conclua a tabulacao para liberar novas chamadas
                </div>
                <div x-show="callActive && call?.status === 'dialing'" x-cloak class="rounded-lg border px-4 py-3 text-sm font-semibold" style="border-color: color-mix(in srgb, var(--status-info) 45%, var(--border)); background: color-mix(in srgb, var(--status-info) 10%, var(--surface)); color: var(--status-info);">
                    Aguardando a resposta da chamada
                </div>
                <div class="flex flex-wrap gap-2" x-show="!call">
                        <form method="POST" action="{{ route('tenant.agent.status') }}" x-on:submit.prevent="setAgentStatus('available')">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="available">
                            <x-ui.button type="submit" x-bind:disabled="!$store.softphone.registered || isCurrentStatus('available')" x-bind:aria-pressed="isCurrentStatus('available')">Disponivel</x-ui.button>
                        </form>
                        <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'agent-pause')" x-bind:aria-label="pauseActionLabel()" x-bind:title="pauseActionLabel()" x-text="pauseActionLabel()">
                            Pausar
                        </x-ui.button>
                        <form method="POST" action="{{ route('tenant.agent.status') }}" x-on:submit.prevent="setAgentStatus('offline')">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="offline">
                            <x-ui.button type="submit" variant="ghost" x-bind:disabled="isCurrentStatus('offline')" x-bind:aria-pressed="isCurrentStatus('offline')">Ficar offline</x-ui.button>
                        </form>
                </div>
            </div>
        </x-ui.card>

        @if ($eligibleCampaigns->isNotEmpty())
            <x-ui.card>
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Campanha operacional</p>
                        <p class="mt-1 font-semibold">{{ $selectedCampaign?->name ?? 'Nenhuma campanha selecionada' }}</p>
                    </div>
                    @if ($canSelectCampaign)
                        <form method="POST" action="{{ route('tenant.agent.campaign.select') }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <select name="campaign_id" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface);">
                                @foreach ($eligibleCampaigns as $campaignOption)
                                    <option value="{{ $campaignOption->id }}" @selected($selectedCampaign?->id === $campaignOption->id)>{{ $campaignOption->name }} · {{ strtoupper($campaignOption->dialing_mode) }}</option>
                                @endforeach
                            </select>
                            <x-ui.button type="submit" variant="secondary">Selecionar</x-ui.button>
                        </form>
                    @endif
                </div>
            </x-ui.card>
        @endif

        <div class="agent-workspace">
            <div class="grid min-w-0 gap-4">
                <x-ui.card x-show="call" x-cloak>
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Atendimento atual</p>
                            <h2 class="mt-1 text-2xl font-bold" x-text="call?.phone ?? 'Sem numero'">Sem numero</h2>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);" x-text="`${call?.campaign_name ?? 'Chamada manual'} | ${call?.channel ?? 'Canal indisponivel'}`">Chamada manual</p>
                            <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" style="border: 1px solid color-mix(in srgb, var(--status-info) 35%, var(--border)); background: color-mix(in srgb, var(--status-info) 10%, var(--surface)); color: var(--status-info);" x-text="({ dialing: 'Discando', ringing: 'Chamando', in_progress: 'Em atendimento', wrap_up: 'Aguardando tabulacao' })[call?.status] ?? 'Atualizando'">Atualizando</span>
                            <div class="mt-3 flex items-center gap-2 text-xs" style="color: var(--fg-muted);">
                                <span x-text="`Protocolo ${call?.protocol ?? 'indisponivel'}`">Protocolo indisponivel</span>
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg border p-3" style="border-color: var(--border); background: var(--surface-alt);">
                                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Contato</p>
                                    <p class="mt-1 font-semibold" x-text="call?.contact_name ?? 'Nao informado'">Nao informado</p>
                                </div>
                                <div class="rounded-lg border p-3" style="border-color: var(--border);">
                                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Lista</p>
                                    <p class="mt-1 font-semibold" x-text="call?.list_name ?? 'Chamada manual'">Chamada manual</p>
                                </div>
                            </div>
                        </div>
                        <form x-show="callActive" method="POST" action="{{ route('tenant.agent.calls.hangup') }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="call_id" x-bind:value="call?.id">
                            <x-ui.button type="submit" variant="danger">Encerrar chamada</x-ui.button>
                        </form>
                    </div>
                </x-ui.card>

                <x-ui.card x-show="!call">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Atendimento atual</p>
                            @if ($currentCall)
                                <h2 class="mt-1 text-2xl font-bold">{{ $currentCall->phone ?? 'Sem numero' }}</h2>
                                <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                                    {{ $currentCall->campaign?->name ?? 'Chamada manual' }} | {{ $currentCall->channel }}
                                </p>
                                @if ($currentCallState)
                                    <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" style="border: 1px solid color-mix(in srgb, {{ $currentCallState['color'] }} 35%, var(--border)); background: color-mix(in srgb, {{ $currentCallState['color'] }} 10%, var(--surface)); color: {{ $currentCallState['color'] }};">
                                        {{ $currentCallState['label'] }}
                                    </span>
                                @endif
                                <div class="mt-3 flex items-center gap-2 text-xs" style="color: var(--fg-muted);">
                                    <span>Protocolo {{ $currentCall->protocol }}</span>
                                    <button type="button" class="th-focus rounded border px-2 py-1 font-semibold" style="border-color: var(--border); color: var(--primary);" x-data x-on:click="navigator.clipboard.writeText(@js($currentCall->protocol)); window.dispatchEvent(new CustomEvent('toast', { detail: { title: 'Protocolo copiado', message: 'O protocolo foi copiado para a area de transferencia.', severity: 'success' } }))">Copiar</button>
                                </div>
                            @elseif ($wrapUpCall)
                                <h2 class="mt-1 text-2xl font-bold">Atendimento encerrado</h2>
                                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Aguardando a classificacao do resultado.</p>
                            @elseif ($previewReservation)
                                <h2 class="mt-1 text-2xl font-bold">{{ $previewReservation->phoneState->contactPhone->phone }}</h2>
                                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Ficha Preview: {{ $previewReservation->phoneState->contact->name ?? 'Lead sem nome' }} · {{ $previewReservation->campaign->name }}</p>
                                <p class="mt-2 text-xs font-semibold" style="color: var(--status-success);">Ficha reservada. Inicie a ligação quando estiver pronto.</p>
                                <form method="POST" action="{{ route('tenant.agent.preview.call') }}" class="mt-3">
                                    @csrf
                                    <x-ui.button type="submit">Ligar para o lead</x-ui.button>
                                </form>
                            @elseif ($currentStatus === 'available' && $selectedCampaign?->dialing_mode === 'preview')
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <h2 class="text-2xl font-bold">Aguardando ficha Preview</h2>
                                        <p class="mt-1 text-sm" style="color: var(--fg-muted);">A campanha Preview nunca inicia uma chamada automaticamente.</p>
                                    </div>
                                    <form method="POST" action="{{ route('tenant.agent.preview.reserve') }}">
                                        @csrf
                                        <x-ui.button type="submit">Carregar ficha</x-ui.button>
                                    </form>
                                </div>
                            @elseif ($currentStatus === 'available')
                                <div class="flex items-center gap-4">
                                    <span class="grid h-14 w-14 shrink-0 place-items-center rounded-full animate-pulse" style="background: color-mix(in srgb, var(--primary) 12%, var(--surface)); color: var(--primary); box-shadow: 0 0 0 8px color-mix(in srgb, var(--primary) 8%, transparent);"><x-ui.icon name="phone" class="h-6 w-6" /></span>
                                    <div>
                                        <h2 class="text-2xl font-bold">Aguardando chamada</h2>
                                        <p class="mt-1 text-sm" style="color: var(--fg-muted);">Ao ficar disponivel, as chamadas das campanhas serao entregues aqui.</p>
                                    </div>
                                </div>
                            @elseif ($currentStatus === 'paused')
                                <h2 class="mt-1 text-2xl font-bold">Em pausa</h2>
                                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Voce pode fazer uma chamada manual autorizada, mas nao recebera chamadas de campanha.</p>
                            @else
                                <h2 class="mt-1 text-2xl font-bold">Painel offline</h2>
                                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Fique disponivel para receber chamadas ou iniciar uma chamada manual.</p>
                            @endif
                        </div>

                        @if ($currentCall)
                            <form method="POST" action="{{ route('tenant.agent.calls.hangup') }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="call_id" value="{{ $currentCall->id }}">
                                <x-ui.button type="submit" variant="danger">Encerrar chamada</x-ui.button>
                            </form>
                        @elseif (! $wrapUpCall && $canMakeManualCall)
                            <form method="POST" action="{{ route('tenant.agent.calls.store') }}" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row" x-data="agentManualCall({ url: @js(route('tenant.agent.calls.store')), csrfToken: @js(csrf_token()) })" x-on:submit.prevent="submit()">
                                @csrf
                                <input type="hidden" name="channel" value="manual">
                                <input name="phone" inputmode="tel" autocomplete="tel" x-model="phone" x-on:input="phone = formatPhone($event.target.value)" class="rounded-lg border px-3 py-2 text-sm" placeholder="(31) 99999-0001" aria-label="Telefone para chamada manual">
                                <x-ui.button type="submit" x-bind:disabled="!$store.softphone.registered || $store.softphone.callActive || submitting"><span x-text="submitting ? 'Chamando...' : 'Ligar'">Ligar</span></x-ui.button>
                            </form>
                        @elseif (! $wrapUpCall)
                            <span class="text-sm font-semibold" style="color: var(--fg-muted);">Chamadas bloqueadas enquanto estiver offline.</span>
                        @endif
                    </div>

                    @if ($currentCall)
                        <div
                            class="mt-5 flex items-center gap-3 rounded-lg border p-4"
                            style="border-color: color-mix(in srgb, var(--primary) 35%, var(--border)); background: color-mix(in srgb, var(--primary) 7%, var(--surface));"
                            x-data="{
                                elapsed: {{ max(0, (int) $currentCall->started_at?->diffInSeconds(now())) }},
                                init() { window.setInterval(() => this.elapsed += 1, 1000) },
                                format(value) {
                                    const minutes = Math.floor(value / 60);
                                    const seconds = value % 60;
                                    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                                }
                            }"
                        >
                            <span class="h-3 w-3 animate-pulse rounded-full" style="background: var(--status-success);"></span>
                            <div>
                                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Tempo da chamada</p>
                                <p class="font-mono text-xl font-bold" x-text="format(elapsed)">00:00</p>
                            </div>
                        </div>
                    @endif
                </x-ui.card>

                <x-ui.card>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Dados do mailing</p>
                            <h2 class="mt-1 text-lg font-semibold">{{ $activeContact?->name ?? 'Nenhum contato em atendimento' }}</h2>
                        </div>
                        @if ($activeContact)
                            <div class="flex flex-wrap justify-end gap-2">
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold" style="border-color: var(--border); color: var(--fg-muted);">
                                    {{ $activeContact->attempts_count }} tentativa(s)
                                </span>
                                <button type="button" class="th-focus rounded-lg border px-3 py-1.5 text-xs font-semibold" style="border-color: var(--border); color: var(--primary);" x-data x-on:click="navigator.clipboard.writeText(@js($mailingCopy)); window.dispatchEvent(new CustomEvent('toast', { detail: { title: 'Dados copiados', message: 'Os dados do mailing foram copiados.', severity: 'success' } }))">Copiar dados</button>
                            </div>
                        @endif
                    </div>

                    @if ($activeContact)
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-lg border p-3" style="border-color: var(--border); background: var(--surface-alt);">
                                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Telefone atual</p>
                                <p class="mt-1 font-semibold">{{ $callForDisposition->phone ?? $activeContact->phone }}</p>
                            </div>
                            <div class="rounded-lg border p-3" style="border-color: var(--border);">
                                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Campanha</p>
                                <p class="mt-1 font-semibold">{{ $callForDisposition->campaign?->name ?? 'Sem campanha' }}</p>
                            </div>
                            <div class="rounded-lg border p-3" style="border-color: var(--border);">
                                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Lista</p>
                                <p class="mt-1 font-semibold">{{ $activeContact->contactList?->name ?? 'Sem lista' }}</p>
                            </div>
                        </div>

                        @if ($activeContact->phones->isNotEmpty())
                            <div class="mt-4">
                                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Telefones do contato</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($activeContact->phones as $phone)
                                        <span class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: {{ $phone->is_primary ? 'var(--primary-soft)' : 'var(--surface)' }};">
                                            {{ $phone->phone }}{{ $phone->is_primary ? ' | Principal' : '' }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if (filled($activeContact->extra))
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($activeContact->extra as $label => $value)
                                    <div class="min-w-0 border-t pt-3" style="border-color: var(--border);">
                                        <p class="truncate text-xs font-semibold uppercase" style="color: var(--fg-muted);">{{ str_replace('_', ' ', $label) }}</p>
                                        <p class="mt-1 break-words text-sm font-medium">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div class="mt-4 rounded-lg border border-dashed p-6 text-center text-sm" style="border-color: var(--border); color: var(--fg-muted);">
                            Os dados do mailing aparecerao quando uma chamada de campanha for entregue.
                        </div>
                    @endif
                </x-ui.card>

                @if ($callForDisposition?->campaign?->service_script)
                    <x-ui.card>
                        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Script de atendimento</p>
                        <div class="mt-3 rounded-lg border p-4 text-sm leading-6" style="border-color: var(--border); background: var(--surface-alt);">
                            {!! nl2br(e($callForDisposition->campaign->service_script)) !!}
                        </div>
                    </x-ui.card>
                @endif

                @if ($activeContact)
                    <x-ui.card>
                        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Historico deste contato</p>
                        <div class="mt-3 grid gap-2">
                            @forelse ($contactHistory as $historyCall)
                                <div class="flex flex-col gap-1 rounded-lg border p-3 text-sm sm:flex-row sm:items-center sm:justify-between" style="border-color: var(--border);">
                                    <div>
                                        <p class="font-semibold">{{ $historyCall->disposition_name ?? 'Sem tabulacao' }}</p>
                                        <p class="text-xs" style="color: var(--fg-muted);">{{ $historyCall->campaign?->name ?? 'Sem campanha' }}{{ $historyCall->disposition_notes ? ' | '.$historyCall->disposition_notes : '' }}</p>
                                    </div>
                                    <span class="text-xs" style="color: var(--fg-muted);">{{ $historyCall->started_at?->format('d/m/Y H:i') ?? 'Sem data' }}</span>
                                </div>
                            @empty
                                <p class="text-sm" style="color: var(--fg-muted);">Nenhum atendimento anterior para este contato.</p>
                            @endforelse
                        </div>
                    </x-ui.card>
                @endif
            </div>

            <aside class="grid min-w-0 content-start gap-4">
                <x-ui.card x-show="call?.status === 'wrap_up'" x-cloak id="call-disposition-live" tone="violet">
                    <div>
                        <p class="text-xs font-semibold uppercase" style="color: var(--status-purple);">Tabulacao</p>
                        <h2 class="mt-1 text-lg font-semibold">Resultado do atendimento</h2>
                    </div>
                    <form method="POST" action="{{ route('tenant.agent.calls.end') }}" class="mt-4 grid gap-4" x-data="{ dirty: false }" x-on:input="dirty = true" x-bind:data-dirty="dirty ? 'true' : 'false'" data-agent-live-form>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="call_id" x-bind:value="call?.id">
                        <label class="grid gap-1 text-sm">
                            <span class="font-semibold">Tabulacao</span>
                            <select name="disposition_id" required>
                                <option value="">Selecione o resultado</option>
                                @foreach ($dispositions as $disposition)
                                    <option value="{{ $disposition->id }}">{{ $disposition->name }} | {{ $disposition->code }}</option>
                                @endforeach
                            </select>
                        </label>
                        <template x-for="field in (call?.service_fields_schema ?? [])" x-bind:key="field.key">
                            <label class="grid gap-1 text-sm">
                                <span class="font-semibold" x-text="`${field.label}${field.required ? ' *' : ''}`"></span>
                                <input x-bind:type="field.type === 'number' || field.type === 'currency' ? 'number' : field.type === 'email' ? 'email' : field.type === 'date' ? 'date' : 'text'" x-bind:name="`service_fields[${field.key}]`" x-bind:required="Boolean(field.required)">
                            </label>
                        </template>
                        <label class="grid gap-1 text-sm">
                            <span class="font-semibold">Anotacoes</span>
                            <textarea name="disposition_notes" rows="3" placeholder="Resumo e proximos passos"></textarea>
                        </label>
                        <x-ui.button type="submit" variant="danger" class="w-full justify-center">Salvar tabulacao</x-ui.button>
                    </form>
                </x-ui.card>

                <x-ui.card x-show="!call" id="call-disposition" tone="violet">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase" style="color: var(--status-purple);">Tabulacao</p>
                            <h2 class="mt-1 text-lg font-semibold">Resultado do atendimento</h2>
                        </div>
                        @if ($wrapUpCall)
                            <div
                                class="text-right"
                                x-data="{
                                    remaining: {{ $wrapUpSecondsRemaining }},
                                    init() {
                                        window.setInterval(() => {
                                            if (this.remaining > 0) this.remaining -= 1;
                                        }, 1000)
                                    },
                                    format(value) {
                                        const minutes = Math.floor(value / 60);
                                        const seconds = value % 60;
                                        return `${minutes}:${String(seconds).padStart(2, '0')}`;
                                    }
                                }"
                            >
                                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Tempo restante</p>
                                <p class="font-mono text-2xl font-bold" style="color: var(--status-purple);" x-text="format(remaining)">0:00</p>
                            </div>
                        @endif
                    </div>

                    @if ($callForDisposition && $dispositions->isNotEmpty())
                        <form method="POST" action="{{ route('tenant.agent.calls.end') }}" class="mt-4 grid gap-4" x-data="{ dirty: false }" x-on:input="dirty = true" x-bind:data-dirty="dirty ? 'true' : 'false'" data-agent-live-form>
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="call_id" value="{{ $callForDisposition->id }}">

                            <label class="grid gap-1 text-sm">
                                <span class="font-semibold">Tabulacao</span>
                                <select name="disposition_id" required>
                                    <option value="">Selecione o resultado</option>
                                    @foreach ($dispositions as $disposition)
                                        <option value="{{ $disposition->id }}" @selected((string) old('disposition_id') === (string) $disposition->id)>
                                            {{ $disposition->name }} | {{ $disposition->code }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('disposition_id')<span class="text-xs" style="color: var(--status-danger);">{{ $message }}</span>@enderror
                            </label>

                            <div class="grid gap-3 rounded-lg border p-3" style="border-color: var(--border); background: var(--surface-alt);">
                                <div>
                                    <p class="text-sm font-semibold">Agendamento de retorno</p>
                                    <p class="mt-1 text-xs" style="color: var(--fg-muted);">Preencha quando a tabulacao exigir novo contato.</p>
                                </div>
                                <label class="grid gap-1 text-sm">
                                    <span class="font-semibold">Data e hora</span>
                                    <input type="datetime-local" name="callback_at" value="{{ old('callback_at') }}">
                                    @error('callback_at')<span class="text-xs" style="color: var(--status-danger);">{{ $message }}</span>@enderror
                                </label>
                                <label class="grid gap-1 text-sm">
                                    <span class="font-semibold">Responsabilidade</span>
                                    <select name="callback_type">
                                        <option value="">Sem retorno</option>
                                        <option value="private" @selected(old('callback_type') === 'private')>Privado | somente para mim</option>
                                        <option value="public" @selected(old('callback_type') === 'public')>Publico | qualquer agente</option>
                                    </select>
                                    @error('callback_type')<span class="text-xs" style="color: var(--status-danger);">{{ $message }}</span>@enderror
                                </label>
                            </div>

                            <details class="rounded-lg border p-3" style="border-color: var(--border);">
                                <summary class="cursor-pointer text-sm font-semibold">Campos do atendimento</summary>
                                <div data-service-fields-grid class="mt-3 grid gap-3">
                                    @foreach ($serviceFieldsSchema as $field)
                                        @php
                                            $fieldType = $field['type'] ?? 'text';
                                            $inputType = match ($fieldType) {
                                                'date' => 'date',
                                                'phone' => 'tel',
                                                'email' => 'email',
                                                default => 'text',
                                            };
                                            $inputMode = in_array($fieldType, ['number', 'currency'], true) ? 'decimal' : (in_array($fieldType, ['cpf', 'cnpj'], true) ? 'numeric' : null);
                                            $masked = in_array($fieldType, ['cpf', 'cnpj', 'phone', 'currency'], true);
                                            $errorKey = 'service_fields.'.$field['key'];
                                        @endphp
                                        <label class="grid gap-1 text-sm">
                                            <span class="font-semibold">{{ $field['label'] }}{{ ($field['required'] ?? false) ? ' *' : '' }}</span>
                                            <input
                                                type="{{ $inputType }}"
                                                name="service_fields[{{ $field['key'] }}]"
                                                value="{{ old('service_fields.'.$field['key']) }}"
                                                @if ($inputMode) inputmode="{{ $inputMode }}" @endif
                                                @if ($fieldType === 'email') autocomplete="email" @endif
                                                @if ($masked) x-data="serviceFieldMask('{{ $fieldType }}')" x-on:input="format($event)" @endif
                                                @required($field['required'] ?? false)
                                            >
                                            @error($errorKey)<span class="text-xs" style="color: var(--status-danger);">{{ $message }}</span>@enderror
                                        </label>
                                    @endforeach
                                </div>
                            </details>

                            <label class="grid gap-1 text-sm">
                                <span class="font-semibold">Anotacoes</span>
                                <textarea name="disposition_notes" rows="3" placeholder="Resumo e proximos passos">{{ old('disposition_notes') }}</textarea>
                            </label>

                            <x-ui.button type="submit" variant="danger" class="w-full justify-center">Salvar tabulacao</x-ui.button>
                        </form>
                    @elseif ($callForDisposition)
                        <p class="mt-4 rounded-lg border p-4 text-sm" style="border-color: var(--border); color: var(--status-danger);">
                            Nao ha tabulacoes ativas. Solicite ao administrador a configuracao dos resultados.
                        </p>
                    @else
                        <p class="mt-4 rounded-lg border border-dashed p-5 text-sm" style="border-color: var(--border); color: var(--fg-muted);">
                            A tabulacao e o agendamento serao liberados quando houver uma chamada em atendimento.
                        </p>
                    @endif
                </x-ui.card>

                <x-ui.card tone="amber">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Agenda</p>
                            <h2 class="mt-1 text-base font-semibold">Proximos retornos</h2>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs font-semibold" style="background: var(--surface-alt); color: var(--fg-muted);">{{ $scheduledCallbacks->count() }}</span>
                    </div>
                    <div class="mt-4 grid gap-2">
                        @forelse ($scheduledCallbacks as $callback)
                            <div class="rounded-lg border p-3 text-sm" style="border-color: {{ $callback->scheduled_at?->isPast() ? 'color-mix(in srgb, var(--status-danger) 50%, var(--border))' : 'var(--border)' }};">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold">{{ $callback->contact?->name ?? $callback->phone }}</p>
                                        <p class="mt-1 text-xs" style="color: var(--fg-muted);">{{ $callback->phone }} | {{ $callback->type === 'private' ? 'Privado' : 'Publico' }}</p>
                                    </div>
                                    <span class="shrink-0 text-xs font-semibold" style="color: {{ $callback->scheduled_at?->isPast() ? 'var(--status-danger)' : 'var(--fg)' }};">
                                        {{ $callback->scheduled_at?->format('d/m H:i') }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm" style="color: var(--fg-muted);">Nenhum retorno pendente.</p>
                        @endforelse
                    </div>
                </x-ui.card>
            </aside>
        </div>

        <x-ui.card class="min-w-0 max-w-full">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Historico de ligacoes</p>
                    <h2 class="mt-1 text-lg font-semibold">Ultimos atendimentos</h2>
                </div>
                <span class="text-xs" style="color: var(--fg-muted);">Ultimas {{ $recentCalls->count() }} chamadas</span>
            </div>

            <div class="mt-4 overflow-x-auto rounded-lg border" style="border-color: var(--border);">
                <table class="w-full min-w-[780px] text-left text-sm">
                    <thead>
                        <tr class="text-xs uppercase">
                            <th class="px-4 py-3">Telefone</th>
                            <th class="px-4 py-3">Protocolo</th>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Campanha / lista</th>
                            <th class="px-4 py-3">Resultado</th>
                            <th class="px-4 py-3">Duracao</th>
                            <th class="px-4 py-3 text-right">Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentCalls as $recentCall)
                            @php
                                $duration = $recentCall->started_at && $recentCall->ended_at
                                    ? max(0, (int) $recentCall->started_at->diffInSeconds($recentCall->ended_at))
                                    : null;
                            @endphp
                            <tr class="border-t" style="border-color: var(--border);">
                                <td class="px-4 py-3">
                                    <button type="button" class="th-focus inline-flex items-center gap-2 font-semibold" style="color: var(--primary);" x-data x-on:click="$dispatch('open-modal', 'call-mailing-{{ $recentCall->id }}')">
                                        <x-ui.icon name="phone" />
                                        {{ $recentCall->phone ?? 'Sem numero' }}
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs">{{ $recentCall->protocol }}</span>
                                        <button type="button" class="th-focus rounded border px-2 py-1 text-xs font-semibold" style="border-color: var(--border); color: var(--primary);" x-data x-on:click="navigator.clipboard.writeText(@js($recentCall->protocol)); window.dispatchEvent(new CustomEvent('toast', { detail: { title: 'Protocolo copiado', message: 'O protocolo foi copiado para a area de transferencia.', severity: 'success' } }))">Copiar</button>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $recentCall->started_at?->format('d/m/Y H:i') ?? 'Sem data' }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $recentCall->campaign?->name ?? data_get($recentCall->report_snapshot, 'campaign_name') ?? 'Chamada manual' }}</p>
                                    <p class="text-xs" style="color: var(--fg-muted);">{{ $recentCall->contact?->contactList?->name ?? data_get($recentCall->report_snapshot, 'list_name') ?? 'Sem lista' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p>{{ $recentCall->disposition_name ?? 'Sem tabulacao' }}</p>
                                    @if ($recentCall->callback_at)
                                        <p class="mt-1 text-xs" style="color: var(--fg-muted);">
                                            Retorno {{ $recentCall->callback_type === 'public' ? 'publico' : 'privado' }} | {{ $recentCall->callback_at->format('d/m H:i') }}
                                        </p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono">{{ $duration === null ? '--:--:--' : sprintf('%02d:%02d:%02d', intdiv($duration, 3600), intdiv($duration % 3600, 60), $duration % 60) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" class="th-focus inline-flex h-9 w-9 items-center justify-center rounded-lg border" style="border-color: var(--border); color: var(--fg-muted);" x-data x-on:click="$dispatch('open-modal', 'call-mailing-{{ $recentCall->id }}')" aria-label="Ver dados do mailing">
                                        <x-ui.icon name="view" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center" style="color: var(--fg-muted);">Nenhuma chamada recente para este usuario.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>

    <x-ui.modal name="agent-pause" title="Iniciar pausa" max-width="md">
        <form method="POST" action="{{ route('tenant.agent.pause') }}">
            @csrf
            @method('PATCH')
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Motivo da pausa</span>
                @if ($pauseReasons->isNotEmpty())
                    <select name="reason" required>
                        @foreach ($pauseReasons as $pauseReason)
                            <option value="{{ $pauseReason->name }}">{{ $pauseReason->name }}</option>
                        @endforeach
                    </select>
                @else
                    <input name="reason" placeholder="Ex.: Almoco" required>
                @endif
            </label>
            <div class="mt-5 flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'agent-pause')">Cancelar</x-ui.button>
                <x-ui.button type="submit">Pausar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    @foreach ($recentCalls as $recentCall)
        @php
            $historySnapshot = $recentCall->report_snapshot ?? [];
            $historyContactName = $recentCall->contact?->name ?? data_get($historySnapshot, 'contact_name');
            $historyCampaignName = $recentCall->campaign?->name ?? data_get($historySnapshot, 'campaign_name');
            $historyListName = $recentCall->contact?->contactList?->name ?? data_get($historySnapshot, 'list_name');
            $historyPhones = $recentCall->contact
                ? $recentCall->contact->phones->map(fn ($phone) => ['phone' => $phone->phone, 'is_primary' => (bool) $phone->is_primary])
                : collect(data_get($historySnapshot, 'contact_phones', []));
            $historyExtra = $recentCall->contact?->extra ?? data_get($historySnapshot, 'contact_extra', []);
            $hasMailingSnapshot = filled($historyContactName) || $historyPhones->isNotEmpty() || filled($historyExtra);
            $historyMailingCopy = collect([
                'Contato: '.($historyContactName ?? 'Nao informado'),
                'Telefone da ligacao: '.($recentCall->phone ?? 'Nao informado'),
                'Protocolo: '.($recentCall->protocol ?? 'Nao informado'),
                'Campanha: '.($historyCampaignName ?? 'Sem campanha'),
                'Lista: '.($historyListName ?? 'Sem lista'),
            ]);

            foreach ($historyPhones as $phone) {
                $historyMailingCopy->push('Telefone cadastrado: '.$phone['phone'].($phone['is_primary'] ? ' (Principal)' : ''));
            }

            foreach ($historyExtra as $label => $value) {
                $historyMailingCopy->push(str_replace('_', ' ', $label).': '.(is_scalar($value) ? $value : json_encode($value)));
            }
        @endphp
        <x-ui.modal name="call-mailing-{{ $recentCall->id }}" title="Dados do mailing da ligacao" max-width="3xl">
            @if ($recentCall->contact || $hasMailingSnapshot)
                <div class="grid gap-5">
                    <div class="flex justify-end">
                        <button type="button" class="th-focus rounded-lg border px-3 py-1.5 text-xs font-semibold" style="border-color: var(--border); color: var(--primary);" x-data x-on:click="navigator.clipboard.writeText(@js($historyMailingCopy->implode('\\n'))); window.dispatchEvent(new CustomEvent('toast', { detail: { title: 'Dados copiados', message: 'Os dados do mailing foram copiados.', severity: 'success' } }))">Copiar todos os dados</button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-lg border p-3" style="border-color: var(--border); background: var(--surface-alt);">
                            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Contato</p>
                            <p class="mt-1 font-semibold">{{ $historyContactName ?? 'Sem nome' }}</p>
                        </div>
                        <div class="rounded-lg border p-3" style="border-color: var(--border);">
                            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Telefone da ligacao</p>
                            <p class="mt-1 font-semibold">{{ $recentCall->phone }}</p>
                        </div>
                        <div class="rounded-lg border p-3" style="border-color: var(--border);">
                            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Protocolo</p>
                            <div class="mt-1 flex items-center gap-2"><p class="font-mono font-semibold">{{ $recentCall->protocol }}</p><button type="button" class="th-focus rounded border px-2 py-1 text-xs font-semibold" style="border-color: var(--border); color: var(--primary);" x-data x-on:click="navigator.clipboard.writeText(@js($recentCall->protocol)); window.dispatchEvent(new CustomEvent('toast', { detail: { title: 'Protocolo copiado', message: 'O protocolo foi copiado para a area de transferencia.', severity: 'success' } }))">Copiar</button></div>
                        </div>
                        <div class="rounded-lg border p-3" style="border-color: var(--border);">
                            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Campanha</p>
                            <p class="mt-1 font-semibold">{{ $historyCampaignName ?? 'Sem campanha' }}</p>
                        </div>
                        <div class="rounded-lg border p-3" style="border-color: var(--border);">
                            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Lista</p>
                            <p class="mt-1 font-semibold">{{ $historyListName ?? 'Sem lista' }}</p>
                        </div>
                    </div>

                    @if ($historyPhones->isNotEmpty())
                        <div>
                            <p class="text-sm font-semibold">Telefones cadastrados</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($historyPhones as $phone)
                                    <span class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border);">
                                        {{ $phone['phone'] }}{{ $phone['is_primary'] ? ' | Principal' : '' }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (filled($historyExtra))
                        <div>
                            <p class="text-sm font-semibold">Informacoes importadas</p>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($historyExtra as $label => $value)
                                    <div class="rounded-lg border p-3" style="border-color: var(--border);">
                                        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">{{ str_replace('_', ' ', $label) }}</p>
                                        <p class="mt-1 break-words text-sm">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="rounded-lg border p-4" style="border-color: var(--border); background: var(--surface-alt);">
                        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Resultado do atendimento</p>
                        <p class="mt-1 font-semibold">{{ $recentCall->disposition_name ?? 'Sem tabulacao' }}</p>
                        <p class="mt-2 text-sm" style="color: var(--fg-muted);">{{ $recentCall->disposition_notes ?: 'Nenhuma anotacao registrada.' }}</p>
                    </div>
                </div>
            @else
                <div class="rounded-lg border border-dashed p-8 text-center" style="border-color: var(--border); color: var(--fg-muted);">
                    Esta foi uma chamada manual e nao possui mailing vinculado.
                </div>
            @endif
        </x-ui.modal>
    @endforeach

    </div>

</x-app-layout>
