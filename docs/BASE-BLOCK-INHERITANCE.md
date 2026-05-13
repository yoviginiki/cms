# BaseBlock Inheritance Model

> **Date**: 2026-05-11
> **Status**: Architecture defined. Staged adoption in progress.

---

## 1. Overview

The CMS defines a set of **global shared properties** via the BaseBlock system. These properties are managed by shared editor panels (Spacing, Visual, Animation, Responsive, Advanced). In the **editor preview**, they are applied automatically to all blocks via the SortableBlock wrapper. In **published Blade output**, each block must explicitly opt in by calling `BlockStyle` helpers — currently only Hero has done this (Level 2). All other blocks are at Level 1 (preview only).

**BaseBlock properties** are distinct from **block-specific properties**:

| Category | Examples | Where stored | Editor UI | Rendered by |
|----------|----------|-------------|-----------|-------------|
| **BaseBlock (global)** | padding, margin, border, shadow, animation, customClass | `block.style`, `block.animation`, `block.responsive`, `block.advanced` | Shared panels in BlockSettings | SortableBlock wrapper (Preview) + `BlockStyle::buildStyle()` (Blade) |
| **Block-specific** | Hero title, Hero bg_image, Hero ctaText, Button text | `block.data` | Block's own Editor.tsx | Block's own Preview.tsx + block Blade template |

**Key principle**: BaseBlock properties control the **outer container** of a block (spacing around it, border, shadow, animation). Block-specific properties control the **inner content** (text, images, layout within the block).

---

## 2. Global Property Set

### 2a. Spacing (via SpacingPanel → `block.style.spacing`)

| Property | Data Key | Editor Control | Preview Wrapper | Blade Helper | Validation | Status |
|----------|---------|---------------|----------------|-------------|------------|--------|
| Padding Top | `style.spacing.paddingTop` | SpacingPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeDim()` | **WORKING** |
| Padding Right | `style.spacing.paddingRight` | SpacingPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeDim()` | **WORKING** |
| Padding Bottom | `style.spacing.paddingBottom` | SpacingPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeDim()` | **WORKING** |
| Padding Left | `style.spacing.paddingLeft` | SpacingPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeDim()` | **WORKING** |
| Margin Top | `style.spacing.marginTop` | SpacingPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeDim()` | **WORKING** |
| Margin Right | `style.spacing.marginRight` | SpacingPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeDim()` | **WORKING** |
| Margin Bottom | `style.spacing.marginBottom` | SpacingPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeDim()` | **WORKING** |
| Margin Left | `style.spacing.marginLeft` | SpacingPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeDim()` | **WORKING** |
| Gap | `style.spacing.gap` | SpacingPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |

### 2b. Visual (via VisualPanel → `block.style.visual`)

| Property | Data Key | Editor Control | Preview Wrapper | Blade Helper | Validation | Status |
|----------|---------|---------------|----------------|-------------|------------|--------|
| Border Width | `style.visual.borderWidth` | VisualPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeDim()` | **WORKING** |
| Border Color | `style.visual.borderColor` | VisualPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeColor()` | **WORKING** |
| Border Style | `style.visual.borderStyle` | VisualPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | allowlist | **WORKING** |
| Border Radius | `style.visual.borderRadius` | VisualPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | `safeDim()` | **WORKING** |
| Box Shadow | `style.visual.boxShadow` | VisualPanel | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | preset allowlist (sm/md/lg) | **WORKING** — advanced custom shadow builder planned as P2, see `docs/ADVANCED-SHADOW-BUILDER-AUDIT.md` |
| Opacity | `style.visual.opacity` | VisualPanel | DISABLED (was fading text) | DISABLED (was fading text) | clamped 0–1 | **DISABLED** — wrapper opacity fades all content; value saved but not rendered |
| Background Color | `style.visual.backgroundColor` | VisualPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| Background Image | `style.visual.backgroundImage` | VisualPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| Background Gradient | `style.visual.backgroundGradient` | VisualPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| Overflow | `style.visual.overflow` | VisualPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |

