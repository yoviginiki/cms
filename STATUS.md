# STATUS.md — System Health Dashboard

**Purpose.** One row per subsystem. This is the permanent traceability matrix for the CMS platform.
Any session that changes a subsystem MUST update its row (rating, tests, verification state) before ending.

**Rating legend:** 🟢 GREEN (works, tested, verified) · 🟡 YELLOW (works with known gaps or untested) · 🔴 RED (broken, unverified, or dangerous) · ⚪ NOT BUILT (planned, absent — not a defect) · ⬜ NOT YET AUDITED

**Honesty rule:** a subsystem with no tests AND no manual verification cannot be GREEN.

Audit branch: `audit/system-health`. Audit is READ-ONLY — no source fixes land on this branch, only STATUS.md / FIXPLAN.md / `audit/` scripts.

---

## Matrix

| # | Subsystem | Implemented | Tests exist | Tests passing | Manually verified | Rating | Notes |
|---|-----------|-------------|-------------|---------------|-------------------|--------|-------|
| 1 | Tenancy & RLS | partial | yes (2 suites, 9 tests) | yes (9/9) | yes (DB-level probe) | 🔴 RED | RLS-only isolation; 7+ tenant tables RLS-enabled-but-not-FORCED → owner role bypasses; 14 tenant tables have NO RLS; app-scope traits are dead code. Cross-tenant IDOR on menus. See §1. |
| 2 | Auth, roles, RBAC gates | full (auth) / partial (RBAC) | stub only (7 tests, all `markTestIncomplete`) | n/a (no real assertions) | yes (code+config read) | 🟡 YELLOW | Auth core is solid (throttled login, secure session, CSRF, no debug). But 7 controllers have write endpoints with NO role check (viewer can mutate themes/magazines/templates); invite/updateRole escalation asymmetry; owner-demotion; `role` mass-assignable; zero real test coverage. See §2. |
| 3 | DB schema integrity | full | indirect (RefreshDatabase migrates every test) | yes (66/66 ran clean, 0 pending) | yes (live-DB FK/orphan/index probes) | 🟡 YELLOW | Strong: all migrations reversible+clean, broad sensible FKs, good scoping-index coverage, no orphans in current data. Gaps: page/post delete orphans polymorphic blocks (no FK/cascade/hook); 3 delete-blocking FKs; 1 irreversible drop migration; some missing indexes; no referential-integrity tests. See §3. |
| 4 | Security layers (purifier/MIME/CSP) | — | — | — | — | ⬜ | Not yet audited (Session A #4) |
| 5 | Blade rendering of every block | — | — | — | — | ⬜ | Session B |
| 6 | Atomic publish / versions / rollback | — | — | — | — | ⬜ | Session B |
| 7 | Delta publish correctness | — | — | — | — | ⬜ | Session B |
| 8 | SEO output (sitemap/robots/OG) | — | — | — | — | ⬜ | Session B |
| 9 | Asset pipeline (WebP/hashing) | — | — | — | — | ⬜ | Session B |
| 10 | Block registry contract compliance | — | — | — | — | ⬜ | Session C |
| 11 | Block editor (CRUD/nesting/undo) | — | — | — | — | ⬜ | Session C |
| 12 | Magazine editor | — | — | — | — | ⬜ | Session C |
| 13 | Block templates / presets | — | — | — | — | ⬜ | Session C |
| 14 | entity_references / dependency graph | — | — | — | — | ⬜ | Session D |
| 15 | Slider system | — | — | — | — | ⬜ | Session D |
| 16 | Menus / theme refs / slug staleness | — | — | — | — | ⬜ | Session D |
| 17 | W3C token engine | — | — | — | — | ⬜ | Session E |
| 18 | Theme Studio live editing | — | — | — | — | ⬜ | Session E |
| 19 | Theme switching | — | — | — | — | ⬜ | Session E |
| 20 | Cinematic layout (wabisabi4) | — | — | — | — | ⬜ | Session E |
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
