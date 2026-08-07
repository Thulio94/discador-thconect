<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">{{ $isSuperAdmin ? 'Super admin' : 'Governanca' }}</p>
                <h1 class="text-2xl font-bold" style="color: var(--fg);">Auditoria</h1>
            </div>
            @if ($isSuperAdmin)
                <x-ui.button
                    type="button"
                    variant="danger"
                    x-data
                    x-on:click="$dispatch('open-modal', 'audit-purge')"
                    :disabled="$purgeSelection === null"
                    title="{{ $purgeSelection ? 'Revisar limpeza do periodo filtrado' : 'Filtre uma empresa ou eventos globais e informe o periodo' }}"
                >
                    Limpar historico
                </x-ui.button>
            @endif
        </div>
    </x-slot>

    <section class="border-y py-4" style="border-color: var(--border);">
        <form method="GET" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="grid gap-1 text-sm">
                <span>Data inicial</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            </label>
            <label class="grid gap-1 text-sm">
                <span>Data final</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            </label>

            @if ($isSuperAdmin)
                <label class="grid gap-1 text-sm">
                    <span>Empresa</span>
                    <select name="company_id" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                        <option value="">Todas</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-end gap-2 pb-2 text-sm">
                    <input type="hidden" name="global_only" value="0">
                    <input type="checkbox" name="global_only" value="1" @checked((bool) ($filters['global_only'] ?? false))>
                    <span>Somente eventos globais</span>
                </label>
            @endif

            <label class="grid gap-1 text-sm">
                <span>Responsavel</span>
                <select name="actor_user_id" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    <option value="">Todos</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}" @selected((string) ($filters['actor_user_id'] ?? '') === (string) $actor->id)>{{ $actor->name }} | {{ $actor->email }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1 text-sm">
                <span>Evento</span>
                <select name="event" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                    <option value="">Todos</option>
                    @foreach ($events as $event)
                        <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $event }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1 text-sm md:col-span-2">
                <span>Busca</span>
                <input name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="Descricao, responsavel, entidade ou evento" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            </label>
            <div class="flex items-end gap-2">
                <x-ui.button type="submit">Filtrar</x-ui.button>
                <a href="{{ route('super-admin.audit-logs.index') }}" class="th-focus rounded-lg border px-3 py-2 text-sm font-semibold" style="border-color: var(--border);">Limpar filtros</a>
            </div>
        </form>
    </section>

    <section class="mt-5 overflow-hidden rounded-lg border" style="border-color: var(--border); background: var(--surface);">
        <div class="overflow-x-auto">
            <table data-audit-table class="w-full table-fixed text-left text-sm">
                <thead style="background: var(--bg-subtle); color: var(--fg-muted);">
                    <tr>
                        <th class="hidden w-36 px-4 py-3 font-semibold sm:table-cell">Data</th>
                        @if ($isSuperAdmin)<th class="hidden w-40 px-4 py-3 font-semibold lg:table-cell">Empresa</th>@endif
                        <th class="hidden w-48 px-4 py-3 font-semibold md:table-cell">Responsavel</th>
                        <th class="w-28 px-3 py-3 font-semibold sm:w-36 sm:px-4">Evento</th>
                        <th class="hidden w-48 px-4 py-3 font-semibold xl:table-cell">Entidade</th>
                        <th class="px-4 py-3 font-semibold">Resumo</th>
                        <th class="w-14 px-4 py-3"><span class="sr-only">Detalhes</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="border-color: var(--border);">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="hidden whitespace-nowrap px-4 py-3 sm:table-cell">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                            @if ($isSuperAdmin)<td class="hidden px-4 py-3 lg:table-cell">{{ $log->company?->name ?? 'Global' }}</td>@endif
                            <td class="hidden px-4 py-3 md:table-cell">
                                <p class="font-medium">{{ $log->actor_name ?? 'Sistema' }}</p>
                                @if ($log->actor_email)<p class="text-xs" style="color: var(--fg-muted);">{{ $log->actor_email }}</p>@endif
                            </td>
                            <td class="px-3 py-3 align-top sm:px-4"><span class="break-words rounded px-2 py-1 text-xs" style="background: var(--bg-subtle);">{{ $log->event }}</span></td>
                            <td class="hidden px-4 py-3 xl:table-cell">{{ $log->subject_label ?? 'Nao informado' }}</td>
                            <td class="break-words px-3 py-3 align-top sm:px-4">{{ $log->description }}</td>
                            <td class="px-4 py-3">
                                <x-ui.action icon="view" label="Detalhes" x-data x-on:click="$dispatch('open-modal', 'audit-detail-{{ $log->id }}')" />
                            </td>
                        </tr>

                    @empty
                        <tr><td colspan="{{ $isSuperAdmin ? 7 : 6 }}" class="px-4 py-8 text-center" style="color: var(--fg-muted);">Nenhum evento encontrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach ($logs as $log)
        @include('audit._details-modal', ['log' => $log])
    @endforeach

    @if ($isSuperAdmin && $purgeSelection)
        <x-ui.modal
            name="audit-purge"
            title="Limpar historico"
            max-width="lg"
            :show="$errors->has('purge') || $errors->hasAny(['scope', 'company_id', 'date_from', 'date_to', 'reason'])"
        >
            <form method="POST" action="{{ route('super-admin.audit-logs.purge') }}" class="grid gap-4">
                @csrf
                @method('DELETE')
                <input type="hidden" name="scope" value="{{ $purgeSelection['scope'] }}">
                @if ($purgeSelection['company_id'])
                    <input type="hidden" name="company_id" value="{{ $purgeSelection['company_id'] }}">
                @endif
                <input type="hidden" name="date_from" value="{{ $purgeSelection['date_from'] }}">
                <input type="hidden" name="date_to" value="{{ $purgeSelection['date_to'] }}">

                <div class="grid gap-3 rounded-lg border p-4 sm:grid-cols-2" style="border-color: var(--border); background: var(--bg-subtle);">
                    <div><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Escopo</p><p class="mt-1 text-sm">{{ $purgeSelection['scope'] === 'global' ? 'Eventos globais' : $companies->firstWhere('id', $purgeSelection['company_id'])?->name }}</p></div>
                    <div><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Periodo</p><p class="mt-1 text-sm">{{ \Carbon\CarbonImmutable::parse($purgeSelection['date_from'])->format('d/m/Y') }} a {{ \Carbon\CarbonImmutable::parse($purgeSelection['date_to'])->format('d/m/Y') }}</p></div>
                    <div class="sm:col-span-2"><p class="text-xs font-semibold uppercase" style="color: var(--fg-muted);">Quantidade estimada</p><p class="mt-1 text-xl font-bold">{{ $purgeEstimate }} registros</p></div>
                </div>

                <p class="text-sm" style="color: var(--fg-muted);">A limpeza afeta somente o escopo e periodo exibidos. Nao existe exclusao de todas as empresas de uma vez.</p>

                <label class="grid gap-1 text-sm">
                    <span>Motivo <span style="color: var(--status-danger);">*</span></span>
                    <textarea name="reason" rows="4" maxlength="1000" required class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">{{ old('reason') }}</textarea>
                    @error('reason')<span class="text-xs" style="color: var(--status-danger);">{{ $message }}</span>@enderror
                    @error('purge')<span class="text-xs" style="color: var(--status-danger);">{{ $message }}</span>@enderror
                </label>

                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" x-data x-on:click="$dispatch('close-modal', 'audit-purge')">Cancelar</x-ui.button>
                    <x-ui.button type="submit" variant="danger" :disabled="$purgeEstimate === 0">Remover registros</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    <div class="mt-5">{{ $logs->links() }}</div>
</x-app-layout>
