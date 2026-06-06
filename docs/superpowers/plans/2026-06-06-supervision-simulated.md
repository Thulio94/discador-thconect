# Supervisao Simulada Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first real tenant supervision page with operational counters, agent grid, filters, and simulated call visibility.

**Architecture:** Use existing `agent_statuses`, `calls`, users, and teams. A focused `SupervisionController` prepares tenant-scoped metrics and grid data; Blade renders a dense dashboard with modal details and future telephony actions marked as "Em breve".

**Tech Stack:** Laravel, Blade, Alpine, Tailwind/THCONECT components, PHPUnit, Docker Compose.

---

## File Structure

- Create `app/Http/Controllers/Tenant/SupervisionController.php`.
- Create `resources/views/tenant/supervision/index.blade.php`.
- Modify `routes/web.php` to replace the coming-soon supervision route.
- Modify `resources/views/layouts/app.blade.php` to remove the "Em breve" badge from Supervisao.
- Modify `tests/Feature/CoreRoutesTest.php` because Supervisao is no longer reserved.
- Create `tests/Feature/TenantSupervisionTest.php`.

---

### Task 1: Supervision Route and Tenant Query

**Files:**
- Create: `tests/Feature/TenantSupervisionTest.php`
- Create: `app/Http/Controllers/Tenant/SupervisionController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/CoreRoutesTest.php`

- [ ] **Step 1: Write failing route tests**

Add tests that `tenant.supervision.index` returns 200, shows `Supervisao`, does not show `Modulo em breve`, and excludes users from another company.

- [ ] **Step 2: Run failing test**

Run: `php artisan test tests\Feature\TenantSupervisionTest.php --no-coverage`

Expected: fail because supervision still points to coming-soon.

- [ ] **Step 3: Implement controller and route**

Create `SupervisionController@index`, query tenant users of type `agent` and `supervisor`, eager-load current status, teams, and active calls, then return `tenant.supervision.index`.

- [ ] **Step 4: Run focused test**

Run: `php artisan test tests\Feature\TenantSupervisionTest.php --no-coverage`

Expected: route tests pass.

---

### Task 2: Metrics and Filters

**Files:**
- Modify: `app/Http/Controllers/Tenant/SupervisionController.php`
- Modify: `resources/views/tenant/supervision/index.blade.php`
- Test: `tests/Feature/TenantSupervisionTest.php`

- [ ] **Step 1: Write failing metrics/filter tests**

Add tests for counters: available, paused, in_call, offline, active calls. Add tests for `?status=paused` and `?team_id=...`.

- [ ] **Step 2: Run failing test**

Run: `php artisan test tests\Feature\TenantSupervisionTest.php --no-coverage`

Expected: fail until metrics/filter logic exists.

- [ ] **Step 3: Implement metrics and filters**

Compute status values from `currentAgentStatus`, defaulting to `offline`. Apply status and team filters. Keep all queries constrained by `company_id`.

- [ ] **Step 4: Run focused test**

Run: `php artisan test tests\Feature\TenantSupervisionTest.php --no-coverage`

Expected: metrics and filters pass.

---

### Task 3: Supervision UI

**Files:**
- Create: `resources/views/tenant/supervision/index.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/TenantSupervisionTest.php`

- [ ] **Step 1: Write failing UI assertions**

Assert the page contains `Agentes em operacao`, `Disponiveis`, `Em pausa`, `Em chamada`, `Offline`, `Chamadas ativas`, `supervision-agent-`, and future action labels `Ouvir`, `Sussurrar`, `Intervir`.

- [ ] **Step 2: Run failing test**

Run: `php artisan test tests\Feature\TenantSupervisionTest.php --no-coverage`

Expected: fail until the view is complete.

- [ ] **Step 3: Implement Blade UI**

Create cards, filters, agent rows, modal details, and future action badges. Do not use native alert/confirm.

- [ ] **Step 4: Run focused test**

Run: `php artisan test tests\Feature\TenantSupervisionTest.php --no-coverage`

Expected: UI assertions pass.

---

### Task 4: Full Verification and Commit

**Files:**
- All files from tasks 1-3.

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

Log in to `http://localhost:8087` as `admin.demo@discador.local` / `password` and request `/tenant/supervision`.

Expected: status 200, contains `Supervisao`, `Agentes em operacao`, and does not contain `Modulo em breve`.

- [ ] **Step 5: Commit**

Run:

```bash
git add .
git commit -m "feat: add simulated supervision dashboard"
```

Expected: commit succeeds.

---

## Self-Review

- Spec coverage: route, tenant isolation, metrics, filters, UI, and future telephony actions are covered.
- Placeholder scan: no `TBD`, `TODO`, or vague implementation steps remain.
- Type consistency: status values match agent panel values: `available`, `paused`, `in_call`, `offline`.
