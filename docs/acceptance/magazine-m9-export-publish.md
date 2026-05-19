# Magazine M9 Export / Publish Prototype — Acceptance Checklist

## Access
Route: `/admin/sites/{siteId}/magazine/dtp-prototype`

## What M9 Adds
- View modes: Edit / Preview / Export toggle in toolbar
- Export tab in right panel with document summary
- Preflight integration: shows pass/warnings/blocked status
- Document stats: pages, spreads, frames, images placed
- Export targets: HTML/Flipbook (prototype), PDF (future), Asset Package (future)
- Fake export button: shows summary alert (no files generated)
- Export blocked when preflight has errors

## Manual Acceptance Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Open prototype | Edit/Preview/Export buttons in toolbar |
| 2 | Click Export mode | Right panel switches to Export tab |
| 3 | Export tab shows document title | Title from mock document |
| 4 | Shows page/spread/frame counts | Correct counts from document |
| 5 | Shows image placement status | e.g., "2/5" images placed |
| 6 | Shows preflight status | PASS / WARNINGS / BLOCKED |
| 7 | Shows issue counts | Errors, warnings, info from preflight |
| 8 | Export targets listed | HTML (prototype), PDF (future), Asset (future) |
| 9 | Click "Prepare Export Summary" | Alert with document summary |
| 10 | Export blocked when errors exist | Button disabled with "Fix errors" text |
| 11 | Fix errors → button enables | Button text changes to "Prepare Export" |
| 12 | Click Preview mode | Mode indicator shows "preview" (handle hiding planned future) |
| 13 | Click Edit mode | Returns to normal editing |
| 14 | M1-M8 features still work | All previous features unchanged |
| 15 | Existing magazine editor | Not replaced |
| 16 | No DB/migration changes | Clean |

## View Modes
- **Edit**: Full editing with tools, handles, guides (default)
- **Preview**: Mode indicator shown; handle/outline hiding is planned future (requires threading viewMode to SpreadCanvas/FrameRenderer)
- **Export**: Shows export readiness panel

## Limitations
- No real file export (prototype summary only)
- No PDF generation
- No asset packaging
- No static HTML output
- Preview mode does not fully hide editing UI (planned future)
- No production integration
