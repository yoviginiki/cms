# Theme Engine Critical Review (T1.1)

**Date:** 2026-07-09 · **Branch:** `feature/theme-engine` · **Status:** review complete, hardening NOT started (T1.2 gate)

Scope: the five T1.1 questions. Method: three parallel code audits (anatomy/packaging, token coverage, switching/Studio) + live-DB probes and in-memory render experiments on the production DB (read-only; the one site mutation ran inside a rolled-back transaction; `block-showcase`'s NULL theme was restored).

**Prerequisite note:** audit Sessions A & B are complete with fixes tested. **Session E (subsystems 17–20: token engine, Theme Studio, switching, cinematic) was never run** — STATUS.md rows are ⬜. This review subsumes it; STATUS rows 17–19 can be filled from this document.

---

## Headline: there are TWO theme systems on one table

| | Legacy | W3C Theme Engine |
|---|---|---|
| Token column | `themes.config` (flat `"color-primary": "#…"`) | `themes.document` (W3C `$type`/`$value`/`{ref}`) |
| Seeder | `app/Domain/Theme/Services/SystemThemeSeeder.php` (reads `storage/app/themes/system/*/theme.json`) | `database/seeders/SystemThemeSeeder.php` (hardcoded PHP) |
| CSS generator | `DesignTokenGenerator` (app/Domain/Theme/Services/DesignTokenGenerator.php) — **the publish path** | `ThemeResolver`→`ThemeCompiler` (app/Services/Theme/) — **the Studio path** |
| Per-site tweaks | `theme_customizations` (site+theme scoped) | `theme_overrides` (tenant/site/page/block scoped, **no theme_id**) |

`DesignTokenGenerator` bridges `document` → CSS vars (emitting both `--semantic-*` and legacy `--color-*` aliases via its `W3C_TO_CSS` map), so published output understands both. But the Theme Engine API (`ThemeEngineController::index`) filters `whereNotNull('document')` — **legacy config-only themes are invisible to the engine's own list**, and the Studio pipeline ignores `config`/`theme_customizations` entirely.

---

## Q1 — Token coverage: can a theme fully restyle every block?

**No. The token schema is complete; consumption is the leak.**

The emitted variable inventory (~186 vars: colors incl. per-heading, fonts, sizes, weights, spacing, radius sm–xl, shadows, motion, nav, footer, buttons) is rich enough to express non-house-style themes — radius and shadow tokens exist even though Stillopress defaults are zero-radius/no-shadow. But:

- **Color:** ~60/86 block Blades are token-clean. **~14 high-visibility blocks hardcode a Tailwind blue/slate palette** and ignore tokens: `pricingcard`, `pricingtable`, `featurecomparison`, `timeline`, `contact-form`, `tabs` (inactive state), `newsletter`, `categorylist`, `latestposts`, `sharebuttons`, `socialembed`, `chart`, `readingprogress`, and the **hero CTA** (uses `#fff`/`#333` instead of `--btn-*`). File:line specifics in the leak table below.
- **Spacing rhythm: 2/86 blocks** use `var(--space-*)`. Section paddings/gaps are literal `2rem`/`1rem` everywhere — themes cannot dial density. Systemic.
- **Shadows: 0/86 blocks** consume `--shadow-*`. `BlockStyle.php:72-78,295-299` box/text-shadow presets are hardcoded rgba — changing shadow tokens has zero effect anywhere.
- **Missing token categories:** form inputs (`--input-*` doesn't exist; contact-form/newsletter hardcode `#d1d5db` borders), code-block theme (`code.blade.php` fixed dark palette), chart series palette, status colors defined but unused (`#22c55e`/`#ef4444` literals).
- **Bonus defect (live-verified):** `DesignTokenGenerator::generate()` line 139 `if (!$theme) return '';` — **a site with no active theme publishes ZERO token CSS** (no defaults!). Every block var() falls back to its inline fallback. `block-showcase` is in this state today.

### Leak table (worst offenders)
| Block | Hardcoded | Severity |
|---|---|---|
| pricingcard.blade.php:28-51 | `#3b82f6` CTA/badge, `#e5e7eb`, `#6b7280`, `#22c55e`, `#f3f4f6` | breaks theming |
| pricingtable.blade.php:24-32 | `#3b82f6` highlight+CTA, greys | breaks theming |
| featurecomparison.blade.php:24-46 | full grey/green palette | breaks theming |
| timeline.blade.php:25-34 | `#3b82f6` nodes, `#d1d5db` line | breaks theming |
| contact-form.blade.php:24-36 | input borders, `#ef4444`/`#16a34a` status | breaks theming |
| hero.blade.php:337-351 | CTA `#fff`/`#333`, bypasses `--btn-*` | medium |
| BlockStyle.php:72-78,295-299 | all shadow presets hardcoded rgba | medium |
| tabs/newsletter/categorylist/latestposts/sharebuttons/socialembed/chart/readingprogress | grey literals for muted/border/track | minor |
| code/flipbook/scroll_page, gallery/podcast/landing wrappers | self-contained chrome palettes | minor/chrome |

---

## Q2 — Theme anatomy: what IS a theme today?

**A bag of tokens + partial metadata. Nothing else.**

- Columns: `name, slug, description, version, schema_version, modes, config, document, manifest_json, is_system, is_active, parent_theme_id, site_id, created_by` (+SoftDeletes).
- **No author column** (exists only in disk theme.json, dropped on seed). **No screenshot/preview column** (legacy `config.screenshot` string only on disk-seeded themes; W3C themes have none).
- **Templates are NOT part of a theme**: `theme_templates` (post/archive/header/footer/404/search + block compositions via morphMany) are **site-scoped with no `theme_id`**. Switching a theme changes tokens only; header/footer/template compositions stay.
- **No schema validation anywhere**: `InvalidThemeDocumentException` exists but is never thrown; `import` validates only `document => required|array`. The de-facto schema lives in three implicit places (seeders, `ReferenceResolver::extractTokens`, `W3C_TO_CSS` map).
- `theme_versions` (snapshots on update/restore) works; `theme_versions` and `theme_customizations` are **empty in production** — never exercised.

### Migration to the target contract (theme.json + template assignments + screenshot + metadata)
1. Add `author`, `preview_image` columns (or fold into `manifest_json`).
2. Add optional theme→template linkage: `theme_templates.theme_id` (nullable; site-scope remains for user customization) OR a `template_seeds` JSON on the theme that instantiates site-scoped `theme_templates` on apply. **Recommend the seed approach** — keeps live templates site-owned (user-editable after apply) while themes carry the defaults.
3. Define a real JSON Schema for `document` + validate on import/update (wire `InvalidThemeDocumentException`).
4. Deprecate the legacy path: migrate the 4 disk themes' flat `config` tokens into `document` (the W3C_TO_CSS map inverts cleanly), then freeze `config` as read-only fallback.

---

## Q3 — Switching (live-verified)

**The render is correct; the lifecycle is broken.**

- ✅ In-memory render test: switching `block-showcase` between Wabi-Sabi (117 flat tokens + document) and Editorial (document-only) produces correct, complete CSS both ways — `--color-primary` `#358733`↔`#3D3D38`, fonts Syncopate↔Fraunces, both `--semantic-*` and legacy aliases emitted (~6.2KB/6.1KB). Structurally different themes switch cleanly at render level.
- ❌ **Nothing is flagged stale.** Live-verified on `ensodo` (11 published pages, rolled-back transaction): setting `active_theme_id` flags **0 pages/posts** `needs_republish`. `ThemeEngineController::assign` (lines 180-233) only invalidates the resolver cache — it never calls `markStale`, unlike every other mutation controller (MenuController:89, PageController:102, SiteController:74). **Published sites keep the old theme's CSS indefinitely.**
- ❌ **Mixed-theme sites guaranteed on partial republish:** token CSS is inlined per page (`layout.blade.php:13-14`), so republishing some pages after a switch yields pages disagreeing on colors/fonts.
- ❌ `fork()` **silently switches the live theme** (ThemeEngineController.php:108) — "fork to experiment" changes production resolution, with the same no-republish gap.
- ⚠️ `theme_overrides` have **no theme_id** — block/site token overrides authored under theme A re-apply under theme B (Studio/resolve path only; published generator never reads them — itself a divergence).
- ⚠️ `ThemeCompiler` writes a shared `theme.{hash}.css` artifact on full publish that **no publishing Blade links** — dead weight.
- Tests: `test_can_assign_theme` asserts HTTP 200 only. No test covers stale-on-switch.

---

## Q4 — Theme Studio fidelity (live-verified)

**Preview ≠ published. Quantified: published pages emit 186 CSS variables; Studio frames emit 70.**

- The 118 missing vars are exactly the legacy `--color-*`/`--font-*`/`--btn-*`/`--nav-*` aliases **that the block Blades actually consume** (46 blocks use `var(--color-*)`), plus defaults. Google Font `@import`s: present in published, absent in Studio.
- Two independent CSS generators: Studio = `ThemeCompiler::renderCss` (semantic-only, `@layer theme`); published = `DesignTokenGenerator` (semantic + aliases + defaults + fonts + body/background rules). Same token values, different variable surface → blocks referencing legacy vars render themed in production and unthemed in Studio.
- Studio previews **six stock demo frames** (`theme-studio/frames/*.blade.php`), not the site's real pages/blocks. It never renders the cinematic (wabisabi4) wrapper — the highest-visual-risk layout is unpreviewable.
- Studio ignores `theme_customizations` (published applies them) and applies `theme_overrides` (published ignores them) — both directions of drift.
- Saves are sound: manual Save → snapshot to `theme_versions` → update → cache invalidation. No autosave (unsaved edits lost on nav).

**Fix direction (T1.2):** one generator. Either the Studio frame pipeline calls `DesignTokenGenerator` (quick), or — better — `ThemeCompiler` becomes the single emitter and `DesignTokenGenerator` reduces to the legacy-alias mapping layer both paths share. Studio should additionally offer a "real page" preview frame via the existing static-preview endpoint.

---

## Q5 — Packaging

**No single-file round-trip today.**

- `export` returns the bare `document` JSON only — no templates, screenshot, author, or metadata wrapper.
- `import` expects `{document: …, name?}` — **the exported file is not directly re-importable** (shape mismatch), and import does no structural validation.
- Target for T1.2: a zip (or single JSON bundle) = `theme.json` (W3C document incl. `$metadata` name/author/version) + `templates/` seeds + `preview.png`; export/import symmetric; import validates against the JSON Schema from Q2.3.

---

## Security finding (from pre-flight, confirmed live)

`themes` RLS policy is `FOR ALL` with **no WITH CHECK**; USING permits `site_id IS NULL AND is_system = true` → RLS allows a tenant session to INSERT a fake system theme visible to every tenant's picker. App code currently blocks the path (fork/import hardcode `is_system=false`; update whitelists fields) **but `is_system` is mass-assignable on the model** — one careless future endpoint away from cross-tenant theme injection. Also: bogus-tenant sessions can read all 5 system themes (by design, but note it). Fix: add `WITH CHECK` denying `is_system`/`site_id IS NULL` writes from tenant sessions + remove `is_system` from `$fillable`.

---

## T1.2 hardening worklist (proposed, in priority order)

1. **Switch lifecycle:** `assign()`/`fork()` mark all site pages+posts stale (reuse StalenessResolver pattern) + surface "N pages need republish" in the response; stop `fork()` auto-switching (or make it explicit opt-in).
2. **Single CSS generator** for Studio + published (kill the 186-vs-70 gap); Studio "real page" preview frame.
3. **Themeless-site defaults:** `generate()` emits defaults when `active_theme_id` is NULL.
4. **Token consumption sweep:** fix the ~14 palette-leaking blocks + wire `BlockStyle` shadow presets to `--shadow-*` + introduce `--input-*` tokens; spacing-rhythm pass over section/card paddings (`--space-*`).
5. **Contract:** author/preview columns, template seeds on themes, JSON Schema validation, symmetric single-file export/import bundle.
6. **RLS WITH CHECK + `is_system` fillable removal** (+ regression test).
7. **`theme_overrides.theme_id`** column (or scope overrides per theme) to stop cross-theme bleed.
8. Tests for all of the above: switch-flags-stale, export→delete→import→identical, Studio/published CSS parity snapshot, viewer-403s (exist), RLS write probe.

Acceptance per master prompt: switch between two structurally different test themes on a seeded site — pixel-sane staged output both ways; export → delete → import → identical.
