# Content Projection Layer + Consumers — STATUS

Honest status matrix. Where something is fragile, blocked, or unproven, it says so.
Last updated: 2026-08-02.

## Substrate — Content Projection Layer (Phase 0–6)

| Component | Status | Grade | Notes |
|---|---|---|---|
| Addressing (`{uuid}#{path}`, no `data.` prefix) | Done | A | Unifies the editor's `data-sp-field`; no second scheme. |
| Descriptor contract (`ProvidesProjection` / `BlockProjection`) | Done | A | Additive, opt-in; ~100 existing blocks untouched. |
| Pilot descriptors (heading, rich-text, image, post-content) | Done | A | post-content intentionally inert (D1). |
| Pure `ProjectionBuilder` + 4 views | Done | A | Zero I/O; 10 golden tests; build <50ms. |
| Publish integration (sidecars, manifest) | Done | A | Gated (default off) → regression byte-identical. |
| Parity guard | Done | A− | Public facts = schema_org text; page-granular failure (D2). |
| Internal snapshot (`page_versions.projection_snapshot`, 4.5) | Done | A | Prod migration applied. |
| Crawler policy → robots.txt (Phase 5) | Done | A | training/retrieval/search split; default permissive. |
| schema.org validation | Partial | B | Structural only (no network for the online validator). |

## Consumers (first slices built)

| Consumer | Status | Grade | What's done / open |
|---|---|---|---|
| **Export** | Done | A | `ProjectionExporter` (JSON/MD) + CLI `projection:export` + API endpoint. |
| **Site Health Ledger** | Partial | B+ | `BrokenLinkScanner` + `projection:health --store` + `site_health_reports` (prod migration applied). Open: PageSpeed / stale_refs report types, history API/UI. |
| **AI Change Proposals** | Partial | A− | `ProposalDiffer` (canonical diff) + `ProposalApplier` (write-back via InlineEdit sanitize, optimistic stale-check). Open: LLM generation of proposals, persistence, review UI. |
| **Sumi (RAG)** | Built, not activated | B | index → retrieve → cited chat; pgvector Phase 1 (see below). **Blocked on a running embedder** (Home PC off / no Voyage key). |

## Sumi / pgvector (D4–D8)

| Item | Status | Notes |
|---|---|---|
| jsonb + PHP-cosine retrieval (D4) | Done | The live read path. |
| Additive `embedding_vec vector(1024)` (D5, mig `000004`) | Done | Applied to prod (extension installed by owner). jsonb kept. |
| `RetrieverInterface` + Jsonb/Pgvector retrievers | Done | pgvector is EXACT (`<=>`, no HNSW) → parity-equivalent to jsonb. |
| Dual-write + parity test | Done | Same query, both paths → same top-K / top-1 / scores (ε=1e-4). |
| Kill-switch `cms.sumi.pgvector` (D6, default OFF) | Done | Off → vector path fully inert (zero server load). |
| Read-switch via `RetrieverResolver` (D7) | Done | Routes to pgvector only when switch on AND query dim == 1024. |
| Selectable embedder + local Ollama (D8) | Done | `cms.sumi.embedder` = auto\|voyage\|ollama\|hash. `OllamaEmbedder` → bge-m3 1024-dim. |
| HNSW index (mig `000005`) | Pending / deferred | Approximate read — needs data + a recall-vs-exact measurement. Separate gate. |
| **Activation** | **Blocked (external)** | Home PC (10.10.0.2) physically OFF; no Voyage key. Turn on Home PC → `SUMI_EMBEDDER=ollama`, or set a Voyage key; then flag ON + reindex. |

## Test coverage (2026-08-02)

- Projection unit: **64 passed** (contract, builder golden ×10, parity, publisher, robots policy, schema validation, retrievers, embedders, factory, proposals).
- Projection + consumer feature: **28 passed** (sidecar, scenarios, export CLI/API, health, rag index, sumi assistant read-switch, proposal applier, pgvector parity + tenant isolation).
- Publishing feature suite: **127 passed** at last full run — zero publish regression.

## Pre-existing issue (NOT introduced here)

`tests/Unit/InlineEdit/InlineEditServiceTest.php:35` defines `private function status()`
which collides with PHPUnit's now-`final` `TestCase::status()`, fataling a full
`--testsuite=Unit` collection. Independent of this work; out of scope. Run targeted
paths (e.g. `tests/Unit/Projection`) instead.

## Publish regression

With `crawler_policy.projection_access: none` and `cms.sumi.pgvector` OFF (both
defaults) every new path is guarded off and the added columns are nullable and
unwritten — publish output is byte-identical. The pre-existing Publishing feature
tests all still pass unchanged.

## Honest bottom line

The substrate + four consumer slices + the full pgvector read path are built and
green. **Semantic Sumi cannot be activated until a real embedding model is
reachable** (local Ollama bge-m3 once the Home PC is on, or a Voyage key). pgvector
storage/speed is ready; the semantic quality comes entirely from the embedder.
