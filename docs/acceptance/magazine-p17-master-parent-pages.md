# MAG-P17 Master / Parent Pages — Acceptance Checklist

## 1. Purpose
Production master/parent pages with repeating elements (page numbers, headers, footers, decorative lines) that render on assigned pages.

## 2. Data Model

### Master pages
- Stored as `MagPageData` with `isMaster: true`
- Negative page numbers (not displayed in page list)
- `_masterName` stores display name (A — Standard, B — Editorial)
- Master elements stored in page's `elements` array

### Page assignment
- `MagPageData.masterPageId` links a content page to a master page
- `null` = no master assigned
- Assign to single page or all pages

### Element fields
- `onMaster: true` marks master-created elements
- Dynamic page numbers use `page_number` element type with `startAt` resolved at render

## 3. Default Master Pages

| Master | Elements |
|--------|----------|
| **A — Standard** | Page number (bottom center) + footer line |
| **B — Editorial** | Page number (bottom center) + header text ("Magazine Title") + footer line |

Created automatically on first document load if no masters exist.

## 4. Master Page UI (PageNavigator)
- Masters section at top of page panel
- Click master name to enter master editing mode
- "← Back to pages" to exit master editing
- Master assignment dropdown for current page
- "Apply to all pages" quick action

## 5. Master Editing Mode
- `store.editingMasterId` tracks active master
- Canvas shows master page when editing
- All standard frame tools work (add/move/resize/edit)
- Exit returns to first content page

## 6. Master Element Rendering in Editor
- Master elements render on assigned pages
- Rendered behind page elements
- Visual markers: "MASTER" badge + dashed warning border
- `opacity: 0.6` to distinguish from page content
- `pointerEvents: none` — not interactive in page mode
- Dynamic page numbers resolved to actual page number

## 7. Override / Detach
**DEFERRED** — Override/detach individual master elements on specific pages is documented as future. Current behavior: master elements are read-only on pages. User can remove master assignment entirely to detach all.

## 8. Manual Acceptance Checklist

| # | Test | Expected |
|---|------|----------|
| 1 | Open DTP editor | Masters section visible in page panel |
| 2 | "A — Standard" master exists | Listed in masters section |
| 3 | "B — Editorial" master exists | Listed in masters section |
| 4 | Click master name | Enters master editing mode |
| 5 | Add/edit master elements | Standard frame tools work |
| 6 | Click "← Back to pages" | Returns to page editing |
| 7 | Select page, assign A — Standard | Master elements appear on page |
| 8 | Page number shows correct number | Matches page position |
| 9 | Footer line visible | Decorative line at bottom |
| 10 | Assign B — Editorial | Header + page number + footer visible |
| 11 | Remove master (set None) | Master elements disappear |
| 12 | Apply to all pages | Every page shows master elements |
| 13 | Add page | Page number updates on all pages |
| 14 | Delete page | Page numbers update |
| 15 | Reorder pages | Page numbers update |
| 16 | Master elements show "MASTER" badge | Yellow badge visible |
| 17 | Master elements have dashed border | Warning-colored dashed outline |
| 18 | Master elements not clickable in page mode | pointerEvents: none |
| 19 | Save document | No errors |
| 20 | Reload page | Master assignments persist |
| 21 | P14 text continuation | Still works |
| 22 | P15 image styling | Still works |
| 23 | P16 page operations | Still works |
| 24 | Old editor fallback | Still works |
| 25 | Viewer renders | Master elements on pages |

## 9. Known Limitations
- Override/detach individual master elements not implemented (remove full master assignment instead)
- Master pages use negative page numbers internally
- No master page size independent of document
- Master name stored as `_masterName` convention
- No "odd pages / even pages" master assignment (apply to all or individual)
- Master elements not interactive in page mode (by design)
- Viewer renders master elements via the same canvas render path
