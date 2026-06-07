# Chamadas Manuais por Ramal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable simulated manual calls for all tenant users with extensions while preserving campaign capacity rules for agents only.

**Architecture:** Extend the existing `AgentPanelController` call flow with a `channel` input limited to `simulated` and `manual`. Blade adds a hidden manual channel marker and copy that reflects ramal usage. Tests cover supervisor/manual behavior and keep agent campaign-simulation behavior intact.

**Tech Stack:** Laravel, Blade, Eloquent, PHPUnit.

---

### Task 1: Manual Extension Calls

**Files:**
- Modify: `tests/Feature/TenantAgentPanelTest.php`
- Modify: `app/Http/Controllers/Tenant/AgentPanelController.php`
- Modify: `resources/views/tenant/agent/index.blade.php`

- [ ] **Step 1: Write failing test**

Add a test where a supervisor opens the panel, sees manual call UI, starts a manual call, gets `channel = manual`, ends it with a disposition, and returns to `offline`.

- [ ] **Step 2: Run red test**

Run: `php artisan test tests\Feature\TenantAgentPanelTest.php --no-coverage`
Expected: FAIL because manual channel and non-agent final status are not implemented yet.

- [ ] **Step 3: Implement controller**

Accept optional `channel`, default to `simulated`, sanitize phone, persist manual channel, and set final status to `available` only for agents.

- [ ] **Step 4: Implement Blade**

Show "Chamada manual simulada" copy and submit `channel=manual` from the manual call form.

- [ ] **Step 5: Verify**

Run focused tests, then full local and Docker verification.
