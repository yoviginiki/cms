# Magazine M3 Canvas Tools — Acceptance Checklist

## Access
Route: `/admin/sites/{siteId}/magazine/dtp-prototype`

## What M3 Adds to M2
- Rulers (horizontal + vertical) with tick marks and labels
- Toggleable guide overlays (margins, center lines)
- Snap engine with page edges, margins, centers, and other frame edges/centers
- Snap indicator lines (cyan) shown during drag
- Shift+click multi-select
- Align toolbar (left/center-h/right/top/center-v/bottom) for 2+ selected frames
- Distribute toolbar (horizontal/vertical) for 3+ selected frames
- Toggle buttons: Rulers, Guides, Snap
- Multi-nudge: arrow keys nudge all selected frames
- Properties panel: multi-select info panel

## Manual Acceptance Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Open prototype | Layout renders with rulers, guides, toolbar |
| 2 | Rulers visible | Horizontal ruler at top, vertical ruler at left, with 50/100px tick marks |
| 3 | Toggle rulers off | Rulers disappear, canvas uses full space |
| 4 | Margin guides visible | Pink dashed margin lines on pages |
| 5 | Center guides visible | Dotted center lines (horizontal + vertical) |
| 6 | Toggle guides off | All guides disappear |
| 7 | Drag frame near page edge | Frame snaps to page edge, cyan line appears |
| 8 | Drag frame near margin | Frame snaps to margin line |
| 9 | Drag frame near page center | Frame snaps to center |
| 10 | Drag frame near another frame edge | Frame snaps to other frame's edge |
| 11 | Toggle snap off | Dragging no longer snaps |
| 12 | Snap at 50% zoom | Snapping works correctly |
| 13 | Snap at 100% zoom | Snapping works correctly |
| 14 | Shift+click second frame | Both frames selected (blue borders) |
| 15 | Properties panel shows multi-select | "2 frames selected" with align instructions |
| 16 | Align Left (2 frames) | Both frames align to leftmost X |
| 17 | Align Center H (2 frames) | Both frames center horizontally |
| 18 | Align Right (2 frames) | Both frames align to rightmost right edge |
| 19 | Align buttons disabled (0-1 frames) | Buttons grayed out with tooltip |
| 20 | Select 3+ frames, Distribute H | Frames evenly spaced horizontally |
| 21 | Distribute disabled (<3 frames) | Buttons grayed out |
| 22 | Arrow keys nudge all selected | All selected frames move 1px |
| 23 | Shift+arrow nudges all | All selected frames move 10px |
| 24 | Status bar shows selected count | "2 selected" shown |
| 25 | Click empty canvas | Clears all selection |
| 26 | M2 features still work | Drag, resize, properties editing, zoom |
| 27 | Existing magazine editor works | Not replaced |
| 28 | No DB/migration changes | Clean |

## Snap Targets
- Page edges (0, width, 0, height)
- Margin lines (left, right, top, bottom)
- Page center (width/2, height/2)
- Other frame edges (left, right, top, bottom)
- Other frame centers (horizontal, vertical)
- Tolerance: 5px in canvas space

## Limitations
- No persistence (state resets on page refresh)
- No undo/redo
- Resize snapping not implemented (move snapping only)
- No drag-to-create-guide from ruler
- No rotation drag handle
- No multi-select box drag (Shift+click only)
- Single frame only for resize handles
