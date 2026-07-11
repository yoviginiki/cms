# System Library Content — Starter Section Packs & Style Presets (Final Report)

**Date:** 2026-07-11 · **Branch:** master (live-checkout workflow) · **Mode:** additive build + live-seed + verify

Two pieces of shared, first-party **system content** shipped together: ready-made
**starter sections** you drop into a blank page, and on-brand **style presets** you
apply to blocks. Both were the last deferred items of the Builder Experience track
(P5 packs, P3 presets), handed to and completed by the Theme track.

Both are **SYSTEM records** — `site_id = NULL`, `is_system = true` — readable by
every tenant, editable by none. Both are **theme-agnostic**: every value is a
design token, so one catalog re-colours across all five first-party themes
(enso · journal · ledger · atelier · hearth) and resolves at publish to flat
static CSS (the PageSpeed invariant holds).

**Deploy target:** live production (`APP_ENV=production`, sys.ensodo.eu). Both
seeders were run on the real box and the results verified, per
[[feedback_deploy_test_rules]].

---

## At a glance

| Deliverable | Records | Commit | State |
|---|---|---|---|
| Reusable privileged-seed helper | `SystemRecordSeeder` | `234b5c3` | ✅ shipped |
| Starter section packs | 8 system `block_templates` | `234b5c3` | ✅ live-seeded |
| System style presets | 11 system `style_presets` | `6e25815` | ✅ live-seeded |

**Regression:** Library suite 12 green · StylePresets + token suite 686 green ·
9 new feature tests. Both seeders idempotent and registered in `DatabaseSeeder`.

---

## The shared problem: writing SYSTEM records past RLS

System rows (`site_id`/`tenant_id` NULL) are exactly what the tenant-isolation
RLS `WITH CHECK` clause forbids from the app connection — that is what makes
`is_system` unforgeable. Seeding them therefore needs a privileged path.

`app/Support/Seeding/SystemRecordSeeder::withRlsDisabled($table, $fn)` is that
path, built **once** and reused by both deliverables (and available to any future
system seeder):

```php
SystemRecordSeeder::withRlsDisabled('style_presets', function () {
    // privileged upserts with site_id = NULL, is_system = true
});
```

- Disables RLS on the table, runs the callback, **re-enables in a `finally`** so a
  throwing callback never leaves a table with security switched off (a hardening
  over the older inline `SystemLayoutsSeeder` pattern).
- Table name is regex-guarded before it is interpolated into DDL.
- **`FORCE ROW LEVEL SECURITY` is preserved.** `style_presets` is FORCE-RLS (the
  table owner is subject to RLS too). `DISABLE`/`ENABLE ROW LEVEL SECURITY` only
  toggles `relrowsecurity`; `relforcerowsecurity` is untouched — verified live on
  prod after seeding (`FORCE preserved: YES`).

---

## Starter section packs (8 sections)

Ready-made Library sections so a blank page is never a blank slate — drop one in,
change the words, publish.

| Category | Sections |
|---|---|
| Hero | Hero — Centered · Hero — Split |
| Features | Features — Three columns *(native editable blocks)* |
| Call to action | Call to action *(alternate background)* |
| Content | Content + Image |
| Social proof | Stats — Four metrics · Testimonial · Logo strip |

**Design decision — theme-agnostic, not per-theme.** The original plan called for
"15–20 sections *per theme*." But the platform is token-based, so a single catalog
built from `var(--color-*)`/`var(--font-*)` re-colours under every theme
automatically — strictly better than five hand-maintained copies. Rich visuals use
`html-embed` (rendered raw at publish via `BuildPageService:334`, exactly how the
flagship marketing site is built); one section (Features) is native blocks to
demonstrate the fully-editable pattern. `MarketingSiteSeeder` was the proven
template harvested for token patterns and the `section/row/column/block` helpers.

- **Storage:** each section is one `block_templates` row, `kind = 'section'`,
  tagged `starter`, `blocks_data = [sectionNode]`. Written directly by the seeder,
  so raw html-embed survives (it does not pass through `LibraryItemSanitizer`,
  which is import-only).
