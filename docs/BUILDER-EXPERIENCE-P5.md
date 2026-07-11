# Builder Experience Track — P5 Layout Logic (Final Report)

**Date:** 2026-07-11 · **Branch:** master (live-checkout workflow) · **Mode:** additive build + live-verify

P5 gives the builder Divi-5-class **layout control**: proportional row layouts,
section width modes, a structural block tree, and true 12-grid column resizing
with per-breakpoint stacking. Every slice is **additive** — existing rows,
sections, and published output keep their behavior; new capability layers on top
and resolves at publish into flat static HTML/CSS (the PageSpeed invariant holds).

**DB / deploy target:** this checkout runs against **live production**
(`APP_ENV=production`, sys.ensodo.eu). Each slice was committed to master, the
admin bundle rebuilt, the Blade view cache cleared, and the render path
smoke-tested on the real box per [[feedback_deploy_test_rules]].

---

## Verdict at a glance

| Slice | Capability | Commit | State |
|---|---|---|---|
| A | Row layout **visual picker** (8 proportional presets) | `ef1eb02` | ✅ shipped · live-verified |
| B | Section **content-width modes** (contained / wide / full-bleed) | `ef1eb02` | ✅ shipped · live-verified |
| C | **Structure panel** — nested collapsible block tree | `c34b433` | ✅ shipped · built + tested |
| D | **12-grid column resize** + mobile stack order | `18bf25e` | ✅ shipped · live-verified |
| E | Starter section packs | — | ⏸️ **deferred → Theme track** |

**Builder-engine work for P5 is complete.** The one remaining item (starter
packs) is per-theme *content*, not builder-engine work, and shares its blocking
infra with the deferred P3 theme presets — handed to the Theme track (below).

**Regression across the phase:** frontend **350 vitest green**, **38 Publishing
feature tests green**, admin build clean at every commit.

---

## Slice A — Row layout visual picker

