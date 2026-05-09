# Block Properties Audit

## Architecture Overview

### How Block Properties Are Stored

Block properties use a dual-storage system:
- **Content data** (`block.data`): Block-specific content fields (title, subtitle, etc.)
- **Style properties** (`block.style`): Shared visual/layout properties managed by property panels
- **Animation** (`block.data.__animation`): Entrance animation settings
- **Responsive** (`block.data.__responsive`): Device visibility/overrides
- **Advanced** (`block.data.__advanced`): Custom CSS class, custom CSS, HTML ID, ARIA label

BlockService stores style in both the `style` JSON column and as `data.__style` for backward compatibility. On read, properties are restored to separate top-level fields.

### How Properties Are Edited

BlockSettings.tsx renders expandable property panels for every selected block:
- **Content** — block-specific Editor component
- **Typography** — TypographyPanel (font, size, weight, alignment, color)
- **Spacing** — SpacingPanel (margin, padding, gap presets)
- **Background & borders** — VisualPanel (bg color, gradient, image, border, radius, shadow, opacity)
- **Layout** — LayoutPanel (width, max-width, min-height, display, flex, alignment, z-index)
- **Animation** — AnimationPanel (entrance: fade/slide-up/slide-left/slide-right/zoom, duration, delay, trigger, hover effects)
- **Responsive** — ResponsivePanel (hide on desktop/tablet/mobile)
- **Advanced** — AdvancedPanel (custom class, custom CSS, HTML ID, ARIA label)

### How Properties Should Be Applied

**BlockStyleResolver** (`app/Domain/Publishing/Services/BlockStyleResolver.php`) exists and can:
- `resolveInlineStyle(style)` — convert all style props to CSS inline style string
- `resolveAnimationStyle(animation)` — generate animation-duration/delay/fill-mode CSS
- `resolveClasses(style, advanced)` — generate CSS class string including customClass
- `isHiddenOnDevice(responsive, device)` — check responsive visibility

### Current Status: BROKEN PIPELINE

**The property pipeline is broken at two critical points:**

#### 1. Editor Preview Does Not Apply Global Properties

`SortableBlock.tsx` renders each block's Preview component without applying `block.style`, `block.animation`, `block.responsive`, or `block.advanced` to the wrapper. The Preview component receives `block` but individual Preview components (like hero/Preview.tsx) only read `block.data` — they don't read `block.style`.

**Result**: Setting spacing, borders, shadow, animation, layout, or global background via the property panels has NO visible effect in the editor canvas.

#### 2. Published Blade Output Does Not Apply Global Properties

`BuildPageService::renderBlock()` only passes `$data` (sanitized block data) to Blade templates. It does NOT pass `$block->style`, animation, responsive, or advanced data. BlockStyleResolver is never called during rendering.

**Result**: All global property panel settings are saved to the database but completely ignored in published output.

#### 3. Animation System Has No CSS

No CSS `@keyframes` are defined for: `fade`, `slide-up`, `slide-left`, `slide-right`, `zoom`. BlockStyleResolver generates `animation-duration` and `animation-delay` CSS but never sets `animation-name`. Even if the pipeline was connected, animations would not work.

#### 4. Hover Effects Not Implemented

AnimationPanel exposes opacity/lift/glow hover effects, but no CSS or JavaScript implements them anywhere.

---

## Shared Property Inventory

