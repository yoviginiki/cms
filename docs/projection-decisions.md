# Projection Decisions Journal

Every time the specification does not cover a case and a decision is taken, it
is recorded here with a one-line rationale — and work stops for owner review
(autonomous-chain condition 6). An empty journal after a completed chain means
no un-sanctioned decisions were made silently.

## Taken decisions

- **D1 — post-content projects as structural / zero-emission (Option A).**
  `post-content` is a dynamic slot: its Blade renders `$__postContentHtml` from
  the ambient Post render context (`resources/views/blocks/post-content.blade.php:17`)
  and holds no fields in `blocks.data`. Its `projection()` returns a descriptor
  with `schemaType(null)` and no fields — it opts into the contract but emits
  nothing of its own. Rationale: the post body is projected at its constituent
  blocks (heading/rich-text/image); projecting it again here would double-count
  the body and break parity (Prime Directive 4) and segment-hash determinism
  (Prime Directive 6). This is the faithful application of Prime Directive 3
  ("empty is acceptable"), keeps the Gate 0 pilot choice, extends no model, and
  is reversible. **The owner was asked to choose between (A) this, (B) a
  context-sourced field concept, and (C) swapping the 4th pilot; the owner was
  away, so (A) — the least presumptuous, model-neutral, reversible option — was
  taken and the chain was STOPPED here for owner review per condition 6.**
  If the owner prefers (B) or (C), only this one descriptor + its test change.
  → **OWNER CONFIRMED D1** (2026-08-01). The chain resumes to Phase 3.

- **D2 — parity failure is page-granular, not deploy-fatal.** Spec 4.3 says a
  parity mismatch means "the build fails, naming the offending field." In the
  live pipeline, aborting an entire multi-page deploy because one page's
  structured data diverges would brick unrelated pages. Instead, on a mismatch
  the pipeline **skips that page's sidecar** (so no divergent projection ever
  ships — the Prime Directive 4 intent) and records the offending field as a
  build **error** in `validationResults["projection:<slug>"]`. The hard,
  build-breaking enforcement of parity lives in the guard's unit test, exactly
  as the spec prescribes ("proven by a test that breaks the build"). Rationale:
  preserves "no divergence ships" + names the field, without turning a
  single-page SEO issue into a site-wide publish outage. **OWNER CONFIRMED D2**
  (2026-08-01).

- **D3 — the internal projection snapshot is populated only when the site has
  opted into the projection.** Phase 4.5 stores the full internal projection in
  `page_versions.projection_snapshot`. Populating it for every publish of every
  site would roughly double per-version storage even for sites that never use
  the projection. So the snapshot is written only when
  `crawler_policy.projection_access` is enabled (the same gate as the sidecar).
  Sites can opt in as Sumi / Ledger roll out. This was the recommendation
  presented in the Phase 4.5 gate message and **accepted by the owner's "да"**
  approving that gate.

- **Migration applied to the TEST database only.** `.env` has
  `APP_ENV=production`; the default connection `cms_saas_platform` is the live
  multi-tenant production database (it holds the production tenants). The
  approved migration was therefore NOT run against it. It is applied
  automatically to `cms_saas_platform_test` by the RefreshDatabase test suite,
  which validates the SQL and the 4.5 wiring end-to-end. **The owner must apply
  the migration to production via the normal deploy process.**
  → Update: the owner later approved running the 4.5 migration (and the Ledger
  `site_health_reports` migration) directly against production, --path scoped.

- **D4 — Sumi stores embeddings as jsonb + cosine-in-PHP, not pgvector.** The
  recommended plan was pgvector. The DB role is NOT a superuser and
  `CREATE EXTENSION vector` fails ("permission denied, must be superuser") — and
  such a migration would break the whole RefreshDatabase test suite. So
  `rag_chunks.embedding` is jsonb (a float array) and `RagStore::search` computes
  cosine in PHP. Correct for typical site sizes; pgvector + an ANN index remains
  a pure optimization once a DBA installs the extension (a later additive
  migration). Unblocks Sumi end-to-end without superuser. Recorded for visibility.
  - **Update (2026-08-01, DBA session): the infrastructure constraint behind D4
    no longer holds.** `vector` 0.6.0 was installed on the dev/test database
    (`cms_saas_platform_test`) via `CREATE EXTENSION`, and the app role `cms_saas`
    can use the type (`::vector`, `<=>`) with **no extra grants**. So "the DB role
    is not a superuser and cannot install the extension" is resolved. **D4 itself
    stands** — jsonb + PHP cosine is still the live implementation; D4 is retired
    only by the session that actually migrates to a `vector(N)` column + ANN index.
    Migration plan, dimension caveat, and production install runbook:
    `docs/pgvector-handoff.md` and `docs/pgvector-production-install.md`.