**Note**: Background properties are dead controls because blocks handle their own backgrounds via block-specific fields (e.g., Hero's BackgroundEditor). The global VisualPanel background would conflict with block-specific backgrounds.

### 2c. Animation (via AnimationPanel → `block.animation`)

| Property | Data Key | Editor Control | Preview Wrapper | Blade Helper | Validation | Status |
|----------|---------|---------------|----------------|-------------|------------|--------|
| Entrance | `animation.entrance` | AnimationPanel | `buildAnimationStyle()` | `BlockStyle::buildStyle()` | allowlist (8 types) | **WORKING** |
| Duration | `animation.duration` | AnimationPanel | `buildAnimationStyle()` | `BlockStyle::buildStyle()` | clamped 50–3000ms | **WORKING** |
| Delay | `animation.delay` | AnimationPanel | `buildAnimationStyle()` | `BlockStyle::buildStyle()` | clamped 0–5000ms | **WORKING** |
| Easing | `animation.easing` | AnimationPanel | `buildAnimationStyle()` | `BlockStyle::buildStyle()` | allowlist (5 values) | **WORKING** |
| Trigger | `animation.trigger` | AnimationPanel (disabled) | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| Hover Effect | `animation.hoverEffect` | Removed from UI | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |

### 2d. Responsive (via ResponsivePanel → `block.responsive`)

| Property | Data Key | Editor Control | Preview Wrapper | Blade Helper | Validation | Status |
|----------|---------|---------------|----------------|-------------|------------|--------|
| Hide On Device | `responsive.hideOn` | ResponsivePanel | Warning badges | `BlockStyle::buildHideOnCss()` | allowlist | **WORKING** |
| Tablet Overrides | `responsive.tablet` | ResponsivePanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| Mobile Overrides | `responsive.mobile` | ResponsivePanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |

**Note**: Block-specific responsive overrides (e.g., Hero's `data.responsive.tablet.textAlignment`) are separate from these shared responsive overrides. See `docs/RESPONSIVE-OVERRIDES.md`.

### 2e. Advanced (via AdvancedPanel → `block.advanced`)

| Property | Data Key | Editor Control | Preview Wrapper | Blade Helper | Validation | Status |
|----------|---------|---------------|----------------|-------------|------------|--------|
| Custom Class | `advanced.customClass` | AdvancedPanel | `buildBlockClasses()` | `BlockStyle::buildClasses()` | `safeClass()` | **WORKING** |
| HTML ID | `advanced.htmlId` | AdvancedPanel | NOT in wrapper | Per-block Blade | `safeId()` | **PARTIAL** |
| ARIA Label | `advanced.ariaLabel` | AdvancedPanel | NOT in wrapper | Per-block Blade | `e()` escaping | **PARTIAL** |
| Custom CSS | `advanced.customCss` | AdvancedPanel | NOT consumed | NOT consumed | BLOCKED (security) | **DEAD_CONTROL** |

### 2f. Layout (via LayoutPanel → `block.style.layout`)

| Property | Data Key | Editor Control | Preview Wrapper | Blade Helper | Validation | Status |
|----------|---------|---------------|----------------|-------------|------------|--------|
| Width | `style.layout.width` | LayoutPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| Max Width | `style.layout.maxWidth` | LayoutPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| Min Height | `style.layout.minHeight` | LayoutPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| Alignment | `style.layout.alignment` | LayoutPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| Z-Index | `style.layout.zIndex` | LayoutPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| Display | `style.layout.display` | LayoutPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |
| Flex Direction | `style.layout.flexDirection` | LayoutPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |

**Note**: LayoutPanel is entirely deferred. Blocks handle their own layout via block-specific fields.

### 2g. Typography (via TypographyPanel → `block.style.typography`)

| Property | Data Key | Editor Control | Preview Wrapper | Blade Helper | Validation | Status |
|----------|---------|---------------|----------------|-------------|------------|--------|
| All typography properties | `style.typography.*` | TypographyPanel | NOT consumed | NOT consumed | — | **DEAD_CONTROL** |

**Note**: Blocks handle their own typography via block-specific fields (e.g., Hero's `headlineSize`, `headlineWeight`).

---

## 3. Global vs Block-Specific Distinction

### What BaseBlock controls (outer container)

- Padding/margin around the entire block section
- Border on the block wrapper
- Shadow on the block wrapper
- Entrance animation of the entire block
- Visibility (hide on device)
- Custom CSS class on the wrapper
- HTML ID and ARIA label on the wrapper

### What blocks control themselves (inner content)

- Hero title spacing (`mb-2` in Preview, `margin-bottom:1rem` in Blade — hardcoded)
- Hero subtitle spacing (`mb-5` in Preview, `margin-bottom:2rem` in Blade — hardcoded)
- Hero CTA group gap/margin (not configurable)
- Hero content box padding (`contentBoxPadding` — configurable)
- Hero content max width (`contentMaxWidth` — configurable)
- Hero background (BackgroundEditor — not the global VisualPanel)
- Button text, URL, style, size
- Heading text, level
- Card title spacing, card image, card description
- Gallery item gap, gallery layout columns
- Testimonial quote spacing, testimonial author styling
- All other block-specific content fields

### Why this separation exists

Blocks like Hero have their own background systems (BackgroundEditor with gradients, images, overlays) that would conflict with the global VisualPanel background. The same applies to layout and typography — each block has domain-specific layout needs that a generic panel cannot safely handle.

---

## 4. Inheritance / Adoption Levels

| Level | Name | What it means |
|-------|------|--------------|
| **Level 0** | None | Block is registered but does not use BlockStyle helpers. Shared panels save data but nothing renders. |
| **Level 1** | Preview Only | SortableBlock wrapper applies shared styles in editor preview. Published Blade does not use `BlockStyle::buildStyle()`. |
| **Level 2** | Preview + Blade | Both editor preview and published Blade use the shared helpers. Spacing, border, shadow, animation render correctly. |
| **Level 3** | Full Compliance | Level 2 + backend validation rules reference shared property constraints + documentation covers shared property support + tests verify rendering. |
| **Level 4** | Responsive | Level 3 + block supports per-breakpoint overrides for shared properties via responsive cascade. |

### Current adoption status

| Block | Level | Notes |
|-------|-------|-------|
| Hero | **Level 2** | Blade uses `BlockStyle::buildStyle()` + `buildClasses()` + `buildHideOnCss()` + `animationAttr()`. No shared property validation in HeroBlockDefinition (block-specific fields are validated). |
| All other blocks | **Level 1** | SortableBlock wrapper applies shared styles in preview. Blade templates do not use BlockStyle helpers. |

---

## 5. Architecture

### Frontend (Editor Preview)

```
BlockSettings.tsx
  ├── SpacingPanel    → writes block.style.spacing
  ├── VisualPanel     → writes block.style.visual
  ├── LayoutPanel     → writes block.style.layout (DEAD)
  ├── TypographyPanel → writes block.style.typography (DEAD)
  ├── AnimationPanel  → writes block.animation
  ├── ResponsivePanel → writes block.responsive
  └── AdvancedPanel   → writes block.advanced

SortableBlock.tsx (wrapper for ALL blocks)
  ├── buildBlockWrapperStyle(block.style) → spacing + visual inline CSS
  ├── buildAnimationStyle(block.animation) → animation inline CSS
  ├── buildBlockClasses(block.advanced)   → custom class
  └── hideOn badges (block.responsive.hideOn)
```

**Key files**:
- `resources/admin/src/lib/blockStyles.ts` — TS helpers
- `resources/admin/src/components/editor/SortableBlock.tsx` — wrapper component
- `resources/admin/src/components/editor/properties/*.tsx` — panel components

### Backend (Published Output)

```
BuildPageService.php
  └── renderBlock()
        ├── $sanitizedData = sanitizer->sanitizeBlock($block)
        ├── $blockStyle = $block->style ?? $sanitizedData['__style'] ?? []
        ├── $blockAnimation = $sanitizedData['__animation'] ?? []
        ├── $blockAdvanced = $sanitizedData['__advanced'] ?? []
        ├── $blockResponsive = $sanitizedData['__responsive'] ?? []
        └── passes all to Blade view

Blade template (e.g., hero.blade.php)
  ├── $sharedStyle = BlockStyle::buildStyle($blockStyle, $blockAnimation)
  ├── $customClass = BlockStyle::safeClass($blockAdvanced['customClass'] ?? '')
  ├── $htmlId = BlockStyle::safeId($blockAdvanced['htmlId'] ?? '')
  ├── $animAttr = BlockStyle::animationAttr($blockAnimation)
  ├── $hideOn = BlockStyle::buildHideOnCss($blockResponsive, $htmlId)
  └── Applies to <section> or root element
```

**Key files**:
- `app/Support/Blocks/BlockStyle.php` — PHP helpers
- `app/Domain/Publishing/Services/BuildPageService.php` — rendering service
- `resources/views/blocks/hero.blade.php` — reference Blade implementation

### Sanitization (both layers)

Both TS and PHP use equivalent allowlists with the same intent:
- **Dimensions**: `safeDim()` — regex `/^\d+(\.\d+)?(px|rem|em|%|vh|vw|auto|0)$/i`
- **Colors**: `safeColor()` — hex, rgb/rgba, hsl/hsla, oklch, named colors
- **Shadows**: preset map (sm/md/lg → CSS strings)
- **Animations**: allowlist of 8 entrance types
- **Easings**: allowlist of 5 values
- **Classes**: strip `[^a-zA-Z0-9_\-\s]`
- **IDs**: strip `[^a-zA-Z0-9_\-]`

---

## 6. Audit Strategy

### Proposed audit fields per block (PLANNED — not yet in `scripts/audit-blocks.mjs`)

| Field | Type | Description |
|-------|------|-------------|
| `hasBaseBlockPreviewSupport` | boolean | SortableBlock wrapper applies shared styles (true for ALL blocks automatically) |
| `hasBaseBlockBladeSupport` | boolean | Blade template uses `BlockStyle::buildStyle()` and helpers |
| `hasBaseBlockValidation` | boolean | PHP BlockDefinition validates shared property constraints |
| `hasBaseBlockDocs` | boolean | Documentation covers shared property support for this block |
| `baseBlockStatus` | enum | Overall adoption level |

### Status values

| Status | Meaning |
|--------|---------|
| `NONE` | Block exists but shared properties are not rendered in Blade |
| `PREVIEW_ONLY` | SortableBlock wrapper works but Blade doesn't use helpers (Level 1) |
| `RENDER_ONLY` | Blade uses helpers but no validation or docs |
| `PARTIAL` | Some helpers used but incomplete coverage |
| `COMPLETE` | Full Level 3 compliance |

### Current audit results

All 69 blocks have `hasBaseBlockPreviewSupport: true` because SortableBlock wrapper applies shared styles automatically. Only Hero has `hasBaseBlockBladeSupport: true`.

---

## 7. Migration / Backward Compatibility

### Rules

1. **No data migration required** — shared properties are stored in `block.style`/`block.animation`/`block.responsive`/`block.advanced`, which are separate from `block.data`. Old blocks without these fields simply have empty/undefined shared properties.
2. **No existing keys renamed** — all shared property keys are stable.
3. **Additive only** — new shared properties can be added to panels and helpers without breaking existing blocks.
4. **Blade adoption is opt-in** — each block's Blade template must explicitly call BlockStyle helpers. No automatic Blade behavior change.
5. **Dead controls are documented** — properties that save but don't render are marked as DEAD_CONTROL with the phase they'll be implemented in.

### Adoption path for new blocks

1. Create Blade template following the Hero reference pattern
2. Add `$sharedStyle`, `$customClass`, `$htmlId`, `$animAttr`, `$hideOn` variables
3. Apply to the root element's `class` and `style` attributes
4. Run `npm run blocks:audit` to verify frontend/Blade/PHP layer completeness (BaseBlock-specific audit fields are planned but not yet implemented in the audit script)
5. Document shared property support in block docs

---

## 8. What Is NOT Inherited

These are explicitly **not** part of BaseBlock inheritance:

- Block-specific content fields (`block.data.*`)
- Block-specific background systems (Hero's BackgroundEditor)
- Block-specific typography (Hero's headlineSize/headlineWeight)
- Block-specific inline editing (InlineTextField, InlineLinkPopover)
- Block-specific responsive overrides (`block.data.responsive.*`)
- Block-specific content box (Hero's contentBox*)
- Block-specific CTA styling (Hero's ctaVariant/ctaBgColor/etc.)

These are managed by each block's own Editor.tsx, Preview.tsx, and Blade template.

---

## 9. Global Field Requirements (Planned)

The following reusable field components are defined as global requirements for all blocks but are **not yet implemented**. They are specified in [HERO-CONTROLS-UX-AUDIT.md](./HERO-CONTROLS-UX-AUDIT.md) Section 4.

| Field Component | Purpose | Priority | Status |
|----------------|---------|----------|--------|
| `BoxSpacingField` | Per-side padding/margin with linked/unlinked toggle | P1 | Not implemented |
| `CornerRadiusField` | Per-corner border radius with presets (none/sm/md/lg/pill) | P1 | Not implemented |
| `ShadowField` | Preset + custom shadow builder (x/y/blur/spread/color/opacity/inset) | Exists | Working (used by Hero section shadow) |
| `TypographyField` | Unified typography: tag, size, weight, line-height, letter-spacing, color, transform | P2 | Not implemented |
| `ResponsivePreview` | Editor canvas viewport preview (desktop/tablet/mobile) | P3 | Working (BuilderCanvas device toggle) |

Once implemented, these components will replace single-value text inputs currently used for padding, border-radius, and typography across all blocks.
