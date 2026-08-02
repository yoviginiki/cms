# pgvector — handoff to the migration session

**From:** the DBA/infra session that installed the extension (read-only on the app; zero
code, zero Laravel migrations, zero production commands).
**To:** the next session, which will change code with **normal (non-superuser)** DB
privileges.

This document tells you what is already in place, what is deliberately **not** done yet,
and the exact order to do it in so retrieval results don't silently change.

---

## What is done

- **Extension installed.** `vector` **0.6.0** created on the **dev/test** database
  `cms_saas_platform_test` (via `CREATE EXTENSION IF NOT EXISTS vector`, superuser peer).
  It survives `RefreshDatabase` — the test suite's `migrate:fresh` drops tables, not the
  extension.
- **App role can use it without extra grants.** Verified as `cms_saas` (non-superuser):
  `'[1,2,3]'::vector` casts, `<=>` cosine works, 1024-dim vectors construct. No `GRANT`
  was needed or given. This directly refutes the blocker behind D4.
- **Production is NOT done here.** The extension is installed only on dev/test. Production
  (`cms_saas_platform`) install is a manual step for the operator — see
  `pgvector-production-install.md`. Run that before doing the prod side of the migration.
- **Package** `postgresql-16-pgvector 0.6.0-1` is present on the machine; no OS install
  was needed.

### Vector dimension — READ THIS BEFORE ADDING A COLUMN

The `vector(N)` column's `N` must match what is **actually stored**. It is not a free
choice:

- **Intended production model:** Voyage `voyage-3`, output **1024** dims.
  Source: `app/Domain/Projection/Rag/VoyageEmbedder.php:20-21` (`$model='voyage-3'`,
  `$dims=1024`).
- **Offline fallback:** `HashEmbedder`, default **16** dims, model id `hash-16`.
  Source: `app/Domain/Projection/Rag/HashEmbedder.php:14`. Used automatically whenever
  `VOYAGE_API_KEY` is unset (`app/Console/Commands/ProjectionRagIndexCommand.php:39-40`).
- **`VOYAGE_API_KEY` is currently ABSENT** from `.env`. So any rows indexed *in this
  environment* are `hash-16` (16-dim), **not** 1024-dim.

**⚠ Therefore: before adding a `vector(N)` column, read the real dimension out of the
production `rag_chunks` rows — do not assume 1024.** Rows may be mixed if the index was
built under different `model` values. The check:

```sql
-- Run against the REAL data (prod), with tenant context as your tooling requires.
SELECT model,
       jsonb_array_length(embedding) AS dims,
       count(*) AS rows
FROM rag_chunks
GROUP BY model, jsonb_array_length(embedding)
ORDER BY rows DESC;
```

- If every row is one dimension → that is your `N`.
- If dimensions are **mixed** (e.g. some `hash-16`, some `voyage-3`) → you must decide a
  single target model, **re-embed** the odd rows to that model first, *then* add the
  column. A `vector(N)` column cannot hold rows of differing length.
- The `embedding` column is `jsonb` today
  (`database/migrations/2026_08_01_000003_create_rag_chunks_table.php:32`); cosine is
  computed in PHP (`app/Domain/Projection/Rag/RagStore.php:61-106`, full scan per site).

---

## What is NOT done here — the recommended migration path

Described only. **Nothing below was executed.** Each step says why it exists.

### 1. Add a `vector(N)` column *alongside* the existing `jsonb`, not replacing it
Add `embedding_vec vector(N)` while `embedding` (jsonb) stays.
**Why:** additive change = zero risk to the running app. The old path keeps working
untouched while you build and validate the new one. Replacing in place gives you no way
back and no way to compare.

### 2. Backfill existing vectors into the new column
Copy each row's jsonb array into `embedding_vec` (cast text/jsonb → `vector`). Do it in
batches for a large table.
**Why:** the ANN index is only useful once the column is populated. Backfilling before
indexing (and before any switch) means the new column is a faithful copy you can compare
against the old one.

### 3. Build the ANN index — HNSW with `vector_cosine_ops`
```sql
CREATE INDEX CONCURRENTLY idx_rag_chunks_embedding_hnsw
  ON rag_chunks USING hnsw (embedding_vec vector_cosine_ops);
```
**Why:** this is the whole point — it turns the O(N) full scan into sub-linear ANN
lookup. `vector_cosine_ops` because retrieval uses cosine (matches `RagStore::cosine`).
Use `CREATE INDEX CONCURRENTLY` so building it does not lock writes on a live table.
Note: HNSW is approximate — see step 4.

