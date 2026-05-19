# MAG-P4 Beta DTP Editor API Connection — Acceptance Checklist

## Access
Route: `/admin/sites/{siteId}/magazine-issues/{issueId}/dtp-editor`
Feature flag: `MAGAZINE_DTP_DESIGNER_ENABLED=true` in .env

## What MAG-P4 Implements
- Beta DTP editor page connected to real MAG-P3 API
- Loads DTP document via GET endpoint
- Saves DTP document via PUT endpoint (manual save button)
- Dirty state tracking (unsaved changes indicator)
- Error handling (load failure, 404/feature flag, save validation errors)
- Reuses prototype components (SpreadCanvas, PropertiesPanel, LayersPanel, etc.)
- API client: `dtpDesigner.loadDocument()` / `dtpDesigner.saveDocument()`

## Manual Acceptance Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Old magazine editor opens | `/magazines/{id}/edit` still works |
| 2 | Beta editor with flag OFF | Shows "DTP Designer Not Available" |
| 3 | Beta editor with flag ON | Loads and shows DTP canvas |
| 4 | Empty issue loads | Shows starter spread with empty page |
| 5 | Issue with data loads | Shows saved spreads/pages/frames |
| 6 | Select/move frame | Frame moves, "Unsaved" appears |
| 7 | Click Save | Document saves to API, "Unsaved" clears |
| 8 | Reload page | Saved changes persist |
| 9 | Save invalid data | Validation error shown |
| 10 | Network error | Error message shown |
| 11 | Layers panel works | Can hide/lock/reorder frames |
| 12 | Preflight panel works | Shows issues from live data |
| 13 | Prototype still works | `/magazine/dtp-prototype` unchanged |
| 14 | No export/publish changes | Flipbook/publish unaffected |

## Architecture
- `DtpEditorBeta.tsx` — full-screen editor page
- `apiToDocument()` — converts API JSON → DtpDocument for canvas
- `documentToApi()` — converts DtpDocument → API save payload
- Reuses all M1-M9 prototype components unchanged

## Limitations
- No autosave (manual save only)
- No zoom/pan controls in beta (uses fixed 50%)
- No template gallery in beta (prototype only)
- No beforeunload warning for unsaved changes
- Image picker uses mock Unsplash URLs (real AssetField in MAG-P5)
- Layers/asset_references pass through on save (not yet editable in beta)
- No export/publish from beta editor
