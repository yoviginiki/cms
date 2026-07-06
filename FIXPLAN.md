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

### FIX-A2a — Add role authorization to unguarded write endpoints
**Source:** STATUS.md §2, Defect D1. **Severity: major.** **Effort: ~0.5 day.**

> Seven controllers expose create/update/delete with only the tenant-checked `Site $site` binding and NO role check, so a `viewer` can mutate them: `MagazineIssueController::store/update/destroy`, `ThemeEngineController::{update,fork,assign,saveOverrides,import,restoreVersion}`, `ThemeTemplateController::{store,update,destroy}`, `MagStyleController::{store,update,destroy}`, `BlockTemplateController::{store,destroy}`, `DtpDocumentController::save`, `DtpVersionController::restore`, and the `sites/{site}/apply-template` route closure (`routes/api.php:237`). Add `$this->authorize('update', $site)` (or a dedicated policy ability) to each write method — mirror the pattern already in `MagazineController::savePages`. For the DTP endpoints keep the feature-flag gate AND add the role check. Then add feature tests asserting a `viewer` gets 403 and an `editor` succeeds on each. This overlaps FIX-A1a/b: for `magazine_issues`/`mag_styles`/`theme_*` the missing role check is also a cross-tenant hole until RLS is forced, so land A1a first or together.

### FIX-A2b — Close user-management escalation paths + lock down `role` mass-assignment
**Source:** STATUS.md §2, Defects D2/D3/D4. **Severity: major.** **Effort: ~0.25 day.**

> Three fixes in `UserController` + `User`: (1) `invite` (`:41`) allows any admin to create an `admin` — apply the same `isOwner()` gate `updateRole` uses (`:93`) before allowing `role=admin` on invite. (2) `updateRole` (`:78-99`) can demote the tenant **owner** — add a guard rejecting any `updateRole` whose target `$user->isOwner()` (mirror the owner-protection already in `destroy` `:112`). (3) Remove `'role'` from `User::$fillable` (`app/Models/User.php:18`) and set it only through explicit, gated code paths, so a future `update($request->all())` can't escalate. Add tests: admin-invites-admin → 403; admin-demotes-owner → 403; mass-assignment of role via any user-writing endpoint is ignored.

### FIX-A2c — Implement real auth/RBAC test coverage
**Source:** STATUS.md §2, Defect D5. **Severity: major (blocks trustworthy GREEN).** **Effort: ~0.5 day.**

> `tests/Feature/Auth/LoginTest.php` is entirely `markTestIncomplete()` stubs (0 assertions). Implement the 7 login/logout/me/throttle tests for real, then add an RBAC matrix test: for each role (viewer/author/editor/admin/owner) assert allowed vs 403 on representative create/update/delete/publish endpoints across Page, Post, Asset, Theme, MagazineIssue, MagStyle, User-management. This suite is what proves FIX-A2a/b landed and prevents regressions. Also add a test asserting password-reset and invite-acceptance flows once FIX-A2d wires them.

### FIX-A2d — Finish invite-acceptance + password-reset delivery (functional, not security)
**Source:** STATUS.md §2, Secondary. **Severity: minor.** **Effort: ~0.5 day.**

> `PasswordResetController::forgotPassword` has its mail send commented out (reset is non-functional in prod) and there is NO server route consuming `invitation_token`, so invited users can never set a password. Wire an accept-invite endpoint (validate token + expiry, set password, clear token, never accept a `role` from the request) and re-enable reset email. Stop returning raw `invitation_token` in `UserController::index` (`:21`).

---

## Secondary (schedule into the owning subsystem's fix-session)
- **`SET LOCAL` vs `SET`** for tenant GUC — revisit when auditing Auth (A#2) / publish (B). Context can leak across requests on reused connections (Octane/queue).
- **`ProcessScheduledContentJob:18`** cross-tenant `Site::withoutGlobalScopes()->get()` returns 0 rows under enforced RLS with no prior context — fix as part of Publish pipeline (Session B).
- **`themes` RLS weakened** (lost `WITH CHECK`, shared `is_system` rows, throws on unset tenant) — fold into Theme Engine fixes (Session E).
- **Public routes "first tenant"** (`routes/web.php:29,50`) — fix when auditing publish / public serving (Session B).
