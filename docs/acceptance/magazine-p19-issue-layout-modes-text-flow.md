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

## 5. Known Limitations
- Spread image spanning not implemented in this slice
- Fixed/unfixed objects not implemented in this slice
- Text wrap around images not implemented in this slice
- Reading direction RTL reserved for future
- Presentation mode uses existing Single view (no slide transitions)
