# Builder Experience Track — P0 Pre-flight Recon

**Date:** 2026-07-11 · **Branch:** `feature/builder-experience` · **Mode:** read-only

Recon before P1. Rates the four foundations the Divi-5-class track builds on
(Library, Global Sections, Style Presets, Editor Ergonomics), so RED is repaired
(repair, not rewrite) before we add capability. **Prime directive stays additive.**

**DB target:** this checkout runs against the **live production** DB
`cms_saas_platform` (`APP_ENV=production`, sys.ensodo.eu). All recon was
read-only; any future acceptance testing follows [[feedback_deploy_test_rules]].

---

## Verdict at a glance

| # | Foundation | Rating | One-line |
|---|---|---|---|
| 1 | Block `style` JSON + Blade→CSS render | 🟢 GREEN | Flat 4-section style JSON → inline CSS at publish; solid. A few dead/duplicated code paths to avoid. |
| 2 | Block-level token references | 🟡 YELLOW | Publish side already passes `var(--token)`; preview mirror + `$token.path` compile missing. |
| 3 | `slider_ref` + staleness → auto-republish | 🟢 GREEN | Full references domain, transitive cascade, republish job. Global Sections maps ~1:1. |
| 4 | `block_templates` as Library predecessor | 🟡 YELLOW | Working predecessor with the hard parts done; **EXTEND**, don't replace. Latent defects to repair in P1. |

**No RED. P1 (The Library) can start.** The two YELLOWs are additive gaps, and the
latent defects live inside the exact tables P1/P3 touch — repair them there as
"defects the work directly trips over," per the additive doctrine.

---

## 1. Block `style` JSON shape + render path — 🟢 GREEN

- **Column:** `blocks.style` (jsonb, nullable) — added by `2026_04_17_000002_add_editor_mode_and_block_style.php`. Model `app/Models/Block.php` casts `data`/`style` → array, uses `HasUuids`, `id` fillable (id preserved across saves).
- **Shape:** flat, camelCase, **4 named sections** — `typography` / `spacing` / `visual` / `layout` (canonical type: `resources/admin/src/types/blocks.ts:79` `BlockStyleProps`). NOT nested by breakpoint. Siblings `animation` / `advanced` / `responsive` live in `data.__animation|__advanced|__responsive` (per-breakpoint overrides are keyed by device, each a partial of the 4-section shape).
- **Persistence:** `app/Domain/Blocks/Services/BlockService.php:95-122` writes `style` to the column **and** duplicates into `data.__style`; `buildTree()` reconstitutes on read.
- **Render:** the real engine is **`app/Support/Blocks/BlockStyle.php::buildStyle()`** → sanitized inline `style="…"` string on the block wrapper `<div>`; `hideOn`/responsive emitted as adjacent scoped `<style>` `@media` blocks. Every Blade partial (`resources/views/blocks/*.blade.php`) uses the identical `@use(BlockStyle)` header. Fed by `app/Domain/Publishing/Services/BuildPageService.php:321-410` (`renderBlock`), after `SanitizationService::sanitizeBlock()`.
- **Publish vs runtime:** resolved at **build/publish time** → flat static HTML + inline CSS + small scoped `<style>` blocks. No runtime style computation shipped. ✅ Matches the "published output = flat static HTML/CSS" invariant (the PageSpeed story).

