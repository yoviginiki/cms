# Background System Audit

> **Date**: 2026-05-12
> **Status**: Audit complete. P0 fixes implemented.

---

## 1. Summary: Duplicated Background Systems

The Hero block has **two overlapping background systems** that create confusion:

### System A: Hero-specific BackgroundEditor (`block.data.bg_*`)

- Located in Hero Editor.tsx → `<BackgroundEditor data={data} onChange={...} />`
- Stores in `block.data`: `bg_type`, `bg_color`, `bg_image`, `bg_gradient_*`, `bg_overlay_*`, `bg_scroll_effect`
- **WORKING** in both Preview and Blade
- Has AssetPicker integration, overlay, scroll effects
- Professional gradient builder with color stops

### System B: Global VisualPanel (`block.style.visual`)

- Located in BlockSettings.tsx → "Background & borders" section
- Stores in `block.style.visual`: `backgroundColor`, `backgroundImage`, `backgroundGradient`, `borderWidth`, `borderColor`, `borderRadius`, `boxShadow`, `opacity`, `overflow`
- Applied by SortableBlock wrapper (Preview) and `BlockStyle::buildStyle()` (Blade)
- **Background fields are DEAD CONTROLS** — `backgroundColor`, `backgroundImage`, `backgroundGradient` are saved but NOT consumed by `buildBlockWrapperStyle()` or `BlockStyle::buildStyle()`
- **Border/radius/shadow/opacity ARE working** — consumed by both TS and PHP helpers

### The Confusion

Users see two places to set backgrounds:
1. Hero "Background" section (BackgroundEditor) — this works
2. "Background & borders" panel (VisualPanel) — background fields don't work, but border/shadow/opacity do

The background inputs in VisualPanel are **dead controls** that save data to `block.style.visual` but nothing reads or renders them. They are confusing because they appear functional.

---

## 2. Background Key Inventory

### Hero-specific keys (stored in `block.data`)

| Key | Editor | Preview | Blade | Validated | Status |
|-----|--------|---------|-------|-----------|--------|
| `bg_type` | BackgroundEditor | `buildBackgroundStyle()` | Blade inline style | `in:none,color,gradient,image` | **WORKING** |
| `bg_color` | BackgroundEditor | `buildBackgroundStyle()` | Blade inline style | regex CSS color | **WORKING** |
| `bg_image` | BackgroundEditor + InlineMediaReplace | `buildBackgroundStyle()` | Blade inline style | string max:2048 | **WORKING** |
| `bg_asset_id` | BackgroundEditor | — | Stored only (metadata) | string max:36 | **PARTIAL** (stored but not used in Blade rendering) |
| `bg_gradient_type` | BackgroundEditor | `buildBackgroundStyle()` | Blade inline style | `in:linear,radial` | **WORKING** |
| `bg_gradient_angle` | BackgroundEditor | `buildBackgroundStyle()` | Blade inline style | int 0-360 | **WORKING** |
| `bg_gradient_stops` | BackgroundEditor | `buildBackgroundStyle()` | Blade inline style | array with color/position | **WORKING** |
| `bg_image_size` | BackgroundEditor | `buildBackgroundStyle()` | Blade inline style | `in:cover,contain,auto` | **WORKING** |
| `bg_image_position` | BackgroundEditor | `buildBackgroundStyle()` | Blade inline style | regex | **WORKING** |
| `bg_image_repeat` | BackgroundEditor | `buildBackgroundStyle()` | Blade inline style | enum | **WORKING** |
| `bg_overlay_color` | BackgroundEditor | `buildOverlayStyle()` | Blade overlay div | regex hex | **WORKING** |
| `bg_overlay_opacity` | BackgroundEditor | `buildOverlayStyle()` | Blade overlay div | numeric 0-1 | **WORKING** |
| `bg_scroll_effect` | BackgroundEditor | `buildBackgroundStyle()` | Blade inline style | `in:none,fixed,parallax,zoom` | **PARTIAL** (only none/fixed rendered; parallax/zoom removed from UI) |
| `bg_parallax_speed` | BackgroundEditor | — | — | numeric 0.1-1 | **DEAD_CONTROL** |
| `backgroundImage` | — (legacy) | Legacy fallback | Legacy fallback | — | **LEGACY** |

### Global/BaseBlock keys (stored in `block.style.visual`)

