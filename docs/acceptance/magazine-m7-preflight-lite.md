# Magazine M7 Preflight Lite — Acceptance Checklist

## Access
Route: `/admin/sites/{siteId}/magazine/dtp-prototype`

## What M7 Adds
- Preflight engine: detects missing images, empty frames, out-of-bounds geometry
- Preflight panel: right-side tab with grouped issues, click-to-select
- Status indicator in toolbar: green (pass) / amber (warnings) / red (blocked) + issue count
- Auto-run on document changes via useMemo
- Readiness score 0-100

## Preflight Rules
| Code | Severity | Trigger |
|------|----------|---------|
| missing_image | error | Image frame with no src |
| missing_alt_text | warning | Image with src but no alt text |
| empty_text_frame | warning | Text/quote frame with no content |
| invalid_dimensions | error | Frame with zero/negative width or height |
| frame_outside_page (full) | error | Frame completely outside page bounds |
| frame_outside_page (partial) | warning | Frame partially outside page |
| frame_outside_safe_area | info | Frame extends beyond margins |
| empty_page | info | Page with no frames |

## Manual Acceptance Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Open prototype | Preflight tab visible in right panel |
| 2 | Click Preflight tab | Shows status, score, and issues |
| 3 | Missing image frames | Error: "No image selected" |
| 4 | Empty text frames | Warning: "Empty text frame" |
| 5 | Move frame fully off page | Error: "Completely outside page" |
| 6 | Move frame partially off page | Warning: "Partially outside page bounds" |
| 7 | Frame beyond margins | Info: "Extends beyond margins" |
| 8 | Click issue in panel | Selects the related frame, switches to Properties tab |
| 9 | Fix issue (add image) | Issue disappears from preflight |
| 10 | All issues fixed | Status: PASS, score: 100 |
| 11 | Toolbar indicator | Shows green/amber/red icon + count |
| 12 | Click toolbar indicator | Toggles preflight panel |
| 13 | M1-M5 features work | Move, resize, zoom, guides, text, images |
| 14 | Existing magazine editor | Not replaced |
| 15 | No DB/migration changes | Clean |

## Score Calculation
- Start at 100
- Each error: -15 points
- Each warning: -5 points
- Each info: -1 point
- Clamped to 0-100

## Limitations
- No text overflow detection (would require DOM measurement, deferred)
- No canvas warning badges on frames (issues shown in panel only, badges planned future)
- No image resolution/DPI checks
- No color management
- No PDF/X validation
- No print readiness
- No persistent preflight results
- No export integration
