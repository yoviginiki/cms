# Collections v3 — Feature Guide

Everything added in the Collections v3 upgrade (July 2026). All features are
tenant-scoped (RLS), validated at the service layer, and covered by feature
tests in `tests/Feature/Collections/`.

---

## 1. Field validation rules

Schema fields gained per-field validation, configured in the schema editor's
field settings panel:

| Setting | Types | Behaviour |
|---|---|---|
| `settings.pattern` | text, sku | Regex the value must match. Validated to compile at schema save — a broken regex never reaches record entry. |
| `settings.pattern_message` | text, sku | Custom error message shown instead of the generic one. |
| `settings.min` / `max` | number, price (numeric), date (`Y-m-d` strings) | Range bounds. Dates compare as calendar days. |
| `settings.max_length` | text | Already existed; unchanged (default 500). |

Example field:

```json
{ "key": "code", "label": "Code", "type": "text",
  "settings": { "pattern": "^[A-Z]{3}-\\d{4}$", "pattern_message": "Code looks like ABC-1234." } }
```

## 2. Default values

Any non-asset, non-relation, non-computed field may define `default`. Defaults
are validated and stored in canonical (processed) form at schema save, and
apply **only at record creation, only for keys the caller omitted** — an
explicit empty value stays empty, updates never re-apply defaults.

## 3. Field descriptions

`description` (≤200 chars) on any field renders as help text under the input
in the record editor. (Existed pre-v3; now surfaced in the editor UI.)

## 4. Record revisions & restore

Every `RecordService::save` writes an immutable snapshot (title, slug, status,
data, relations, acting user) to `record_revisions` (RLS FORCED), pruned to
the newest **20 per record**. Snapshot failure never blocks a save.

- `GET  …/records/{r}/revisions` — newest first.
- `POST …/records/{r}/revisions/{rev}/restore` — replays data+relations+status
  through the normal save path and writes a `restored` snapshot.
- Admin: record editor → **History** drawer → Restore.

Revisions die with their record (FK cascade) — history is for undoing edits,
not resurrecting deletions.

## 5. Duplicate record

`POST …/records/{r}/duplicate` copies data + relations as a **draft** with a
"(copy)" title and fresh slug. Admin: copy icon on any records-list row.

## 6. Bulk field edit

The bulk endpoint (`POST …/records/bulk`) gained `action: "set_field"` with
`field` + `value` (max 200 ids). Each row revalidates individually; failures
are reported in `skipped` with the validation message, never a 500.
Relation and computed fields can't be bulk-edited. Admin: select rows →
**Set field…**.

Also in the admin list: **double-click any scalar cell to edit inline**
(Enter saves, Esc cancels).

## 7. Record scheduling

`publish_at` / `unpublish_at` on records (record editor → Scheduling panel).
The per-minute scheduled-content job flips due records (draft→published /
published→draft), flags `needs_republish`, and triggers a partial site
publish — record pages, archives and search indexes rebuild automatically.
`unpublish_at` must be after `publish_at`.

## 8. Per-record SEO

`seo_meta` jsonb on records: `title` (≤200), `description` (≤300),
`og_image` (asset uuid). Published record pages prefer these over the derived
`<title>`, meta description and og:image. Admin: record editor → SEO panel.

## 9. Import upsert (pre-existing, now surfaced) & scheduled URL imports

Upsert mode (`mode: upsert` + `key_field` = a unique schema field) existed in
the import job; v3 adds **scheduled URL imports** on top:

Collection settings (schema editor → Data source card):

| Setting | Meaning |
|---|---|
| `import_url` | https URL of a CSV **in the export format** (headers are field keys; slug/status columns optional and ignored for mapping). |
| `import_schedule` | `hourly` or `daily` (or off). |
| `import_key` | Unique field key → runs as upsert (update matched rows); empty → insert. |
| `import_status` | Status for newly created rows (`draft`/`published`). |

`collections:fetch-imports` runs hourly via the scheduler (gates each
collection on its own cadence via `settings.import_last_run`), enforces
https + a private-IP DNS guard (SSRF), 50MB cap, and dispatches the standard
import job — so URL imports get validation, uniqueness, relations and
staleness exactly like manual imports. Manual run:
`php artisan collections:fetch-imports --collection=<uuid>`.

## 10. Webhooks

Outgoing signed webhooks per site (admin sidebar → **Webhooks**, max 20/site).

- Events: `record.created`, `record.updated`, `record.deleted`, `form.submitted`.
- Delivery: JSON POST with `X-Cms-Event` and `X-Cms-Signature` =
  HMAC-SHA256(raw body, secret). The secret is shown **once** at creation.
