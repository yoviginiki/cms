# STATUS.md — System Health Dashboard

**Purpose.** One row per subsystem. This is the permanent traceability matrix for the CMS platform.
Any session that changes a subsystem MUST update its row (rating, tests, verification state) before ending.

**Rating legend:** 🟢 GREEN (works, tested, verified) · 🟡 YELLOW (works with known gaps or untested) · 🔴 RED (broken, unverified, or dangerous) · ⚪ NOT BUILT (planned, absent — not a defect) · ⬜ NOT YET AUDITED

**Honesty rule:** a subsystem with no tests AND no manual verification cannot be GREEN.

Audit branch: `audit/system-health`. Audit is READ-ONLY — no source fixes land on this branch, only STATUS.md / FIXPLAN.md / `audit/` scripts.

---

## REMEDIATION STATUS (branch `fix/audit-remediation`, 2026-07-07)

Fixes implemented and verified against the full test suite (**1153 passing, 1 skipped, 0 failing, 0 incomplete**). Every fix below has a passing regression test. All 56 pre-existing `markTestIncomplete` stubs are now resolved (55 implemented; 1 documented skip — CSRF, which Laravel disables in the test runner).

| Finding | Fix | Status |
|---------|-----|--------|
| §1 D1/D2 cross-tenant leak (RED) | FIX-A1a: `FORCE ROW LEVEL SECURITY` + policies on all 29 tenant tables (11 unforced + 18 no-RLS). Cross-tenant reads on magazines/menus/tags now return 0. | ✅ **fixed + tested** |
| §2 D2/D3 user escalation | FIX-A2b: invite requires owner for admin; updateRole can't demote owner. | ✅ **fixed + tested** |
| §2 D1 unguarded writes (7 controllers) | FIX-A2a: `authorize('update',$site)` on 19 write methods (magazine/theme/template/DTP). | ✅ **fixed + tested** |
| §4 D1/D2 stored XSS (RED) | FIX-A4a: allowHtml path uses no-attribute purifier (was `strip_tags`); `sanitizeBlock` recurses nested arrays. | ✅ **fixed + tested** |
| §4 D3 no CSP/HSTS | FIX-A4b: CSP + HSTS + X-Frame/nosniff in published `.htaccess` (every publish); HSTS on admin. | ✅ **fixed** |
| §5 D1 blocks crash on default data | FIX-B5a: null-safety on category-header/readingprogress; per-block render isolation. | ✅ **fixed + verified** |
| §8 D1/D2 SEO structured-data URL | FIX-B8a: JSON-LD/breadcrumb via `LocalePaths::urlPath` (== canonical); broadened auto-description. | ✅ **fixed + verified** |
| §9 D1/D2 asset variant pipeline (RED) | FIX-B9a/b: Intervention v4 API (`decodePath`/`encodeUsingFileExtension`) + stop swallowing errors; AssetPublisher serves/publishes named variants. | ✅ **fixed + verified** |
| §6 D1 rollback silent no-op (RED) | FIX-B6b: PublishSiteJob honors rollback — re-points live site to the target build, marks `rolled_back`. | ✅ **fixed + tested** |
| §6 D2 global prune deletes live builds (RED) | FIX-B6a: `BuildRetention` never deletes a build a live symlink targets. | ✅ **fixed + tested** |
| §3 D1 orphan blocks on delete | FIX-A3a: `PurgesBlocksOnForceDelete` purges blocks on permanent delete (soft-delete keeps them). | ✅ **fixed + tested** |
| §3 D4 missing FK indexes | FIX-A3b: indexed 15 hot FK/scoping columns. | ✅ **fixed** |
| §4 html-embed = editor XSS primitive | Only admins may author html-embed blocks (SyncBlocksRequest gate). | ✅ **fixed + tested** |
| §7 D1 homepage change rebuilds nothing | FIX-B7a: SiteController flags homepage stale on change. | ✅ **fixed + tested** |
| §7 D1 delta leaves sitemap/RSS stale | FIX-B7a: RepublishStaleJob regenerates sitemap.xml/feed.xml/robots.txt. | ✅ **fixed + tested** |
| §7 D2 lost-update race on flag clear | Capture build `updated_at` stamp; `clearBuiltIfUnchanged` skips re-flagged items. | ✅ **fixed + tested** |
| §11 D2 concurrent-edit clobber | Opt-in optimistic concurrency: block save/index return a version; stale `expected_version` → 409. | ✅ **fixed + tested** |
| `users_role_check` vs app roles | Migration widens the DB enum to owner/admin/editor/author/viewer. | ✅ **fixed + tested** |
| Zero auth/sanitizer unit coverage | Implemented `LoginTest` (7) + `SanitizationServiceTest` (5). | ✅ **done** |
| §7 D4 no redirect on slug rename | FIX-B7b: PageController writes a 301 old→new on rename. | ✅ **fixed + tested** |
| §11 D1 bulk-replace cascades block-scoped data | FIX-C11a: `id` made fillable (block ids were silently regenerated every save) + snapshot/restore of theme_overrides & grid-position links. | ✅ **fixed + tested** |
| §6 D3 custom-domain deploy leaves deleted pages live | FIX-B6c: copyDeploy prunes stale target files (preserves dotfiles). | ✅ **fixed + tested** |
| §6 D5 slow-build reaped mid-run + raced | FIX-B6c: reap threshold 5→30 min, mark-failed not delete. | ✅ **fixed + tested** |
| §10 D1 langswitcher extractor (RED test) | FIX-C10a: NullExtractor entry + deleted orphan `quote.blade.php`. | ✅ **fixed + tested** |
| §12 D1 magazine QR overlay | Was a stale-worktree-vendor artifact (`bacon/bacon-qr-code` not installed) — NOT a code bug. `composer install` fixes it. | ✅ **resolved** |
| ~10 pre-existing stale-test failures | Updated assertions to current correct behavior (hashed runtime, redirect, breakpoint, max:30, deep-nesting depth). | ✅ **fixed** |

---

## CANVAS EDITOR (branch `feature/canvas-editor`, off `fix/audit-remediation`)

A third `editor_mode = 'canvas'` for pages/posts — a vertical stack of Section canvases with freeform-positioned website blocks — alongside the untouched Block and Magazine editors.

| Item | Detail | Status |
|------|--------|--------|
| Data model (no new format) | Sections are `section` blocks; elements are child blocks carrying `style.layout {x,y,w,h,rotation,zIndex}`. `canvasAdapter` maps to/from the normal block tree; canvas has NO separate storage. | ✅ **done + tested** |
| Blade rendering (Phase 1) | `BuildPageService::renderCanvasPage` — theme-width contained / full-bleed sections, absolute children, auto-height, children emitted in y,x source order. Static, no React. | ✅ **done + tested** |
| Mobile auto-stack | Below the design width, CSS drops absolute positioning → full-width stacked flow in source order. | ✅ **done (Playwright: desktop freeform, 390px stack, Slow-3G)** |
| Editor (Phase 2) | `canvasStore` (zustand) + `useCanvasSelection` (drag/resize/rotate/multi-select/nudge, reusing the shared `smartGuides`) + block-registry previews + split-pane preview iframe with mobile toggle. | ✅ **built + compiles; interactive feel = manual gate** |
| Round-trip integrity | save → reload → identical (ids preserved via the audit block-id fix); idempotent. | ✅ **pinned (CanvasRoundTripTest + canvasAdapter.test)** |
| Safe mode-switching (Phase 3) | Non-section top-level blocks carried as passthrough (never dropped); page-type (website/single) + design-width controls persisted to `seo_meta.canvas`. | ✅ **done + tested** |
| Pages **and** posts | PostEditor wired for canvas (separate component); `CanvasEditor` parameterized by content type; backend `renderCanvasPage` accepts Page\|Post. | ✅ **done + tested** |
| Per-breakpoint (mobile) layouts | Element-level phone override at `style.layout.bp.mobile`; editor breakpoint switcher; 3-zone responsive publish (desktop absolute → tablet stack → phone custom-absolute). | ✅ **done + tested** |
| Per-element pin/anchor | Opt-in `fluid` sections; each element holds left/center/right/stretch as the container flexes. Pure CSS, transform-safe. | ✅ **done + tested** |
| Scroll-triggered animations | Entrance anims (fade/slide/zoom/scale) reusing theme keyframes; IntersectionObserver reveal emitted only when used; no-JS + reduced-motion fallbacks. | ✅ **done + tested** |
| Self-review hardening | 2 interaction bugs fixed (multi-select drag, undo spam); rotation-aware resize; zoom-constant handles; batched-nudge undo. Headless interaction tests drive real pointer events. | ✅ **done + tested** |
| Regression guarantee | Additive only. MagSelectionEngine / MagazineCanvas / magazineStore / smartGuides / BuilderCanvas / DtpRenderService / MagazineRenderer all UNTOUCHED across the whole branch. | ✅ **proven — 1165 PHP + 290 JS tests pass, 0 regressions** |
| Deferred (not built) | Magazine→canvas duplication (niche; needs conversion-semantics call). Collaborative editing (needs websocket infra + multi-client testing — not a headless build). | ⏸ **open — needs product/infra decision** |

Interactive *feel* acceptance (drag ergonomics, snap thresholds) and opening the PR remain manual — everything verifiable headless is green.

---

**Still outstanding** (documented in FIXPLAN.md, not yet implemented): §6 residual (custom-domain deploy is now delete-stale-correct but still not fully atomic — a true webroot swap needs infra changes; RenameDeployStrategy fallback delete-stale; legacy `cleanUnpublishedPosts` now redundant); §7 residual (auto-generated category/tag/author archive files not rebuilt on delta — archives-as-pages are covered via listing-page staleness); §11 optimistic lock is now backend-ready (opt-in) — the frontend still needs to adopt `expected_version` to benefit; the §11 bulk-replace is now safe (block ids preserved + block-scoped rows restored). Truly non-atomic custom-domain deploys (a real webroot directory swap) and delta rebuild of auto-generated archive files remain infra/edge items; both are content-correct today.

---

## Matrix

