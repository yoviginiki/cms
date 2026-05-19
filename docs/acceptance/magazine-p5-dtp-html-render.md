# MAG-P5 DTP HTML Render — Acceptance Checklist

## Access
Preview: `GET /api/v1/sites/{site}/magazine-issues/{issue}/dtp-preview`
Feature flag: `MAGAZINE_DTP_DESIGNER_ENABLED=true`

## What MAG-P5 Implements
- `DtpRenderService` — loads DTP data, renders spreads/pages/frames as HTML
- `DtpPreviewController` — preview route behind feature flag
- `dtp-preview.blade.php` — full-page HTML render with dark background
- Frame types: text, image, quote, pageNumber, shape, line, decorative
- Style: absolute positioning, rotation, z-index, visibility, overflow:hidden
- Image: fit/fill/stretch/original, focal point, opacity, caption overlay
- Safety: strip_tags for text, e() for user content, URL validation for images

## Manual Acceptance Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Save DTP issue in beta editor | Data persisted |
| 2 | Open DTP preview URL | HTML renders with spreads/pages/frames |
| 3 | Text frame appears | Content visible at correct position |
| 4 | Image frame appears | Image with correct fit/focal point |
| 5 | Missing image shows placeholder | "No image" grey box |
| 6 | Hidden frame not rendered | frame.visible=false excluded |
| 7 | Z-order correct | Higher z-index frames on top |
| 8 | Rotation applied | Rotated frames render correctly |
| 9 | Quote frame styled | Blockquote with purple border |
| 10 | Page number shows correct number | From page_index + 1 |
| 11 | Feature flag off | 404 response |
| 12 | Old magazine preview works | Flipbook viewer unchanged |
| 13 | Empty issue shows message | "No DTP content" |

## Architecture
- `DtpRenderService::render()` → loads from MP2 models → returns render-ready array
- `DtpPreviewController::preview()` → passes to Blade view
- `dtp-preview.blade.php` → dark-bg scroll layout with positioned frames

## Limitations
- No PDF export
- No print-ready output
- No responsive reader mode
- No old publish pipeline replacement
- No preflight enforcement before preview
- Preview is admin-only (auth required)
