# Magazine Editor Gap Analysis

> **Update 2026-05-19**: DTP prototype M0-M9 complete. Production integration plan available at [MAGAZINE-PRODUCTION-INTEGRATION-PLAN.md](MAGAZINE-PRODUCTION-INTEGRATION-PLAN.md).

## 1. Executive Summary

**Status: PROTOTYPE COMPLETE** — The DTP prototype track (M1-M9) has been fully implemented, proving spread view, rulers, snap/guides, align/distribute, text threading, image controls, layers, preflight, templates, master pages, and export readiness. The production magazine editor also received fixes for align/distribute, rotation drag, and style application. Next step: production integration (MP1-MP10).

**Architecture verdict:** The current data model (mag_pages, mag_elements, mag_styles) is **solid and extensible** — it already has fields for master pages, text threading, spreads, baseline grids, and styles. The gap is primarily in **UI implementation**, not data model. No schema changes needed for M0-M4.

---

## 2. Current Implementation Findings

### What Works Well
- **Canvas**: DOM-based with CSS transforms, zoom 0.25-4x, pan, fit-to-page
- **25+ element types**: Text frames (6), image frames (6), shapes (6), media (4), interactive (5), data (4), page structure (3)
- **Selection**: Single/multi-select, drag-to-move, 8-point resize, shift+click
- **Smart guides**: Snap to page center, margins, other elements (4px threshold)
- **Grid snap**: 8px configurable grid
- **Typography**: 23 properties including OpenType, drop caps, orphans/widows
- **Styling**: Fill/stroke/gradients, shadow, blur, blend modes, corner radius
- **Layers panel**: Z-order tree, visibility/lock, groups
- **Page management**: Multi-page, thumbnails, add/delete, A4/A3/Letter/Tabloid presets
- **Margin/column/baseline guides**: Visual overlays
- **Undo/redo**: 50-step JSON snapshot history in Zustand store
- **Keyboard shortcuts**: V/T/I/R/E/L tools, Delete, Ctrl+D, arrows, Ctrl+;
- **AI wizard**: 7-step editorial planning with Claude, provisions to canvas

### What's Broken
1. **Align/Distribute**: Buttons exist, NO implementation (empty handlers)
2. **Rotation drag**: Handle renders but no angle calculation in pointer move
3. **Paragraph/character styles**: Can create styles but apply button does nothing
4. **Text threading**: Fields exist (threadId/threadOrder) but no UI to create/manage threads
5. **Reference point**: UI selector exists but doesn't affect transforms
6. **Marquee selection**: State skeleton present but drag-to-select not wired
7. **Text wrap**: Panel exists with full controls but no wrapping behavior in renderer
8. **Drop caps/OpenType/hyphenation**: UI controls exist but not applied to rendered text

### What's Missing
- Rulers, editable guides, spread view, master page system
- Drag rotation, marquee selection, drag-from-palette
- Text threading UI, text overflow management
- Component/symbol system, artboard groups
- Preflight validation, export pipeline
- Color swatches, gradient visual editor
- Path editing, freeform shapes

---

## 3. Professional DTP Feature Checklist

### 3.1 Document Structure

| Feature | Status | Evidence | Risk | Action |
|---------|--------|----------|------|--------|
| Issue/document | WORKING | magazine_issues + magazines tables | LOW | None |
| Sections | PARTIAL | mag_articles with sort_order | LOW | Add section grouping (M6) |
| Pages | WORKING | mag_pages with page_number | LOW | None |
| Spreads | PARTIAL | spread_with field exists, no spread view UI | MEDIUM | Add spread view (M1) |
| Page order | WORKING | page_number + PageNavigator | LOW | None |
| Page size | WORKING | pageSize JSON (width/height), 4 presets | LOW | None |
| Bleed | WORKING | bleed JSON (4 sides) in mag_pages | LOW | None |
| Margins | WORKING | margins JSON (4 sides) + visual guides | LOW | None |
| Slug/safe area | MISSING | No slug area concept | LOW | Add in M7 (preflight) |
| Master pages | PARTIAL | is_master + master_page_id fields exist, no UI | MEDIUM | Wire UI (M4) |
| Page numbering | PARTIAL | page_number element type exists, no auto-numbering | LOW | Auto-number in M4 |
| Table of contents | MISSING | No TOC generation | LOW | Future (M8+) |

