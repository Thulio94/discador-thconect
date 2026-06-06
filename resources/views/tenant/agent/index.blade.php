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
                            <p class="mt-1 text-sm" style="color: var(--fg-muted);">Use o simulador para validar fluxo operacional.</p>
                        @endif
                    </div>

                    @if ($currentCall)
                        <form method="POST" action="{{ route('tenant.agent.calls.end') }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="call_id" value="{{ $currentCall->id }}">
                            <x-ui.button type="submit" variant="danger">Encerrar chamada</x-ui.button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('tenant.agent.calls.store') }}" class="flex flex-col gap-2 sm:flex-row">
                            @csrf
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
                <input
                    name="reason"
                    class="rounded-lg border px-3 py-2"
                    style="border-color: var(--border); background: var(--surface); color: var(--fg);"
                    placeholder="Pausa almoco"
                    required
                >
            </label>
            <div class="mt-5 flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'agent-pause')">Cancelar</x-ui.button>
                <x-ui.button type="submit">Pausar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-app-layout>