| Property | Panel | Data Path | Editor Preview | Published Blade | Validation | Status |
|----------|-------|-----------|---------------|----------------|------------|--------|
| **Typography** | | | | | | |
| Font family | TypographyPanel | style.typography.fontFamily | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Font size | TypographyPanel | style.typography.fontSize | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Font weight | TypographyPanel | style.typography.fontWeight | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Line height | TypographyPanel | style.typography.lineHeight | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Letter spacing | TypographyPanel | style.typography.letterSpacing | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Text align | TypographyPanel | style.typography.textAlign | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Text transform | TypographyPanel | style.typography.textTransform | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Text color | TypographyPanel | style.typography.textColor | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Paragraph spacing | TypographyPanel | style.typography.paragraphSpacingAfter | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| **Spacing** | | | | | | |
| Margin (T/R/B/L) | SpacingPanel | style.spacing.margin* | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Padding (T/R/B/L) | SpacingPanel | style.spacing.padding* | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Gap | SpacingPanel | style.spacing.gap | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| **Visual / Background** | | | | | | |
| Background color | VisualPanel | style.visual.backgroundColor | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Background gradient | VisualPanel | style.visual.backgroundGradient | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Background image | VisualPanel | style.visual.backgroundImage | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Border width/color/style | VisualPanel | style.visual.border* | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Border radius | VisualPanel | style.visual.borderRadius | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Box shadow | VisualPanel | style.visual.boxShadow | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Opacity | VisualPanel | style.visual.opacity | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Overflow | VisualPanel | style.visual.overflow | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| **Layout** | | | | | | |
| Width / max-width | LayoutPanel | style.layout.width/maxWidth | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Min height | LayoutPanel | style.layout.minHeight | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Alignment | LayoutPanel | style.layout.alignment | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Display | LayoutPanel | style.layout.display | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Flex direction | LayoutPanel | style.layout.flexDirection | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Justify content | LayoutPanel | style.layout.justifyContent | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Align items | LayoutPanel | style.layout.alignItems | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Z-index | LayoutPanel | style.layout.zIndex | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| **Animation** | | | | | | |
| Entrance (fade/slide/zoom) | AnimationPanel | animation.entrance | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Duration | AnimationPanel | animation.duration | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Delay | AnimationPanel | animation.delay | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Trigger | AnimationPanel | animation.trigger | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| Hover effect | AnimationPanel | animation.hoverEffect | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| **Responsive** | | | | | | |
| Hide on device | ResponsivePanel | responsive.hideOn | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| **Advanced** | | | | | | |
| Custom class | AdvancedPanel | advanced.customClass | NOT APPLIED | NOT APPLIED | Escaped | DEAD_CONTROL |
| Custom CSS | AdvancedPanel | advanced.customCss | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| HTML ID | AdvancedPanel | advanced.htmlId | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |
| ARIA label | AdvancedPanel | advanced.ariaLabel | NOT APPLIED | NOT APPLIED | None | DEAD_CONTROL |

**Total dead controls: 35+**

---

## Hero Property Matrix

Hero has TWO background systems:
1. **Block-specific BackgroundEditor** (bg_type, bg_color, bg_image, etc.) — stored in `block.data` — WORKING
2. **Global VisualPanel** (style.visual.backgroundColor, etc.) — stored in `block.style` — DEAD_CONTROL

| Property | Editor UI | Preview | Blade | Validated | Status |
|----------|----------|---------|-------|-----------|--------|
| **Content (block.data)** | | | | | |
| title | TextField | Yes | Yes | Yes (max:255) | WORKING |
| subtitle | TextField | Yes | Yes | Yes (max:500) | WORKING |
| ctaText | TextField | Yes | Yes | Yes (max:100) | WORKING |
| ctaUrl | TextField | Yes | Yes | Yes (regex+not_regex) | WORKING |
| alt | TextField | Yes (aria) | Yes (aria-label) | Yes (max:255) | WORKING |
| **Background (block.data via BackgroundEditor)** | | | | | |
| bg_type | BackgroundEditor | Yes | Yes | Yes (enum) | WORKING |
| bg_color | BackgroundEditor | Yes | Yes | Yes (regex) | WORKING |
| bg_gradient_type | BackgroundEditor | Yes | Yes | Yes (enum) | WORKING |
| bg_gradient_angle | BackgroundEditor | Yes | Yes | Yes (int 0-360) | WORKING |
| bg_gradient_stops | BackgroundEditor | Yes | Yes | Yes (array+regex) | WORKING |
| bg_image | BackgroundEditor | Yes | Yes | Yes (max:2048) | WORKING |
| bg_image_size | BackgroundEditor | Yes | Yes | Yes (enum) | WORKING |
| bg_image_position | BackgroundEditor | Yes | Yes | Yes (regex) | WORKING |
| bg_image_repeat | BackgroundEditor | Yes | Yes | Yes (enum) | WORKING |
| bg_overlay_color | BackgroundEditor | Yes | Yes | Yes (regex) | WORKING |
| bg_overlay_opacity | BackgroundEditor | Yes | Yes | Yes (0-1) | WORKING |
| bg_scroll_effect | BackgroundEditor | Yes | Yes | Yes (enum) | WORKING |
| bg_parallax_speed | BackgroundEditor | Yes | Yes | Yes (0.1-1) | WORKING |
| **Global style properties (block.style)** | | | | | |
| Spacing (padding/margin) | SpacingPanel | No | No | No | DEAD_CONTROL |
| Background color | VisualPanel | No | No | No | DEAD_CONTROL |
| Background gradient | VisualPanel | No | No | No | DEAD_CONTROL |
| Border/radius/shadow | VisualPanel | No | No | No | DEAD_CONTROL |
| Opacity | VisualPanel | No | No | No | DEAD_CONTROL |
| Animation entrance | AnimationPanel | No | No | No | DEAD_CONTROL |
| Animation delay/duration | AnimationPanel | No | No | No | DEAD_CONTROL |
| Hover effects | AnimationPanel | No | No | No | DEAD_CONTROL |
| Responsive hide | ResponsivePanel | No | No | No | DEAD_CONTROL |
| Custom class | AdvancedPanel | No | No | No | DEAD_CONTROL |
| Custom CSS | AdvancedPanel | No | No | No | DEAD_CONTROL |
| Layout width/height | LayoutPanel | No | No | No | DEAD_CONTROL |
| Typography overrides | TypographyPanel | No | No | No | DEAD_CONTROL |

