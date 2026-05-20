# MAG-P15 Image Frame Workflow + Visual Styling — Acceptance Checklist

## 1. Purpose
Complete image frame workflow with replacement, fit modes, focal point, opacity, caption, visual styling, and cross-page preservation.

## 2. Image Panel Controls (ImagePanel.tsx)

| Control | Status |
|---------|--------|
| Asset picker (select/replace image) | WORKING — AssetField component |
| Clear image button | NEW — removes src and assetId |
| Alt text input + missing warning | WORKING + NEW warning |
| Caption input | NEW |
| Show caption toggle | NEW |
| Fit mode buttons (Fill/Fit/Stretch/Original) | IMPROVED — buttons with descriptions |
| Focal point sliders (0-100%) | IMPROVED — range sliders with % display + reset |
| Opacity slider (0-100%) | NEW |
| Scale slider (10-400%) | IMPROVED — percentage display |
| Shadow presets (None/Subtle/Medium/Strong/Float) | NEW |
| Border radius slider (0-50px) | NEW |
| Background color picker | NEW |
| Advanced: offset, rotation, clip shape, filters | PRESERVED — collapsed section |

## 3. Renderer Changes (MagElementRenderer.tsx)

| Feature | Implementation |
|---------|---------------|
| Image opacity | CSS opacity on `<img>` |
| Image filters | CSS filter (brightness/contrast/saturation/grayscale) |
| Caption display | Absolute-positioned bottom overlay, 20px height |
| Show/hide caption | Conditional render based on `showCaption` flag |
| Border radius | containerStyle.borderRadius on non-circular images |
| Shadow | containerStyle.boxShadow from shadow preset CSS |
| Background color | containerStyle.backgroundColor |

## 4. Manual Acceptance Checklist

| # | Test | Expected |
|---|------|----------|
| 1 | Open DTP editor, add image frame | Empty placeholder appears |
| 2 | Click image frame, open Properties | Image panel shows all controls |
| 3 | Select image via asset picker | Image appears in frame |
| 4 | Click "Clear image" | Image removed, placeholder returns |
| 5 | Select image again | Image restored |
| 6 | Switch fit to Fit (contain) | Image letterboxed inside frame |
| 7 | Switch fit to Stretch | Image distorted to fill |
| 8 | Switch fit to Original | Image natural size |
| 9 | Switch back to Fill | Image covers frame |
| 10 | Drag focal point X slider to 100% | Image pans right |
| 11 | Click Reset focal point | Returns to 50%/50% center |
| 12 | Set opacity to 50% | Image semi-transparent |
| 13 | Set opacity to 100% | Image fully opaque |
| 14 | Type alt text | No warning shown |
| 15 | Clear alt text | "Missing alt text" warning appears |
| 16 | Type caption text | Caption appears below image |
| 17 | Uncheck "Show caption" | Caption hidden |
| 18 | Check "Show caption" | Caption reappears |
| 19 | Select shadow preset "Medium" | Shadow visible on frame |
| 20 | Select shadow preset "None" | Shadow removed |
| 21 | Increase border radius to 20px | Rounded corners |
| 22 | Set background to #333 | Visible behind fit/original images |
| 23 | Open Advanced section | Offset/rotation/clip/filters visible |
| 24 | Increase brightness to 150% | Image brighter |
| 25 | Enable grayscale | Image black & white |
| 26 | Save document | No errors |
| 27 | Reload page | All image settings persist |
| 28 | Drag image frame to page 2 (if P14 works) | Settings preserved |
| 29 | Viewer renders image | Image with fit/focal/opacity renders |
| 30 | Old editor still works | Navigate to /magazines/:id/edit |

## 5. Known Limitations
- Shadow presets are CSS strings, not separate x/y/blur/spread controls (those exist in EffectsPanel for element-level shadow)
- Custom shadow values via EffectsPanel still work independently
- Caption is single-line (truncated), not multi-line
- No image cropping tool (use focal point + fit mode)
- Filters are applied via CSS, not server-side
- Background color only visible when fit mode creates empty space (Fit/Original)
