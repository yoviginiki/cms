# Magazine M8 Page Templates & Master Pages — Acceptance Checklist

## Access
Route: `/admin/sites/{siteId}/magazine/dtp-prototype`

## What M8 Adds
- Template gallery (Tmpl tab): 7 templates (Cover, TOC, Editorial, Article, Interview, Gallery, Quote)
- Apply template: add frames to page/spread or replace existing
- Master page assignment: None, Default Master, Editorial Master
- Master objects: page number, header, footer — rendered with dashed amber border
- Master objects locked and read-only on regular pages
- MASTER badge on hover/selection
- Properties panel: "Master object — read-only" message

## Manual Acceptance Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Open prototype, click Tmpl tab | Template gallery + master page list visible |
| 2 | Click "Add to page" on Cover template | Cover frames added to current page |
| 3 | Click "Replace" on Editorial Spread | Existing page frames replaced, master objects kept |
| 4 | Template frames editable | Can move/resize/edit template frames normally |
| 5 | Assign Default Master | Page number, header, footer appear with dashed amber border |
| 6 | Page number shows actual number | Shows page number from spread data |
| 7 | Master objects have MASTER badge | Badge visible on hover/selection |
| 8 | Select master object | Properties shows "Master object — read-only" |
| 9 | Try drag master object | Can't move (locked) |
| 10 | Try resize master object | Can't resize (locked) |
| 11 | Assign Editorial Master | Different header, page number position |
| 12 | Assign None | Master objects removed |
| 13 | Layers panel shows master objects | With lock icon (locked by default) |
| 14 | M1-M7 features still work | All previous features unchanged |
| 15 | Existing magazine editor | Not replaced |
| 16 | No DB/migration changes | Clean |

## Limitations
- No persistent templates (state resets on refresh)
- No master page editing UI (read-only overlay only)
- No template marketplace/import
- No export integration
- No production integration
- Master page assignment is per-spread, not per-page