| Key | Editor | Preview | Blade | Validated | Status |
|-----|--------|---------|-------|-----------|--------|
| `backgroundColor` | VisualPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| `backgroundImage` | VisualPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| `backgroundGradient` | VisualPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| `borderWidth` | VisualPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeDim()` | **WORKING** |
| `borderColor` | VisualPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeColor()` | **WORKING** |
| `borderStyle` | VisualPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | allowlist | **WORKING** |
| `borderRadius` | VisualPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeDim()` | **WORKING** |
| `boxShadow` | VisualPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | preset map | **WORKING** |
| `opacity` | VisualPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | clamped 0-1 | **RISK** — fades ALL content |
| `overflow` | VisualPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |

### Canonical key cross-reference

Some keys are referenced by different names across the spec. This table maps canonical names to actual implementation:

| Canonical Name | Actual Key | Location | Status |
|---------------|------------|----------|--------|
| `overlayColor` | `bg_overlay_color` | `block.data` | **WORKING** (Hero BackgroundEditor) |
| `overlayOpacity` | `bg_overlay_opacity` | `block.data` | **WORKING** (Hero BackgroundEditor) |
| `backgroundOpacity` | No dedicated key | — | **MISSING** — VisualPanel `opacity` fades whole block, not background only |
| `backgroundAttachment` | `bg_scroll_effect` | `block.data` | **PARTIAL** — `none`/`fixed` work; `parallax`/`zoom` not rendered |
| `backgroundPosition` | `bg_image_position` | `block.data` | **WORKING** (Hero BackgroundEditor) |
| `backgroundSize` | `bg_image_size` | `block.data` | **WORKING** (Hero BackgroundEditor) |
| `backgroundRepeat` | `bg_image_repeat` | `block.data` | **WORKING** (Hero BackgroundEditor) |
| `shadow` | `boxShadow` | `block.style.visual` | **WORKING** (preset-only: sm/md/lg) |

---

## 3. Source of Truth Recommendation

### Current architecture (keep for now)

- **Hero background**: owned by `block.data.bg_*` via BackgroundEditor
  - This is the professional, working system with asset picker, gradients, overlay, scroll effects
  - It writes to `block.data` (block-specific)
  - Preview reads it via `buildBackgroundStyle()`/`buildOverlayStyle()`
  - Blade reads it directly from `$data['bg_*']`

- **Global border/shadow/opacity**: owned by `block.style.visual` via VisualPanel
  - Border, radius, shadow work correctly
  - Opacity has a bug (see section 4)
  - Applied by SortableBlock wrapper and `BlockStyle::buildStyle()`

### What should NOT change (yet)

- Do not move Hero bg_* keys to block.style.visual — this would break all saved Hero blocks
- Do not make VisualPanel background fields override Hero background — they are currently dead controls

### Priority rules

1. Hero `block.data.bg_*` is the source of truth for Hero background
2. Global `block.style.visual` border/radius/shadow apply to the outer wrapper
3. VisualPanel background fields (backgroundColor, backgroundImage, backgroundGradient) should be **hidden or clearly labeled as not applicable for blocks with their own background system**

### Future direction

When BaseBlock background is fully implemented, it should:
- Replace VisualPanel's dead background controls with a working shared background system
- Blocks with their own background (Hero) should opt out of the shared system
- Migration from Hero bg_* to shared system would require careful data migration

---

## 4. Opacity Bug Analysis

### The Bug

VisualPanel "Opacity" slider (stored as `style.visual.opacity`) is applied to the **wrapper div** of every block. This means:

- Setting opacity to 0.5 fades **everything**: title, subtitle, CTA buttons, images
- The user likely expects "background opacity" but gets "whole block opacity"
- This is **not** the same as Hero's `bg_overlay_opacity` which correctly fades only the overlay div

### Where it happens

**Preview** (blockStyles.ts lines 70-72):
```typescript
if (vis.opacity !== undefined && vis.opacity < 1) {
  css.opacity = Math.max(0, Math.min(1, vis.opacity));
}
```
Applied to SortableBlock wrapper div → fades ALL children.

**Blade** (BlockStyle.php lines 133-136):
```php
if (isset($vis['opacity']) && (float) $vis['opacity'] < 1) {
    $parts[] = "opacity:{$op}";
}
```
Applied to section wrapper element → fades ALL children.

### Hero's correct overlay opacity (for comparison)

Hero uses a **separate div** for overlay opacity:
```html
<div style="position:absolute;inset:0;background-color:#000;opacity:0.5;pointer-events:none;z-index:0;"></div>
```
This only fades the overlay, not the text. This is the correct pattern.

### Recommended fix

**P0**: Rename the VisualPanel label from "Opacity" to "Block Opacity (affects all content)" to set correct expectations. This is a UI label change only.

**P1**: If background-only opacity is needed globally:
- Add a separate background layer div (like Hero's overlay) in the SortableBlock wrapper
- Apply opacity to that layer only
- Keep "Block Opacity" as an advanced option

---

## 5. Shadow Control Analysis

### Current implementation

| Aspect | Value |
|--------|-------|
| Key | `style.visual.boxShadow` |
| UI | 4 preset buttons: None, SM, MD, LG |
| Preview | `SHADOW_MAP` → CSS string | 
| Blade | `BlockStyle::safeShadow()` → CSS string |
| Validation | Preset allowlist only |

### Shadow presets

| Preset | CSS Value |
|--------|-----------|
| `sm` | `0 1px 2px rgba(0,0,0,0.04)` |
| `md` | `0 4px 12px rgba(0,0,0,0.06)` |
| `lg` | `0 12px 32px rgba(0,0,0,0.10)` |

### Assessment

- Presets are **safe** (allowlisted, no raw CSS)
- Presets are **limited** (no color control, no inset, no custom blur/spread)
- No user can inject unsafe CSS through shadow presets

### Recommended improvement

**Phase 1 (safe, recommended first)**:
Add more presets without changing the model:
- `subtle` — very light shadow
- `glow` — colored glow effect (e.g., `0 0 20px rgba(59,130,246,0.3)`)
- `inner` — inset shadow

**Phase 2 (advanced, later)**:
Add individual shadow controls:
- `shadowX`, `shadowY` — offset
- `shadowBlur` — blur radius
- `shadowSpread` — spread
- `shadowColor` — color
- `shadowOpacity` — opacity of shadow color
- `shadowInset` — boolean

This requires new validation regex and careful sanitization. Defer to Phase 2.

---

## 6. Scroll / Fixed / Parallax Analysis

### Current support

| Effect | Key | Editor UI | Preview | Blade | Status |
|--------|-----|-----------|---------|-------|--------|
| Normal scroll | `bg_scroll_effect: 'none'` | BackgroundEditor | Yes | Yes | **WORKING** |
| Fixed | `bg_scroll_effect: 'fixed'` | BackgroundEditor | `backgroundAttachment: 'fixed'` | `background-attachment:fixed` | **WORKING** |
| Parallax | `bg_scroll_effect: 'parallax'` | Removed from UI | NOT rendered | NOT rendered | **DEAD_CONTROL** |
| Zoom | `bg_scroll_effect: 'zoom'` | Removed from UI | NOT rendered | NOT rendered | **DEAD_CONTROL** |

### Assessment

- **Normal scroll**: works correctly
- **Fixed**: works correctly in both Preview and Blade
- **Parallax**: removed from AnimationPanel UI but still in BackgroundEditor type. Would need JS (IntersectionObserver + transform) to implement
- **Zoom**: removed from UI, would need JS scroll handler

### Recommendation

- **Phase 1**: Normal and Fixed are already working. No changes needed.
- **Phase 2**: Parallax with CSS `transform: translateY()` + IntersectionObserver on published pages. Safe and performant.
- **Phase 3**: Zoom and advanced scroll effects.

---

## 7. Preview vs Blade Mismatch Table (Background-Related)

| Property | Preview Source | Blade Source | Match? |
|----------|--------------|-------------|--------|
| Background color (Hero) | `buildBackgroundStyle()` from `bg_color` | `$cssVal($data['bg_color'])` | **YES** |
| Background image (Hero) | `buildBackgroundStyle()` from `bg_image` | `$cssUrl($data['bg_image'])` | **YES** |
| Background gradient (Hero) | `buildBackgroundStyle()` from `bg_gradient_*` | Blade inline gradient | **YES** |
| Overlay color/opacity | `buildOverlayStyle()` overlay div | Blade overlay div | **YES** |
| Global border | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | **YES** |
| Global shadow | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | **YES** |
| Global opacity | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | **YES** (both have the bug) |
| Global backgroundColor | NOT consumed | NOT consumed | **N/A** (dead control) |
| Global backgroundImage | NOT consumed | NOT consumed | **N/A** (dead control) |

---

## 8. Recommended Fix Plan

### P0: Immediate Safety & Parity Fixes

1. ~~**Define source of truth**~~: Hero `block.data.bg_*` is the background source. VisualPanel background fields are dead controls — **DONE**: helper text added to VisualPanel.
2. ~~**Fix opacity bug**~~: Wrapper opacity no longer applied to block elements in Preview or Blade. Label renamed to "Block Opacity" with warning text. Opacity value preserved in saved data for future background-layer implementation. **DONE**.
3. ~~**Add helper text**~~ to VisualPanel dead background fields — **DONE**: added note explaining blocks with own background use their own controls.
4. ~~**Verify Preview/Blade alignment**~~ — confirmed: Hero bg_* system matches between Preview and Blade. **DONE**.
5. ~~**Preserve legacy fallback**~~ — `backgroundImage` key continues to work for old saved Hero data. **DONE** (unchanged).
6. ~~**Verify scroll/fixed**~~ works end-to-end — confirmed: normal scroll and fixed attachment both work. **DONE**.

### P1: Background UX Improvements

7. **Hide dead background controls** in VisualPanel for blocks that have their own background (Hero, etc.) — or split VisualPanel into "Border & Shadow" and "Background" sections
8. ~~**Add shadow presets**~~ — **DONE**: Hero has section-level shadow presets (subtle/medium/large/glow) in Editor, Preview, and Blade
9. ~~**Verify border/radius/shadow parity**~~ — **DONE**: Hero has section-level border (width/color/style/radius) matching between Preview and Blade
10. **Improve overlay controls** — overlay color picker + opacity slider already in BackgroundEditor

### P2: Advanced Features

7. **Advanced shadow builder** — individual X/Y/blur/spread/color/inset controls with validation
8. **Background focal point** — click-to-set position on image
9. **Responsive background** — different image/position for mobile
10. **Background blend modes** — `mix-blend-mode` presets

### P3: Future

11. **Parallax** — JS-based transform on scroll
12. **Video background** — `<video>` element with poster frame
13. **Unified background system** — migrate Hero bg_* to shared system with opt-in/opt-out