### 4. Parallel period — run BOTH paths and compare results *(the step people skip)*
For a real interval, run each query through **both** the old PHP-cosine path and the new
pgvector path, and compare the returned top-K.
**Why this is not optional:** HNSW is an **approximate** index. Its top-K can differ from
exact cosine, especially with tuning params (`m`, `ef_construction`, `ef_search`). If you
cut over blind, retrieval quality shifts and nobody notices until answers get subtly
worse. The parallel period is how you prove the new path returns equivalent results (or
tune `ef_search` until it does) **before** trusting it. Log divergences; investigate any
query where the top result differs.

### 5. Switch reads to pgvector — only after proven match
Change `RagStore::search` to order by `embedding_vec <=> :query` in SQL instead of loading
all rows and scoring in PHP. Do this only once step 4 shows the results match.
**Why:** the switch is the payoff (latency drops from the numbers below to index speed),
but it is only safe once equivalence is demonstrated, not assumed.

### 6. Remove the old `jsonb` column — separate decision, separate gate
Do **not** bundle this with step 5. Drop `embedding` (jsonb) only after the pgvector path
has run in production long enough to trust, as its own change.
**Why:** keeping the old column costs almost nothing and is your rollback if step 5
regresses. Dropping it is irreversible; give it its own review.

---

## The numbers

### Baseline (measured this session, synthetic — dev/test index is empty; prod is off-limits)

Current mechanism = **PHP full scan**: `RagStore::search` loads every row for a site and
computes cosine in PHP. Two components, both grow O(N):

| rows/site | PHP decode+cosine (1024-dim) | DB fetch of jsonb (1024-dim) | combined |
|---:|---:|---:|---:|
| 100 | ~85 ms | — | — |
| 250 | ~133 ms | — | — |
| 500 | ~335 ms | — | — |
| 1 000 | ~538 ms (max ~598) | ~222 ms | **~760 ms** |
| 10 000 | ~5 100 ms | ~2 300 ms | **~7 400 ms** |

For the offline `hash-16` fallback (16-dim) the PHP side is far cheaper: 1k→17 ms,
10k→129 ms, 50k→553 ms.

### Predicted 200 ms threshold (current PHP path)

- **voyage-3 (1024-dim): ~350 chunks per site.** The DB fetch alone crosses 200 ms near
  ~900 rows; PHP cosine near ~370. This is *per site*, and it is very low — the reason
  pgvector matters.
- **hash-16 (16-dim): ~17 000 chunks per site.**

### What to re-measure after the migration (to prove it was worth it)

1. Same top-K query latency at 1k / 10k / 50k rows via `embedding_vec <=> :query` with the
   HNSW index — expect single-digit-to-low-double-digit ms, roughly flat vs row count.
2. Result equivalence vs the old path (step 4 output) — % of queries with identical top-K,
   and tuning needed to reach it.
3. Index build time and size at the real production row count.
   Put the before (this table) and after side by side; that delta is the justification.

---

## Status of D4

Recorded a note in `docs/projection-decisions.md`: **the infrastructure constraint behind
D4 — that the app DB role is not a superuser and cannot `CREATE EXTENSION vector` — no
longer holds.** The extension is installed and usable by the app role without extra
grants.

**D4 itself is NOT revoked here.** It stays in force until the session that actually
replaces the implementation (steps 1–6 above) flips it. This session only removed the
reason; the decision is retired by the work, not by this note.

---

## Known pre-existing issue found (not caused here, not fixed here)

The full PHPUnit suite does not currently run clean, for reasons unrelated to pgvector —
recording it so the next session isn't surprised:

- `tests/Unit/InlineEdit/InlineEditServiceTest.php:35` fatals under **PHPUnit 12.5.20**:
  `Cannot override final method PHPUnit\Framework\TestCase::status()`. The file is
  committed (572c98a). This aborts the whole suite at class-load time.
- Running the Feature suite around that file: **903 tests, 3 errors, 7 failures** — all in
  unrelated areas (missing `test-builds/*.json` artifacts, RLS tenant context on `sites`
  inserts, HTML/SEO assertions, `@import` in font CSS in `TokenProfileTest`).
- The relevant RAG/Projection unit tests are **green: 53/53** — the extension install is
  inert, as expected. But the suite health issue above is real and predates this session.