### 3.2 Canvas and Navigation

| Feature | Status | Evidence | Risk | Action |
|---------|--------|----------|------|--------|
| Single page view | WORKING | Default canvas mode | LOW | None |
| Spread view | MISSING | spread_with field unused | MEDIUM | Implement (M1) |
| Zoom | WORKING | 0.25-4x, presets, Ctrl+wheel, fit | LOW | None |
| Pan | WORKING | Middle-click, Alt+drag, scroll | LOW | None |
| Fit to page | WORKING | Calculates from viewport | LOW | None |
| Page thumbnails | WORKING | PageNavigator with mini previews | LOW | None |
| Page navigator | WORKING | Vertical strip, click to navigate | LOW | None |
| Rulers | MISSING | No ruler elements | MEDIUM | Add (M1) |
| Guides (visual) | WORKING | Margin, column, baseline overlays | LOW | None |
| Guides (editable) | MISSING | Cannot drag/create custom guides | MEDIUM | Add (M1) |
| Grids | WORKING | 8px snap grid, baseline grid | LOW | None |
| Snapping | WORKING | Smart guides + grid snap | LOW | None |
| Measurement units | MISSING | Pixels only, no mm/in/pt | LOW | Future |

### 3.3 Layout Objects / Frames

| Feature | Status | Evidence | Risk | Action |
|---------|--------|----------|------|--------|
| Text frame | WORKING | 6 variants with inline editing | LOW | None |
| Image frame | WORKING | 6 variants with asset picker | LOW | None |
| Shape/frame | WORKING | Rectangle, ellipse, line | LOW | None |
| Line | WORKING | SVG line with stroke styles | LOW | None |
| Caption frame | WORKING | caption_frame type | LOW | None |
| Quote frame | WORKING | pullquote_frame type | LOW | None |
| Page number | PARTIAL | Type exists, no auto-numbering | LOW | Auto-number (M4) |
| Article placeholder | WORKING | Via AI wizard provisioning | LOW | None |
| Decorative elements | PARTIAL | decorative_rule, limited | LOW | Expand (M8) |
| Grouped objects | PARTIAL | Group type exists, no visual grouping | MEDIUM | Implement (M2) |
| Locked objects | WORKING | Lock toggle, pointer-events disabled | LOW | None |
| Object duplication | WORKING | Ctrl+D, +10px offset | LOW | None |

### 3.4 Typography

| Feature | Status | Evidence | Risk | Action |
|---------|--------|----------|------|--------|
| Paragraph styles | BROKEN | UI exists, apply does nothing | HIGH | Fix (M0) |
| Character styles | BROKEN | UI exists, apply does nothing | HIGH | Fix (M0) |
| Object styles | MISSING | Not implemented | MEDIUM | Add (M3) |
| Text alignment | WORKING | L/C/R/J in panel | LOW | None |
| Columns in text frame | WORKING | 1-4 columns with gap | LOW | None |
| Baseline grid | PARTIAL | Visual guide exists, text doesn't snap to it | MEDIUM | Wire snap (M3) |
| Hyphenation | DEAD_CONTROL | UI toggle, not applied | LOW | Wire or disable (M2) |
| Leading (line height) | WORKING | 0.5-4 range | LOW | None |
| Kerning/tracking | WORKING | letterSpacing, wordSpacing | LOW | None |
| Font selection | PARTIAL | Text input only, no font picker | MEDIUM | Integrate FontPicker (M3) |
| Font size | WORKING | 6-200px range | LOW | None |
| Text overflow indicator | WORKING | Red + badge on overflow | LOW | None |
| Linked text frames | PARTIAL | threadId/threadOrder fields, no UI | HIGH | Implement (M5) |

### 3.5 Images and Media

