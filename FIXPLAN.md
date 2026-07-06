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

### FIX-B7a — Make delta publish regenerate sitemap/feeds/homepage (output completeness)
**Source:** STATUS.md §7, Defect D1. **Severity: major.** **Effort: ~0.5 day.**

> Incremental/auto-republish leaves the sitemap, RSS/feed, and archives stale, and a homepage change (`settings['homepage_id']`) rebuilds nothing. Fixes: (1) in `RepublishStaleJob` (or its promote step) always regenerate `sitemap.xml` + `feed.xml` when the batch adds/removes/renames any published URL (cheap, deterministic). (2) In `SiteController::update`, when `homepage_id`/`homepage_type`/`homepage_grid_id` changes, mark the homepage stale (or trigger a homepage rebuild) so the old `index.html` doesn't persist. (3) Wire category/tag name/slug edits to `markStale` so archive labels refresh. Consider reviving the dead `DependencyGraph::getAffectedTargets` closure (it already models these targets) instead of re-deriving. Add tests asserting sitemap/homepage refresh after a delta publish.

### FIX-B7b — Close the lost-update race + write a redirect on slug rename
**Source:** STATUS.md §7, Defects D2/D4. **Severity: major (D2) / moderate (D4).** **Effort: ~0.5 day.**

> D2: at promote time, clear `needs_republish` only for rows whose flag/reason still matches what was built (compare `needs_republish_reason` or a per-batch version token, or re-check `updated_at` against the build snapshot), so a re-flag arriving after the build snapshot isn't erased; and let the dedupe guard queue a corrective batch when the flag was re-set. D4: on slug rename (`PageController::update`/`PostController::update`), auto-create a `Redirect` from the old path to the new (the redirects table + `.htaccess` generation already exist) so the old URL 301s instead of 404ing. Add tests for both (re-flag during pending batch stays stale; renamed URL redirects).

### FIX-B8a — Fix structured-data URLs + empty meta descriptions
**Source:** STATUS.md §8, Defects D1/D2. **Severity: moderate.** **Effort: ~0.25 day.**

> D1: `StructuredDataService` hardcodes `/blog/{slug}` for the JSON-LD Article url (`:34`) and post breadcrumb, but posts serve at `/{category}/{slug}/`. Replace the hardcoded `/blog/` construction with `LocalePaths::urlPath($site, $post)` (the same helper the sitemap and — via `SeoService`'s path logic — the canonical use) so structured data, canonical, og:url, and sitemap all agree. Do the same for the page WebPage url and every breadcrumb item. D2: broaden `SeoService::autoDescription` to fall back across text-bearing block types (`paragraph`, `rich-text`, `text`, `heading`, hero subtitle) — pull the first non-empty stripped text — so pages without an explicit description aren't shipped empty. Add SEO tests asserting canonical == og:url == JSON-LD url == sitemap URL for a page and a categorized post, and that description is non-empty when the page has text.

### FIX-B9a — Repair image variant generation (Intervention v4 API) + stop silently swallowing failures
**Source:** STATUS.md §9, Defect D1. **Severity: blocker.** **Effort: ~0.25 day.**

> `AssetService::generateImageVariants:100` calls `$this->imageManager->read()`, which doesn't exist in the installed Intervention Image v4.0.1 — so it throws and the trailing `catch (\Throwable) {}` hides it, yielding 0 variants for all images. Update to the v4 API (`$this->imageManager->read(...)` is the v3 name; use the v4 equivalent — `read()` was replaced; confirm against the installed version's `ImageManager` methods, e.g. `->read()`/`->decode*`), and **stop swallowing the error** — log it (or at least `report($e)`) so a future library break is visible instead of silently disabling the whole pipeline. Backfill variants for existing assets with a one-off command. Add a test that uploads a >800px image and asserts webp_800/medium_800/thumb_200 variants are created and non-empty.

### FIX-B9b — Make AssetPublisher actually serve variants (URL + publish)
**Source:** STATUS.md §9, Defect D2. **Severity: major (do WITH B9a — it's a landmine otherwise).** **Effort: ~0.5 day.**

> `AssetPublisher::resolveUrl` ignores the variant suffix and the `rewriteHtml` regex (`/serve(?:/[a-z]+)?`) mangles `…/serve/webp_800` into `/assets/files/{hash}.{ext}_800`. Fixes: (1) parse the full variant name in both the `resolveUrl` API-URL regex and `rewriteHtml` (allow `[a-z0-9_]+`), (2) map each variant to its own hashed public path and **copy the variant file** (not the original) to the deploy target, (3) return the variant's public URL. Verify `image.blade`'s `<picture>`/`srcset` resolve to real files for a >800px image end-to-end (publish, then assert every `src`/`srcset` URL exists on disk). MUST land together with B9a, or fixing generation will break large images in published output.

### FIX-C10a — Restore extractor-contract coverage (langswitcher) + delete orphan view
**Source:** STATUS.md §10, Defects D1/D2. **Severity: moderate.** **Effort: ~15 min.**

> Add a `'langswitcher' => new NullExtractor()` entry (or a real `FieldMapExtractor` if the block references locale-linked content) to `ReferenceExtractorRegistry::__construct` so `ExtractorCoverageTest` goes green again — the test is currently RED, which also means it isn't gating CI. Delete the orphan `resources/views/blocks/quote.blade.php` (leftover from the quote→pullquote rename; no registered type renders it). While here, ensure `ExtractorCoverageTest` (and the suite generally) actually runs in CI so a future block added without its contract artifacts fails the build.

### FIX-C11a — Stop block-save from cascading away block-linked data + add concurrency guard
**Source:** STATUS.md §11, Defects D1/D2. **Severity: moderate.** **Effort: ~0.5-1 day.**

> D1: `syncBlocks` deletes all blocks then re-inserts, so `theme_overrides.block_id` / `grid_position_blocks.block_id` (ON DELETE CASCADE) rows are wiped and never restored. Options: (a) make `syncBlocks` a real diff (update-in-place existing block ids, insert new, delete removed) instead of delete-all — this preserves the FK rows for unchanged blocks; or (b) snapshot the block-scoped `theme_overrides`/`grid_position_blocks` before the delete and re-attach them to the recreated same-id blocks inside the transaction. Prefer (a). Add a test: create a page with a block, add a block-scoped theme override, re-save the page's blocks, assert the override still exists. D2: add optimistic concurrency to the save — the page/blockable carries a version or `updated_at`; the editor sends it back and `syncBlocks` rejects (409) if it changed since load, so a second editor can't silently clobber. Surface the conflict in the editor (reload/merge prompt).

---

## Secondary (schedule into the owning subsystem's fix-session)
- **`SET LOCAL` vs `SET`** for tenant GUC — revisit when auditing Auth (A#2) / publish (B). Context can leak across requests on reused connections (Octane/queue).
- **`ProcessScheduledContentJob:18`** cross-tenant `Site::withoutGlobalScopes()->get()` returns 0 rows under enforced RLS with no prior context — fix as part of Publish pipeline (Session B).
- **`themes` RLS weakened** (lost `WITH CHECK`, shared `is_system` rows, throws on unset tenant) — fold into Theme Engine fixes (Session E).
- **Public routes "first tenant"** (`routes/web.php:29,50`) — fix when auditing publish / public serving (Session B).
