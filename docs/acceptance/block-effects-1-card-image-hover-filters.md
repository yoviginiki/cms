# BLOCK-EFFECTS-1 — Global Card/Image Hover Effects + Image Filters

## 1. Purpose
Reusable visual effects system for blocks that render cards and/or images.
Provides configurable hover animations, image filters, and color overlays.

## 2. Global Effects Schema

```json
{
  "effects": {
    "enabled": true,
    "hover": {
      "enabled": true,
      "preset": "soft-pop",
      "scale": 1.02,
      "translateY": -3,
      "shadow": "soft",
      "duration": 300,
      "easing": "ease-out"
    },
    "imageFilter": {
      "enabled": true,
      "preset": "grayscale",
      "grayscale": 100,
      "sepia": 0,
      "brightness": 100,
      "contrast": 100,
      "saturation": 100
    },
    "overlay": {
      "enabled": true,
      "color": "#000000",
      "opacity": 30,
      "blendMode": "normal"
    }
  }
}
```

All fields optional. Default: disabled. Old blocks render unchanged.

## 3. Hover Presets

| Preset | Scale | Lift | Shadow |
|--------|-------|------|--------|
| none | 1.0 | 0px | none |
| lift | 1.0 | -6px | medium |
| scale | 1.03 | 0px | soft |
| lift-scale | 1.02 | -4px | medium |
| soft-pop | 1.02 | -3px | soft |
| strong-pop | 1.05 | -8px | strong |

## 4. Image Filters

| Preset | Effect |
|--------|--------|
| none | No filter |
| grayscale | Full black & white |
| sepia | Warm vintage tone |
| muted | Desaturated, low contrast |
| high-contrast | Vivid, punchy |
| custom | Manual sliders (grayscale, sepia, brightness, contrast, saturation) |

## 5. Color Overlay
- Color picker
- Opacity 0-100%
- Blend modes: normal, multiply, screen, overlay, soft-light
- Renders as absolute-positioned div over image, pointer-events:none

## 6. Adopted Blocks

| Block | Status | Notes |
|-------|--------|-------|
| postgrid | COMPLETE | Full hover + filter + overlay |
| latestposts | FUTURE | Same architecture, easy adoption |
| gallery | FUTURE | Image filter + overlay applicable |
| image | FUTURE | Filter + overlay applicable |
| featuregrid | FUTURE | Card hover applicable |
| postcard | FUTURE | Card hover applicable |
| pricingcard | FUTURE | Card hover applicable |
| testimonial | FUTURE | Card hover applicable |

## 7. Preview / Blade Parity

| Feature | Preview (React) | Blade (PHP) |
|---------|----------------|-------------|
| Hover lift/scale | onMouseEnter/Leave + inline styles | Scoped CSS :hover rule |
| Image filter | inline filter CSS | inline filter CSS |
| Overlay | absolute div with opacity | absolute div with opacity |
| Shadow values | SHADOW_MAP constant | SHADOW_VALUES constant |
| Presets | HOVER_PRESETS | HOVER_PRESETS (identical) |
| Reduced motion | N/A (editor) | @media(prefers-reduced-motion) |

## 8. Validation / Sanitization

- Presets: allowlisted enums
- Scale: clamped 1.0–1.2
- TranslateY: clamped -40–0
- Duration: clamped 100–1000ms
- Filter values: clamped to safe ranges
- Colors: regex validated (#hex or rgba())
- Opacity: clamped 0–100
- Blend modes: allowlisted
- No raw CSS injection

## 9. Manual Acceptance Checklist

### Post Grid Hover
- [ ] Open page with Post Grid, select block
- [ ] Enable Card Effects
- [ ] Enable Hover Effect, select "Soft Pop"
- [ ] Hover card in editor preview → card lifts + shadow appears
- [ ] Disable → hover returns to normal
- [ ] Save, view published page
- [ ] Hover card → same effect on frontend

### Image Filters
- [ ] Enable Image Filter, select "Black & White"
- [ ] Images become grayscale in editor
- [ ] Save, view published page → images grayscale
- [ ] Switch to "Sepia" → sepia visible
- [ ] Switch to "Custom" → sliders appear, adjust

### Color Overlay
- [ ] Enable overlay, set blue color, 40% opacity
- [ ] Blue overlay visible over images in editor
- [ ] Save, view published page → overlay visible
- [ ] Disable overlay → disappears

### Regression
- [ ] Card links still clickable (pointer-events:none on overlay)
- [ ] Images still load correctly
- [ ] Grid layout not broken
- [ ] Mobile layout acceptable
- [ ] Reduced motion respected on frontend
- [ ] Build passes

## 10. Known Limitations
- Hover effect in editor uses JS onMouseEnter; on frontend uses CSS :hover
- prefers-reduced-motion only enforced on frontend, not editor preview
- Overlay blend modes may render differently across browsers
- Only Post Grid fully adopted in this slice; other blocks need separate adoption

## 11. Future Adoption Plan
Wire CardEffectsPanel into each eligible block's Editor, add effects rendering
to their Blade templates, and add BlockEffects::validationRules() merge.

## Files

| File | Purpose |
|------|---------|
| `resources/admin/src/lib/blockEffects.ts` | Shared effects helpers (TS) |
| `resources/admin/src/components/editor/fields/CardEffectsPanel.tsx` | Reusable UI panel |
| `app/Support/Blocks/BlockEffects.php` | Blade rendering helpers (PHP) |
| `resources/admin/src/components/blocks/postgrid/Editor.tsx` | Post Grid wiring |
| `resources/admin/src/components/blocks/postgrid/Preview.tsx` | Post Grid preview |
| `resources/views/blocks/postgrid.blade.php` | Post Grid frontend |
| `app/Domain/Blocks/Definitions/PostgridBlockDefinition.php` | Validation |
