# CSV List Import With Lead Phones Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace manual contact-list textarea creation with CSV/TXT upload and dynamic column selection, storing multiple phones per lead and letting campaigns decide how alternate phones are attempted without re-calling a contacted lead.

**Architecture:** Keep `contacts` as the lead record and add `contact_phones` for phone attempts. A backend CSV import service parses the uploaded file using user-selected column metadata, writes one lead with many phones, and keeps selected non-phone columns in `contacts.extra`. Campaigns get a lead-phone strategy setting, and `MailingQueueService` chooses the next pending phone according to that strategy while skipping leads already connected to an operator in the campaign.

**Tech Stack:** PHP 8.3, Laravel 12, Blade, Alpine.js, PHPUnit feature/unit tests, Node test runner for CSV preview helpers, Docker Compose.

---

## File Map

- Create `database/migrations/2026_06_18_000001_create_contact_phones_table.php`: phone records per lead.
- Create `database/migrations/2026_06_18_000002_add_contact_phone_id_to_calls_table.php`: link outbound calls to the selected phone.
- Create `database/migrations/2026_06_18_000003_add_lead_phone_strategy_to_campaigns_table.php`: campaign strategy setting.
- Create `app/Models/ContactPhone.php`: model for alternate phones.
- Modify `app/Models/Contact.php`: `phones()` relation.
- Modify `app/Models/Call.php`: fillable/cast/relation for `contact_phone_id`.
- Modify `app/Models/Campaign.php`: fillable/cast for `lead_phone_strategy`.
- Create `app/Services/Lists/CsvContactListImportService.php`: server-side CSV/TXT parser and importer.
- Create `app/Services/Lists/CsvContactListImportResult.php`: immutable summary object.
- Modify `app/Http/Controllers/Tenant/ContactListController.php`: accept file import and mapping payload while keeping legacy textarea compatibility.
- Modify `resources/views/tenant/lists/_form.blade.php`: modal UI for upload, preview and mapping.
- Create `resources/js/list-import-preview.js`: browser CSV parser, preview state and Alpine component.
- Create `tests/Frontend/list-import-preview.test.js`: frontend parser/mapping tests.
- Modify `resources/js/app.js`: expose the list import component.
- Modify `app/Http/Controllers/Tenant/CampaignController.php`: validate and persist phone strategy.
- Modify `resources/views/tenant/campaigns/_form.blade.php`: campaign strategy select.
- Modify `app/Services/Dialer/MailingQueueService.php`: choose next `ContactPhone` by strategy and skip contacted leads.
- Modify `app/Services/Dialer/SimulatedDialerService.php`: create calls with `contact_phone_id` and call phone.
- Modify `app/Http/Controllers/Tenant/AgentPanelController.php`: keep contacted leads blocked by call history and update selected phone metadata on tabulation.
- Modify `tests/Feature/TenantCampaignListCrudTest.php`: list import and campaign strategy coverage.
- Modify `tests/Feature/TenantAgentPanelTest.php`: dialer strategy and anti-rechamada coverage.
- Modify `docs/ROADMAP_CONCLUSAO.md`: record implemented capability.

### Task 1: Lead Phone Storage Foundation

**Files:**
- Create: `database/migrations/2026_06_18_000001_create_contact_phones_table.php`
- Create: `database/migrations/2026_06_18_000002_add_contact_phone_id_to_calls_table.php`
- Create: `database/migrations/2026_06_18_000003_add_lead_phone_strategy_to_campaigns_table.php`
- Create: `app/Models/ContactPhone.php`
- Modify: `app/Models/Contact.php`
- Modify: `app/Models/Call.php`
- Modify: `app/Models/Campaign.php`
- Test: `tests/Feature/TenantCampaignListCrudTest.php`

- [ ] **Step 1: Write the failing storage test**

Add to `tests/Feature/TenantCampaignListCrudTest.php`:

```php
public function test_contact_can_store_multiple_ordered_phones_for_a_lead(): void
{
    [$admin] = $this->tenantAdmin();

    $list = ContactList::create([
        'company_id' => $admin->company_id,
        'name' => 'Lista Telefones',
        'status' => 'active',
    ]);
    $contact = $list->contacts()->create([
        'company_id' => $admin->company_id,
        'name' => 'Maria',
        'phone' => '31999990001',
        'status' => 'pending',
    ]);

    $contact->phones()->createMany([
        [
            'company_id' => $admin->company_id,
            'phone' => '31999990001',
            'column_name' => 'Telefone principal',
            'position' => 0,
            'is_primary' => true,
            'status' => 'pending',
        ],
        [
            'company_id' => $admin->company_id,
            'phone' => '31988880002',
            'column_name' => 'Telefone extra',
            'position' => 1,
            'is_primary' => false,
            'status' => 'pending',
        ],
    ]);

    $this->assertSame([
        '31999990001',
        '31988880002',
    ], $contact->fresh()->phones()->orderBy('position')->pluck('phone')->all());
    $this->assertTrue($contact->fresh()->phones()->where('phone', '31999990001')->sole()->is_primary);
    $this->assertFalse($contact->fresh()->phones()->where('phone', '31988880002')->sole()->is_primary);
}
```