| Feature | Status | Evidence | Risk | Action |
|---------|--------|----------|------|--------|
| Image placement | WORKING | AssetField picker | LOW | None |
| Fit/fill | WORKING | 4 modes (fill/fit/stretch/none) | LOW | None |
| Crop/focal point | WORKING | x/y 0-1 + offset/scale | LOW | None |
| Rotation | WORKING | 0-360 in ImagePanel | LOW | None |
| Opacity | WORKING | 0-100% in EffectsPanel | LOW | None |
| Alt/caption | WORKING | Alt text input | LOW | None |
| Missing image warning | PARTIAL | Placeholder shown, no warning badge | LOW | Add badge (M7) |
| Resolution warning | MISSING | No DPI/resolution checking | MEDIUM | Add (M7) |
| Replace image | WORKING | Via AssetField | LOW | None |
| Drag/drop image | MISSING | Click-to-add only | LOW | Add (M2) |

### 3.6 Object Controls

| Feature | Status | Evidence | Risk | Action |
|---------|--------|----------|------|--------|
| X/Y position | WORKING | TransformPanel + drag | LOW | None |
| Width/height | WORKING | TransformPanel + resize handles | LOW | None |
| Rotation | PARTIAL | Panel input works, drag handle broken | MEDIUM | Fix drag (M0) |
| Opacity | WORKING | EffectsPanel slider | LOW | None |
| Z-index/layer | WORKING | LayersPanel + bring/send | LOW | None |
| Align/distribute | BROKEN | UI buttons exist, no implementation | HIGH | Implement (M0) |
| Lock/unlock | WORKING | Toggle in layers + element | LOW | None |
| Copy/paste | WORKING | Store clipboard with offset paste | LOW | None |
| Undo/redo | WORKING | 50-step snapshot history | LOW | None |
| Keyboard nudging | WORKING | Arrow keys (1px, Shift+10px) | LOW | None |

### 3.7 Layers

| Feature | Status | Evidence | Risk | Action |
|---------|--------|----------|------|--------|
| Layer panel | WORKING | MagLayersPanel with tree view | LOW | None |
| Object order | WORKING | z-index sorting, reorder buttons | LOW | None |
| Hide/show | WORKING | Eye toggle per element | LOW | None |
| Lock/unlock | WORKING | Lock toggle per element | LOW | None |
| Move between layers | MISSING | No drag-between-layers | LOW | Add (M6) |
| Master page layer | MISSING | No master page layer behavior | MEDIUM | Add (M6) |

### 3.8 Styles and Design System

| Feature | Status | Evidence | Risk | Action |
|---------|--------|----------|------|--------|
| Typography styles | BROKEN | mag_styles table + UI, no application | HIGH | Fix (M0) |
| Color swatches | MISSING | No swatch panel | MEDIUM | Add (M3) |
| Object styles | MISSING | Type field supports it | MEDIUM | Add (M3) |
| Reusable templates | WORKING | 19 templates in config/magazine_templates.php | LOW | None |
| Theme tokens | PARTIAL | issue_design_system table exists | LOW | Wire (M3) |

### 3.9 Preflight / Validation

| Feature | Status | Evidence | Risk | Action |
|---------|--------|----------|------|--------|
| Missing images | MISSING | No validation | MEDIUM | Add (M7) |
| Low-resolution images | MISSING | No DPI checking | MEDIUM | Add (M7) |
| Text overflow | PARTIAL | Visual indicator, no preflight report | LOW | Add report (M7) |
| Empty frames | MISSING | No validation | LOW | Add (M7) |
| Invalid fonts | MISSING | No font validation | LOW | Add (M7) |
| Outside bleed/safe area | MISSING | No boundary checking | MEDIUM | Add (M7) |
| Export readiness | MISSING | No preflight score | MEDIUM | Add (M7) |

### 3.10 Export / Publishing

| Feature | Status | Evidence | Risk | Action |
|---------|--------|----------|------|--------|
| Preview flipbook | WORKING | Via linked page + viewer | LOW | None |
| Static HTML output | WORKING | Via publishing pipeline | LOW | None |
| PDF export | MISSING | Not implemented | MEDIUM | Add (M9) |
| Web magazine output | WORKING | Via page publishing | LOW | None |

### 3.11 Collaboration / Editorial

| Feature | Status | Evidence | Risk | Action |
|---------|--------|----------|------|--------|
| Issue status | WORKING | draft/handed_off in magazine_issues | LOW | None |
| Article assignment | WORKING | mag_articles + issue_content_items | LOW | None |
| Version history | MISSING | No version tracking for mag content | MEDIUM | Add (M9+) |
| AI editorial planning | WORKING | 7-step wizard with Claude | LOW | None |

