<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Operacao</p>
            <h1 class="text-2xl font-bold" style="color: var(--fg);">Supervisao</h1>
        </div>
    </x-slot>

    <div x-data="{ spyAgent: null }">
        <form method="GET" action="{{ route('tenant.supervision.index') }}" class="mb-4 flex flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:items-end" style="border-color: var(--border); background: var(--surface);">
            <label class="grid flex-1 gap-1 text-xs font-semibold" style="color: var(--fg-muted);">
                Status
                <select name="status" x-on:change="$el.form.requestSubmit()" x-on:blur="$el.form.requestSubmit()" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    <option value="">Todos os status</option>
                    @foreach (['available' => 'Disponivel', 'paused' => 'Em pausa', 'in_call' => 'Em chamada', 'wrap_up' => 'Tabulando', 'offline' => 'Offline'] as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid flex-1 gap-1 text-xs font-semibold" style="color: var(--fg-muted);">
                Equipe
                <select name="team_id" x-on:change="$el.form.requestSubmit()" x-on:blur="$el.form.requestSubmit()" class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    <option value="">Todas as equipes</option>
                    @foreach ($teamOptions as $team)
                        <option value="{{ $team['id'] }}" @selected($selectedTeamId === $team['id'])>{{ $team['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex gap-2">
                <x-ui.button type="submit"><x-ui.icon name="filter" /> Filtrar</x-ui.button>
                <a href="{{ route('tenant.supervision.index') }}" class="th-focus inline-flex items-center justify-center rounded-lg border px-3 py-2 text-sm font-semibold" style="border-color: var(--border); background: var(--surface-alt); color: var(--fg);">Limpar</a>
            </div>
        </form>

        <div
            class="supervision-workspace"
            x-data="pollingRegion({
                url: @js(route('tenant.supervision.poll', array_filter([
                    'status' => $selectedStatus,
                    'team_id' => $selectedTeamId,
                ])), JSON_UNESCAPED_SLASHES),
                selectors: ['#supervision-agents', '#supervision-calls'],
            intervalSeconds: 20,
            })"
            x-init="start(); subscribeToSupervision(@js($realtimeCompanyId), () => poll())"
            x-on:visibilitychange.document="handleVisibilityChange()"
        >
            @include('tenant.supervision._agents')
            @include('tenant.supervision._calls')
        </div>

        @can('tenant.supervision.operate')
            <div x-show="spyAgent" x-cloak x-transition class="supervision-spy-panel">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-bold">Modo Spy em <span style="color: var(--primary);" x-text="spyAgent?.name"></span></p>
                        <p class="mt-1 text-xs" style="color: var(--fg-muted);">Ramal <span x-text="spyAgent?.extension"></span> · <span x-text="spyAgent?.phone"></span></p>
                    </div>
                    <button type="button" class="th-focus grid h-7 w-7 place-items-center rounded-md" style="color: var(--fg-muted);" x-on:click="spyAgent = null" aria-label="Fechar modo Spy"><x-ui.icon name="cancel" /></button>
                </div>
                <p class="mt-3 text-[11px] font-semibold uppercase" style="color: var(--fg-muted);">Falar com</p>
                <div class="mt-2 grid grid-cols-3 gap-2">
                    @foreach ([
                        'listen' => ['label' => 'So ouvir', 'icon' => 'view'],
                        'whisper' => ['label' => 'Agente', 'icon' => 'users'],
                        'barge' => ['label' => 'Todos', 'icon' => 'team'],
                    ] as $mode => $control)
                        <form method="POST" x-bind:action="spyAgent?.url">
                            @csrf
                            <input type="hidden" name="mode" value="{{ $mode }}">
                            <button type="submit" class="th-focus flex min-h-10 w-full items-center justify-center gap-1 rounded-lg border px-2 text-xs font-semibold" style="border-color: {{ $mode === 'listen' ? 'var(--primary)' : 'var(--border)' }}; background: {{ $mode === 'listen' ? 'var(--primary)' : 'var(--surface-alt)' }}; color: {{ $mode === 'listen' ? '#fff' : 'var(--fg)' }};">
                                <x-ui.icon :name="$control['icon']" /> {{ $control['label'] }}
                            </button>
                        </form>
                    @endforeach
                </div>
                <div class="mt-3 flex items-center gap-2 border-t pt-3 text-[11px]" style="border-color: var(--border); color: var(--fg-muted);">
                    <span class="h-2 w-2 rounded-full" style="background: var(--status-success);"></span>
                    Monitoria protegida pela conexao Asterisk
                </div>
            </div>
        @endcan
    </div>
</x-app-layout>
