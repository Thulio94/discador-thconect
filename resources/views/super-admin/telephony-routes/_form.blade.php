@props(['route' => null])

<div class="grid gap-4 md:grid-cols-2" x-data="{ auth: '{{ old('authentication_mode', $route?->authentication_mode ?? 'ip') }}' }">
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">No Asterisk</span>
        <select name="asterisk_node_id" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            @foreach($asteriskNodes as $node)
                <option value="{{ $node->id }}" @selected((int) old('asterisk_node_id', $route?->asterisk_node_id) === $node->id)>{{ $node->name }} · {{ $node->max_channels }} canais</option>
            @endforeach
        </select>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Canais contratados</span>
        <input type="number" min="1" max="5000" name="max_concurrent_channels" value="{{ old('max_concurrent_channels', $route?->max_concurrent_channels) }}" placeholder="Sem teto proprio" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Nome da rota</span>
        <input name="name" value="{{ old('name', $route?->name) }}" placeholder="SMP Classificada" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Operadora</span>
        <input name="provider" value="{{ old('provider', $route?->provider) }}" placeholder="Solutions VoIP" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Tech (opcional)</span>
        <input name="tech" inputmode="numeric" value="{{ old('tech', $route?->tech) }}" placeholder="5006" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        <span class="text-xs" style="color: var(--fg-muted);">Quando informado: Tech + 55 + DDD + telefone. Para rota liberada por IP sem Tech, deixe vazio e configure o prefixo nacional.</span>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Dominio ou IP SIP</span>
        <input name="host" value="{{ old('host', $route?->host) }}" placeholder="sbc.operadora.com.br" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Porta SIP</span>
        <input type="number" min="1" max="65535" name="sip_port" value="{{ old('sip_port', $route?->sip_port ?? 5060) }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">From Domain</span>
        <input name="from_domain" value="{{ old('from_domain', $route?->from_domain) }}" placeholder="sbc.operadora.com.br" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Outbound proxy</span>
        <input name="outbound_proxy" value="{{ old('outbound_proxy', $route?->outbound_proxy) }}" placeholder="sip:proxy.operadora.com.br:5060;lr" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Transporte SIP</span>
        <select name="transport" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            <option value="udp" @selected(old('transport', $route?->transport ?? 'udp') === 'udp')>UDP</option>
            <option value="tcp" @selected(old('transport', $route?->transport) === 'tcp')>TCP</option>
            <option value="tls" @selected(old('transport', $route?->transport) === 'tls')>TLS</option>
        </select>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Autenticacao da operadora</span>
        <select name="authentication_mode" x-model="auth" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            <option value="ip">Por IP liberado</option>
            <option value="registration">Usuario e senha SIP</option>
            <option value="digest">Digest no INVITE, sem registro</option>
        </select>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Caller ID</span>
        <input name="caller_id" value="{{ old('caller_id', $route?->caller_id) }}" placeholder="Numero autorizado" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Prefixo de discagem nacional</span>
        <input name="dial_prefix" inputmode="numeric" value="{{ old('dial_prefix', $route?->dial_prefix) }}" placeholder="0" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        <span class="text-xs" style="color: var(--fg-muted);">Obrigatorio sem Tech. Ex.: 0 envia 0 + DDD + telefone.</span>
    </label>
    <label class="grid gap-1 text-sm" x-show="auth === 'registration' || auth === 'digest'" x-cloak>
        <span class="font-semibold">Usuario SIP</span>
        <input name="sip_username" autocomplete="off" value="{{ old('sip_username', $route?->sip_username) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm" x-show="auth === 'registration' || auth === 'digest'" x-cloak>
        <span class="font-semibold">Senha SIP</span>
        <input name="sip_password" type="password" autocomplete="new-password" placeholder="{{ $route?->sip_password ? 'Mantida com seguranca' : 'Senha SIP' }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm" x-show="auth === 'registration' || auth === 'digest'" x-cloak>
        <span class="font-semibold">From User</span>
        <input name="from_user" value="{{ old('from_user', $route?->from_user) }}" placeholder="Usuario apresentado no From" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm" x-show="auth === 'registration'" x-cloak>
        <span class="font-semibold">Contact User</span>
        <input name="contact_user" value="{{ old('contact_user', $route?->contact_user) }}" placeholder="Usuario do Contact no REGISTER" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Codecs</span>
        <input name="codecs" value="{{ old('codecs', $route?->codecs ?? 'alaw,g729') }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">DTMF</span>
        <select name="dtmf_mode" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            @foreach(['rfc4733' => 'RFC 4733', 'info' => 'SIP INFO', 'inband' => 'Inband', 'auto' => 'Automatico'] as $value => $label)
                <option value="{{ $value }}" @selected(old('dtmf_mode', $route?->dtmf_mode ?? 'rfc4733') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Identidade do Caller ID</span>
        <select name="caller_id_header" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            @foreach(['none' => 'Somente From', 'pai' => 'P-Asserted-Identity', 'rpid' => 'Remote-Party-ID', 'both' => 'PAI e RPID'] as $value => $label)
                <option value="{{ $value }}" @selected(old('caller_id_header', $route?->caller_id_header ?? 'none') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">OPTIONS a cada (segundos)</span>
        <input type="number" min="0" max="300" name="qualify_frequency" value="{{ old('qualify_frequency', $route?->qualify_frequency ?? 15) }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm" x-show="auth === 'registration'" x-cloak>
        <span class="font-semibold">Expiracao do registro (segundos)</span>
        <input type="number" min="60" max="86400" name="registration_expiration" value="{{ old('registration_expiration', $route?->registration_expiration ?? 3600) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Prioridade</span>
        <input type="number" min="1" max="1000" name="priority" value="{{ old('priority', $route?->priority ?? 100) }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Contexto Asterisk</span>
        <input name="dialplan_context" value="{{ old('dialplan_context', $route?->dialplan_context ?? 'thconect-outbound') }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-semibold">Extensao Asterisk</span>
        <input name="dialplan_extension" value="{{ old('dialplan_extension', $route?->dialplan_extension ?? 's') }}" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
    </label>
    <label class="flex items-center gap-2 self-end rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border);">
        <input type="hidden" name="enabled" value="0">
        <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $route?->enabled))>
        <span>Habilitar rota para testes e chamadas</span>
    </label>
</div>
