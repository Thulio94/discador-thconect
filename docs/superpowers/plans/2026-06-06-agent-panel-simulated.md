# Painel do Agente Simulado Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first real tenant Agent panel with status control, pause modal, simulated call lifecycle, and tenant-safe capacity counting.

**Architecture:** Laravel remains the business center. Agent state is stored in tenant-scoped tables and manipulated through a focused `AgentPanelController`; capacity calculation lives in a small service so future dialer pacing can reuse it.

**Tech Stack:** Laravel, Blade, Alpine, Tailwind/THCONECT components, MariaDB, PHPUnit, Docker Compose.

---

## File Structure

- Create `database/migrations/2026_06_06_000001_create_agent_statuses_table.php` for current and historical agent state.
- Create `database/migrations/2026_06_06_000002_create_agent_pauses_table.php` for pause records.
- Create `database/migrations/2026_06_06_000003_create_calls_table.php` for simulated calls.
- Create `app/Models/AgentStatus.php`, `app/Models/AgentPause.php`, and `app/Models/Call.php`.
- Create `app/Services/Dialer/AgentCapacityService.php` for available-agent counting.
- Create `app/Http/Controllers/Tenant/AgentPanelController.php`.
- Create `resources/views/tenant/agent/index.blade.php`.
- Modify `routes/web.php` to replace the coming-soon agent route.
- Modify `resources/views/layouts/app.blade.php` to remove the "Em breve" badge from Agente.
- Modify `app/Models/User.php` and `app/Models/Company.php` with relations.
- Test in `tests/Feature/TenantAgentPanelTest.php`.

---

### Task 1: Agent State Data Model

**Files:**
- Create: `database/migrations/2026_06_06_000001_create_agent_statuses_table.php`
- Create: `database/migrations/2026_06_06_000002_create_agent_pauses_table.php`
- Create: `database/migrations/2026_06_06_000003_create_calls_table.php`
- Create: `app/Models/AgentStatus.php`
- Create: `app/Models/AgentPause.php`
- Create: `app/Models/Call.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Company.php`
- Test: `tests/Feature/TenantAgentPanelTest.php`

- [ ] **Step 1: Write failing model test**

Create `tests/Feature/TenantAgentPanelTest.php` with a test that creates an agent status, pause, and simulated call for a tenant user, then asserts relations work.

- [ ] **Step 2: Run failing test**

Run: `php artisan test tests\Feature\TenantAgentPanelTest.php --no-coverage`

Expected: fail because `AgentStatus`, `AgentPause`, or `Call` does not exist.

- [ ] **Step 3: Implement migrations and models**

Create tables with `company_id`, `user_id`, status/call fields, timestamps, indexes, and foreign keys. Add relations from `User` and `Company`.

- [ ] **Step 4: Run focused test**

Run: `php artisan test tests\Feature\TenantAgentPanelTest.php --no-coverage`

Expected: pass for the data model test.

---

### Task 2: Agent Capacity Service

**Files:**
- Create: `app/Services/Dialer/AgentCapacityService.php`
- Test: `tests/Feature/TenantAgentPanelTest.php`

- [ ] **Step 1: Write failing capacity tests**

Add tests proving that only active `agent` users with latest status `available` count, and supervisors/company admins do not count.

- [ ] **Step 2: Run failing tests**

Run: `php artisan test tests\Feature\TenantAgentPanelTest.php --no-coverage`

Expected: fail because `AgentCapacityService` does not exist.

- [ ] **Step 3: Implement service**

Implement methods:

```php
availableAgentsForCompany(int $companyId): int
availableAgentsForCampaign(Campaign $campaign): int
allowedCallsForCampaign(Campaign $campaign): int
```

`allowedCallsForCampaign` uses `available agents * max_calls_per_available_agent` and respects `max_concurrent_calls` when present.

- [ ] **Step 4: Run focused tests**

Run: `php artisan test tests\Feature\TenantAgentPanelTest.php --no-coverage`

Expected: capacity tests pass.

---

### Task 3: Agent Panel Routes and Actions

**Files:**
- Create: `app/Http/Controllers/Tenant/AgentPanelController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TenantAgentPanelTest.php`

- [ ] **Step 1: Write failing controller tests**

Add tests for:

- `GET tenant.agent.index` returns 200 and no longer shows "Modulo em breve";
- `PATCH tenant.agent.status` changes to `available`;
- `PATCH tenant.agent.pause` creates pause and status `paused`;
- `POST tenant.agent.calls` creates simulated call and status `in_call`;
- `PATCH tenant.agent.calls.end` ends call and returns status `available`.

- [ ] **Step 2: Run failing tests**

Run: `php artisan test tests\Feature\TenantAgentPanelTest.php --no-coverage`

Expected: fail because routes/controller do not exist.

- [ ] **Step 3: Implement controller and routes**

Add route names:

- `tenant.agent.index`
- `tenant.agent.status`
- `tenant.agent.pause`
- `tenant.agent.calls.store`
- `tenant.agent.calls.end`

Use redirects with `with('status', ...)` for premium toast handling.

- [ ] **Step 4: Run focused tests**

Run: `php artisan test tests\Feature\TenantAgentPanelTest.php --no-coverage`

Expected: all controller tests pass.

---

### Task 4: Agent Panel UI

**Files:**
- Create: `resources/views/tenant/agent/index.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/TenantAgentPanelTest.php`

- [ ] **Step 1: Write failing view assertions**

Assert the panel contains "Painel do Agente", "Disponivel", "Pausa", "Offline", "Chamada simulada", and modal marker `agent-pause`.

- [ ] **Step 2: Run failing test**

Run: `php artisan test tests\Feature\TenantAgentPanelTest.php --no-coverage`

Expected: fail until the view is created.

- [ ] **Step 3: Implement Blade UI**

Create compact cards for status, ramal, capacity summary, simulated call, and recent status history. Use modal for pause reason. Do not use native `alert` or `confirm`.

- [ ] **Step 4: Run focused test**

Run: `php artisan test tests\Feature\TenantAgentPanelTest.php --no-coverage`

Expected: view assertions pass.

---

### Task 5: Full Verification and Commit

**Files:**
- All files from tasks 1-4.

- [ ] **Step 1: Run Laravel tests**

Run: `php artisan test --no-coverage`

Expected: all tests pass.

- [ ] **Step 2: Run frontend build**

Run: `npm run build`

Expected: Vite build succeeds.

- [ ] **Step 3: Run Docker migrations and tests**

Run: `docker compose exec -T app php artisan migrate`

Expected: migrations apply without error.

Run: `docker compose exec -T app php artisan test --no-coverage`

Expected: all tests pass in Docker.

- [ ] **Step 4: Smoke test HTTP**

Log in to `http://localhost:8087` as `admin.demo@discador.local` / `password` and request `/tenant/agent`.

Expected: status 200 and page contains `Painel do Agente` and `agent-pause`.

- [ ] **Step 5: Commit**

Run:

```bash
git add .
git commit -m "feat: add simulated agent panel"
```

Expected: commit succeeds.

---

## Self-Review

- Spec coverage: status control, pause modal, simulated call lifecycle, capacity counting, tenant isolation, and no real telephony are covered.
- Placeholder scan: no `TBD`, `TODO`, or unspecified implementation steps remain.
- Type consistency: status values are `offline`, `available`, `paused`, and `in_call`; route names are under `tenant.agent.*`; data models use `company_id` and `user_id`.
