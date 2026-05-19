# Magazine M6 Layers Panel — Acceptance Checklist

## Access
Route: `/admin/sites/{siteId}/magazine/dtp-prototype`

## What M6 Adds
- Layers tab in right panel (Props / Layers / Check)
- Frame list sorted by z-index (top layer first)
- Visibility toggle (eye icon) — hides frame from canvas
- Lock toggle (lock icon) — prevents move/resize/edit
- Z-order controls: move up/down, bring to front/send to back
- Selection sync: clicking layer selects frame on canvas
- Locked/hidden status shown in Properties panel
- Cursor changes to not-allowed for locked frames

## Manual Acceptance Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Open prototype, click Layers tab | All frames listed by z-index |
| 2 | Click layer in list | Frame selected on canvas |
| 3 | Select frame on canvas | Matching layer highlighted |
| 4 | Click eye icon | Frame disappears from canvas |
| 5 | Hidden frame in layers | Shown as dimmed (opacity 40%) |
| 6 | Click eye icon again | Frame reappears |
| 7 | Click lock icon | Lock icon turns amber |
| 8 | Try to drag locked frame | Can't move (cursor: not-allowed) |
| 9 | Try to resize locked frame | Nothing happens |
| 10 | Arrow keys on locked frame | Frame doesn't nudge |
| 11 | Properties panel for locked frame | Shows "Locked" warning |
| 12 | Properties panel for hidden frame | Shows "Hidden" warning |
| 13 | Click move up button | Frame z-index increases, canvas stacking changes |
| 14 | Click bring to front | Frame goes to top z-index |
| 15 | Click send to back | Frame goes to lowest z-index |
| 16 | Unlock frame | Can drag/resize again |
| 17 | M1-M5 features still work | Move, resize, zoom, guides, text, images |
| 18 | Existing magazine editor | Not replaced |
| 19 | No DB/migration changes | Clean |

## Limitations
- No persistent layer state (resets on refresh)
- No layer groups
- No drag-and-drop layer reorder (buttons only)
- No master page layers
- No named layers (frames are the layers)
