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

## Stage 2 — Permissions, active content, public leaks (P1)

### F05 · raw_html / html-embed bypassed the editor gate — **fixed**

- **Confirmed:** `SyncBlocksRequest` refused `html-embed` for editors (422) but `raw_html` on the same
  request was accepted and rendered verbatim; version restore and inline edit had no gate at all
  (an editor could inline-patch the `html` field of an existing embed).
- **Fix:** `App\Domain\Blocks\Support\TrustedHtml` is the one capability check, applied on every write
  path: page/post/template blocks sync (403 when a non-admin *introduces or changes* an embed — keeping
  or removing an existing one is allowed, so editors can still work on pages with admin-authored embeds),
  `raw_html` (403 when a non-admin sets/changes it; re-sending the unchanged value is fine; omitting it
  leaves it untouched), version restore (403 when the snapshot would introduce trusted HTML), inline edit
  (403 on trusted block types). The old 422 in the FormRequest is removed (superseded).
- **Also fixed:** `VersionController` restores verified that the version belongs to the page/post (404).
- **Tests:** `tests/Feature/Security/TrustedHtmlGateTest.php` (6).

### F06 · HTML uploaded as .txt/.md served inline — **fixed**

- **Fix:** upload refuses active MIME types (`text/html`, XML, JavaScript…) regardless of extension and
  content-sniffs text uploads; `AssetServeController::headersFor()` sends only images/video/audio/fonts/PDF
  inline, everything else as an attachment, executable legacy MIME rows as `application/octet-stream`
  attachment, sanitized SVG inline with `script-src 'none'`. Same headers on the public `/media` route
  (it delegates to the controller).
- **Tests:** `tests/Feature/Api/AssetActiveContentTest.php` (4). Note: `UploadedFile::fake()` reports a
  MIME from the *name*; the test also uses a real sniffed upload.

### F07 · Template blocks writable by any tenant user; nested resources unbound — **fixed**

- **Fix:** `App\Policies\ThemeTemplatePolicy` (view: tenant; create/update/delete: admin+) registered in
  `AppServiceProvider`; `BlockController::syncForTemplate`/`indexForTemplate` and
  `ThemeTemplateController` authorize against the template. `PublishController::status` now authorizes
  `view` on the site and both `status`/`rollback` require the deployment to belong to `{site}` (404).
  Redirect update/destroy were bound in F04.
- **Tests:** `tests/Feature/Security/TemplateAuthorizationTest.php` (4: role matrix for sync + CRUD,
  cross-site 404s, status permission).

### F08 · Site API returned credentials to every tenant role — **fixed**

- **Fix:** `App\Domain\Sites\Support\SiteSecrets` — secret keys (`anthropic_api_key`, `openai_api_key`,
  `deploy_ssh_key`, and any nested `api_key`/`api_token`/`private_key`/`secret`/`password`/`*_token`…)
  are masked (`••••••••`) in `SiteResource` (index/show/update responses), preserved when the SPA
  round-trips the mask (`SiteService::updateSite`), stripped recursively from the backup export.
  **`phpunit.xml` no longer contains the DB password** — it was the production password and the file
  ships in the "Download CMS" zip.
- **Tests:** `tests/Feature/Security/SiteSecretsTest.php` (4).
- **⚠ Operator action required:** the production DB password is present in git history and in every CMS
  export zip generated before this change — including
  `public/dl/cms-platform-2026-09-22-*.zip`, which is reachable over HTTPS on sys.ensodo.eu right now.
  Rotate the `cms_saas` role password (ALTER ROLE + `.env` + php-fpm/queue restart), delete the zips in
  `public/dl/` and `storage/app/cms-export.zip`, and regenerate. Not done here (production change).
- **Deferred with reason:** encryption at rest of the two AI keys (nothing in `app/` reads them yet;
  would need a data migration). Backup/restore completeness is F19.

### F23 · /issue/{slug} showed unpublished magazine pages — **fixed**

`MagazineViewController::showPage` filters `status = published`. Test: `tests/Feature/Magazine/PublicIssueVisibilityTest.php`.

### F24 · Comments API leaked email/IP, lost concurrent writes, merged non-ASCII slugs — **fixed**

- **Fix:** `App\Domain\Comments\CommentStore` — exclusive-locked read-modify-write, files keyed by
  `sha1(slug)`, explicit public projection (`id`, `name`, `body`, `created_at`); routes require a
  *published* post with that exact slug (404 otherwise). No comments existed in production storage.
- **Tests:** `tests/Feature/Api/PublicCommentsTest.php` (5, incl. two forked processes appending
  concurrently — every append kept).
- **Honest status:** there is still no moderation UI; approval is `CommentStore::setStatus()` only. The
  feature is a receiver, not a finished module.

### F25 · Webhook SSRF guard only checked the first host's A records — **fixed**