**Traps to avoid (document, don't edit blindly):**
- `app/Domain/Publishing/Services/BlockStyleResolver.php` is **orphaned/dead** (self-referenced only) and weaker than `BlockStyle.php` (no `shadowCustom`, per-corner radius, `bg_*`, animations, `var()`). Anyone "editing the block style resolver" must edit `BlockStyle.php`.
- `resources/admin/src/lib/blockStyles.ts` claims to mirror `BlockStyleResolver.php` but the real output is `BlockStyle.php` — silent preview/publish drift (see #2).
- `$block->responsive` accessor read in `BuildPageService.php:390` has **no column** — always null, always takes fallback. Harmless, misleading.
- Style is a **double source of truth** (`style` column + `data.__style`); update paths must keep both in sync.
- `advanced.customCss` is persisted but has **no render path** (only `customClass`/`htmlId`/`ariaLabel` are emitted).

## 2. Block-level token references — 🟡 YELLOW

- **Token engine:** `app/Domain/Theme/Services/DesignTokenGenerator.php` emits a flat `:root{}` of ~120 CSS vars, **kebab-case, unprefixed** (`--color-accent`, `--space-4`, `--border-radius-md`, `--font-heading`, `--shadow-sm`, `--chart-1..6`, `--btn-*`, …) plus `--semantic-{path}` aliases from the W3C `document`. Naming quirks: it's `--space-4` (not `-md`) and `--border-radius-md` (not `--radius-md`).
- **Publish side already supports tokens:** `app/Support/Blocks/BlockStyle.php:11` `CSS_VAR_PATTERN` whitelists `var(--name, fallback)`; `safeDim`/`safeColor`/`safeCssVal` pass it through. So a block style storing `{visual:{backgroundColor:"var(--color-accent)"}}` or `{spacing:{paddingTop:"var(--space-6)"}}` **already renders correctly today.** ~10 blocks already emit `var(--…)` in their Blade output.
- **Gaps (additive):**
  - **Preview mirror** `resources/admin/src/lib/blockStyles.ts:19-33` `safeDim`/`safeColor` have **no `var()` branch** → the React inspector/preview silently drops token values. Fix = add the same `CSS_VAR_PATTERN` allowance (small, mirrors PHP). **P3 trips over this.**
  - **No `$token.path` syntax** — nothing compiles `$color.accent` → `var(--color-accent)`. If presets store token *paths* (nicer than raw `var()`), add a compile step (reuse the `W3C_TO_CSS` map inverted) in/near `buildStyle`. Otherwise store raw `var(--x)`.
  - **No token-picker UI** in the inspector and no allowlist tying pickable tokens to the emitted set.

## 3. `slider_ref` + staleness → auto-republish — 🟢 GREEN

Fully live, tested, and clearly designed to be extended. This is the foundation
for **Global Sections** and it maps ~1:1.

- **Reference-not-copy:** `app/Domain/Blocks/Definitions/SliderRefBlockDefinition.php` (`slider_ref` stores only `sliderId`); build-time inlining `BuildPageService.php:864` `enrichSliderRef()` with a recursion guard.
- **Graph:** `entity_references` table (`2026_07_03_000001`), model `app/Models/EntityReference.php` — polymorphic source/target, `kind ∈ embeds|links|uses_asset|site_scope|lists`, forward + inverse indexes, RLS.
- **Extraction:** `ReferenceExtractorRegistry` maps every block type to an extractor (coverage enforced by `tests/Unit/References/ExtractorCoverageTest.php`); `slider_ref` → `FieldMapExtractor(['sliderId'=>['slider','embeds']])`. Recomputed on **every save** in the same txn (`BlockService.php:56`). Handles **nested blocks** (flat table, no parent filter).
- **Staleness:** `app/Domain/References/Services/StalenessResolver.php` — transitive BFS over inverse edges (`MAX_DEPTH=5`), flags `pages.needs_republish` / `posts.needs_republish`. Intermediate node types (slider, etc.) are traversed through → `asset→slider→page` cascades.
- **Auto-republish:** `StaleAutoRepublisher::maybeQueue()` → `RepublishStaleJob` (partial build + auto-promote via `DeployService::deployPartial()` + race-safe clear). **Default OFF**, local-deploy only; otherwise pages surface in the manual stale-content UI.
- **Where-used:** `ReferenceUsageService` + `GET sites/{site}/references/usage`.

**To extend for Global Sections (all additive, ~mirror slider):** a `global_section` entity exposing `site_id`; a `global_ref` block; one registry line; an `enrichGlobalRef` inliner; a service that calls `markStale($site,'global_section',$id)`. Then add `global_section` to the `ReferenceController` `target_type` whitelist + a `usage()` title arm (not enum-enforced — remember these). Watch the depth-5 ceiling for global-in-global nesting.

## 4. `block_templates` as Library predecessor — 🟡 YELLOW → **EXTEND, don't replace**

- **Table** `0001_01_01_000008_create_block_templates_table.php`: `id`, `site_id` (nullable), `name`, `category`, `description?`, `blocks_data` (jsonb tree), `preview_image?` (**never written/read**), `is_system`, timestamps. RLS site-scoped (`0001_01_01_000015`). Save flow `BlockToolbar.tsx`; insert flow `PresetBrowser.tsx` → `editorStore.insertSectionTemplate` → **`deepCloneWithNewIds`** (fresh IDs top-to-bottom — the trickiest correctness bit, already right). Copy-not-transclude is a pinned invariant (`tests/Feature/References/PresetsCopyTest.php`).
- **Latent defects to repair when P1 touches this:**
  - `is_system` **is mass-assignable** in `BlockTemplate` (Theme/Layout deliberately guard it) — security asymmetry.
  - Model lacks `HasUuids` + `$incrementing=false` → int-key cast wart (tests work around it).
  - **Dead global/system path:** RLS filters `site_id IN (...)`, so `site_id = NULL` (system template) is `NULL IN (...)` → invisible; and no seeder creates system templates. Decide the global-item model deliberately (`site_id IS NULL OR …` in the policy, or model globals differently).
- **Missing vs. `library_items` spec (additive):** `kind` (section|row|block-composition), `tags`, server-rendered preview thumbnail (column exists, pipeline doesn't), multi-block save (today one subtree), `update`/rename + `index`/`show` authz, import side (export exists in `BackupExportService`, no import), a dedicated manager page.
- **Recommendation:** extend `block_templates` (optionally `Schema::rename` → `library_items` in the same migration) — a parallel table would duplicate RLS, save/insert, fresh-ID copy, and the copy-not-transclude test for no benefit and strand existing rows.

---

## Recommended P1 entry

Start **P1 The Library** by extending `block_templates`:
1. `add_library_columns` migration: `kind`, `tags` (jsonb), nullable `slug`; optionally rename → `library_items`.
2. Model hygiene (defects it trips over): add `HasUuids` + `$incrementing=false`, guard `is_system` from mass-assignment, and make the deliberate global/system RLS decision.
3. Preview-thumbnail service that finally populates `preview_image` (server-rendered static preview → screenshot, cached).
4. Multi-block/selection save; `update`+rename endpoints; `index`/`show` authorization; validated+sanitized single-item import to match export.
5. Library manager page (browse/rename/recategorize/preview/delete + import/export).

Keep the existing save/insert/deep-copy flows — reuse, don't rebuild.

**STOP gate:** per the track's phase discipline, P1 begins only on explicit go-ahead.