- [ ] **Step 2: Run the storage test and confirm RED**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantCampaignListCrudTest.php --filter=multiple_ordered_phones --no-coverage
```

Expected: FAIL because `phones()` and `contact_phones` do not exist.

- [ ] **Step 3: Add migrations**

Create `database/migrations/2026_06_18_000001_create_contact_phones_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_phones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('phone');
            $table->string('column_name')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();

            $table->unique(['contact_id', 'phone']);
            $table->index(['company_id', 'status', 'next_attempt_at']);
            $table->index(['contact_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_phones');
    }
};
```

Create `database/migrations/2026_06_18_000002_add_contact_phone_id_to_calls_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->foreignId('contact_phone_id')
                ->nullable()
                ->after('contact_id')
                ->constrained('contact_phones')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contact_phone_id');
        });
    }
};
```

Create `database/migrations/2026_06_18_000003_add_lead_phone_strategy_to_campaigns_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('lead_phone_strategy')->default('vertical')->after('dialing_mode');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn('lead_phone_strategy');
        });
    }
};
```

- [ ] **Step 4: Add model and relations**

Create `app/Models/ContactPhone.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'contact_id', 'phone', 'column_name', 'position', 'is_primary', 'status', 'attempts_count', 'last_attempt_at', 'next_attempt_at'])]
class ContactPhone extends Model
{
    protected $casts = [
        'is_primary' => 'boolean',
        'position' => 'integer',
        'attempts_count' => 'integer',
        'last_attempt_at' => 'datetime',
        'next_attempt_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
```

Modify `app/Models/Contact.php`:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

```php
public function phones(): HasMany
{
    return $this->hasMany(ContactPhone::class)->orderBy('position');
}
```

Modify `app/Models/Call.php`:

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
```

Add `contact_phone_id` to the `Fillable` attribute and add:

```php
public function contactPhone(): BelongsTo
{
    return $this->belongsTo(ContactPhone::class);
}
```

Modify `app/Models/Campaign.php` by adding `lead_phone_strategy` to `Fillable`.

- [ ] **Step 5: Run storage test and migrations**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantCampaignListCrudTest.php --filter=multiple_ordered_phones --no-coverage
docker compose exec -T app php artisan migrate
```

Expected: focused test PASS and migration applies.

- [ ] **Step 6: Commit storage foundation**

```powershell
git add app/Models database/migrations tests/Feature/TenantCampaignListCrudTest.php
git commit -m "feat: add lead phone storage"
```

### Task 2: Server-Side CSV/TXT Import Service

**Files:**
- Create: `app/Services/Lists/CsvContactListImportResult.php`
- Create: `app/Services/Lists/CsvContactListImportService.php`
- Modify: `app/Http/Controllers/Tenant/ContactListController.php`
- Test: `tests/Feature/TenantCampaignListCrudTest.php`

- [ ] **Step 1: Write failing import tests**

Add to `TenantCampaignListCrudTest`:

```php
use Illuminate\Http\UploadedFile;
```

```php
public function test_tenant_admin_can_import_csv_with_multiple_phones_and_selected_extra_columns(): void
{
    [$admin] = $this->tenantAdmin();

    $file = UploadedFile::fake()->createWithContent(
        'leads.csv',
        "Nome;Telefone 1;Telefone 2;CPF;Ignorar\nMaria;(31) 99999-0001;31 98888-0002;123;fora\nJoao;12345;31977770003;456;fora"
    );

    $this->actingAs($admin)
        ->post(route('tenant.lists.store'), [
            'name' => 'Lista CSV',
            'description' => 'Importada',
            'status' => 'active',
            'import_file' => $file,
            'separator' => ';',
            'delimiter' => '"',
            'import_columns' => [
                ['index' => 0, 'enabled' => '1', 'name' => 'Nome', 'type' => 'extra'],
                ['index' => 1, 'enabled' => '1', 'name' => 'Telefone 1', 'type' => 'phone'],
                ['index' => 2, 'enabled' => '1', 'name' => 'Telefone 2', 'type' => 'phone'],
                ['index' => 3, 'enabled' => '1', 'name' => 'CPF', 'type' => 'extra'],
                ['index' => 4, 'name' => 'Ignorar', 'type' => 'extra'],
            ],
        ])
        ->assertRedirect(route('tenant.lists.index'))
        ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '2 leads importados')
            && str_contains($message, '3 telefones importados')
            && str_contains($message, '1 invalido'));

    $list = ContactList::where('company_id', $admin->company_id)->where('name', 'Lista CSV')->sole();
    $maria = $list->contacts()->where('name', 'Maria')->sole();
    $joao = $list->contacts()->where('name', 'Joao')->sole();

    $this->assertSame('31999990001', $maria->phone);
    $this->assertSame(['Nome' => 'Maria', 'CPF' => '123'], $maria->extra);
    $this->assertSame(['31999990001', '31988880002'], $maria->phones()->pluck('phone')->all());
    $this->assertSame('31977770003', $joao->phone);
    $this->assertSame(['31977770003'], $joao->phones()->pluck('phone')->all());
    $this->assertArrayNotHasKey('Ignorar', $maria->extra);
}

public function test_csv_import_requires_at_least_one_phone_column(): void
{
    [$admin] = $this->tenantAdmin();
    $file = UploadedFile::fake()->createWithContent('leads.txt', "Nome;Telefone\nMaria;31999990001");

    $this->actingAs($admin)
        ->post(route('tenant.lists.store'), [
            'name' => 'Lista Sem Telefone',
            'status' => 'active',
            'import_file' => $file,
            'separator' => ';',
            'delimiter' => '"',
            'import_columns' => [
                ['index' => 0, 'enabled' => '1', 'name' => 'Nome', 'type' => 'extra'],
                ['index' => 1, 'enabled' => '1', 'name' => 'Telefone', 'type' => 'extra'],
            ],
        ])
        ->assertSessionHasErrors('import_columns');
}
```

- [ ] **Step 2: Run import tests and confirm RED**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantCampaignListCrudTest.php --filter=csv_import --no-coverage
```

Expected: FAIL because `import_file` and `import_columns` are not supported.

- [ ] **Step 3: Add immutable import result**

Create `app/Services/Lists/CsvContactListImportResult.php`:

```php
<?php

namespace App\Services\Lists;

final readonly class CsvContactListImportResult
{
    public function __construct(
        public int $leadsImported,
        public int $phonesImported,
        public int $blocked,
        public int $invalid,
        public int $duplicated,
    ) {}

    public function toSummaryArray(): array
    {
        return [
            'leads_imported' => $this->leadsImported,
            'phones_imported' => $this->phonesImported,
            'blocked' => $this->blocked,
            'invalid' => $this->invalid,
            'duplicated' => $this->duplicated,
        ];
    }
}
```

- [ ] **Step 4: Add CSV importer service**

Create `app/Services/Lists/CsvContactListImportService.php` with these public methods:

```php
<?php

namespace App\Services\Lists;

use App\Models\BlacklistNumber;
use App\Models\ContactList;
use Illuminate\Http\UploadedFile;
use SplFileObject;

class CsvContactListImportService
{
    public function import(ContactList $list, UploadedFile $file, array $columns, string $separator = ';', string $delimiter = '"'): CsvContactListImportResult
    {
        $selected = collect($columns)
            ->filter(fn (array $column): bool => filled($column['enabled'] ?? null))
            ->map(fn (array $column): array => [
                'index' => (int) ($column['index'] ?? 0),
                'name' => trim((string) ($column['name'] ?? '')),
                'type' => ($column['type'] ?? 'extra') === 'phone' ? 'phone' : 'extra',
            ])
            ->filter(fn (array $column): bool => $column['name'] !== '')
            ->values();

        if ($selected->where('type', 'phone')->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'import_columns' => 'Selecione pelo menos uma coluna de telefone.',
            ]);
        }

        $blockedPhones = BlacklistNumber::query()
            ->where('company_id', $list->company_id)
            ->where('status', 'active')
            ->pluck('phone')
            ->map(fn (string $phone): string => $this->normalizePhone($phone))
            ->all();

        $summary = ['leads' => 0, 'phones' => 0, 'blocked' => 0, 'invalid' => 0, 'duplicated' => 0];
        $fileObject = new SplFileObject($file->getRealPath());
        $fileObject->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $fileObject->setCsvControl($separator[0] ?? ';', $delimiter[0] ?? '"');
        $fileObject->fgetcsv();

        foreach ($fileObject as $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            $extra = [];
            $phones = [];

            foreach ($selected as $column) {
                $value = trim((string) ($row[$column['index']] ?? ''));

                if ($column['type'] === 'phone') {
                    $phone = $this->normalizePhone($value);

                    if (strlen($phone) < 10) {
                        $summary['invalid']++;
                        continue;
                    }

                    if (in_array($phone, $blockedPhones, true)) {
                        $summary['blocked']++;
                        continue;
                    }

                    if (isset($phones[$phone])) {
                        $summary['duplicated']++;
                        continue;
                    }

                    $phones[$phone] = ['phone' => $phone, 'column_name' => $column['name']];
                    continue;
                }

                if ($value !== '') {
                    $extra[$column['name']] = $value;
                }
            }

            if ($phones === []) {
                continue;
            }

            $firstPhone = array_key_first($phones);
            $contact = $list->contacts()->updateOrCreate(
                ['phone' => $firstPhone],
                [
                    'company_id' => $list->company_id,
                    'name' => $extra['Nome'] ?? $extra['nome'] ?? null,
                    'status' => 'pending',
                    'extra' => $extra ?: null,
                ],
            );

            $contact->phones()->delete();

            foreach (array_values($phones) as $position => $phone) {
                $contact->phones()->create([
                    'company_id' => $list->company_id,
                    'phone' => $phone['phone'],
                    'column_name' => $phone['column_name'],
                    'position' => $position,
                    'is_primary' => $position === 0,
                    'status' => 'pending',
                ]);
                $summary['phones']++;
            }

            $summary['leads']++;
        }

        return new CsvContactListImportResult(
            $summary['leads'],
            $summary['phones'],
            $summary['blocked'],
            $summary['invalid'],
            $summary['duplicated'],
        );
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: '';
    }
}
```

- [ ] **Step 5: Wire controller to file import while keeping legacy textarea**

Inject the service:

```php
public function __construct(
    private readonly AppNotificationService $notifications,
    private readonly \App\Services\Lists\CsvContactListImportService $csvImport,
) {}
```

Add validation rules:

```php
'import_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:20480'],
'separator' => ['nullable', 'string', 'max:4'],
'delimiter' => ['nullable', 'string', 'max:4'],
'import_columns' => ['nullable', 'array'],
'import_columns.*.index' => ['required_with:import_columns', 'integer', 'min:0'],
'import_columns.*.enabled' => ['nullable'],
'import_columns.*.name' => ['nullable', 'string', 'max:120'],
'import_columns.*.type' => ['nullable', Rule::in(['extra', 'phone'])],
```

In `store()`, after creating the list:

```php
$summary = $request->hasFile('import_file')
    ? $this->csvImport->import($list, $request->file('import_file'), $data['import_columns'] ?? [], $data['separator'] ?? ';', $data['delimiter'] ?? '"')->toSummaryArray()
    : $this->syncContacts($list, $data['contacts'] ?? '');
```

In `update()`, keep legacy textarea behavior only; file import for edit is not part of this task.

Update `summaryMessage()` to support both formats:

```php
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
```

