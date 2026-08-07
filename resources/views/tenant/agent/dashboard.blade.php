<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Minha operacao</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Meu dashboard</h1>
        </div>
    </x-slot>

    <div class="relative">
        @if (session('agent_console_released'))
            <div class="mb-5 flex items-start gap-3 rounded-lg border px-4 py-3" role="status" style="border-color: color-mix(in srgb, var(--accent-violet) 42%, var(--border)); background: color-mix(in srgb, var(--accent-violet) 9%, var(--surface));">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-white" style="background: var(--accent-violet);"><x-ui.icon name="status" /></span>
                <div>
                    <p class="text-sm font-bold">Voce esta deslogado do painel do agente</p>
                    <p class="text-xs" style="color: var(--fg-muted);">Seu status foi alterado para offline e a licenca foi liberada. Para receber chamadas novamente, volte ao painel do agente e fique disponivel.</p>
                </div>
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-3">
            <x-ui.card title="Chamadas no dia" tone="cyan">
                <p class="text-3xl font-bold">{{ $callsTodayCount }}</p>
                <p class="mt-2 text-sm" style="color: var(--fg-muted);">Todas as tentativas registradas na data selecionada.</p>
            </x-ui.card>
            <x-ui.card title="Chamadas atendidas" tone="emerald">
                <p class="text-3xl font-bold">{{ $answeredCallsCount }}</p>
                <p class="mt-2 text-sm" style="color: var(--fg-muted);">Chamadas que tiveram atendimento.</p>
            </x-ui.card>
            <x-ui.card title="Chamadas finalizadas" tone="violet">
                <p class="text-3xl font-bold">{{ $completedCallsCount }}</p>
                <p class="mt-2 text-sm" style="color: var(--fg-muted);">Chamadas concluídas, com ou sem tabulacao.</p>
            </x-ui.card>
        </section>

        <x-ui.card class="mt-4" tone="cyan">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-lg font-semibold">Historico de chamadas</p>
                    <p class="mt-1 text-sm" style="color: var(--fg-muted);">Escolha uma data para consultar apenas as suas chamadas. Cada pagina exibe no maximo 25 registros.</p>
                </div>
                <form method="GET" action="{{ route('dashboard') }}" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-end" x-data="{ phone: @js($phoneSearch), formatPhone(value) { const digits = value.replace(/\D/g, '').replace(/^55(?=\d{10,11}$)/, '').slice(0, 11); if (digits.length <= 2) return digits; if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`; return digits.length <= 10 ? `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}` : `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`; }, init() { this.phone = this.formatPhone(this.phone) } }">
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Data</span>
                        <input type="date" name="date" value="{{ $historyDate }}" class="h-10 rounded-lg border px-3" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-semibold">Telefone</span>
                        <input name="phone" inputmode="tel" autocomplete="tel" x-model="phone" x-on:input="phone = formatPhone($event.target.value)" placeholder="(31) 99999-0001" class="h-10 rounded-lg border px-3" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    </label>
                    <x-ui.button type="submit">Buscar</x-ui.button>
                </form>
            </div>

            <div class="mt-5 overflow-x-auto rounded-lg border" style="border-color: var(--border);">
                <table class="min-w-[760px] w-full text-left text-sm">
                    <thead style="background: var(--bg-subtle); color: var(--fg-muted);">
                        <tr>
                            <th class="px-3 py-3">Telefone / Lead</th>
                            <th class="px-3 py-3">Protocolo</th>
                            <th class="px-3 py-3">Campanha / Lista</th>
                            <th class="px-3 py-3">Inicio</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Tabulacao</th>
                            <th class="px-3 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--border);">
                        @forelse ($recentCalls as $call)
                            <tr>
                                <td class="px-3 py-3">
                                    <button type="button" class="th-focus font-semibold" style="color: var(--primary);" x-data x-on:click="$dispatch('open-modal', 'agent-dashboard-mailing-{{ $call->id }}')">{{ $call->phone ?: 'Sem telefone' }}</button>
                                    <p class="mt-1 text-xs" style="color: var(--fg-muted);">{{ $call->contact?->name ?? 'Chamada manual' }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs">{{ $call->protocol }}</span>
                                        <button type="button" class="th-focus rounded border px-2 py-1 text-xs font-semibold" style="border-color: var(--border); color: var(--primary);" x-data x-on:click="navigator.clipboard.writeText(@js($call->protocol)); window.dispatchEvent(new CustomEvent('toast', { detail: { title: 'Protocolo copiado', message: 'O protocolo foi copiado para a area de transferencia.', severity: 'success' } }))">Copiar</button>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <p>{{ $call->campaign?->name ?? 'Manual' }}</p>
                                    <p class="mt-1 text-xs" style="color: var(--fg-muted);">{{ $call->contact?->contactList?->name ?? 'Sem lista' }}</p>
                                </td>
                                <td class="px-3 py-3">{{ $call->started_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td class="px-3 py-3">{{ match($call->status) { 'in_progress' => 'Em chamada', 'wrap_up' => 'Tabulando', 'ended' => 'Finalizada', default => $call->status } }}</td>
                                <td class="px-3 py-3">{{ $call->disposition_name ?? 'Sem tabulacao' }}</td>
                                <td class="px-3 py-3 text-right"><x-ui.action icon="view" label="Dados do mailing" x-data x-on:click="$dispatch('open-modal', 'agent-dashboard-mailing-{{ $call->id }}')" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-10 text-center" style="color: var(--fg-muted);">Nenhuma chamada sua nesta data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $recentCalls->links() }}</div>
        </x-ui.card>

        @foreach ($recentCalls as $call)
            @php
                $dashboardMailingCopy = collect([
                    'Lead: '.($call->contact?->name ?? 'Nao informado'),
                    'Telefone utilizado: '.($call->phone ?? 'Nao informado'),
                    'Protocolo: '.($call->protocol ?? 'Nao informado'),
                    'Campanha: '.($call->campaign?->name ?? 'Manual'),
                    'Lista: '.($call->contact?->contactList?->name ?? 'Sem lista'),
                ]);

                foreach ($call->contact?->phones ?? [] as $phone) {
                    $dashboardMailingCopy->push('Telefone cadastrado: '.$phone->phone.($phone->is_primary ? ' (Principal)' : ''));
                }

                foreach (($call->contact?->extra ?? []) as $key => $value) {
                    $dashboardMailingCopy->push(str_replace('_', ' ', $key).': '.(is_scalar($value) ? $value : json_encode($value)));
                }
            @endphp
            <x-ui.modal name="agent-dashboard-mailing-{{ $call->id }}" title="Dados do mailing da ligacao" max-width="3xl">
                @if ($call->contact)
                    <div class="grid gap-4 text-sm md:grid-cols-2">
                        <div class="md:col-span-2 flex justify-end"><button type="button" class="th-focus rounded-lg border px-3 py-1.5 text-xs font-semibold" style="border-color: var(--border); color: var(--primary);" x-data x-on:click="navigator.clipboard.writeText(@js($dashboardMailingCopy->implode('\\n'))); window.dispatchEvent(new CustomEvent('toast', { detail: { title: 'Dados copiados', message: 'Os dados do mailing foram copiados.', severity: 'success' } }))">Copiar todos os dados</button></div>
                        <div><p class="text-xs" style="color: var(--fg-muted);">Lead</p><p class="font-semibold">{{ $call->contact->name ?? 'Nao informado' }}</p></div>
                        <div><p class="text-xs" style="color: var(--fg-muted);">Telefone utilizado</p><p class="font-semibold">{{ $call->phone }}</p></div>
                        <div><p class="text-xs" style="color: var(--fg-muted);">Protocolo</p><div class="mt-1 flex items-center gap-2"><p class="font-mono font-semibold">{{ $call->protocol }}</p><button type="button" class="th-focus rounded border px-2 py-1 text-xs font-semibold" style="border-color: var(--border); color: var(--primary);" x-data x-on:click="navigator.clipboard.writeText(@js($call->protocol)); window.dispatchEvent(new CustomEvent('toast', { detail: { title: 'Protocolo copiado', message: 'O protocolo foi copiado para a area de transferencia.', severity: 'success' } }))">Copiar</button></div></div>
                        <div><p class="text-xs" style="color: var(--fg-muted);">Campanha</p><p>{{ $call->campaign?->name ?? 'Nao informada' }}</p></div>
                        <div><p class="text-xs" style="color: var(--fg-muted);">Lista</p><p>{{ $call->contact->contactList?->name ?? 'Nao informada' }}</p></div>
                        <div class="md:col-span-2"><p class="mb-2 text-xs" style="color: var(--fg-muted);">Telefones do lead</p><div class="flex flex-wrap gap-2">@forelse ($call->contact->phones as $phone)<span class="rounded-full border px-3 py-1 text-xs" style="border-color: var(--border);">{{ $phone->phone }}</span>@empty<span style="color: var(--fg-muted);">Nenhum telefone adicional.</span>@endforelse</div></div>
                        @foreach (($call->contact->extra ?? []) as $key => $value)
                            <div><p class="text-xs" style="color: var(--fg-muted);">{{ str($key)->replace('_', ' ')->title() }}</p><p class="break-words">{{ is_scalar($value) ? $value : json_encode($value) }}</p></div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm" style="color: var(--fg-muted);">Esta e uma chamada manual e nao possui dados de mailing vinculados.</p>
                @endif
            </x-ui.modal>
        @endforeach

        @if ($activeCall)
            <div class="absolute inset-0 z-20 grid min-h-full place-items-center rounded-lg bg-slate-950/70 px-4 text-center text-white backdrop-blur-sm" aria-live="assertive">
                <div class="max-w-md rounded-lg border border-white/20 bg-slate-900/90 p-6 shadow-xl">
                    <p class="text-lg font-bold">Voce esta em chamada</p>
                    <p class="mt-2 text-sm text-slate-200">O dashboard fica bloqueado durante a ligação. Retorne ao painel do agente para acompanhar ou encerrar a chamada.</p>
                    <a href="{{ route('tenant.agent.index') }}" class="th-focus mt-5 inline-flex rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background: var(--primary);">Voltar ao painel do agente</a>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
