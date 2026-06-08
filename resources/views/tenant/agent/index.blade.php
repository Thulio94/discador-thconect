<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Operacao</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Painel do Agente</h1>
        </div>
    </x-slot>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="grid gap-4">
            <x-ui.card>
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Status atual</p>
                        <h2 class="mt-1 text-3xl font-bold">{{ $currentStatus }}</h2>
                        <p class="mt-1 text-sm" style="color: var(--fg-muted);">
                            Ramal {{ auth()->user()->extension }} | {{ auth()->user()->user_type }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('tenant.agent.status') }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="available">
                            <x-ui.button type="submit">Disponivel</x-ui.button>
                        </form>

                        <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'agent-pause')">Pausa</x-ui.button>

                        <form method="POST" action="{{ route('tenant.agent.status') }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="offline">
                            <x-ui.button type="submit" variant="ghost">Offline</x-ui.button>
                        </form>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card>
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Chamada simulada</p>
                        @if ($currentCall)
                            <h2 class="mt-1 text-xl font-bold">{{ $currentCall->phone ?? 'Sem numero' }}</h2>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">Canal {{ $currentCall->channel }} em andamento</p>
                        @else
                            <h2 class="mt-1 text-xl font-bold">Nenhuma chamada ativa</h2>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">Chamada manual simulada pelo ramal do usuario.</p>
                        @endif
                    </div>

                    @if ($currentCall)
                        <x-ui.button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'call-disposition')">Tabular e encerrar</x-ui.button>
                    @else
                        <form method="POST" action="{{ route('tenant.agent.calls.store') }}" class="flex flex-col gap-2 sm:flex-row">
                            @csrf
                            <input type="hidden" name="channel" value="manual">
                            <input
                                name="phone"
                                class="rounded-lg border px-3 py-2 text-sm"
                                style="border-color: var(--border); background: var(--surface); color: var(--fg);"
                                placeholder="31999990001"
                            >
                            <x-ui.button type="submit">Iniciar chamada</x-ui.button>
                        </form>
                    @endif
                </div>
            </x-ui.card>

            @if ($currentCall?->contact)
                <x-ui.card>
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
                        <div>
                            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Ficha do contato</p>
                            <h2 class="mt-1 text-xl font-bold">{{ $currentCall->contact->name ?? 'Contato sem nome' }}</h2>
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">{{ $currentCall->contact->phone }}</p>

                            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                <div class="rounded-lg border p-3" style="border-color: var(--border);">
                                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Campanha</p>
                                    <p class="mt-1 text-sm font-semibold">{{ $currentCall->campaign?->name ?? 'Sem campanha' }}</p>
                                </div>
                                <div class="rounded-lg border p-3" style="border-color: var(--border);">
                                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Lista</p>
                                    <p class="mt-1 text-sm font-semibold">{{ $currentCall->contact->contactList?->name ?? 'Sem lista' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <div class="rounded-lg border p-3" style="border-color: var(--border); background: var(--surface-alt);">
                                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Tentativas</p>
                                <p class="mt-1 text-2xl font-bold">{{ $currentCall->contact->attempts_count }}</p>
                            </div>
                            <div class="rounded-lg border p-3" style="border-color: var(--border);">
                                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Ultimo resultado</p>
                                <p class="mt-1 text-sm font-semibold">{{ $currentCall->contact->last_result ?? 'Sem registro' }}</p>
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Historico do contato</p>
                    <div class="mt-4 grid gap-2">
                        @forelse ($contactHistory as $historyCall)
                            <div class="rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-semibold">{{ $historyCall->disposition_name ?? 'Sem tabulacao' }}</span>
                                    <span class="text-xs" style="color: var(--fg-muted);">{{ $historyCall->started_at?->format('d/m H:i') ?? 'sem data' }}</span>
                                </div>
                                <p class="mt-1 text-xs" style="color: var(--fg-muted);">
                                    {{ $historyCall->campaign?->name ?? 'Sem campanha' }}
                                    @if ($historyCall->disposition_notes)
                                        | {{ $historyCall->disposition_notes }}
                                    @endif
                                </p>
                            </div>
                        @empty
                            <p class="text-sm" style="color: var(--fg-muted);">Nenhum historico anterior para este contato.</p>
                        @endforelse
                    </div>
                </x-ui.card>

                @if ($currentCall->campaign?->service_script)
                    <x-ui.card>
                        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Script de atendimento</p>
                        <div class="mt-3 rounded-lg border p-4 text-sm leading-6" style="border-color: var(--border); background: var(--surface-alt); color: var(--fg);">
                            {!! nl2br(e($currentCall->campaign->service_script)) !!}
                        </div>
                    </x-ui.card>
                @endif
            @endif
        </div>

        <div class="grid gap-4">
            <x-ui.card>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Capacidade</p>
                <p class="mt-2 text-3xl font-bold">{{ $availableAgents }}</p>
                <p class="mt-1 text-sm" style="color: var(--fg-muted);">Agentes disponiveis nesta empresa.</p>
            </x-ui.card>

            <x-ui.card>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Historico</p>
                        <h2 class="mt-1 text-base font-semibold">Ultimos status</h2>
                    </div>
                </div>

                <div class="mt-4 grid gap-2">
                    @forelse ($recentStatuses as $status)
                        <div class="rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold">{{ $status->status }}</span>
                                <span class="text-xs" style="color: var(--fg-muted);">{{ $status->started_at->format('H:i') }}</span>
                            </div>
                            @if ($status->reason)
                                <p class="mt-1 text-xs" style="color: var(--fg-muted);">{{ $status->reason }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm" style="color: var(--fg-muted);">Nenhum status registrado.</p>
                    @endforelse
                </div>
            </x-ui.card>
        </div>
    </div>

    <x-ui.modal name="agent-pause" title="Iniciar pausa" max-width="md">
        <form method="POST" action="{{ route('tenant.agent.pause') }}">
            @csrf
            @method('PATCH')
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Motivo</span>
                @if ($pauseReasons->isNotEmpty())
                    <select
                        name="reason"
                        class="rounded-lg border px-3 py-2"
                        style="border-color: var(--border); background: var(--surface); color: var(--fg);"
                        required
                    >
                        @foreach ($pauseReasons as $pauseReason)
                            <option value="{{ $pauseReason->name }}">{{ $pauseReason->name }}</option>
                        @endforeach
                    </select>
                @else
                    <input
                        name="reason"
                        class="rounded-lg border px-3 py-2"
                        style="border-color: var(--border); background: var(--surface); color: var(--fg);"
                        placeholder="Pausa almoco"
                        required
                    >
                @endif
            </label>
            <div class="mt-5 flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'agent-pause')">Cancelar</x-ui.button>
                <x-ui.button type="submit">Pausar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    @if ($currentCall)
        <x-ui.modal name="call-disposition" title="Tabular chamada" max-width="lg">
            @if ($dispositions->isNotEmpty())
                <form method="POST" action="{{ route('tenant.agent.calls.end') }}" class="grid gap-4">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="call_id" value="{{ $currentCall->id }}">

                    <div class="rounded-lg border p-3 text-sm" style="border-color: var(--border); background: var(--surface-alt);">
                        <p class="font-semibold">{{ $currentCall->phone ?? 'Sem numero' }}</p>
                        <p class="mt-1 text-xs" style="color: var(--fg-muted);">Chamada {{ $currentCall->channel }} em andamento.</p>
                    </div>

                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Resultado</span>
                        <select
                            name="disposition_id"
                            class="rounded-lg border px-3 py-2"
                            style="border-color: var(--border); background: var(--surface); color: var(--fg);"
                            required
                        >
                            @foreach ($dispositions as $disposition)
                                <option value="{{ $disposition->id }}">{{ $disposition->name }} | {{ $disposition->code }} | {{ $disposition->category }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="grid gap-3 rounded-lg border p-3" style="border-color: var(--border); background: var(--surface-alt);">
                        <div>
                            <p class="text-sm font-semibold">Retorno</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="grid gap-1 text-sm">
                                <span class="font-semibold">Data e hora</span>
                                <input
                                    type="datetime-local"
                                    name="callback_at"
                                    class="rounded-lg border px-3 py-2"
                                    style="border-color: var(--border); background: var(--surface); color: var(--fg);"
                                >
                            </label>

                            <label class="grid gap-1 text-sm">
                                <span class="font-semibold">Tipo de retorno</span>
                                <select
                                    name="callback_type"
                                    class="rounded-lg border px-3 py-2"
                                    style="border-color: var(--border); background: var(--surface); color: var(--fg);"
                                >
                                    <option value="">Sem retorno</option>
                                    <option value="public">Publico</option>
                                    <option value="private">Privado</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="grid gap-3 rounded-lg border p-3" style="border-color: var(--border);">
                        <div>
                            <p class="text-sm font-semibold">Campos do atendimento</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <label class="grid gap-1 text-sm">
                                <span class="font-semibold">Documento</span>
                                <input
                                    name="service_fields[documento]"
                                    class="rounded-lg border px-3 py-2"
                                    style="border-color: var(--border); background: var(--surface); color: var(--fg);"
                                    placeholder="CPF/CNPJ"
                                >
                            </label>
                            <label class="grid gap-1 text-sm">
                                <span class="font-semibold">Protocolo</span>
                                <input
                                    name="service_fields[protocolo]"
                                    class="rounded-lg border px-3 py-2"
                                    style="border-color: var(--border); background: var(--surface); color: var(--fg);"
                                    placeholder="PROTO-123"
                                >
                            </label>
                            <label class="grid gap-1 text-sm">
                                <span class="font-semibold">Valor</span>
                                <input
                                    name="service_fields[valor]"
                                    class="rounded-lg border px-3 py-2"
                                    style="border-color: var(--border); background: var(--surface); color: var(--fg);"
                                    placeholder="199.90"
                                >
                            </label>
                        </div>
                    </div>

                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Anotacoes do atendimento</span>
                        <textarea
                            name="disposition_notes"
                            rows="4"
                            class="rounded-lg border px-3 py-2"
                            style="border-color: var(--border); background: var(--surface); color: var(--fg);"
                            placeholder="Resumo, combinados, objecoes e proximos passos"
                        ></textarea>
                    </label>

                    <div class="flex justify-end gap-2">
                        <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'call-disposition')">Cancelar</x-ui.button>
                        <x-ui.button type="submit" variant="danger">Salvar tabulacao</x-ui.button>
                    </div>
                </form>
            @else
                <div class="grid gap-4">
                    <p class="rounded-lg border p-4 text-sm" style="border-color: var(--border); color: var(--fg-muted);">
                        Cadastre uma tabulacao ativa em Configuracoes para encerrar chamadas.
                    </p>
                    <div class="flex justify-end">
                        <a href="{{ route('tenant.settings.index') }}" class="th-focus inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold" style="background: var(--primary); color: #fff; border-radius: var(--radius-md);">Abrir configuracoes</a>
                    </div>
                </div>
            @endif
        </x-ui.modal>
    @endif
</x-app-layout>
