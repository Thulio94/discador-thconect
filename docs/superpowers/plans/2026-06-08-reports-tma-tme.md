# TMA e TME nos Relatorios Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adicionar TMA, TME, duracao e espera aos relatorios do Discador THCONECT.

**Architecture:** O `ReportController` continua centralizando filtros e queries de relatorios. Os calculos de tempo serao feitos em PHP sobre clones da query filtrada para evitar SQL especifico de banco e manter compatibilidade SQLite/MySQL.

**Tech Stack:** Laravel, Blade, PHPUnit, Docker Compose.

---

### Task 1: Teste Feature

**Files:**
- Modify: `tests/Feature/TenantReportsTest.php`

- [ ] **Step 1: Write the failing test**

Adicionar um teste que cria chamadas encerradas com `answered_at`, valida cards `TMA`/`TME`, medias por agente/campanha, duracao/espera no analitico e colunas CSV.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TenantReportsTest::test_reports_show_tma_tme_and_export_time_metrics --no-coverage`

Expected: FAIL porque a tela ainda nao mostra TMA/TME.

### Task 2: Controller

**Files:**
- Modify: `app/Http/Controllers/Tenant/ReportController.php`

- [ ] **Step 1: Add time metric helpers**

Criar helpers para formatar segundos, calcular media de atendimento/espera e aplicar medias nas colecoes de agentes/campanhas.

- [ ] **Step 2: Wire metrics and CSV**

Adicionar `avg_talk_time_label`, `avg_wait_time_label`, `talk_time_label` e `wait_time_label` aos dados enviados para a view/CSV.

### Task 3: View

**Files:**
- Modify: `resources/views/tenant/reports/index.blade.php`

- [ ] **Step 1: Add cards**

Exibir TMA e TME no bloco de metricas.

- [ ] **Step 2: Add performance and analytic labels**

Exibir TMA por agente/campanha e coluna de espera no analitico.

### Task 4: Docs and Verification

**Files:**
- Modify: `docs/ROADMAP_CONCLUSAO.md`

- [ ] **Step 1: Mark TMA/TME done**

Atualizar roadmap e proximo plano recomendado.

- [ ] **Step 2: Verify**

Run:
- `vendor\bin\pint.bat --dirty`
- `php artisan test --filter=TenantReportsTest --no-coverage`
- `php artisan test --no-coverage`
- `npm run build`
- `docker compose exec -T app php artisan test --no-coverage`

- [ ] **Step 3: Commit**

Commit: `feat: add report time metrics`
