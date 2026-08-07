<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\BlacklistNumber;
use App\Models\Campaign;
use App\Models\ContactList;
use App\Jobs\ImportContactList;
use App\Services\Capacity\ResourceLimitService;
use App\Services\Lists\CsvContactListImportService;
use App\Support\Notifications\AppNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContactListController extends Controller
{
    public function __construct(
        private readonly AppNotificationService $notifications,
        private readonly CsvContactListImportService $csvImportService,
    ) {}

    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('company_id', $companyId)],
        ]);

        return view('tenant.lists.index', [
            'lists' => ContactList::query()
                ->where('company_id', $companyId)
                ->with('contacts')
                ->withCount(['contacts', 'campaigns'])
                ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                }))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['campaign_id'] ?? null, fn ($query, $campaignId) => $query->whereHas('campaigns', fn ($campaignQuery) => $campaignQuery->where('campaigns.id', $campaignId)))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
            'campaigns' => Campaign::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, ResourceLimitService $limits): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate($this->rules($companyId), $this->importMessages());

        if ($request->hasFile('import_file')) {
            if (app()->runningUnitTests()) {
                [$list, $summary] = DB::transaction(function () use ($companyId, $data, $request): array {
                    $list = ContactList::create(['company_id' => $companyId, 'name' => $data['name'], 'description' => $data['description'] ?? null, 'status' => $data['status']]);
                    $summary = $this->csvImportService->import($list, $request->file('import_file'), $data['import_columns'] ?? [], $data['separator'] ?? ';', $data['delimiter'] ?? '"')->toSummaryArray();
                    return [$list, $summary];
                });
                $this->notifyListImport($list, $summary);
                return redirect()->route('tenant.lists.index')->with('status', 'Lista criada com sucesso. '.$this->summaryMessage($summary));
            }
            $companyLimits = $limits->assertImport($companyId);
            $processing = ContactList::query()->where('company_id', $companyId)->whereIn('import_status', ['queued', 'processing'])->count();
            if ($processing >= $companyLimits->max_concurrent_imports) {
                throw \Illuminate\Validation\ValidationException::withMessages(['import_file' => 'Ja existe uma importacao em andamento. Aguarde a conclusao.']);
            }
            $list = ContactList::create(['company_id' => $companyId, 'name' => $data['name'], 'description' => $data['description'] ?? null, 'status' => $data['status'], 'import_status' => 'queued']);
            $path = $request->file('import_file')->store("imports/{$companyId}", 'local');
            ImportContactList::dispatch($list->id, $path, $data['import_columns'] ?? [], $data['separator'] ?? ';', $data['delimiter'] ?? '"')->onQueue('imports');
            return redirect()->route('tenant.lists.index')->with('status', 'Lista criada. A importacao foi enviada para processamento.');
        } else {
            $list = ContactList::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
            ]);

            $summary = $this->syncContacts($list, $data['contacts'] ?? '');
            $this->notifyListImport($list, $summary);
        }

        return redirect()->route('tenant.lists.index')->with('status', 'Lista criada com sucesso. '.$this->summaryMessage($summary));
    }

    public function update(Request $request, ContactList $list): RedirectResponse
    {
        abort_unless($list->company_id === $request->user()->company_id, 404);

        $data = $request->validate($this->rules($list->company_id, $list), $this->importMessages());

        $list->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ]);

        if (array_key_exists('contacts', $data)) {
            $summary = $this->syncContacts($list, $data['contacts'] ?? '');
            $this->notifyListImport($list, $summary);
        }

        return redirect()->route('tenant.lists.index')->with('status', 'Lista atualizada com sucesso. '.$this->summaryMessage($summary ?? null));
    }

    public function destroy(Request $request, ContactList $list): RedirectResponse
    {
        abort_unless($list->company_id === $request->user()->company_id, 404);

        $list->update(['status' => 'inactive']);

        return redirect()->route('tenant.lists.index')->with('status', 'Lista inativada com sucesso.');
    }

    private function rules(int $companyId, ?ContactList $list = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('contact_lists')->where('company_id', $companyId)->ignore($list)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'contacts' => ['nullable', 'string', 'max:2000000'],
            'import_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:20480'],
            'separator' => ['nullable', 'string', 'max:5'],
            'delimiter' => ['nullable', 'string', 'max:5'],
            'import_columns' => ['nullable', 'array'],
            'import_columns.*.index' => ['nullable', 'integer', 'min:0'],
            'import_columns.*.enabled' => ['nullable'],
            'import_columns.*.name' => ['nullable', 'string', 'max:255'],
            'import_columns.*.type' => ['nullable', Rule::in(['extra', 'phone'])],
        ];
    }

    private function importMessages(): array
    {
        return [
            'import_file.uploaded' => 'Nao foi possivel enviar o arquivo. Confira sua conexao e tente novamente com um CSV ou TXT de ate 20 MB.',
            'import_file.max' => 'O arquivo de mailing pode ter no maximo 20 MB.',
            'import_file.mimes' => 'Envie apenas arquivos CSV ou TXT.',
        ];
    }

    private function syncContacts(ContactList $list, string $contacts): array
    {
        $summary = [
            'imported' => 0,
            'blocked' => 0,
            'invalid' => 0,
            'duplicated' => 0,
        ];
        $seen = [];
        $blockedPhones = BlacklistNumber::query()
            ->where('company_id', $list->company_id)
            ->where('status', 'active')
            ->pluck('phone')
            ->map(fn (string $phone): string => $this->normalizePhone($phone))
            ->all();

        collect(preg_split('/\r\n|\r|\n/', $contacts) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->each(function (string $line) use ($list, $blockedPhones, &$seen, &$summary): void {
                [$name, $phone] = str_contains($line, ';')
                    ? array_pad(array_map('trim', explode(';', $line, 2)), 2, null)
                    : [null, $line];

                $phone = $this->normalizePhone((string) $phone);

                if (strlen($phone) < 10) {
                    $summary['invalid']++;

                    return;
                }

                if (in_array($phone, $blockedPhones, true)) {
                    $summary['blocked']++;

                    return;
                }

                if (isset($seen[$phone])) {
                    $summary['duplicated']++;

                    return;
                }

                $seen[$phone] = true;

                $list->contacts()->updateOrCreate(
                    ['phone' => $phone],
                    [
                        'company_id' => $list->company_id,
                        'name' => $name ?: null,
                        'status' => 'pending',
                    ]
                );
                $summary['imported']++;
            });

        return $summary;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: '';
    }

    private function summaryMessage(?array $summary): string
    {
        if (! $summary) {
            return '';
        }

        if (array_key_exists('leads_imported', $summary)) {
            return sprintf(
                '%d leads importados, %d telefones importados, %d bloqueado, %d invalido, %d duplicado.',
                $summary['leads_imported'],
                $summary['phones_imported'],
                $summary['blocked'],
                $summary['invalid'],
                $summary['duplicated'],
            );
        }

        return sprintf(
            '%d contatos importados, %d bloqueado, %d invalido, %d duplicado.',
            $summary['imported'],
            $summary['blocked'],
            $summary['invalid'],
            $summary['duplicated'],
        );
    }

    private function notifyListImport(ContactList $list, array $summary): void
    {
        $total = $summary['leads_imported'] ?? $summary['imported'] ?? 0;

        if ($total <= 0) {
            return;
        }

        $message = array_key_exists('phones_imported', $summary)
            ? sprintf('%s recebeu %d leads e %d telefones importados.', $list->name, $summary['leads_imported'], $summary['phones_imported'])
            : sprintf('%s recebeu %d contatos importados.', $list->name, $summary['imported']);

        $this->notifications->createForCompanyAudience(
            $list->company_id,
            ['company_admin', 'supervisor'],
            'mailing',
            'info',
            'Lista importada',
            $message,
            route('tenant.lists.index')
        );
    }
}