- **D5 — pgvector migration, phase 1: additive `vector(1024)` column + exact
  retriever behind an interface. jsonb path unchanged; no read-switch, no ANN
  index yet.** Approved by the owner ("да", gate on the SQL). Additive only:
  - Migration `2026_08_01_000004_add_embedding_vec_to_rag_chunks` adds
    `embedding_vec vector(1024)` **beside** the existing `embedding` jsonb.
    `N=1024` = the production embedder (voyage-3), kept as
    `RagChunkRecord::VECTOR_DIMS`. The migration does **not** `CREATE EXTENSION`
    (app role is not superuser) and guards with a clear error if the extension is
    absent — so it is safe to run only where a superuser has installed pgvector
    (test has it; prod install is the owner's manual step per the runbook).
  - `RetrieverInterface` extracted; `JsonbCosineRetriever` wraps the existing
    `RagStore::search` (old path, untouched), `PgvectorRetriever` uses exact
    `embedding_vec <=> :q` cosine distance (no HNSW). Nothing above the interface
    knows which backend answered. `SumiAssistant` still reads via the old path —
    the switch is a later gate.
  - Dual-write: `RagStore::store` writes `embedding_vec` alongside the jsonb, but
    **only** when the embedding length == `VECTOR_DIMS` and the column exists.
    Offline hash-16 rows stay jsonb-only; a host without the (hand-run) migration
    keeps indexing without error. Backfill of existing rows: `php artisan
    projection:rag:backfill-vector {site}` (copies jsonb → vector, no re-embed,
    zero Voyage cost).
  - **Parity gate** (`tests/Feature/Publishing/RagPgvectorParityTest`): the same
    query through both retrievers returns the same top-K membership, the same
    top-1, and per-chunk scores within `1e-4` (float4 storage vs float8 jsonb).
    Green. This is the honest "разминаване = спираш" check — and the reason the
    **HNSW index is deferred to its own gate**: HNSW is approximate and would make
    an exact parity assertion meaningless. The handoff's steps 3–5 (HNSW build,
    tuning parallel period, read-switch) and step 6 (drop jsonb) remain future,
    separately-gated sessions.
  - **D4 still stands**: jsonb + PHP cosine is still the live read path. D5 only
    lays the additive column + validated second retriever next to it. Migration
    applied to the **test** DB only (via RefreshDatabase); **production is not
    migrated** — the owner runs the extension install + this migration there when
    they decide.

- **D6 — global kill-switch for the pgvector path (`cms.sumi.pgvector`, default
  OFF).** Owner-requested: pgvector could add server load, so they want an
  on/off. A global config flag (`env SUMI_PGVECTOR_ENABLED`, default false) gates
  `RagStore::writeVector` (and the future read-switch): when off, the vector
  column/index are fully inert — no dual-write, reads stay on jsonb — so a host
  can carry the schema at zero added load until the operator opts in. Global
  (not per-site) because the motivation is instance-level server load; a per-site
  UI toggle can be layered later if wanted. Additive, no migration. The parity
  test flips the flag on in setUp; a new test asserts dual-write is skipped when
  off. **Owner-approved plan ("да").** Chosen over per-site UI per the plan's
  recommendation.

- **D7 — read-switch: retrieval routes through `RetrieverResolver`, gated by the
  same `cms.sumi.pgvector` flag AND the query dimension.** Owner-requested
  ("davai read-switch-a"). `SumiAssistant` no longer calls `RagStore::search`
  directly; it asks `RetrieverResolver::forQuery($qe)`, which returns
  `PgvectorRetriever` ONLY when the switch is on AND `count($qe) === VECTOR_DIMS`
  (1024), else `JsonbCosineRetriever`. The dimension guard mirrors the dual-write
  rule, so reads hit `embedding_vec` exactly when it is populated — flipping the
  switch before content is re-embedded at full dim cannot produce an empty-result
  trap (short/offline queries stay on jsonb). PgvectorRetriever is still EXACT
  (no HNSW), so the switch is behaviourally equivalent to jsonb — the parity test
  proves it; the APPROXIMATE HNSW read stays a separate later gate (recall
  measurement + `000005` + `hnsw.ef_search`). Additive, no migration; jsonb is
  still the default (switch off). Tests: `RetrieverResolverTest` (routing matrix)
  + a `SumiAssistant` read-switch test (flag on + 1024-dim → answer via pgvector).
  **D4 still stands** as the default; D7 only adds the flag-gated alternate read.

