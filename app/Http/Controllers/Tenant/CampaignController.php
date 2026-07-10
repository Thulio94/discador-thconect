<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CompanyLicense;
use App\Models\ContactList;
use App\Models\Team;
use App\Services\Dialer\MailingQueueService;
use App\Services\Dialer\SimulatedDialerService;
use App\Support\Notifications\AppNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function __construct(private readonly AppNotificationService $notifications) {}

    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'paused', 'finished'])],
            'dialing_mode' => ['nullable', Rule::in(['preview', 'manual', 'power', 'predictive'])],
            'team_id' => ['nullable', Rule::exists('teams', 'id')->where('company_id', $companyId)],
            'list_id' => ['nullable', Rule::exists('contact_lists', 'id')->where('company_id', $companyId)],
        ]);

        $campaigns = Campaign::query()
            ->where('company_id', $companyId)
            ->with(['teams', 'contactLists'])
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['dialing_mode'] ?? null, fn ($query, $mode) => $query->where('dialing_mode', $mode))
            ->when($filters['team_id'] ?? null, fn ($query, $teamId) => $query->whereHas('teams', fn ($teamQuery) => $teamQuery->where('teams.id', $teamId)))
            ->when($filters['list_id'] ?? null, fn ($query, $listId) => $query->whereHas('contactLists', fn ($listQuery) => $listQuery->where('contact_lists.id', $listId)))
            ->latest()
            ->paginate(12)
            ->withQueryString();
        $queue = app(MailingQueueService::class);
        $dialer = app(SimulatedDialerService::class);

        return view('tenant.campaigns.index', [
            'campaigns' => $campaigns,
            'mailingSummaries' => $campaigns->getCollection()
                ->mapWithKeys(fn (Campaign $campaign): array => [$campaign->id => $queue->summaryForCampaign($campaign)])
                ->all(),
            'scheduleStates' => $campaigns->getCollection()
                ->mapWithKeys(fn (Campaign $campaign): array => [$campaign->id => $dialer->isCampaignInOperationalSchedule($campaign)])
                ->all(),
            'teams' => Team::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(),
            'lists' => ContactList::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate($this->rules($companyId));
        $this->validateListSelection($request, $companyId, $data['list_ids'] ?? []);

        $campaign = Campaign::create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'service_script' => $data['service_script'] ?? null,
            'service_fields_schema' => $this->serviceFieldsSchemaPayload($data['service_fields_schema'] ?? []),
            'dialing_mode' => $data['dialing_mode'],
            'lead_phone_strategy' => $data['lead_phone_strategy'] ?? 'vertical',
            ...$this->advancedDialingPayload($data),
            'status' => $data['status'],
            'priority' => $data['priority'] ?? 1,
            'operational_schedule' => $this->schedulePayload($data['operational_schedule'] ?? []),
        ]);

        $this->syncRelations($campaign, $data['team_ids'] ?? [], $data['list_ids'] ?? []);

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha criada com sucesso.');
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $data = $request->validate($this->rules($campaign->company_id, $campaign));
        $this->validateListSelection($request, $campaign->company_id, $data['list_ids'] ?? []);

        $campaign->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'service_script' => $data['service_script'] ?? null,
            'service_fields_schema' => $this->serviceFieldsSchemaPayload($data['service_fields_schema'] ?? []),
            'dialing_mode' => $data['dialing_mode'],
            'lead_phone_strategy' => $data['lead_phone_strategy'] ?? 'vertical',
            ...$this->advancedDialingPayload($data),
            'status' => $data['status'],
            'priority' => $data['priority'] ?? 1,
            'operational_schedule' => $this->schedulePayload($data['operational_schedule'] ?? []),
        ]);

        $this->syncRelations($campaign, $data['team_ids'] ?? [], $data['list_ids'] ?? []);

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha atualizada com sucesso.');
    }

    public function destroy(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $campaign->update([
            'status' => 'finished',
            'ends_at' => now(),
        ]);
        $this->notifyCampaign($campaign, 'warning', 'Campanha finalizada', "A campanha {$campaign->name} foi finalizada.");

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha finalizada com sucesso.');
    }

    public function start(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $campaign->update([
            'status' => 'active',
            'starts_at' => $campaign->starts_at ?? now(),
            'ends_at' => null,
        ]);
        $this->notifyCampaign($campaign, 'success', 'Campanha iniciada', "A campanha {$campaign->name} foi iniciada.");

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha iniciada com sucesso.');
    }

    public function pause(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $campaign->update(['status' => 'paused']);
        $this->notifyCampaign($campaign, 'warning', 'Campanha pausada', "A campanha {$campaign->name} foi pausada.");

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha pausada com sucesso.');
    }

    public function restart(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $campaign->update([
            'status' => 'active',
            'starts_at' => $campaign->starts_at ?? now(),
            'ends_at' => null,
        ]);
        $this->notifyCampaign($campaign, 'success', 'Campanha reiniciada', "A campanha {$campaign->name} foi reiniciada.");

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha reiniciada com sucesso.');
    }

    public function duplicate(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $copy = Campaign::create([
            'company_id' => $campaign->company_id,
            'name' => $this->copyName($campaign),
            'description' => $campaign->description,
            'service_script' => $campaign->service_script,
            'service_fields_schema' => $campaign->service_fields_schema,
            'dialing_mode' => $campaign->dialing_mode,
            'lead_phone_strategy' => $campaign->lead_phone_strategy ?? 'vertical',
            'max_attempts_per_contact' => $campaign->max_attempts_per_contact,
            'retry_delay_minutes' => $campaign->retry_delay_minutes,
            'max_concurrent_calls' => $campaign->max_concurrent_calls,
            'amd_mode' => $campaign->amd_mode ?? 'disabled',
            'dialing_route' => $campaign->dialing_route,
            'status' => 'draft',
            'priority' => $campaign->priority,
            'operational_schedule' => $campaign->operational_schedule,
        ]);

        $this->syncRelations(
            $copy,
            $campaign->teams()->pluck('teams.id')->all(),
            $campaign->contactLists()->get()->mapWithKeys(fn (ContactList $list): array => [
                $list->id => ['selected' => '1', 'weight' => (int) ($list->pivot->weight ?? 1)],
            ])->all()
        );

        return redirect()->route('tenant.campaigns.index')->with('status', 'Campanha duplicada com sucesso.');
    }

    public function recycle(Request $request, Campaign $campaign, MailingQueueService $queue): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        $count = $queue->recycleCampaign($campaign);

        return redirect()
            ->route('tenant.campaigns.index')
            ->with('status', sprintf('%d contato reciclado para a fila da campanha.', $count));
    }

    public function dial(Request $request, Campaign $campaign, SimulatedDialerService $dialer): RedirectResponse
    {
        abort_unless($campaign->company_id === $request->user()->company_id, 403);

        if (! $dialer->isCampaignInOperationalSchedule($campaign)) {
            return redirect()
                ->route('tenant.campaigns.index')
                ->with('status', 'Campanha fora da agenda operacional. Nenhuma chamada simulada foi criada.');
        }

        $count = $dialer->runCampaign($campaign);

        return redirect()
            ->route('tenant.campaigns.index')
            ->with('status', sprintf('%d chamada simulada criada para agentes disponiveis.', $count));
    }

    private function rules(int $companyId, ?Campaign $campaign = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('campaigns')->where('company_id', $companyId)->ignore($campaign)],
            'description' => ['nullable', 'string', 'max:2000'],
            'service_script' => ['nullable', 'string', 'max:5000'],
            'dialing_mode' => ['required', Rule::in(['preview', 'manual', 'power', 'predictive'])],
            'lead_phone_strategy' => ['nullable', Rule::in(['vertical', 'horizontal'])],
            'max_attempts_per_contact' => ['nullable', 'integer', 'min:1', 'max:50'],
            'retry_delay_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'max_concurrent_calls' => [
                'nullable',
                'integer',
                'min:1',
                'max:100000',
                function (string $attribute, mixed $value, \Closure $fail) use ($companyId): void {
                    $licenseLimit = CompanyLicense::query()
                        ->where('company_id', $companyId)
                        ->where('status', 'active')
                        ->where('starts_at', '<=', today())
                        ->where('ends_at', '>=', today())
                        ->latest('starts_at')
                        ->value('max_concurrent_calls');

                    if ($licenseLimit !== null && (int) $value > (int) $licenseLimit) {
                        $fail('O limite da campanha nao pode ser maior que o limite contratado da empresa.');
                    }
                },
            ],
            'amd_mode' => ['nullable', Rule::in(['disabled', 'simulated'])],
            'dialing_route' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'finished'])],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => [Rule::exists('teams', 'id')->where('company_id', $companyId)],
            'list_ids' => ['required', 'array', 'min:1'],
            'list_ids.*' => [
                function (string $attribute, mixed $value, \Closure $fail) use ($companyId): void {
                    if (is_array($value)) {
                        return;
                    }

                    $exists = ContactList::query()
                        ->where('company_id', $companyId)
                        ->where('id', (int) $value)
                        ->exists();

                    if (! $exists) {
                        $fail('Selecione apenas listas desta empresa.');
                    }
                },
            ],
            'operational_schedule' => ['nullable', 'array'],
            'operational_schedule.*.enabled' => ['nullable'],
            'operational_schedule.*.start' => ['nullable', 'date_format:H:i'],
            'operational_schedule.*.end' => ['nullable', 'date_format:H:i'],
            'service_fields_schema' => ['nullable', 'array'],
            'service_fields_schema.*.enabled' => ['nullable'],
            'service_fields_schema.*.key' => ['nullable', 'string', 'max:60'],
            'service_fields_schema.*.label' => ['nullable', 'string', 'max:80'],
            'service_fields_schema.*.type' => [
                'nullable',
                Rule::in(['text', 'number', 'date', 'cpf', 'cnpj', 'phone', 'email', 'currency']),
            ],
            'service_fields_schema.*.required' => ['nullable'],
        ];
    }

    private function syncRelations(Campaign $campaign, array $teamIds, array $listIds): void
    {
        $campaign->teams()->sync(
            collect($teamIds)->mapWithKeys(fn ($id): array => [(int) $id => ['company_id' => $campaign->company_id]])->all()
        );

        $campaign->contactLists()->sync($this->listSyncPayload($campaign, $listIds));
    }

    private function validateListSelection(Request $request, int $companyId, array $listIds): void
    {
        $ids = collect($listIds)
            ->map(fn ($value, $key): int => is_array($value) ? (int) $key : (int) $value)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $validCount = ContactList::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->count();

        if ($validCount !== $ids->unique()->count()) {
            back()->withErrors(['list_ids.0' => 'Selecione apenas listas desta empresa.'])->throwResponse();
        }
    }

    private function listSyncPayload(Campaign $campaign, array $listIds): array
    {
        return collect($listIds)
            ->filter(fn ($value): bool => ! is_array($value) || filled($value['selected'] ?? null))
            ->mapWithKeys(function ($value, $key) use ($campaign): array {
                if (is_array($value)) {
                    return [(int) $key => [
                        'company_id' => $campaign->company_id,
                        'weight' => max(1, (int) ($value['weight'] ?? 1)),
                    ]];
                }

                return [(int) $value => [
                    'company_id' => $campaign->company_id,
                    'weight' => 1,
                ]];
            })
            ->all();
    }

    private function schedulePayload(array $schedule): ?array
    {
        $payload = collect($schedule)
            ->filter(fn (array $day): bool => filled($day['enabled'] ?? null))
            ->map(fn (array $day): array => [
                'enabled' => true,
                'start' => $day['start'] ?? '09:00',
                'end' => $day['end'] ?? '18:00',
            ])
            ->filter(fn (array $day): bool => $day['start'] < $day['end'])
            ->all();

        return $payload ?: null;
    }

    private function advancedDialingPayload(array $data): array
    {
        return [
            'max_attempts_per_contact' => $data['max_attempts_per_contact'] ?? null,
            'retry_delay_minutes' => $data['retry_delay_minutes'] ?? null,
            'max_concurrent_calls' => $data['max_concurrent_calls'] ?? null,
            'amd_mode' => $data['amd_mode'] ?? 'disabled',
            'dialing_route' => filled($data['dialing_route'] ?? null) ? trim($data['dialing_route']) : null,
        ];
    }

    private function serviceFieldsSchemaPayload(array $fields): ?array
    {
        $usedKeys = [];
        $allowedTypes = ['text', 'number', 'date', 'cpf', 'cnpj', 'phone', 'email', 'currency'];
        $payload = collect($fields)
            ->filter(fn (array $field): bool => filled($field['enabled'] ?? null))
            ->map(function (array $field) use (&$usedKeys, $allowedTypes): ?array {
                $key = $this->fieldKey((string) ($field['key'] ?? ''));

                if ($key === '' || in_array($key, $usedKeys, true) || blank($field['label'] ?? null)) {
                    return null;
                }

                $usedKeys[] = $key;

                return [
                    'key' => $key,
                    'label' => trim((string) $field['label']),
                    'type' => in_array($field['type'] ?? 'text', $allowedTypes, true) ? $field['type'] : 'text',
                    'required' => filled($field['required'] ?? null),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $payload ?: null;
    }

    private function fieldKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?: '';

        return trim($key, '_');
    }

    private function copyName(Campaign $campaign): string
    {
        $baseName = $campaign->name.' - copia';
        $name = $baseName;
        $counter = 2;

        while (Campaign::query()->where('company_id', $campaign->company_id)->where('name', $name)->exists()) {
            $name = $baseName.' '.$counter;
            $counter++;
        }

        return $name;
    }

    private function notifyCampaign(Campaign $campaign, string $severity, string $title, string $message): void
    {
        $this->notifications->createForCompanyAudience(
            $campaign->company_id,
            ['company_admin', 'supervisor'],
            'campaign',
            $severity,
            $title,
            $message,
            route('tenant.campaigns.index')
        );
    }
}
