# Report Scheduling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adicionar agendamentos diarios e semanais de relatorios, com filtros tenant-aware, CRUD modal e execucao simulada registrada no historico de exportacoes.

**Architecture:** Um modelo `ReportSchedule` armazena configuracao e estado por empresa. Um controller tenant cuida de validacao e CRUD, enquanto `SimulatedReportScheduleRunner` calcula o periodo relativo, consulta chamadas com os filtros salvos e registra a execucao em `report_exports`. O painel existente de Relatorios carrega e opera os agendamentos sem criar uma pagina separada.

**Tech Stack:** PHP 8.3, Laravel, Eloquent, Blade, Alpine.js, Tailwind CSS, PHPUnit, MariaDB, Docker Compose.

---

## File Structure

- Create: `database/migrations/2026_06_13_000001_create_report_schedules_table.php`
  Persistencia tenant-aware dos agendamentos e da ultima execucao.
- Create: `app/Models/ReportSchedule.php`
  Modelo, casts e relacionamentos.
- Create: `app/Services/Reports/SimulatedReportScheduleRunner.php`
  Calculo do periodo, aplicacao de filtros, contagem e registro da execucao.
- Create: `app/Http/Controllers/Tenant/ReportScheduleController.php`
  Validacao, CRUD, alternancia de status e comando de execucao.
- Create: `resources/views/tenant/reports/_schedule-form.blade.php`
  Formulario reutilizado pelos modais de criacao e edicao.
- Create: `tests/Feature/TenantReportScheduleTest.php`
  Contrato funcional do modulo, isolamento SaaS e execucao simulada.
- Modify: `app/Models/Company.php`
  Relacionamento `reportSchedules`.
- Modify: `app/Models/User.php`
  Relacionamento `reportSchedules`.
- Modify: `app/Http/Controllers/Tenant/ReportController.php`
  Carregamento e rotulos dos agendamentos no painel.
- Modify: `routes/web.php`
  Rotas tenant de cadastro, atualizacao, status e execucao.
- Modify: `resources/views/tenant/reports/index.blade.php`
  Bloco visual, comandos e modais.
- Modify: `docs/ROADMAP_CONCLUSAO.md`
  Conclusao do item e definicao do proximo modulo.

### Task 1: Persistencia e relacionamentos

**Files:**
- Create: `database/migrations/2026_06_13_000001_create_report_schedules_table.php`
- Create: `app/Models/ReportSchedule.php`
- Modify: `app/Models/Company.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/TenantReportScheduleTest.php`

- [ ] **Step 1: Write the failing persistence test**

Criar `TenantReportScheduleTest` com `RefreshDatabase` e um helper tenant equivalente ao usado em `TenantReportsTest`. O primeiro teste deve criar empresa, admin e o modelo pela relacao:

```php
public function test_company_and_user_own_report_schedules(): void
{
    [$admin, $company] = $this->tenantUser();

    $schedule = $company->reportSchedules()->create([
        'user_id' => $admin->id,
        'name' => 'Resumo diario',
        'format' => 'xlsx',
        'frequency' => 'daily',
        'run_time' => '08:30',
        'filters' => ['period_days' => 30],
        'status' => 'active',
    ]);

    $this->assertTrue($admin->reportSchedules->contains($schedule));
    $this->assertSame(30, $schedule->filters['period_days']);
    $this->assertNull($schedule->last_run_at);
}
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
php artisan test --filter=TenantReportScheduleTest::test_company_and_user_own_report_schedules --no-coverage
```

Expected: FAIL because `report_schedules`, the model and relationships do not exist.

- [ ] **Step 3: Implement the migration and model**

Migration columns:

```php
Schema::create('report_schedules', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('format', 12);
    $table->string('frequency', 12);
    $table->unsignedTinyInteger('weekday')->nullable();
    $table->time('run_time');
    $table->json('filters')->nullable();
    $table->string('status', 12)->default('active');
    $table->timestamp('last_run_at')->nullable();
    $table->unsignedInteger('last_records_count')->nullable();
    $table->timestamps();
    $table->index(['company_id', 'status']);
    $table->index(['company_id', 'frequency', 'run_time']);
});
```

Modelo:

```php
#[Fillable([
    'company_id',
    'user_id',
    'name',
    'format',
    'frequency',
    'weekday',
    'run_time',
    'filters',
    'status',
    'last_run_at',
    'last_records_count',
])]
class ReportSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'weekday' => 'integer',
            'last_run_at' => 'datetime',
            'last_records_count' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Adicionar `reportSchedules(): HasMany` em `Company` e `User`.

- [ ] **Step 4: Run the test and verify GREEN**

Run:

```powershell
php artisan test --filter=TenantReportScheduleTest::test_company_and_user_own_report_schedules --no-coverage
```

Expected: PASS.

- [ ] **Step 5: Commit persistence**

```powershell
git add database/migrations/2026_06_13_000001_create_report_schedules_table.php app/Models/ReportSchedule.php app/Models/Company.php app/Models/User.php tests/Feature/TenantReportScheduleTest.php
git commit -m "feat: add report schedule model"
```

### Task 2: CRUD, validacao e isolamento SaaS

**Files:**
- Create: `app/Http/Controllers/Tenant/ReportScheduleController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TenantReportScheduleTest.php`

- [ ] **Step 1: Write failing CRUD tests**

Adicionar testes separados para:

```php
public function test_admin_can_create_daily_and_weekly_report_schedules(): void
```

Enviar um cadastro diario sem `weekday` e um semanal com `weekday = 1`; verificar redirect para `tenant.reports.index`, mensagem na sessao e registros no banco com filtros:

```php
'filters' => [
    'period_days' => 30,
    'campaign_id' => $campaign->id,
]
```

Adicionar:

```php
public function test_weekly_schedule_requires_weekday_and_filter_ids_from_same_company(): void
```

Verificar erros em `weekday` e `campaign_id` quando o ID pertence a outra empresa.

Adicionar:

```php
public function test_admin_can_update_and_toggle_own_schedule_but_not_another_company_schedule(): void
```

Verificar atualizacao, alternancia `active`/`inactive` e resposta 404 para agendamento de outra empresa.

- [ ] **Step 2: Run CRUD tests and verify RED**

Run:

```powershell
php artisan test --filter=TenantReportScheduleTest --no-coverage
```

Expected: os novos testes falham porque controller e rotas ainda nao existem.

- [ ] **Step 3: Add tenant routes**

Importar `ReportScheduleController` e adicionar:

```php
Route::post('reports/schedules', [ReportScheduleController::class, 'store'])->name('reports.schedules.store');
Route::put('reports/schedules/{reportSchedule}', [ReportScheduleController::class, 'update'])->name('reports.schedules.update');
Route::patch('reports/schedules/{reportSchedule}/status', [ReportScheduleController::class, 'toggleStatus'])->name('reports.schedules.status');
Route::post('reports/schedules/{reportSchedule}/run', [ReportScheduleController::class, 'run'])->name('reports.schedules.run');
```

- [ ] **Step 4: Implement validation and CRUD**

O controller deve:

```php
private function authorizeTenant(Request $request, ReportSchedule $reportSchedule): void
{
    abort_unless($reportSchedule->company_id === $request->user()->company_id, 404);
}
```

Usar regras:

```php
[
    'name' => ['required', 'string', 'max:255'],
    'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])],
    'frequency' => ['required', Rule::in(['daily', 'weekly'])],
    'weekday' => ['nullable', 'required_if:frequency,weekly', 'integer', 'between:0,6'],
    'run_time' => ['required', 'date_format:H:i'],
    'period_days' => ['required', 'integer', Rule::in([7, 15, 30, 60, 90])],
    'status' => ['required', Rule::in(['active', 'inactive'])],
    'user_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
    'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('company_id', $companyId)],
    'disposition_id' => ['nullable', Rule::exists('dispositions', 'id')->where('company_id', $companyId)],
    'team_id' => ['nullable', Rule::exists('teams', 'id')->where('company_id', $companyId)],
    'list_id' => ['nullable', Rule::exists('contact_lists', 'id')->where('company_id', $companyId)],
]
```

Persistir os cinco IDs e `period_days` em `filters`, removendo somente valores nulos ou vazios. Forcar `weekday = null` em agendamento diario. `toggleStatus` alterna entre `active` e `inactive`. Todos os redirects voltam a `tenant.reports.index` com `status` para o toast.

- [ ] **Step 5: Run CRUD tests and verify GREEN**

Run:

```powershell
php artisan test --filter=TenantReportScheduleTest --no-coverage
```

Expected: todos os testes de persistencia, CRUD, validacao e isolamento passam.

- [ ] **Step 6: Commit CRUD**

```powershell
git add app/Http/Controllers/Tenant/ReportScheduleController.php routes/web.php tests/Feature/TenantReportScheduleTest.php
git commit -m "feat: add report schedule management"
```

### Task 3: Execucao simulada e historico

**Files:**
- Create: `app/Services/Reports/SimulatedReportScheduleRunner.php`
- Modify: `app/Http/Controllers/Tenant/ReportScheduleController.php`
- Test: `tests/Feature/TenantReportScheduleTest.php`

- [ ] **Step 1: Write the failing execution tests**

Criar:

```php
public function test_active_schedule_run_counts_filtered_calls_and_registers_export_history(): void
```

Fixar o relogio com `Carbon::setTestNow('2026-06-13 10:00:00')`. Criar chamadas dentro e fora dos ultimos 30 dias, de campanhas diferentes e de outra empresa. Executar a rota `tenant.reports.schedules.run` e verificar:

```php
$schedule->refresh();
$this->assertSame(1, $schedule->last_records_count);
$this->assertTrue($schedule->last_run_at->equalTo(now()));