---

## 4. Architecture Readiness

**Is it a true page-layout canvas?** Yes — DOM-based with absolute positioning, page dimensions, margins, bleed.

**Is it more like a block/page editor?** No — it has a proper frame/object model with x/y/w/h/rotation/zIndex, not a vertical block flow.

**Does it have page/spread model?** Partially — pages exist with spread_with field, but no spread view UI.

**Does it have object/frame model?** Yes — MagElement with 25+ types, full transform, styling, typography.

**Does it have enough structured data for InDesign-like behavior?** Yes — the data model already supports master pages, text threading, styles, spreads, baseline grids, columns. The gap is UI implementation.

**Does it need a new model before adding UI features?** No — the existing model is sufficient for M0-M6. Only M7+ (preflight, advanced export) may need additions.

---

## 5. Target Data Model

### Existing entities (already in database)

| Entity | Table | Status |
|--------|-------|--------|
| MagazineIssue | magazine_issues | EXISTS |
| MagazineArticle | mag_articles | EXISTS |
| MagazinePage | mag_pages | EXISTS |
| MagazineElement (Frame) | mag_elements | EXISTS |
| MagazineStyle | mag_styles | EXISTS |
| IssueDesignSystem | issue_design_system | EXISTS |
| IssueContentItem | issue_content_items | EXISTS |
| CurationRun | magazine_curation_runs | EXISTS |
| WizardSession | mag_wizard_sessions | EXISTS |

### Target entities (need creation or evolution)

| Entity | Current State | Action |
|--------|--------------|--------|
| MagazineSection | mag_articles has sort_order | Evolve: add section grouping to mag_articles |
| MagazineSpread | spread_with field on mag_pages | Evolve: add spread model or view query (M1) |
| MagazineMasterPage | is_master + master_page_id fields exist | Wire UI in M4 (no schema change needed) |
| MagazineFrame | mag_elements serves this role | Already exists as MagElement |
| MagazineLayer | layer_name field on mag_elements | Evolve: add layers table for named layer groups (M6) |
| MagazineAsset | Uses existing assets system | Add mag-specific asset references table (M7+) |

### Target frame types

| Type | Current Support | Status |
|------|----------------|--------|
| text | text_frame (6 variants) | EXISTS |
| image | image_frame (6 variants) | EXISTS |
| shape | rectangle, ellipse | EXISTS |
| line | line with SVG rendering | EXISTS |
| quote | pullquote_frame | EXISTS |
| pageNumber | page_number type | EXISTS (no auto-numbering) |
| articleReference | Via issue_content_items | PARTIAL — needs frame-level link |
| decorative | decorative_rule type | EXISTS |

### Fields already present for future features
- `spread_with` — spread pairing
- `is_master`, `master_page_id` — master pages
- `thread_id`, `thread_order` — text threading
- `on_master` — master page elements
- `based_on`, `next_style` — style inheritance
- `responsive_overrides` — responsive breakpoints

---

## 6. Target UI Layout

```
┌──────────────────────────────────────────────────────────────┐
│ TOOLBAR                                                      │
│ [Select|Text|Image|Shape|Line] [Zoom] [Guides] [Preflight]  │
├──────────┬──────────────────────────────────┬────────────────┤
│ LEFT     │ CENTER                           │ RIGHT          │
│ Pages    │ ┌──────────────────────────────┐ │ Properties     │
│ Thumbs   │ │ Ruler (top)                  │ │ Transform      │
│ Spreads  │ │ ┌─────────────────────────┐  │ │ Typography     │
│ Masters  │ │R│ Page Canvas             │  │ │ Fill/Stroke    │
│          │ │u│ Margin guides           │  │ │ Effects        │
│          │ │l│ Column guides           │  │ │ Image          │
│          │ │e│ Baseline grid           │  │ │ Text Frame     │
│          │ │r│ Elements (frames)       │  │ │ ────────────── │
│          │ │ │ Smart guides            │  │ │ Layers         │
│          │ │ └─────────────────────────┘  │ │ Styles         │
│          │ │ Pasteboard area              │ │ Assets         │
│          │ └──────────────────────────────┘ │ Preflight      │
├──────────┴──────────────────────────────────┴────────────────┤
│ STATUS: Zoom 100% | Page 3/16 | 2 selected | 0 warnings    │
└──────────────────────────────────────────────────────────────┘
```