**Was:** the row block already had 9 column-split presets (`layout`:
`1/2+1/2`, `1/3+2/3`, …) rendering correctly in Blade + canvas, with content
auto-preserved (they're CSS-grid cells — switching layout is free). The only gap
was the control: a plain `<select>` dropdown.

**Now:** `resources/admin/src/components/blocks/row/RowLayoutPicker.tsx` renders
each of the 8 offered presets as a **proportional bar diagram** (parses
`LAYOUT_GRID`'s `Nfr` tokens into flex weights via the exported `proportions()`),
with active / hover / focus states in house style. Wired into `row/Editor.tsx`,
replacing the dropdown. Purely additive — same `layout` field.

- **Tests:** `RowLayoutPicker.test.ts` (4 vitest — proportion parsing for every
  offered layout).

---

## Slice B — Section content-width modes

**Was:** section had a single free-text `max_width` (default 1200px), always
centered. No named modes.

**Now:** a new `width_mode` field drives the section's inner wrapper:

| Mode | Inner wrapper | Meaning |
|---|---|---|
| `contained` (default) | `max-width: <max_width>; margin: 0 auto` | honors the Max Width field |
| `wide` | `max-width: 1440px; margin: 0 auto` | wide container |
| `full` | `max-width: none; margin: 0` | edge-to-edge full-bleed |

**Backward-compatible:** an unset `width_mode` renders exactly the legacy
contained behavior.

- **Wiring:** `SectionBlockDefinition` validation (`in:contained,wide,full`);
  `resources/views/blocks/section.blade.php` (`$innerWrap`, now `safeDim`-guarded
  on the max-width); React `section/Preview.tsx` mirror; `section/Editor.tsx`
  segmented control (Max Width field shown only for `contained`);
  `section/definition.ts` default `width_mode: 'contained'`.
- **Tests:** `tests/Feature/Publishing/SectionWidthModeTest` (5 feature —
  contained/wide/full/legacy + hostile-max-width sanitization).
- **Live:** rendered all four modes through the compiled Blade on prod —
  `contained→960px`, `wide→1440px`, `full→none/0`, legacy→`1200px`.

---

## Slice C — Structure panel

**Was:** `LayersPanel.tsx` exists but is **flat and z-index-oriented** (built for
the canvas editor) — not a hierarchy view.

**Now:** `resources/admin/src/components/editor/StructurePanel.tsx` — a nested,
collapsible tree of the block hierarchy, mounted as a new **"Tree" tab** in the
PageEditor right sidebar (`PageEditorSidebar`). It **complements, not replaces**
the flat LayersPanel.

- **Collapse/expand** per node with children.
- **Click-to-select** → canvas highlight. The existing auto-jump-to-Block-tab
  effect is guarded (`t === 'tree' ? t : 'block'`) so tree navigation stays put.
- **Inline rename** (double-click) → `data.__label`. This is a `__`-meta key;
  `SanitizationService::sanitizeBlock` iterates **all** data keys and preserves
  them (strings HTML-stripped), so the label round-trips like
  `__stylePreset` / `__responsive`.
- **Visibility toggle** → `style.layout.display`, which `BlockStyle.php:362`
  emits into published CSS — hiding a block **persists to output**, not just the
  editor.
- **Native HTML5 drag-reorder** → `editorStore.moveBlock(before|after|inside)`.
  The store already enforces section→row→column→module containment and no-ops
  invalid drops (including dropping a node into its own descendant).

All mutations route through `editorStore`, so undo/redo + dirty-tracking work for
free. Pure logic (`structureLabel`, `dropZone`) lives in
`resources/admin/src/lib/structureHelpers.ts`.

- **Tests:** `structureHelpers.test.ts` (9 vitest — label derivation + drop-zone
  math).
- **Scope note:** mounted in **PageEditor** only. PostEditor/TemplateEditor also
  render `BuilderCanvas` with their own sidebars — adding the Tree tab there is a
  cheap follow-up.

---

## Slice D — 12-grid column resize + per-breakpoint stack order

The flagship: row column widths, decoupled from the fixed fraction presets, on a
12-unit grid, plus custom stacking order on mobile. Both are additive row data.

**Data model:**
- `col_spans` — `int[]` summing to 12, one per column. **Overrides** the `layout`
  preset when present; absent → preset stands.
- `stack_order` — a permutation of 0-based column indices for how columns stack
  below 768px.
- `RowBlockDefinition` validates both (`col_spans.*` 1–12, `stack_order.*` 0–5).

**Editor** (`resources/admin/src/components/blocks/row/ColumnControls.tsx`):
- **`ColumnWidthBar`** — drag the dividers on a 12-unit track (pointer events;
  snaps to whole units; neighbours never drop below 1; total always 12 via
  `resizeSpans`).
- **`StackOrderControl`** — up/down reorder the mobile stack; Reset returns to
  natural order.
- `RowEditor` derives count/spans (`presetToSpans` / `normalizeSpans`).
  **Picking a preset in the visual picker resets `col_spans` + `stack_order`** —
  coarse presets vs. fine 12-grid tuning is a clean split.

**Render parity (the correctness-critical part):**
- `resources/views/blocks/row.blade.php` builds `grid-template-columns` from valid
  `col_spans` (else the preset map), and emits scoped
  `.<row> > div > *:nth-child(n){order:N}` rules inside the existing ≤767px media
  query for a valid `stack_order`. **Both validated server-side** — an
  out-of-range span array or a non-permutation is ignored and the preset /
  natural order stands.
- `SortableBlock.tsx` `childrenStyle` + `row/Preview.tsx` mirror `col_spans`, so
  the canvas shows resizing **live** (populated row columns render via
  `childrenStyle`, not `RowPreview`).

Pure geometry in `resources/admin/src/lib/columnLayout.ts`
(`equalSpans` / `presetToSpans` / `normalizeSpans` / `resizeSpans` /
`spansToGridTemplate` / `orderFor` / `isCustomStackOrder`).

- **Tests:** `columnLayout.test.ts` (14 vitest) + `RowColumnLayoutTest`
  (6 feature — render override, preset fallback, invalid-span rejection, mobile
  order rules, malformed-order rejection).
- **Live:** rendered on prod Blade — `[3,4,5]→3fr 4fr 5fr`; invalid `[99,4,5]`→
  preset fallback `1fr 1fr 1fr`; `stack_order [2,0,1]`→ mobile `order:1/2/0`.

---

## Deferred — Slice E: Starter section packs → Theme track

15–20 pre-built Library sections per first-party theme (the blank-page killer).
**Handed to the Theme track**, because the packs are per-theme *content* rather
than builder-engine work, and their one blocking dependency is shared:

> A **privileged system-library seeder** that inserts `site_id=NULL,
> is_system=true` rows. RLS `WITH CHECK` blocks NULL-site writes from the app, so
> the seeder must run outside tenant scope (artisan seeder / direct DB). This is
> the **same** infra the still-deferred **P3 theme system-presets** need — build
> it once, use it for both.

Section trees can be authored in the builder and exported via the existing
Library single-item export JSON (`LibraryPage`) as pack fixtures. See
[[project_theme_track]] backlog.

---

## Deliberate non-changes

Per the additive prime directive, P5 intentionally did **not**:
- add canvas-**inline** column drag handles (Divi-style, directly on the canvas).
  Resize lives in the settings panel, matching the Slice-A pattern. A future
  enhancement, not a gap.
- reorder the editor's **mobile preview** by `stack_order` — the canvas shows
  stacking but the custom order is a publish-only refinement (avoids canvas
  layout surgery).
- touch the **canvas editor's** section path (`cv-section` / `cv-bleed`) — that
  is a separate render pipeline; `CanvasRenderTest` (11) stayed green throughout.
- rewrite `LayersPanel` — the Structure panel is a sibling, not a replacement.

---

## What Divi still does that we don't (P5 scope)

- **Canvas-inline** column drag handles (we resize from the settings panel).
- **Arbitrary per-breakpoint layouts** (different column *widths* per device) —
  we do width (12-grid, all breakpoints) + stack *order* on mobile; per-device
  width overrides remain deliberately out of scope (see DEFERRED list).
- **Row/section spacing drag handles** on the canvas (a P4-remaining ergonomic).
- **Starter packs shipped with themes** — deferred to the Theme track (above).

---

## File index (new this phase)

```
resources/admin/src/components/blocks/row/RowLayoutPicker.tsx        (A)
resources/admin/src/components/blocks/row/ColumnControls.tsx         (D)
resources/admin/src/components/editor/StructurePanel.tsx             (C)
resources/admin/src/lib/structureHelpers.ts                         (C)
resources/admin/src/lib/columnLayout.ts                             (D)
tests: RowLayoutPicker.test.ts, structureHelpers.test.ts,
       columnLayout.test.ts (vitest);
       SectionWidthModeTest, RowColumnLayoutTest (feature)
```

Related: [[project_builder_experience_track]], [[project_theme_track]],
[[feedback_deploy_test_rules]], [[feedback_cms_architecture]].