- [ ] **Step 6: Run import and legacy tests**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantCampaignListCrudTest.php --filter="csv_import|create_contact_list|sanitizes_deduplicates" --no-coverage
```

Expected: CSV import tests PASS and legacy textarea import tests still PASS.

- [ ] **Step 7: Commit CSV import backend**

```powershell
git add app/Http/Controllers/Tenant/ContactListController.php app/Services/Lists tests/Feature/TenantCampaignListCrudTest.php
git commit -m "feat: import contact lists from csv"
```

### Task 3: CSV/TXT Modal Preview UI

**Files:**
- Create: `resources/js/list-import-preview.js`
- Create: `tests/Frontend/list-import-preview.test.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/tenant/lists/_form.blade.php`
- Modify: `resources/views/tenant/lists/index.blade.php`
- Test: `tests/Feature/TenantCampaignListCrudTest.php`

- [ ] **Step 1: Write frontend parser tests**

Create `tests/Frontend/list-import-preview.test.js`:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import {
    parseDelimitedText,
    buildColumnConfig,
    hasPhoneColumn,
} from '../../resources/js/list-import-preview.js';

test('parses semicolon csv with quoted delimiters', () => {
    const parsed = parseDelimitedText('Nome;Telefone;Obs\\n\"Maria; Silva\";31999990001;VIP', ';', '\"');

    assert.deepEqual(parsed.headers, ['Nome', 'Telefone', 'Obs']);
    assert.deepEqual(parsed.previewRows[0], ['Maria; Silva', '31999990001', 'VIP']);
});

test('drops columns without headers from config', () => {
    const columns = buildColumnConfig(['Nome', '', 'Telefone']);

    assert.deepEqual(columns.map((column) => column.index), [0, 2]);
    assert.equal(columns[0].enabled, true);
    assert.equal(columns[0].type, 'extra');
});

test('requires at least one phone column in active config', () => {
    assert.equal(hasPhoneColumn([{ enabled: true, type: 'extra' }]), false);
    assert.equal(hasPhoneColumn([{ enabled: true, type: 'phone' }]), true);
});
```

- [ ] **Step 2: Run frontend tests and confirm RED**

Run:

```powershell
npm run test:frontend
```

Expected: FAIL because `list-import-preview.js` does not exist.

- [ ] **Step 3: Implement frontend parser and Alpine component**

Create `resources/js/list-import-preview.js`:

```js
export function parseDelimitedText(text, separator = ';', delimiter = '"') {
    const rows = [];
    let cell = '';
    let row = [];
    let quoted = false;

    for (let index = 0; index < text.length; index += 1) {
        const char = text[index];
        const next = text[index + 1];

        if (char === delimiter && quoted && next === delimiter) {
            cell += delimiter;
            index += 1;
            continue;
        }

        if (char === delimiter) {
            quoted = !quoted;
            continue;
        }

        if (char === separator && !quoted) {
            row.push(cell.trim());
            cell = '';
            continue;
        }

        if ((char === '\n' || char === '\r') && !quoted) {
            if (char === '\r' && next === '\n') {
                index += 1;
            }
            row.push(cell.trim());
            rows.push(row);
            row = [];
            cell = '';
            continue;
        }

        cell += char;
    }

    row.push(cell.trim());
    rows.push(row);

    const headers = (rows.shift() ?? []).map((header) => header.trim());

    return {
        headers,
        previewRows: rows.filter((item) => item.some((cellValue) => cellValue !== '')).slice(0, 2),
    };
}

export function buildColumnConfig(headers) {
    return headers
        .map((name, index) => ({ index, name: name.trim(), enabled: name.trim() !== '', type: 'extra' }))
        .filter((column) => column.name !== '');
}

export function hasPhoneColumn(columns) {
    return columns.some((column) => column.enabled && column.type === 'phone');
}

export function listImportPreview() {
    return {
        fileName: '',
        separator: ';',
        delimiter: '"',
        columns: [],
        previewRows: [],
        error: '',
        async loadFile(file) {
            this.error = '';
            this.fileName = file?.name ?? '';

            if (!file) {
                return;
            }

            if (!/\.(csv|txt)$/i.test(file.name)) {
                this.error = 'Envie um arquivo CSV ou TXT.';
                return;
            }

            const parsed = parseDelimitedText(await file.text(), this.separator || ';', this.delimiter || '"');
            this.columns = buildColumnConfig(parsed.headers);
            this.previewRows = parsed.previewRows;

            if (this.columns.length === 0) {
                this.error = 'O arquivo precisa ter cabecalho.';
            }
        },
        choose(event) {
            this.loadFile(event.target.files?.[0]);
        },
        drop(event) {
            this.loadFile(event.dataTransfer.files?.[0]);
        },
        canSubmit() {
            return hasPhoneColumn(this.columns);
        },
    };
}
```

- [ ] **Step 4: Expose component**

Modify `resources/js/app.js`:

```js
import { listImportPreview } from './list-import-preview';

window.listImportPreview = listImportPreview;
```

Keep existing imports and assignments.

- [ ] **Step 5: Replace create-list textarea with upload/preview UI**

In `resources/views/tenant/lists/_form.blade.php`, keep the current edit textarea only when `$list` exists. For create mode render:

