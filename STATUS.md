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
| 2 | Auth, roles, RBAC gates | — | — | — | — | ⬜ | Not yet audited (Session A #2) |
| 3 | DB schema integrity | — | — | — | — | ⬜ | Not yet audited (Session A #3) |
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
