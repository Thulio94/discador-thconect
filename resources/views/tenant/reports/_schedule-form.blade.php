@php
    $scheduleFilters = $schedule?->filters ?? [];
    $inputClass = 'rounded-lg border px-3 py-2';
    $inputStyle = 'border-color: var(--border); background: var(--surface); color: var(--fg);';
@endphp

<div
    x-data="{ frequency: @js(old('frequency', $schedule?->frequency ?? 'daily')) }"
    class="grid gap-4"
>
    <div class="grid gap-4 md:grid-cols-2">
        <label class="grid gap-1 text-sm md:col-span-2">
            <span class="font-semibold">Nome</span>
            <input
                type="text"
                name="name"
                value="{{ old('name', $schedule?->name) }}"
                maxlength="255"
                required
                class="{{ $inputClass }}"
                style="{{ $inputStyle }}"
            >
        </label>

        <label class="grid gap-1 text-sm md:col-span-2">
            <span class="font-semibold">Relatório</span>
            <select name="report_type" required class="{{ $inputClass }}" style="{{ $inputStyle }}">
                @foreach (collect($reportCatalog)->except('exports') as $value => $definition)
                    <option value="{{ $value }}" @selected(old('report_type', $schedule?->report_type ?? 'calls') === $value)>{{ $definition['title'] }}</option>
                @endforeach
            </select>
        </label>

        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Formato</span>
            <select name="format" required class="{{ $inputClass }}" style="{{ $inputStyle }}">
                @foreach (['csv' => 'CSV', 'xlsx' => 'XLSX', 'pdf' => 'PDF'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('format', $schedule?->format ?? 'xlsx') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Periodo</span>
            <select name="period_days" required class="{{ $inputClass }}" style="{{ $inputStyle }}">
                @foreach ([7, 15, 30, 60, 90] as $days)
                    <option value="{{ $days }}" @selected((int) old('period_days', $scheduleFilters['period_days'] ?? 30) === $days)>Ultimos {{ $days }} dias</option>
                @endforeach
            </select>
        </label>

        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Frequencia</span>
            <select
                name="frequency"
                x-model="frequency"
                required
                class="{{ $inputClass }}"
                style="{{ $inputStyle }}"
            >
                <option value="daily">Diaria</option>
                <option value="weekly">Semanal</option>
            </select>
        </label>

        <label class="grid gap-1 text-sm" x-show="frequency === 'weekly'" x-cloak>
            <span class="font-semibold">Dia da semana</span>
            <select name="weekday" class="{{ $inputClass }}" style="{{ $inputStyle }}">
                @foreach ([0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terca-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sabado'] as $day => $label)
                    <option value="{{ $day }}" @selected((int) old('weekday', $schedule?->weekday ?? 1) === $day)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Horario</span>
            <input
                type="time"
                name="run_time"
                value="{{ old('run_time', $schedule?->run_time ? substr($schedule->run_time, 0, 5) : '08:00') }}"
                required
                class="{{ $inputClass }}"
                style="{{ $inputStyle }}"
            >
        </label>

        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Status</span>
            <select name="status" required class="{{ $inputClass }}" style="{{ $inputStyle }}">
                <option value="active" @selected(old('status', $schedule?->status ?? 'active') === 'active')>Ativo</option>
                <option value="inactive" @selected(old('status', $schedule?->status) === 'inactive')>Inativo</option>
            </select>
        </label>
    </div>

    <div class="border-t pt-4" style="border-color: var(--border);">
        <p class="text-sm font-semibold">Filtros do relatorio</p>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Agente</span>
                <select name="user_id" class="{{ $inputClass }}" style="{{ $inputStyle }}">
                    <option value="">Todos</option>
                    @foreach ($agents as $agent)
                        <option value="{{ $agent->id }}" @selected((int) old('user_id', $scheduleFilters['user_id'] ?? 0) === $agent->id)>{{ $agent->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Campanha</span>
                <select name="campaign_id" class="{{ $inputClass }}" style="{{ $inputStyle }}">
                    <option value="">Todas</option>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" @selected((int) old('campaign_id', $scheduleFilters['campaign_id'] ?? 0) === $campaign->id)>{{ $campaign->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Lista</span>
                <select name="list_id" class="{{ $inputClass }}" style="{{ $inputStyle }}">
                    <option value="">Todas</option>
                    @foreach ($lists as $list)
                        <option value="{{ $list->id }}" @selected((int) old('list_id', $scheduleFilters['list_id'] ?? 0) === $list->id)>{{ $list->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Equipe</span>
                <select name="team_id" class="{{ $inputClass }}" style="{{ $inputStyle }}">
                    <option value="">Todas</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected((int) old('team_id', $scheduleFilters['team_id'] ?? 0) === $team->id)>{{ $team->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="grid gap-1 text-sm md:col-span-2">
                <span class="font-semibold">Resultado</span>
                <select name="disposition_id" class="{{ $inputClass }}" style="{{ $inputStyle }}">
                    <option value="">Todos</option>
                    @foreach ($dispositions as $disposition)
                        <option value="{{ $disposition->id }}" @selected((int) old('disposition_id', $scheduleFilters['disposition_id'] ?? 0) === $disposition->id)>{{ $disposition->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
</div>
