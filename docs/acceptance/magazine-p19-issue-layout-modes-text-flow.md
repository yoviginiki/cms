# MAG-P19 Issue Layout Modes + Text Flow

## 1. Issue Settings
- `layoutMode`: single | book | presentation
- `coverMode`: standalone | spread (book mode only)
- `readingDirection`: ltr | rtl (reserved)
- Saved in DTP API `meta.issueSettings`
- Loaded on document init
- Default: single/standalone/ltr

## 2. Layout Modes

### Single Page
- Pages stacked vertically (existing behavior)
- Use Single view in toolbar

### Magazine / Book
- Cover page alone (if coverMode=standalone)
- Inside pages side-by-side: 2-3, 4-5, etc.
- Use Spread view in toolbar
- Page numbers and master pages still correct

### Presentation
- One page/slide at a time
- Use Single view for slide navigation

## 3. Issue Settings UI
- "Issue" tab in right panel
- Layout mode buttons with descriptions
- Cover mode toggle (book mode only)
- View mode hints

## 4. Manual Acceptance Checklist

| # | Test | Expected |
|---|------|----------|
| 1 | Open Issue tab | Issue settings visible |
| 2 | Select Single Page | Pages stack vertically |
| 3 | Select Magazine/Book | Spread view shows cover alone + pairs |
| 4 | Cover Alone toggle | Cover page shown alone |
| 5 | Cover in Spread | Cover paired with page 2 |
| 6 | Select Presentation | Single slide view |
| 7 | Save + reload | Layout mode persists |
| 8 | All existing features | Still work |

## 5. Fixed / Unfixed Objects
- Image frames show 📌 Fix / 📌 Unfix button (top-right when selected)
- Fixed state: `positionMode: 'fixed'` — shows FIXED badge
- Free state: `positionMode: 'free'` (default)
- Fixed images treated as obstacles by text continuation
- Saved in `metadata.positionMode`, loaded on init

## 6. Spread Images
- Image frames show 📖 Spread / 📖 Single button (bottom-left when selected)
- Spread state: `spanMode: 'spread'` — shows SPREAD badge
- Only meaningful in book mode
- Saved in `metadata.spanMode`, loaded on init

## 7. Text Flow Around Fixed Objects
- `continueTextToNextPage` checks for fixed images on target page
- Continuation frame Y position placed below any overlapping fixed images (8px gap)
- Bounding-box avoidance only (not contour wrapping)

## 8. Manual Acceptance (Phase 2)

| # | Test | Expected |
|---|------|----------|
| 9 | Select image frame | Fix/Unfix + Spread/Single buttons visible |
| 10 | Click 📌 Fix | FIXED badge appears |
| 11 | Click 📌 Unfix | Badge disappears |
| 12 | Click 📖 Spread | SPREAD badge appears |
| 13 | Click 📖 Single | Badge disappears |
| 14 | Save + reload | Fixed/spread state persists |
| 15 | Add fixed image + continue text | Continuation avoids fixed image area |

## 9. Known Limitations
- Spread image visual rendering across two pages not yet implemented (badge only)
- Text wrap is bounding-box avoidance during continuation, not CSS shape-outside
- Reading direction RTL reserved for future
- Presentation mode uses existing Single view (no slide transitions)
- Fixed/unfixed does not affect drag behavior (frame can still be moved)