- **D8 — selectable embedding provider; a local Ollama path added alongside
  Voyage.** Owner-requested (no Voyage key, but a home GPU box runs Ollama with
  `bge-m3`). New `OllamaEmbedder` (HTTP to Ollama `/api/embeddings`, default host
  `10.10.0.2:11434` = the Home PC on the wg0 tunnel, model `bge-m3`, 1024-dim —
  fits `vector(1024)`, no migration). `EmbedderFactory` picks the provider from
  `cms.sumi.embedder`: `voyage | ollama | hash | auto` (auto = Voyage-if-key-else-
  hash, backward-compatible default). Both provider implementations stay in the
  codebase; the operator switches in settings. The rag index/ask commands now
  build their embedder via the factory. Additive, no migration; default `auto`
  preserves prior behaviour. Tests: `OllamaEmbedderTest` (Http::fake) +
  `EmbedderFactoryTest` (routing). **NB — activation still blocked on a running
  embedder:** the Home PC is physically OFF (wg0 peer, last handshake 32 days
  ago); Voyage has no key. The switch + code are ready for the moment either an
  Ollama host is up or a key is set.

## Module Framework + Culture Engine session (2026-08-07)

- **DB-ENV — build/test against `cms_saas_platform_test` only; production
  migrations are an explicit owner handoff, never run in-session.** Phase 0
  pre-flight found `.env` resolves to the production database `cms_saas_platform`
  (`APP_ENV=production`) and there is no `.env.testing`; the testing env lives
  inline in `phpunit.xml` (`cms_saas_platform_test`). Per Phase 0 step 2 this is
  a production-name match → `php artisan migrate` is NEVER run in this session
  (it would bind to `.env` = prod). All development and the full test suite run
  against `cms_saas_platform_test` via `RefreshDatabase`, which never touches
  prod. The finished migrations are handed to the owner to run against prod in a
  maintenance window (single-confirm gate). Rationale: satisfies the FORBIDDEN /
  SINGLE-CONFIRM rules and Autonomy Rule 5 while still allowing full TDD.
  → Owner said "go" (2026-08-07): proceed on the test DB.

- **RBAC — the three module abilities map onto the existing role hierarchy;
  no named-permission layer is introduced.** The spec (§2.1) names permissions
  `module.culture.use`, `module.culture.manage`, `modules.administer` and says
  "wire into the existing RBAC, do not invent a parallel one." The existing RBAC
  is a *linear role hierarchy* — `User::hasMinimumRole()` + `EnsureRole`
  middleware (`role` alias), roles `viewer<author<editor<admin<owner`, enforced
  by the `users_role_check` DB constraint. There is no permissions table / policy
  primitive to attach named abilities to. Building one would be the forbidden
  "parallel system." Decision: express the three abilities as role thresholds and
  gate via the existing `role` middleware + policy methods:
    - `module.culture.use`    → `editor`+ (see module UI, view received drafts)
    - `module.culture.manage` → `admin`+ (tenant on/off, settings, tokens)
    - `modules.administer`    → `owner`  (platform global on/off, all modules)
  A thin `ModulePermissions` helper centralises these thresholds so the mapping is
  changeable in one place if the owner later disagrees. Additive, reversible.
  → Owner said "go" (2026-08-07): approved the role-threshold mapping.

- **RLS-TOKENS — `module_tokens` carries NO tenant RLS (mirrors Sanctum's
  `personal_access_tokens`); `module_tenant` gets standard FORCE-RLS.** Spec
  §1.1 says token/pivot RLS must "mirror whatever pattern the audit found; if
  ambiguous, log a decision and STOP." `module_tenant` (tenant_id NOT NULL) is
  unambiguous → `tenant_isolation` policy `tenant_id = current_setting(
  'app.current_tenant_id', true)::uuid`, ENABLE+FORCE, exactly like `webhooks`.
  `module_tokens` has an auth-bootstrap conflict: the bearer token identifies the
  tenant, so the row must be found *before* any tenant GUC can be set — RLS keyed
  on the GUC would hide every row and make auth impossible. The audit shows the
  nearest existing precedent is Sanctum's `personal_access_tokens`, which is
  deliberately NOT tenant-RLS'd for this exact reason. Decision (not a STOP — the
  pattern is determinable, not ambiguous): `module_tokens` is an auth-credential
  table with no tenant RLS; the `AuthModuleToken` guard looks the token up by
  hash, then SETs `app.current_tenant_id` from `token.tenant_id` (identical shape
  to `SetTenantFromAuth`); all management reads/writes filter by `tenant_id`
  explicitly and are RBAC-gated (`module.culture.manage` = admin+). Only the
  hash is stored, so a row read yields no usable secret — same tradeoff Sanctum
  accepts. Reversible. If the owner wants stricter isolation, a `SECURITY DEFINER`
  lookup function is the fallback.

