# Conversao por Campanha, Equipe e Agente Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adicionar taxa de conversao geral e por agente, campanha e equipe aos relatorios.

**Architecture:** O `ReportController` reutiliza a query filtrada de chamadas. A conversao usa a relacao `calls.disposition_id -> dispositions.is_success`, sem criar novas tabelas.

**Tech Stack:** Laravel, Blade, PHPUnit, Docker Compose.

---

### Task 1: Teste Feature

**Files:**
- Modify: `tests/Feature/TenantReportsTest.php`

- [ ] **Step 1: Write failing test**

Criar chamadas encerradas com tabulacao de sucesso e nao sucesso, vinculadas a agente, campanha e equipe. Validar cards gerais, taxas nos blocos de performance e CSV com coluna `conversao`.

- [ ] **Step 2: Verify RED**

Run: `php artisan test --filter=TenantReportsTest::test_reports_show_conversion_by_campaign_team_and_agent --no-coverage`

Expected: FAIL porque a tela ainda nao mostra conversao.

### Task 2: Controller

**Files:**
- Modify: `app/Http/Controllers/Tenant/ReportController.php`

- [ ] **Step 1: Add conversion helpers**

Criar helpers para contar chamadas encerradas com tabulacao, conversoes e formatar percentual.

- [ ] **Step 2: Add metrics to scopes**

Adicionar conversoes/taxa ao geral, agente, campanha e equipe.

- [ ] **Step 3: Add CSV flag**

Adicionar coluna `conversao` com `sim`/`nao`.

### Task 3: View

**Files:**
- Modify: `resources/views/tenant/reports/index.blade.php`

- [ ] **Step 1: Add cards**

Exibir `Conversoes` e `Taxa conversao`.

- [ ] **Step 2: Add scoped labels**

Exibir conversao/taxa em agente, campanha e equipe.

### Task 4: Docs and Verification

**Files:**
- Modify: `docs/ROADMAP_CONCLUSAO.md`

- [ ] **Step 1: Mark conversion done**

Atualizar roadmap e proximo plano recomendado.

- [ ] **Step 2: Verify**

Run:
- `vendor\bin\pint.bat --dirty`
- `php artisan test --filter=TenantReportsTest --no-coverage`
- `php artisan test --no-coverage`
- `npm run build`
- `docker compose exec -T app php artisan test --no-coverage`

- [ ] **Step 3: Commit**

Commit: `feat: add report conversion metrics`
