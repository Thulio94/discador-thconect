@props(['company' => null])

@php
    $license = $company?->license;
    $resource = $company?->resourceLimit;
    $companyStatus = $company?->status ?? 'active';
    $companyStatusLabel = ['active' => 'Ativa', 'blocked' => 'Bloqueada', 'inactive' => 'Inativa'][$companyStatus];
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Nome</span>
        <input name="name" value="{{ old('name', $company?->name) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Documento</span>
        <input name="document" value="{{ old('document', $company?->document) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <div class="grid gap-2 text-sm md:col-span-2">
        <span class="font-semibold">Rotas liberadas para campanhas</span>
        <div class="grid gap-2 md:grid-cols-2">
            @foreach ($telephonyRoutes as $telephonyRoute)
                <label class="flex items-center gap-2 rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface-alt);">
                    <input type="checkbox" name="telephony_route_ids[]" value="{{ $telephonyRoute->id }}" @checked(in_array($telephonyRoute->id, old('telephony_route_ids', $company?->telephonyRoutes->pluck('id')->all() ?? [])))>
                    <span>{{ $telephonyRoute->name }} · {{ filled($telephonyRoute->tech) ? 'Tech '.$telephonyRoute->tech : 'Sem Tech' }}</span>
                </label>
            @endforeach
        </div>
        <span class="text-xs" style="color: var(--fg-muted);">A empresa podera apenas escolher estas rotas nas campanhas. Dados tecnicos seguem exclusivos do SuperAdmin.</span>
    </div>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Status</span>
        <input type="hidden" name="status" value="{{ $companyStatus }}">
        <span class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface-alt); color: var(--fg-muted);">{{ $companyStatusLabel }}</span>
        <span class="text-xs" style="color: var(--fg-muted);">Use os controles de bloqueio e inativacao na empresa.</span>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Dia de vencimento mensal</span>
        <input type="number" min="1" max="28" name="billing_due_day" value="{{ old('billing_due_day', $license?->billing_due_day ?? min(28, now()->day)) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
        <span class="text-xs" style="color: var(--fg-muted);">Dia mensal previsto para pagamento. Use de 1 a 28 para funcionar em todos os meses.</span>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Chamadas por operador disponivel</span>
        <input type="number" min="1" max="10" name="max_calls_per_available_agent" value="{{ old('max_calls_per_available_agent', $license?->max_calls_per_available_agent ?? 1) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);" required>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Usuarios disponiveis simultaneamente</span>
        <input type="number" min="1" max="100000" name="max_concurrent_agents" value="{{ old('max_concurrent_agents', $license?->max_concurrent_agents) }}" placeholder="Sem limite" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        <span class="text-xs" style="color: var(--fg-muted);">A licenca e consumida por qualquer usuario da empresa ao ficar disponivel.</span>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Chamadas simultaneas maximas</span>
        <input type="number" min="1" max="100000" name="max_concurrent_calls" value="{{ old('max_concurrent_calls', $license?->max_concurrent_calls) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Retenção de gravações (dias)</span>
        <input type="number" min="1" max="3650" name="recording_retention_days" value="{{ old('recording_retention_days', $company?->recording_retention_days ?? 90) }}" placeholder="Sem expiração" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        <span class="text-xs" style="color: var(--fg-muted);">Deixe vazio para não expirar. O super admin define essa regra por empresa.</span>
    </label>
</div>

<section class="mt-5 border-t pt-5" style="border-color: var(--border);">
    <h3 class="font-semibold">Protecao de recursos</h3>
    <p class="mb-4 text-xs" style="color: var(--fg-muted);">Limites tecnicos internos. Nao limitam usuarios conectados e nao aparecem no painel da empresa.</p>
    @php($resourceFields = [
        'max_registered_users' => ['Usuarios cadastrados', 1000], 'max_active_contacts' => ['Contatos ativos', 500000],
        'max_import_rows' => ['Linhas por importacao', 100000], 'max_concurrent_imports' => ['Importacoes simultaneas', 1],
        'max_active_campaigns' => ['Campanhas ativas', 20], 'max_active_webhooks' => ['Webhooks ativos', 20],
        'max_concurrent_exports' => ['Exportacoes simultaneas', 2], 'max_csv_rows' => ['Linhas por CSV', 1000000],
        'max_xlsx_rows' => ['Linhas por XLSX', 200000], 'max_pdf_rows' => ['Linhas por PDF', 10000],
        'max_report_range_days' => ['Periodo de relatorio (dias)', 90], 'max_daily_attempts' => ['Tentativas diarias', 500000],
        'dialer_weight' => ['Peso no discador (1 a 10)', 1], 'hot_history_months' => ['Historico detalhado (meses)', 24],
    ])
    <div class="grid gap-3 md:grid-cols-3">
        @foreach($resourceFields as $field => [$label, $default])
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">{{ $label }}</span>
                <input type="number" min="1" name="{{ $field }}" value="{{ old($field, $resource?->{$field} ?? $default) }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            </label>
        @endforeach
    </div>
</section>

<div class="mt-4 grid gap-3 md:grid-cols-4">
    @foreach ([
        'power_enabled' => 'Power',
        'predictive_enabled' => 'Preditivo',
        'preview_enabled' => 'Preview',
        'amd_enabled' => 'AMD',
    ] as $field => $label)
        <label class="flex items-center gap-2 rounded-lg border p-3 text-sm" style="border-color: var(--border);">
            <input type="hidden" name="{{ $field }}" value="0">
            <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $license?->{$field} ?? $field === 'power_enabled' || $field === 'preview_enabled'))>
            <span>{{ $label }}</span>
        </label>
    @endforeach
</div>