```blade
@if ($list)
    {{-- existing textarea stays for editing existing lists --}}
@else
    <div x-data="listImportPreview()" class="grid gap-4">
        <label class="grid gap-1 text-sm">
            <span class="font-semibold">Lista de contatos</span>
            <input type="file" name="import_file" accept=".csv,.txt,text/csv,text/plain" class="sr-only" x-ref="file" x-on:change="choose($event)">
            <button type="button" class="rounded-lg border border-dashed px-4 py-10 text-center text-sm" style="border-color: var(--border); color: var(--fg-muted);" x-on:click="$refs.file.click()" x-on:dragover.prevent x-on:drop.prevent="drop($event)">
                Arraste e solte ou clique para selecionar o arquivo
            </button>
            <span class="text-xs" style="color: var(--fg-muted);" x-text="fileName || 'Envie um arquivo CSV/TXT.'"></span>
            <span class="text-xs" style="color: var(--status-danger);" x-show="error" x-text="error"></span>
        </label>

        <div class="grid gap-3 md:grid-cols-2">
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Separador</span>
                <input name="separator" x-model="separator" maxlength="4" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            </label>
            <label class="grid gap-1 text-sm">
                <span class="font-semibold">Delimitador</span>
                <input name="delimiter" x-model="delimiter" maxlength="4" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
            </label>
        </div>

        <div class="rounded-lg border p-3 text-sm" style="border-color: var(--border); background: var(--surface-alt); color: var(--fg-muted);">
            Para que a lista seja valida, marque pelo menos uma coluna como Telefone. Colunas sem cabecalho serao descartadas.
        </div>

        <div x-show="columns.length > 0" class="overflow-x-auto rounded-lg border" style="border-color: var(--border);">
            <table class="min-w-full text-sm">
                <thead style="background: var(--surface-alt); color: var(--fg-muted);">
                    <tr>
                        <th class="px-3 py-2 text-left">Coluna</th>
                        <th class="px-3 py-2 text-left">Tipo da coluna</th>
                        <th class="px-3 py-2 text-left">Nome da coluna</th>
                        <th class="px-3 py-2 text-left">Linha 1</th>
                        <th class="px-3 py-2 text-left">Linha 2</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(column, index) in columns" :key="column.index">
                        <tr class="border-t" style="border-color: var(--border);">
                            <td class="px-3 py-2">
                                <input type="hidden" x-bind:name="`import_columns[${index}][index]`" x-bind:value="column.index">
                                <input type="hidden" x-bind:name="`import_columns[${index}][name]`" x-bind:value="column.name">
                                <input type="checkbox" x-bind:name="`import_columns[${index}][enabled]`" value="1" x-model="column.enabled">
                            </td>
                            <td class="px-3 py-2">
                                <select x-bind:name="`import_columns[${index}][type]`" x-model="column.type" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
                                    <option value="extra">Dado extra</option>
                                    <option value="phone">Telefone</option>
                                </select>
                            </td>
                            <td class="px-3 py-2 font-semibold" x-text="column.name"></td>
                            <td class="px-3 py-2" x-text="previewRows[0]?.[column.index] || ''"></td>
                            <td class="px-3 py-2" x-text="previewRows[1]?.[column.index] || ''"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <p x-show="columns.length > 0 && !canSubmit()" class="text-xs" style="color: var(--status-danger);">
            Marque pelo menos uma coluna como Telefone.
        </p>
    </div>
@endif
```

Set the create form in `resources/views/tenant/lists/index.blade.php` to `enctype="multipart/form-data"`.

- [ ] **Step 6: Add feature rendering assertion**

In `test_tenant_admin_can_view_contact_lists_index`, assert:

```php
->assertSee('Arraste e solte ou clique para selecionar o arquivo')
->assertSee('Separador')
->assertSee('Delimitador')
->assertSee('Marque pelo menos uma coluna como Telefone');
```

- [ ] **Step 7: Run frontend, feature and build checks**

Run:

```powershell
npm run test:frontend
docker compose exec -T app php artisan test tests/Feature/TenantCampaignListCrudTest.php --filter=view_contact_lists_index --no-coverage
npm run build
```

Expected: frontend tests PASS, rendering test PASS, build PASS.

- [ ] **Step 8: Commit import UI**

```powershell
git add resources/js resources/views/tenant/lists tests/Frontend tests/Feature/TenantCampaignListCrudTest.php
git commit -m "feat: add csv list import preview"
```

### Task 4: Campaign Phone Strategy Setting

**Files:**
- Modify: `app/Http/Controllers/Tenant/CampaignController.php`
- Modify: `resources/views/tenant/campaigns/_form.blade.php`
- Test: `tests/Feature/TenantCampaignListCrudTest.php`

- [ ] **Step 1: Write failing campaign strategy test**

Add:

```php
public function test_tenant_admin_can_configure_campaign_lead_phone_strategy(): void
{
    [$admin, $team] = $this->tenantAdmin();
    $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Estrategia']);

    $this->actingAs($admin)
        ->post(route('tenant.campaigns.store'), [
            'name' => 'Campanha Estrategia',
            'dialing_mode' => 'power',
            'lead_phone_strategy' => 'horizontal',
            'status' => 'draft',
            'team_ids' => [$team->id],
            'list_ids' => [$list->id],
        ])
        ->assertRedirect(route('tenant.campaigns.index'))
        ->assertSessionHas('status');

    $campaign = Campaign::where('company_id', $admin->company_id)->where('name', 'Campanha Estrategia')->sole();

    $this->assertSame('horizontal', $campaign->lead_phone_strategy);

    $this->actingAs($admin)
        ->get(route('tenant.campaigns.index'))
        ->assertOk()
        ->assertSee('Estrategia de telefones do lead')
        ->assertSee('Horizontal ativado')
        ->assertSee('Horizontal desativado');
}
```

- [ ] **Step 2: Run test and confirm RED**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantCampaignListCrudTest.php --filter=lead_phone_strategy --no-coverage
```

Expected: FAIL because the controller/form do not handle the field.

- [ ] **Step 3: Persist strategy in controller**

In `CampaignController::rules()` add:

```php
'lead_phone_strategy' => ['required', Rule::in(['vertical', 'horizontal'])],
```

In `store()` and `update()` payloads add:

```php
'lead_phone_strategy' => $data['lead_phone_strategy'],
```

- [ ] **Step 4: Render strategy select**

In campaign form operation section, near `dialing_mode`, add:

```blade
<label class="grid gap-1 text-sm">
    <span class="font-semibold">Estrategia de telefones do lead</span>
    <select name="lead_phone_strategy" class="rounded-lg border px-3 py-2" style="border-color: var(--border); background: var(--surface); color: var(--fg);">
        <option value="vertical" @selected(old('lead_phone_strategy', $campaign?->lead_phone_strategy ?? 'vertical') === 'vertical')>Horizontal desativado</option>
        <option value="horizontal" @selected(old('lead_phone_strategy', $campaign?->lead_phone_strategy) === 'horizontal')>Horizontal ativado</option>
    </select>
    <span class="text-xs" style="color: var(--fg-muted);">Desativado tenta principais primeiro; ativado tenta alternativos do mesmo lead antes de seguir.</span>
