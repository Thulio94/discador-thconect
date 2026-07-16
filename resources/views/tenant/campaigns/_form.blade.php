@php
    $campaign = $campaign ?? null;
    $selectedTeamIds = collect(old('team_ids', $campaign?->teams?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $oldListIds = old('list_ids');
    $selectedListIds = $oldListIds
        ? collect($oldListIds)->filter(fn ($value) => ! is_array($value) || filled($value['selected'] ?? null))->map(fn ($value, $key) => (int) (is_array($value) ? $key : $value))->all()
        : collect($campaign?->contactLists?->pluck('id')->all() ?? [])->map(fn ($id) => (int) $id)->all();
    $listWeights = $oldListIds
        ? collect($oldListIds)->mapWithKeys(fn ($value, $key) => [(int) (is_array($value) ? $key : $value) => (int) (is_array($value) ? ($value['weight'] ?? 1) : 1)])->all()
        : collect($campaign?->contactLists ?? [])->mapWithKeys(fn ($list) => [$list->id => (int) ($list->pivot->weight ?? 1)])->all();
    $schedule = old('operational_schedule', $campaign?->operational_schedule ?? []);
    $serviceFields = old('service_fields_schema', $campaign?->service_fields_schema ?? []);
    $leadPhoneStrategy = old('lead_phone_strategy', $campaign?->lead_phone_strategy ?? 'vertical');
    $amdMode = old('amd_mode', $campaign?->amd_mode ?? 'disabled');
    $fieldRows = collect(range(0, 4))->map(fn ($index) => $serviceFields[$index] ?? [])->all();
    $days = [
        'monday' => 'Segunda',
        'tuesday' => 'Terca',
        'wednesday' => 'Quarta',
        'thursday' => 'Quinta',
        'friday' => 'Sexta',
        'saturday' => 'Sabado',
        'sunday' => 'Domingo',
    ];
@endphp

<div class="grid gap-4">
    <div class="grid gap-2 sm:grid-cols-4">
        @foreach (['Etapa 1' => 'Basico', 'Etapa 2' => 'Operacao', 'Etapa 3' => 'Publico', 'Etapa 4' => 'Revisao'] as $step => $label)
            <div class="rounded-lg border p-3" style="border-color: var(--border); background: var(--surface-alt);">
                <p class="text-[11px] font-semibold uppercase" style="color: var(--fg-muted);">{{ $step }}</p>
                <p class="mt-1 text-sm font-semibold">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 rounded-lg border p-4" style="border-color: var(--border);">
        <p class="text-sm font-semibold">Etapa 1 - Basico</p>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Nome</span>
            <input name="name" value="{{ old('name', $campaign?->name) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
        </label>

        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Descricao</span>
            <textarea name="description" rows="2" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">{{ old('description', $campaign?->description) }}</textarea>
        </label>
    </div>

    <div class="grid gap-4 rounded-lg border p-4" style="border-color: var(--border);">
        <p class="text-sm font-semibold">Etapa 2 - Operacao</p>
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Script de atendimento</span>
            <textarea name="service_script" rows="4" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" placeholder="Abordagem, perguntas e proximos passos.">{{ old('service_script', $campaign?->service_script) }}</textarea>
        </label>

        <div>
            <p class="mb-2 text-sm font-semibold">Campos dinamicos</p>
            <div class="grid gap-2">
                @foreach ($fieldRows as $index => $field)
                    <div class="grid gap-2 rounded-lg border p-3 text-sm lg:grid-cols-[110px_minmax(0,1fr)_minmax(0,1fr)_140px_110px]" style="border-color: var(--border);">
                        <label class="flex items-center gap-2 font-semibold">
                            <input type="checkbox" name="service_fields_schema[{{ $index }}][enabled]" value="1" @checked(filled($field['enabled'] ?? null) || filled($field['key'] ?? null))>
                            <span>Ativo</span>
                        </label>
                        <label class="grid gap-1">
                            <span class="text-xs" style="color: var(--fg-muted);">Rotulo</span>
                            <input name="service_fields_schema[{{ $index }}][label]" value="{{ $field['label'] ?? '' }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" placeholder="CPF Cliente">
                        </label>
                        <label class="grid gap-1">
                            <span class="text-xs" style="color: var(--fg-muted);">Chave</span>
                            <input name="service_fields_schema[{{ $index }}][key]" value="{{ $field['key'] ?? '' }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" placeholder="cpf_cliente">
                        </label>
                        <label class="grid gap-1">
                            <span class="text-xs" style="color: var(--fg-muted);">Tipo</span>
                            <select name="service_fields_schema[{{ $index }}][type]" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                                <option value="text" @selected(($field['type'] ?? 'text') === 'text')>Texto</option>
                                <option value="number" @selected(($field['type'] ?? 'text') === 'number')>Numero</option>
                                <option value="date" @selected(($field['type'] ?? 'text') === 'date')>Data</option>
                                <option value="cpf" @selected(($field['type'] ?? 'text') === 'cpf')>CPF</option>
                                <option value="cnpj" @selected(($field['type'] ?? 'text') === 'cnpj')>CNPJ</option>
                                <option value="phone" @selected(($field['type'] ?? 'text') === 'phone')>Telefone</option>
                                <option value="email" @selected(($field['type'] ?? 'text') === 'email')>E-mail</option>
                                <option value="currency" @selected(($field['type'] ?? 'text') === 'currency')>Moeda</option>
                            </select>
                        </label>
                        <label class="flex items-center gap-2 font-semibold">
                            <input type="checkbox" name="service_fields_schema[{{ $index }}][required]" value="1" @checked(filled($field['required'] ?? null))>
                            <span>Obrigatorio</span>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Modo</span>
            <select name="dialing_mode" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="preview" @selected(old('dialing_mode', $campaign?->dialing_mode ?? 'preview') === 'preview')>Preview</option>
                <option value="manual" @selected(old('dialing_mode', $campaign?->dialing_mode) === 'manual')>Manual</option>
                <option value="power" @selected(old('dialing_mode', $campaign?->dialing_mode) === 'power')>Power</option>
                <option value="predictive" @selected(old('dialing_mode', $campaign?->dialing_mode) === 'predictive')>Preditivo</option>
            </select>
        </label>

        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Estrategia de telefones do lead</span>
            <select name="lead_phone_strategy" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="vertical" @selected($leadPhoneStrategy === 'vertical')>Horizontal desativado</option>
                <option value="horizontal" @selected($leadPhoneStrategy === 'horizontal')>Horizontal ativado</option>
            </select>
            <span class="text-xs" style="color: var(--fg-muted);">desativado tenta principais primeiro; ativado tenta alternativos do mesmo lead antes de seguir.</span>
        </label>

        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Status</span>
            <select name="status" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <option value="draft" @selected(old('status', $campaign?->status ?? 'draft') === 'draft')>Rascunho</option>
                <option value="active" @selected(old('status', $campaign?->status) === 'active')>Ativa</option>
                <option value="paused" @selected(old('status', $campaign?->status) === 'paused')>Pausada</option>
                <option value="finished" @selected(old('status', $campaign?->status) === 'finished')>Finalizada</option>
            </select>
        </label>

        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Prioridade</span>
            <input name="priority" type="number" min="1" max="10" value="{{ old('priority', $campaign?->priority ?? 1) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        </label>
        </div>

        <div class="grid gap-4 rounded-lg border p-3 md:grid-cols-2 xl:grid-cols-4" style="border-color: var(--border); background: var(--surface-alt);">
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Maximo de tentativas por lead</span>
                <input name="max_attempts_per_contact" type="number" min="1" max="50" value="{{ old('max_attempts_per_contact', $campaign?->max_attempts_per_contact) }}" placeholder="Usar regra da tabulacao" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <span class="text-xs" style="color: var(--fg-muted);">Aplica o menor limite entre campanha e tabulacao.</span>
            </label>
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Intervalo minimo entre tentativas</span>
                <input name="retry_delay_minutes" type="number" min="0" max="10080" value="{{ old('retry_delay_minutes', $campaign?->retry_delay_minutes) }}" placeholder="Usar regra da tabulacao" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <span class="text-xs" style="color: var(--fg-muted);">Em minutos; usa o maior intervalo entre campanha e tabulacao.</span>
            </label>
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Tempo para tabulacao</span>
                <input name="wrap_up_minutes" type="number" min="1" max="60" value="{{ old('wrap_up_minutes', $campaign?->wrap_up_minutes ?? 1) }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <span class="text-xs" style="color: var(--fg-muted);">Em minutos. Nesse periodo o agente nao recebe outra chamada.</span>
            </label>
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Teto simultaneo da campanha</span>
                <input name="max_concurrent_calls" type="number" min="1" max="100000" value="{{ old('max_concurrent_calls', $campaign?->max_concurrent_calls) }}" placeholder="Usar limite do contrato" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <span class="text-xs" style="color: var(--fg-muted);">Nunca ultrapassa o limite contratado da empresa.</span>
            </label>
        </div>

        <div class="grid gap-4 rounded-lg border p-3 md:grid-cols-2" style="border-color: var(--border);">
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">AMD</span>
                <select name="amd_mode" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    <option value="disabled" @selected($amdMode === 'disabled')>Desativado</option>
                    <option value="simulated" @selected($amdMode === 'simulated')>Simulado</option>
                    <option value="asterisk" @selected($amdMode === 'asterisk')>Asterisk / rota</option>
                </select>
                <span class="text-xs" style="color: var(--fg-muted);">A rota Asterisk envia o resultado AMD recebido pelo dialplan ou provedor para o historico da chamada.</span>
            </label>
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Rota de discagem</span>
                <input name="dialing_route" value="{{ old('dialing_route', $campaign?->dialing_route) }}" placeholder="Ex.: Saida principal" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                <span class="text-xs" style="color: var(--fg-muted);">Use a mesma identificacao configurada em Telefonia para facilitar a operacao.</span>
            </label>
        </div>

        <div>
            <p class="mb-2 text-sm font-semibold">Agenda operacional</p>
            <div class="grid gap-2">
                @foreach ($days as $key => $label)
                    @php($day = $schedule[$key] ?? [])
                    <div class="grid gap-2 rounded-lg border p-3 text-sm md:grid-cols-[130px_1fr_1fr]" style="border-color: var(--border);">
                        <label class="flex items-center gap-2 font-semibold">
                            <input type="checkbox" name="operational_schedule[{{ $key }}][enabled]" value="1" @checked(filled($day['enabled'] ?? null))>
                            <span>{{ $label }}</span>
                        </label>
                        <label class="grid gap-1">
                            <span class="text-xs" style="color: var(--fg-muted);">Inicio</span>
                            <input type="time" name="operational_schedule[{{ $key }}][start]" value="{{ $day['start'] ?? '09:00' }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                        </label>
                        <label class="grid gap-1">
                            <span class="text-xs" style="color: var(--fg-muted);">Fim</span>
                            <input type="time" name="operational_schedule[{{ $key }}][end]" value="{{ $day['end'] ?? '18:00' }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-4 rounded-lg border p-4" style="border-color: var(--border);">
        <p class="text-sm font-semibold">Etapa 3 - Publico</p>
        <div class="grid gap-4 md:grid-cols-2">
        <div>
            <p class="mb-2 text-sm font-semibold">Equipes</p>
            @forelse ($teams as $team)
                <label class="mb-2 flex items-center gap-2 rounded-lg border p-3 text-sm" style="border-color: var(--border);">
                    <input type="checkbox" name="team_ids[]" value="{{ $team->id }}" @checked(in_array($team->id, $selectedTeamIds, true))>
                    <span>{{ $team->name }}</span>
                </label>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Cadastre uma equipe antes.</p>
            @endforelse
        </div>

        <div>
            <p class="mb-2 text-sm font-semibold">Listas</p>
            @forelse ($lists as $list)
                <div class="mb-2 grid gap-2 rounded-lg border p-3 text-sm sm:grid-cols-[minmax(0,1fr)_90px]" style="border-color: var(--border);">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="list_ids[{{ $list->id }}][selected]" value="1" @checked(in_array($list->id, $selectedListIds, true))>
                        <span>{{ $list->name }}</span>
                    </label>
                    <label class="grid gap-1">
                        <span class="text-xs font-semibold" style="color: var(--fg-muted);">Peso</span>
                        <input type="number" min="1" max="10" name="list_ids[{{ $list->id }}][weight]" value="{{ $listWeights[$list->id] ?? 1 }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    </label>
                </div>
            @empty
                <p class="text-sm" style="color: var(--fg-muted);">Cadastre uma lista antes.</p>
            @endforelse
        </div>
        </div>
    </div>

    <div class="grid gap-3 rounded-lg border p-4" style="border-color: var(--border); background: var(--surface-alt);">
        <p class="text-sm font-semibold">Etapa 4 - Revisao da campanha</p>
        <div class="grid gap-2 text-sm sm:grid-cols-4">
            <div>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Modo</p>
                <p class="mt-1 font-semibold">{{ old('dialing_mode', $campaign?->dialing_mode ?? 'preview') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Telefones</p>
                <p class="mt-1 font-semibold">{{ $leadPhoneStrategy === 'horizontal' ? 'Horizontal ativado' : 'Horizontal desativado' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Status</p>
                <p class="mt-1 font-semibold">{{ old('status', $campaign?->status ?? 'draft') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Prioridade</p>
                <p class="mt-1 font-semibold">{{ old('priority', $campaign?->priority ?? 1) }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Tentativas</p>
                <p class="mt-1 font-semibold">{{ old('max_attempts_per_contact', $campaign?->max_attempts_per_contact) ?: 'Pela tabulacao' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Tabulacao</p>
                <p class="mt-1 font-semibold">{{ old('wrap_up_minutes', $campaign?->wrap_up_minutes ?? 1) }} minuto(s)</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">AMD</p>
                <p class="mt-1 font-semibold">{{ $amdMode === 'simulated' ? 'Simulado' : 'Desativado' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Rota</p>
                <p class="mt-1 font-semibold">{{ old('dialing_route', $campaign?->dialing_route) ?: 'Em breve' }}</p>
            </div>
        </div>
    </div>
</div>
