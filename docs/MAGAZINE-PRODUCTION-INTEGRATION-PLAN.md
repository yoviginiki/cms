# Magazine Production Integration Plan

## 1. Summary

The DTP prototype (M1-M9) proved that the frontend can deliver InDesign-like layout editing. The production magazine system already has two parallel data models: legacy (`magazines`/`magazine_pages`/`magazine_elements`) and new (`mag_pages`/`mag_elements`/`mag_styles`). The safest path is **Option C: Hybrid** — keep the existing editor, introduce the DTP designer as a beta alongside it, and migrate once feature parity is confirmed.

---

## 2. Current Production Magazine Editor

- **3 legacy tables**: `magazines`, `magazine_pages`, `magazine_elements`
- **3 DDD tables**: `mag_pages`, `mag_elements`, `mag_styles` (already used by MagEditor)
- **29 production components** (canvas, toolbar, renderer, 9 property panels, layers, styles, etc.)
- **710-line Zustand store** with undo/redo, clipboard, view modes
- **AI wizard** (7-step editorial planning → provision to canvas)
- **Flipbook viewer** (Blade template with page-turn animation)
- **Legacy format round-trip** via `_v2` markers in content JSON

## 3. DTP Prototype Findings

- **11 prototype components** (canvas, frames, layers, preflight, templates, export)
- **Mocked data only** — no persistence, no API calls
- Features proven: spread view, snap/guides/rulers, align/distribute, preflight, templates, master pages, text threading, image controls, layers, export readiness
- Architecture: simpler component model, single-file state, type-safe frame model

---

## 4. Gap Comparison

| Area | Production Editor | DTP Prototype | Recommendation |
|------|-------------------|---------------|----------------|
| Issue model | magazine_issues (full) | None (mocked) | KEEP_EXISTING |
| Page model | mag_pages (full) | DtpPage (mocked) | KEEP_EXISTING |
| Spread model | spread_with field | DtpSpread | HYBRID — add spread grouping |
| Frame model | mag_elements (37 cols) | DtpFrame (15 fields) | KEEP_EXISTING — adopt prototype UX |
| Canvas rendering | DOM-based, single page | DOM-based, multi-page | ADOPT_PROTOTYPE (multi-page) |
| Save/load | API endpoints exist | None (mocked) | KEEP_EXISTING |
| Asset handling | AssetField + upload | Mock asset picker | KEEP_EXISTING |
| Text handling | contentEditable + DOMPurify | contentEditable + DOMPurify | KEEP_EXISTING |
| Image handling | AssetField + fit modes | Mock images + fit modes | KEEP_EXISTING |
| Layers | MagLayersPanel (production) | LayersPanel (prototype) | HYBRID |
| Templates | 19 backend templates | 7 prototype templates | HYBRID — merge into backend |
| Master pages | Fields exist, no UI | Full prototype UI | ADOPT_PROTOTYPE UI |
| Preflight | None | preflight.ts | ADOPT_PROTOTYPE |
| Preview | Flipbook viewer | View mode toggle | HYBRID |
| Export/publish | Full pipeline | Fake summary | KEEP_EXISTING |
| Permissions | Sanctum + RLS | None | KEEP_EXISTING |
| Performance | Real data | Mocked (fast) | REWRITE for virtualization |
| Text threading | TextThreading.ts (production) | Same engine | KEEP_EXISTING |
| View modes | single/spread/grid | edit/preview/export | HYBRID |

---

## 5. Recommended Production Data Model

### Existing tables to KEEP (no new migrations needed for MP1-MP4):

| Entity | Table | Status |
|--------|-------|--------|
| MagazineIssue | magazine_issues | EXISTS |
| MagazinePage | mag_pages | EXISTS (page_size, margins, bleed, columns, baseline_grid, master_page_id, spread_with) |
| MagazineFrame | mag_elements | EXISTS (37 columns: transform, style, typography, text_wrap, threading, layers) |
| MagazineStyle | mag_styles | EXISTS (paragraph/character/object styles, inheritance) |
| MagazineArticle | mag_articles | EXISTS |

### Tables to ADD in future phases (MP5+):

| Entity | Purpose | When |
|--------|---------|------|
| MagazineTemplate | Persisted page/spread templates | MP8 |
| MagazineMasterPage | Persisted master page definitions | MP8 |
| MagazinePreflightRun | Saved preflight results per issue | MP7 |
| MagazineAssetRef | Frame-to-asset linkage for preflight | MP5 |

### MagazineFrame (mag_elements) — already has:
```
id, page_id, parent_id, type, name, data (JSONB),
x, y, width, height, rotation, scale_x, scale_y,
z_index, locked, visible, layer_name,
style (JSONB), typography (JSONB), text_wrap (JSONB),
thread_id, thread_order, page_number, on_master,
responsive_overrides (JSONB), created_by
```

**No schema changes needed for MP1-MP4.** The existing `mag_elements` table already supports everything the prototype uses.

---

## 6. Save/Load Architecture

### Current (production):
- `MagEditorController::show()` → returns pages + elements
- `MagEditorController::sync()` → atomic replace (DELETE + INSERT)
- Auto-save via `useAutoSave` hook (3s debounce)

### Recommended (keep existing, enhance):
1. **Keep atomic sync** for simplicity
2. **Add draft versioning** — snapshot before sync (like theme versions)
3. **Add optimistic updates** — UI updates immediately, API confirms
4. **Add conflict detection** — content_hash comparison on save
5. **Keep autosave** — reduce debounce to 5s for magazine (more data)

