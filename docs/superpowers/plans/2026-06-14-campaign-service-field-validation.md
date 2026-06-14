# Advanced Campaign Service Field Validation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add typed validation, normalization, field-level errors, and input masks for the eight campaign service-field types without changing the current campaign or agent workflows.

**Architecture:** A pure `ServiceFieldValidator` receives the campaign schema and submitted values, then returns a readonly result containing normalized values and Laravel-compatible error keys. Campaign configuration remains in `CampaignController`; call lifecycle remains in `AgentPanelController`, which delegates only dynamic-field concerns to the new service. Frontend formatting is isolated in a dependency-free ES module and the backend remains authoritative.

**Tech Stack:** PHP 8.3, Laravel 12, PHPUnit, Blade, Alpine.js, Node test runner, Vite, Docker Compose.

---

## File Map

- Create `app/Services/Campaigns/ServiceFieldValidationResult.php`: immutable validation result.
- Create `app/Services/Campaigns/ServiceFieldValidator.php`: validation and normalization for all supported types.
- Create `tests/Unit/ServiceFieldValidatorTest.php`: fast exhaustive validation tests.
- Modify `app/Http/Controllers/Tenant/CampaignController.php`: allow the eight schema types.
- Modify `resources/views/tenant/campaigns/_form.blade.php`: expose the eight options in the existing modal/wizard.
- Modify `tests/Feature/TenantCampaignListCrudTest.php`: verify allowed and rejected schema types.
- Modify `app/Http/Controllers/Tenant/AgentPanelController.php`: delegate submitted values to the validator before mutating the call.
- Modify `resources/views/tenant/agent/index.blade.php`: render type-specific attributes, old values, masks, and field errors.
- Modify `tests/Feature/TenantAgentPanelTest.php`: verify rendering, failure atomicity, normalized persistence, and tenant isolation.
- Create `resources/js/service-field-masks.js`: reusable visual formatters.
- Create `tests/Frontend/service-field-masks.test.js`: formatter behavior.
- Modify `resources/js/app.js`: expose the Alpine mask component.
- Modify `docs/ROADMAP_CONCLUSAO.md`: record the completed capability and verification.

### Task 1: Validation Result and Core Scalar Types

**Files:**
- Create: `app/Services/Campaigns/ServiceFieldValidationResult.php`
- Create: `app/Services/Campaigns/ServiceFieldValidator.php`
- Create: `tests/Unit/ServiceFieldValidatorTest.php`

- [ ] **Step 1: Write failing tests for required, text, number, date, and unknown keys**

Create `tests/Unit/ServiceFieldValidatorTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\Campaigns\ServiceFieldValidator;
use PHPUnit\Framework\TestCase;

class ServiceFieldValidatorTest extends TestCase
{
    private ServiceFieldValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new ServiceFieldValidator;
    }

    public function test_it_normalizes_core_scalar_types_and_ignores_unknown_keys(): void
    {
        $result = $this->validator->validate([
            ['key' => 'nome', 'label' => 'Nome', 'type' => 'text', 'required' => true],
            ['key' => 'quantidade', 'label' => 'Quantidade', 'type' => 'number', 'required' => false],
            ['key' => 'taxa', 'label' => 'Taxa', 'type' => 'number', 'required' => false],
            ['key' => 'nascimento', 'label' => 'Nascimento', 'type' => 'date', 'required' => false],
        ], [
            'nome' => '  Cliente  ',
            'quantidade' => '12,5',
            'taxa' => '12.50',
            'nascimento' => '2026-06-14',
            'fora_schema' => 'nao persistir',
        ]);

        $this->assertTrue($result->passes());
        $this->assertSame([], $result->errors);
        $this->assertSame([
            'nome' => 'Cliente',
            'quantidade' => '12.5',
            'taxa' => '12.50',
            'nascimento' => '2026-06-14',
        ], $result->values);
    }

    public function test_it_reports_required_and_invalid_core_values_by_field_key(): void
    {
        $result = $this->validator->validate([
            ['key' => 'nome', 'label' => 'Nome do cliente', 'type' => 'text', 'required' => true],
            ['key' => 'numero', 'label' => 'Numero', 'type' => 'number', 'required' => false],
            ['key' => 'data', 'label' => 'Data de nascimento', 'type' => 'date', 'required' => false],
        ], [
            'nome' => '   ',
            'numero' => 'doze',
            'data' => '2026-02-30',
        ]);

        $this->assertFalse($result->passes());
        $this->assertSame([
            'service_fields.nome' => 'Informe Nome do cliente.',
            'service_fields.numero' => 'Numero possui numero invalido.',
            'service_fields.data' => 'Data de nascimento possui data invalida.',
        ], $result->errors);
        $this->assertSame([], $result->values);
    }

    public function test_zero_is_a_valid_required_value_and_optional_blanks_are_omitted(): void
    {
        $result = $this->validator->validate([
            ['key' => 'contador', 'label' => 'Contador', 'type' => 'number', 'required' => true],
            ['key' => 'observacao', 'label' => 'Observacao', 'type' => 'text', 'required' => false],
        ], [
            'contador' => '0',
            'observacao' => ' ',
        ]);

        $this->assertTrue($result->passes());
        $this->assertSame(['contador' => '0'], $result->values);
    }

    public function test_text_is_limited_to_255_characters(): void
    {
        $result = $this->validator->validate([
            ['key' => 'observacao', 'label' => 'Observacao', 'type' => 'text', 'required' => false],
        ], [
            'observacao' => str_repeat('a', 256),
        ]);

        $this->assertSame([
            'service_fields.observacao' => 'Observacao deve ter no maximo 255 caracteres.',
        ], $result->errors);
    }
}
```

