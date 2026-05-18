# Magazine M2 Editable Frame Geometry — Acceptance Checklist

## Access
Route: `/admin/sites/{siteId}/magazine/dtp-prototype`

## What M2 Adds to M1
- Frames can be moved by dragging
- Frames can be resized via 8 corner/edge handles
- Properties panel has editable X/Y/W/H/Rotation/Z-Index inputs
- Arrow keys nudge selected frame (1px, Shift+10px)
- All operations are zoom-aware
- Minimum frame size: 20x20px
- All state is local (mocked data, no persistence)

## Manual Acceptance Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Open prototype | Full layout renders (toolbar, spreads, canvas, properties) |
| 2 | Click a frame | Selected with border + 8 handles + type badge |
| 3 | Drag selected frame | Frame moves, x/y update in properties panel live |
| 4 | Drag at 50% zoom | Movement is smooth and correct (not doubled/halved) |
| 5 | Drag at 100% zoom | Movement matches mouse delta exactly |
| 6 | Drag at 200% zoom | Movement is smooth and correct |
| 7 | Drag a corner handle (SE) | Frame resizes, w/h update in properties panel |
| 8 | Drag a corner handle (NW) | Frame resizes from top-left, x/y/w/h all update |
| 9 | Drag an edge handle (E) | Only width changes |
| 10 | Drag an edge handle (S) | Only height changes |
| 11 | Resize below 20px | Width/height clamped to 20px minimum |
| 12 | Type X value in properties | Frame moves to typed X position on Enter/blur |
| 13 | Type W value in properties | Frame width changes (min 20px enforced) |
| 14 | Type NaN in properties | Reverts to previous value (no NaN CSS) |
| 15 | Arrow keys nudge frame | Moves 1px per press |
| 16 | Shift+arrow nudges frame | Moves 10px per press |
| 17 | Arrow keys while in input | Does not nudge (input handles keystrokes) |
| 18 | Click empty canvas | Selection clears, properties show document info |
| 19 | Switch spreads | Selection clears, new spread renders |
| 20 | Zoom in/out | Canvas scales, drag/resize still zoom-correct |
| 21 | Status bar | Shows frame position and dimensions when selected |
| 22 | Existing magazine editor | Still opens at /magazines/{id}/edit |
| 23 | No DB/migration changes | No new files in database/migrations/ |

## Out-of-Bounds Behavior
Frames are allowed outside page boundaries (no clamping). This matches InDesign behavior where objects can extend onto the pasteboard. Visual boundary enforcement is planned for M7 (preflight).

## Limitations
- No persistence (state resets on page refresh)
- No snap-to-margin (planned M3)
- No rotation drag handle (rotation editable via properties panel only)
- No multi-select drag (single frame only)
- No undo/redo (planned M3)