- **Insert flow:** a tenant inserts from the Library → the frontend deep-copies
  with fresh IDs → save → publish (html-embed rendered raw). Unchanged path.
- **Seeder:** `database/seeders/StarterSectionSeeder.php`, idempotent upsert by
  slug.

---

## System style presets (11 presets)

Shared, on-brand style bundles a block links to (element) or stacks (option-group).

| Block type | Element presets | Group presets (`*`, stackable) |
|---|---|---|
| button | Primary · Outline | Uppercase label *(typography)* |
| heading | Display · Eyebrow | Roomy spacing *(spacing)* |
| paragraph | Body · Lead | Hairline border *(border)* |
| section | Contained · Alternate surface | |

- **Token-native.** Every value is written in `$path` form (`$color.primary`,
  `$font.heading`, `$color.text-muted`), which `StyleTokens` compiles to
  `var(--…)` and `BlockStyle::safeColor/safeDim` validate at publish. House style:
  zero radius, no shadows, heading font, vermilion accent.
- **`is_default = FALSE` on every preset — deliberate.** These are a library to
  *apply*, not a silent global restyle of every tenant's new blocks. Auto-linking
  a system default across all tenants regardless of their chosen theme would be
  intrusive; per-theme defaults can be enabled later, coordinated with theme
  selection.
- **Storage:** `style_presets`, `kind` element|group, `group` scoping option-group
  presets. Element link is `data.__stylePreset`; group links are
  `data.__presetGroups` (NOT the `preset_id` column — that is block-template
  provenance).
- **Seeder:** `database/seeders/SystemStylePresetSeeder.php`, idempotent upsert by
  slug, via `withRlsDisabled('style_presets', …)`.

---

## Verification

**Starter packs — `StarterSectionSeederTest` (5):**
- seeds ≥8 system items (site_id NULL, is_system, kind section, tagged starter)
- idempotent (re-seed → same count)
- **every tree validates** through `LibraryItemSanitizer` (known block types,
  within node/depth bounds)
- a tenant reads system items via the API but owns none
- a seeded section **publishes with tokens intact** (`var(--color-heading)` +
  literal copy reach the built HTML)

**Style presets — `SystemStylePresetSeederTest` (4):**
- seeds ≥11 system presets, all `is_default = false`
- idempotent
- a tenant **lists** system presets but **cannot edit** them (PATCH → 403)
- a block linking a preset via `data.__stylePreset` **resolves its `$tokens` to
  `var(--color-primary)` at publish** — proving the resolver + BlockStyle compile
  path end-to-end (plus the preset's `text-transform:uppercase` applied)

**Live on prod (both):** seeders run → 8 sections + 11 presets confirmed present
and correctly scoped; RLS re-enabled on both tables; FORCE-RLS preserved on
`style_presets`.

---

## Deliberate non-changes

- No per-theme content duplication — one token-based catalog serves all themes.
- No system `is_default` presets — no silent global restyle without opt-in.
- `LibraryItemSanitizer`, the insert deep-copy, and the publish pipeline were
  reused unchanged — the seeders only add rows.

---

## Follow-ups (optional, recorded in memory)

- More starter sections + more native-block (vs html-embed) variants; the original
  ask floated 15–20 — extend by adding catalog entries.
- Per-section preview thumbnails (ties into the deferred P1 Library thumbnail
  infra).
- Per-theme preset defaults, enabled at theme-selection time (would set
  `is_default` per site rather than globally).

---

## File index (new)

```
app/Support/Seeding/SystemRecordSeeder.php            (shared privileged-seed helper)
database/seeders/StarterSectionSeeder.php             (8 starter sections)
database/seeders/SystemStylePresetSeeder.php          (11 style presets)
database/seeders/DatabaseSeeder.php                   (registers both)
tests/Feature/Library/StarterSectionSeederTest.php    (5)
tests/Feature/StylePresets/SystemStylePresetSeederTest.php (4)
```

Related: [[project_theme_track]], [[project_builder_experience_track]],
[[feedback_deploy_test_rules]], [[feedback_cms_architecture]].
