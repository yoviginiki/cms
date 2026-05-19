# MAG-P3 Save/Load API — Acceptance Checklist

## Endpoints
```
GET /api/v1/sites/{site}/magazine-issues/{issue}/dtp-document
PUT /api/v1/sites/{site}/magazine-issues/{issue}/dtp-document
```
Both behind `RequireDtpDesigner` middleware (feature flag).

## What MAG-P3 Implements
- `DtpDocumentController` — GET (load) + PUT (save)
- `DtpDocumentService` — load/save with atomic transaction
- `SaveDtpDocumentRequest` — validation (frame types, geometry, URLs)
- `RequireDtpDesigner` middleware — feature flag gate
- Routes registered behind middleware group
- ID mapping: client UUIDs → server UUIDs on save
- Cascade: spreads → pages → layers → frames → asset_references

## Acceptance Tests

| # | Test | Expected |
|---|------|----------|
| 1 | GET empty issue | Returns empty arrays for spreads/pages/frames |
| 2 | PUT valid document | Persists and returns full document |
| 3 | GET after PUT | Returns same data that was saved |
| 4 | PUT invalid frame_type | 422 with validation error |
| 5 | PUT negative width | 422 "min:1" error |
| 6 | PUT javascript: URL | 422 "must use http or https" |
| 7 | Feature flag off | 404 "DTP Designer not enabled" |
| 8 | Wrong site issue | 404 |
| 9 | PUT replaces cleanly | Old frames deleted, new ones inserted |
| 10 | Existing magazine tests pass | No regression |

## Not in MAG-P3
- No frontend connection
- No PATCH frame
- No duplicate/reorder endpoints
- No preflight endpoint
- No autosave
- No publish/export
