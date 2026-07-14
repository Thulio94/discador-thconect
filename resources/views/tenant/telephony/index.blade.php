<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Telefonia</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Rota e Asterisk AMI</h1>
        </div>
    </x-slot>

    @php
        $isReady = $configuration->isReadyForAsterisk();
        $status = $configuration->connection_status ?: 'not_configured';
        $statusLabels = [
            'online' => 'Conectada',
            'pending_test' => 'Aguardando teste',
            'error' => 'Com falha',
            'not_configured' => 'Simulador ativo',
        ];
    @endphp

    <div class="grid gap-5 xl:grid-cols-[1.35fr_0.65fr]">
        <x-ui.card>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Saida de chamadas</h2>
                    <p class="mt-1 text-sm" style="color: var(--fg-muted);">A rota real e isolada por empresa. Sem configuracao validada, as chamadas permanecem no simulador.</p>
                </div>
                @can('tenant.telephony.update')
                    <x-ui.button type="button" x-data x-on:click="$dispatch('open-modal', 'telephony-configuration')">Configurar rota</x-ui.button>
                @endcan
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border p-4" style="border-color: var(--border); background: var(--surface-alt);">
                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Modo</p>
                    <p class="mt-2 font-semibold">{{ $configuration->driver === 'asterisk_ami' ? 'Asterisk AMI' : 'Simulador' }}</p>
                </div>
                <div class="rounded-lg border p-4" style="border-color: var(--border); background: var(--surface-alt);">
                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Tronco</p>
                    <p class="mt-2 font-semibold">{{ $configuration->trunk_label ?: 'Nao definido' }}</p>
                </div>
                <div class="rounded-lg border p-4" style="border-color: var(--border); background: var(--surface-alt);">
                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Caller ID</p>
                    <p class="mt-2 font-semibold">{{ $configuration->caller_id ?: 'Nao definido' }}</p>
                </div>
                <div class="rounded-lg border p-4" style="border-color: var(--border); background: var(--surface-alt);">
                    <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Status</p>
                    <p class="mt-2 font-semibold" style="color: {{ $status === 'online' ? 'var(--success, #15803d)' : ($status === 'error' ? 'var(--danger, #b91c1c)' : 'var(--fg)') }};">{{ $statusLabels[$status] ?? 'Nao configurada' }}</p>
                </div>
            </div>

            @if ($configuration->driver === 'asterisk_ami')
                <div class="mt-5 rounded-lg border p-4 text-sm" style="border-color: var(--border); background: var(--bg-subtle);">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ $configuration->ami_host ?: 'Host AMI nao informado' }}:{{ $configuration->ami_port ?: 5038 }}</p>
                            <p class="mt-1" style="color: var(--fg-muted);">Canal {{ $configuration->outbound_channel_template ?: 'nao definido' }} | Contexto {{ $configuration->dialplan_context ?: 'nao definido' }} / {{ $configuration->dialplan_extension ?: 's' }}</p>
                        </div>
                        @can('tenant.telephony.update')
                            <form method="POST" action="{{ route('tenant.telephony.test') }}">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" :disabled="! $isReady">Testar AMI</x-ui.button>
                            </form>
                        @endcan
                    </div>
                    @if ($configuration->last_checked_at)
                        <p class="mt-3 text-xs" style="color: var(--fg-muted);">Ultimo teste: {{ $configuration->last_checked_at->format('d/m/Y H:i') }}</p>
                    @endif
                    @if ($status === 'error' && $configuration->last_error)
                        <p class="mt-2 text-xs" style="color: var(--danger, #b91c1c);">{{ $configuration->last_error }}</p>
                    @endif
                </div>
            @endif
        </x-ui.card>

        <x-ui.card>
            <h2 class="text-lg font-semibold">Pronto para a rota</h2>
            <div class="mt-4 grid gap-3 text-sm">
                <div class="rounded-lg border p-3" style="border-color: var(--border); background: var(--surface-alt);">Credenciais AMI criptografadas por empresa.</div>
                <div class="rounded-lg border p-3" style="border-color: var(--border); background: var(--surface-alt);">Ponte Go separada com autenticao interna.</div>
                <div class="rounded-lg border p-3" style="border-color: var(--border); background: var(--surface-alt);">Eventos de toque, atendimento, AMD e desligamento registrados na chamada.</div>
            </div>
        </x-ui.card>
    </div>

    @can('tenant.telephony.update')
        <x-ui.modal name="telephony-configuration" title="Configurar rota de telefonia" max-width="2xl">
            <form method="POST" action="{{ route('tenant.telephony.update') }}" class="grid gap-4" x-data="{ driver: '{{ old('driver', $configuration->driver ?: 'simulated') }}' }">
                @csrf
                @method('PUT')
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Modo de discagem</span>
                        <select name="driver" x-model="driver" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                            <option value="simulated">Simulador local</option>
                            <option value="asterisk_ami">Asterisk AMI / rota VoIP</option>
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Nome do tronco</span>
                        <input name="trunk_label" value="{{ old('trunk_label', $configuration->trunk_label) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Nome da rota de saida</span>
                        <input name="outbound_route_label" value="{{ old('outbound_route_label', $configuration->outbound_route_label) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Caller ID padrao</span>
                        <input name="caller_id" value="{{ old('caller_id', $configuration->caller_id) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    </label>
                </div>

                <div x-show="driver === 'asterisk_ami'" x-cloak class="grid gap-4 border-t pt-4" style="border-color: var(--border);">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="grid gap-1 text-sm"><span class="font-semibold">Host AMI</span><input name="ami_host" value="{{ old('ami_host', $configuration->ami_host) }}" placeholder="pbx.exemplo.com" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);"></label>
                        <label class="grid gap-1 text-sm"><span class="font-semibold">Porta AMI</span><input name="ami_port" type="number" min="1" max="65535" value="{{ old('ami_port', $configuration->ami_port ?: 5038) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);"></label>
                        <label class="grid gap-1 text-sm"><span class="font-semibold">Usuario AMI</span><input name="ami_username" autocomplete="off" placeholder="{{ $configuration->ami_username ? 'Mantido com seguranca' : 'usuario AMI' }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);"></label>
                        <label class="grid gap-1 text-sm"><span class="font-semibold">Segredo AMI</span><input name="ami_secret" type="password" autocomplete="new-password" placeholder="{{ $configuration->ami_secret ? 'Mantido com seguranca' : 'segredo AMI' }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);"></label>
                        <label class="grid gap-1 text-sm md:col-span-2"><span class="font-semibold">Template do canal</span><input name="outbound_channel_template" value="{{ old('outbound_channel_template', $configuration->outbound_channel_template) }}" placeholder="PJSIP/{phone}@nome-do-tronco" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);"><span style="color: var(--fg-muted);">Use {phone} para o numero discado.</span></label>
                        <label class="grid gap-1 text-sm"><span class="font-semibold">Contexto do dialplan</span><input name="dialplan_context" value="{{ old('dialplan_context', $configuration->dialplan_context) }}" placeholder="discador-outbound" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);"></label>
                        <label class="grid gap-1 text-sm"><span class="font-semibold">Extensao do dialplan</span><input name="dialplan_extension" value="{{ old('dialplan_extension', $configuration->dialplan_extension ?: 's') }}" placeholder="s" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);"></label>
                        <label class="flex items-center gap-2 self-end rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border);"><input name="bridge_enabled" type="checkbox" value="1" @checked(old('bridge_enabled', $configuration->bridge_enabled))><span>Habilitar chamadas reais apos o teste</span></label>
                    </div>
                </div>

                @error('telephony')<p class="text-sm" style="color: var(--danger, #b91c1c);">{{ $message }}</p>@enderror
                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'telephony-configuration')">Cancelar</x-ui.button>
                    <x-ui.button type="submit">Salvar configuracao</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endcan
</x-app-layout>
