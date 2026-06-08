# Exportacao XLSX dos Relatorios Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir download XLSX do relatorio analitico com os mesmos filtros do CSV.

**Architecture:** `ReportController` ganha um metodo `exportXlsx` usando PhpSpreadsheet. A query filtrada e os labels ja existentes para duracao, espera e conversao sao reaproveitados para manter CSV e XLSX consistentes.

**Tech Stack:** Laravel, PhpSpreadsheet, PHPUnit, Docker Compose.

---

### Task 1: Teste Feature

**Files:**
- Modify: `tests/Feature/TenantReportsTest.php`

- [ ] **Step 1: Write failing test**

Criar chamadas de duas campanhas, baixar XLSX filtrado por campanha, ler a planilha com PhpSpreadsheet e validar cabecalho/conteudo.

- [ ] **Step 2: Verify RED**

Run: `php artisan test --filter=TenantReportsTest::test_reports_xlsx_export_respects_filters_and_time_columns --no-coverage`

Expected: FAIL porque a rota XLSX ainda nao existe.

### Task 2: Backend

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Tenant/ReportController.php`

- [ ] **Step 1: Add route**

Adicionar `tenant.reports.export-xlsx`.

- [ ] **Step 2: Add XLSX method**

Criar `exportXlsx` com PhpSpreadsheet, cabecalho, autosize simples e download.

### Task 3: View and Docs

**Files:**
- Modify: `resources/views/tenant/reports/index.blade.php`
- Modify: `docs/ROADMAP_CONCLUSAO.md`

- [ ] **Step 1: Add button**

Adicionar link "Exportar XLSX" ao lado do CSV.

- [ ] **Step 2: Update roadmap**

Marcar XLSX concluido e sugerir PDF/historico como proximo passo.

### Task 4: Verification

Run:
- `vendor\bin\pint.bat --dirty`
- `php artisan test --filter=TenantReportsTest --no-coverage`
- `php artisan test --no-coverage`
- `npm run build`
- `docker compose exec -T app php artisan test --no-coverage`

Commit: `feat: add xlsx report export`