- **AUDIT — token-authenticated requests are audited to a dedicated
  `module_api_logs` table, not the existing `activity_logs`.** Spec §2.2 says use
  "existing audit mechanism if present; otherwise a minimal `module_api_logs`
  table — log the decision." The existing `ActivityLogService`/`activity_logs`
  is user- and site-centric: it stamps `user_id = Auth::id()` and its RLS policy
  is `site_id IN (sites WHERE tenant = GUC)`. Token requests have no
  authenticated user, and may be platform-level or tenant-level with NO site —
  a null `site_id` row fails that policy's WITH CHECK and cannot be inserted. So
  the existing mechanism structurally cannot log these requests. Decision: a
  minimal `module_api_logs` table (module_id, module_token_id, tenant_id nullable,
  method, path, ability, decision, status_code, ip). No tenant RLS — it is
  auth-adjacent (a denied-auth request has no tenant) and mirrors the
  RLS-TOKENS rationale; admin reads filter by tenant_id + RBAC. Writes are
  best-effort (try/catch, never block the request).

- **VISUAL-GATE — Phase 3 Playwright capture deferred to close-out (owner away).**
  Spec Phase 3 ends "STOP. Present screenshots/Playwright captures." In THIS
  environment that is not possible without crossing into production: the admin
  vite build outputs to `public/admin-assets/` (the LIVE bundle — `npx vite
  build` is the deploy step), and serving the SPA drives `/api/v1` against the
  prod `.env` DB. Both violate DB-ENV. The owner was asked how to handle the
  visual gate and was away (60s no response). Best-judgment decision: the Phase 3
  code is complete and verified as far as is prod-safe — backend 6/6 feature
  tests green, frontend `tsc --noEmit` clean — and the live visual capture is
  deferred to Phase 5, to be done via a prod-safe scratch build (`vite build
  --outDir` to a throwaway dir + Playwright with `/api/v1/modules` intercepted by
  canned JSON), or handed to the owner's staging env. Proceeding to Phase 4.
  → **RESOLVED (2026-08-07):** owner said "go"; ran the prod-safe path. Scratch
  `vite build --base=/ --outDir <tmp>` (never touched `public/admin-assets/`),
  served statically, Playwright (Chromium 1228) with all `/api/v1/**` intercepted
  by canned JSON. The Modules screen renders correctly — both gated nav entries,
  Culture Engine card, platform/tenant toggles, settings-schema select, token
  manager with the seeded token — normally (2.2s) and under CDP Slow-3G
  throttling (15.1s full load, no crash). Screenshots in the session scratchpad.
  Zero production contact. Settings UI upgraded YELLOW→GREEN in STATUS.md.

- **ENTITY — received bulletins are created as `Post` drafts (not Pages).**
  Spec §4.1 says "choose the entity the audit shows is appropriate for articles;
  log the decision." The audit shows `Post` is the blog/article entity (category,
  tags, excerpt, post_format, published_at, RSS) while `Page` is structural site
  chrome. Bulletins are dated editorial articles → `Post`, created via the
  existing `PostService::createPost` (slug uniqueness, default category) with
  `status='draft'`. Never published (Prime Directive; spec §4.1).

- **TARGET-SITE — the draft's site = module_tenant.settings['target_site_id'],
  else the tenant's first site.** The contract payload names no site, but a Post
  needs one. Resolution: if the tenant's culture-engine settings carry a valid
  `target_site_id` (belonging to the tenant) use it; otherwise fall back to the
  tenant's earliest-created site. No site at all → 422 `no_target_site`. Keeps the
  wire contract unchanged and configurable from Settings → Modules later.

- **IDEMPOTENCY — `(module_id, tenant_id, idempotency_key)` unique, payload hash
  = sha256 of the raw request body.** New tenant-RLS'd table
  `module_idempotency_keys` (written inside the token's tenant context). Same key
  + same body-hash → 200 with the SAME external_id (the existing draft); same key
  + different hash → 409 `idempotency_key_conflict`; no key → create without
  dedupe. Type validation runs BEFORE any write, so an unknown-block 422 persists
  nothing. external_id returned to the Culture Engine is the draft Post's uuid.
