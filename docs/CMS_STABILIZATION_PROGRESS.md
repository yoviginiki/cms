# CMS Stabilization Progress (audit 2026-09-22)

Backlog source: „StilloPress / Ensodo CMS: технически одит и план за Claude Code" (22.09.2026,
archive SHA-256 `2d328c20…78b3`). Work happens on branch `stabilization/audit-2026-09-22`
(git worktree `private/cms-stabilization`, based on `a116d9b6`, the commit the export of
2026-09-22 15:43 was taken from).

Statuses: `confirmed` · `fixed` · `already_fixed` · `blocked` · `not_reproduced` · `deferred_with_reason`.

## Stage 0 — Baseline

| Item | Status | Notes |
|---|---|---|
| Test environment | done | Isolated worktree + own vendor copy; DB `cms_saas_platform_stab` / `_stab2` (role `cms_saas`, no superuser, no BYPASSRLS → RLS is real in tests). Publish/staging/tenant paths sandboxed by `phpunit.xml`. Run as `cytechno`: `DB_DATABASE=cms_saas_platform_stab php artisan test`. |
| PHPUnit baseline (before any fix) | recorded | First run aborted with a **fatal**: `tests/Unit/InlineEdit/InlineEditServiceTest.php` declared `private function status()` which overrides the final `TestCase::status()` of PHPUnit 12 → the whole suite could not load. Fixed by renaming the helper to `httpStatus()` (test-only). Second run: **770 passed, 20 failed, 1020 risky**, 814 s. |
| Pre-existing failures (not caused by this work) | recorded | `Unit\InlineEdit\PageInlinePolicyTest` ×3 and `SpEditableRenderTest` ×4 (ArgumentCountError — `@dataProvider` annotations on PHPUnit 12), `Unit\System\CmsExportServiceTest`, `Feature\Blocks\InspectorRoundTripTest`, `Feature\Collections\CollectionHierarchyTest` ×2, `CollectionPublishTest` ×4, `Feature\InlineEdit\InlineEditRbacTest`, `Feature\Publishing\CloudflarePurgeTest`, `RowColumnLayoutTest`, `Feature\SiteWizard\SiteWizardFlowTest`, `Feature\ThemeWizard\TokenProfileTest`. Tracked for F26/F31 stage. |
| Frontend suite / build | not run yet | Stage 0 scope for F26; PHP side first per the audit's priority order. |

## Stage 1 — Contain destructive risk (P0)

### F01 · Clear of one site walked the shared public root — **fixed**

- **Confirmed:** `PublishController::clear` scanned `config('publishing.public_path')` — which in
  production is `/home/cytechno/web/ensodo.eu/public_html`, i.e. the live ensodo.eu docroot that also
  holds every slug-hosted site as a symlink (`artday`, `docs`, `men-root`, …). `File::deleteDirectory`
  follows symlinks, so a clear for *any* site would have emptied other sites' builds and the ensodo.eu
  pages. Reachable with the `publish` ability (editor).
- **Fix:** new `App\Domain\Publishing\Services\DeployTargetResolver` — the single authority for a site's
  live target and ownership. `clearLiveOutput()` removes only symlinks that resolve to one of *this
  site's* deployments (or the legacy real dir named after the site), never descends into or removes
  other symlinks, never touches dot-entries. Custom-domain sites: only the files the last live artifact
  defines are removed (no artifact → refuse to guess). New `SitePolicy::clearPublished` (admin+) replaces
  the `publish` ability for this endpoint; every call is written to `activity_logs`
  (`site.published_output_cleared`). `DeployService::pruneStale` (full copy deploys) now skips symlinks
  entirely — a full publish of ensodo.eu could otherwise prune *inside* other sites' builds.
  Non-UUID symlink names no longer reach the `deployments` query (was a QueryException/500).
- **Files:** `app/Domain/Publishing/Services/DeployTargetResolver.php` (new),
  `app/Http/Controllers/Api/V1/PublishController.php`, `app/Policies/SitePolicy.php`,
  `app/Domain/Publishing/Services/DeployService.php`, `app/Domain/Sites/Services/SiteService.php`,
  `config/publishing.php` (`reserved_domains`, `reserved_slugs`, `preserve_paths`).
- **Tests:** `tests/Feature/Publishing/PublishClearScopeTest.php` (4 tests: A/B builds + unmanaged files +
  out-of-root sentinel byte-for-byte unchanged; editor 403 / admin 200 + audit row; custom-domain
  managed-files-only incl. a foreign symlink inside the docroot; nothing-live no-op). Red before, green after.
- **Residual risk:** `SymlinkDeployStrategy::rollback` and `BuildRetention` still resolve paths on their
  own (F15/F18 scope). The frontend button calls the same endpoint; editors now get 403 (intended).

### F02 · Tenant admin could apply its own system update package — **fixed**

- **Confirmed:** `SystemController::applyUpdate` only required `hasMinimumRole('admin')`; the request
  supplied `download_url` + `checksum`; `UpdateService` copied the archive over `base_path()` and swallowed
  migration failures.
- **Fix:** web-applied updates are **off by default** (`CMS_UPDATES_WEB_APPLY=false` → 403 for every
  tenant role). When an operator enables them: only the *owner* of the configured operator tenant
  (`CMS_UPDATES_OPERATOR_TENANT`), `download_url` must be https on the configured update-server host,
  `version` is a strict token (used in a file name), and `checksum` must carry an Ed25519 `signature`
  verifiable with `CMS_UPDATES_PUBLIC_KEY` — all checked **before** any download. `UpdateService`: no
  redirects, size limit, pre-flight of every archive entry (no traversal/absolute paths, no symlink entries,
  allow-listed application paths only, protected paths refused → whole package rejected before a byte is
  copied), extraction into a private temp dir, flock, and a failed migration **restores the previous files
  and fails the update**. Sandboxed base path is injectable, so tests never touch the checkout.