### No changes needed for MP1-MP4. Existing save/load works.

---

## 7. Publish/Export Architecture

### Phase 1 — HTML/Web Magazine (existing):
- BuildPageService already renders mag_elements to HTML
- Flipbook viewer already reads page data
- **No new work needed** — just ensure DTP-edited pages render correctly

### Phase 2 — Static Flipbook:
- Pre-render pages as static HTML snapshots
- Store in publish output directory
- Serve from CDN/static hosting

### Phase 3 — Page Thumbnails:
- Server-side rendering of page previews (Puppeteer or wkhtmltoimage)
- Used for: page navigator, TOC, sharing

### Phase 4 — PDF Export:
- Server-side PDF generation (Puppeteer print-to-PDF or dedicated library)
- Support A4/Letter/custom page sizes
- Map CSS to PDF faithfully

### Phase 5 — Print-Ready PDF/X (future):
- ICC color profiles
- Bleed/trim marks
- Font embedding
- CMYK conversion

---

## 8. Migration Strategy

### Recommended: Option C — Hybrid

```
Current flow:
  Magazine List → MagazineEditorV2 (existing editor)

New flow (MP4+):
  Magazine List → MagazineEditorV2 (existing)
                → Magazine Designer (beta, feature-flagged)

Both editors read/write the SAME data (mag_pages + mag_elements).
No data migration needed.
```

### Why Hybrid:
1. **Zero data migration** — both editors use the same tables
2. **No feature loss** — old editor remains fully functional
3. **Gradual adoption** — users can try designer beta, fall back to old editor
4. **Safe rollback** — feature flag disables beta instantly

### What changes:
- Old editor: keep all existing features
- New designer: DTP prototype components reading/writing real data
- Both share: magazineStore, save/load API, element types

---

## 9. Feature Flag / Beta Route

```
Route: /admin/sites/{siteId}/magazines/{issueId}/designer
Feature flag: magazine_dtp_designer_enabled (per-site setting)
```

### Implementation:
1. Add `magazine_dtp_designer_enabled` to site settings JSON
2. Add route in App.tsx (lazy-loaded)
3. Show "Open in Designer" button on magazine list (when flag enabled)
4. Designer reads from same API as existing editor
5. No changes to existing editor behavior

---

## 10. Production Roadmap MP1-MP10

### MP1: Data Model Audit (docs only)
- Verify mag_elements has all needed columns
- Document any missing fields
- Plan migrations if needed (likely none)
- **Risk: LOW**

### MP2: Production Types + Normalizers
- Create shared TypeScript types for production frames
- Create normalizer functions (API response → editor state)
- No UI changes
- **Risk: LOW**

### MP3: Save/Load DTP Document (behind flag)
- Connect DTP canvas components to real API
- Replace mocked data with API data
- Manual save button
- Feature flag required
- **Risk: MEDIUM**

### MP4: Beta Route + Real Canvas
- Add `/designer` route
- DTP shell reads real mag_pages/mag_elements
- Full editing with save
- **Risk: MEDIUM**

### MP5: Asset Library Integration
- Connect AssetField/AssetPicker to image frames
- Upload from canvas
- Missing asset preflight
- **Risk: LOW**

### MP6: HTML Preview/Export
- Preview button renders saved DTP document
- Flipbook viewer reads DTP layout
- **Risk: MEDIUM**

### MP7: Preflight from Real Data
- Run preflight against saved document
- Persist preflight results
- Show in UI
- **Risk: LOW**

### MP8: Templates + Master Pages Persisted
- Save/load templates to DB
- Save/load master page definitions
- Template marketplace (future)
- **Risk: MEDIUM**

### MP9: Production Beta Acceptance
- Manual testing by Niki
- Performance benchmarks (50+ pages)
- Edge case testing
- **Risk: LOW**

### MP10: Deprecate Old Editor
- Only after MP9 acceptance
- Keep old editor available behind "classic editor" flag
- Gradual rollout
- **Risk: HIGH — only do after full testing**

---

## 11. Test Strategy

### Required:
- Save/load round-trip (create → save → reload → verify)
- Frame geometry validation (no NaN, min size enforced)
- XSS in text frames (DOMPurify coverage)
- Asset URL safety (http/https only)
- Preflight correctness (missing images, overflow, bounds)
- Thread persistence (threadId/threadOrder survive save/load)
- Master page frame rendering (locked, visible, correct page number)

### Recommended:
- Migration tests (if any schema changes in MP1)
- Existing magazine editor regression (old editor still works)
- Performance tests (50+ pages, 200+ frames)
- Export snapshot tests (rendered HTML matches expected)

---

## 12. Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Data loss during migration | HIGH | No migration needed — same tables |
| Old editor breaks | HIGH | Feature flag isolation |
| Performance with large documents | MEDIUM | Virtualization in MP9 |
| Legacy format round-trip breaks | MEDIUM | Keep _v2 markers |
| Asset upload integration complexity | LOW | Reuse existing AssetField |
| Preflight false positives | LOW | Tune thresholds in MP7 |

---

## 13. Recommended Next Step

**MP1: Data Model Audit** — Verify that `mag_elements` has every column needed by the prototype frame model. Document any gaps. This is a zero-risk documentation task that sets the foundation for MP2-MP10.

After MP1, proceed to MP2 (shared types) → MP3 (save/load) → MP4 (beta route). The entire MP1-MP4 sequence can be done without any database migrations, since the existing tables already have all required fields.
