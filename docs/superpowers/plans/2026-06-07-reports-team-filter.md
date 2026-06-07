# Relatorios por Equipe Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add team filtering and team performance summary to tenant reports.

**Architecture:** Extend the existing `ReportController` filter tuple with `team_id`. Reuse tenant-scoped calls query and constrain by users attached to the selected team through `team_user`. Blade adds one select and one summary card following existing report UI patterns.

**Tech Stack:** Laravel, Blade, Eloquent, PHPUnit, Docker Compose.

---

### Task 1: Reports Team Filter

**Files:**
- Modify: `tests/Feature/TenantReportsTest.php`
- Modify: `app/Http/Controllers/Tenant/ReportController.php`
- Modify: `resources/views/tenant/reports/index.blade.php`

- [ ] **Step 1: Write failing test**

Add a feature test proving the page shows a team filter, summarizes team call counts, filters by team, excludes other teams, and exports CSV with the same filter.

- [ ] **Step 2: Run red test**

Run: `php artisan test tests\Feature\TenantReportsTest.php --no-coverage`
Expected: FAIL because the team filter and summary are not implemented yet.

- [ ] **Step 3: Implement controller**

Load tenant teams, add `team_id` to filters, constrain calls by `whereHas('user.teams')`, and build team performance counts.

- [ ] **Step 4: Implement Blade**

Add the `Equipe` select to the filter form, preserve it in the CSV link, and render `Performance por equipe`.

- [ ] **Step 5: Verify**

Run: `php artisan test tests\Feature\TenantReportsTest.php --no-coverage`
Expected: PASS.

- [ ] **Step 6: Final verification**

Run local tests, build, Docker tests, and HTTP smoke for `/tenant/reports`.
