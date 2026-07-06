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

### FIX-A4a — Close the two stored-XSS holes in block sanitization
**Source:** STATUS.md §4, Defects D1 & D2. **Severity: blocker.** **Effort: ~0.5 day.**

> `SanitizationService::sanitizeBlock` has two holes, both ending in `{!! !!}` sinks. **(1)** The `allowHtml` path (line 127-129) uses `strip_tags($value, '<br><em><strong><span>')`, which does NOT strip attributes — `<span onmouseover=...>` survives. Replace it with a real HTMLPurifier profile that allows exactly `br,em,strong,span` with NO attributes (or the minimal safe set), same as the rich profile but tag-restricted. **(2)** The `foreach` skips non-string values (line 119), so nested HTML in arrays (`accordion.items[].content`, `catalog.items[].content/contentSecondary`, and any block storing HTML in arrays) is never purified. Make sanitization **recursive**: walk nested arrays/objects and purify every string leaf that corresponds to an HTML field, driven by each block definition's declared HTML fields (extend `sanitizationConfig()` to name nested HTML paths, e.g. `items.*.content`). Then add tests proving: `allowHtml` span with `onmouseover` is stripped; accordion `items[].content` with `<img onerror>` is stripped; both in the published HTML. Verify against every block that renders `{!! !!}` (grep `resources/views/blocks/*.blade.php` for `{!!` — accordion, catalog, runningtext, dropcap, footnote, latestposts, heading, etc.) that its HTML-bearing fields are covered.

### FIX-A4b — Add a Content-Security-Policy (admin + published) and security headers to published output
**Source:** STATUS.md §4, Defect D3. **Severity: major.** **Effort: ~0.5-1 day.**

> Two surfaces. **(1) Admin:** add a `Content-Security-Policy` to `SecurityHeaders` middleware (start report-only, then enforce) restricting `script-src`/`style-src`/`img-src`/`connect-src` to self + known origins; add `Strict-Transport-Security` (HSTS) since the admin is https-only. **(2) Published static sites:** `PublishSiteJob` currently writes only redirect rules to `.htaccess` (`:488-502`) — extend it (and/or the deploy nginx template) to emit `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options`, `Referrer-Policy`, and HSTS for the published output, OR inject a CSP `<meta http-equiv>` into the generated HTML `<head>` as a fallback where server config isn't controlled. This is the defense-in-depth backstop for FIX-A4a; do both, don't rely on either alone.

### FIX-A4c — Real sanitizer tests + gate html-embed + tighten CORS/uploads
**Source:** STATUS.md §4, Defects D4/D5 + secondary. **Severity: major (tests, html-embed) / minor (rest).** **Effort: ~0.5 day.**

> Implement the 5 stubbed `tests/Unit/Services/SanitizationServiceTest.php` tests for real and add the D1/D2 regression cases. Gate the raw `html-embed` block (`HTML.Allowed => '*'`, `BuildPageService::renderBlock:311`) behind an owner/admin `authorize` check and document it as a trusted-HTML surface — today any editor can inject arbitrary `<script>` through it. Tighten CORS (`config/cors.php`) to `https` + the specific admin origin instead of any `*.ensodo.eu` over http/https with credentials + `allowed_headers:['*']`. Add an image dimension/pixel-count cap to `UploadAssetRequest` (decompression-bomb guard). SVG handling is already solid (dedicated `SvgSanitizer` + regex double-gate) — no change needed there. Optionally add on-write purification as defense-in-depth so the DB never stores raw HTML.

---

## P1 — SCHEMA / DATA INTEGRITY

### FIX-A3a — Stop orphaning polymorphic blocks on parent delete
**Source:** STATUS.md §3, Defect D1. **Severity: moderate.** **Effort: ~0.5 day.**

> `blocks.blockable_id` (and `taggables.taggable_id`) are polymorphic with no DB FK, and no `deleting` cascade exists on `Page`/`Post`/`Slider`/`ThemeTemplate` — `PageController::destroy` just calls `$page->delete()`, so every deleted parent leaks its block/taggable rows forever. Fix: add a `static::deleting` hook to each parent model that deletes `$model->blocks()` (and detaches taggables), OR centralize it in a trait applied to all blockable parents. Guard against the soft-delete case if any parent uses SoftDeletes (delete blocks only on force-delete, or keep them for restore — decide per model). Add a test: create page+blocks, delete page, assert 0 orphan blocks. Optionally add a one-off cleanup command for any orphans already accumulated in prod. Note the interaction with §1 FIX-A1a — blocks RLS must stay correct after cleanup.

### FIX-A3b — Add missing FK indexes + fix delete-blocking FKs (perf + teardown safety)
**Source:** STATUS.md §3, Defects D2/D4/D5. **Severity: minor.** **Effort: ~0.5 day.**

