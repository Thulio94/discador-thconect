<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Operacao</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Configuracoes Operacionais</h1>
        </div>
    </x-slot>

    <div class="grid gap-4">
        <div class="grid gap-3 md:grid-cols-3">
            <x-ui.card>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Motivos de pausa</p>
                <p class="mt-2 text-2xl font-bold">{{ $pauseReasons->where('status', 'active')->count() }} ativos</p>
            </x-ui.card>
            <x-ui.card>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Tabulacoes</p>
                <p class="mt-2 text-2xl font-bold">{{ $dispositions->where('status', 'active')->count() }} ativas</p>
            </x-ui.card>
            <x-ui.card>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Blacklist</p>
                <p class="mt-2 text-2xl font-bold">{{ $blacklistNumbers->where('status', 'active')->count() }} bloqueados</p>
            </x-ui.card>
        </div>

        <x-ui.card>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Parametros gerais</h2>
                    <p class="text-sm" style="color: var(--fg-muted);">Limites da operacao simulada aplicados ao painel do agente e supervisao.</p>
                </div>
                @can('tenant.settings.update')
                    <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'edit-operational-parameters')">Editar parametros</x-ui.button>
                @endcan
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-4">
                <div class="rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Pausa minima</p>
                    <p class="mt-1 text-xl font-bold">{{ $operationalParameter->min_pause_minutes }} min</p>
                </div>
                <div class="rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Pausa maxima</p>
                    <p class="mt-1 text-xl font-bold">{{ $operationalParameter->max_pause_minutes }} min</p>
                </div>
                <div class="rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Alerta pausa</p>
                    <p class="mt-1 text-xl font-bold">{{ $operationalParameter->pause_alert_minutes }} min</p>
                </div>
                <div class="rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Alerta chamada</p>
                    <p class="mt-1 text-xl font-bold">{{ $operationalParameter->call_alert_minutes }} min</p>
                </div>
            </div>
            <div class="mt-3 grid gap-2 text-sm md:grid-cols-2" style="color: var(--fg-muted);">
                <p>Ao salvar a tabulacao, o agente volta automaticamente para disponivel.</p>
                <p>{{ $operationalParameter->allow_manual_calls_while_paused ? 'Chamada manual permitida durante pausa.' : 'Chamada manual bloqueada durante pausa.' }}</p>
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Motivos de pausa</h2>
                    <p class="text-sm" style="color: var(--fg-muted);">Opcoes usadas no painel do agente.</p>
                </div>
                @can('tenant.settings.create')
                    <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'create-pause-reason')">Novo motivo</x-ui.button>
                @endcan
            </div>

            <div class="mt-4 grid gap-2">
                @forelse ($pauseReasons as $reason)
                    <div class="grid gap-3 rounded-lg border p-3 text-sm md:grid-cols-[minmax(0,1fr)_130px_120px_170px]" style="border-color: var(--border);">
                        <div>
                            <p class="font-semibold">{{ $reason->name }}</p>
                            <p class="text-xs" style="color: var(--fg-muted);">{{ $reason->description ?? 'Sem descricao' }}</p>
                        </div>
                        <span>{{ $reason->is_billable ? 'Produtiva' : 'Nao produtiva' }}</span>
                        <span>{{ $reason->status }}</span>
                        <div class="flex flex-wrap justify-start gap-2 md:justify-end">
                            @can('tenant.settings.update')
                                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'edit-pause-reason-{{ $reason->id }}')">Editar</x-ui.button>
                            @endcan
                            @can('tenant.settings.delete')
                                <x-ui.button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'delete-pause-reason-{{ $reason->id }}')">Inativar</x-ui.button>
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="rounded-lg border p-4 text-sm" style="border-color: var(--border); color: var(--fg-muted);">Nenhum motivo de pausa cadastrado.</p>
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Tabulacoes</h2>
                    <p class="text-sm" style="color: var(--fg-muted);">Classificacoes usadas no encerramento, retorno para fila e relatorios de chamadas.</p>
                </div>
                @can('tenant.settings.create')
                    <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'create-disposition')">Nova tabulacao</x-ui.button>
                @endcan
            </div>

            <div class="mt-4 grid gap-2">
                @forelse ($dispositions as $disposition)
                    <div class="grid gap-3 rounded-lg border p-3 text-sm md:grid-cols-[minmax(0,1fr)_120px_120px_170px]" style="border-color: var(--border);">
                        <div>
                            <p class="font-semibold">{{ $disposition->name }}</p>
                            <p class="text-xs" style="color: var(--fg-muted);">
                                {{ $disposition->code }}
                                @if ($disposition->retry_enabled)
                                    | Nova tentativa em {{ $disposition->retry_delay_minutes ?? 0 }} min
                                    @if ($disposition->max_attempts)
                                        | Maximo de {{ $disposition->max_attempts }} tentativas
                                    @endif
                                @endif
                            </p>
                        </div>
                        <span>{{ $disposition->category }}</span>
                        <span>{{ $disposition->status }}</span>
                        <div class="flex flex-wrap justify-start gap-2 md:justify-end">
                            @can('tenant.settings.update')
                                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'edit-disposition-{{ $disposition->id }}')">Editar</x-ui.button>
                            @endcan
                            @can('tenant.settings.delete')
                                <x-ui.button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'delete-disposition-{{ $disposition->id }}')">Inativar</x-ui.button>
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="rounded-lg border p-4 text-sm" style="border-color: var(--border); color: var(--fg-muted);">Nenhuma tabulacao cadastrada.</p>
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Blacklist</h2>
                    <p class="text-sm" style="color: var(--fg-muted);">Numeros bloqueados para campanhas futuras.</p>
                </div>
                @can('tenant.settings.create')
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'import-blacklist-numbers')">Importar em massa</x-ui.button>
                        <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'create-blacklist-number')">Adicionar numero</x-ui.button>
                    </div>
                @endcan
            </div>

            <div class="mt-4 grid gap-2">
                @forelse ($blacklistNumbers as $number)
                    <div class="grid gap-3 rounded-lg border p-3 text-sm md:grid-cols-[180px_minmax(0,1fr)_120px_170px]" style="border-color: var(--border);">
                        <span class="font-semibold">{{ $number->phone }}</span>
                        <span>{{ $number->reason ?? 'Sem motivo' }}</span>
                        <span>{{ $number->status }}</span>
                        <div class="flex flex-wrap justify-start gap-2 md:justify-end">
                            @can('tenant.settings.update')
                                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'edit-blacklist-number-{{ $number->id }}')">Editar</x-ui.button>
                            @endcan
                            @can('tenant.settings.delete')
                                <x-ui.button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'delete-blacklist-number-{{ $number->id }}')">Inativar</x-ui.button>
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="rounded-lg border p-4 text-sm" style="border-color: var(--border); color: var(--fg-muted);">Nenhum numero na blacklist.</p>
                @endforelse
            </div>
        </x-ui.card>

        <div class="grid gap-3 md:grid-cols-3">
            <a href="{{ route('tenant.telephony.index') }}" class="rounded-lg border p-4 text-sm transition" style="border-color: var(--border); background: var(--surface);">
                <p class="font-semibold">Telefonia</p>
                <p class="mt-1" style="color: var(--fg-muted);">Configure a rota Asterisk, tronco, ramais e teste a conexao AMI.</p>
            </a>
            <a href="{{ route('tenant.campaigns.index') }}" class="rounded-lg border p-4 text-sm transition" style="border-color: var(--border); background: var(--surface);">
                <p class="font-semibold">AMD por campanha</p>
                <p class="mt-1" style="color: var(--fg-muted);">Defina o modo AMD e a rota de discagem nas configuracoes de cada campanha.</p>
            </a>
            <a href="{{ route('tenant.integrations.index') }}" class="rounded-lg border p-4 text-sm transition" style="border-color: var(--border); background: var(--surface);">
                <p class="font-semibold">Integracoes</p>
                <p class="mt-1" style="color: var(--fg-muted);">Gerencie os conectores e webhooks externos da empresa.</p>
            </a>
        </div>
    </div>

    @can('tenant.settings.create')
    <x-ui.modal name="create-pause-reason" title="Novo motivo de pausa" max-width="lg">
        @include('tenant.settings.partials.pause-reason-form', ['action' => route('tenant.settings.pause-reasons.store'), 'reason' => null])
    </x-ui.modal>
    @endcan

    @can('tenant.settings.update')
    <x-ui.modal name="edit-operational-parameters" title="Parametros gerais da operacao" max-width="lg">
        @include('tenant.settings.partials.operational-parameters-form')
    </x-ui.modal>
    @endcan

    @foreach ($pauseReasons as $reason)
        @can('tenant.settings.update')
        <x-ui.modal name="edit-pause-reason-{{ $reason->id }}" title="Editar motivo de pausa" max-width="lg">
            @include('tenant.settings.partials.pause-reason-form', ['action' => route('tenant.settings.pause-reasons.update', $reason), 'reason' => $reason, 'method' => 'PUT'])
        </x-ui.modal>
        @endcan
        @can('tenant.settings.delete')
        <x-ui.modal name="delete-pause-reason-{{ $reason->id }}" title="Inativar motivo de pausa" max-width="md">
            <form method="POST" action="{{ route('tenant.settings.pause-reasons.destroy', $reason) }}">
                @csrf
                @method('DELETE')
                <p class="text-sm" style="color: var(--fg-muted);">O motivo {{ $reason->name }} sera marcado como inativo.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'delete-pause-reason-{{ $reason->id }}')">Cancelar</x-ui.button>
                    <x-ui.button type="submit" variant="danger">Inativar</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
        @endcan
    @endforeach

    @can('tenant.settings.create')
    <x-ui.modal name="create-disposition" title="Nova tabulacao" max-width="lg">
        @include('tenant.settings.partials.disposition-form', ['action' => route('tenant.settings.dispositions.store'), 'disposition' => null])
    </x-ui.modal>
    @endcan

    @foreach ($dispositions as $disposition)
        @can('tenant.settings.update')
        <x-ui.modal name="edit-disposition-{{ $disposition->id }}" title="Editar tabulacao" max-width="lg">
            @include('tenant.settings.partials.disposition-form', ['action' => route('tenant.settings.dispositions.update', $disposition), 'disposition' => $disposition, 'method' => 'PUT'])
        </x-ui.modal>
        @endcan
        @can('tenant.settings.delete')
        <x-ui.modal name="delete-disposition-{{ $disposition->id }}" title="Inativar tabulacao" max-width="md">
            <form method="POST" action="{{ route('tenant.settings.dispositions.destroy', $disposition) }}">
                @csrf
                @method('DELETE')
                <p class="text-sm" style="color: var(--fg-muted);">A tabulacao {{ $disposition->name }} sera marcada como inativa.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'delete-disposition-{{ $disposition->id }}')">Cancelar</x-ui.button>
                    <x-ui.button type="submit" variant="danger">Inativar</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
        @endcan
    @endforeach

    @can('tenant.settings.create')
    <x-ui.modal name="create-blacklist-number" title="Adicionar numero" max-width="lg">
        @include('tenant.settings.partials.blacklist-form', ['action' => route('tenant.settings.blacklist.store'), 'number' => null])
    </x-ui.modal>

    <x-ui.modal name="import-blacklist-numbers" title="Importar blacklist" max-width="lg">
        <form method="POST" action="{{ route('tenant.settings.blacklist.import') }}" enctype="multipart/form-data" class="grid gap-4">
            @csrf
            <p class="text-sm" style="color: var(--fg-muted);">Cole um numero por linha ou envie CSV/TXT. Para incluir o motivo, use <span class="font-semibold">telefone;motivo</span>.</p>
            <textarea name="numbers" rows="8" placeholder="31999990001;Solicitou bloqueio&#10;31999990002" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);"></textarea>
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Arquivo CSV ou TXT</span>
                <input type="file" name="file" accept=".csv,.txt,text/csv,text/plain" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            </label>
            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'import-blacklist-numbers')">Cancelar</x-ui.button>
                <x-ui.button type="submit">Importar numeros</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
    @endcan

    @foreach ($blacklistNumbers as $number)
        @can('tenant.settings.update')
        <x-ui.modal name="edit-blacklist-number-{{ $number->id }}" title="Editar numero" max-width="lg">
            @include('tenant.settings.partials.blacklist-form', ['action' => route('tenant.settings.blacklist.update', $number), 'number' => $number, 'method' => 'PUT'])
        </x-ui.modal>
        @endcan
        @can('tenant.settings.delete')
        <x-ui.modal name="delete-blacklist-number-{{ $number->id }}" title="Inativar numero" max-width="md">
            <form method="POST" action="{{ route('tenant.settings.blacklist.destroy', $number) }}">
                @csrf
                @method('DELETE')
                <p class="text-sm" style="color: var(--fg-muted);">O numero {{ $number->phone }} sera marcado como inativo.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'delete-blacklist-number-{{ $number->id }}')">Cancelar</x-ui.button>
                    <x-ui.button type="submit" variant="danger">Inativar</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
        @endcan
    @endforeach
</x-app-layout>
