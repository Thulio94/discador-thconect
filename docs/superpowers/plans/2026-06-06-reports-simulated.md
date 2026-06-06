# Relatorios Simulados Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first real tenant reports page with simulated operational KPIs, filters, and summaries.

**Architecture:** Use existing tenant-scoped operational tables. `ReportController` prepares call metrics, agent status metrics, recent calls, agent performance, campaign performance, and list summaries for a Blade dashboard.

**Tech Stack:** Laravel, Blade, Tailwind/THCONECT components, PHPUnit, Docker Compose.

---

## File Structure

- Create `app/Http/Controllers/Tenant/ReportController.php`.
- Create `resources/views/tenant/reports/index.blade.php`.
- Modify `routes/web.php` to replace the coming-soon reports route.
- Modify `resources/views/layouts/app.blade.php` to remove the "Em breve" badge from Relatorios.
- Modify `tests/Feature/CoreRoutesTest.php` because Relatorios is no longer reserved.
- Create `tests/Feature/TenantReportsTest.php`.

---

### Task 1: Reports Route and Tenant Isolation

- [ ] **Step 1: Write failing route test**

Create `tests/Feature/TenantReportsTest.php` proving `tenant.reports.index` returns 200, shows `Relatorios`, does not show `Modulo em breve`, shows own tenant call data, and hides another tenant call data.

- [ ] **Step 2: Run failing test**

Run: `php artisan test tests\Feature\TenantReportsTest.php --no-coverage`

Expected: fail because reports still points to coming-soon.

- [ ] **Step 3: Implement controller, route, and minimal view**

Create `ReportController@index`, query calls by authenticated user's `company_id`, return `tenant.reports.index`, and replace the route.

- [ ] **Step 4: Run focused test**

Run: `php artisan test tests\Feature\TenantReportsTest.php --no-coverage`

Expected: route test passes.

---

### Task 2: Metrics and Filters

- [ ] **Step 1: Write failing metrics/filter tests**

Add tests for totals, in-progress calls, ended calls, agent status counters, agent filter, and campaign filter.

- [ ] **Step 2: Run failing test**

Run: `php artisan test tests\Feature\TenantReportsTest.php --no-coverage`

Expected: fail until metrics and filters are rendered.

- [ ] **Step 3: Implement metrics and filters**

Use `started_at` date range. Default to last 30 days. Apply `user_id` and `campaign_id` filters when present. Keep all queries tenant-scoped.

- [ ] **Step 4: Run focused test**

Run: `php artisan test tests\Feature\TenantReportsTest.php --no-coverage`

Expected: metrics/filter tests pass.

---

### Task 3: Reports UI

- [ ] **Step 1: Write failing UI assertions**

Assert page contains `Chamadas totais`, `Em andamento`, `Encerradas`, `Performance por agente`, `Performance por campanha`, `Resumo de listas`, `Ultimas chamadas`, and `Exportar - Em breve`.

- [ ] **Step 2: Run failing test**

Run: `php artisan test tests\Feature\TenantReportsTest.php --no-coverage`

Expected: fail until view is complete.

- [ ] **Step 3: Implement Blade UI**

Create filters, KPI cards, summary tables, recent calls table, and export badge. Do not use native alert/confirm.

- [ ] **Step 4: Run focused test**

Run: `php artisan test tests\Feature\TenantReportsTest.php --no-coverage`

Expected: UI assertions pass.

---

### Task 4: Full Verification and Commit

- [ ] **Step 1: Run Laravel tests**

Run: `php artisan test --no-coverage`

Expected: all tests pass.

- [ ] **Step 2: Run frontend build**

Run: `npm run build`

Expected: Vite build succeeds.

- [ ] **Step 3: Run Docker tests**

Run: `docker compose exec -T app php artisan test --no-coverage`

Expected: all tests pass in Docker.

- [ ] **Step 4: Smoke test HTTP**

Log in to `http://localhost:8087` as `admin.demo@discador.local` / `password` and request `/tenant/reports`.

Expected: status 200, contains `Relatorios`, `Chamadas totais`, and does not contain `Modulo em breve`.

- [ ] **Step 5: Commit**

Run:

```bash
git add .
git commit -m "feat: add simulated reports dashboard"
```

Expected: commit succeeds.

---

## Self-Review

- Spec coverage: route, tenant isolation, metrics, filters, UI, and export placeholder are covered.
- Placeholder scan: no `TBD`, `TODO`, or vague implementation steps remain.
- Type consistency: report route is `tenant.reports.index`; filters are `date_from`, `date_to`, `user_id`, and `campaign_id`.
