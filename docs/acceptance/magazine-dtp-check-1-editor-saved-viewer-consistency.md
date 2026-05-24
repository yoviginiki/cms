# MAG-DTP-CHECK-1 — Editor vs Saved vs Viewer Consistency Checker

## 1. Purpose

Automatic consistency checker that detects when what the editor shows does not
match what is saved, loaded, or rendered. Permanent diagnostic/QA tool inside
the DTP editor debug panel.

## 2. Relationship to Debug Mode

Builds on MAG-DTP-DEBUG-1. The consistency checker appears as a "Check" tab
inside the existing debug panel. Requires `localStorage.getItem('dtp-debug') === '1'`.

## 3. Consistency Stages

| Stage | Description |
|-------|-------------|
| A. editorDocument | Current in-memory MagPageData[] + issueSettings |
| B. savePayload | Output of pagesToDtpApi sent to backend |
| C. loadedRaw | Raw API response from dtpDesigner.loadDocument |
| D. normalized | After dtpApiToPages normalization |
| E. viewerModel | Viewer adapter input (partial — full viewer render model not accessible) |

## 4. Tracked Paths

### Issue/Document Settings
- settings.layoutMode, settings.coverMode, settings.readingDirection

### Pages
- pages.length, page.id, page order, page.width, page.height, page.margins
- page.backgroundColor, page.masterPageId

### Frames
- frame count per page, frame.id, frame.type
- frame.x, frame.y, frame.width, frame.height
- frame.zIndex, frame.visible, frame.locked, frame.rotation

### Text Content
- content.html, typography.fontFamily, typography.fontSize
- typography.fontWeight, typography.lineHeight, typography.letterSpacing
- typography.color/textColor, typography.textAlign

### Image Content
- content.src, content.alt, content.caption, content.showCaption
- content.fitMode, content.focalPoint, content.opacity

### Style
- style.fill, style.stroke, style.opacity, style.borderRadius
- style.shadow, style.blur, style.backdropBlur

### Flow/Links
- threadId, threadOrder, positionMode, spanMode

### Master Pages
- masterPageId

## 5. Issue Codes

| Code | Severity | Description |
|------|----------|-------------|
| field_missing_in_payload | warning | Field in editor but not in save payload |
| field_lost_after_save | error | Field in payload but missing after reload |
| field_stripped_by_normalizer | warning | Normalization removed a field |
| viewer_render_mismatch | warning | Viewer model differs from editor |
| layout_mode_mismatch | error | Layout mode changed between stages |
| frame_missing_after_load | error | Frame exists in editor/payload but missing after reload |
| image_missing_after_save | error | Image src lost during save |
| style_not_rendered_in_viewer | warning | Style exists in editor but not in viewer |
| typography_not_rendered_in_viewer | error | Typography lost after save/reload |
| page_order_mismatch | warning | Page ordering differs |
| page_count_mismatch | error | Page count differs between stages |
| frame_count_mismatch | warning | Frame count differs on a page |
| field_changed_after_load | error | Value changed after save/reload |

## 6. UI Behavior

- **Status badge**: PASS (green) / WARNINGS (yellow) / FAIL (red)
- **Summary counts**: errors, warnings, lost fields, viewer mismatches, save mismatches
- **Run button**: manual "Run Consistency Check" or auto after save
- **Issue list**: grouped by severity, expandable with full stage values
- **Click to select**: clicking an issue with a relatedFrameId selects it on canvas
- **Copy/Export**: consistency report as JSON

## 7. How to Run Check

1. Enable debug mode: `localStorage.setItem('dtp-debug', '1')`
2. Open DTP editor, click Debug button in status bar
3. Switch to "Check" tab
4. Click "Run Consistency Check"
5. Or: save the document — check runs automatically after save success

## 8. Interpreting Results

- **PASS**: All checked paths match across available stages
- **WARNINGS**: Some fields differ but no critical data loss detected
- **FAIL**: Critical fields (layout mode, image src, text content, typography) lost or mismatched

## 9. Example Lost Field Cases

- Text color set to red in editor → saved → reloaded → color missing = `typography_not_rendered_in_viewer`
- Image src set → saved → reloaded → src empty = `image_missing_after_save`
- Layout mode set to "book" → saved → meta.issueSettings missing = `layout_mode_mismatch`
- Frame added → saved → frame not in loaded response = `frame_missing_after_load`

## 10. Security/Privacy

- No auth tokens, cookies, passwords, or headers captured
- Export is user-initiated only (click required)
- Debug mode off by default — no consistency data collected when off
- Consistency result contains only document structure data

## 11. Known Limitations

- **Viewer comparison is partial**: Full viewer render model is not accessible from
  the editor. The checker verifies editor → save → load round-trip fidelity but
  cannot fully verify viewer rendering. Reported as: "Viewer render model
  unavailable; checking editor vs save/load only."
- **savedResponse stage not captured**: The DTP save API does not return the
  modified document in its response body. The checker cannot compare what the
  backend actually stored — only what was sent (payload) vs what comes back on
  reload (loaded). If the API adds a response body in the future, the checker
  can be extended to accept it.
- **normalizedDocument stage not separate**: The editor's `dtpApiToPages`
  normalizes inline during load — there is no separate pre-normalization vs
  post-normalization snapshot. The `field_stripped_by_normalizer` issue code
  exists in the enum for future use when normalization is refactored into a
  distinct step. Currently `normalizationMismatches` reports 0.
- **Editor settings are editor-local**: Guide/grid/snap settings are not
  persisted to the API. The checker reports them at info-level only when they
  deviate from defaults after a reload, to document this expected behavior.
- **Style comparison is key-level**: Nested style objects (fill.gradient, etc.)
  are compared as whole objects, not individual sub-paths.

## 12. Manual Acceptance Checklist

### Setup
- [ ] Open DTP editor
- [ ] Enable debug mode: `localStorage.setItem('dtp-debug', '1')`
- [ ] Debug panel appears with "Check" tab

### Save/Load Round-Trip
- [ ] Add text frame, set text color red
- [ ] Save document
- [ ] Consistency check auto-runs after save
- [ ] If color survives round-trip: PASS
- [ ] If color lost: issue appears with code `typography_not_rendered_in_viewer`

### Image Consistency
- [ ] Add image frame, set image src
- [ ] Save and reload
- [ ] Run consistency check
- [ ] If image src lost: issue code `image_missing_after_save`

### Layout Mode
- [ ] Set layout mode to "Book"
- [ ] Save and reload
- [ ] Run check
- [ ] If mode lost: issue code `layout_mode_mismatch`

### Viewer Comparison
- [ ] Note "Viewer render model unavailable" message when viewer model not provided
- [ ] This is expected — viewer check is partial

### Export
- [ ] Copy consistency report JSON (Copy button)
- [ ] Export consistency report (Download button)
- [ ] Verify no secrets in exported data

### Regression
- [ ] Debug off: no Check tab visible
- [ ] Public viewer: no debug/check UI
- [ ] Save/load works normally
- [ ] Clicking issue selects related frame on canvas

## Files Changed

- `resources/admin/src/lib/dtpConsistencyChecker.ts` — Core checker logic (new)
- `resources/admin/src/components/magazine/DtpConsistencyPanel.tsx` — UI component (new)
- `resources/admin/src/components/magazine/DtpDebugPanel.tsx` — Added "Check" tab
- `resources/admin/src/pages/DtpEditorBeta.tsx` — Wired consistency checker + auto-run
