# BLOCK-EFFECTS-2 — Image Hover Reveal: Filtered to Original

## 1. Purpose
Images appear filtered (grayscale, sepia, etc.) by default and reveal the
original full-color image on hover with configurable transition modes.

## 2. Schema

```json
{
  "effects": {
    "imageHoverReveal": {
      "enabled": true,
      "mode": "fade",
      "duration": 500,
      "easing": "ease-out"
    }
  }
}
```

Requires `effects.enabled` and `effects.imageFilter.enabled` to be active.

## 3. Reveal Modes

| Mode | Effect |
|------|--------|
| none | No reveal |
| fade | Opacity crossfade to original |
| reveal-left | Clip slides left to right |
| reveal-right | Clip slides right to left |
| reveal-top | Clip slides top to bottom |
| reveal-bottom | Clip slides bottom to top |
| circle | Circle shrinks from center |
| diagonal | Diagonal wipe |

## 4. Rendering Strategy
- Original image below (no filter, keeps `alt` text)
- Filtered duplicate on top (`alt=""`, `aria-hidden="true"`)
- Filtered layer has CSS `filter` + `clip-path`/`opacity`
- On hover: filtered layer fades or clips away, revealing original
- Only rendered when reveal is enabled (no duplicate image otherwise)

## 5. Accessibility
- Original image keeps proper `alt` text
- Filtered duplicate uses `alt=""` + `aria-hidden="true"`
- Overlay/filtered layers use `pointer-events: none`
- Links remain clickable

## 6. Editor Preview
- Filtered placeholder visible by default
- Hover over card: filtered layer transitions away (JS onMouseEnter)
- Changing mode updates immediately
- Disabling returns to normal filtered image

## 7. Blade / Frontend
- Scoped CSS per block instance (`.pgfx-HASH .img-reveal-filtered`)
- CSS `:hover` handles the transition
- `@media(prefers-reduced-motion)` disables transition

## 8. Adopted Blocks
- Post Grid: COMPLETE

## 9. Manual Acceptance Checklist

- [ ] Enable image filter (e.g. Black & White)
- [ ] Enable Image Hover Reveal
- [ ] Select "Fade to original"
- [ ] Hover image in editor → original appears
- [ ] Save, view frontend → same effect
- [ ] Test reveal-left, reveal-right, reveal-top, reveal-bottom
- [ ] Test circle reveal
- [ ] Test diagonal reveal
- [ ] Test duration change (150ms–1500ms)
- [ ] Test easing change
- [ ] Disable reveal → normal filter works
- [ ] Card hover pop still works alongside reveal
- [ ] Links remain clickable
- [ ] No duplicate alt text for screen readers
- [ ] Mobile layout not broken

## 10. Known Limitations
- Editor preview uses JS hover, frontend uses CSS :hover
- Circle/diagonal clip-path may not work in older browsers
- Reveal only works when image filter is enabled
- Only Post Grid adopted in this slice

## Files

| File | Change |
|------|--------|
| `resources/admin/src/lib/blockEffects.ts` | Added reveal types, helpers |
| `resources/admin/src/components/editor/fields/CardEffectsPanel.tsx` | Added reveal UI section |
| `app/Support/Blocks/BlockEffects.php` | Added reveal CSS/HTML methods, validation |
| `resources/admin/src/components/blocks/postgrid/Preview.tsx` | Layered reveal rendering |
| `resources/views/blocks/postgrid.blade.php` | Layered reveal rendering |