---

## Dead Controls Summary

**ALL global property panel controls are dead controls.** The data is saved correctly to the database, but:
- Editor Preview (SortableBlock.tsx) does not apply block.style to the preview wrapper
- Published output (BuildPageService.renderBlock()) does not pass style/animation/responsive/advanced to Blade
- Animation CSS keyframes do not exist
- Hover effect CSS does not exist

This affects ALL 69 block types, not just Hero.

---

## Global Properties Contract

### How shared properties MUST be implemented (future fix):

#### 1. Editor Preview
SortableBlock.tsx must apply global properties to a wrapper div around each block's Preview:
- Read `block.style` and convert to inline CSS (mirror BlockStyleResolver logic)
- Apply animation classes/data attributes
- Apply responsive visibility (hide in editor if hidden on current viewport)
- Apply advanced.customClass to wrapper

#### 2. Published Blade Output
Two options:
- **Option A (recommended):** Create a `<x-block-wrapper>` Blade component that wraps every block, reads style/animation/responsive/advanced from the block, and applies them
- **Option B:** Modify BuildPageService.renderBlock() to generate a wrapper div with inline styles, pass it to each Blade template

#### 3. Animation CSS
Define @keyframes in the published CSS:
```css
@keyframes block-fade { from { opacity: 0; } to { opacity: 1; } }
@keyframes block-slide-up { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
@keyframes block-slide-left { from { opacity: 0; transform: translateX(-30px); } to { opacity: 1; transform: translateX(0); } }
@keyframes block-slide-right { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }
@keyframes block-zoom { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
```

#### 4. Scroll Trigger
Add Intersection Observer JS to trigger animations on scroll into view.

#### 5. Validation
All style values must be validated/sanitized before rendering in published output:
- CSS colors: regex validation
- CSS dimensions: whitelist units
- CSS gradients: parsed and reconstructed
- Custom class: HTML-escaped
- Custom CSS: scoped or disabled
- URLs in background-image: safe URL validation

#### 6. No Dead Controls Rule
If a property panel control is visible, it MUST either:
- Work in both preview and published output
- Be explicitly disabled/hidden with a "coming soon" indicator
- Be removed from the UI

---

## Required Fixes (Priority Order)

### P0: Dead Controls (visible setting does nothing)
1. **Connect SortableBlock wrapper** — Apply block.style as inline CSS to preview wrapper div
2. **Connect BuildPageService** — Pass style/animation/responsive/advanced to Blade, or create block wrapper component
3. **Define animation keyframes** — Add CSS for fade, slide-up, slide-left, slide-right, zoom
4. **Implement scroll trigger** — Add Intersection Observer for on-scroll animations

### P1: Preview and Blade mismatch
5. **Hero has dual background systems** — Global VisualPanel bg vs block-specific BackgroundEditor bg. Must decide: merge, disable global bg for blocks with custom bg, or document the dual system