- **Files:** `app/Domain/System/Services/UpdateService.php`, `app/Http/Controllers/Api/V1/SystemController.php`,
  `config/cms.php` (`updates.*`; duplicate `updates` key merged).
- **Tests:** `tests/Feature/Security/SystemUpdateAccessTest.php` (5), `tests/Unit/System/UpdateServiceHardeningTest.php` (5).
- **Residual risk / operator notes:** the preferred model remains deployment from the release pipeline; the
  HTTP path stays disabled unless the three env vars are set. No release-signing tooling exists yet (key
  generation + signing script are a follow-up if web updates are ever wanted). The admin SPA has no UI for
  this endpoint (`api.ts` only).

### F03 · Domain setting could aim the deploy at an unprovisioned web root — **fixed**

- **Confirmed:** `UpdateSiteRequest` had no reserved-domain check (only create did); `DeployService` built
  `{tenant_base}/{domain}/public_html` and only checked `is_dir`; domain uniqueness is enforced by a global
  DB index that the RLS-scoped validator cannot see (cross-tenant duplicate → 500).
- **Fix:** one policy in `DeployTargetResolver`: reserved domains = `publishing.reserved_domains` +
  `APP_URL` host + Sanctum stateful hosts; reserved slugs for `settings.deploy_slug`; enforced in
  `CreateSiteRequest`, `UpdateSiteRequest` **and** in the deploy layer (`authorizeCustomDomainTarget`):
  target must exist under `tenant_base` (containment via realpath, never created by us, no symlinked
  docroot), and must be unclaimed or claimed by this site — first deploy writes an ownership marker
  `.cms-site` (site id, `O_EXCL`), a marker naming another site refuses the deploy. `StaticCleaner`,
  `StalePathCleaner`, `SliderRender::publishRuntime` resolve the docroot through the same resolver (the
  slider runtime no longer writes into a slug folder we don't own). `SiteController` maps the DB unique
  violation to a 422 (savepoint-wrapped so the connection stays usable).
- **Files:** `app/Http/Requests/CreateSiteRequest.php`, `app/Http/Requests/UpdateSiteRequest.php`,
  `app/Http/Controllers/Api/V1/SiteController.php`, `app/Domain/Publishing/Services/DeployService.php`,
  `app/Domain/Publishing/Services/StaticCleaner.php`, `app/Domain/Publishing/Services/StalePathCleaner.php`,
  `app/Support/Blocks/SliderRender.php`, `app/Domain/Publishing/Services/DeployTargetResolver.php`.
- **Tests:** `tests/Feature/Publishing/DeployTargetOwnershipTest.php` (7: update/create refuse reserved
  domains; deploy layer refuses reserved domain with an existing dir + sentinel unchanged; marker of another
  site refuses; first deploy claims + survives prune; missing target not created + traversal domains
  rejected; global uniqueness → 422 across tenants; full copy deploy never enters/removes symlinks).
- **Residual risk:** existing production docroots have no marker yet — the first publish after deploy of this
  branch claims them (expected, no operator action needed). Marker is a dot-file; Hestia/LE never touch it.
  Concurrent claims are serialized by `O_EXCL`; concurrent *domain* claims by the DB unique index.

### F04 · Redirect source could write outside staging — **fixed**

- **Confirmed:** `source_path` was only `string`; `../neighbour` passed the regex-character filter and
  `File::put("{$staging}/{$source}/index.html")` wrote outside the build. `target_url` was free text and
  went verbatim into `_redirects` / `.htaccess` lines.
- **Fix:** `App\Domain\Publishing\Support\RedirectRules` — one rule set used by the API **and** the
  publish-time writers. Literal sources: normalized segments, no `.`/`..`, no encoded separators, no
  control/whitespace/backslash, no regex metacharacters; regex sources are an explicit kind (`is_regex`)
  with traversal/character rules and `preg_match` validity. Targets: site-relative `/…` or http(s) URL,
  no whitespace/control chars, no script schemes. `PublishSiteJob::buildRedirectsManifest` resolves the
  stub path with `RedirectRules::stubPath()` (realpath containment incl. symlinked ancestors), never
  overwrites a real page the build produced, and drops unsafe rows from `_redirects`/`.htaccess`
  (literal `.htaccess` patterns are `preg_quote`d). `RedirectController` update/destroy now verify the
  redirect belongs to `{site}`.
- **Files:** `app/Domain/Publishing/Support/RedirectRules.php` (new), `app/Http/Controllers/Api/V1/RedirectController.php`,
  `app/Domain/Publishing/Jobs/PublishSiteJob.php`.
- **Tests:** `tests/Feature/Publishing/RedirectContainmentTest.php` (25 incl. table-driven traversal/target cases,
  legacy rows contained at build time with sentinels, page-collision, escaping, rules helper).
- **Residual risk:** the 5 production rows are all valid under the new rules (checked read-only). Redirects
  created by `PageController` on slug rename come from `LocalePaths` (trusted) and are contained at write time anyway.

## Next

Stage 2 (F05–F08, F23–F25), then Stage 3 (F11–F14). See sections below as they are added.