</label>
```

- [ ] **Step 5: Run campaign tests**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantCampaignListCrudTest.php --filter="lead_phone_strategy|create_campaign" --no-coverage
```

Expected: strategy test PASS and existing campaign creation PASS.

- [ ] **Step 6: Commit strategy setting**

```powershell
git add app/Http/Controllers/Tenant/CampaignController.php resources/views/tenant/campaigns/_form.blade.php tests/Feature/TenantCampaignListCrudTest.php
git commit -m "feat: configure campaign lead phone strategy"
```

### Task 5: Dialer Queue Uses Lead Phones

**Files:**
- Modify: `app/Services/Dialer/MailingQueueService.php`
- Modify: `app/Services/Dialer/SimulatedDialerService.php`
- Modify: `app/Http/Controllers/Tenant/AgentPanelController.php`
- Test: `tests/Feature/TenantAgentPanelTest.php`

- [ ] **Step 1: Write failing horizontal strategy test**

Add to `TenantAgentPanelTest`:

```php
public function test_horizontal_strategy_tries_next_phone_from_same_lead_before_next_lead(): void
{
    [$admin, $company] = $this->tenantUser('company_admin');
    $campaign = Campaign::create([
        'company_id' => $company->id,
        'name' => 'Campanha Horizontal',
        'dialing_mode' => 'power',
        'lead_phone_strategy' => 'horizontal',
        'status' => 'active',
    ]);
    $list = ContactList::create(['company_id' => $company->id, 'name' => 'Lista Horizontal', 'status' => 'active']);
    $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id, 'weight' => 1]]);

    $leadA = $list->contacts()->create(['company_id' => $company->id, 'name' => 'Lead A', 'phone' => '31999990001', 'status' => 'pending']);
    $leadA->phones()->createMany([
        ['company_id' => $company->id, 'phone' => '31999990001', 'position' => 0, 'is_primary' => true, 'status' => 'pending'],
        ['company_id' => $company->id, 'phone' => '31999990002', 'position' => 1, 'is_primary' => false, 'status' => 'pending'],
    ]);
    $leadB = $list->contacts()->create(['company_id' => $company->id, 'name' => 'Lead B', 'phone' => '31999990003', 'status' => 'pending']);
    $leadB->phones()->create(['company_id' => $company->id, 'phone' => '31999990003', 'position' => 0, 'is_primary' => true, 'status' => 'pending']);

    $queue = app(\App\Services\Dialer\MailingQueueService::class);
    $first = $queue->nextContactForCampaign($campaign);
    $queue->markAttempt($first);
    $first->phones()->where('is_primary', true)->update(['status' => 'failed']);

    $second = $queue->nextContactForCampaign($campaign);

    $this->assertSame($leadA->id, $second->id);
    $this->assertSame('31999990002', $second->selected_phone->phone);
}
```

- [ ] **Step 2: Write failing vertical strategy test**

Add:

```php
public function test_vertical_strategy_tries_primary_phones_before_alternates(): void
{
    [$admin, $company] = $this->tenantUser('company_admin');
    $campaign = Campaign::create([
        'company_id' => $company->id,
        'name' => 'Campanha Vertical',
        'dialing_mode' => 'power',
        'lead_phone_strategy' => 'vertical',
        'status' => 'active',
    ]);
    $list = ContactList::create(['company_id' => $company->id, 'name' => 'Lista Vertical', 'status' => 'active']);
    $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id, 'weight' => 1]]);

    $leadA = $list->contacts()->create(['company_id' => $company->id, 'name' => 'Lead A', 'phone' => '31999990101', 'status' => 'pending']);
    $leadA->phones()->createMany([
        ['company_id' => $company->id, 'phone' => '31999990101', 'position' => 0, 'is_primary' => true, 'status' => 'pending'],
        ['company_id' => $company->id, 'phone' => '31999990102', 'position' => 1, 'is_primary' => false, 'status' => 'pending'],
    ]);
    $leadB = $list->contacts()->create(['company_id' => $company->id, 'name' => 'Lead B', 'phone' => '31999990103', 'status' => 'pending']);
    $leadB->phones()->create(['company_id' => $company->id, 'phone' => '31999990103', 'position' => 0, 'is_primary' => true, 'status' => 'pending']);

    $queue = app(\App\Services\Dialer\MailingQueueService::class);
    $first = $queue->nextContactForCampaign($campaign);
    $queue->markAttempt($first);
    $first->phones()->where('is_primary', true)->update(['status' => 'failed']);

    $second = $queue->nextContactForCampaign($campaign);

    $this->assertSame($leadB->id, $second->id);
    $this->assertSame('31999990103', $second->selected_phone->phone);
}
```

- [ ] **Step 3: Write failing anti-rechamada test**

Add:

```php
public function test_lead_connected_to_operator_is_not_selected_again_for_alternate_phone(): void
{
    [$agent, $company] = $this->tenantUser('agent');
    $campaign = Campaign::create([
        'company_id' => $company->id,
        'name' => 'Campanha Anti Rechamada',
        'dialing_mode' => 'power',
        'lead_phone_strategy' => 'horizontal',
        'status' => 'active',
    ]);
    $list = ContactList::create(['company_id' => $company->id, 'name' => 'Lista Anti Rechamada', 'status' => 'active']);
    $campaign->contactLists()->sync([$list->id => ['company_id' => $company->id, 'weight' => 1]]);
    $lead = $list->contacts()->create(['company_id' => $company->id, 'name' => 'Lead', 'phone' => '31999990201', 'status' => 'pending']);
    $primary = $lead->phones()->create(['company_id' => $company->id, 'phone' => '31999990201', 'position' => 0, 'is_primary' => true, 'status' => 'dialing']);
    $lead->phones()->create(['company_id' => $company->id, 'phone' => '31999990202', 'position' => 1, 'is_primary' => false, 'status' => 'pending']);

    \App\Models\Call::create([
        'company_id' => $company->id,
        'user_id' => $agent->id,
        'campaign_id' => $campaign->id,
        'contact_id' => $lead->id,
        'contact_phone_id' => $primary->id,
        'direction' => 'outbound',
        'status' => 'in_progress',
        'channel' => 'simulated',
        'phone' => $primary->phone,
        'started_at' => now(),
        'answered_at' => now(),
    ]);

    $this->assertNull(app(\App\Services\Dialer\MailingQueueService::class)->nextContactForCampaign($campaign));
}
```

- [ ] **Step 4: Run strategy tests and confirm RED**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantAgentPanelTest.php --filter="strategy|anti_rechamada" --no-coverage
```

Expected: FAIL because queue does not select `contact_phones`.

- [ ] **Step 5: Update queue selection**

In `MailingQueueService::nextContactForCampaign()`, join `contact_phones` and exclude contacted leads:

```php
$query = Contact::query()
    ->select('contacts.*')
    ->addSelect('contact_phones.id as selected_contact_phone_id')
    ->join('contact_phones', 'contact_phones.contact_id', '=', 'contacts.id')
    ->join('campaign_contact_list', function ($join) use ($campaign): void {
        $join->on('campaign_contact_list.contact_list_id', '=', 'contacts.contact_list_id')
            ->where('campaign_contact_list.campaign_id', $campaign->id)
            ->where('campaign_contact_list.company_id', $campaign->company_id);
    })
    ->where('contact_phones.company_id', $campaign->company_id)
    ->where('contact_phones.status', 'pending')
    ->where(function ($query): void {
        $query->whereNull('contact_phones.next_attempt_at')
            ->orWhere('contact_phones.next_attempt_at', '<=', now());
    })
    ->whereDoesntHave('calls', function ($query) use ($campaign): void {
        $query->where('campaign_id', $campaign->id)
            ->whereNotNull('answered_at');
    });
```

Ordering:

```php
->orderByDesc('campaign_contact_list.weight')
->when(($campaign->lead_phone_strategy ?? 'vertical') === 'vertical', fn ($query) => $query->orderBy('contact_phones.position'))
->orderBy('contacts.attempts_count')
->orderBy('contacts.id')
->when(($campaign->lead_phone_strategy ?? 'vertical') === 'horizontal', fn ($query) => $query->orderBy('contact_phones.position'))
```

After fetching, set relation:

```php
if ($contact) {
    $phone = \App\Models\ContactPhone::query()->find($contact->selected_contact_phone_id);
    $contact->setRelation('selected_phone', $phone);
}
```

Keep compatibility fallback: if a contact has no `contact_phones`, create a primary phone row lazily before selection in a private `ensurePrimaryPhones(array $listIds, int $companyId): void`.

- [ ] **Step 6: Update markAttempt to mark selected phone**

In `markAttempt()`:

```php
$selectedPhone = $contact->getRelation('selected_phone');

if ($selectedPhone) {
    $selectedPhone->forceFill([
        'status' => $status,
        'attempts_count' => $selectedPhone->attempts_count + 1,
        'last_attempt_at' => now(),
        'next_attempt_at' => $nextAttemptAt,
    ])->save();
}
```

Keep the existing contact-level attempt update for compatibility and reporting.

- [ ] **Step 7: Use selected phone when creating calls**

In `SimulatedDialerService::createBatch()`:

```php
$selectedPhone = $contact->getRelation('selected_phone');

Call::create([
    'company_id' => $campaign->company_id,
    'user_id' => $agent->id,
    'campaign_id' => $campaign->id,
    'contact_id' => $contact->id,
    'contact_phone_id' => $selectedPhone?->id,
    'direction' => 'outbound',
    'status' => $isWinner ? 'in_progress' : 'cancelled',
    'end_reason' => $isWinner ? null : 'answered_elsewhere',
    'channel' => 'simulated',
    'phone' => $selectedPhone?->phone ?? $contact->phone,
    'started_at' => now(),
    'answered_at' => $isWinner ? now() : null,
    'ended_at' => $isWinner ? null : now(),
]);
```

- [ ] **Step 8: Run focused dialer tests**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantAgentPanelTest.php --filter="strategy|anti_rechamada|simulated_dialer_assigns" --no-coverage
```

Expected: new strategy tests PASS and existing simulated assignment tests PASS.

- [ ] **Step 9: Commit queue strategy**

```powershell
git add app/Services/Dialer app/Http/Controllers/Tenant/AgentPanelController.php tests/Feature/TenantAgentPanelTest.php
git commit -m "feat: dial lead phones by campaign strategy"
```

### Task 6: Import Edge Cases, Notifications and Roadmap

**Files:**
- Modify: `app/Services/Lists/CsvContactListImportService.php`
- Modify: `app/Http/Controllers/Tenant/ContactListController.php`
- Modify: `docs/ROADMAP_CONCLUSAO.md`
- Test: `tests/Feature/TenantCampaignListCrudTest.php`

- [ ] **Step 1: Add edge-case tests**

Add:

```php
public function test_csv_import_rejects_empty_file_and_file_without_header(): void
{
    [$admin] = $this->tenantAdmin();

    foreach (['vazio.csv' => '', 'sem-cabecalho.csv' => "\n31999990001"] as $name => $content) {
        $this->actingAs($admin)
            ->post(route('tenant.lists.store'), [
                'name' => 'Lista '.$name,
                'status' => 'active',
                'import_file' => UploadedFile::fake()->createWithContent($name, $content),
                'separator' => ';',
                'delimiter' => '"',
                'import_columns' => [
                    ['index' => 0, 'enabled' => '1', 'name' => 'Telefone', 'type' => 'phone'],
                ],
            ])
            ->assertSessionHasErrors('import_file');
    }
}

public function test_csv_import_applies_blacklist_to_all_phone_columns(): void
{
    [$admin] = $this->tenantAdmin();
    BlacklistNumber::create([
        'company_id' => $admin->company_id,
        'phone' => '31988880002',
        'reason' => 'Bloqueado',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->post(route('tenant.lists.store'), [
            'name' => 'Lista Blacklist Multiphone',
            'status' => 'active',
            'import_file' => UploadedFile::fake()->createWithContent('leads.csv', "Nome;Tel1;Tel2\nMaria;31999990001;31988880002"),
            'separator' => ';',
            'delimiter' => '"',
            'import_columns' => [
                ['index' => 0, 'enabled' => '1', 'name' => 'Nome', 'type' => 'extra'],
                ['index' => 1, 'enabled' => '1', 'name' => 'Tel1', 'type' => 'phone'],
                ['index' => 2, 'enabled' => '1', 'name' => 'Tel2', 'type' => 'phone'],
            ],
        ])
        ->assertSessionHas('status', fn (string $message): bool => str_contains($message, '1 bloqueado'));

    $list = ContactList::where('company_id', $admin->company_id)->where('name', 'Lista Blacklist Multiphone')->sole();

    $this->assertSame(['31999990001'], $list->contacts()->sole()->phones()->pluck('phone')->all());
}
```

- [ ] **Step 2: Run edge tests and confirm RED where needed**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantCampaignListCrudTest.php --filter="empty_file|blacklist_to_all" --no-coverage
```

Expected: empty/header validation may fail until importer validates headers explicitly.

- [ ] **Step 3: Validate empty/header server-side**

In `CsvContactListImportService::import()`, read headers before selected columns:

```php
$headers = $fileObject->fgetcsv();

if (! is_array($headers) || collect($headers)->filter(fn ($header) => trim((string) $header) !== '')->isEmpty()) {
    throw \Illuminate\Validation\ValidationException::withMessages([
        'import_file' => 'O arquivo precisa ter uma linha de cabecalho.',
    ]);
}
```

Do not persist columns whose `name` is empty.

- [ ] **Step 4: Update notification message for lead imports**

In `notifyListImport()`, support both summaries:

```php
$total = $summary['leads_imported'] ?? $summary['imported'] ?? 0;

if ($total <= 0) {
    return;
}

$message = array_key_exists('phones_imported', $summary)
    ? sprintf('%s recebeu %d leads e %d telefones importados.', $list->name, $summary['leads_imported'], $summary['phones_imported'])
    : sprintf('%s recebeu %d contatos importados.', $list->name, $summary['imported']);
```

- [ ] **Step 5: Update roadmap**

Add to `docs/ROADMAP_CONCLUSAO.md`:

```markdown
- [x] Importacao CSV/TXT de listas com telefones do lead
  - Upload por clique ou arrastar no modal de lista.
  - Primeira linha como cabecalho e selecao dinamica de colunas.
  - Uma ou mais colunas de telefone por lead.
  - Campanha controla estrategia horizontal ativada/desativada.
  - Anti-rechamada quando qualquer telefone do lead chega ao operador/ramal.
```

- [ ] **Step 6: Run focused tests**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantCampaignListCrudTest.php --filter="csv_import|empty_file|blacklist_to_all|contact_list_import_notifies" --no-coverage
```

Expected: focused tests PASS.

- [ ] **Step 7: Commit edge cases and roadmap**

```powershell
git add app/Services/Lists app/Http/Controllers/Tenant/ContactListController.php tests/Feature/TenantCampaignListCrudTest.php docs/ROADMAP_CONCLUSAO.md
git commit -m "feat: harden csv list imports"
```

### Task 7: Full Verification and Browser QA

**Files:**
- Verify all changed files.
- No expected code changes unless verification exposes a defect.

- [ ] **Step 1: Run full PHP suite**

Run:

```powershell
docker compose up -d
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan test --no-coverage
```

Expected: full PHP suite PASS, with only existing intentional skips.

- [ ] **Step 2: Run PHP style**

Run:

```powershell
docker compose exec -T app ./vendor/bin/pint --test
```

Expected: Pint exits 0.

- [ ] **Step 3: Run frontend tests and build**

Run:

```powershell
npm run test:frontend
npm run build
```

Expected: all frontend tests PASS and build exits 0.

- [ ] **Step 4: Browser QA in local app**

Open `http://localhost:8087` and verify:

1. `Listas > Nova lista` opens a modal, not a new page.
2. Upload accepts `.csv` and `.txt`.
3. Drag and drop populates preview.
4. First line appears as column names.
5. Columns can be disabled.
6. One or more columns can be marked `Telefone`.
7. Saving without phone column shows inline/form error, no `alert`.
8. Saving a valid file shows the right-side notification and creates leads/phones.
9. Campaign form shows `Estrategia de telefones do lead`.
10. The page remains visually consistent with the existing design system on desktop and mobile.

- [ ] **Step 5: Final diff review**

Run:

```powershell
git status --short
git diff --check
git diff --stat master...HEAD
```

Expected: only planned files changed, no whitespace errors, no `vendor`, `node_modules`, logs or screenshots staged.

- [ ] **Step 6: Commit any verification fixes**

If verification required fixes:

Stage the files fixed during verification and commit:

```powershell
git status --short
git add app resources tests docs database
git commit -m "fix: polish csv list import flow"
```

If no fixes were needed, do not create an empty commit.