- [ ] **Step 2: Run the unit test and confirm the missing-class failure**

Run:

```powershell
docker compose exec -T app php artisan test tests/Unit/ServiceFieldValidatorTest.php --no-coverage
```

Expected: FAIL because `App\Services\Campaigns\ServiceFieldValidator` does not exist.

- [ ] **Step 3: Create the immutable result**

Create `app/Services/Campaigns/ServiceFieldValidationResult.php`:

```php
<?php

namespace App\Services\Campaigns;

final readonly class ServiceFieldValidationResult
{
    public function __construct(
        public array $values,
        public array $errors,
    ) {}

    public function passes(): bool
    {
        return $this->errors === [];
    }
}
```

- [ ] **Step 4: Implement the minimal validator for text, number, and date**

Create `app/Services/Campaigns/ServiceFieldValidator.php`:

```php
<?php

namespace App\Services\Campaigns;

use DateTimeImmutable;

final class ServiceFieldValidator
{
    public function validate(array $schema, array $values): ServiceFieldValidationResult
    {
        $normalized = [];
        $errors = [];

        foreach ($schema as $field) {
            $key = (string) ($field['key'] ?? '');
            $label = (string) ($field['label'] ?? $key);
            $type = (string) ($field['type'] ?? 'text');

            if ($key === '') {
                continue;
            }

            $value = $values[$key] ?? null;
            $trimmed = is_string($value) ? trim($value) : $value;

            if ($trimmed === null || $trimmed === '') {
                if ((bool) ($field['required'] ?? false)) {
                    $errors["service_fields.{$key}"] = "Informe {$label}.";
                }

                continue;
            }

            [$valid, $result, $message] = match ($type) {
                'number' => $this->number($trimmed, $label),
                'date' => $this->date($trimmed, $label),
                default => $this->text($trimmed, $label),
            };

            if (! $valid) {
                $errors["service_fields.{$key}"] = $message;

                continue;
            }

            $normalized[$key] = $result;
        }

        return new ServiceFieldValidationResult($normalized, $errors);
    }

    private function text(mixed $value, string $label): array
    {
        if (! is_string($value) || mb_strlen($value) > 255) {
            return [false, null, "{$label} deve ter no maximo 255 caracteres."];
        }

        return [true, $value, null];
    }

    private function number(mixed $value, string $label): array
    {
        if (! is_string($value) || preg_match('/^-?\d+(?:[.,]\d+)?$/', $value) !== 1) {
            return [false, null, "{$label} possui numero invalido."];
        }

        return [true, str_replace(',', '.', $value), null];
    }

    private function date(mixed $value, string $label): array
    {
        if (! is_string($value)) {
            return [false, null, "{$label} possui data invalida."];
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (! $date || $date->format('Y-m-d') !== $value) {
            return [false, null, "{$label} possui data invalida."];
        }

        return [true, $value, null];
    }
}
```

- [ ] **Step 5: Run the focused test and formatting**

Run:

```powershell
docker compose exec -T app php artisan test tests/Unit/ServiceFieldValidatorTest.php --no-coverage
docker compose exec -T app ./vendor/bin/pint --test app/Services/Campaigns tests/Unit/ServiceFieldValidatorTest.php
```

Expected: all four tests PASS and Pint exits 0.

- [ ] **Step 6: Commit the core validator**

```powershell
git add app/Services/Campaigns tests/Unit/ServiceFieldValidatorTest.php
git commit -m "feat: add campaign service field validator"
```

### Task 2: CPF and CNPJ Check-Digit Validation

**Files:**
- Modify: `app/Services/Campaigns/ServiceFieldValidator.php`
- Modify: `tests/Unit/ServiceFieldValidatorTest.php`

- [ ] **Step 1: Add failing document tests**

Append these methods to `ServiceFieldValidatorTest`:

```php
public function test_it_normalizes_valid_cpf_and_cnpj(): void
{
    $result = $this->validator->validate([
        ['key' => 'cpf', 'label' => 'CPF Cliente', 'type' => 'cpf', 'required' => true],
        ['key' => 'cnpj', 'label' => 'CNPJ Empresa', 'type' => 'cnpj', 'required' => true],
    ], [
        'cpf' => '529.982.247-25',
        'cnpj' => '04.252.011/0001-10',
    ]);

    $this->assertTrue($result->passes());
    $this->assertSame([
        'cpf' => '52998224725',
        'cnpj' => '04252011000110',
    ], $result->values);
}

public function test_it_rejects_invalid_and_repeated_documents(): void
{
    $result = $this->validator->validate([
        ['key' => 'cpf_invalido', 'label' => 'CPF Invalido', 'type' => 'cpf', 'required' => false],
        ['key' => 'cpf_repetido', 'label' => 'CPF Repetido', 'type' => 'cpf', 'required' => false],
        ['key' => 'cnpj_invalido', 'label' => 'CNPJ Invalido', 'type' => 'cnpj', 'required' => false],
        ['key' => 'cnpj_repetido', 'label' => 'CNPJ Repetido', 'type' => 'cnpj', 'required' => false],
    ], [
        'cpf_invalido' => '529.982.247-24',
        'cpf_repetido' => '111.111.111-11',
        'cnpj_invalido' => '04.252.011/0001-11',
        'cnpj_repetido' => '11.111.111/1111-11',
    ]);

    $this->assertSame([
        'service_fields.cpf_invalido' => 'CPF Invalido possui CPF invalido.',
        'service_fields.cpf_repetido' => 'CPF Repetido possui CPF invalido.',
        'service_fields.cnpj_invalido' => 'CNPJ Invalido possui CNPJ invalido.',
        'service_fields.cnpj_repetido' => 'CNPJ Repetido possui CNPJ invalido.',
    ], $result->errors);
}
```

- [ ] **Step 2: Run the tests and confirm document cases fail**

Run:

```powershell
docker compose exec -T app php artisan test tests/Unit/ServiceFieldValidatorTest.php --filter=document --no-coverage
```

Expected: FAIL because CPF and CNPJ currently fall through to text.

- [ ] **Step 3: Add document dispatch and check-digit helpers**

Add these match arms:

```php
'cpf' => $this->document($trimmed, $label, 11, 'CPF', [10, 9, 8, 7, 6, 5, 4, 3, 2]),
'cnpj' => $this->document($trimmed, $label, 14, 'CNPJ', [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]),
```

Add these methods:

```php
private function document(mixed $value, string $label, int $length, string $name, array $weights): array
{
    $digits = is_string($value) ? preg_replace('/\D+/', '', $value) : '';
    $valid = strlen($digits) === $length
        && preg_match('/^(\d)\1+$/', $digits) !== 1
        && $this->hasValidCheckDigits($digits, $weights);

    if (! $valid) {
        return [false, null, "{$label} possui {$name} invalido."];
    }

    return [true, $digits, null];
}

private function hasValidCheckDigits(string $digits, array $firstWeights): bool
{
    $baseLength = count($firstWeights);
    $first = $this->checkDigit(substr($digits, 0, $baseLength), $firstWeights);
    $secondWeights = array_merge([$firstWeights[0] + 1], $firstWeights);
    $second = $this->checkDigit(substr($digits, 0, $baseLength).$first, $secondWeights);

    return substr($digits, -2) === (string) $first.(string) $second;
}

private function checkDigit(string $digits, array $weights): int
{
    $sum = 0;

    foreach ($weights as $index => $weight) {
        $sum += ((int) $digits[$index]) * $weight;
    }

    $remainder = $sum % 11;

    return $remainder < 2 ? 0 : 11 - $remainder;
}
```

- [ ] **Step 4: Run all validator tests**

Run:

```powershell
docker compose exec -T app php artisan test tests/Unit/ServiceFieldValidatorTest.php --no-coverage
```

Expected: all tests PASS.

- [ ] **Step 5: Commit document validation**

```powershell
git add app/Services/Campaigns/ServiceFieldValidator.php tests/Unit/ServiceFieldValidatorTest.php
git commit -m "feat: validate cpf and cnpj service fields"
```

### Task 3: Phone, Email, and Brazilian Currency

**Files:**
- Modify: `app/Services/Campaigns/ServiceFieldValidator.php`
- Modify: `tests/Unit/ServiceFieldValidatorTest.php`

- [ ] **Step 1: Add failing phone, email, and currency tests**

Append:

```php
public function test_it_normalizes_brazilian_and_e164_phones(): void
{
    $result = $this->validator->validate([
        ['key' => 'fixo', 'label' => 'Fixo', 'type' => 'phone', 'required' => false],
        ['key' => 'celular', 'label' => 'Celular', 'type' => 'phone', 'required' => false],
        ['key' => 'internacional', 'label' => 'Internacional', 'type' => 'phone', 'required' => false],
    ], [
        'fixo' => '(31) 3333-4444',
        'celular' => '(31) 99999-0000',
        'internacional' => '+1 202-555-0123',
    ]);

    $this->assertTrue($result->passes());
    $this->assertSame([
        'fixo' => '3133334444',
        'celular' => '31999990000',
        'internacional' => '12025550123',
    ], $result->values);
}

public function test_it_rejects_phone_outside_the_allowed_ranges(): void
{
    $result = $this->validator->validate([
        ['key' => 'br', 'label' => 'Telefone', 'type' => 'phone', 'required' => false],
        ['key' => 'e164', 'label' => 'Internacional', 'type' => 'phone', 'required' => false],
    ], [
        'br' => '123456789',
        'e164' => '+1234567',
    ]);

    $this->assertSame([
        'service_fields.br' => 'Telefone possui telefone invalido.',
        'service_fields.e164' => 'Internacional possui telefone invalido.',
    ], $result->errors);
}

public function test_it_normalizes_email_and_rejects_invalid_email(): void
{
    $valid = $this->validator->validate([
        ['key' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => false],
    ], ['email' => ' CLIENTE@EXAMPLE.COM ']);

    $invalid = $this->validator->validate([
        ['key' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => false],
    ], ['email' => 'cliente@']);

    $this->assertSame(['email' => 'cliente@example.com'], $valid->values);
    $this->assertSame([
        'service_fields.email' => 'E-mail possui e-mail invalido.',
    ], $invalid->errors);
}

public function test_it_converts_brazilian_currency_to_integer_cents(): void
{
    foreach ([
        '10' => 1000,
        '10,5' => 1050,
        '10,50' => 1050,
        'R$ 1.234,56' => 123456,
    ] as $input => $expected) {
        $result = $this->validator->validate([
            ['key' => 'valor', 'label' => 'Valor', 'type' => 'currency', 'required' => false],
        ], ['valor' => $input]);

        $this->assertSame(['valor' => $expected], $result->values);
    }
}

public function test_it_rejects_negative_or_malformed_currency(): void
{
    foreach (['-10,00', '10,123', '1.23,45', 'R$ abc'] as $input) {
        $result = $this->validator->validate([
            ['key' => 'valor', 'label' => 'Valor da proposta', 'type' => 'currency', 'required' => false],
        ], ['valor' => $input]);

        $this->assertSame([
            'service_fields.valor' => 'Valor da proposta possui moeda invalida.',
        ], $result->errors);
    }
}
```

- [ ] **Step 2: Run the new tests and confirm they fail**

Run:

```powershell
docker compose exec -T app php artisan test tests/Unit/ServiceFieldValidatorTest.php --filter="phone|email|currency" --no-coverage
```

Expected: FAIL because the new types are not dispatched.

- [ ] **Step 3: Implement dispatch and normalization**

Add match arms:

```php
'phone' => $this->phone($trimmed, $label),
'email' => $this->email($trimmed, $label),
'currency' => $this->currency($trimmed, $label),
```

Add:

```php
private function phone(mixed $value, string $label): array
{
    if (! is_string($value)) {
        return [false, null, "{$label} possui telefone invalido."];
    }

    $international = str_starts_with($value, '+');
    $digits = preg_replace('/\D+/', '', $value);
    $length = strlen($digits);
    $valid = $international
        ? $length >= 8 && $length <= 15
        : in_array($length, [10, 11], true);

    if (! $valid) {
        return [false, null, "{$label} possui telefone invalido."];
    }

    return [true, $digits, null];
}

private function email(mixed $value, string $label): array
{
    $normalized = is_string($value) ? mb_strtolower($value) : '';

    if (mb_strlen($normalized) > 255 || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
        return [false, null, "{$label} possui e-mail invalido."];
    }

    return [true, $normalized, null];
}

private function currency(mixed $value, string $label): array
{
    if (! is_string($value)) {
        return [false, null, "{$label} possui moeda invalida."];
    }

    $normalized = preg_replace('/^R\$\s*/i', '', trim($value));
    $normalized = str_replace(' ', '', $normalized);
    $valid = preg_match('/^(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{1,2})?$/', $normalized) === 1;

    if (! $valid) {
        return [false, null, "{$label} possui moeda invalida."];
    }

    [$whole, $decimal] = array_pad(explode(',', str_replace('.', '', $normalized), 2), 2, '');
    $cents = ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');

    return [true, $cents, null];
}
```

- [ ] **Step 4: Run the complete unit suite for the service**

Run:

```powershell
docker compose exec -T app php artisan test tests/Unit/ServiceFieldValidatorTest.php --no-coverage
docker compose exec -T app ./vendor/bin/pint --test app/Services/Campaigns tests/Unit/ServiceFieldValidatorTest.php
```

Expected: all validator tests PASS and Pint exits 0.

- [ ] **Step 5: Commit the remaining typed validators**

```powershell
git add app/Services/Campaigns/ServiceFieldValidator.php tests/Unit/ServiceFieldValidatorTest.php
git commit -m "feat: validate contact and currency service fields"
```

### Task 4: Campaign Schema Supports All Eight Types

**Files:**
- Modify: `app/Http/Controllers/Tenant/CampaignController.php`
- Modify: `resources/views/tenant/campaigns/_form.blade.php`
- Modify: `tests/Feature/TenantCampaignListCrudTest.php`

- [ ] **Step 1: Expand the campaign feature test and add an invalid-type test**

Change the schema in `test_tenant_admin_can_configure_campaign_dynamic_service_fields` to submit:

```php
'service_fields_schema' => [
    ['enabled' => '1', 'key' => 'observacao', 'label' => 'Observacao', 'type' => 'text'],
    ['enabled' => '1', 'key' => 'quantidade', 'label' => 'Quantidade', 'type' => 'number'],
    ['enabled' => '1', 'key' => 'data', 'label' => 'Data', 'type' => 'date'],
    ['enabled' => '1', 'key' => 'cpf', 'label' => 'CPF', 'type' => 'cpf', 'required' => '1'],
    ['enabled' => '1', 'key' => 'cnpj', 'label' => 'CNPJ', 'type' => 'cnpj'],
    ['enabled' => '1', 'key' => 'telefone', 'label' => 'Telefone', 'type' => 'phone'],
    ['enabled' => '1', 'key' => 'email', 'label' => 'E-mail', 'type' => 'email'],
    ['enabled' => '1', 'key' => 'valor', 'label' => 'Valor', 'type' => 'currency'],
],
```