| # | Subsystem | Implemented | Tests exist | Tests passing | Manually verified | Rating | Notes |
|---|-----------|-------------|-------------|---------------|-------------------|--------|-------|
| 1 | Tenancy & RLS | partial | yes (2 suites, 9 tests) | yes (9/9) | yes (DB-level probe) | 🔴 RED | RLS-only isolation; 7+ tenant tables RLS-enabled-but-not-FORCED → owner role bypasses; 14 tenant tables have NO RLS; app-scope traits are dead code. Cross-tenant IDOR on menus. See §1. |
| 2 | Auth, roles, RBAC gates | full (auth) / partial (RBAC) | stub only (7 tests, all `markTestIncomplete`) | n/a (no real assertions) | yes (code+config read) | 🟡 YELLOW | Auth core is solid (throttled login, secure session, CSRF, no debug). But 7 controllers have write endpoints with NO role check (viewer can mutate themes/magazines/templates); invite/updateRole escalation asymmetry; owner-demotion; `role` mass-assignable; zero real test coverage. See §2. |
| 3 | DB schema integrity | full | indirect (RefreshDatabase migrates every test) | yes (66/66 ran clean, 0 pending) | yes (live-DB FK/orphan/index probes) | 🟡 YELLOW | Strong: all migrations reversible+clean, broad sensible FKs, good scoping-index coverage, no orphans in current data. Gaps: page/post delete orphans polymorphic blocks (no FK/cascade/hook); 3 delete-blocking FKs; 1 irreversible drop migration; some missing indexes; no referential-integrity tests. See §3. |
| 4 | Security layers (purifier/MIME/CSP) | partial | yes (Xss + SecuritySanitization pass; unit sanitizer suite is stubs) | partial (top-level XSS covered; 2 vectors uncaught) | yes (concrete PoC of both XSS holes) | 🔴 RED | HTMLPurifier is strong for top-level fields, uploads are well-guarded. But 2 confirmed stored-XSS vectors (allowHtml→strip_tags keeps event handlers; nested array fields never purified) render raw via `{!! !!}`, and there is NO CSP anywhere + NO security headers on published static sites. See §4. |
| 5 | Blade rendering of every block | full (86 types) | new audit script renders all 86 | 84/86 render clean; 2 throw | yes (rendered every view, empty + fixture data) | 🟡 YELLOW | 84/86 blocks render robustly with missing data. `category-header` and `readingprogress` throw on default data (null-safety bugs), and the main publish loop has no per-block try/catch → one bad block fails the whole page. See §5. |
| 6 | Atomic publish / versions / rollback | full (versions) / broken (rollback) | PublishTest = 6 stubs; VersionTest = 5 real pass | versions pass; publish untested | yes (read swap/rollback/prune/job code) | 🔴 RED | Slug-site go-live IS atomic; versioning works+tested. But ROLLBACK is a silent no-op (job ignores target, republishes current); global build prune keeps only 3 newest across ALL sites while live symlinks point into that dir (>3 sites → broken live output); custom-domain/rename deploys are non-atomic; publish has zero real tests. See §6. |
| 7 | Delta publish correctness | full (engine) / partial (write) | yes — References suite 35 pass | yes (35/35) | yes (traced delta path) | 🟡 YELLOW | Staleness/dependency ENGINE is well-built + tested (transitive, cycle-guarded, slug-rename flags referrers); needs_republish lifecycle is safe. But delta OUTPUT is incomplete: no sitemap/RSS/archive rebuild, no deleted/renamed-file removal, no version snapshot; write is non-atomic and mutates the live build in place; SmartPublisher delta engine is dead code. See §7. |
| 8 | SEO output (sitemap/robots/OG/schema) | full | yes (StructuredDataTest 5) | pass | yes (live head on prod) | 🟢 GREEN | Per-page meta/OG/Twitter/canonical + valid sitemap/robots (verified live). JSON-LD URL bug fixed (FIX-B8a: `LocalePaths::urlPath` == canonical). Track F1 (2026-07-12) added `LocalBusiness` (specific subtypes), `BlogPosting`+author, and block-driven `FAQPage`, plus the first SEO tests. Track F2 (2026-07-12) added per-page SEO controls end-to-end: snippet-preview SEO panel in Page+Post editors, canonical override, decoupled index/follow robots toggles, description fallback chain (excerpt → blocks → site default), post author picker, verification-tag slot, publisher logo/sameAs in schema (SeoHeadTest 7 + PostSeoTest 3). See §8 (+ Track F1/F2 addenda). |
| 9 | Asset pipeline (WebP/hashing) | full (broken) | none | no | yes (empirical: read() throws, 0/21 variants, rewrite mangles) | 🔴 RED | Content hashing/dedup + tenant-isolated storage + SVG scrub + base-URL resolution all work. But image variant generation is 100% broken — `AssetService` calls `ImageManager::read()` which doesn't exist in the installed Intervention v4, throws, and is swallowed by a silent catch → 0/21 assets have variants. Even if fixed, `AssetPublisher` mangles variant URLs and never publishes variant files. WebP/responsive pipeline is non-functional end-to-end. See §9. |
| 10 | Block registry contract compliance | full | yes (ExtractorCoverageTest — currently RED) | 85/86 compliant | yes (scripted full cross-reference) | 🟡 YELLOW | Healthiest subsystem so far, near-GREEN: 86 types, all 86 have Blade views, 84/84 React dirs complete (Editor+Preview+definition), extractor registry with an enforcing test. Only gaps: `langswitcher` missing its extractor entry (ExtractorCoverageTest failing), orphan `quote.blade.php` leftover. See §10. |
| 11 | Block editor (CRUD/nesting/undo) | full | yes (Hierarchy 10, InspectorRoundTrip 14, many ValidationTests) | pass | yes (read save path + ran tests) | 🟡 YELLOW | Nesting is server-enforced + well-tested (Section→Row→Column→Module, depth≤4, ≤500 blocks); property round-trip to published CSS tested; block IDs preserved; undo exists. But save is a destructive DELETE-all-then-reinsert that cascades block-scoped `theme_overrides`/`grid_position_blocks` (silent data loss), and there's no concurrency protection — `active_editors` is presence-only → last-write-wins clobbering. See §11. |
| 12 | Magazine editor | full (rebuilt) | yes — 94 pass / 2 fail across DTP+IssueStudio | 94/96 | yes (ran suites, confirmed freeze) | 🟡 YELLOW | NOT "known-broken" anymore — rebuilt. Legacy editor intentionally FROZEN (product decision, no data to migrate); DTP editor is the single current editor (extensive render/PDF/publish/version tests); Issue Studio is the live creation wizard (tests pass). Only 2 failing tests (DTP video-frame QR overlay SVG). See §12. |
| 13 | Block templates / presets | full | broad (Library 7, StylePresets 15, StarterSection 5, StarterTemplate 5, LibraryThumbnail 5, PresetsCopy 4, …) | pass | yes (Builder Experience track D1–D5, live-verified on prod) | 🟢 GREEN | Builder Experience track (2026-07-11→12) built out on top: Library (save/insert/manage + import sanitizer), Global Sections, Style Presets (element+group+default resolution, republish cascade, 11 seeded **system** presets, adopt-as-site-default), ergonomics, layout (12-grid resize + structure panel), 15 starter sections, **Full-Site starter template with AI industry-specific copy + curated images**, and server-rendered Library preview thumbnails. Role auth now present on block-template writes (FIX-A2a); imports validated via `LibraryItemSanitizer`. Concept sprawl (5 template systems) persists as a design note, not a defect. See §13 (+ Builder Experience addendum). |
| 14 | entity_references / dependency graph | — | — | — | — | ⬜ | Session D |
| 15 | Slider system | — | — | — | — | ⬜ | Session D |
| 16 | Menus / theme refs / slug staleness | — | — | — | — | ⬜ | Session D |
| 17 | W3C token engine | full | yes (ThemeEngineTest 20) | pass | yes (T1 review + hardening) | 🟢 GREEN | W3C `document` engine works; T1.2 closed the token-consumption leaks (~14 blocks + shadows + inputs now tokenized), added themeless defaults, symmetric single-file bundle export/import, RLS WITH CHECK. Legacy flat-`config` path still coexists (documented in docs/THEME-ENGINE-REVIEW.md) — deprecation deferred. |
| 18 | Theme Studio live editing | full (server) | yes (fidelity test) | pass | yes (T1 review + fix) | 🟡 YELLOW | Studio iframe now emits the SAME CSS surface as published (unified on DesignTokenGenerator — was 70 vs 186 vars). Remaining: client postMessage instant-patch still semantic-only (reloads correct on save); Studio previews stock frames, not real pages; no autosave. |
| 19 | Theme switching | full | yes (ThemeEngineTest + ThemeRlsTest) | pass | yes (T1 review + fix, live 5/5) | 🟢 GREEN | assign() now flags all published pages+posts stale (was silent no-op → old CSS forever); per-page + clear flag their page; fork() no longer silently switches (opt-in ?activate=1); theme_overrides scoped by theme_id (no cross-theme bleed). Live-verified on sys.ensodo.eu. |
| 20 | Cinematic layout (wabisabi4) | — | — | — | — | ⬜ | not theme-scoped (per-page experience_mode); deferred to a rendering session |
| 20b | First-party themes (T2) | full | n/a (seeder + live picker) | pass | yes (5 distinct + live preview) | 🟢 GREEN | 5 hand-crafted themes (Ensō/Journal/Ledger/Atelier/Hearth) — distinct palette+type+radius+shadow AND layout personality; live theme-picker preview (real render per theme). |
| 20c | Theme Wizard (T3) | full | yes (ThemeWizard suites 32) | pass | yes (W0-W5 + live runs) | 🟢 GREEN | Conversational AI theme creation from URL/screenshot/description → nudge → accept into an editable theme. "Inspired not copied" guardrails structurally enforced (open-font substitution + tokens-only schema). URL-screenshot path needs a CLI queue worker (proc_open off in php-fpm); upload+conversation cover it. Docs: THEME-WIZARD.md, THEMES-CHOOSING.md. |
| 21 | Playwright audit suite | — | — | — | — | ⬜ | Session F |
| 22 | PageSpeed on staged output | — | — | — | — | ⬜ | Session F |
| 23 | Mobile responsiveness | — | — | — | — | ⬜ | Session F |
| 24 | Error handling & logging | — | — | — | — | ⬜ | Session F |
| 25 | Test suite overall | — | — | — | — | ⬜ | Session F |

---

## §1 — Tenancy & RLS  🔴 RED  (audited 2026-07-06)

### Intended behaviour
Two-level tenancy: `Tenant → Site → content`. `users.tenant_id` binds a user to a tenant; content binds to a `Site` via `site_id`, and the site binds to a tenant via `sites.tenant_id`. The design intends **two isolation layers**: (A) PostgreSQL Row-Level Security keyed on a per-connection GUC `app.current_tenant_id`, and (B) application-level Eloquent global scopes (`TenantScoped` / `SiteScoped` traits).

### What was checked
- Read the RLS migrations, the tenant-resolution middleware, the scope traits, and the models/controllers for the magazine, theme, and menu subsystems.
- Ran both isolation test suites against the test DB (`cms_saas_platform_test`).
- Probed the **live dev DB** (`cms_saas_platform`) directly to observe RLS enforcement per table (ground truth, independent of migration source).
- Verified the app's DB role privileges.

