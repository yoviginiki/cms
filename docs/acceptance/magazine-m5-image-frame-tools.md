# Magazine M5 Image Frame Tools — Acceptance Checklist

## Access
Route: `/admin/sites/{siteId}/magazine/dtp-prototype`

## What M5 Adds
- Image frame model: src, alt, caption, fitMode, focalPoint, opacity
- Image rendering: fill/fit/stretch/original with focal point positioning
- Mock asset picker: 5 sample images in properties panel
- Image controls: fit mode, focal point X/Y, opacity slider, alt text, caption
- Missing image warning: amber triangle + "No image" placeholder
- Caption display: overlaid at bottom of image frame
- Clear image button returns to placeholder

## Manual Acceptance Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Open prototype | Layout renders with image frames showing placeholder |
| 2 | Select image frame | Properties panel shows Image section |
| 3 | Missing image warning | Amber triangle + "No image" + "Missing image" in panel |
| 4 | Click mock asset thumbnail | Image appears in frame on canvas |
| 5 | Fit mode: fill | Image covers frame (object-fit: cover) |
| 6 | Fit mode: fit | Image contained in frame (object-fit: contain) |
| 7 | Fit mode: stretch | Image stretches to fill (object-fit: fill) |
| 8 | Fit mode: original | Image at natural size (object-fit: none) |
| 9 | Focal point X/Y | Changing values shifts image position (visible in fill mode) |
| 10 | Opacity slider | Image becomes transparent, selection UI stays visible |
| 11 | Alt text input | Text saved in properties (plain text) |
| 12 | Caption input | Caption appears at bottom of image frame |
| 13 | Clear image | Returns to placeholder with warning |
| 14 | Image doesn't block drag | Can still drag image frame by clicking on it |
| 15 | Image doesn't block resize | Resize handles still work over image |
| 16 | Text frames still work | Text editing, typography unchanged |
| 17 | Move/resize/zoom/guides | All M1-M4 features still work |
| 18 | Existing magazine editor | Not replaced |
| 19 | No DB/migration changes | Clean |

## Fit Mode Behavior
- **fill**: `object-fit: cover` — image covers entire frame, may crop
- **fit**: `object-fit: contain` — full image visible, may have letterboxing
- **stretch**: `object-fit: fill` — image stretches to exact frame dimensions
- **original**: `object-fit: none` — image at natural size, centered by focal point

## Caption Behavior
Caption displays as overlay text at the bottom of the image frame (semi-transparent dark background). Caption is also editable as metadata in the properties panel.

## Limitations
- No real asset upload (mock images only)
- No persistent image selection (resets on refresh)
- No advanced crop editor (focal point numeric only)
- No visual focal point picker (planned)
- No image resolution/DPI warning
- No drag-drop images from file system
- No PDF/export integration
