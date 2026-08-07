<x-ui.card class="mb-4">
    <form method="GET" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <input type="hidden" name="report" value="{{ $selectedReport }}">
        <label class="grid gap-1 text-sm"><span>Data inicial</span><input type="date" name="date_from" value="{{ $filters['date_from']->toDateString() }}" class="rounded-lg border px-3 py-2" style="border-color:var(--border);background:var(--surface);"></label>
        <label class="grid gap-1 text-sm"><span>Data final</span><input type="date" name="date_to" value="{{ $filters['date_to']->toDateString() }}" class="rounded-lg border px-3 py-2" style="border-color:var(--border);background:var(--surface);"></label>
        @if (in_array($selectedReport, ['calls','agents','campaigns','qualifications','teams','utilization','callbacks']))
            <label class="grid gap-1 text-sm"><span>Agente</span><select name="user_id" class="rounded-lg border px-3 py-2" style="border-color:var(--border);background:var(--surface);"><option value="">Todos</option>@foreach($options['agents'] as $agent)<option value="{{ $agent->id }}" @selected($filters['user_id']===$agent->id)>{{ $agent->name }} · {{ $agent->extension }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-sm"><span>Campanha</span><select name="campaign_id" class="rounded-lg border px-3 py-2" style="border-color:var(--border);background:var(--surface);"><option value="">Todas</option>@foreach($options['campaigns'] as $campaign)<option value="{{ $campaign->id }}" @selected($filters['campaign_id']===$campaign->id)>{{ $campaign->name }}</option>@endforeach</select></label>
        @endif
        @if (in_array($selectedReport, ['calls','agents','qualifications','teams','utilization']))
            <label class="grid gap-1 text-sm"><span>Equipe</span><select name="team_id" class="rounded-lg border px-3 py-2" style="border-color:var(--border);background:var(--surface);"><option value="">Todas</option>@foreach($options['teams'] as $team)<option value="{{ $team->id }}" @selected($filters['team_id']===$team->id)>{{ $team->name }}</option>@endforeach</select></label>
        @endif
        @if (in_array($selectedReport, ['calls','lists','qualifications']))
            <label class="grid gap-1 text-sm"><span>Lista</span><select name="list_id" class="rounded-lg border px-3 py-2" style="border-color:var(--border);background:var(--surface);"><option value="">Todas</option>@foreach($options['lists'] as $list)<option value="{{ $list->id }}" @selected($filters['list_id']===$list->id)>{{ $list->name }}</option>@endforeach</select></label>
        @endif
        @if (in_array($selectedReport, ['calls','qualifications']))
            <label class="grid gap-1 text-sm"><span>Tabulação</span><select name="disposition_id" class="rounded-lg border px-3 py-2" style="border-color:var(--border);background:var(--surface);"><option value="">Todas</option>@foreach($options['dispositions'] as $disposition)<option value="{{ $disposition->id }}" @selected($filters['disposition_id']===$disposition->id)>{{ $disposition->name }}</option>@endforeach</select></label>
        @endif
        @if ($selectedReport === 'calls')
            <label class="grid gap-1 text-sm"><span>Telefone</span><input name="phone" value="{{ $filters['phone'] }}" placeholder="DDD + número" class="rounded-lg border px-3 py-2" style="border-color:var(--border);background:var(--surface);"></label>
            <label class="grid gap-1 text-sm"><span>Rota</span><select name="telephony_route_id" class="rounded-lg border px-3 py-2" style="border-color:var(--border);background:var(--surface);"><option value="">Todas</option>@foreach($options['routes'] as $route)<option value="{{ $route->id }}" @selected($filters['telephony_route_id']===$route->id)>{{ $route->name }} · {{ $route->tech }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-sm"><span>Status</span><select name="status" class="rounded-lg border px-3 py-2" style="border-color:var(--border);background:var(--surface);"><option value="">Todos</option>@foreach(['dialing'=>'Discando','ringing'=>'Chamando','in_progress'=>'Em chamada','wrap_up'=>'Tabulando','ended'=>'Encerrada','cancelled'=>'Cancelada'] as $value=>$label)<option value="{{ $value }}" @selected($filters['status']===$value)>{{ $label }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-sm"><span>Quem desligou</span><select name="hangup_party" class="rounded-lg border px-3 py-2" style="border-color:var(--border);background:var(--surface);"><option value="">Todos</option>@foreach(['agent'=>'Agente','customer'=>'Cliente','carrier'=>'Operadora','system'=>'Sistema','unknown'=>'Desconhecido'] as $value=>$label)<option value="{{ $value }}" @selected($filters['hangup_party']===$value)>{{ $label }}</option>@endforeach</select></label>
        @endif
        @if ($selectedReport === 'callbacks')
            <label class="grid gap-1 text-sm"><span>Situação</span><select name="callback_status" class="rounded-lg border px-3 py-2" style="border-color:var(--border);background:var(--surface);"><option value="">Todas</option>@foreach(['pending'=>'Pendente','completed'=>'Concluído','cancelled'=>'Cancelado'] as $value=>$label)<option value="{{ $value }}" @selected($filters['callback_status']===$value)>{{ $label }}</option>@endforeach</select></label>
        @endif
        <div class="flex items-end gap-2"><x-ui.button type="submit">Buscar</x-ui.button><a href="{{ route('tenant.reports.index', ['report'=>$selectedReport]) }}" class="th-focus rounded-lg border px-3 py-2 text-sm font-semibold" style="border-color:var(--border);">Limpar</a></div>
    </form>
</x-ui.card>