### Verification evidence
- **Tests pass but cover only the protected tables.** `tests/Feature/Security/TenantIsolationTest.php` (7 tests) + `tests/Feature/References/RlsIsolationTest.php` (2 tests) → **9/9 passing** (21.8s). They exercise `sites`, `pages`, and `entity_references` — all of which *are* correctly protected. They give false confidence about the subsystem as a whole.
- **DB role is correctly restricted.** `cms_saas` is `rolsuper=f`, `rolbypassrls=f`. So RLS *is* enforced for the app role — the isolation failures below are NOT a superuser/BYPASSRLS problem.
- **Reproducible cross-tenant read at the DB level** (as the app's own role, `cms_saas`):
  ```sql
  SET app.current_tenant_id = '00000000-0000-0000-0000-000000000000';  -- a tenant that owns nothing
  SELECT count(*) FROM sites;            -- 0   ✅ (FORCE on)
  SELECT count(*) FROM magazines;        -- 7   ❌ leaked
  SELECT count(*) FROM magazine_issues;  -- 12  ❌ leaked
  SELECT count(*) FROM mag_pages;        -- 2   ❌ leaked
  SELECT count(*) FROM menus;            -- 8   ❌ leaked (no RLS at all)
  ```

### Root causes (three compounding defects)

**Defect 1 — `TenantScoped` / `SiteScoped` traits are dead code (isolation is RLS-only).**
`app/Domain/Concerns/TenantScoped.php` and `SiteScoped.php` define an Eloquent global scope + auto-fill, but **no model applies either trait** (`grep -rn 'use TenantScoped\|use SiteScoped\|addGlobalScope' app/` returns only the trait files themselves). The advertised "second layer after RLS" does not exist at runtime. Therefore isolation rests **entirely** on Postgres RLS, and any table where RLS is absent or bypassable has **zero** isolation. `Site` is the sole model with any app-level filtering, and it's a `resolveRouteBinding()` override (`app/Models/Site.php:22`), not a global scope.

**Defect 2 — 7 tenant tables (+4 child tables) have RLS ENABLED but not FORCED → the owner role bypasses them.**
Postgres does not apply RLS to a table's **owner** unless `FORCE ROW LEVEL SECURITY` is set. The app connects as `cms_saas`, which **owns every table**. The base migration `database/migrations/0001_01_01_000015_enable_row_level_security.php` correctly pairs `ENABLE` with `FORCE` (lines 24-25, 35-36, …). Later migrations omitted `FORCE`:
  - `2026_04_17_000001_create_magazine_tables.php:66-68` — `magazines`, `magazine_pages`, `magazine_elements` (ENABLE only)
  - `2026_04_17_000004_create_issue_composer_tables.php:97-100` — `magazine_issues` (+ `issue_content_items`, `magazine_curation_runs`, `issue_design_system`)
  - plus `layouts`, `mag_pages`, `mag_elements`, `mag_styles`, `theme_assignments`, `theme_overrides`, `theme_versions`

  Live DB confirms `relrowsecurity=t, relforcerowsecurity=f` on: `layouts, mag_elements, mag_pages, mag_styles, magazine_elements, magazine_issues, magazine_pages, magazines, theme_assignments, theme_overrides, theme_versions`. Their tenant_isolation policies exist but are inert for the app.

**Defect 3 — 14 tenant-bearing tables have NO RLS policy at all.**
Confirmed by live DB (`relrowsecurity=f`) on tables carrying `site_id`/`tenant_id`: `menus`, `menu_items`(via menu), `tags`, `taggables`, `redirects`, `grids`, `grid_assignments`, `grid_positions`, `position_overrides`, `global_blocks`, `popups`, `activity_logs`, `page_views`, `search_queries`, `site_templates`, `theme_customizations`, `theme_templates`, `users`. With no RLS and no app scope, isolation depends entirely on each controller scoping manually.

### Full protection matrix (live DB, ground truth)
| Status | Tables |
|--------|--------|
| ✅ PROTECTED (RLS forced) | sites, pages, posts, categories, assets, deployments, themes, block_templates, blocks, page_versions, deploy_artifacts, entity_references, sliders, issue_studio_sessions, issue_studio_spreads |
| ❌ RLS NOT FORCED (owner bypass) | magazines, magazine_pages, magazine_elements, magazine_issues, mag_pages, mag_elements, mag_styles, layouts, theme_assignments, theme_overrides, theme_versions |
| ❌ NO RLS | menus, menu_items, tags, taggables, redirects, grids, grid_assignments, grid_positions, position_overrides, global_blocks, popups, activity_logs, page_views, search_queries, site_templates, theme_customizations, theme_templates, users |

### Concrete exploitable defects

**D1 (blocker) — Cross-tenant IDOR on menus.** `app/Http/Controllers/Api/V1/MenuController.php:52-58` `show(Site $site, Menu $menu)` authorizes `view` on the route-bound `$site` (attacker's own site, passes), then returns `$menu` resolved by its **global id** with no `child.site_id == site.id` check. Routes declare no `->scopeBindings()` (`routes/api.php:141`), and `menus` has no RLS. A user of tenant A calling `GET /api/v1/sites/{ownSiteId}/menus/{foreignMenuId}` reads another tenant's menu; `update`/`destroy`/`syncItems` (`:61,:94,:124`) allow cross-tenant **write/delete**. The Tag, Redirect, and Grid controllers share this exact shape over other NO-RLS tables. **Severity: blocker.**

**D2 (blocker) — Cross-tenant write on magazines.** Same unscoped-nested-binding pattern: `sites/{site}/magazines/{magazine}/pages` (`routes/api.php:130` → `MagazineController::savePages`) authorizes only `$site`; the `{magazine}` binding resolves any magazine by id because `magazines` is not FORCE-protected and no app scope exists. `savePages` then rewrites/deletes the foreign magazine's pages. **Severity: blocker.**

**D3 (major) — Cross-site, same-tenant IDOR.** Even for the PROTECTED tables, nested `show(Site $site, Page $page)` etc. (`PageController:50`, `PostController:58`, `AssetController:51`, `CategoryController:30`) never assert `page.site_id == site.id`. RLS blocks cross-*tenant*, but a user can read another **site of their own tenant** by id. Lower impact (same tenant) but an authorization gap. **Severity: major.**

### Secondary findings (verify in their own subsystem sessions)
- **`SET` vs `SET LOCAL`.** Every tenant setter (`app/Http/Middleware/TenantScope.php:21`, `SetTenantFromAuth.php:20`, jobs) uses session-level `SET`, which persists on a reused connection. Low risk under php-fpm; a **context-leak hazard under Octane or long-lived queue workers**. (Session A #2 / Session B)
- **`ProcessScheduledContentJob.php:18`** does `Site::withoutGlobalScopes()->...->get()` before any tenant GUC is set. With RLS enforced and no prior context, the `sites` policy (`current_setting(..., true)` → NULL) returns **0 rows** — scheduled cross-tenant publishing may silently no-op, or depend on leaked connection context. (Session B — publish pipeline)
- **`themes` RLS weakened.** `migrations/2026_05_17_200323_fix_themes_rls_allow_system_themes.php` drops `WITH CHECK` and the `, true` missing_ok flag, and exposes shared `is_system` rows to all tenants. (Session E — theme engine)
- **Public routes hardcode "first tenant"** — `routes/web.php:29,50` do `SELECT id FROM tenants LIMIT 1` for public media/font serving. Safe only in single-tenant deployments. (Session B)
- **`users` NO-RLS is mitigated** — `UserController` (`:20,:45,:84`) filters by `tenant_id` explicitly; `PasswordResetController` is by-email by design. No unscoped user listing found.

### Rating rationale
Isolation is real and tested for core content (sites/pages/posts/assets/categories/blocks/entity_references), but is **absent or owner-bypassable for the entire magazine and theme-customization subsystems and for menus/tags/redirects/grids**, with **no application-level backstop**, and at least two **confirmed cross-tenant write** vectors. A multi-tenant platform with cross-tenant write IDOR cannot be rated above RED regardless of passing tests.

---

## §2 — Auth, roles, RBAC gates  🟡 YELLOW  (audited 2026-07-06)

### Intended behaviour
Session-based SPA authentication (Sanctum stateful cookie over the `web` session guard) with a 5-level role hierarchy — `viewer(0) < author(1) < editor(2) < admin(3) < owner(4)` — enforced via Eloquent Policies (`$this->authorize()`), inline `hasMinimumRole()` checks, and a `role:<name>` route middleware (`EnsureRole`).

### What was checked
- Read the auth config (`config/auth.php`, `config/sanctum.php`, `config/session.php`), `bootstrap/app.php` middleware wiring, `AuthController`, `LoginRequest`, `PasswordResetController`, `UserController`.
- Read the `User` role model, all 7 Policies, the `AuthorizesWithTenant` trait, and `EnsureRole`.
- Swept all 40 API controllers comparing write-method count to authorization-call count; inspected every controller with write methods but zero auth calls.
- Verified production `.env` security flags and ran the auth test suite.

### What works (verified)
- **Session/auth hardening is correct.** `AuthController::login` uses `Auth::attempt` + `session()->regenerate()` (fixation-safe); `logout` invalidates + regenerates token. Login is throttled **three ways** (route `throttle:5,1` at `routes/api.php:29`, `LoginRequest::ensureIsNotRateLimited` 5/min keyed by email+IP, and `RateLimiter::hit/clear` in the controller).
- **Production env is safe.** `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN=sys.ensodo.eu`, `SANCTUM_STATEFUL_DOMAINS=sys.ensodo.eu`. CSRF enforced via the Sanctum stateful flow. (Note: `.env.example` leaves `SESSION_SECURE_COOKIE` unset — a deploy footgun, but the live env is correct.)
- **No self-registration** endpoint and **no `Gate::before` super-admin bypass** — owner privilege is expressed purely through hierarchy ordering.
- **Core content is properly policy-gated.** Site/Page/Post/Category/Tag/Asset/Block/Menu/Magazine(save)/Publish controllers all call `$this->authorize()` with tenant-aware policies (create/update→editor, delete→admin, site delete/reset→owner, publish→editor). User/System/Debug management is admin-gated inline; Issue Studio is behind `role:admin` middleware.

### Defects

**D1 (major) — 7 controllers expose write endpoints with NO role authorization.** They rely only on the tenant-checked `Site $site` binding, so **any authenticated tenant user — including a `viewer` (intended read-only) — can create/update/delete**:
| Controller.method | File:line |
|---|---|
| `MagazineIssueController::store/update/destroy` | `MagazineIssueController.php:23,41,57` (index authorizes view; writes do not) |
| `ThemeEngineController::update/fork/assign/saveOverrides/import/restoreVersion` | `ThemeEngineController.php:79,115,178,235,287,338` |
| `ThemeTemplateController::store/update/destroy` | `ThemeTemplateController.php:42,77,102` |
| `MagStyleController::store/update/destroy` | `MagStyleController.php:28,59,79` |
| `BlockTemplateController::store/destroy` | `BlockTemplateController.php:25,46` |
| `DtpDocumentController::save` | `DtpDocumentController.php:37` (feature-flag gate only) |
| `DtpVersionController::restore` | `DtpVersionController.php:34` (feature-flag gate only) |
| inline route closure `sites/{site}/apply-template` | `routes/api.php:237` (mutates site content, no role check) |
Note the double jeopardy with §1: several of these tables (`magazine_issues`, `mag_styles`, `theme_*`) also lack forced RLS, so the unauthorized write is *also* cross-tenant where the child is bound by global id. **Severity: major** (within-tenant privilege bypass across the whole theme + magazine subsystem; cross-tenant where it compounds with §1).

**D2 (major) — Privilege-escalation asymmetry in user management.** `UserController::updateRole` requires `isOwner()` to assign the `admin` role (`:93`), but `UserController::invite` validates `role in editor,admin,viewer,author` with **no owner check** (`:41`) — so a plain **admin can create a brand-new admin account**, escalating admin population beyond the intended owner-gated boundary. **Severity: major.**

**D3 (major) — `updateRole` can demote the tenant owner.** `updateRole` only blocks *setting* the `admin` role for non-owners; it has no guard preventing an admin from targeting the **owner** and setting them to `editor`/`viewer` (`UserController.php:78-99`). `destroy` explicitly protects the owner (`:112`) but `updateRole` does not — an admin can strip the owner's control. **Severity: major (integrity/escalation).**

**D4 (major) — `role` is mass-assignable.** `User::$fillable` includes `'role'` (`app/Models/User.php:18`). Current writers use explicit arrays, but any future `User::create/update($request->all())` silently becomes a privilege-escalation hole. **Severity: major (latent).**

**D5 (major) — Zero real auth/RBAC test coverage.** The only auth test file, `tests/Feature/Auth/LoginTest.php`, has all 7 tests stubbed with `markTestIncomplete()` (0 assertions — the suite reports WARN/incomplete, not PASS). There are **no policy tests** and no tests asserting that a `viewer` is denied writes. Per the honesty rule this subsystem cannot be GREEN. **Severity: major.**

### Secondary findings
- **Password-reset email is not sent** — the mail dispatch in `PasswordResetController::forgotPassword` (~`:30`) is commented out; reset is non-functional end-to-end in production. (minor/functional)
- **Invite acceptance is not implemented** — `invitation_token` is generated (`UserController::invite`) but **no server-side route consumes it**; invited users cannot set a password / complete signup. Also `UserController::index` returns raw `invitation_token` values in the list response (`:21`). (minor/functional + hygiene)
- **No email verification, no 2FA, no account lockout** beyond rate limiting. Password strength is only `min:8` on reset. (minor — acceptable for an admin CMS, note for launch)
- **No CSP header** (SecurityHeaders sets X-Frame-Options/X-Content-Type-Options/Referrer-Policy but not Content-Security-Policy) — defer to §4 (security layers).

### Rating rationale
The authentication core is genuinely well-built and cross-tenant reads are still held for the RLS-protected surface, so this is not RED. But **within-tenant authorization is enforced inconsistently** — an entire class of write endpoints (theme + magazine + templates) skips the role system, two user-management escalation paths exist, `role` is mass-assignable, and there is **no functional test coverage** to catch regressions. That is a solid 🟡 YELLOW with major gaps that must close before public launch.

---

## §3 — DB schema integrity  🟡 YELLOW  (audited 2026-07-06)

### What was checked
- `php artisan migrate:status` (read-only) on the dev DB; migration source read across all 66 files.
- Live-DB probes (as the app role `cms_saas`): full FK inventory with `ON DELETE` rules, index coverage on every `site_id`/`tenant_id`, orphan queries against seeded data, PK-type consistency, RLS-policy expressions.
- Verified the delete paths for polymorphic children (blocks) in controllers/models.
- **No migration command was run** (read-only track). Migration cleanliness is evidenced indirectly by the full test suite, which uses `RefreshDatabase` (migrates a fresh test DB on every run).

### What works (verified)
- **All 66 migrations ran clean, 0 pending**, and **every migration defines a `down()`** (only one is a deliberate no-op — see D3). Continuously re-verified by `RefreshDatabase`.
- **Broad, sensible FK coverage.** ~100 FK constraints: `CASCADE` for ownership edges (site→content, magazine→pages, issue→children), `SET NULL` for optional refs (author, layout, grid, parent). Live DB confirms the rules.
- **Good scoping-index coverage.** Of all `site_id`/`tenant_id` columns, only 4 lack an index (see D4).
- **No orphaned rows** in the current seeded data (blocks→page/post, taggables→tag, magazine_pages→magazine all clean).
- **Enum enforcement at DB level** for the important status columns (`sites.status`, `pages.status`, `posts.status`, `users.role`, `deployments.type/status`, `sliders.status` are Laravel `enum()` → CHECK constraints).
- **Blocks polymorphic RLS was correctly extended** to 4 blockable types (page/post/template/slider) — the 88 non-page/post blocks are legitimately scoped, not a leak.

### Defects

**D1 (moderate, confirmed) — Page/Post deletion orphans polymorphic blocks.** `blocks.blockable_id` is polymorphic with **no DB FK**, and there is **no `static::deleting` cascade** on `Page`/`Post`/`Slider`/`ThemeTemplate` (only `Layout` has a deleting hook, for a different purpose). `PageController::destroy` (`PageController.php`) simply calls `$page->delete()` with no block cleanup. So every hard-deleted page/post/slider/template leaves its `blocks` rows (and `taggables` rows) permanently orphaned — dead rows that RLS still counts and that accumulate forever. Currently latent (dev DB has 0 orphans because nothing has been deleted yet), but the code path is wrong. **Severity: moderate (data integrity, slow leak).**

**D2 (minor, latent) — 3 delete-blocking FKs (`NO ACTION`).** `deployments.triggered_by → users`, `page_versions.published_by → users`, and `magazine_issues.tenant_id → tenants` have no `onDelete`. Normal user deletion is safe because `User` uses `SoftDeletes` (no hard DELETE fires the FK), but a **tenant hard-teardown** or any real user DELETE will be blocked/ordering-sensitive. **Severity: minor/latent.**

**D3 (minor) — One irreversible migration.** `2026_07_05_210001_drop_legacy_issue_composer_wizard_tables.php` drops 5 tables (`mag_wizard_*`, `issue_content_items`, `magazine_curation_runs`, `issue_design_system`) with an empty comment-only `down()` — rollback does not recreate them (permanent loss). Intentional cleanup of legacy tables, but flagged for the record. Also `2026_05_14_rename_quote_to_pullquote` has a lossy `down()` that over-reverts. **Severity: minor (hygiene).**

**D4 (minor) — Missing indexes on FK/scoping columns.** Postgres does not auto-index FKs. Notably unindexed: `themes.site_id` (queried on every render), `deploy_artifacts.{deployment_id,page_id,post_id}` (table has zero indexes), `menu_items.{page_id,post_id,category_id,parent_id}`, `global_blocks.site_id`, `popups.site_id`, `sites.active_theme_id`, plus many optional-ref columns. These table-scan on join/cascade. **Severity: minor (perf, grows with data).**

**D5 (minor) — Some `tenant_id` columns have no FK to `tenants`.** `layouts.tenant_id`, the three `theme_assignments/overrides/versions.tenant_id`, and `issue_studio_*` tenant columns have no referential constraint — RLS relies solely on the app-set session var, so a bad `tenant_id` write has no DB guard. **Severity: minor.**

**D6 (info) — PK-strategy inconsistency.** The schema is uniformly UUID except `page_views` which uses a bigint auto-increment PK (`2026_04_16_..._create_analytics_tables.php:12`) while carrying a uuid `site_id` FK. No join type-mismatch (FK targets are uuid), just an outlier. `taggables` uses a composite varchar PK (normal for a pivot). **Severity: cosmetic.**

**D7 (minor) — Several enum-like columns lack DB CHECK** (`theme mode`, `magazine_frames.frame_type`, `entity_references.source_type/target_type`, `grid_assignments.assignable_type`, `activity_logs.action`) — validated only in app code. **Severity: minor.**

Cross-ref §1: the `blocks` and `themes` RLS policies use `current_setting('app.current_tenant_id')` **without** the `,true` missing_ok flag, so those tables **throw** (not return empty) when queried with no tenant context — a robustness hazard for public-render/job code paths.

### Rating rationale
The schema is genuinely well-engineered and continuously migration-tested, so it is far healthier than §1/§2. It is not GREEN because of one **confirmed data-integrity defect** (polymorphic orphan-on-delete that will silently accumulate dead rows in production) plus the absence of any referential-integrity/orphan tests. Everything else is minor perf/hygiene. Honest rating: 🟡 YELLOW (healthy end).

---

## §4 — Security layers (sanitization / uploads / headers)  🔴 RED  (audited 2026-07-06)

### What was checked
- Read `SanitizationService` (3 HTMLPurifier profiles), traced where it is invoked in the publish path (`BuildPageService::renderBlock`), and enumerated the 40+ `{!! !!}` raw sinks in `resources/views/blocks/*.blade.php`.
- Read `SecurityHeaders` middleware, the published-output generator (`PublishSiteJob`), CORS config, upload validation (`UploadAssetRequest` + `AssetController` + `AssetService`).
- Ran the security test suites and produced concrete PoCs for the two XSS vectors.

### What works (verified)
- **HTMLPurifier is well-configured** (`SanitizationService.php`): a rich profile (allowlisted tags, `URI.AllowedSchemes` = http/https/mailto/tel so `javascript:` is blocked), a strict profile (strips all HTML), and a magazine profile with a constrained CSS property allowlist. `purifyRich`/`purifyMagazine` are shared by the magazine/DTP renderers.
- **Top-level field sanitization is correct and tested.** `XssTest` (6 pass) and `SecuritySanitizationTest` (4 pass) confirm script tags, event handlers, `javascript:` URLs, iframe/object, and SVG script/handler/entity-expansion payloads are stripped for top-level string fields and the magazine path.
- **Upload validation is solid** (`UploadAssetRequest`): a hard `BLOCKED_EXTENSIONS` denylist (`php`, `phtml`, `sh`, `exe`, `htaccess`, `env`, `jsp`, `asp`…) applied even over site-configured allowlists; content-sniffed MIME (`getMimeType`) cross-checked against extension; `getimagesize()` real-content validation for rasters; an SVG `<script>`/`<foreignObject>`/`on*=` scan; 100 MB cap; `authorize('upload')` role gate; stored on a dedicated `assets` disk (not the app webroot).
- **Admin HTTP headers are reasonable**: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` locking camera/mic/geo, `X-Frame-Options: DENY` (SAMEORIGIN only for studio/preview). CORS is scoped to `api/*` and `*.ensodo.eu` origins.

### Defects

**D1 (blocker) — Stored XSS via `allowHtml` → `strip_tags`.** `SanitizationService::sanitizeBlock` (line 127-129) sanitizes the `allowHtml` inline path with `strip_tags($value, '<br><em><strong><span>')`. **`strip_tags` does not remove attributes.** PoC (executed):
`strip_tags('<span onmouseover="alert(document.cookie)" onclick="steal()">hi</span>', '<br><em><strong><span>')` → returns the string **unchanged**, event handlers intact. Any block with `allowHtml=true` in a `text/heading/title/quote` field (editor-settable) injects live event handlers that render raw via `{!! !!}` (e.g. `heading.blade.php:50`). **Severity: blocker (stored XSS).**

**D2 (blocker) — Stored XSS via unsanitized nested array fields.** `sanitizeBlock` iterates `foreach ($data as $key => $value)` and, at line 119, **passes through any non-string value untouched** (`if (!is_string($value)) { $sanitized[$key] = $value; continue; }`). Blocks that store HTML inside arrays — `accordion` (`items[].content`), `catalog` (`items[].content`/`contentSecondary`) and similar — are therefore **never purified**, then rendered raw: `accordion.blade.php:32` `{!! $item['content'] !!}`, `catalog.blade.php:77,81`. PoC confirmed: accordion's definition stores `items: [{content: '<p>Answer</p>'}]`; an editor can set `items[].content = '<img src=x onerror=alert(1)>'` and it publishes verbatim. Reachable by any editor, executes on the published static site **and in the admin preview origin** (`sys.ensodo.eu`), where it can run with an admin/owner's session → within-tenant privilege escalation. **Severity: blocker (stored XSS).**

**D3 (major) — No Content-Security-Policy anywhere, and NO security headers on published sites.** `SecurityHeaders` sets no CSP and no HSTS, and it only decorates Laravel admin responses. The published static tenant sites get **zero** security headers — `PublishSiteJob` writes an `.htaccess` containing only redirect RewriteRules (`:488-502`). So the D1/D2 XSS payloads execute on published output with **no CSP backstop**, and there is no HSTS on admin or published output. **Severity: major (removes the defense-in-depth that would contain D1/D2).**

**D4 (major) — Sanitizer unit tests are stubs.** `tests/Unit/Services/SanitizationServiceTest.php` has all 5 tests as `markTestIncomplete()` (WARN, 0 assertions). The passing feature tests only exercise top-level string fields, so **both D1 and D2 are entirely uncovered**. **Severity: major.**

**D5 (major, by-design) — `html-embed` block is a full stored-XSS primitive.** `BuildPageService::renderBlock:311` skips sanitization for `html-embed`, and `HtmlEmbedBlockDefinition` sets `HTML.Allowed => '*'`; the content renders raw at `html-embed.blade.php:17`. Any editor can inject arbitrary `<script>`/`<iframe>`/`<style>` into a published page. This is deliberate ("trusted HTML"), but with **no CSP** (D3) there is zero mitigation and it's available to the lowest write role. Confirm against the intended trust model; gate to owner/admin at minimum. **Severity: major.**

### Secondary findings
- **Sanitization runs on render, not on write.** Block writes (`BlockController.php:39-52`) store **raw** HTML in the DB; safety depends on every read/render path calling `sanitizeBlock`/`purify`. Any future render path that emits stored data raw becomes a live sink. On-write purification would add defense-in-depth. (moderate)
- **Raw theme-controlled JS/CSS injected into every published page** — `{!! $customCss !!}`, `{!! $headScripts !!}`, `{!! $bodyScripts !!}`, hook scripts in `grid-layout.blade.php` / `layouts/cytechno.blade.php`. Server/theme-sourced (not editor input) so trusted-by-origin, but unsanitized and, again, no CSP backstop. (moderate)
- **CORS pattern** `/https?:\/\/(.*\.)?ensodo\.eu$/` with `supports_credentials: true` and `allowed_headers: ['*']` allows **any** `*.ensodo.eu` subdomain over **http or https** to make credentialed requests — a compromised/attacker subdomain could ride user credentials against the API. Tighten to https + the specific admin origin. (minor-moderate)
- **No image dimension / pixel-count cap** on uploads (`UploadAssetRequest`) → decompression-bomb DoS risk; original (non-SVG) image bytes stored verbatim (EXIF retained; only derived variants are re-encoded). (minor)
- **`X-XSS-Protection: 0`** is intentional/correct (the legacy header is deprecated). Not a defect.

### Strengths worth recording
- **SVG handling is genuinely strong** (corrected): a dedicated DOM-based `app/Domain/Assets/Services/SvgSanitizer.php` (strips script/foreignObject/iframe/embed/animate/on*-handlers, restricts URL attrs, kills `url()`/`expression`/`@import`, rejects `<!ENTITY>` for XXE/billion-laughs, loads with `LIBXML_NONET`) applied at upload in `AssetService`, **double-gated** by the `UploadAssetRequest` regex scan.
- Consistent `javascript:`/`data:`/`vbscript:` scheme blocking across `BlockStyle::safeUrl`, `MenuItem::resolveUrl`, `MenuRenderer`, `DtpRenderService`. Executable-extension denylist. Tenant-isolated asset storage (`sites/{siteId}/assets/`). No hardcoded secrets found.

### Rating rationale
Upload hardening and top-level HTMLPurifier usage are genuinely good, but this subsystem ships **two confirmed, editor-reachable stored-XSS vectors** whose output lands unescaped on public sites and in the admin preview origin, with **no CSP anywhere** to contain them and **no test coverage** over the affected code paths. For a public-facing publishing platform that is a 🔴 RED — these gate launch and should be the highest-priority security fixes alongside the §1 tenancy work.

---

## §5 — Blade rendering of every registered block  🟡 YELLOW  (audited 2026-07-06)

### What was checked
Wrote `audit/render_blocks.php` + `audit/render_blocks2.php` (read-only): enumerate all registered block types from `BlockRegistry`, and render each block's Blade view twice — with **empty data** and with a **fixture** — under a real seeded `Site`, catching throws and empty output. This is the first exhaustive render sweep; no prior test renders every block.

### Results
- **86 registered block types; all 86 have a Blade view; 84 render cleanly** with missing/empty data (they use `?? default` guards correctly).
- **2 blocks throw on default data** (confirmed real null-safety bugs):
  - `category-header.blade.php:23` — `in_array($data['textAlign'] ?? 'center', [...]) ? $data['textAlign'] : 'center'`: the null-coalesce guards the `in_array` condition but the ternary's true-branch returns the **unguarded** `$data['textAlign']`, so a block with no `textAlign` throws `Undefined array key`.
  - `readingprogress.blade.php:18` — `$color = $data['color'] ?: '#3b82f6'` uses `?:` (elvis) instead of `??`, so a missing `color` key raises `Undefined array key` → thrown ErrorException.
- The 7 additional throws in the first pass were **fixture artifacts** (my generic fixture injected `columns`/`items`/`rows` as arrays into blocks that read those as scalars, e.g. `stats` reads `columns` as a number at `stats.blade.php:21`). A clean scalar-only fixture reproduced **only** the 2 real throws. Recorded so the false positives aren't mistaken for defects.
- `langswitcher` renders empty output (0 bytes) with no locales configured — legitimate (nothing to switch), not a defect.

### Defect

**D1 (moderate) — 2 blocks crash on default data, and the publish loop does not isolate a failing block.** The main page build loop (`BuildPageService.php:220-221`) calls `$renderedBlocks .= $this->renderBlock($block, $site)` with **no per-block try/catch**. So when `category-header` or `readingprogress` (or any future fragile block) throws on missing data — reachable via API/import/template creation, data-shape drift, or a field added before defaults — the **entire page publish fails**, not just that block. The two null-safety bugs are one-line fixes; the missing isolation is the systemic amplifier. **Severity: moderate (publish reliability).**

### Rating rationale
Block rendering is broadly healthy — 84/86 types render robustly and the shared-property/overlay plumbing works. It is not GREEN because two blocks have confirmed crash-on-default-data bugs, there is no per-block error isolation at publish time (one bad block breaks a whole page), and until this audit no test rendered the full block set. 🟡 YELLOW.

---

## §6 — Atomic publish / versions / rollback  🔴 RED  (audited 2026-07-06)

### What was checked
Read the full publish flow (`PublishController` → `PublishOrchestrator` → `PublishSiteJob` → `DeployService` → `SymlinkDeployStrategy`/`RenameDeployStrategy`), the retention/prune functions, rollback wiring, `page_versions` snapshot/restore, and the concurrency guard. Ran `PublishTest` and `VersionTest`.

### What works (verified)
- **Slug-site go-live is genuinely atomic.** `SymlinkDeployStrategy::deploy` builds `{publicPath}.new` → `symlink($staging, .new)` → `rename($new, $publicPath)` (`:20,:33`). `rename(2)` of a symlink on the same filesystem is atomic — no broken window.
- **Per-site publish is guarded** by a Postgres advisory lock (`AdvisoryLock::run("publish_site_{id}")`, `PublishOrchestrator.php:35`) plus an active-deployment `exists()` pre-check (`:27-33`).
- **Versioning works and is tested.** `createVersion` snapshots `blocks_snapshot` + `seo_snapshot` (block JSON + SEO, per page/post) on every publish and on block save (`PublishSiteJob.php:219-234`). `VersionController` restore writes blocks back to the DB. `VersionTest` = **5 real passing tests** (list/show/restore for page + post, editor role). This is the healthy part of the subsystem.

### Defects

**D1 (blocker) — Rollback is a silent no-op.** `PublishController::rollback` → `PublishOrchestrator::rollback` creates a `type='rollback'` deployment and dispatches `PublishSiteJob::dispatch($deployment, 'rollback', $target)`, but **`PublishSiteJob::handle()` never reads `$this->rollbackTargetId`** (stored at `:38,:49`, never used in `handle`). So "roll back to deployment X" just runs a **normal full rebuild from current DB state** — it does not restore the target build or snapshot. All the real rollback code (`DeployService::rollback`, `SymlinkDeployStrategy::rollback`, `RenameDeployStrategy::rollback`) is **dead — never invoked**, and the `rolled_back` status is never set. No test references rollback at all. Users get a success response while nothing is actually reverted. **Severity: blocker (data/operational — a broken deploy cannot be undone).**

**D2 (blocker) — Global build pruning deletes live sites' build targets.** The live symlink points **directly into** `storage/app/builds/{deploymentId}` (no copy to a stable location). `PublishSiteJob::cleanOldBuilds` (`:665-677`, runs every publish) globs the **global** `storage/app/builds` across **all sites/tenants**, sorts by mtime, and deletes everything beyond the **3 newest** (`$dirs->slice(3)`). `SymlinkDeployStrategy::cleanOldBuilds` does the same keeping 5. So once there are more than 3 build dirs globally — i.e. more than ~3 active sites, or one site publishing 3× — the older sites' **live build directories are deleted**, leaving their live symlinks dangling → 404/500 on those tenants' published sites. Retention is global where it must be per-site. **Severity: blocker (multi-tenant availability).**

**D3 (major) — Non-atomic deploy for custom-domain sites and the rename fallback.** Custom-domain sites (real customer domains) never use symlink — `DeployService::deployLocal` routes them to `copyDeploy()`, a per-file `File::copy` into the live `public_html` (`DeployService.php:81-95,138-164`). `RenameDeployStrategy` copies each file to `.tmp`+rename one by one (`:40-48`) — atomic per file but the site is **half-updated across the loop**, and it never deletes files for removed pages (stale/deleted pages stay live). On mid-deploy failure the live site is left half-written (the `catch` only sets `status='failed'`, no FS revert). **Severity: major (broken-window on every custom-domain publish).**

**D4 (major) — `cleanUnpublishedPosts` mutates the live docroot mid-build.** `PublishSiteJob:569-631` deletes directories from the **live** `publishing.public_path` *before* the atomic swap (`:173`, swap at `:177`), and scans the **shared parent** `sites/` dir rather than a per-site root — so its deletes are not cleanly tenant-scoped and touch live output during the build for all strategies. **Severity: major.**

**D5 (major) — Concurrency race for slow builds.** The advisory lock only wraps deployment-record *creation*, not `PublishSiteJob::handle()` on the worker. The guard also **hard-deletes** any deployment stuck `queued/building/deploying` for >5 min (`PublishOrchestrator.php:20-24`) — so a build legitimately taking >5 min has its row deleted, letting a second publish start and race the live docroot / symlink swap. The stale-batch promote path (`StaleContentController`, `RepublishStaleJob`) takes no lock at all and writes per-file to live. **Severity: major.**

**D6 (major) — Zero real publish test coverage.** `tests/Feature/Publishing/PublishTest.php` is **6 `markTestIncomplete` stubs** (0 assertions). Nothing exercises the deploy swap, retention, rollback, or concurrency. Only versioning (`VersionTest`) is genuinely tested. **Severity: major.**

### Secondary
- **`deploy_artifacts` is a dead table** — no `DeployArtifact::create` anywhere; the `output_path`/`content_hash` manifest columns are never populated, so there is no per-file manifest or content hashing in the pipeline (relevant to §7 delta publish). (info/major)
- Custom-domain path sanitizes the domain against traversal (`DeployService.php:85-87`) — good.

### Rating rationale
The atomic go-live for slug sites and the versioning subsystem are correct and (for versions) tested — but **rollback does not work at all**, **routine publishing deletes other tenants' live sites** once more than ~3 sites exist, custom-domain publishes are non-atomic, the live docroot is mutated mid-build, concurrency races on slow builds, and the publish pipeline has no real test coverage. These are production-availability and data-safety blockers. 🔴 RED.

---

## §7 — Delta / partial publish correctness  🟡 YELLOW  (audited 2026-07-06)

### What was checked
Traced both delta systems, the entity_references staleness engine, the `needs_republish` lifecycle, and the delta write path; ran the full `tests/Feature/References/` suite.

### Architecture note — two delta systems, one is dead
- **`SmartPublisher` + `DependencyGraph::getAffectedTargets`** (the DependencyGraph-based delta engine) is **dead code** — zero callers in app/tests/routes. `DependencyGraph` is used only by an admin graph-visualization endpoint. Its closure logic (homepage/menu/archive targeting) never runs.
- **The live delta** is the References staleness engine: `StalenessResolver` sets `needs_republish` → `StaleAutoRepublisher`/`StaleContentController` snapshot a `stale_batch` → `RepublishStaleJob` builds only the target pages/posts to staging → `DeployService::deployPartial` per-file merges into live.

### What works (verified — this is a real strength)
- **The staleness/dependency engine is well-built and well-tested: `tests/Feature/References/` = 35 tests / 97 assertions, all pass.** It walks `entity_references` inverse edges (BFS, cycle-guarded via a visited set, bounded to depth 5), correctly propagates `embeds`/`uses_asset`/`lists`/`site_scope`, flags referrers on **slug change and delete** (`markStaleForLinkTargets`), flags listing pages on new-post publish, and collapses menu/theme changes to a single site flag rather than per-page rows. Delete protection (409 + force + flag referrers) is tested.
- **`needs_republish` clears only after a successful promote** (per built-id, in `autoPromote`/`StaleContentController::promote`/`clearForSite`); a failed promote leaves the batch `staged` for manual retry — no optimistic clear.
- **Slug rename/delete removes the old static file immediately** via `StaticCleaner::removePath`/`removeContent` in `PageController::update/destroy`.
- Promotion is human-confirmed (build → `staged` → operator promote) unless `auto_republish_stale` is on.

### Defects

**D1 (major) — Delta output is incomplete: sitemap / RSS / archives / homepage go stale.** `RepublishStaleJob` explicitly does not regenerate `sitemap.xml`, `feed.xml`, or archives (docblock `:22-26`); only a full publish does. So any incremental/auto-republish (new post, deleted page, slug rename) leaves the **sitemap and feeds pointing at old/dead URLs** until a full publish. Worse, **changing the homepage rebuilds nothing at all**: `SiteController::update` mutates `settings['homepage_id']` with no `markStale`/trigger, so the old `index.html` stays live indefinitely on every path. **Severity: major (silent stale output, SEO impact; full publish is the only fix).**

**D2 (major) — Lost-update race on re-flag during a pending batch.** A batch snapshots page IDs at queue time and reads content at build time, but clears `needs_republish` by **ID unconditionally** at promote time — with no check that the flag/reason is still the one that was built. If dependency X changes again after the build snapshot but before promote, the newer staleness is erased and live output reflects the older build; the dedupe guard (`StaleAutoRepublisher`) even suppresses the corrective follow-up batch. Result: stale output with `needs_republish=false`. **Severity: major (silent data staleness).**

**D3 (moderate) — Delta write is non-atomic and mutates the live build in place.** `deployPartial` → `copyDeploy` copies each file directly onto the live docroot one by one; for slug sites it resolves the live symlink and writes **into the served full-build directory** (`readlink`, `DeployService.php:57`). So the site is a partially-updated set during promotion (broken window), and the mutated build no longer matches its deployment/version snapshot (and is prunable per §6). **Severity: moderate.**

**D4 (moderate) — Some structural changes aren't auto-covered.** Site-wide menu/header/footer and theme edits set only a site flag; `StaleAutoRepublisher` skips site-wide changes, so with `auto_republish_stale` on (but full auto-publish off) a menu edit **queues nothing** and relies on the operator noticing the site-level flag. Category/tag name/slug edits have no `markStale` caller in the live path (only post-change touches the category). Slug rename writes **no redirect** and does not rewrite stored hrefs, so the old URL 404s and referrer links stay broken until an editor manually fixes them. Dependency chains deeper than 5 hops are silently truncated (logged only). **Severity: moderate.**

**D5 (info) — `SmartPublisher`/`DependencyGraph::getAffectedTargets` dead code.** The more complete delta engine that would target homepage/menus/archives is unwired; the live path is the narrower References engine. **Severity: info (confusing dead code; also a fix opportunity for D1).**

### Test coverage
The dependency closure is well-covered (35 References tests). **Uncovered:** sitemap/feed staleness after delta (D1), homepage-change rebuild (D1), the lost-update re-flag race (D2), delta-write atomicity / partial-promote crash (D3), and redirect-on-rename (D4).

### Rating rationale
The "what to rebuild" brain — the staleness/dependency engine — is genuinely well-designed and is the best-tested subsystem in the audit so far, and the `needs_republish` clear-after-success ordering is safe. But the delta **output** is incomplete (stale sitemap/feeds, homepage never rebuilt, site-wide menu/theme not auto-covered), it has a real lost-update race, and the write is non-atomic and mutates builds in place. These are silent-staleness correctness bugs, mitigated by the fact that a full publish (the default publish path) regenerates everything — so the blast radius is limited to users relying on the incremental/auto-republish feature. Not RED (the tested core is correct and a working escape hatch exists); not GREEN (multiple silent-staleness gaps + a lost-update race). 🟡 YELLOW (weak end).

---

## §8 — SEO output (sitemap / robots / OG / clean URLs)  🟡 YELLOW  (audited 2026-07-06)

### What was checked
Read `SeoService`, `SitemapGenerator`, `RobotsGenerator`, `StructuredDataService`, and the layout head wiring; then **generated real output** (`audit/seo_output.php`, `audit/seo_head.php`) for the seeded sites — including the live "Ensodo" site (custom domain, 11 published pages) — and inspected the actual sitemap, robots, and page/post `<head>`.

### What works (verified with real output)
- **Per-page SEO head is comprehensive and correct.** `SeoService::generatePageHead` (wired to `$headContent` for every page/post at `BuildPageService.php:51`) emits: title with `{title} | {site_name}` template, meta description, `no_index` support, `<link rel=canonical>`, full Open Graph (title/description/url/type/site_name/image), Twitter card, and for posts `article:published_time`/`modified_time`/`section` — all properly `e()`-escaped. Confirmed on a real published page and post.
- **Structured data present**: JSON-LD `WebPage`/`Article` + `BreadcrumbList` emitted per page/post.
- **Sitemap is valid and comprehensive**: well-formed XML, absolute URLs, `priority`/`changefreq`/`lastmod` (W3C), covering homepage + published pages + categories-with-posts + posts + published magazines, locale-aware (`LocalePaths::urlPath`), escaped `<loc>`.
- **Robots is correct**: `User-agent: *`, `Allow: /`, `Disallow: /admin/`, absolute `Sitemap:` directive; fully overridable via `settings['robots']`.
- **Clean URLs**: content is written as `{path}/index.html` and served without `.html` in the URL. Custom-domain vs `{slug}.ensodo.eu` base URL handled consistently.

### Defects

**D1 (moderate) — Structured-data URLs are wrong and conflict with the canonical.** `StructuredDataService` hardcodes `/blog/{slug}` for the JSON-LD `Article` url (`:34`) and the post breadcrumb's final item, but posts are actually served at `/{category}/{slug}/` (`LocalePaths::postPath:152-159`), which is what the (correct) canonical, `og:url`, and sitemap use. Real output for post "Hello world": canonical/og:url = `https://ensodo.eu/aboutenso/hello-world` but JSON-LD `"url":"https://ensodo.eu/blog/hello-world"` and breadcrumb item = `/blog/hello-world` — **a non-existent URL**, inconsistent even within the breadcrumb (category item is `/aboutenso`, post item is `/blog/...`). Search engines get conflicting canonical signals pointing at a 404. **Severity: moderate (SEO correctness).**

**D2 (moderate) — Auto meta-description only reads top-level `text` blocks.** `SeoService::autoDescription` queries `blocks()->where('type','text')` only, so a page built from `paragraph`/`rich-text`/`hero`/`heading` blocks and no explicit `seo_meta.description` gets an **empty** `<meta name="description">`. Many pages will ship with no description. **Severity: moderate.**

**D3 (minor) — Canonical omits the trailing slash of the served URL.** Canonical/og:url emit `/retrijt` while the page is served at `/retrijt/` (directory index). Minor duplicate-URL ambiguity for crawlers. **Severity: minor.**

**D4 (minor) — Sitemap completeness + no og:image fallback.** The sitemap includes categories but not tag/author archives or the blog index; pages with no configured image emit no `og:image` (no site-logo fallback). **Severity: minor.**

**D5 (moderate) — No SEO tests at all.** Nothing asserts sitemap validity, canonical correctness, or URL consistency — which is exactly why D1's structured-data/canonical mismatch went unnoticed. **Severity: moderate.** Cross-ref §7: delta publish does not regenerate the sitemap, so it can also go stale between full publishes.

### Rating rationale
The SEO output is broadly strong and, for the core surface (title/description/OG/Twitter/canonical/sitemap/robots/clean URLs), verifiably correct on a real live site — well above the audit's average. It is not GREEN because the JSON-LD/breadcrumb URLs are hardcoded to a `/blog/` scheme that doesn't match the actual `/{category}/{slug}/` routing (conflicting canonical signals to a 404), auto-descriptions are frequently empty, and there are zero SEO tests to guard any of it. 🟡 YELLOW.

### Track F1 addendum (2026-07-12) — 🟡 → 🟢
The three YELLOW reasons are resolved and structured data is materially enriched:
- **JSON-LD URL defect fixed** (FIX-B8a): `StructuredDataService::contentUrl` uses `LocalePaths::urlPath` == the canonical/sitemap URL — no more `/blog/` mismatch to a 404.
- **Structured data enriched:** `LocalBusiness` on the homepage with a specific schema.org subtype (HVACBusiness/Plumber/LodgingBusiness/RoofingContractor/… driven by the site's recorded `business_type`); posts emit `BlogPosting` (configurable Article/NewsArticle/BlogPosting via `seo_defaults.article_type`) with an `author` Person + `mainEntityOfPage`; block-driven **`FAQPage`** extracted from `accordion` blocks (Google's ≥2-Q&A minimum). All wired into the Full-Site generator so AI-built small-business sites ship this out of the box.
- **First SEO tests exist** — `StructuredDataTest`(5): LocalBusiness subtypes, homepage-only emission, BlogPosting/author, FAQPage extraction. Closes the "zero SEO tests" gap.
- **Auto-descriptions** improved (`autoDescription` reads more block types) and generated pages/posts carry explicit `seo_meta.description`.

Verified live on prod (roofing/massage/hotel sites): correct specific business subtype + FAQPage in the published `<head>`, 0 residual (txn-rollback checks).

**Remaining F1 (not yet done):** consolidated `@graph` (currently separate `<script>` tags), `CollectionPage`+`ItemList` on archives, featured images as `ImageObject` w/ dimensions, `WebSite` `SearchAction`.

### Track F2 addendum (2026-07-12) — per-page SEO controls
- **Shared `SeoPanel`** (Page + Post editors — posts previously had zero SEO UI): Google-style snippet preview with greyed automatic fallbacks, title/description length indicators (revives the previously-dead `seoHelpers.ts`), social image, canonical override, decoupled robots toggles.
- **Backend:** `seo_meta.canonical` (validated URL, drives canonical + og:url), independent `no_index`/`no_follow` (no tag when default index,follow — replaces the old hardwired `noindex, nofollow` coupling), description fallback chain explicit → post excerpt → block scrape → `seo_defaults.description` (site default was stored but never read before), verification-tag slot (`seo_defaults.verification_google`/`verification_bing` → `google-site-verification`/`msvalidate.01` metas), publisher `Organization` now carries `logo` + `sameAs` from site branding settings.
- **Posts:** author picker (`author_id` — feeds Article schema; `/users`-gated, hides below admin role), full SEO field validation in `UpdatePostRequest` (previously unvalidated), `PostService` now merges partial `seo_meta` patches like `PageService` (canvas config can no longer be clobbered).
- **Tests:** `SeoHeadTest`(7) + `PostSeoTest`(3), all SEO/publishing/API suites + 350 admin vitest green. NOT yet live-verified on sys.ensodo.eu (worktree branch, pending deploy).

---

## §9 — Asset pipeline (WebP variants / content hashing / reference resolution)  🔴 RED  (audited 2026-07-06)

### What was checked
Read `AssetService` (upload/hashing/variant generation), `AssetPublisher` (publish-time resolution/rewrite), and `image.blade`. Then empirically: queried variant coverage across all tenants, reproduced the URL-rewrite behavior, and tested Intervention Image's API in isolation.

### What works (verified)
- **Content hashing + dedup**: sha256 `hash_file` checksum, dedup by `(site_id, checksum)` returns the existing asset on re-upload (`AssetService.php:23-32`). Published filenames are content-hashed (`/assets/files/{checksum}.{ext}`, `AssetPublisher.php`), giving correct cache-busting.
- **Tenant-isolated storage** (`sites/{site_id}/assets/…`), **SVG sanitization** at upload, and **base image references resolve correctly** — `rewriteHtml` (wired into publish at `BuildPageService.php:169,288`) rewrites the base `…/serve` URL to the hashed static path, so images **do display** on published sites.
- GD and WebP are available on the server (so the fix below is just the code, not the environment).

### Defects

**D1 (blocker) — Image variant generation is 100% broken (silently).** `AssetService::generateImageVariants:100` calls `$this->imageManager->read($path)`, but the installed **Intervention Image v4.0.1** `ImageManager` has **no `read()` method** (it exposes `decode`/`decodePath`/…). Verified empirically: `$manager->read(...)` throws `Call to undefined method Intervention\Image\ImageManager::read()`. So **every** upload's variant generation throws at the first line and is swallowed by the method's silent `catch (\Throwable) {}`, returning `[]`. Confirmed against data: **0 of 21 image assets (all tenants) have any variants.** No thumb, no responsive sizes, and **no WebP is ever produced**. The silent catch hid this library-upgrade regression entirely. **Severity: blocker (the pipeline's defining feature produces nothing; every published image is a full-size original — directly undermines the PageSpeed goals, §22).**

**D2 (major, latent) — Even with variants, `AssetPublisher` can't serve them.** Three compounding bugs: (a) `resolveUrl` extracts only the asset UUID and **ignores the variant suffix**, always returning the original file's path; (b) the `rewriteHtml` regex `…/serve(?:/[a-z]+)?` matches `[a-z]+` only, so a variant URL like `…/serve/webp_800` rewrites to a **mangled** `/assets/files/{hash}.{ext}_800` (verified by reproduction); (c) variant files are **never copied** to the published output — only `$asset->storage_path` (the original) is. Because `image.blade:61` uses `medium_800` as the `<img src>` for images >800px, fixing D1 without D2 would immediately **break the base image** for large images in published output (broken `src` + broken `srcset`). **Severity: major (latent landmine — activates the moment D1 is fixed).**

### Rating rationale
Content hashing, dedup, tenant-isolated storage, SVG scrubbing, and base-image resolution all work — but the asset pipeline's headline capability, **WebP + responsive variants, is non-functional end-to-end**: variants are never generated (a silent library-API regression), and the publish layer would mangle and fail to serve them even if they were. Published sites ship full-size originals with no WebP. Two of the three named capabilities for this subsystem (WebP variants; variant reference resolution) are broken. 🔴 RED.

---

## §10 — Block registry contract compliance  🟡 YELLOW  (audited 2026-07-06)

### What was checked
Wrote `audit/block_compliance.php` (read-only): for all 86 registered block types, cross-referenced the presence of the PHP definition, Blade view, React `Editor.tsx`/`Preview.tsx`/`definition.ts`, `index.ts` registration, `sanitizationConfig`, and reference-extractor entry — plus orphan detection (React dirs / Blade views with no registered type). Ran `ExtractorCoverageTest`.

### Results — the healthiest subsystem so far (near-GREEN)
Contrary to the audit's prediction that this would be "the biggest finding," compliance is **excellent**:
- **86 registered PHP types; all 86 have a Blade view** (0 missing).
- **All 84 React block directories are complete** — every one has `Editor.tsx` + `Preview.tsx` + `definition.ts` (0 missing any of them).
- **`sanitizationConfig` present on all 86** definitions.
- **Reference-extractor coverage is a first-class, enforced contract**: `ReferenceExtractorRegistry` maps every type explicitly (`NullExtractor` for reference-free blocks), and `ExtractorCoverageTest` fails the build if a type is unmapped.
- The `slide`/`slider` types having no standalone React dir is **by design** — the slider system is authored via the `slider_ref` block (full Editor/Preview/definition) and a dedicated slider builder, not the standard per-block editor.

### Defects
**D1 (moderate) — `langswitcher` violates the extractor contract; `ExtractorCoverageTest` is RED.** The recently-added `langswitcher` block has no entry in `ReferenceExtractorRegistry`, so `tests/Unit/References/ExtractorCoverageTest.php` **currently fails** ("Block types without a reference extractor: langswitcher"). This is both a live contract violation and a red test sitting in the suite (implying it isn't being run/enforced in CI). The fix is one line (add a `NullExtractor` or a real extractor entry). **Severity: moderate (contract gap + failing test).**

**D2 (minor) — Orphan `quote.blade.php`.** `resources/views/blocks/quote.blade.php` has no registered block type — a leftover from the `quote → pullquote` rename (the `quote` references elsewhere in app/ are the separate magazine DTP frame type, not this page block). Dead file. **Severity: minor (cleanup).**

### Rating rationale
The block registry contract is genuinely well-defined and largely enforced — Blade, React (editor/preview/schema), sanitizer, and extractor coverage are complete for 85 of 86 types, with a test that guards extractor completeness. It is not GREEN only because that guard test is **currently failing** (langswitcher unmapped) and there's a dead orphan view — a single-line fix and a file deletion away from GREEN. 🟡 YELLOW (near-GREEN, the healthiest subsystem audited).

---

## §11 — Block editor (CRUD / nesting / undo / save integrity)  🟡 YELLOW  (audited 2026-07-06)

### What was checked
Read `BlockService::syncBlocks`/`insertBlocks`/`buildTree`, `SyncBlocksRequest`, `HierarchyValidator`, and the concurrency/undo surfaces; ran the hierarchy, round-trip, and validation tests.

### What works (verified)
- **Nesting is checked server-side (with gaps — see D5) and well-tested in isolation.** `SyncBlocksRequest` rejects >500 blocks and depth >4 unconditionally, and runs `HierarchyValidator::validate` for level containment (`:91`). `HierarchyValidatorTest` = **10 passing** (module-in-section, row-in-row, column/row-at-root, module-with-children all rejected). The React DnD layer (dnd-kit) mirrors the same level rules.
- **Property round-trip to published output is tested.** `InspectorRoundTripTest` = **14 passing** — editor-set style/token/opacity/border/object-fit/video-audio attributes all reach the published CSS/markup.
- **Block IDs are preserved on save** (`insertBlocks`: `'id' => $blockData['id'] ?? uuid`), and `syncBlocks` is transactional with entity_reference edges recomputed in the same transaction.
- **Per-block data validation** exists for dozens of block types (`tests/Unit/Blocks/*ValidationTest`), and **undo/history exists** in the React editor stores (`editorStore.ts`, `storeFlow.ts`; `magazineStoreUndo.test.ts` covers the magazine store).

### Defects

**D1 (moderate) — Destructive bulk-replace silently loses block-linked data.** `syncBlocks` **deletes all of a page's blocks and re-inserts the tree** on every save. Because `theme_overrides.block_id` and `grid_position_blocks.block_id` are `ON DELETE CASCADE` (§3), the delete step **cascades those rows away**, and the re-insert (same block ids) does **not** restore them — nothing in `BlockService`/`BlockController` preserves or re-creates them (grep-confirmed). So a block-scoped theme override or a grid-position block association vanishes the next time its page's blocks are saved. **Severity: moderate (silent data loss, feature-usage dependent).**

**D2 (moderate) — No concurrency protection (last-write-wins).** `active_editors` (`EditorPresenceService`) is **presence-only** — there is no version token, optimistic lock, `If-Match`, or conflict detection in the block save path. Two editors on the same page (or two browser tabs, or an autosave racing a manual save) each send a full tree; the second `syncBlocks` **overwrites the first's entire block tree** with no warning. **Severity: moderate (collaborative data loss).**

**D3 (minor) — Round-trip JSON is not byte-identical.** `insertBlocks` stores `style`/`animation`/`responsive`/`advanced` **both** in dedicated columns **and** as `data.__style`/`__animation`/… keys; `buildTree` restores the top-level fields but leaves the `__`-prefixed duplicates inside `data`. So reloaded block JSON carries extra keys vs what was sent (functionally harmless, but not identity-clean and it grows the stored blob). **Severity: minor.**

**D4 (minor) — Round-trip JSON is not byte-identical + snapshot divergence.** Beyond the `__*` pollution: empty/falsy `style`/`animation`/`responsive` are dropped on write, non-canonical top-level keys are stripped by `buildTree`, and `PageVersion.blocks_snapshot` stores the **raw request tree** (pre-normalization) — so a version restore reintroduces a differently-shaped tree than a normal read. No test asserts save→reload JSON equality. **Severity: minor.**

**D5 (moderate) — Server nesting enforcement is bypassable.** Three holes let an invalid tree save via the API: (a) `HierarchyValidator` only runs when `anyBlockHasLevel($blocks)` is true, so a payload that **omits `level` on every block** skips containment entirely (blocks then default to `level='module'`); (b) validation keys on `level`, never cross-checking that `type:'section'` carries `level:'section'` — a `type:heading, level:section` tree passes; (c) `allowsChildren()`/`maxChildren()` are **never called on the write path** (only surfaced to the frontend `/types` API) — a Row with 100 columns saves. The DnD layer enforces the rules for normal use, but the API itself is not authoritative. **Severity: moderate (API hardening / invalid-state persistence).**

**D6 (minor) — Duplicate-ID payload silently fails the whole save.** Because `insertBlocks` sets explicit primary keys, a payload with two identical block ids (e.g. a paste bug) throws a unique violation that rolls back the entire transaction — the save fails with only a client-side error. Also: `blocks.last_edited_by/last_edited_at` columns exist but are never written; page/post delete leaves orphaned blocks (cross-ref §3/§5). **Severity: minor.**

### Rating rationale
The editor's core — level-containment nesting (well-tested in isolation), property round-trip to output, per-block validation, ID preservation, and a persisted undo stack — is solid. It is not GREEN because the save model is a **destructive bulk-replace that cascades away block-linked data**, there is **no protection against concurrent-edit clobbering** (presence-only, last-write-wins), and the server nesting check is **bypassable** (level-omission / type-blind / no maxChildren). Real, if usage-dependent, data-loss and invalid-state paths. 🟡 YELLOW.

---

## §12 — Magazine editor  🟡 YELLOW  (audited 2026-07-06)

### Correction to the audit brief
The brief labels this "current known-broken state." That is **out of date** — the magazine editor was **rebuilt** (Magazine Flow Engine + Issue Studio) and the legacy editor was intentionally retired. Recording the actual current state.

### The three magazine systems (current topology)
1. **Legacy page-flip editor** (`MagazineEditorV2`, `Magazine`/`MagazinePage`/`MagazineElement`) — **intentionally FROZEN read-only** by explicit product decision (`App.tsx:20-22`: "legacy magazine editor FROZEN read-only — no legacy magazines to migrate; DTP editor is the single magazine editor"). `magazine-editor-acceptance.md` documents the acceptance. **Not a defect — a deliberate retirement** (there were no legacy magazines to migrate).
2. **DTP editor** (`DtpEditorBeta`, `magazine_dtp_pages`/`magazine_frames`/`magazine_spreads`/`magazine_layers`) — the **single current magazine editor**. Renders via `DtpRenderService` to static output + PDF (`DtpPdfService`/`DtpZipService`), with preflight, versioning, and rollout services.
3. **Issue Studio** (`IssueStudioService` + Flatplan/Spread/Interview engines) — the **live conversational creation wizard** (routes `/issue-studio*`), the primary way issues are authored.

### What works (verified)
- **94 of 96 magazine tests pass (349 assertions).** Coverage spans `DtpRenderServiceParityTest` (editor→publish render parity), `DataElementRenderTest`, `InlineFigureSanitizeTest`, `DtpPdfServiceTest`, `MagazineReferenceExtractorTest`, `MagazineStaticPublishTest`, `DtpVersionsTest`, `DtpRolloutTest`, and Issue Studio `IssueStudioSessionTest`/`FlatplanTest`/`SpreadGenerationTest`. This is substantial, genuine coverage — the magazine subsystem is one of the better-tested areas.
- Recent hardening (per git history) fixed editor round-trip of shape visuals, figure/figcaption publish survival, and data-element/video-frame rendering.

### Defects
**D1 (minor) — 2 failing tests: DTP video-frame QR overlay.** `tests/Unit/Magazine/VideoFrameRenderTest.php:35` and `:50` expect a `<svg` QR overlay when `showQr` is set, but the rendered video frame omits it (the play button is a CSS triangle; the QR SVG isn't emitted). A cosmetic rendering gap in `DtpRenderService`'s video frame, not a structural break — but it's a red suite. **Severity: minor.**

**D2 (minor) — Multi-generation sprawl / "beta" labeling.** Three magazine systems coexist (frozen legacy + `DtpEditorBeta` + Issue Studio) plus a `dtp-prototype` route. The current editor is still named/routed as "beta." The legacy freeze is clean, but the beta labeling and prototype routes are latent confusion/cleanup debt. **Severity: minor.**

### Rating rationale
This is not a broken subsystem — it is a **rebuilt, live, and well-tested** one (94/96 passing, legacy cleanly frozen, Issue Studio in production). It is not GREEN only because 2 tests fail (QR overlay) and the current editor still carries "beta"/prototype status with some multi-generation cleanup owed. 🟡 YELLOW (healthy). The dedicated "magazine rebuild track" the brief anticipated is effectively **already done**; what remains is the QR fix and de-beta cleanup, not a rebuild.

---

## §13 — Block templates / presets-from-primitives  🟡 YELLOW  (audited 2026-07-06)

### The template/preset landscape
There are **five overlapping "template" concepts**: block templates (`block_templates`, `BlockTemplateController` — reusable block subtrees), theme/page-post templates (`theme_templates`, `ThemeTemplateController` — audited under theme engine §17-19), starter templates (`StarterTemplateService`, whole-site scaffolds via `apply-template`), site templates (`site_templates`, `SiteCloneController::importTemplate`), and grid presets (`GridPresetSeeder`). Plus per-block **style/scene presets** (`in:preset,custom` shadow modes; Experience-Mode scene presets on `section`).

### What works (verified)
- **Block templates: save + copy-instantiate work with correct semantics.** `store` saves the selected block subtree as a `blocks_data` JSON column; instantiation is a **client-side copy** into the page's block tree (then persisted via `syncBlocks`). `PresetsCopyTest` (**passes**, 4 assertions) confirms the key invariant: **instantiating a template creates no reference edges to the template** — the inserted blocks are independent copies, so deleting/changing the template can't dangle a live page. This is the correct "preset = copy, not reference" behavior.
- **Style/scene presets** are ordinary validated block fields (covered by per-block ValidationTests + `InspectorRoundTripTest`).
- `destroy` blocks system templates and checks site ownership.

### Defects
**D1 (moderate) — Block-template `store`/`destroy` have no role authorization (cross-ref §2 D1).** Both rely only on the tenant-checked `Site $site` binding, so any tenant user (incl. `viewer`) can create or delete block templates. **Severity: moderate (folds into FIX-A2a).**

**D2 (minor) — `blocks_data` is stored raw and unvalidated.** `store` accepts `blocks_data` as any array with no `HierarchyValidator`/depth/size check and no sanitization (same at-rest model as blocks — sanitized only at render). A template can therefore carry an invalid or oversized tree that instantiates into a page; it's bounded by the block editor's (bypassable, §11) guarantees on the eventual save, not at template creation. **Severity: minor.**

**D3 (minor) — Thin test coverage + concept sprawl.** Only one preset/template test (`PresetsCopyTest`). The five overlapping template systems have no unifying model and little cross-coverage; the `index` `orWhere('is_system', true)` for system block templates is effectively dead (0 exist, and RLS would hide `site_id IS NULL` rows anyway — cross-ref §1 the themes-style is_system exception is absent here). **Severity: minor.**

### Rating rationale
Block templates and presets **function correctly** where it matters — the copy-not-reference invariant is verified, and style presets round-trip. It is not GREEN because coverage is thin (one test across five template systems), the block-template write endpoints lack role authorization, and stored `blocks_data` is unvalidated. No broken behavior, but light and slightly sprawling. 🟡 YELLOW.

### Builder Experience track addendum (2026-07-11 → 2026-07-12) — 🟡 → 🟢
Substantial **additive** build (Track D) has landed on this subsystem since the 2026-07-06 audit, resolving the YELLOW gaps:
- **Library (D1):** `BlockTemplateController` full CRUD + `LibraryItemSanitizer` (validates shape/node-count/depth/known-types + per-node sanitize on import). `LibraryTest`(7).
- **Style Presets (D3):** element + option-group + default resolution (`StylePresetResolver`), token compile (`StyleTokens`: `$a.b.c`→`var(--a-b-c)`), edit→republish cascade, **11 seeded system presets** (`SystemStylePresetSeeder` via the privileged `SystemRecordSeeder::withRlsDisabled` — style_presets is FORCE-RLS), and **adopt-as-site-default** (clone a system preset into an editable site default). `StylePresetApiTest`(7)+`PresetResolutionTest`(4)+`StyleTokensTest`(5)+`SystemStylePresetSeederTest`(4).
- **Starter content (D5):** **15 token-based system starter sections** (`StarterSectionSeeder`) + a **Full-Site starter template** (`StarterTemplateService` via `apply-template`) scaffolding all 8 pages (home/landing/catalog/portfolio/contact/blog/about/features); with a named business type it generates **AI industry-specific copy** (`AiSiteContentService`, schema-forced `claude-sonnet-5`) + curated per-industry images (loremflickr, no key). `StarterSectionSeederTest`(5)+`StarterTemplateTest`(5). **Live-verified on prod** (HVAC + day-spa → genuinely industry-specific copy; end-to-end apply = 8 pages + 3 posts + gallery images, 0 residual via txn rollback).
- **Library preview thumbnails (P1 Slice E):** detached-tree render (txn-rollback) → Playwright `capture-html.mjs` → served at `/library-thumbnails/{id}` (no extension — nginx would serve `.png` statically); `library:thumbnails` backfill command + auto-on-save `GenerateLibraryThumbnailJob`. `LibraryThumbnailTest`(5). 15 system thumbnails generated + served live.
- **Role authorization** now enforced on block-template `store`/`update`/`import` (`authorize('update',$site)`), closing the §13 D1 defect. The D2 minor note (raw `blocks_data`) stands only for the editor's own `store()` payload (sanitized at render like all blocks); the import path is now validated.

Full per-subsystem D-track STATUS rows (Library / Globals / Presets / Ergonomics / Theme Parts) remain a Track-D **D7 wrap** deliverable.
