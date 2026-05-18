# Magazine M1 Canvas Prototype — Acceptance Checklist

## Access
Route: `/admin/sites/{siteId}/magazine/dtp-prototype`

This is a **dev-only prototype** using mocked data. It does NOT replace the existing magazine editor.

## Manual Acceptance Tests

| # | Test | Expected Result |
|---|------|-----------------|
| 1 | Open the DTP Canvas Prototype URL | Full-screen layout with toolbar, spread navigator, canvas, properties panel |
| 2 | Canvas shows pasteboard with pages | Grey pasteboard background, white page sheet(s) with shadow |
| 3 | Cover spread shows single page | One page centered with margin guides (pink dashed) |
| 4 | Editorial spread shows two pages | Two pages side-by-side with spine indicator between them |
| 5 | Margin guides visible | Pink dashed lines showing content margins on each page (safe area is defined by margins in this prototype — a separate safe-area field is planned for M7 preflight) |
| 6 | Text frames visible with content | Text frames show lorem ipsum content with blue type badge |
| 7 | Image placeholders visible | Grey boxes with image icon and dimensions |
| 8 | Quote frames visible | Purple-bordered frames with italic quote text |
| 9 | Page number frames visible | Small frames with page number centered |
| 10 | Click a frame — it selects | Blue border + 8 resize handles appear, type badge shows |
| 11 | Properties panel updates | Right panel shows frame ID, type, X/Y/W/H, rotation, z-index, content |
| 12 | Click empty canvas — clears selection | Frame deselects, properties panel shows document/spread info |
| 13 | Zoom in/out buttons work | Canvas scales up/down (25%-200%) |
| 14 | Fit spread button | Canvas resets to 50% zoom |
| 15 | Spread navigator (left) | Shows thumbnails for all 3 spreads (Cover, Editorial, Gallery) |
| 16 | Click different spread | Canvas switches to that spread, selection clears |
| 17 | Status bar shows info | Spread count, page numbers, frame count, zoom level |
| 18 | Tool buttons visible | Select, Text, Image, Quote tool buttons in toolbar |
| 19 | Existing magazine editor still works | `/admin/sites/{siteId}/magazines/{id}/edit` opens normally |
| 20 | No database changes | No new migration files, no schema modifications |

## What This Prototype Proves
- Frontend can render a professional page-layout canvas model
- Spread/page/frame hierarchy works visually
- Selection and properties inspection works
- Page boundaries, margins, pasteboard are visible
- Multiple spreads can be navigated
- Architecture is ready for M2+ interactive features

## What This Prototype Does NOT Do
- No drag/move/resize (M2)
- No save/persist (uses mocked data only)
- No master pages (M4)
- No text threading (M5)
- No preflight (M7)
- No PDF export (M9)