Assert:

```php
$this->assertSame(
    ['text', 'number', 'date', 'cpf', 'cnpj', 'phone', 'email', 'currency'],
    collect($campaign->service_fields_schema)->pluck('type')->all(),
);
```

Add:

```php
public function test_tenant_admin_cannot_configure_an_unknown_service_field_type(): void
{
    [$admin, $team] = $this->tenantAdmin();
    $list = ContactList::create(['company_id' => $admin->company_id, 'name' => 'Lista Tipo Invalido']);

    $this->actingAs($admin)
        ->post(route('tenant.campaigns.store'), [
            'name' => 'Campanha Tipo Invalido',
            'dialing_mode' => 'power',
            'status' => 'draft',
            'team_ids' => [$team->id],
            'list_ids' => [$list->id],
            'service_fields_schema' => [
                ['enabled' => '1', 'key' => 'arquivo', 'label' => 'Arquivo', 'type' => 'file'],
            ],
        ])
        ->assertSessionHasErrors('service_fields_schema.0.type');

    $this->assertDatabaseMissing('campaigns', ['name' => 'Campanha Tipo Invalido']);
}
```

- [ ] **Step 2: Run the campaign tests and confirm allowed-type failure**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantCampaignListCrudTest.php --filter=service_field --no-coverage
```

Expected: the eight-type test FAILS on validation; the unknown type test PASSES.

- [ ] **Step 3: Expand the controller allowlist**

Change the rule in `CampaignController::rules()` to:

```php
'service_fields_schema.*.type' => [
    'nullable',
    Rule::in(['text', 'number', 'date', 'cpf', 'cnpj', 'phone', 'email', 'currency']),
],
```

In `serviceFieldsSchemaPayload()`, replace the three-type check with the same allowlist:

```php
$allowedTypes = ['text', 'number', 'date', 'cpf', 'cnpj', 'phone', 'email', 'currency'];
```

Capture it in the map closure:

```php
->map(function (array $field) use (&$usedKeys, $allowedTypes): ?array {
```

Then persist only an allowed type:

```php
'type' => in_array($field['type'] ?? 'text', $allowedTypes, true)
    ? $field['type']
    : 'text',
```

Keep this method responsible for enabled rows, normalized unique keys, labels, types, and required flags. Do not create another schema-normalization layer.

- [ ] **Step 4: Add all options to the existing campaign form**

Replace the three options with:

```blade
<option value="text" @selected(($field['type'] ?? 'text') === 'text')>Texto</option>
<option value="number" @selected(($field['type'] ?? 'text') === 'number')>Numero</option>
<option value="date" @selected(($field['type'] ?? 'text') === 'date')>Data</option>
<option value="cpf" @selected(($field['type'] ?? 'text') === 'cpf')>CPF</option>
<option value="cnpj" @selected(($field['type'] ?? 'text') === 'cnpj')>CNPJ</option>
<option value="phone" @selected(($field['type'] ?? 'text') === 'phone')>Telefone</option>
<option value="email" @selected(($field['type'] ?? 'text') === 'email')>E-mail</option>
<option value="currency" @selected(($field['type'] ?? 'text') === 'currency')>Moeda</option>
```

- [ ] **Step 5: Run campaign tests**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantCampaignListCrudTest.php --filter=service_field --no-coverage
```

Expected: both focused tests PASS.

- [ ] **Step 6: Commit campaign schema support**

```powershell
git add app/Http/Controllers/Tenant/CampaignController.php resources/views/tenant/campaigns/_form.blade.php tests/Feature/TenantCampaignListCrudTest.php
git commit -m "feat: expose typed campaign service fields"
```

### Task 5: Validate Before Ending a Call

**Files:**
- Modify: `app/Http/Controllers/Tenant/AgentPanelController.php`
- Modify: `tests/Feature/TenantAgentPanelTest.php`

- [ ] **Step 1: Replace the existing dynamic-field feature test with typed normalization**

In `test_agent_panel_uses_campaign_dynamic_service_fields`, use:

```php
'service_fields_schema' => [
    ['key' => 'cpf_cliente', 'label' => 'CPF Cliente', 'type' => 'cpf', 'required' => true],
    ['key' => 'cnpj_empresa', 'label' => 'CNPJ Empresa', 'type' => 'cnpj', 'required' => false],
    ['key' => 'telefone', 'label' => 'Telefone', 'type' => 'phone', 'required' => false],
    ['key' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => false],
    ['key' => 'valor_oferta', 'label' => 'Valor Oferta', 'type' => 'currency', 'required' => false],
],
```

Submit invalid values and assert the call remains untouched:

```php
$this->actingAs($agent)
    ->patch(route('tenant.agent.calls.end'), [
        'call_id' => $call->id,
        'disposition_id' => $disposition->id,
        'service_fields' => [
            'cpf_cliente' => '111.111.111-11',
            'cnpj_empresa' => '11.111.111/1111-11',
            'telefone' => '123',
            'email' => 'cliente@',
            'valor_oferta' => '-10,00',
        ],
    ])
    ->assertSessionHasErrors([
        'service_fields.cpf_cliente',
        'service_fields.cnpj_empresa',
        'service_fields.telefone',
        'service_fields.email',
        'service_fields.valor_oferta',
    ]);

$this->assertSame('in_progress', $call->fresh()->status);
$this->assertSame('in_call', $agent->fresh()->currentAgentStatus->status);
```

Then submit valid values:

```php
'service_fields' => [
    'cpf_cliente' => '529.982.247-25',
    'cnpj_empresa' => '04.252.011/0001-10',
    'telefone' => '(31) 99999-0000',
    'email' => ' CLIENTE@EXAMPLE.COM ',
    'valor_oferta' => 'R$ 1.234,56',
    'campo_fora_schema' => 'ignorado',
],
```

Assert:

```php
$this->assertSame([
    'cpf_cliente' => '52998224725',
    'cnpj_empresa' => '04252011000110',
    'telefone' => '31999990000',
    'email' => 'cliente@example.com',
    'valor_oferta' => 123456,
], $call->fresh()->service_fields);
```

- [ ] **Step 2: Add tenant-isolation coverage for the schema**

Add a test that creates another company's campaign with the same field key but a different required/type definition, ends the current tenant's call, and proves only the current call campaign schema is used:

```php
public function test_service_field_validation_uses_only_the_current_calls_campaign_schema(): void
{
    [$agent, $company] = $this->tenantUser('agent');
    [, $otherCompany] = $this->tenantUser('agent');

    Campaign::create([
        'company_id' => $otherCompany->id,
        'name' => 'Campanha Externa',
        'dialing_mode' => 'power',
        'status' => 'active',
        'service_fields_schema' => [
            ['key' => 'documento', 'label' => 'CNPJ Externo', 'type' => 'cnpj', 'required' => true],
        ],
    ]);

    $list = ContactList::create([
        'company_id' => $company->id,
        'name' => 'Lista Isolada',
        'status' => 'active',
    ]);
    $contact = $list->contacts()->create([
        'company_id' => $company->id,
        'name' => 'Cliente Isolado',
        'phone' => '31999990077',
        'status' => 'dialing',
    ]);
    $campaign = Campaign::create([
        'company_id' => $company->id,
        'name' => 'Campanha Isolada',
        'dialing_mode' => 'power',
        'status' => 'active',
        'service_fields_schema' => [
            ['key' => 'documento', 'label' => 'Documento Local', 'type' => 'text', 'required' => false],
        ],
    ]);
    $disposition = Disposition::create([
        'company_id' => $company->id,
        'name' => 'Venda Isolada',
        'code' => 'VENDA_ISOLADA',
        'category' => 'success',
        'status' => 'active',
    ]);
    $call = Call::create([
        'company_id' => $company->id,
        'user_id' => $agent->id,
        'campaign_id' => $campaign->id,
        'contact_id' => $contact->id,
        'direction' => 'outbound',
        'status' => 'in_progress',
        'channel' => 'simulated',
        'phone' => $contact->phone,
        'started_at' => now(),
    ]);
    AgentStatus::create([
        'company_id' => $company->id,
        'user_id' => $agent->id,
        'status' => 'in_call',
        'started_at' => now(),
    ]);

    $this->actingAs($agent)
        ->patch(route('tenant.agent.calls.end'), [
            'call_id' => $call->id,
            'disposition_id' => $disposition->id,
            'service_fields' => ['documento' => 'ABC-123'],
        ])
        ->assertRedirect(route('tenant.agent.index'))
        ->assertSessionHas('status');

    $this->assertSame(['documento' => 'ABC-123'], $call->fresh()->service_fields);
}
```

- [ ] **Step 3: Run the focused tests and verify failure**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantAgentPanelTest.php --filter=service_field --no-coverage
```

Expected: FAIL because `AgentPanelController` only checks required fields and persists unnormalized strings.

- [ ] **Step 4: Inject and invoke the validator before call mutation**

Add:

```php
use App\Services\Campaigns\ServiceFieldValidator;
```

Add constructor injection to the controller:

```php
public function __construct(
    private readonly ServiceFieldValidator $serviceFieldValidator,
) {}
```

In `endCall()`, keep request shape validation:

```php
'service_fields' => ['nullable', 'array'],
'service_fields.*' => ['nullable', 'string', 'max:255'],
```

Remove the eager `$serviceFields` collection. After loading the tenant-scoped call and resolving its schema, use:

```php
$serviceFieldResult = $this->serviceFieldValidator->validate(
    $schema,
    $data['service_fields'] ?? [],
);

if (! $serviceFieldResult->passes()) {
    return back()->withErrors($serviceFieldResult->errors)->withInput();
}
```

Persist:

```php
'service_fields' => $serviceFieldResult->values ?: null,
```

Delete the private `serviceFieldErrors()` and `serviceFieldsPayload()` methods. Keep `serviceFieldsSchema()` because it owns the compatibility fallback.

- [ ] **Step 5: Run focused and regression tests**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantAgentPanelTest.php --filter="service_field|basic_service_fields" --no-coverage
```

Expected: all focused tests PASS. The basic fallback still stores `valor` as the normalized string `"199.90"`.

- [ ] **Step 6: Commit controller integration**

```powershell
git add app/Http/Controllers/Tenant/AgentPanelController.php tests/Feature/TenantAgentPanelTest.php
git commit -m "feat: validate service fields before ending calls"
```

### Task 6: Agent Inputs, Field Errors, and Reusable Masks

**Files:**
- Create: `resources/js/service-field-masks.js`
- Create: `tests/Frontend/service-field-masks.test.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/tenant/agent/index.blade.php`
- Modify: `tests/Feature/TenantAgentPanelTest.php`

- [ ] **Step 1: Write failing frontend formatter tests**

Create `tests/Frontend/service-field-masks.test.js`:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import {
    formatServiceField,
    serviceFieldMask,
} from '../../resources/js/service-field-masks.js';

test('formats cpf and cnpj progressively', () => {
    assert.equal(formatServiceField('cpf', '52998224725'), '529.982.247-25');
    assert.equal(formatServiceField('cnpj', '04252011000110'), '04.252.011/0001-10');
});

test('formats Brazilian landline and mobile numbers', () => {
    assert.equal(formatServiceField('phone', '3133334444'), '(31) 3333-4444');
    assert.equal(formatServiceField('phone', '31999990000'), '(31) 99999-0000');
});

test('preserves e164 input beginning with plus', () => {
    assert.equal(formatServiceField('phone', '+1 202-555-0123'), '+1 202-555-0123');
});

test('formats Brazilian currency', () => {
    assert.equal(formatServiceField('currency', '123456'), 'R$ 1.234,56');
    assert.equal(formatServiceField('currency', '1'), 'R$ 0,01');
});

test('alpine component formats the event target', () => {
    const component = serviceFieldMask('cpf');
    const event = { target: { value: '52998224725' } };

    component.format(event);

    assert.equal(event.target.value, '529.982.247-25');
});
```

- [ ] **Step 2: Run frontend tests and confirm module failure**

Run:

```powershell
npm run test:frontend
```

Expected: FAIL because `resources/js/service-field-masks.js` does not exist.

- [ ] **Step 3: Implement dependency-free visual formatters**

Create `resources/js/service-field-masks.js`:

```js
function digits(value, limit) {
    return String(value ?? '').replace(/\D/g, '').slice(0, limit);
}

function formatCpf(value) {
    const input = digits(value, 11);

    return input
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1-$2');
}

function formatCnpj(value) {
    const input = digits(value, 14);

    return input
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2')
        .replace(/(\d{4})(\d)/, '$1-$2');
}

function formatPhone(value) {
    const original = String(value ?? '');

    if (original.trim().startsWith('+')) {
        return original;
    }

    const input = digits(original, 11);

    if (input.length <= 10) {
        return input
            .replace(/^(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4})(\d)/, '$1-$2');
    }

    return input
        .replace(/^(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d)/, '$1-$2');
}

function formatCurrency(value) {
    const input = digits(value, 15);
    const cents = input.padStart(3, '0');
    const whole = cents.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `R$ ${whole},${cents.slice(-2)}`;
}

export function formatServiceField(type, value) {
    return {
        cpf: formatCpf,
        cnpj: formatCnpj,
        phone: formatPhone,
        currency: formatCurrency,
    }[type]?.(value) ?? value;
}

export function serviceFieldMask(type) {
    return {
        format(event) {
            event.target.value = formatServiceField(type, event.target.value);
        },
    };
}
```

- [ ] **Step 4: Export the Alpine component globally**

Modify `resources/js/app.js`:

```js
import Alpine from 'alpinejs';
import { toastStack } from './notifications';
import { pollingRegion } from './polling';
import { serviceFieldMask } from './service-field-masks';

window.Alpine = Alpine;
window.toastStack = toastStack;
window.pollingRegion = pollingRegion;
window.serviceFieldMask = serviceFieldMask;

Alpine.start();
```

- [ ] **Step 5: Run frontend tests**

Run:

```powershell
npm run test:frontend
```

Expected: all existing tests and the five new tests PASS.

- [ ] **Step 6: Add a failing Blade rendering assertion**

Extend the GET section of `test_agent_panel_uses_campaign_dynamic_service_fields`:

```php
->assertSee('name="service_fields[cpf_cliente]"', false)
->assertSee('inputmode="numeric"', false)
->assertSee('name="service_fields[telefone]"', false)
->assertSee('type="tel"', false)
->assertSee('name="service_fields[email]"', false)
->assertSee('autocomplete="email"', false)
->assertSee('name="service_fields[valor_oferta]"', false)
->assertSee("serviceFieldMask('currency')", false);
```

After the invalid PATCH, follow the redirect and assert the field-level message and old value:

```php
$this->get(route('tenant.agent.index'))
    ->assertOk()
    ->assertSee('CPF Cliente possui CPF invalido.')
    ->assertSee('111.111.111-11');
```

- [ ] **Step 7: Run the rendering test and confirm failure**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantAgentPanelTest.php --filter=campaign_dynamic_service_fields --no-coverage
```

Expected: FAIL because all fields still use the old text/number/date mapping and errors are not rendered beside inputs.

- [ ] **Step 8: Render type-specific attributes, masks, values, and errors**

Inside the Blade loop, define:

```blade
@php
    $fieldType = $field['type'] ?? 'text';
    $inputType = match ($fieldType) {
        'date' => 'date',
        'phone' => 'tel',
        'email' => 'email',
        default => 'text',
    };
    $inputMode = match ($fieldType) {
        'number', 'currency' => 'decimal',
        'cpf', 'cnpj' => 'numeric',
        default => null,
    };
    $masked = in_array($fieldType, ['cpf', 'cnpj', 'phone', 'currency'], true);
    $errorKey = 'service_fields.'.$field['key'];
@endphp
```

Render the input with:

```blade
<input
    type="{{ $inputType }}"
    name="service_fields[{{ $field['key'] }}]"
    value="{{ old('service_fields.'.$field['key']) }}"
    class="rounded-lg border px-3 py-2"
    style="border-color: var(--border); background: var(--surface); color: var(--fg);"
    @if ($inputMode) inputmode="{{ $inputMode }}" @endif
    @if ($fieldType === 'email') autocomplete="email" @endif
    @if ($masked)
        x-data="serviceFieldMask('{{ $fieldType }}')"
        x-on:input="format($event)"
    @endif
    @required($field['required'] ?? false)
>
@error($errorKey)
    <span class="text-xs" style="color: var(--status-danger);">{{ $message }}</span>
@enderror
```

Do not use `alert`, `confirm`, navigation, or a new modal. Laravel's existing redirect-with-input returns to the same agent screen and the existing modal state must continue to be controlled by the page's current validation behavior.

- [ ] **Step 9: Run focused backend, frontend, and build checks**

Run:

```powershell
docker compose exec -T app php artisan test tests/Feature/TenantAgentPanelTest.php --filter=service_field --no-coverage
npm run test:frontend
npm run build
```

Expected: focused PHP tests PASS, all frontend tests PASS, and Vite build exits 0.

- [ ] **Step 10: Commit the agent UI**

```powershell
git add resources/js/app.js resources/js/service-field-masks.js resources/views/tenant/agent/index.blade.php tests/Frontend/service-field-masks.test.js tests/Feature/TenantAgentPanelTest.php
git commit -m "feat: add service field masks and inline errors"
```

### Task 7: Documentation and Full Verification

**Files:**
- Modify: `docs/ROADMAP_CONCLUSAO.md`
- Verify: all files changed in Tasks 1-6

- [ ] **Step 1: Update the roadmap with implemented behavior**

Add a completed item under the campaign/agent section:

```markdown
- [x] Validacao avancada dos campos dinamicos de atendimento
  - Tipos: texto, numero, data, CPF, CNPJ, telefone, e-mail e moeda.
  - CPF/CNPJ com digitos verificadores; telefone BR/E.164; moeda em centavos.
  - Mascaras visuais no painel e validacao autoritativa no backend.
  - Erros exibidos no campo, sem `javascript.alert` e sem nova pagina.
```

- [ ] **Step 2: Run the full PHP test suite in Docker**

Run:

```powershell
docker compose up -d
docker compose exec -T app php artisan test --no-coverage
```

Expected: the entire suite PASS, with only explicitly documented skipped tests.

- [ ] **Step 3: Run PHP formatting**

Run:

```powershell
docker compose exec -T app ./vendor/bin/pint --test
```

Expected: exit code 0.

- [ ] **Step 4: Run all frontend tests and production build**

Run:

```powershell
npm run test:frontend
npm run build
```

Expected: all Node tests PASS and Vite build exits 0.

- [ ] **Step 5: Exercise the feature in the local browser**

Open `http://localhost:8087`, authenticate as a company administrator, and verify:

1. The campaign modal offers Texto, Numero, Data, CPF, CNPJ, Telefone, E-mail, and Moeda.
2. Saving a campaign with those fields does not navigate to a separate CRUD page.
3. An agent call renders the configured fields with the correct native input types and input modes.
4. CPF, CNPJ, Brazilian phone, and currency are formatted while typing.
5. Invalid values return inline errors and the call remains in progress.
6. Valid values end the call and no JavaScript alert appears.

Capture desktop and mobile screenshots if any Blade or CSS adjustment is required during verification.

- [ ] **Step 6: Review the final diff for scope and accidental artifacts**

Run:

```powershell
git status --short
git diff --check
git diff --stat
```

Expected: only planned files are changed, `git diff --check` prints nothing, and no generated `node_modules`, `vendor`, logs, screenshots, or build artifacts are staged.

- [ ] **Step 7: Commit roadmap and verification record**

```powershell
git add docs/ROADMAP_CONCLUSAO.md
git commit -m "docs: record typed service field validation"
```
