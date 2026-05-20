# MAG-P16 Page Panel, Thumbnails, Reorder/Delete/Duplicate + Templates

## 1. Purpose
Complete page management workflow: thumbnails, drag/drop reorder, duplicate, delete with confirmation, and 4 page templates.

## 2. Page Panel
- Left panel shows all pages sorted by page number
- 64px wide thumbnails with proportional height
- Mini element indicators (text=gray, image=green blocks)
- Empty pages show "Empty" text
- Selected page highlighted with primary border
- Click to select, right-click for context menu
- Drag to reorder (cursor changes to grab/grabbing)

## 3. Add Page
- "+" button adds page after current
- Copies page size/margins from current
- New page becomes selected
- Undo/redo supported (pushSnapshot in store)

## 4. Duplicate Page
- Copy button or context menu "Duplicate"
- Deep clones page with new page ID
- All frames get new unique IDs (crypto.randomUUID)
- Thread links within same page preserved; cross-page links cleared
- Duplicate inserted after source, pages renumbered
- Undo/redo supported

## 5. Delete Page
- Context menu or can be triggered from UI
- Confirmation dialog shows element count
- Cannot delete last page (store guard)
- Removes page and all elements
- Pages renumbered after delete
- Undo/redo supported

## 6. Reorder Pages (Drag/Drop)
- Drag page thumbnail to new position
- Blue border indicator shows drop target
- On drop: pages reordered and renumbered
- All frame pageNumber fields updated
- Undo/redo supported

## 7. Page Templates

| Template | Frames Created |
|----------|---------------|
| **Cover** | Full-page cover image + headline + subtitle caption |
| **Article** | Headline + 2-column body text |
| **Gallery** | Title + 4 images in 2×2 grid + 4 captions |
| **Interview** | Title + portrait image + intro text + 2-column Q&A |

- Template picker appears in page panel
- Applying to empty page: immediate
- Applying to non-empty page: confirmation dialog
- Frames added alongside existing content (not replaced)
- All template frames get unique IDs
- Frames are fully editable after creation

## 8. Manual Acceptance Checklist

| # | Test | Expected |
|---|------|----------|
| 1 | Open DTP editor | Page panel visible on left |
| 2 | Page thumbnails visible | Proportional mini previews |
| 3 | Click page in panel | Canvas switches to that page |
| 4 | Add page | New page after current, selected |
| 5 | Duplicate page with frames | New page with cloned frames, new IDs |
| 6 | Right-click page | Context menu (Duplicate/Template/Delete) |
| 7 | Delete page with frames | Confirmation with element count |
| 8 | Confirm delete | Page removed, pages renumbered |
| 9 | Try delete last page | Blocked (store guard) |
| 10 | Drag page to new position | Order changes, blue drop indicator |
| 11 | Apply Cover template (empty page) | Cover image + title + subtitle frames |
| 12 | Apply Article template | Headline + 2-col body text |
| 13 | Apply Gallery template | Title + 4 images + 4 captions |
| 14 | Apply Interview template | Title + portrait + intro + Q&A |
| 15 | Apply template to non-empty page | Confirmation dialog |
| 16 | Template frames editable | Select, move, edit text, change image |
| 17 | Save/reload | Pages, order, templates preserved |
| 18 | Undo add page | Page removed |
| 19 | Undo duplicate | Duplicate removed |
| 20 | Undo delete | Page restored |
| 21 | Undo reorder | Order restored |
| 22 | P14 text continuation | Still works after reorder |
| 23 | P15 image styling | Still works after duplicate |
| 24 | Viewer renders pages in order | Page order matches editor |
| 25 | Old editor fallback | Still works |

## 9. Known Limitations
- Template frames are added alongside existing content, not replaced
- Drag/drop uses HTML5 drag API (not pointer-based custom drag)
- No master/parent pages yet
- No page size change per-page in template picker
- Thread links cleared on duplicate if they cross page boundaries