- **Fix:** `App\Support\Http\OutboundHttpPolicy` (https only, no userinfo, no literal IPs/localhost,
  A **and** AAAA all public, IPv4-mapped/6to4/Teredo/NAT64 refused, connection pinned to the checked
  address via `CURLOPT_RESOLVE`, `allow_redirects=false`). Used by `DeliverWebhookJob` and
  `CollectionsFetchImportsCommand` (old per-class guards removed). The test bypass only skips DNS —
  redirects stay disabled.
- **Tests:** `tests/Feature/Collections/WebhookSsrfTest.php` (4: destination table, pinning/no-redirect
  options, 302 not followed + failed attempt recorded, private host never contacted).

### Stage 2 regression

Affected suites after Stage 2: **1476 passed, 17 failed** — the 17 are exactly the pre-existing baseline
failures (no new failure).

## Stage 3 — Reliable editing (F11–F14)

### F13 · Optimistic concurrency incomplete and non-atomic — **fixed**

- **Confirmed:** the SPA dropped `version` from GET and never sent `expected_version`; the token was
  `count:max(updated_at)` (repeatable within a second) checked *before* the transaction.
- **Fix:** migration `2026_09_22_000001_add_content_revision_to_content_tables` adds
  `content_revision` (bigint, default 0) to `pages`, `posts`, `theme_templates`. `BlockService::syncBlocks`
  compares-and-increments it **inside** the write transaction (`UPDATE … WHERE content_revision = ?`
  takes the row lock, so a concurrent writer re-evaluates against the committed value and fails):
  one winner, one `StaleContentRevisionException` (→ 409 with `current_version`). `blocksVersion()`
  returns the revision for these tables (legacy token elsewhere). Inline patches bump the same revision
  in their transaction, so a stale full save cannot clobber an inline edit. The interactive API now
  **requires** `expected_version`; programmatic last-write-wins must say `overwrite: true` (422 otherwise).
- **Tests:** `tests/Feature/Blocks/ContentRevisionTest.php` (3), **`ContentRevisionRaceTest`** (two forked
  OS processes, two independent connections, same start revision → exactly one success, one 409, no mixed
  tree), `BlockConcurrencyTest` updated to the new contract.
- **Migration note:** additive column with default; safe to run on prod (`php artisan migrate` as
  `cytechno`). Until it runs, `blocksVersion()` would 500 on the missing column → **deploy code and
  migration together**.

### F14 · Block definition rules not applied on sync — **fixed (with an explicit scope)**

- **Fix:** `App\Domain\Blocks\Support\BlockTreeValidator` runs inside `BlockService::syncBlocks`
  *before* any write, for every caller: unknown types are refused with a path (`blocks.0.children.1.type`),
  each node's data is validated with its definition's `validationRules()` for **shape/format**
  (max/in/regex/uuid/not_regex…), `allowsChildren` is enforced, size/depth caps apply. Errors surface as
  422 `{errors: {path: [...]}}` via `bootstrap/app.php`. Presence rules (`required*`) are deliberately
  **not** enforced on save (a freshly added block is legitimately incomplete during autosave);
  `maxChildren()` is not enforced (values are editor hints; real pages exceed them). Trusted programmatic
  writers (seeders, importers, wizards, clones — 20 call sites) use `syncTrusted()`, which skips the field
  rules but never the type check.
- **Inventory found by the validator:** `StarterTemplateService` emitted the unregistered type
  `feature-grid` (real type `featuregrid`) — fixed, test corrected; six definitions' CSS-dimension regex
  rejected a bare `0` that the section seeder and editors use — relaxed to `^(0|\d+(\.\d+)?(px|…))$`.
- **Tests:** `tests/Feature/Blocks/BlockTreeValidationTest.php` (4: unknown/nested invalid refused with
  paths and zero writes; programmatic callers refused too; every registered type accepts empty defaults
  through the real endpoint; save→load round trip).

### F11 · Save / autosave / Ctrl+S could lose an edit — **fixed**

- **Confirmed:** autosave and Ctrl+S read the block store even in canvas mode, Ctrl+S dropped raw_html,
  every path cleared `dirty` on *its* response regardless of newer edits or of the page having changed,
  and `blocks.sync` always sent `raw_html: ''` (a post/template save would have wiped a page's raw HTML
  had it been a page). Publish did not wait for a save.
- **Fix:** `resources/admin/src/lib/saveCoordinator.ts` — the single save path: serializes by the
  session's builder (canvas tree vs block tree), sends `raw_html` only for pages from the hydrated store,
  sends `expected_version` and stores the new revision, one request at a time with coalescing, clears
  `dirty` only when the sent state is still current (reference identity), ignores responses for a session
  that is no longer open, 409 → session `conflict` (local edit kept, autosave paused, "Конфликт — презареди"
  button re-hydrates from the server). `useAutoSave`, `useEditorShortcuts` (Ctrl+S = the page's Save
  handler), `PageEditor`/`PostEditor`/`TemplateEditor` Save/Publish/builder-switch, the canvas collab
  autosave and `VersionHistory` restore all go through it. `PublishButton` gets `onBeforePublish` and
  the page editor saves first (publish refused if the save fails). `blocks.sync` omits `raw_html` unless given.
