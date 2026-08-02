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
