# FIXPLAN.md — Prioritized Fix Plan

Derived from STATUS.md. Fix-sessions are sized to ≤1 day. Ordered by: (1) blockers in foundations,
(2) blockers in publish, (3) block-registry compliance, (4) everything else.
This file is populated as the audit proceeds; only subsystems already audited appear below.

**Standing rule:** fixes happen in follow-up sessions off `master` (NOT on `audit/system-health`). Each brief below is paste-ready for a future Claude Code session.

---

## P0 — FOUNDATIONS / SECURITY (blockers)

### FIX-A1a — Enforce RLS on all tenant tables (add FORCE + missing policies)
**Source:** STATUS.md §1, Defects 2 & 3. **Severity: blocker.** **Effort: ~0.5 day.**

> The CMS relies on PostgreSQL RLS as its ONLY tenant-isolation layer (the `TenantScoped`/`SiteScoped` traits are dead code — no model uses them). Two gaps make isolation fail for whole subsystems. **(1)** These tables have RLS ENABLED but not FORCED, so the app's owner role (`cms_saas`) bypasses their policies entirely: `magazines, magazine_pages, magazine_elements, magazine_issues, mag_pages, mag_elements, mag_styles, layouts, theme_assignments, theme_overrides, theme_versions` (and `issue_content_items, magazine_curation_runs, issue_design_system`). Write a new migration that runs `ALTER TABLE <t> FORCE ROW LEVEL SECURITY` for each, and verify each already has a `tenant_isolation` policy (add one keyed on `current_setting('app.current_tenant_id', true)::uuid`, scoped via `site_id`→`sites` or direct `tenant_id`, where missing). **(2)** These tenant-bearing tables have NO RLS policy at all: `menus, menu_items, tags, taggables, redirects, grids, grid_assignments, grid_positions, position_overrides, global_blocks, popups, activity_logs, page_views, search_queries, site_templates, theme_customizations, theme_templates`. Add `ENABLE`+`FORCE`+`tenant_isolation` policy (site_id→sites subquery pattern from `0001_01_01_000015_enable_row_level_security.php`) to each. Reproduce the bug first: `SET app.current_tenant_id='00000000-0000-0000-0000-000000000000'; SELECT count(*) FROM magazines;` returns >0 today, must return 0 after. Add a data-driven test that asserts EVERY table carrying `site_id`/`tenant_id` has `relrowsecurity AND relforcerowsecurity` true — so this can never regress silently. Do NOT run `migrate:fresh`; write a forward migration and run `migrate`.

### FIX-A1b — Scope nested route-model bindings (close cross-tenant/cross-site IDOR)
**Source:** STATUS.md §1, Defects D1/D2/D3. **Severity: blocker.** **Effort: ~0.5 day.**

> Nested API routes resolve the child model by its global id and authorize only the parent `Site`, so a user can pass their own `{site}` and a foreign `{menu}`/`{magazine}`/`{page}` id. FIX-A1a closes this at the DB level for RLS-covered tables, but add defense-in-depth + fix same-tenant-cross-site: apply `->scopeBindings()` to the nested resource groups in `routes/api.php` (sites.pages, sites.posts, sites.assets, sites.categories, sites.menus, sites.tags, sites.magazines, sites.magazine-issues, grids, redirects, …) so Laravel enforces `child.site_id == site.id` on binding. Verify every child model exposes the relationship Laravel needs for implicit scoping, or add `resolveChildRouteBinding`. Then extend `tests/Feature/Security/TenantIsolationTest.php` with cross-tenant AND cross-site cases for menus, magazines, tags, redirects, grids, pages — asserting 404. Concrete repro to fix: `GET /api/v1/sites/{ownSiteId}/menus/{foreignMenuId}` currently returns the foreign menu (`MenuController.php:52`).

### FIX-A1c — Decide the app-scope layer: wire the traits or delete them
**Source:** STATUS.md §1, Defect 1. **Severity: major.** **Effort: ~0.5 day.**

> `app/Domain/Concerns/TenantScoped.php` + `SiteScoped.php` advertise a "second layer after RLS" but are applied to zero models — the docblocks lie and there is no defense-in-depth if RLS is ever misconfigured (as it was, above). Either (preferred) apply `SiteScoped` to every site-scoped model and `TenantScoped` to every tenant-scoped model so a forgotten `FORCE` can't silently expose data again, and add `withoutGlobalScopes()` only where genuinely cross-tenant (audit those call sites — `ProcessScheduledContentJob:18`, `PositionRenderer` ×22, `ReferencesBackfillCommand`); OR delete the traits and add a code comment on the tenancy design that RLS is the sole layer, so nobody assumes a backstop exists. If wiring them: confirm the global scope resolves tenant from `Auth::user()` correctly inside queue jobs (Auth is null there — the scope no-ops, which is why RLS must stay primary).

---

## Secondary (schedule into the owning subsystem's fix-session)
- **`SET LOCAL` vs `SET`** for tenant GUC — revisit when auditing Auth (A#2) / publish (B). Context can leak across requests on reused connections (Octane/queue).
- **`ProcessScheduledContentJob:18`** cross-tenant `Site::withoutGlobalScopes()->get()` returns 0 rows under enforced RLS with no prior context — fix as part of Publish pipeline (Session B).
- **`themes` RLS weakened** (lost `WITH CHECK`, shared `is_system` rows, throws on unset tenant) — fold into Theme Engine fixes (Session E).
- **Public routes "first tenant"** (`routes/web.php:29,50`) — fix when auditing publish / public serving (Session B).