$this->assertDatabaseHas('report_exports', [
    'company_id' => $company->id,
    'user_id' => $admin->id,
    'format' => 'xlsx',
    'records_count' => 1,
]);
```

Verificar tambem que o JSON do historico possui `campaign_id`, `date_from`, `date_to` e `schedule_id`.

Criar:

```php
public function test_inactive_schedule_cannot_run(): void
```

Esperar redirect com erro `schedule` e ausencia de alteracao em `last_run_at` e `report_exports`.

- [ ] **Step 2: Run execution tests and verify RED**

Run:

```powershell
php artisan test --filter=TenantReportScheduleTest --no-coverage
```

Expected: FAIL porque o runner e a execucao ainda nao existem.

- [ ] **Step 3: Implement the simulated runner**

Criar uma classe invocavel:

```php
final class SimulatedReportScheduleRunner
{
    public function run(ReportSchedule $schedule, User $actor): int
    {
        $filters = $schedule->filters ?? [];
        $dateTo = now()->endOfDay();
        $dateFrom = now()->subDays(((int) $filters['period_days']) - 1)->startOfDay();

        $query = Call::query()
            ->where('company_id', $schedule->company_id)
            ->whereBetween('started_at', [$dateFrom, $dateTo])
            ->when($filters['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when($filters['campaign_id'] ?? null, fn ($query, $id) => $query->where('campaign_id', $id))
            ->when($filters['disposition_id'] ?? null, fn ($query, $id) => $query->where('disposition_id', $id));
```

Aplicar `team_id` com a mesma subconsulta de `team_user` e `list_id` com a mesma subconsulta de `contacts` utilizadas por `ReportController`.

Contar o resultado, atualizar o agendamento e criar:

```php
ReportExport::create([
    'company_id' => $schedule->company_id,
    'user_id' => $actor->id,
    'format' => $schedule->format,
    'filters' => array_filter([
        'date_from' => $dateFrom->toDateString(),
        'date_to' => $dateTo->toDateString(),
        'user_id' => $filters['user_id'] ?? null,
        'campaign_id' => $filters['campaign_id'] ?? null,
        'disposition_id' => $filters['disposition_id'] ?? null,
        'team_id' => $filters['team_id'] ?? null,
        'list_id' => $filters['list_id'] ?? null,
        'schedule_id' => $schedule->id,
    ], fn ($value) => $value !== null && $value !== ''),
    'records_count' => $recordsCount,
]);
```

- [ ] **Step 4: Connect the controller run action**

Injetar `SimulatedReportScheduleRunner`, autorizar tenant, impedir status diferente de `active` com:

```php
throw ValidationException::withMessages([
    'schedule' => 'Ative o agendamento antes de executar.',
]);
```

Executar e retornar toast:

```php
return redirect()
    ->route('tenant.reports.index')
    ->with('status', sprintf('Agendamento executado. %d registros encontrados.', $recordsCount));
```

- [ ] **Step 5: Run execution tests and verify GREEN**

Run:

```powershell
php artisan test --filter=TenantReportScheduleTest --no-coverage
```

Expected: todos os testes passam.

- [ ] **Step 6: Commit execution**

```powershell
git add app/Services/Reports/SimulatedReportScheduleRunner.php app/Http/Controllers/Tenant/ReportScheduleController.php tests/Feature/TenantReportScheduleTest.php
git commit -m "feat: run simulated report schedules"
```

### Task 4: Painel modal-first

**Files:**
- Modify: `app/Http/Controllers/Tenant/ReportController.php`
- Create: `resources/views/tenant/reports/_schedule-form.blade.php`
- Modify: `resources/views/tenant/reports/index.blade.php`
- Test: `tests/Feature/TenantReportScheduleTest.php`

- [ ] **Step 1: Write the failing presentation test**

Criar:

```php
public function test_reports_panel_shows_only_company_schedules_and_modal_controls(): void
```

Criar agendamentos de duas empresas. Acessar `tenant.reports.index` e verificar:

```php
->assertSee('Agendamentos de relatorios')
->assertSee('Novo agendamento')
->assertSee('Resumo diario')
->assertSee('Executar agora')
->assertSee('Editar')
->assertSee('Inativar')
->assertDontSee('Rotina outra empresa');
```

Tambem verificar a presenca dos campos `period_days`, `format`, `frequency`, `run_time` e do modal de edicao pelo nome `report-schedule-edit-{id}`.

- [ ] **Step 2: Run presentation test and verify RED**

Run:

```powershell
php artisan test --filter=TenantReportScheduleTest::test_reports_panel_shows_only_company_schedules_and_modal_controls --no-coverage
```

Expected: FAIL porque o painel ainda nao apresenta agendamentos.

- [ ] **Step 3: Load schedules and presentation labels**

No `ReportController@index`, carregar:

```php
'reportSchedules' => ReportSchedule::query()
    ->where('company_id', $companyId)
    ->with('user')
    ->latest()
    ->get()
    ->each(fn (ReportSchedule $schedule) => $this->withScheduleLabels($schedule, $companyId)),
```

O helper deve definir:

- `frequency_label`: `Diario` ou `Semanal - Segunda-feira`;
- `filter_label`: periodo e nomes de filtros da mesma empresa;
- `last_run_label`: `Nunca executado` ou data/hora com quantidade;
- `status_label`: `Ativo` ou `Inativo`.

- [ ] **Step 4: Build reusable form and modals**

O partial deve receber `$schedule`, `$agents`, `$campaigns`, `$teams`, `$dispositions` e `$lists`. Usar Alpine no agrupamento:

```html
<div x-data="{ frequency: @js(old('frequency', $schedule?->frequency ?? 'daily')) }">
```

O campo de dia da semana usa `x-show="frequency === 'weekly'"`. Todos os campos devem ter labels visiveis e estilos do design system atual.

No `index.blade.php`:

- adicionar botao `Novo agendamento`;
- listar cards compactos sem cards aninhados;
- abrir criacao em `report-schedule-create`;
- abrir cada edicao em `report-schedule-edit-{id}`;
- usar forms POST/PUT/PATCH para executar, editar e alternar status;
- desabilitar visualmente `Executar agora` quando inativo;
- nao usar `alert`, `confirm` ou navegacao para outra pagina.

- [ ] **Step 5: Run presentation and report suites**

Run:

```powershell
php artisan test --filter=TenantReportScheduleTest --no-coverage
php artisan test --filter=TenantReportsTest --no-coverage
```

Expected: ambas as suites passam.

- [ ] **Step 6: Commit interface**

```powershell
git add app/Http/Controllers/Tenant/ReportController.php resources/views/tenant/reports/_schedule-form.blade.php resources/views/tenant/reports/index.blade.php tests/Feature/TenantReportScheduleTest.php
git commit -m "feat: add report scheduling panel"
```

### Task 5: Roadmap e verificacao integral

**Files:**
- Modify: `docs/ROADMAP_CONCLUSAO.md`

- [ ] **Step 1: Update roadmap**

Atualizar a data para `2026-06-13`, marcar:

```markdown
- [x] Agendamento de relatorios.
```

Registrar que o MVP usa execucao simulada manual e manter Scheduler/envio como evolucao futura. Definir como proximo plano recomendado `Atualizacao em tempo real da supervisao e notificacoes por polling leve`, pois telefonia e integracoes continuam `Em breve`.

- [ ] **Step 2: Run formatter**

```powershell
vendor\bin\pint.bat --dirty
```

Expected: exit code 0.

- [ ] **Step 3: Run complete local verification**

```powershell
php artisan test --no-coverage
npm run build
git diff --check
```

Expected: todos os testes passam, build Vite conclui e `git diff --check` nao apresenta saida.

- [ ] **Step 4: Run Docker verification**

```powershell
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan test --no-coverage
```

Smoke:

```powershell
$response = Invoke-WebRequest -UseBasicParsing http://localhost:8087/login
"STATUS=$($response.StatusCode)"
```

Expected: migrations aplicadas, suite Docker passa e `STATUS=200`.

- [ ] **Step 5: Commit documentation and formatting**

```powershell
git add docs/ROADMAP_CONCLUSAO.md
git add -u
git commit -m "docs: complete report scheduling roadmap"
```

- [ ] **Step 6: Confirm final state**

```powershell
git status --short
git log -5 --oneline
```

Expected: arvore limpa e commits do modulo visiveis.
