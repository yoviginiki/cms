# Ultimate Block System — Architecture Specification

> **Status**: Architecture document — proposed future direction.
> **Date**: 2026-05-09
> **Scope**: Documentation and gap analysis only. No code changes.
> **Pilot block**: Hero

---

## Table of Contents

1. [Current State Summary](#1-current-state-summary)
2. [Ultimate Base Block Model](#2-ultimate-base-block-model)
3. [Professional Hero Block Specification](#3-professional-hero-block-specification)
4. [Proposed JSON Schema](#4-proposed-json-schema)
5. [Current vs Target Mapping](#5-current-vs-target-mapping)
6. [Phased Roadmap](#6-phased-roadmap)
7. [Warnings and Constraints](#7-warnings-and-constraints)

---

## 1. Current State Summary

### What exists today

The CMS has a three-layer block architecture:

| Layer | Location | Role |
|-------|----------|------|
| Frontend (React) | `resources/admin/src/components/blocks/{type}/` | Editor panel + canvas preview |
| Backend (PHP) | `app/Domain/Blocks/Definitions/{Type}BlockDefinition.php` | Validation + sanitization rules |
| Rendering (Blade) | `resources/views/blocks/{type}.blade.php` | Published HTML output |

**Block inventory** (from `npm run blocks:audit`):

- 69 total block types registered
- 18 COMPLETE (all three layers present and wired)
- 50 MISSING_BACKEND (frontend + Blade exist, no PHP definition)
- 1 ORPHAN_BACKEND (PHP + Blade exist, no frontend)

**Property system**:

- Dual storage: `block.data` (content-specific) and `block.style` / `block.animation` / `block.responsive` / `block.advanced` (shared/global)
- 7 shared property panels exist in the editor: Typography, Spacing, Visual, Layout, Animation, Responsive, Advanced
- 7 shared field components: TextField, TextArea, SelectField, NumberField, ColorField, ToggleField, ImageField
- `blockStyles.ts` helper converts shared props to inline CSS for editor preview
- `BuildPageService.renderBlock()` passes `$blockStyle`, `$blockAnimation`, `$blockAdvanced` to Blade
- `BlockStyleResolver.php` provides server-side CSS generation

**Hero block** is the first block with shared properties connected end-to-end (preview + Blade).

### What is partially implemented

| Feature | Status | Detail |
|---------|--------|--------|
| Shared spacing (padding/margin) | WORKING | Applied in Hero Blade and preview wrapper |
| Shared border/radius/shadow | WORKING | Applied in Hero Blade and preview wrapper |
| Shared animation (entrance) | WORKING | fade/slide/zoom with duration/delay |
| Custom class | WORKING | Sanitized in preview and Blade |
| HTML ID | WORKING | Sanitized in Blade |
| ARIA label | WORKING | Applied in Blade |
| Animation on-scroll trigger | PARTIAL | Saved to data, no Intersection Observer JS |
| Hover effects | DEAD_CONTROL | Panel saves data, no CSS/JS renders it |
| Responsive hideOn | DEAD_CONTROL | Panel saves data, not applied in preview or Blade |
| Custom CSS | DEAD_CONTROL | Panel saves data, blocked for security |
| Typography overrides (global) | DEAD_CONTROL | Panel exists, not applied by wrapper or Blade |
| Layout overrides (global) | DEAD_CONTROL | Panel exists, not applied by wrapper or Blade |
| Background from VisualPanel | DEAD_CONTROL for Hero | Hero uses its own BackgroundEditor; global bg ignored |

### What is missing

- No inline editing (contentEditable) for block content fields
- No responsive breakpoint override system (only hideOn toggle)
- No design token binding in block properties
- No reusable symbol / master block / pattern system
- No layer model (background, overlay, decorative, content, foreground)
- No easing control for animations
- No parallax implementation (field exists, no runtime)
- No shape dividers
- No stagger animation for child elements
- No gradient field component (gradient is hardcoded in Hero's BackgroundEditor)
- No link field component with URL type validation
- No dimension field component with unit selector
- No focal point selector for background images

---

## 2. Ultimate Base Block Model

Every block in the system should conform to this layered model. This is the target architecture — current blocks implement a subset.

### A. Identity

```
identity:
  id:           string (UUID)
  type:         string (kebab-case, e.g. "hero", "rich-text")
  label:        string (human-readable, e.g. "Hero Section")
  category:     enum (layout | content | media | interactive | commerce |
                      forms | typography | data | blog | embed |
                      navigation | marketing | advanced)
  icon:         string (Lucide icon name)
  description:  string (one sentence)
  version:      integer (schema version for migrations)
  readiness:    enum (L0_skeleton | L1_functional | L2_polished |
                      L3_production | L4_exemplary)
  tier:         enum (core | advanced | pro)
  allowsChildren: boolean
  maxChildren:  integer | null
```

### B. Content

Content fields are block-type-specific. They live in `block.data` and are defined per block type.

```
content:
  # Text fields
  textFields:        Record<string, { value, maxLength?, required?, htmlTag? }>
  richTextFields:    Record<string, { value, allowedTags?, maxLength? }>
  inlineEditable:    string[]  # field keys editable on canvas

  # Media fields
  mediaFields:       Record<string, { assetId?, url?, alt?, focalPoint?,
                                       loading?: 'eager' | 'lazy',
                                       aspectRatio? }>

  # Link/CTA fields
  ctaFields:         Record<string, { label, url, style?, variant?, target?,
                                       rel?, ariaLabel? }>

  # Accessibility content
  a11yFields:        Record<string, { ariaLabel?, role?, htmlId? }>
```

**Current state**: Content is stored as a flat `Record<string, unknown>` in `block.data`. There is no structured schema per field — validation lives in `HeroBlockDefinition.validationRules()`.

### C. Settings (Shared Properties)

These are common across all block types and stored outside `block.data`:

```
settings:
  spacing:
    paddingTop:      dimension
    paddingRight:    dimension
    paddingBottom:   dimension
    paddingLeft:     dimension
    marginTop:       dimension
    marginRight:     dimension
    marginBottom:    dimension
    marginLeft:      dimension
    gap:             dimension

  visual:
    backgroundColor: color
    backgroundImage: url
    backgroundGradient: gradient
    borderWidth:     dimension
    borderColor:     color
    borderStyle:     enum (none | solid | dashed | dotted)
    borderRadius:    dimension
    boxShadow:       enum (none | sm | md | lg) | custom
    opacity:         number (0–1)
    overflow:        enum (visible | hidden | scroll)

  layout:
    width:           dimension
    maxWidth:        dimension
    minHeight:       dimension
    alignment:       enum (left | center | right | stretch)
    display:         enum (block | flex | grid | none)
    flexDirection:   enum (row | column)
    justifyContent:  string
    alignItems:      string
    zIndex:          integer

  typography:
    fontFamily:      string | token
    fontSize:        dimension
    fontWeight:      enum (400 | 500 | 600 | 700)
    lineHeight:      dimension
    letterSpacing:   dimension
    textAlign:       enum (left | center | right | justify)
    textTransform:   enum (none | uppercase | lowercase | capitalize)
    textColor:       color

  animation:
    entrance:        enum (none | fade | slide-up | slide-left |
                           slide-right | zoom | reveal)
    trigger:         enum (on-load | on-scroll)
    duration:        integer (ms, 50–3000)
    delay:           integer (ms, 0–5000)
    easing:          enum (ease | ease-in | ease-out | ease-in-out | linear)
    hoverEffect:     enum (none | opacity | lift | glow | scale)
    reducedMotion:   enum (respect | force-none)

  advanced:
    customClass:     string (sanitized to [a-zA-Z0-9_\-\s])
    customCss:       string (BLOCKED — security risk, future scoped CSS)
    htmlId:          string (sanitized to [a-zA-Z0-9_\-])
    ariaLabel:       string
```

**Current state**: `block.style` stores spacing + visual + layout. `block.animation` stores animation. `block.advanced` stores advanced. Typography is in `block.style.typography` but is a DEAD_CONTROL (not rendered).

### D. Responsive Overrides

```
responsive:
  desktop:     base values (always present)
  tablet:      Partial<settings> (override only changed values)
  mobile:      Partial<settings> (override only changed values)

  behavior:
    inheritance:  desktop → tablet → mobile (cascade)
    clearOverride: reset individual property to inherited value
    previewViewport: desktop | tablet | mobile (editor preview)
```

**Current state**: `block.responsive` has `hideOn: ('desktop' | 'tablet' | 'mobile')[]` and partial tablet/mobile style overrides. None of this is applied in preview or Blade. DEAD_CONTROL.

### E. Effects and Interactions

```
effects:
  entrance:
    type:          enum (see animation.entrance above)
    trigger:       enum (on-load | on-scroll)
    threshold:     number (0–1, for Intersection Observer)
    duration:      integer (ms)
    delay:         integer (ms)
    easing:        string
    stagger:       integer (ms, for child elements)

  scroll:
    parallax:      boolean
    parallaxSpeed: number (-1 to 1)
    reveal:        enum (none | fade | slide | mask)

  hover:
    effect:        enum (none | opacity | lift | glow | scale)
    duration:      integer (ms)

  transform:
    rotate:        number (degrees)
    scale:         number
    translateX:    dimension
    translateY:    dimension

  reducedMotion:
    behavior:      enum (respect | force-none)
    fallback:      'instant' (show immediately without animation)
```

**Current state**: Only entrance animation (5 types) with duration/delay works. On-scroll trigger, hover, parallax, stagger, reveal, transform are all MISSING or DEAD_CONTROL.

### F. Layers

```
layers:
  - name:          string (background | overlay | decorative | content |
                           foreground | media)
    type:          enum (color | gradient | image | video | svg | none)
    value:         depends on type
    blendMode:     enum (normal | multiply | screen | overlay | ...)
    opacity:       number (0–1)
    zIndex:        integer
    position:      enum (static | absolute | relative)
    isolation:     boolean
    offsets:        { top, right, bottom, left }
```

**Current state**: Hero has a two-layer system hardcoded in Blade: background (from `data.bg_*`) and overlay (from `data.bg_overlay_*`). No generic layer model exists.

### G. Design Tokens

```
tokens:
  color:
    primary:       { light, dark, semantic? }
    secondary:     { light, dark }
    accent:        { light, dark }
    text:          { primary, secondary, muted }
    surface:       { default, elevated, sunken }
    border:        { default, strong }
    ...custom

  typography:
    heading:       { family, weight, lineHeight, letterSpacing }
    body:          { family, weight, lineHeight }
    caption:       { family, weight, lineHeight }
    mono:          { family, weight }

  spacing:
    xs:            dimension
    sm:            dimension
    md:            dimension
    lg:            dimension
    xl:            dimension

  radius:
    sm:            dimension
    md:            dimension
    lg:            dimension
    full:          dimension

  shadow:
    sm:            string
    md:            string
    lg:            string

  fallback:
    behavior:      'use raw value if token not found'
```

**Current state**: The CMS has a W3C Design Tokens system (`app/Services/Theme/`) for site-wide theming, but blocks do not reference tokens. Block properties use raw CSS values. No token binding exists.

### H. Reusable Symbols / Components

```
symbol:
  isMaster:        boolean
  masterBlockId:   string | null (if this is an instance)
  overrides:       Record<string, unknown> (instance-level changes)
  lockedFields:    string[] (fields that instances cannot change)
  editableSlots:   string[] (fields that instances CAN change)
  usageCount:      integer (how many instances reference this master)
  detach:          'convert instance to independent block'
  pushToMaster:    'propagate instance changes to master and all instances'
```

**Current state**: No symbol or reusable block system exists. Each block is fully independent.

---

## 3. Professional Hero Block Specification

The Hero is the pilot block for the Ultimate Base Block model. This section defines the complete target specification.

### A. Content

| Field | Type | Default | Required | Notes |
|-------|------|---------|----------|-------|
| eyebrow | string | `''` | No | Small text above headline ("NEW", "ANNOUNCING") |
| headline | string | `'Hero Title'` | Yes | Main title |
| headlineTag | enum | `'h1'` | Yes | `h1` \| `h2` \| `h3` — SEO heading level |
| subheadline | string | `''` | No | Supporting text below headline |
| bodyText | richText | `''` | No | Optional paragraph below subheadline |
| primaryCtaLabel | string | `''` | No | Button text |
| primaryCtaUrl | string | `''` | No | Button destination (safe schemes only) |
| primaryCtaStyle | enum | `'filled'` | No | `filled` \| `outline` \| `ghost` \| `link` |
| secondaryCtaLabel | string | `''` | No | Second button text |
| secondaryCtaUrl | string | `''` | No | Second button destination |
| secondaryCtaStyle | enum | `'outline'` | No | `filled` \| `outline` \| `ghost` \| `link` |
| caption | string | `''` | No | Photo credit or source text |
| badge | string | `''` | No | Small pill/tag label |
| trustText | string | `''` | No | Social proof ("Trusted by 10,000+") |
| accessibilityLabel | string | `''` | No | ARIA label for the section |

**Current Hero content fields**: `title` (→ headline), `subtitle` (→ subheadline), `ctaText` (→ primaryCtaLabel), `ctaUrl` (→ primaryCtaUrl), `alt` (→ image accessibility). Missing: eyebrow, headlineTag, bodyText, primaryCtaStyle, all secondary CTA, caption, badge, trustText.

### B. Media and Background

| Field | Type | Default | Notes |
|-------|------|---------|-------|
| bgType | enum | `'none'` | `none` \| `color` \| `gradient` \| `image` \| `video` |
| bgColor | color | `''` | Solid background color |
| bgGradientType | enum | `'linear'` | `linear` \| `radial` |
| bgGradientAngle | integer | `180` | Degrees (0–360) |
| bgGradientStops | array | 2 stops | `[{ color, position }]` |
| bgImage | url | `''` | Background image URL |
| bgMobileImage | url | `''` | Smaller/different image for mobile |
| bgVideo | url | `''` | Background video URL (MP4) |
| bgPosition | string | `'center center'` | CSS background-position |
| bgSize | enum | `'cover'` | `cover` \| `contain` \| `auto` |
| bgRepeat | enum | `'no-repeat'` | `no-repeat` \| `repeat` \| `repeat-x` \| `repeat-y` |
| bgAttachment | enum | `'scroll'` | `scroll` \| `fixed` (parallax) |
| bgParallaxSpeed | number | `0.5` | 0–1, used if attachment is fixed |
| overlayColor | color | `'#000000'` | Overlay layer color |
| overlayOpacity | number | `0` | 0–1 |
| overlayBlendMode | enum | `'normal'` | `normal` \| `multiply` \| `screen` \| `overlay` |
| foregroundImage | url | `''` | Optional media in content area |
| foregroundAspectRatio | enum | `'auto'` | `auto` \| `16:9` \| `4:3` \| `1:1` \| `3:2` |
| imageAlt | string | `''` | Alt text for background image |
| mediaLoading | enum | `'eager'` | `eager` \| `lazy` — Hero is above fold, default eager |
| focalPoint | object | `null` | `{ x: 0.5, y: 0.5 }` — future |

**Current Hero background fields**: `bg_type`, `bg_color`, `bg_gradient_type`, `bg_gradient_angle`, `bg_gradient_stops`, `bg_image`, `bg_image_size`, `bg_image_position`, `bg_image_repeat`, `bg_overlay_color`, `bg_overlay_opacity`, `bg_scroll_effect`, `bg_parallax_speed`, `alt`. Missing: bgMobileImage, bgVideo, overlayBlendMode, foregroundImage, foregroundAspectRatio, mediaLoading, focalPoint.

### C. Layout and Style

| Field | Type | Default | Notes |
|-------|------|---------|-------|
| textAlignment | enum | `'center'` | `left` \| `center` \| `right` |
| verticalPosition | enum | `'center'` | `top` \| `center` \| `bottom` |
| sectionHeight | enum | `'auto'` | `auto` \| `min-400` \| `min-600` \| `min-800` \| `fullscreen` |
| contentMaxWidth | dimension | `'800px'` | Max width of inner content |
| containerWidth | enum | `'full'` | `boxed` \| `full` |
| paddingTop | dimension | `'2rem'` | |
| paddingBottom | dimension | `'2rem'` | |
| paddingLeft | dimension | `'2rem'` | |
| paddingRight | dimension | `'2rem'` | |
| marginTop | dimension | `''` | |
| marginBottom | dimension | `''` | |
| gap | dimension | `'1rem'` | Gap between child elements |
| buttonLayout | enum | `'inline'` | `inline` \| `stacked` |
| mobileStacking | enum | `'vertical'` | How split layouts stack on mobile |
| splitLayout | enum | `'none'` | `none` \| `media-left` \| `media-right` |
| shapeDividerTop | enum | `'none'` | Future: `none` \| `wave` \| `angle` \| `curve` |
| shapeDividerBottom | enum | `'none'` | Future |

**Current**: Hero has hardcoded `min-height:400px`, `text-align:center`, `max-width:800px`, `padding:2rem`. Shared spacing from `$blockStyle` is applied. Missing: textAlignment, verticalPosition, sectionHeight, contentMaxWidth, containerWidth, gap, buttonLayout, splitLayout, shapeDividers.

### D. Typography

| Field | Type | Default | Notes |
|-------|------|---------|-------|
| headlineToken | string | `''` | Design token reference |
| headlineSize | dimension | `'2.5rem'` | Custom override |
| headlineSizeTablet | dimension | `''` | Responsive override |
| headlineSizeMobile | dimension | `''` | Responsive override |
| headlineWeight | enum | `700` | `400` \| `500` \| `600` \| `700` \| `800` \| `900` |
| headlineLineHeight | dimension | `'1.2'` | |
| headlineLetterSpacing | dimension | `''` | |
| headlineTransform | enum | `'none'` | `none` \| `uppercase` \| `lowercase` \| `capitalize` |
| headlineColor | color | `''` | Empty = inherit from context |
| subheadlineSize | dimension | `'1.25rem'` | |
| subheadlineWeight | enum | `400` | |
| subheadlineColor | color | `''` | |
| adaptiveTextColor | boolean | `true` | Auto-switch text color based on bg |

**Current**: Hero uses hardcoded inline styles (`font-size:2.5rem;font-weight:700` for h1, `font-size:1.25rem` for subtitle). Text color is derived from `$hasBg`. No typography controls exposed. DEAD_CONTROL via global TypographyPanel.

### E. Buttons

| Field | Type | Default | Notes |
|-------|------|---------|-------|
| primaryVariant | enum | `'filled'` | `filled` \| `outline` \| `ghost` \| `link` |
| primaryRadius | dimension | `'0.375rem'` | |
| primarySize | enum | `'md'` | `sm` \| `md` \| `lg` |
| primaryColorToken | string | `''` | Design token for button color |
| primaryHoverStyle | string | `''` | Future |
| secondaryVariant | enum | `'outline'` | |
| urlValidation | rule | — | Allow: `http`, `https`, `mailto`, `tel`, relative, anchor. Reject: `javascript:`, `data:`, `vbscript:` |
| iconSupport | boolean | `false` | Future |

**Current**: Hero renders one CTA with hardcoded styles. `safeUrl()` validates URL scheme. No variant, radius, size, or color token controls. No secondary CTA.

### F. Responsive

| Field | Type | Default | Notes |
|-------|------|---------|-------|
| tabletOverrides | Partial<settings> | `{}` | Override any setting for tablet |
| mobileOverrides | Partial<settings> | `{}` | Override any setting for mobile |
| hideOnDesktop | boolean | `false` | |
| hideOnTablet | boolean | `false` | |
| hideOnMobile | boolean | `false` | |
| breakpoints | object | — | `{ tablet: 768, mobile: 480 }` |
| inheritance | rule | — | Desktop → tablet → mobile cascade |
| fluidValues | boolean | `false` | Use `clamp()` for sizes/spacing |

**Current**: `block.responsive.hideOn` exists in schema but is DEAD_CONTROL. No responsive overrides apply. No fluid/clamp values. No breakpoint model.

### G. Animations and Interactions

| Field | Type | Default | Notes |
|-------|------|---------|-------|
| entrance | enum | `'none'` | `none` \| `fade` \| `slide-up` \| `slide-left` \| `slide-right` \| `zoom` \| `reveal` |
| trigger | enum | `'on-load'` | `on-load` \| `on-scroll` |
| duration | integer | `400` | ms, clamped 50–3000 |
| delay | integer | `0` | ms, clamped 0–5000 |
| easing | enum | `'ease'` | `ease` \| `ease-in` \| `ease-out` \| `ease-in-out` \| `linear` |
| staggerChildren | integer | `0` | ms between child animations (future) |
| hoverEffect | enum | `'none'` | `none` \| `opacity` \| `lift` \| `glow` \| `scale` |
| parallaxIntensity | number | `0` | 0–1 (future) |
| reducedMotion | enum | `'respect'` | `respect` \| `force-none` |

**Current**: 5 entrance types with duration/delay work end-to-end. Easing not exposed. On-scroll trigger saved but not implemented. Hover effects saved but not rendered. Parallax field exists in definition but no runtime. `@media (prefers-reduced-motion: reduce)` rule exists in both admin CSS and published critical CSS.

### H. Layer and Z-Index

| Layer | Purpose | Z-Index | Current Status |
|-------|---------|---------|----------------|
| background | Color/gradient/image/video | 0 | WORKING (Hero-specific) |
| overlay | Semi-transparent color filter | 0 (with opacity) | WORKING (Hero-specific) |
| decorative | SVG patterns, shapes, dividers | 1 | MISSING |
| content | Text, buttons, badges | 1 | WORKING |
| media | Foreground image/video | 2 | MISSING |

**Current**: Hero has background + overlay + content layers hardcoded in Blade. No generic layer system. No blend mode. No decorative or media layer.

### I. SEO and Accessibility

| Requirement | Status | Notes |
|-------------|--------|-------|
| Heading tag selection (h1/h2/h3) | MISSING | Hardcoded `<h1>` |
| Image alt text | WORKING | `alt` field in data |
| ARIA label on section | WORKING | From `$blockAdvanced` |
| No empty links | PARTIAL | CTA only renders if both label and URL present |
| Meaningful CTA text | NOT_ENFORCED | No validation rule |
| Contrast check | MISSING | No automated contrast |
| Reduced motion | WORKING | CSS rule exists |
| Focus states | PARTIAL | `:focus-visible` rule exists globally |
| LCP image consideration | MISSING | No `fetchpriority="high"` or preload |
| Eager loading for above-fold | MISSING | No `loading="eager"` attribute |

### J. Security

| Rule | Status | Implementation |
|------|--------|----------------|
| Safe URL schemes (http, https, mailto, tel, relative, anchor) | WORKING | `$safeUrl()` in Blade |
| Reject javascript:/data:/vbscript: | WORKING | `$safeUrl()` regex |
| Safe CSS dimensions | WORKING | `$cssDim()` regex |
| Safe CSS colors | WORKING | `$cssVal()` regex |
| Safe image URLs | WORKING | `$cssUrl()` regex |
| No raw HTML in content fields | WORKING | `{{ }}` Blade escaping |
| Custom class sanitization | WORKING | `[a-zA-Z0-9_\-\s]` regex |
| HTML ID sanitization | WORKING | `[a-zA-Z0-9_\-]` regex |
| Shadow values allowlisted | WORKING | `$shadowMap` lookup |
| Animation names allowlisted | WORKING | `$animNames` lookup |
| Duration/delay clamped | WORKING | `max(50, min(3000, ...))` |
| HTMLPurifier for content | WORKING | SanitizationService strips all HTML from hero title/subtitle |

---

## 4. Proposed JSON Schema

> **This is a proposed future schema.** The current system does NOT use this structure.
> Current Hero data is a flat `Record<string, unknown>` in `block.data`.

```jsonc
{
  // ─── Identity ───
  "id": "uuid-string",
  "type": "hero",
  "version": 1,

  // ─── Content (block-type-specific) ───
  "content": {
    "eyebrow": "",
    "headline": "Hero Title",
    "headlineTag": "h1",
    "subheadline": "",
    "bodyText": "",
    "primaryCta": {
      "label": "",
      "url": "",
      "style": "filled"
    },
    "secondaryCta": {
      "label": "",
      "url": "",
      "style": "outline"
    },
    "caption": "",
    "badge": "",
    "trustText": "",
    "accessibilityLabel": ""
  },

  // ─── Settings (shared across block types) ───
  "settings": {
    "spacing": {
      "paddingTop": "2rem",
      "paddingBottom": "2rem",
      "paddingLeft": "2rem",
      "paddingRight": "2rem",
      "marginTop": "",
      "marginBottom": "",
      "gap": "1rem"
    },
    "visual": {
      "borderWidth": "",
      "borderColor": "",
      "borderStyle": "none",
      "borderRadius": "",
      "boxShadow": "none",
      "opacity": 1,
      "overflow": "hidden"
    },
    "layout": {
      "textAlignment": "center",
      "verticalPosition": "center",
      "sectionHeight": "auto",
      "contentMaxWidth": "800px",
      "containerWidth": "full",
      "splitLayout": "none",
      "buttonLayout": "inline"
    },
    "typography": {
      "headlineSize": "2.5rem",
      "headlineWeight": 700,
      "headlineLineHeight": "1.2",
      "headlineLetterSpacing": "",
      "headlineTransform": "none",
      "headlineColor": "",
      "subheadlineSize": "1.25rem",
      "subheadlineWeight": 400,
      "subheadlineColor": "",
      "adaptiveTextColor": true
    },
    "animation": {
      "entrance": "none",
      "trigger": "on-load",
      "duration": 400,
      "delay": 0,
      "easing": "ease",
      "reducedMotion": "respect"
    },
    "advanced": {
      "customClass": "",
      "htmlId": "",
      "ariaLabel": ""
    }
  },

  // ─── Background (Hero-specific, could be a layer) ───
  "background": {
    "type": "none",
    "color": "",
    "gradient": {
      "type": "linear",
      "angle": 180,
      "stops": [
        { "color": "#3b82f6", "position": 0 },
        { "color": "#8b5cf6", "position": 100 }
      ]
    },
    "image": {
      "url": "",
      "mobileUrl": "",
      "position": "center center",
      "size": "cover",
      "repeat": "no-repeat",
      "attachment": "scroll",
      "alt": "",
      "loading": "eager"
    },
    "video": {
      "url": "",
      "poster": ""
    },
    "overlay": {
      "color": "#000000",
      "opacity": 0,
      "blendMode": "normal"
    }
  },

  // ─── Responsive overrides ───
  "responsive": {
    "tablet": {},
    "mobile": {},
    "hideOn": []
  },

  // ─── Effects ───
  "effects": {
    "hover": {
      "effect": "none"
    },
    "scroll": {
      "parallax": false,
      "parallaxSpeed": 0.5
    }
  },

  // ─── Layers (future generic system) ───
  "layers": [],

  // ─── Design token bindings (future) ───
  "tokens": {
    "headlineColor": "",
    "primaryCtaColor": "",
    "backgroundColor": ""
  },

  // ─── Symbol/reusable (future) ───
  "symbol": {
    "isMaster": false,
    "masterBlockId": null,
    "overrides": {},
    "lockedFields": [],
    "editableSlots": []
  }
}
```

### Current key mapping

| Proposed key | Current key in `block.data` | Notes |
|--------------|-----------------------------|-------|
| `content.headline` | `data.title` | Rename needed |
| `content.subheadline` | `data.subtitle` | Rename needed |
| `content.primaryCta.label` | `data.ctaText` | Rename needed |
| `content.primaryCta.url` | `data.ctaUrl` | Rename needed |
| `content.accessibilityLabel` | `data.alt` | Rename needed |
| `background.type` | `data.bg_type` | Move out of data |
| `background.color` | `data.bg_color` | Move out of data |
| `background.gradient.*` | `data.bg_gradient_*` | Move out of data |
| `background.image.url` | `data.bg_image` | Move out of data |
| `background.image.size` | `data.bg_image_size` | Move out of data |
| `background.image.position` | `data.bg_image_position` | Move out of data |
| `background.image.repeat` | `data.bg_image_repeat` | Move out of data |
| `background.overlay.color` | `data.bg_overlay_color` | Move out of data |
| `background.overlay.opacity` | `data.bg_overlay_opacity` | Move out of data |
| `background.scroll` | `data.bg_scroll_effect` | Move out of data |
| `settings.spacing.*` | `block.style.spacing.*` | Already in shared props |
| `settings.visual.*` | `block.style.visual.*` | Already in shared props |
| `settings.animation.*` | `block.animation.*` | Already in shared props |
| `settings.advanced.*` | `block.advanced.*` | Already in shared props |

---

## 5. Current vs Target Mapping

| Property | Current Key | Status | Files | Implementation Step | Priority |
|----------|-------------|--------|-------|---------------------|----------|
| **Content** | | | | | |
| Headline | `data.title` | WORKING | Editor.tsx, Preview.tsx, hero.blade.php | — | — |
| Subheadline | `data.subtitle` | WORKING | Editor.tsx, Preview.tsx, hero.blade.php | — | — |
| H1/H2/H3 tag | `data.headlineTag` | WORKING | Editor.tsx, Preview.tsx, hero.blade.php, HeroBlockDefinition.php | — | P1 ✅ |
| Eyebrow | — | MISSING | — | Add `eyebrow` field to Editor/Preview/Blade/Definition | P2 |
| Primary CTA label | `data.ctaText` | WORKING | Editor.tsx, hero.blade.php | — | — |
| Primary CTA URL | `data.ctaUrl` | WORKING | Editor.tsx, hero.blade.php | — | — |
| Primary CTA style | — | MISSING | — | Add `ctaStyle` select, update Blade button rendering | P2 |
| Secondary CTA label | — | MISSING | — | Add `secondaryCtaText` field, render second button | P2 |
| Secondary CTA URL | — | MISSING | — | Add `secondaryCtaUrl` field with safeUrl validation | P2 |
| Caption/credit | — | MISSING | — | Add `caption` field to Editor/Blade below content | P3 |
| Badge | — | MISSING | — | Add `badge` field, render as pill above eyebrow | P3 |
| Trust text | — | MISSING | — | Add `trustText` field, render below CTAs | P3 |
| **Background** | | | | | |
| Background type | `data.bg_type` | WORKING | Editor.tsx, hero.blade.php | — | — |
| Background color | `data.bg_color` | WORKING | Editor.tsx, hero.blade.php | — | — |
| Background image | `data.bg_image` | WORKING | Editor.tsx, hero.blade.php | — | — |
| Mobile image | — | MISSING | — | Add `bg_mobile_image` field, `<picture>` with srcset | P2 |
| Background video | — | MISSING | — | Add video upload, `<video>` element, poster frame | P3 |
| Gradient | `data.bg_gradient_*` | WORKING | Editor.tsx, hero.blade.php | — | — |
| Overlay color | `data.bg_overlay_color` | WORKING | hero.blade.php | — | — |
| Overlay opacity | `data.bg_overlay_opacity` | WORKING | hero.blade.php | — | — |
| Overlay blend mode | — | MISSING | — | Add `bg_overlay_blend` select, apply `mix-blend-mode` | P3 |
| Background position | `data.bg_image_position` | WORKING | hero.blade.php | — | — |
| Background size | `data.bg_image_size` | WORKING | hero.blade.php | — | — |
| Background repeat | `data.bg_image_repeat` | WORKING | hero.blade.php | — | — |
| Parallax | `data.bg_scroll_effect` | PARTIAL | definition.ts, hero.blade.php | Add JS Intersection Observer for transform parallax | P2 |
| Focal point | — | FUTURE | — | Build focal point picker UI, map to `object-position` | P3 |
| Media loading (eager/lazy) | `data.mediaLoading` | WORKING | Editor.tsx, definition.ts, HeroBlockDefinition.php | — | P1 ✅ |
| **Layout** | | | | | |
| Text alignment | `data.textAlignment` | WORKING | Editor.tsx, Preview.tsx, hero.blade.php | — | P1 ✅ |
| Vertical position | `data.verticalPosition` | WORKING | Editor.tsx, Preview.tsx, hero.blade.php | — | P1 ✅ |
| Section height | `data.sectionHeight` | WORKING | Editor.tsx, Preview.tsx, hero.blade.php | — | P1 ✅ |
| Content max width | `data.contentMaxWidth` | WORKING | Editor.tsx, Preview.tsx, hero.blade.php | — | P1 ✅ |
| Container width | — | MISSING | Hardcoded full | Add `containerWidth` toggle (boxed/full) | P2 |
| Padding | `block.style.spacing.padding*` | WORKING | blockStyles.ts, hero.blade.php | — | — |
| Margin | `block.style.spacing.margin*` | WORKING | blockStyles.ts, hero.blade.php | — | — |
| Gap | — | MISSING | — | Add `gap` to spacing panel, apply in Blade flex container | P2 |
| Split layout | — | FUTURE | — | Add media-left/media-right layout mode with grid | P3 |
| Shape dividers | — | FUTURE | — | Add SVG shape divider component with presets | P3 |
| **Visual** | | | | | |
| Border | `block.style.visual.border*` | WORKING | blockStyles.ts, hero.blade.php | — | — |
| Radius | `block.style.visual.borderRadius` | WORKING | blockStyles.ts, hero.blade.php | — | — |
| Shadow | `block.style.visual.boxShadow` | WORKING | blockStyles.ts, hero.blade.php | — | — |
| Opacity | `block.style.visual.opacity` | WORKING | blockStyles.ts, hero.blade.php | — | — |
| **Typography** | | | | | |
| Headline size | `data.headlineSize` | WORKING | Editor.tsx, Preview.tsx, hero.blade.php | — | P1 ✅ |
| Headline weight | `data.headlineWeight` | WORKING | Editor.tsx, Preview.tsx, hero.blade.php | — | P1 ✅ |
| Headline line height | — | MISSING | — | Add `headlineLineHeight` dimension field | P2 |
| Headline color | `data.headlineColor` | WORKING | Editor.tsx, Preview.tsx, hero.blade.php | — | P1 ✅ |
| Subheadline size | `data.subheadlineSize` | WORKING | Editor.tsx, Preview.tsx, hero.blade.php | — | P1 ✅ |
| Text transform | — | MISSING | — | Add `headlineTransform` select (uppercase/capitalize) | P2 |
| Adaptive text color | `data.adaptiveTextColor` | WORKING | Editor.tsx, Preview.tsx, hero.blade.php | — | P1 ✅ |
| **Animation** | | | | | |
| Entrance animation | `block.animation.entrance` | WORKING | blockStyles.ts, hero.blade.php | — | — |
| Duration | `block.animation.duration` | WORKING | blockStyles.ts, hero.blade.php | — | — |
| Delay | `block.animation.delay` | WORKING | blockStyles.ts, hero.blade.php | — | — |
| Easing | — | MISSING | — | Add `easing` select, apply `animation-timing-function` | P2 |
| On-scroll trigger | `block.animation.trigger` | PARTIAL | Field saved, no JS runtime | Add Intersection Observer JS to published output | P2 |
| Hover effect | `block.animation.hoverEffect` | DEAD_CONTROL | Field saved, no CSS/JS | Add CSS `:hover` rules or remove panel control | P2 |
| Parallax intensity | `data.bg_parallax_speed` | DEAD_CONTROL | Field saved, no JS | Add scroll-driven transform JS or remove control | P3 |
| Stagger children | — | FUTURE | — | Add delay offset per child in container blocks | P3 |
| Reduced motion | — | WORKING | CSS rule in admin + published | — | — |
| **Infrastructure** | | | | | |
| Z-index control | — | MISSING | — | Add to LayoutPanel, apply in wrapper style | P2 |
| Layer model | — | FUTURE | Hero has hardcoded layers | Abstract layer array model from Hero pattern | P3 |
| Design tokens | — | FUTURE | No token binding | Add token picker to ColorField, resolve at build | P3 |
| Responsive hideOn | `block.responsive.hideOn` | WORKING | SortableBlock.tsx (badges), hero.blade.php (media queries), BuildPageService.php | — | P1 ✅ |
| Responsive overrides (per-breakpoint) | `block.responsive.tablet/mobile` | MISSING | Schema exists, not rendered | Add per-property override cascade | P2 |
| Reusable symbol | — | FUTURE | — | Add master_block_id column, symbol library UI | P3 |

### Summary counts

| Status | Count |
|--------|-------|
| WORKING | 34 |
| PARTIAL | 1 |
| MISSING | 12 |
| DEAD_CONTROL | 2 |
| FUTURE | 6 |

---

## 6. Phased Roadmap

### Phase 0: Stabilize current working tree ✅

- [x] Connect shared properties in SortableBlock preview wrapper
- [x] Connect shared properties in BuildPageService → Blade
- [x] Apply shared properties in Hero Blade
- [x] Add animation @keyframes to published CSS
- [x] Fix Theme::HasFactory (XssTest)
- [x] Fix Site::resolveRouteBinding (TenantIsolationTest x3)
- [x] Fix all pre-existing TypeScript errors
- [x] Verify `tsc && vite build` passes
- [x] Verify `php artisan test` passes (minus pre-existing ExampleTest)

### Phase 1: Hero P0 property completeness ✅

Target: No dead controls, no hardcoded layout values, preview and Blade match.

- [x] Add `headlineTag` field (h1/h2/h3 selector)
- [x] Add `textAlignment` field (left/center/right)
- [x] Add `verticalPosition` field (top/center/bottom)
- [x] Add `sectionHeight` field (auto/sm/md/lg/fullscreen)
- [x] Add `contentMaxWidth` field
- [x] Replace hardcoded typography with configurable values (`headlineSize`, `headlineWeight`, `subheadlineSize`)
- [x] Add `headlineColor` / `adaptiveTextColor` toggle
- [x] Add `mediaLoading` field (eager/lazy)
- [x] Wire responsive `hideOn` in preview (warning badges) and Blade (scoped media queries)
- [x] Pass `$blockResponsive` from BuildPageService to all Blade templates
- [x] Add PHPUnit tests for all new validation rules (35 tests, 36 assertions)
- [x] Add 12 new validation rules to HeroBlockDefinition
- [x] Verify `tsc && vite build` passes
- [x] Verify `php artisan test` passes (220 passed, 1 pre-existing ExampleTest failure)
- [ ] Remove or implement hover effects panel for Hero (remaining dead control)
- [ ] Add Hero demo fixtures for all new fields
- [ ] Update BLOCK-PROPERTIES-AUDIT.md with new property status
- [ ] Update BLOCK-PROPERTIES-AUDIT.md

### Phase 2: Shared BaseBlock property engine ✅

Target: Any block can use the same property pipeline without copy-pasting Hero's Blade logic.

- [x] Create BlockStyle PHP helper with safe sanitizers
- [x] Create docs/BASE-BLOCK-PROPERTY-ENGINE.md
- [x] Export utility functions from blockStyles.ts
- [x] Hero Blade uses BlockStyle helper
- [x] Unit tests for BlockStyle sanitizers
- [ ] Common Blade partial for shared style (deferred — each block calls BlockStyle directly)
- [ ] Wire TypographyPanel to preview wrapper (deferred — blocks handle own typography)
- [ ] Wire LayoutPanel to preview wrapper (deferred — blocks handle own layout)
- [x] "No dead controls" rule documented

### Phase 3: Shared editor field components

Target: Professional field components that any block Editor can use.

- [ ] `AssetSelectField` — media picker with preview, alt, focal point
- [ ] `ColorField` (exists) — add token picker integration
- [ ] `GradientField` — visual gradient builder
- [ ] `LinkField` — URL input with scheme validation, anchor picker, page picker
- [ ] `DimensionField` — value + unit selector (px/rem/em/%/vh/vw)
- [ ] `AlignmentField` — visual alignment picker (text or flex)
- [ ] `ResponsiveField` — wrapper that shows breakpoint indicator
- [ ] `AnimationField` — entrance type + preview
- [ ] `RepeaterField` — add/remove/reorder items

### Phase 4: Responsive overrides

Target: Blocks have per-breakpoint property overrides with visual indicators.

- [ ] Define breakpoint model: desktop (≥1024), tablet (768–1023), mobile (<768)
- [ ] Store responsive overrides as `{ tablet: { spacing: { paddingTop: '1rem' } } }`
- [ ] Cascade behavior: desktop → tablet → mobile (inherit unless overridden)
- [ ] Visual indicator in property panels for overridden values
- [ ] "Reset to inherited" action per property
- [ ] Preview viewport switcher (desktop/tablet/mobile)
- [ ] Blade rendering with `@media` queries for overrides
- [ ] Fluid/clamp values as optional enhancement

### Phase 5: Animations and interactions

Target: Reliable entrance animations, scroll-triggered effects, reduced motion.

- [ ] Implement Intersection Observer for `on-scroll` trigger
- [ ] Add easing control (ease, ease-in, ease-out, ease-in-out, linear)
- [ ] Implement hover effects CSS (opacity, lift, glow, scale)
- [ ] Add `reveal` entrance type (clip-path reveal)
- [ ] Add stagger option for container blocks with children
- [ ] Parallax implementation (transform-based, not background-attachment)
- [ ] Performance: `will-change`, `contain`, avoid layout thrash
- [ ] `prefers-reduced-motion` respected everywhere

### Phase 6: Design tokens

Target: Block properties can reference site design tokens instead of raw values.

- [ ] Token picker in ColorField (shows site palette)
- [ ] Token picker in typography fields (shows defined font stacks)
- [ ] Token references stored as `{ $token: 'color.primary' }` or raw value
- [ ] Resolve tokens at build time in BuildPageService
- [ ] Resolve tokens at preview time in blockStyles.ts
- [ ] Light/dark mode token variants
- [ ] Semantic tokens (e.g., `hero.background` maps to `color.primary`)
- [ ] Fallback behavior when token not found

### Phase 7: Reusable components / symbols

Target: Create a block once, reuse it across pages with linked updates.

- [ ] Master block concept: a block that can be instanced
- [ ] Instance: references master, stores only overrides
- [ ] Locked fields: master author can lock specific fields
- [ ] Editable slots: master author can mark fields as instance-editable
- [ ] Push to master: propagate instance changes to master
- [ ] Detach: convert instance to independent block
- [ ] Usage counter: show where a master is used
- [ ] Symbol library UI: browse and insert symbols
- [ ] Migration: existing blocks are all independent (no breaking change)

### Phase 8: Apply shared system to core blocks

Target: All core blocks use the shared property engine consistently.

Priority order:
1. **section** — container block, uses all shared properties
2. **heading** — typography-heavy, heading tag selector
3. **rich-text / paragraph** — typography + spacing
4. **image** — media fields, aspect ratio, loading strategy
5. **button** — CTA fields, variants, states
6. **columns** — layout system, gap, responsive stacking
7. **quote / pullquote** — typography + visual
8. **gallery** — media + layout + responsive
9. **card** — composite (image + text + CTA)
10. **divider** — minimal, visual only

Each block repair follows the process in BLOCK-CONTRACT.md §15 (Repair Workflow):
1. Run audit → identify status
2. Add/fix missing layers
3. Wire shared properties
4. Test preview + Blade parity
5. Add demo fixtures
6. Update readiness level

---

## 7. Warnings and Constraints

### Do NOT implement yet

| Item | Reason |
|------|--------|
| Reusable symbols / master blocks | Requires significant DB schema changes (master_block_id, overrides column), UI for symbol library, and careful migration planning. Phase 7. |
| Custom CSS rendering | Security risk. Requires CSS scoping (shadow DOM or class prefixing) to prevent style injection. Keep as BLOCKED. |
| Background video | Requires video upload pipeline, poster frame extraction, autoplay policies, accessibility (pause button, captions). Phase 3+ dependency. |
| Shape dividers | SVG generation, responsive sizing, many edge cases. Nice-to-have, not structural. |
| Inline editing (contentEditable) | Requires TipTap integration per field, cursor management, selection handling, undo/redo sync. Major effort. Separate initiative. |
| Generic layer model | Current hardcoded layers work. Abstracting to a generic system is over-engineering until more blocks need it. |

### Too risky before tests

| Item | Risk |
|------|------|
| Renaming `data.title` → `content.headline` | Breaks all existing Hero blocks in the database. Requires data migration, backward-compatible Blade rendering, and frontend migration. |
| Moving background fields out of `data` | Same as above — all saved Hero blocks reference `data.bg_*`. |
| Changing `block.style` structure | All shared property panels read/write this shape. Schema changes need coordinated frontend + backend migration. |
| Adding new DB columns (version, symbol fields) | Requires migration, model changes, API changes. Must be tested against existing data. |

### Requires migration / backward compatibility

Any schema change to block data must:

1. Add a `version` field to track schema version per block
2. Write a Laravel migration that transforms existing data
3. Keep Blade templates backward-compatible (read old AND new keys)
4. Keep frontend backward-compatible (Editor/Preview handle both shapes)
5. Run migration on a staging copy first
6. Verify `npm run blocks:audit` still passes
7. Verify all demo fixtures still render

### Performance considerations

- Hero with background image should use `fetchpriority="high"` and `loading="eager"` since it's above the fold
- Entrance animations should use `transform` and `opacity` only (compositable properties)
- Parallax should use `transform: translateY()` not `background-attachment: fixed` (better performance)
- Design token resolution should be cached per site build
- Responsive CSS should use `@media` queries, not JavaScript-based breakpoints