> One migration to: (1) add indexes on hot unindexed FK/scoping columns — prioritize `themes.site_id`, `deploy_artifacts.{deployment_id,page_id,post_id}`, `menu_items.{page_id,post_id,category_id,parent_id}`, `global_blocks.site_id`, `popups.site_id`, `sites.active_theme_id`; (2) change the 3 `NO ACTION` user/tenant FKs to `SET NULL` (`deployments.triggered_by`, `page_versions.published_by`) and confirm tenant-teardown order for `magazine_issues.tenant_id`; (3) optionally add FK constraints on the un-constrained `tenant_id` columns (`layouts`, `theme_*`, `issue_studio_*`) for referential safety. Forward migration only — no `migrate:fresh`. Low risk, measurable query improvement once sites have real data.

---

## P1 — PUBLISH RELIABILITY

### FIX-B5a — Fix 2 crash-on-default-data blocks + isolate block failures at publish
**Source:** STATUS.md §5, Defect D1. **Severity: moderate.** **Effort: ~0.25 day.**

> Two one-line null-safety fixes: `category-header.blade.php:23` returns unguarded `$data['textAlign']` from the ternary true-branch — capture the coalesced value into a variable first (`$ta = $data['textAlign'] ?? 'center'; $textAlign = in_array($ta,[...]) ? $ta : 'center';`). `readingprogress.blade.php:18` uses `?:` — change `$data['color'] ?: '#3b82f6'` to `($data['color'] ?? '') ?: '#3b82f6'`. Then add per-block isolation in `BuildPageService::renderBlock`'s callers (the `foreach` at `:220-221`, and the templated/context loops): wrap each `renderBlock` in try/catch, emit an HTML comment placeholder + log on failure, so one fragile block can't fail the entire page publish. Add the `audit/render_blocks.php` sweep as a real test (render every registered block with empty data, assert no throw) to prevent regressions.

### FIX-B6a — Make build retention per-site so publishing can't delete other sites' live output
**Source:** STATUS.md §6, Defect D2. **Severity: blocker.** **Effort: ~0.5 day.**

> The live symlink points into `storage/app/builds/{deploymentId}` and BOTH prune functions (`PublishSiteJob::cleanOldBuilds` keep-3, `SymlinkDeployStrategy::cleanOldBuilds` keep-5) glob the GLOBAL builds dir and delete by mtime — so >3 active sites means older sites' live builds get deleted (dangling symlink → dead site). Fix: partition builds per site (`storage/app/builds/{siteId}/{deploymentId}`) and make retention prune only within the current site's subtree; NEVER delete the build the site's live symlink currently targets (read the symlink, exclude its target). Unify the two divergent prune functions into one. Add a test: publish site A, publish site B ×4, assert site A's live build still exists and its symlink resolves.

### FIX-B6b — Actually implement rollback (it is currently a silent no-op)
**Source:** STATUS.md §6, Defect D1. **Severity: blocker.** **Effort: ~0.5-1 day.**

> `PublishSiteJob::handle()` ignores `$this->rollbackTargetId` and republishes current DB content, so rollback restores nothing; `DeployService::rollback` and both strategy `rollback()` methods are dead code. Wire `handle()` to branch on the rollback type: for symlink sites, re-point the live symlink to the target deployment's retained build (atomically, via `.new`+rename — see FIX-B6c); mark the new deployment `rolled_back`. This depends on FIX-B6a (the target build must still exist — retain enough per-site history to cover the rollback window, and block rollback with a clear error if the target build was pruned). Add a test that publishes v1, publishes v2, rolls back, and asserts the live output matches v1.

### FIX-B6c — Make custom-domain / rename deploys atomic + fix mid-build live mutation & concurrency
**Source:** STATUS.md §6, Defects D3/D4/D5. **Severity: major.** **Effort: ~1 day.**

> (1) Custom-domain sites use per-file `copyDeploy` into live `public_html` (non-atomic). Build into a staging dir and swap atomically (symlink where the FS allows, else build-then-rename-dir); on failure leave the previous live output untouched. (2) `RenameDeployStrategy` must also delete files for removed pages (diff old vs new manifest) so deleted pages don't stay live. (3) Move `cleanUnpublishedPosts` so it operates on the staging tree (or the post-swap live tree), never mutating the live docroot mid-build, and scope it to the site's own root, not the shared `sites/` parent. (4) Hold the publish advisory lock (or a DB `building` guard that is NOT auto-deleted mid-flight) across the whole job, and remove/raise the >5-min stale-delete so a slow build can't be wiped and raced. Replace the 6 `PublishTest` stubs with real tests covering deploy swap, retention, and concurrent-publish rejection.

---

## Secondary (schedule into the owning subsystem's fix-session)
- **`SET LOCAL` vs `SET`** for tenant GUC — revisit when auditing Auth (A#2) / publish (B). Context can leak across requests on reused connections (Octane/queue).
- **`ProcessScheduledContentJob:18`** cross-tenant `Site::withoutGlobalScopes()->get()` returns 0 rows under enforced RLS with no prior context — fix as part of Publish pipeline (Session B).
- **`themes` RLS weakened** (lost `WITH CHECK`, shared `is_system` rows, throws on unset tenant) — fold into Theme Engine fixes (Session E).
- **Public routes "first tenant"** (`routes/web.php:29,50`) — fix when auditing publish / public serving (Session B).
