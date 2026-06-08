# Historico de Exportacoes dos Relatorios Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Registrar e exibir historico recente de exportacoes CSV, XLSX e PDF.

**Architecture:** Criar `ReportExport` como modelo tenant-scoped. `ReportController` registra a exportacao antes de iniciar o stream e envia os registros recentes para a view.

**Tech Stack:** Laravel, Eloquent, PHPUnit, Docker Compose.

---

### Task 1: Teste Feature

**Files:**
- Modify: `tests/Feature/TenantReportsTest.php`

- [ ] **Step 1: Write failing test**

Exportar CSV, XLSX e PDF, verificar registros em `report_exports`, filtros salvos e historico exibido na tela sem vazar outra empresa.

- [ ] **Step 2: Verify RED**

Run: `php artisan test --filter=TenantReportsTest::test_reports_register_and_show_export_history --no-coverage`

Expected: FAIL porque a tabela/model ainda nao existem.

### Task 2: Data Model

**Files:**
- Create: `database/migrations/2026_06_08_000004_create_report_exports_table.php`
- Create: `app/Models/ReportExport.php`
- Modify: `app/Models/Company.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Add migration/model**

Criar campos `company_id`, `user_id`, `format`, `filters`, `records_count`.

### Task 3: Controller and View

**Files:**
- Modify: `app/Http/Controllers/Tenant/ReportController.php`
- Modify: `resources/views/tenant/reports/index.blade.php`

- [ ] **Step 1: Register exports**

Registrar CSV/XLSX/PDF com filtros atuais e quantidade de registros.

- [ ] **Step 2: Show history**

Adicionar card de historico recente na tela de relatorios.

### Task 4: Docs and Verification

**Files:**
- Modify: `docs/ROADMAP_CONCLUSAO.md`

- [ ] **Step 1: Mark history done**

Atualizar roadmap e sugerir agendamento de relatorios.

- [ ] **Step 2: Verify**

Run:
- `vendor\bin\pint.bat --dirty`
- `php artisan test --filter=TenantReportsTest --no-coverage`
- `php artisan test --no-coverage`
- `npm run build`
- `docker compose exec -T app php artisan test --no-coverage`

Commit: `feat: add report export history`
