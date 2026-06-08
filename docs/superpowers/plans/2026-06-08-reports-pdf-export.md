# Exportacao PDF dos Relatorios Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir download PDF do relatorio com resumo executivo e amostra analitica filtrada.

**Architecture:** Criar um gerador PDF simples em `app/Services/Reports/SimplePdfReport.php` para evitar dependencia nova. O `ReportController` monta linhas de resumo e amostra a partir da query filtrada.

**Tech Stack:** Laravel, PHPUnit, gerador PDF interno simples, Docker Compose.

---

### Task 1: Teste Feature

**Files:**
- Modify: `tests/Feature/TenantReportsTest.php`

- [ ] **Step 1: Write failing test**

Criar chamadas de duas campanhas, baixar PDF filtrado por campanha e validar assinatura `%PDF`, resumo, campanha correta e ausencia da outra campanha.

- [ ] **Step 2: Verify RED**

Run: `php artisan test --filter=TenantReportsTest::test_reports_pdf_export_respects_filters_and_summary --no-coverage`

Expected: FAIL porque a rota PDF ainda nao existe.

### Task 2: Backend

**Files:**
- Create: `app/Services/Reports/SimplePdfReport.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Tenant/ReportController.php`

- [ ] **Step 1: Add PDF generator**

Criar gerador PDF basico com texto em Helvetica.

- [ ] **Step 2: Add route and controller method**

Adicionar `tenant.reports.export-pdf` e metodo `exportPdf`.

### Task 3: View and Docs

**Files:**
- Modify: `resources/views/tenant/reports/index.blade.php`
- Modify: `docs/ROADMAP_CONCLUSAO.md`

- [ ] **Step 1: Add button**

Adicionar link "Exportar PDF" ao lado de CSV/XLSX.

- [ ] **Step 2: Update roadmap**

Marcar PDF concluido e sugerir historico de exportacoes como proximo plano.

### Task 4: Verification

Run:
- `vendor\bin\pint.bat --dirty`
- `php artisan test --filter=TenantReportsTest --no-coverage`
- `php artisan test --no-coverage`
- `npm run build`
- `docker compose exec -T app php artisan test --no-coverage`

Commit: `feat: add pdf report export`