- Verify on the receiver: `hash_hmac('sha256', $rawBody, $secret) === $header`.
- Reliability: per-delivery rows, exponential backoff (5/10/20/40 min,
  max 5 attempts) re-driven by `webhooks:retry` every 5 minutes; https +
  private-IP guard; failures never affect the triggering save.
- Payload shape:

```json
{ "event": "record.updated",
  "site": { "id": "…", "slug": "artshop" },
  "occurred_at": "2026-07-21T12:00:00Z",
  "data": { "record": { "id": "…", "collection": "products", "slug": "…",
             "title": "…", "status": "published", "data": { } } } }
```

## 11. Saved-query JSON feeds

Toggle **Publish as JSON feed** on a saved query (settings.feed_enabled) →
every site publish writes `/queries/{slug}.json`:
`{ query, name, generated, count, rows }` — rows in the public-query shape
(`{u,t,d}` for record queries, raw rows for SQL mode), capped at 500.
Static, cacheable, CDN-friendly.

## 12. Computed rollup fields

New display-only field type `computed`: *count or sum over published records
whose relation points at this record* (e.g. "Books per author",
"Total pages"). Config:

```json
{ "key": "book_count", "label": "Books", "type": "computed",
  "computed": { "fn": "count", "collection_id": "<books uuid>", "relation_key": "author" } }
{ "key": "total_pages", "label": "Total pages", "type": "computed",
  "computed": { "fn": "sum", "collection_id": "<books uuid>", "relation_key": "author", "sum_field": "pages" } }
```

Resolved at render/publish (`RecordDisplay`), never stored; only published
source records count. Excluded from forms, import, sorting, queries and
bulk edit. `sum_field` must be number/price.

## 13. Tier promotion

Collections over the static-tier threshold (config
`collections.tier1_threshold`, default 2000 records) surface `tier_warning`;
the schema editor shows an amber banner with a one-click **Switch to
dynamic**.

## 14. Guided type conversion (text → select)

Schema editor → text field → **Convert to select…**: previews the distinct
stored values (+counts), then converts the field with those values as the
options list (1–100 distinct values required). Stored data already matches,
so no records are rewritten.

- `GET  …/collections/{c}/convert-preview?field=key`
- `POST …/collections/{c}/convert {field, to: "select"}`

## 15. Cross-collection search

Every publish writes `/search/index.json` listing each static collection's
per-collection index (`sources[]`). The search island detects `sources`,
loads every index, and tags rows with a synthetic **`_type`** facet — one
page searches the whole site with a Type filter.

Authoring: Search Wizard → **All collections**, or set any search block's
`collectionId` to `'*'`. Dynamic-tier collections aren't included (their
search is API-backed per collection).

## 16. Search analytics

Anonymous per-day search-term counters (`search_terms`, RLS; term text only —
no IP/UA/session). The published search island beacons the settled term
(2s idle, once per term) via `sendBeacon` to
`POST /api/v1/sites/{site}/search-beacon` (throttled 60/min). Opt out per
site with `settings.search_analytics = false` (removes the beacon attribute
at publish AND rejects server-side).

Admin data: `GET /api/v1/sites/{site}/search-terms?days=30` → top 100 terms.

## 17. Gallery carousel + lightbox

`record-image` blocks bound to a gallery field now render **all** images:

- 1 image → plain `<img>` with `srcset` (small_400/medium_800 variants).
- 2+ images → dependency-free carousel (medium_800 main, thumb_200 strip,
  prev/next, dots-free), click → fullscreen lightbox with the original.
- Variant files are published automatically (`assetUrl(..., $variant)` →
  variant-aware rewrite at publish).

## 18. Quick-create related records

In the record editor's relation picker, an unmatched search offers
**+ Create "…"** — a minimal modal creates the target record as a draft
(title only) and links it immediately.

---

## Operational notes

- Migrations: `record_revisions`, `webhooks`, `webhook_deliveries`,
  `search_terms`, `records.publish_at/unpublish_at/seo_meta` — run
  `php artisan migrate --force` at deploy.
- Scheduler additions: `collections:fetch-imports` (hourly),
  `webhooks:retry` (5 min). Both idempotent, both cross-tenant with per-site
  RLS context.
- All new tables follow the house RLS pattern (ENABLE + FORCE + single
  `tenant_isolation` policy on site ownership).
- Test coverage: FieldRulesTest, RecordRevisionTest, RecordSchedulingSeoTest,
  UrlImportTest, WebhookTest, ComputedFieldsAndFeedsTest, TypeConversionTest,
  CrossCollectionSearchTest, SearchAnalyticsAndGalleryTest (+ SPA
  collectionFieldTypes.test.ts).