---

## 7. Implementation Roadmap

### Phase M0: Audit + Stabilization (THIS DOCUMENT)
- Document current state and gap analysis
- Fix broken controls (align/distribute, rotation drag)
- Wire paragraph/character style application
- No schema changes required

### Phase M0.5: Data Model Evolution Plan
- Define MagazineSpread view/query from existing spread_with field
- Plan MagazineLayer table for named layer groups
- Plan MagazineAsset reference table for preflight
- Document migration strategy (additive only, no breaking changes)
- Schema changes deferred to the phase that needs them (M1 spread, M6 layers, M7 assets)

### Phase M1: Canvas Foundation
- Add rulers (horizontal + vertical with tick marks)
- Implement spread view (two pages side-by-side)
- Add editable guides (drag from ruler)
- Implement marquee selection (drag-to-select)
- Fix rotation handle (calculate angle from mouse)

### Phase M2: Object/Frame Polish
- Implement align/distribute logic
- Add drag-from-palette (not just click-to-add)
- Wire text wrap behavior in renderer
- Apply drop caps, hyphenation, OpenType in rendered text
- Add reference point to resize transforms

### Phase M3: Typography Styles
- Wire paragraph/character style application to elements
- Style inheritance chain (based_on)
- Next style auto-application
- Integrate FontPicker into typography panel
- Baseline grid text snapping

### Phase M4: Master Pages
- Master page creation/editing UI
- Apply master page to document pages
- Master page elements render on child pages (non-editable)
- Auto page numbering from master
- Running headers/footers

### Phase M5: Text Threading
- Visual thread connectors between frames
- Create thread by clicking out-port → in-port
- Text overflow flows to next threaded frame
- Thread management in layers panel
- Unthread/rethread controls

### Phase M6: Layers Enhancement
- Named layers (not just element z-index)
- Drag elements between layers
- Layer-level lock/hide
- Master page layer (locked, below content)
- Paste in place (same layer position)

### Phase M7: Preflight
- Missing image detection
- Text overflow warnings
- Empty frame detection
- Outside bleed/margin warnings
- Font availability check
- Preflight panel with severity icons
- Export readiness score

### Phase M8: Templates/Presets
- Cover template
- Table of contents template
- Editorial spread templates
- Visual essay spread
- Interview layout
- Gallery spread
- Template browser with previews

### Phase M9: Export/Publish
- HTML/flipbook parity verification
- High-resolution image export
- PDF export (via server-side rendering)
- Print-ready PDF/X (future)

---

## 8. Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Broken align/distribute misleads users | HIGH | Fix in M0 (remove or implement) |
| Broken styles misleads users | HIGH | Fix in M0 or M3 |
| DOM-based canvas may hit performance limits at 50+ elements/page | MEDIUM | Monitor; consider virtualization in M6 |
| Legacy format round-trip fragile | MEDIUM | Migrate to V2-only format |
| No preflight = users publish broken magazines | MEDIUM | Add in M7 |
| Text threading data exists but unused = silent data loss risk | LOW | Implement in M5 |

---

## 9. First Recommended Slice

**Magazine Editor M0: Fix Broken Controls**

Fix the 3 most misleading broken features:
1. **Align/Distribute** — implement the 8 alignment functions (already have buttons)
2. **Rotation drag** — calculate angle in pointer move handler
3. **Style application** — wire onApplyStyle to actually apply paragraph/character style properties to selected elements

These are safe, scoped, no schema changes, and immediately improve user trust in the editor.

---

## 10. Manual Acceptance Checklist

### M0 (after fixes):
1. Select 3 elements → Align Left → all left-aligned to leftmost
2. Select 3 elements → Distribute Horizontally → evenly spaced
3. Select element → grab rotation handle → drag → element rotates smoothly
4. Create paragraph style "Body" → select text frame → Apply → font/size/color change
5. All existing features still work (zoom, pan, select, move, resize, guides)