- **Tests:** `resources/admin/src/lib/saveCoordinator.test.ts` (10: delayed A + edit B → B stays dirty
  and is saved with the new revision; 409 keeps the edit; failure never shows saved; foreign session
  ignored; canvas serializer + both dirty flags; one-at-a-time + coalescing; reload after conflict).

### F12 · Load order could leave Canvas empty or show a stale document — **fixed**

- **Fix:** `resources/admin/src/lib/editorHydration.ts` — `hydrateEditorSession()` runs once per session
  and only when the metadata **and** the blocks (with revision) for the *same* content id are present, in
  either order; canvas pages load the canvas store at that moment. Editors call `beginSession(key)` on id
  change (resets editor + canvas stores, session key, revision, conflict; PostEditor previously never reset
  its loaded flag). Save/Publish are disabled until hydrated; the coordinator refuses to save before that.
- **Tests:** `resources/admin/src/lib/editorHydration.test.ts` (5).

### Stage 3 verification

- Frontend: `vitest run` — **47 files, 456 tests passed**; `tsc --noEmit` — **0 errors** on this branch.
- Backend: see the full-suite result below.

## Stage 4 — Reliable publishing (F15–F18, F20, F27)

### F15 · Concurrent publishes and the reaper raced — **fixed**

- **Confirmed:** the active check ran before the advisory lock; the lock keyed the worker by deployment id,
  not site; rollback/delta/promote bypassed the guard; the reaper used the row's age (30 min) while the job
  timeout is 60 min.
- **Fix:** `App\Domain\Publishing\Services\DeploymentGate` — every deployment kind (full, partial, rollback,
  stale batch manual/auto, follow-up, scheduled) is created via `open()` inside the site advisory lock,
  after a heartbeat-based reap and the active check, and backed by a **partial unique index**
  `deployments_one_active_per_site` (migration `2026_09_22_000002`, reaps stuck rows first). Each deployment
  gets a site-monotonic `generation` and remembers the live `base_generation`; `mayGoLive()` fences the live
  swap (reaped/failed/superseded or a newer generation already live → no swap). `promote()` runs under the
  same lock and refuses a staged batch whose base generation is older than the live one. Workers heartbeat
  (`metadata.heartbeat_at`) on every status/progress write; a build with a fresh heartbeat is never reaped,
  a dead one (30 min silent; queued never started for 60 min) is.
- **Tests:** `tests/Feature/Publishing/DeploymentGateTest.php` (6), **`DeploymentGateRaceTest`** (two OS processes
  request a publish simultaneously → exactly one deployment).

### F16 · Retries could end without a real retry — **fixed**

- **Fix:** dedicated queue connection `builds` (`config/queue.php`, mirrors the default driver, `sync` in
  tests) with `retry_after 3900 > 3600` job timeout; `PublishSiteJob`, `BuildPostsChunkJob`, `RepublishStaleJob`
  and the post batch run on it; Horizon gets `supervisor-builds` (`config/horizon.php`, timeout 3700).
  Retry policy in `PublishSiteJob::handleFailure`: a `NonRetryableBuildException` (hard output errors,
  superseded deployment, missing rollback target) is terminal at once (`fail()`), any other failure keeps the
  deployment `building` (resumable staging dir, `retry_attempt`, `last_error`) and rethrows for the queue
  (backoff 30/90/180 s); the last attempt and sync runs end `failed`; `failed()` hook covers timeouts/max
  attempts. Page/post files are written atomically (tmp + rename) so `is_file()` is a safe resume criterion.
- **Tests:** `tests/Feature/Publishing/PublishRetryTest.php` (6).
- **Operator notes:** after deploying: `php artisan horizon:terminate` (Horizon re-reads the config and starts
  `supervisor-builds`); production `.env` already has `REDIS_QUEUE_RETRY_AFTER=300` for the default queue —
  the builds connection uses `BUILDS_QUEUE_RETRY_AFTER` (default 3900). Jobs already queued on `default`
  before the deploy still run there once.

### F17 · Auto-publish could leave saved changes unpublished — **fixed**

- **Fix:** `AutoPublishService` records a change made while a deployment runs (`needs_republish` on the entity,
  or the site-wide stale marker) instead of skipping it; `followUp()` republishes everything flagged since the
  finished deployment started in **one** coalesced delta batch (or a full build for site-wide/SSH sites), called
  at the end of every successful full publish and delta promotion. `StalenessResolver::clearForSite($site,
  $builtBefore)` clears only flags older than the build start. SSH/zip sites get a full build instead of an
  unsupported delta. `RepublishStaleJob` regenerates sitemap/feeds/archives and promotes even when the targets
  were unpublished (nothing to build, but the URLs must leave the indexes).
- **Tests:** `tests/Feature/Publishing/AutoPublishFollowUpTest.php` (3).
- **Residual:** metadata and blocks are still two requests from the SPA; the follow-up guarantees the second
  one is published, not that both land in the same deployment.