### P2: Missing validation/sanitization
6. **Global style values unvalidated** — No regex/whitelist validation for CSS values from property panels
7. **Custom CSS injection risk** — advanced.customCss could inject arbitrary CSS
8. **Background image URL** — VisualPanel accepts raw URL without validation

### P3: UX Polish
9. **Hover effects** — Implement or remove from AnimationPanel
10. **Responsive overrides** — Only hideOn is partially defined; no responsive style override capability
11. **Custom CSS scoping** — If custom CSS is kept, scope it to the block

---

## P0 Fix Status (Hero-first implementation)

### What was fixed

The shared property pipeline was connected end-to-end:

#### Editor Preview (all blocks)
- **SortableBlock.tsx** now applies `block.style`, `block.animation`, and `block.advanced` to a wrapper div around every block Preview
- Helper module `resources/admin/src/lib/blockStyles.ts` provides:
  - `buildBlockWrapperStyle()` — spacing, border, radius, shadow, opacity
  - `buildAnimationStyle()` — entrance animation with duration/delay
  - `buildBlockClasses()` — sanitized custom class
- CSS `@keyframes` for `block-fade`, `block-slide-up`, `block-slide-left`, `block-slide-right`, `block-zoom` added to `index.css`

#### Published Blade (all blocks)
- **BuildPageService::renderBlock()** now passes `$blockStyle`, `$blockAnimation`, `$blockAdvanced` to every Blade template
- Animation `@keyframes` added to `buildCriticalCss()` so they're available in published output
- Respects `prefers-reduced-motion: reduce`

#### Hero Blade (Hero-specific)
- Hero Blade reads `$blockStyle`, `$blockAnimation`, `$blockAdvanced` and applies:
  - Spacing (padding/margin) with `cssDim()` sanitization
  - Border (width/color/style) with safe validation
  - Border radius with `cssDim()` sanitization
  - Shadow presets (sm/md/lg)
  - Opacity
  - Animation name/duration/delay with `data-animation` attribute
  - Custom class (sanitized to safe tokens)
  - HTML ID (sanitized)
  - ARIA label

### Properties now WORKING for Hero

| Property | Preview | Blade | Status |
|----------|---------|-------|--------|
| Spacing (padding/margin) | Yes (via SortableBlock wrapper) | Yes | WORKING |
| Border width/color/style | Yes (via wrapper) | Yes | WORKING |
| Border radius | Yes (via wrapper) | Yes | WORKING |
| Shadow (sm/md/lg) | Yes (via wrapper) | Yes | WORKING |
| Opacity | Yes (via wrapper) | Yes | WORKING |
| Animation entrance (fade/slide/zoom) | Yes (via wrapper) | Yes | WORKING |
| Animation duration | Yes | Yes | WORKING |
| Animation delay | Yes | Yes | WORKING |
| Custom class | Yes (via wrapper) | Yes | WORKING |
| HTML ID | N/A (preview) | Yes | WORKING |
| ARIA label | N/A (preview) | Yes | WORKING |

### Properties still PARTIAL or DEAD_CONTROL

| Property | Status | Reason |
|----------|--------|--------|
| Animation trigger (on-scroll) | PARTIAL | on-load works; on-scroll needs Intersection Observer JS (not implemented) |
| Hover effects (opacity/lift/glow) | DEAD_CONTROL | No CSS/JS implementation |
| Responsive hide on device | DEAD_CONTROL | Not connected in SortableBlock or Blade |
| Custom CSS | DEAD_CONTROL | Security risk — not rendered |
| Global VisualPanel bg color/gradient/image | DEAD_CONTROL for Hero | Hero uses its own BackgroundEditor; global bg ignored |
| Typography panel overrides | DEAD_CONTROL | Not applied in SortableBlock wrapper or Blade |
| Layout panel (width/height/display) | DEAD_CONTROL | Not applied in SortableBlock wrapper |

### Pattern for other blocks

Other Blade templates can now access `$blockStyle`, `$blockAnimation`, `$blockAdvanced` — they just need to apply them. The Hero Blade implementation serves as the reference. The SortableBlock wrapper already applies shared properties to ALL block previews automatically.

### This fix is Hero-first
- SortableBlock + BuildPageService changes benefit all blocks
- Only Hero Blade currently renders the shared properties
- Other blocks will gain Blade support as they're repaired per the Block Quality Contract
