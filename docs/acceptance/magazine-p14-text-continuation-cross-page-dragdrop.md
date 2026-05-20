# MAG-P14 Text Continuation + Cross-page Drag/Drop — Acceptance Checklist

## 1. Purpose
Implement real text frame continuation to next page and cross-page frame drag/drop.

## 2. Text Overflow Detection
- DOM-based: `scrollHeight > clientHeight + 2`
- Updates on text edit, resize, font change
- Red overflow indicator on frame

## 3. "+ Continue" Control
- Visible `+ Continue` button on bottom-right of overflowing text frames
- Only appears when: text overflows AND frame is NOT already threaded
- If already threaded and overflowing: shows `⋯` warning icon
- Clicking triggers `continueTextToNextPage(elementId)` in store

## 4. Continue to Next Page Behavior
1. Finds source element across all pages
2. Determines next page number
3. If next page doesn't exist → creates it automatically (same size/margins as current)
4. Creates continuation text frame on next page at same X, top margin Y
5. Links via `threadId` (shared) and `threadOrder` (sequential)
6. Continuation frame starts with placeholder text "Continued text..."
7. Navigates to next page and selects the continuation frame
8. Full undo support (pushes snapshot before action)

## 5. Linked Text Frame Indicators
- **Source frame**: blue badge "Continues → p.X" at bottom-right
- **Continuation frame**: blue badge "← Continued from p.Y" at top-left
- **Thread dot**: blue circle on bottom-right with thread position tooltip
- Indicators are pointer-events:none (don't block interaction)

## 6. Actual Text Flow Status
**LINKED FOUNDATION** — frames are linked via threadId/threadOrder. The existing TextThreading engine (`distributeThreadContent`) distributes content across threaded frames. Manual text editing in continuation frames is supported. Full professional text composition is not claimed.

## 7. Cross-page Drag/Drop: `moveElementToPage()`
New store action:
- Removes element from source page's elements array
- Inserts clone into target page's elements array
- Updates `pageNumber` field
- Optionally updates x/y coordinates
- Preserves all content, style, settings, thread links
- Pushes undo snapshot
- Navigates to target page and selects the element

**Current limitation**: The actual drag gesture in `MagSelectionEngine` moves elements within the current page's coordinate space. To drag across pages in spread/grid view, the user currently uses `moveElementToPage` programmatically. Visual cross-page drag during pointer move requires detecting which page the cursor is over in spread view — this is partially supported by the spread view layout but not yet fully wired for automatic page detection during drag. Documented honestly as partial.

## 8. Manual Acceptance Checklist

| # | Test | Expected |
|---|------|----------|
| 1 | Open DTP editor | Canvas loads |
| 2 | Add text frame | Frame appears |
| 3 | Double-click, type long text | Inline editing works |
| 4 | Resize frame smaller | Red overflow indicator + "Continue" button appears |
| 5 | Click "+ Continue" | Next page created if needed, continuation frame appears |
| 6 | Source frame shows "Continues → p.2" | Blue badge at bottom |
| 7 | Continuation frame shows "← Continued from p.1" | Blue badge at top |
| 8 | Save document | No errors |
| 9 | Reload page | Text, links, pages all persist |
| 10 | Undo after Continue | Continuation frame and page removed |
| 11 | Redo | Continuation restored |
| 12 | Thread indicators visible | Blue dots on linked frames |
| 13 | Already-threaded overflow shows ⋯ | Warning icon, not Continue button |
| 14 | Image frame on page 1 | Image frame exists |
| 15 | Call moveElementToPage | Frame moves to page 2 |
| 16 | Existing behavior: add/move/resize | Still works |
| 17 | Viewer still works | No crash on linked frames |
| 18 | Old editor fallback | Still opens |
| 19 | Preflight still works | Status panel shows result |

## 9. Known Limitations
- Cross-page drag during pointer move not fully wired for automatic page detection (user uses store action or text continuation button)
- Text flow is linked-frame-based with distributeThreadContent, not a professional typesetting engine
- Continuation frame starts with placeholder text, not automatically split overflow
- Cross-page move updates page assignment but visual drag between pages in spread view requires further MagSelectionEngine integration
- No drag-and-drop visual feedback showing target page highlight during drag