### F18 · Release artifacts were mutated; retention ignored active builds — **fixed**

- **Fix:** `DeployService::deployPartial` onto a symlink-served docroot now creates a **new release**
  (`builds/{id}-release`: hard-link copy of the live build + per-file tmp+rename merge) and swaps the symlink;
  the previous build's inodes are never touched. Rollback uses the target's `artifact_path` (a full release
  view, never a delta's partial staging dir). `BuildRetention` is state-aware: never deletes live symlink
  targets, active/staged builds, the newest live build per site or its `previous_build`/rollback references;
  the rest is kept per site up to N. Custom-domain (copy) docroots remain per-file deploys by nature (documented).
- **Tests:** `tests/Feature/Publishing/ImmutableReleaseTest.php` (2: A's tree hash unchanged after delta B,
  rollback to A and to B byte-for-byte; retention across sites).

### F20 · Scheduled publishing looked for a status that cannot exist — **fixed**

- **Fix:** contract is `draft` + due `scheduled_at` (what the schema, requests and editor already produce).
  `ProcessScheduledContentJob` iterates tenants with the GUC set (and reset in `finally`), flips due
  pages/posts (idempotent: `scheduled_at` cleared in the same write), flags them `needs_republish` (durable
  request) and publishes through the gate; if a deployment is active the flags carry the request to the
  follow-up.
- **Tests:** `tests/Feature/Publishing/ScheduledPublishTest.php` (2: two tenants under the restricted role,
  future item untouched, second run no duplicates; active deployment → request survives).

### F27 · "Blocking" errors did not block; score is not Lighthouse — **fixed**

- **Fix:** `OutputValidator` now produces hard errors (empty output, no `<html>`, truncated `</html>`);
  `PublishSiteJob` refuses to deploy when any page/post carries one (non-retryable failure, live untouched);
  parallel chunks record `metadata.hard_errors` for the finalize run; delta batches treat them as per-item
  failures. `metadata.lighthouse_checks.kind = 'heuristic'` (key kept for the admin UI, which already labels it
  "HTML validation"; `score_estimate` is the 100/95/80 constant).
- **Tests:** `tests/Feature/Publishing/OutputHardErrorTest.php` (3).

### Stage 4 verification

Full suite after Stage 4: **770 passed, 1118 risky, 25 failed** — 19 pre-existing + 6 caused by the new
invariants and fixed before commit: test fixtures that created two active deployments for one site
(`DeployTargetOwnershipTest`, `ParallelPostBuildTest` helpers now retire the earlier one), the fork-based
race tests leaving soft-deleted tenants behind (now hard-deleted), and a `''` tenant GUC that the RLS
policies cannot cast to uuid (the "no tenant" context is now the nil UUID, see F22).

## Stage 5 — Completed core flows (F09, F10, F19, F21, F22)

### F09 · Reset token never expired; mail never sent — **fixed**

- **Confirmed:** `now()->diffInMinutes($created) > 60` is a *signed* difference in Carbon 3 (negative
  for a past timestamp) — no token ever expired; the mail call was a comment.
- **Fix:** explicit `created_at <= now - 60 min`; `App\Notifications\PasswordResetLink` (queued mail with
  the `/admin/reset-password?token&email` link); the token is consumed once under a row lock
  (`lockForUpdate`, so two concurrent attempts yield one success); enumeration-safe answer unchanged.
  DB-driver sessions are invalidated; Redis sessions cannot be enumerated (documented). `users` has no
  `remember_token` column (rotation is conditional on the column).
- **Tests:** `tests/Feature/Auth/PasswordResetTest.php` (4, frozen-time 59/60/61 min, single use, foreign token).
- **UI:** "forgot your password?" flow on the login page; `/admin/reset-password` page.

### F10 · Invitations had no acceptance flow — **fixed**

- **Fix:** tokens stored **hashed** (sha256), 48 h expiry, `App\Notifications\UserInvitation` really sent
  (the raw link is still returned once to the inviting admin); public `GET /auth/invite/{token}` (masked
  email, 410 when invalid/expired/used) and `POST /auth/invite/{token}/accept` (password, one-time, row
  lock); `POST /users/{user}/invite/resend` (new token, old link dies) and `DELETE /users/{user}/invite`
  (hard-deletes the never-used placeholder so the email can be re-invited); the users list returns the
  status only, never the token. SPA: `/admin/invite/:token` page, Resend/Revoke actions in Users.
- **Tests:** `tests/Feature/Auth/InvitationFlowTest.php` (4: invite → open → accept → login; expired/reused/
  wrong; resend/revoke; no token in the list).
- **Note:** `MAIL_MAILER=log` in production — notifications land in the log until SMTP is configured.

### F19 · JSON backup was neither complete nor restorable — **fixed (declared scope)**

- **Confirmed:** export read a non-existent `parent_id` (model column is `parent_block_id`), dropped block
  ids, raw_html, editor mode, the theme document; `Site::redirects()` did not exist (the endpoint 500'd);
  `validateForRestore` was a shallow dry run; no restore existed; the endpoint had no role gate.
- **Fix:** `BackupExportService` schema **2.0.0**: block *trees*, raw_html, editor/experience mode, dates,
  theme document/config, categories (with parents), menus + nested items (by slug), redirects (with kind),
  section templates, asset metadata + checksums, secrets stripped recursively, and an explicit `scope`
  (`included` / `excluded`: asset bytes, collections, magazines, grids, sliders, global sections, forms,
  users, versions, translations are **not** in this backup). `restore()` writes a 2.0.0 manifest into an
  **empty** site in one transaction and reports counts; `POST sites/{site}/backup/restore` (owner only),
  export/validate admin+. `SiteCloneService::export` also strips secrets now.
- **Tests:** `tests/Feature/Sites/BackupRestoreTest.php` (3): export → JSON → restore into a clean site —
  nested tree equality (ids stripped), raw page, canvas mode, page parent, category tree, menu items with
  page links, redirects incl. regex, theme document, no secret marker; **built HTML of the restored home
  page equals the source** (ids/hashes normalised); non-empty target refused; poisoned manifest refused;
  a broken page entry rolls everything back; role gates.
- **Deferred with reason:** asset bytes (a media manifest with checksums is exported; a file bundle needs a
  streaming zip endpoint), collections/records and the magazine/DTP domains (own importers exist per domain).
  The admin UI still calls this "backup"; the manifest's `scope` is the source of truth.

### F21 · Share preview had a broken URL, no tenant context, an unsafe message handler — **fixed**

- **Fix:** token bound to `tenant/site/type/content` and validated at issue (content must belong to the
  site, type ∈ page|post, 422/404 otherwise); stored under its sha256; URL from the named route
  `preview.public` (`/api/v1/preview/{token}`, throttled); consumption runs in the token's tenant via
  `PublicTenantResolver::withTenant` and renders **read-only** (no editor listener, no edit mode). The
  authenticated editor preview's listener now checks `event.origin === APP_URL` **and**
  `event.source === window.parent`, validates the message shape, reaches the reload branch, and targets
  `data-sp-block` / `data-block-id`. `addBlockIds()` walks top-level blocks in order with a cursor, so two
  blocks of the same type get their own ids.
- **Tests:** `tests/Feature/Api/SharedPreviewTest.php` (4).

### F22 · Public routes worked only for the first tenant — **fixed**

- **Fix:** `App\Domain\Tenancy\PublicTenantResolver` — site id → tenant and public host → site, probing
  tenants once and caching (hits forever, misses 60 s), setting the GUC for the winner and **clearing it on
  a miss** ("no tenant" = the nil UUID, because every RLS policy casts the GUC to uuid and `''` errors);
  `withTenant()` restores the previous context even on exceptions. Used by `/media`, `/serve-font`, the
  `/{slug}` fallback (now scoped to the request host's site), `MagazineViewController::resolveSite`
  (`X-Original-Host` only from a trusted proxy), the analytics beacon and `SetTenantFromPublicSite`.
  `ProcessScheduledContentJob` restores its caller's context.
- **Tests:** `tests/Feature/Security/PublicTenantResolutionTest.php` (4: media/font/magazine/beacon/search
  for a *second* tenant, negative lookup leaves no context, cached mapping issues no tenant scan, context
  restore).
- **Residual:** `SetTenantFromAuth`/`TenantScope` set the GUC per request and PHP-FPM connections are not
  persistent, so request-level leakage was not reproduced; queue jobs set their own context at start.

## Stage 6 — Release quality (F26, F28–F31)

### F26 · CI did not run the backend; test config was overridden — **fixed (workflow not executed here)**

- **Fix:** `.github/workflows/ci.yml` — `backend` job: PostgreSQL 16 + pgvector service, lockfile
  `composer install`, PHP 8.3 with the needed extensions, `ALTER ROLE … NOSUPERUSER NOBYPASSRLS` so RLS is
  real, `migrate:fresh`, `php artisan test`; `frontend` job: `tsc --noEmit` **as the gate** (not
  `build:vite`), Vitest, Vite build, the block audit. `AppServiceProvider` no longer overrides the test
  drivers: the Redis fallback replaces a driver only when it is set to `redis` (the old unconditional
  override gave the suite a FILE cache that outlived runs — it produced a stale negative lookup during
  this work). `phpunit.xml` takes the DB password from the environment (F08).
- **Not verified:** the workflow itself was not executed (no runner here); the suite it runs is the one
  reported below. The `src/lib/__tests__/dtpConsistencyChecker.test.ts` exclusion in `vite.config.ts` is
  left explicit (it is a consistency-checker fixture, not a unit test) — documented, unchanged.

### F28 · Slug reuse after soft delete crashed on the unique index — **fixed**

`PostService::generateUniqueSlug` uses `withTrashed()` (as `PageService` already did); a lost race on the
index is retried with the next suffix. Test: `tests/Feature/Api/PostSlugReuseTest.php` (2).

### F29 · Date-time inputs mixed UTC and local — **fixed**

`resources/admin/src/lib/dateTimeLocal.ts`: `toLocalInputValue()` renders an instant as wall-clock time in
the browser zone (Intl-based, DST-correct) and `fromLocalInputValue()` sends an offset-aware ISO string back;
overlap → first (DST) occurrence, gap → the instant after it (documented, deterministic). Used for
`published_at`/`scheduled_at` in the post editor and the page scheduler. Test:
`resources/admin/src/lib/dateTimeLocal.test.ts` (4: Sofia winter/summer, UTC, round trips across both DST
edges without drift, boundary cases, null safety).

### F30 · CSV export lost fields and allowed spreadsheet formulas — **fixed**

Columns are the union of every submission's fields (first-seen order) computed in a first pass; cells
starting with `= + - @ \t \r` are prefixed with `'` (numbers such as `-12.5` stay numbers); `?raw=1` keeps
values verbatim for machine consumers. Test: `tests/Feature/Forms/FormExportTest.php` (2).

### F31 · Documentation and the two block audits contradicted each other — **fixed**

`scripts/block-manifest.json` declares helper dirs/files and internal types; `scripts/audit-blocks.mjs`
reads it, counts only real `implements BlockDefinition` classes and reports `INTERNAL` (not orphan) for
slide/slider; `scripts/block-audit.sh` is now a wrapper around it (one audit, same result from both entry
points, run in CI). The real gap it found — `collection-categories` had no PHP definition — is closed
(`CollectionCategoriesBlockDefinition`). Result: **102 COMPLETE, 2 INTERNAL, exit 0**. README block/test/CI
claims corrected.

## Hypotheses (H01–H04) — status

- **H01 SSH shell construction:** not changed in this cycle. `UpdateSiteRequest` validates the port
  (1–65535) and `settings.deploy_ssh_*` are admin-only; the create/import/clone entry points were not
  re-audited for the exact Laravel 13 validation semantics. Recommended next: argument-vector `Process`,
  value objects for host/user/port/path, refuse `/` (empty path) before `rsync --delete`.
- **H02 CORS/CSRF on custom domains:** not changed; needs browser tests on a real custom domain.
- **H03 memory/resource limits of imports:** not measured.
- **H04 live parity / Lighthouse:** not measured; the deployment metadata is now labelled heuristic (F27).

## What is NOT fixed (deferred with reason)

| ID | Reason |
|---|---|
| F19 (partial) | asset bytes / collections / magazines / DTP outside the declared backup scope — needs per-domain exporters and a streaming bundle |
| F08 (partial) | AI keys are masked and preserved but stored in plain `settings` JSON; encryption at rest needs a data migration and readers (nothing in `app/` reads them today) |
| F17 (partial) | metadata + blocks are still two SPA requests; the follow-up guarantees nothing is lost, not that both land in one deployment |
| F18 (partial) | custom-domain (copy) docroots remain per-file deploys — Hestia docroots cannot be symlink-swapped |
| H01–H04 | see above |
| Pre-existing test failures (19) | `Unit\InlineEdit\PageInlinePolicyTest` ×3 / `SpEditableRenderTest` ×4 (`@dataProvider` annotations on PHPUnit 12), `InspectorRoundTripTest`, `CollectionHierarchyTest` ×2, `CollectionPublishTest` ×4, `InlineEditRbacTest`, `CloudflarePurgeTest`, `RowColumnLayoutTest`, `SiteWizardFlowTest`, `TokenProfileTest` — unrelated to this audit's findings, untouched so the baseline stays honest |

## Migrations and rollout

1. `2026_09_22_000001_add_content_revision_to_content_tables` — additive (default 0); **deploy together
   with the code** (the code reads the column on every save/version check).
2. `2026_09_22_000002_one_active_deployment_per_site` — reaps deployments stuck in an active status for over
   an hour (and duplicates), then creates the partial unique index. Safe on prod; a `building` row that a
   worker is genuinely still processing (>1 h) would be marked failed — the job then stops at its fence.
3. Run as `cytechno`: `php artisan migrate --force`; then `php artisan queue:restart` and
   `php artisan horizon:terminate` (new `supervisor-builds` / `builds` connection).
4. Frontend: `npx vite build` in `resources/admin` (tsc is clean on this branch).
5. Optional env: `CMS_UPDATES_*` stay unset (web updates disabled); `BUILDS_QUEUE_RETRY_AFTER` defaults to
   3900; `PUBLISH_RESERVED_DOMAINS` defaults to the three admin hosts.

## Operator actions required (not done here — production changes)

1. **Rotate the `cms_saas` PostgreSQL password** (it was committed in `phpunit.xml` and ships in every CMS
   export zip; `public/dl/cms-platform-2026-09-22-*.zip` is publicly reachable over HTTPS right now): `ALTER
   ROLE`, update `.env`, reload php-fpm, restart Horizon. Delete the zips under `public/dl/` and
   `storage/app/cms-export.zip`, regenerate if needed.
2. Configure SMTP (`MAIL_MAILER=log` today) so reset/invitation mails leave the log.
3. First publish after deploy claims each custom-domain docroot with `.cms-site` (no action; just expected).

## Final verification (this branch)

- PHP: see the last full-suite line appended below.
- Frontend: `vitest run` **48 files / 460 tests passed**; `tsc --noEmit` **0 errors**.
- Block audit: 102 COMPLETE, 2 INTERNAL, exit 0 (both entry points).
- Not executed: GitHub workflow, browser tests, Lighthouse, load tests, production deploy.

### Final full suite (branch head)

`php artisan test` on PostgreSQL 16 / restricted role: **770 passed, 1146 risky, 19 failed** (10 188 assertions,
after adding the reference extractor for the new `collection-categories` definition). The 19 failures are
exactly the pre-existing baseline set listed above; every test added in this cycle passes. "Risky" is
PHPUnit 12's "test code or tested code removed error handlers" flag (pre-existing, harmless, not silenced).


## Round 2 (2026-09-23)

### Pre-existing failures (19) — **fixed**, none by weakening the guarded behaviour

| Test | Cause | Fix |
|---|---|---|
| `PageInlinePolicyTest` ×3, `SpEditableRenderTest` ×4 | `@dataProvider` docblocks are ignored by PHPUnit 12 → ArgumentCountError | attributes; `cross_tenant` got its own `roles()` provider (arity warning) |
| `SpEditableRenderTest` (heading) | pinned snapshot predates the deliberate `0.4em` heading margin | re-pinned after review (`SP_UPDATE_SNAPSHOTS=1` switch); "no artifacts" now asserts no inline-edit **overlay** instead of no `<script>` (timers/decks ship their own runtime) |
| `InlineEditRbacTest` | other tenant's site inserted under the wrong RLS context | fixture sets that tenant's context |
| `CollectionPublishTest` ×4, `CollectionHierarchyTest` ×2 | URLs/shards now carry the slug-hosting base path | base-aware assertions |
| `InspectorRoundTripTest` | asserted no `url(` anywhere — the theme's Google Fonts import is legitimate; the payload itself was already stripped (verified) | asserts the injected payload is absent |
| `CloudflarePurgeTest` | purger deliberately moved to `purge_everything` (documented in the class) | test follows the documented contract |
| `RowColumnLayoutTest` | presets are 12-grid spans (`1/3+2/3` → `4fr 8fr`, same ratio) | expectation updated |
| `SiteWizardFlowTest`, `TokenProfileTest` | order-dependent | root cause below |

**Real bug found by the order-dependent tests:** `AssetPublisher::$deployTarget` is static and the build jobs
never cleared it — a long-lived worker kept the previous build's directory, so a later job/preview could write
assets and self-hosted fonts into an OLD build. All three build jobs now reset it in `finally`
(`tests/Feature/Publishing/DeployTargetLeakTest.php`).

### H01 · SSH deploy shell construction — **confirmed reachable, fixed**

`CreateSiteRequest` validated `settings` only as an array, so a tenant admin could create a site with
`deploy_ssh_port = "22; …"`; it was interpolated unescaped into a shell string on the next publish (command
execution as the web user). Also: a user starting with `-` became an rsync option, `deploy_ssh_key` could name
any server file (e.g. the platform's own SSH identity), an empty path became `/` under `rsync --delete`.
`App\Domain\Publishing\Services\Deploy\SshTarget` validates host/user/port/path/key on create, update
(after resolving the masked key) and right before the deploy; rsync runs as an argument vector (no shell);
keys only from `publishing.ssh_keys_path` (`PUBLISH_SSH_KEYS_PATH`, default `storage/app/ssh-keys`); `/` and
system directories refused. No production site uses SSH deploy (checked read-only), so nothing to migrate.
Tests: `tests/Feature/Publishing/SshDeployHardeningTest.php` (17).

### H02 · CORS for public forms on custom domains — **confirmed, fixed**

The published `contact-form` posts FormData with `X-Requested-With` (not a simple request → preflight). The
global credentialed CORS handler answered that preflight **without** `Access-Control-Allow-Origin` for a
custom domain, so browsers blocked the form on every custom-domain site. `App\Http\Middleware\HandleCors`
now skips the public per-site endpoints (exact per-segment patterns — admin routes such as
`sites/{site}/wizard/search` are not affected); `PublicSiteCors` answers OPTIONS for the site's own origins
(custom domain, www, slug subdomain, CMS origin), advertises GET/HEAD/POST only and never allows credentials.
Tests: `tests/Feature/Security/PublicCorsTest.php` (4). Not browser-tested on a live domain.

### F08 · Encryption at rest — **fixed**

`App\Casts\SiteSettings` replaces the `array` cast on `sites.settings`: values under secret keys (any depth)
are stored as `enc:v1:<Crypt payload>` and decrypted on read, so the GA/Cloudflare readers are unchanged;
legacy plaintext reads fine and is encrypted on the next save; an undecryptable value (APP_KEY changed) reads
as null and is logged. No production site currently stores such a secret (checked read-only), so no data
migration is required. **Rotating APP_KEY now requires re-entering site credentials.**
Tests: `tests/Feature/Security/SiteSecretsAtRestTest.php` (3).

### F19 · Asset bytes — **fixed**

`App\Services\BackupBundleService`: bundle = `manifest.json` + `assets/{id}.{ext}`. Restore (empty target, one
transaction) accepts only allow-listed entry names/extensions, verifies sha256 per file, refuses active content,
sanitizes SVG, creates assets with **new ids**, rewrites asset ids and the source site id in every reference
(blocks, serve URLs, featured images), and deletes written files if the DB restore fails. Admin download
`GET sites/{site}/backup/bundle`; `php artisan cms:backup:export {site}` and `cms:backup:restore {zip} {site}`
(regenerates WebP variants). Tests: `tests/Feature/Sites/BackupBundleTest.php` (4). Still excluded from the
backup scope: collections/records, magazines/DTP, grids, sliders, global sections, forms, users, versions.

### Round 2 verification

Clean full run on the branch head: **905 passed, 0 failed, exit 0** (10 605 assertions). The 1 188 "risky"
entries are PHPUnit 12's "test code or tested code removed error handlers" notice — pre-existing, not silenced.
The one `AssetVariantsTest` failure seen in an intermediate run did not reproduce (that run overlapped with code
edits). Frontend unchanged this round (48 files / 460 tests, tsc 0 errors at the previous check).

**Remaining open (by decision, not oversight):** merge/deploy to production and the operator actions above
(DB password rotation, public zip removal, SMTP); H03 load/memory measurements and H04 Lighthouse/visual parity
need a running environment; backup scope still excludes collections, magazines/DTP, grids, sliders, global
sections, forms, users and versions; F17 metadata+blocks remain two requests (nothing is lost, the follow-up
publishes the second).


## Round 3 (2026-09-23)

### F17 residual · metadata + blocks in ONE deployment — **fixed**

The editor's metadata PUT sends `defer_publish: true`; the post/page is only flagged (`needs_republish`, durable)
and the following blocks save triggers the single build containing both. Callers without the flag keep the
immediate auto-publish. Test: `tests/Feature/Publishing/MetadataBlocksSinglePublishTest.php` (2).

### H03 · Measured and fixed (test DB, same server; `scripts/bench/blocks-bench.php`)

| Operation | Before | After |
|---|---|---|
| save 50 blocks | 0.76 s / 70 queries | 0.08 s / 12 |
| save 200 blocks | 2.8 s / 241 | 0.14 s / 13 |
| save 500 blocks | 7.6 s / 586 | 0.28 s / 14 |
| render 500 blocks | 4.9 s / 589 queries | 0.64 s / 14 |
| full publish, 100 posts | 15.9 s | 11.0 s |

Causes: per-node `exists()+create()` (→ one collision lookup + chunked INSERTs), quadratic `buildTree`
(→ group by parent once), one Validator per node with every rule (→ rules cached per type, only rules for fields
present; all shape rules are `sometimes`), one children query per rendered block (→ `Block::preloadTree()`).
Published bytes unchanged (pinned inline-edit snapshots pass). Not measured: imports/large uploads, worker memory
under concurrent builds.

### H04 · Real Lighthouse measurement (Lighthouse 12.8.2, mobile preset)

Sample site published from this branch (`scripts/bench/publish-sample.php`, served locally by `php -S`):
first run **Accessibility 90** — the button on every NEW site failed contrast: `SiteService::createSite` seeded
primary `#3b82f6` (3.68:1 with white), although `DesignTokenGenerator` had already moved its fallback to
`#1b6df5` (4.61:1). Seed aligned (existing themes untouched); test `tests/Feature/Sites/DefaultThemeContrastTest.php`.
After: **Performance 99, Accessibility 100, Best Practices 96, SEO 100** (LCP 1.8 s, CLS 0.002, TBT 40 ms). The
96 is the sample's analytics beacon hitting the production API for a site that only exists in the test DB;
compression/cache-TTL hints come from `php -S`, not from production hosting.

Live reference (read-only): monikcreations.eu 100/100/100/**92** (homepage has no meta description — content);
ensodo.eu 100/**97**/96/100 (heading-order: an `<h3>` without a preceding `<h2>` in page content; one console
error). Content fixes, not code. Screenshot/visual parity of block/canvas/DTP fixtures is still not automated.

### Round 3 verification

Full PHP suite: **0 failed**, exit 0 (905 passed + 1 191 "risky" = PHPUnit 12 error-handler notice, 10 628 assertions). Frontend: 460/460 on three consecutive reruns and tsc 0 errors. One Vitest run failed while the PHP suite was running in parallel and did not reproduce under the same load; the failing test was not captured.
