# Hero Controls UX Audit

> **Date**: 2026-05-12
> **Status**: P0 fixes implemented
> **Scope**: Hero block editor controls — UX clarity, missing controls, global field contract

---

## 1. Current Control Inventory

### Content Fields

| Label | Data Key | Applies To | Editor | Preview | Blade | Validation | Status |
|-------|----------|------------|--------|---------|-------|------------|--------|
| Title | `title` | Title text | yes | yes | yes | required, string, max:255 | WORKING |
| Subtitle | `subtitle` | Subtitle text | yes | yes | yes | sometimes, nullable, string, max:500 | WORKING |

### Layout (Section: "Layout — Whole Hero Section")

| Label | Data Key | Applies To | Editor | Preview | Blade | Validation | Status |
|-------|----------|------------|--------|---------|-------|------------|--------|
| Heading Tag | `headlineTag` | Title element tag | yes | yes | yes | in:h1,h2,h3 | WORKING |
| Text Alignment | `textAlignment` | Content wrapper | yes (responsive) | yes | yes | in:left,center,right | WORKING |
| Vertical Position | `verticalPosition` | Whole Hero section | yes | yes | yes | in:top,center,bottom | WORKING |
| Section Height | `sectionHeight` | Whole Hero section | yes (responsive) | yes | yes | in:auto,sm,md,lg,fullscreen | WORKING |
| Content Max Width | `contentMaxWidth` | Content wrapper | yes (responsive) | yes | yes | regex CSS dim | WORKING |

### Background (Section: "Background — Whole Hero Section")

| Label | Data Key | Applies To | Editor | Preview | Blade | Validation | Status |
|-------|----------|------------|--------|---------|-------|------------|--------|
| Background Type | `bg_type` | Whole Hero | yes (BackgroundEditor) | yes | yes | in:none,color,gradient,image | WORKING |
| Background Color | `bg_color` | Whole Hero | yes | yes | yes | regex color | WORKING |
| Gradient Type | `bg_gradient_type` | Whole Hero | yes | yes | yes | in:linear,radial | WORKING |
| Gradient Angle | `bg_gradient_angle` | Whole Hero | yes | yes | yes | integer 0–360 | WORKING |
| Gradient Stops | `bg_gradient_stops` | Whole Hero | yes | yes | yes | array of color+position | WORKING |
| Background Image | `bg_image` | Whole Hero | yes | yes | yes | string max:2048 | WORKING |
| Image Size | `bg_image_size` | Whole Hero | yes | yes | yes | in:cover,contain,auto | WORKING |
| Image Position | `bg_image_position` | Whole Hero | yes | yes | yes | regex CSS position | WORKING |
| Image Repeat | `bg_image_repeat` | Whole Hero | yes | yes | yes | in:no-repeat,repeat,repeat-x,repeat-y | WORKING |
| Overlay Color | `bg_overlay_color` | Whole Hero overlay | yes | yes | yes | regex hex | WORKING |
| Overlay Opacity | `bg_overlay_opacity` | Whole Hero overlay | yes | yes | yes | numeric 0–1 | WORKING |
| Scroll Effect | `bg_scroll_effect` | Whole Hero | yes | yes | yes (fixed only) | in:none,fixed,parallax,zoom | PARTIAL — parallax/zoom not implemented |
| Parallax Speed | `bg_parallax_speed` | Whole Hero | yes | no | no | numeric 0.1–1 | DEAD_CONTROL |
| Legacy Image | `backgroundImage` | Whole Hero | no (legacy fallback) | yes (fallback) | yes (fallback) | not validated | WORKING (backward compat) |

### Typography (Section: "Typography — Title & Subtitle")

| Label | Data Key | Applies To | Editor | Preview | Blade | Validation | Status |
|-------|----------|------------|--------|---------|-------|------------|--------|
| Title Size | `headlineSize` | Title | yes | yes | yes | regex CSS dim | WORKING |
| Title Weight | `headlineWeight` | Title | yes | yes | yes | in:400–900 | WORKING |
| Title Color | `headlineColor` | Title | yes | yes | yes | regex color | WORKING |
| Title Line Height | `headlineLineHeight` | Title | yes (P2) | yes (P2) | yes (P2) | regex CSS dim (unitless ok) | WORKING (P2) |
| Title Letter Spacing | `headlineLetterSpacing` | Title | yes (P2) | yes (P2) | yes (P2) | regex CSS dim | WORKING (P2) |
| Title Text Transform | `headlineTextTransform` | Title | yes (P2) | yes (P2) | yes (P2) | in:uppercase,lowercase,capitalize | WORKING (P2) |
| Subtitle Size | `subheadlineSize` | Subtitle | yes | yes | yes | regex CSS dim | WORKING |
| Subtitle Weight | `subheadlineWeight` | Subtitle | yes (P1) | yes (P1) | yes (P1) | in:400–900 | WORKING (P1) |
| Subtitle Color | `subtitleColor` | Subtitle | yes (P0) | yes (P0) | yes (P0) | regex color | WORKING (P0) |
| Subtitle Line Height | `subheadlineLineHeight` | Subtitle | yes (P2) | yes (P2) | yes (P2) | regex CSS dim (unitless ok) | WORKING (P2) |
| Subtitle Letter Spacing | `subheadlineLetterSpacing` | Subtitle | yes (P2) | yes (P2) | yes (P2) | regex CSS dim | WORKING (P2) |
| Subtitle Text Transform | `subheadlineTextTransform` | Subtitle | yes (P2) | yes (P2) | yes (P2) | in:uppercase,lowercase,capitalize | WORKING (P2) |
| Auto Text Color | `adaptiveTextColor` | Title + Subtitle | yes | yes | yes | boolean | WORKING |

### Call to Action (Section: "Call to Action — CTA Button")

| Label | Data Key | Applies To | Editor | Preview | Blade | Validation | Status |
|-------|----------|------------|--------|---------|-------|------------|--------|
| Button Text | `ctaText` | CTA button | yes (inline) | yes | yes | string max:100 | WORKING |
| Button URL | `ctaUrl` | CTA button | yes (inline) | yes | yes | regex safe URL | WORKING |

### CTA Button Style (Section: "CTA Button Style")

| Label | Data Key | Applies To | Editor | Preview | Blade | Validation | Status |
|-------|----------|------------|--------|---------|-------|------------|--------|
| Variant | `ctaVariant` | CTA button | yes | yes | yes | in:filled,outline,ghost,link | WORKING |
| Size | `ctaSize` | CTA button | yes | yes | yes | in:sm,md,lg | WORKING |
| Alignment | `ctaAlign` | CTA button | yes | yes | yes | in:,left,center,right | WORKING |
| Background Color | `ctaBgColor` | CTA button | yes | yes | yes | regex color | WORKING |
| Text Color | `ctaTextColor` | CTA button | yes | yes | yes | regex color | WORKING |
| Border Color | `ctaBorderColor` | CTA button | yes | yes | yes | regex color | WORKING |
| Border Width | `ctaBorderWidth` | CTA button | yes | yes | yes | regex CSS dim | WORKING |
| Border Radius | `ctaBorderRadius` | CTA button | yes (P1: CornerRadiusField) | yes | yes | dimOrArray per-corner | WORKING (P1) |
| Shadow | `ctaShadow*` | CTA button | yes (P2: ShadowField) | yes (P2) | yes (P2) | preset+custom validated | WORKING (P2) |

### Border & Shadow (Section: "Border & Shadow — Whole Hero Section")

| Label | Data Key | Applies To | Editor | Preview | Blade | Validation | Status |
|-------|----------|------------|--------|---------|-------|------------|--------|
| Border Width | `sectionBorderWidth` | Whole Hero | yes | yes | yes | regex CSS dim | WORKING |
| Border Color | `sectionBorderColor` | Whole Hero | yes | yes | yes | regex color | WORKING |
| Border Style | `sectionBorderStyle` | Whole Hero | yes | yes | yes | in:,solid,dashed,dotted | WORKING |
| Border Radius | `sectionBorderRadius` | Whole Hero | yes | yes | yes | regex CSS dim | WORKING |
| Shadow | `sectionShadow*` | Whole Hero | yes (preset+custom) | yes | yes | preset+custom validated | WORKING |

### Content Box (Section: "Content Box — Text Readability Layer")

| Label | Data Key | Applies To | Editor | Preview | Blade | Validation | Status |
|-------|----------|------------|--------|---------|-------|------------|--------|
| Enable Content Box | `contentBoxEnabled` | Content box toggle | yes | yes | yes | boolean | WORKING |
| Background Color | `contentBoxBgColor` | Content box | yes | yes | yes | regex color | WORKING |
| Opacity | `contentBoxOpacity` | Content box bg | yes | yes | yes | integer 0–100 | WORKING |
| Border Radius | `contentBoxBorderRadius` | Content box | yes (P1: CornerRadiusField) | yes | yes | dimOrArray per-corner | WORKING (P1) |
| Border Color | `contentBoxBorderColor` | Content box | yes | yes | yes | regex color | WORKING |
| Border Width | `contentBoxBorderWidth` | Content box | yes | yes | yes | regex CSS dim | WORKING |
| Shadow | `contentBoxShadow*` | Content box | yes (P2: ShadowField preset+custom) | yes (P2) | yes (P2) | preset+custom validated | WORKING (P2) |
| Padding | `contentBoxPadding` | Content box | yes (P1: BoxSpacingField per-side) | yes | yes | dimOrArray per-side | WORKING (P1) |

### Accessibility

| Label | Data Key | Applies To | Editor | Preview | Blade | Validation | Status |
|-------|----------|------------|--------|---------|-------|------------|--------|
| Background Alt Text | `alt` | Whole Hero aria-label | yes | no (admin only) | yes | string max:255 | WORKING |

### Unused / Dead

| Label | Data Key | Status | Notes |
|-------|----------|--------|-------|
| Media Loading | `mediaLoading` | DEAD_CONTROL | Validated but never used in Editor, Preview, or Blade |
| Parallax Speed | `bg_parallax_speed` | DEAD_CONTROL | Validated but parallax scroll effect not implemented |

---

## 2. Misleading Controls (Pre-P0)

### 2.1 Section Labels Missing Target Context

**Problem**: Section dividers like "Layout", "Typography", "Border & Shadow", "Content Box" did not specify what element they apply to. A user seeing "Border Width" under "Border & Shadow" could not tell if it applies to the whole hero section, the content box, or the title.

**P0 Fix**: All section dividers now include target:
- "Layout" → "Layout — Whole Hero Section"
- "Typography" → "Typography — Title & Subtitle"
- "Border & Shadow" → "Border & Shadow — Whole Hero Section"
- "Content Box" → "Content Box — Text Readability Layer"
- "Call to Action" → "Call to Action — CTA Button"

### 2.2 Background Location

**Problem**: `BackgroundEditor` sits between Layout and Typography sections. Users could assume the background control affects only the content area or heading area, not the entire hero section.

**Status**: BackgroundEditor is its own collapsible component with clear "Background" header. The P0 fix adds "Applies to whole Hero section" helper text. Further separation is P1.

### 2.3 Headline Color Without Subtitle Color

**Problem**: `headlineColor` existed for the title, but there was no `subtitleColor`. The subtitle only got adaptive color (white on background, inherit otherwise). Users could not independently style the subtitle color.

**P0 Fix**: Added `subtitleColor` field in Editor, Preview, and Blade.

### 2.4 "Auto Text Color" Scope

**Problem**: The `adaptiveTextColor` toggle affects both title and subtitle color resolution, but was labeled simply "Auto Text Color" without explaining scope.

**P0 Fix**: Added helper text: "When enabled, title and subtitle automatically use light text on dark backgrounds."

### 2.5 Layout Controls — Verified Working

**Reviewed**: Text Alignment, Vertical Position, Section Height, Content Max Width all visibly affect Preview and Blade output. These are not misleading — they work as labeled. No layout controls were found that change in UI but do not visibly change the site.

### 2.6 Whole-Block Opacity — Not Applicable to Hero

**Reviewed**: Hero does not expose a whole-block opacity control in its Editor.tsx. The shared VisualPanel "Block Opacity" slider (which fades all content) is a known dead control documented in BLOCK-PROPERTIES-AUDIT.md and BACKGROUND-SYSTEM-AUDIT.md, but it is not part of Hero's own control set. Hero only exposes Content Box Opacity (which correctly affects only the content box background layer, not text). No misleading opacity control exists within Hero's own editor.

### 2.7 Content Box Padding — Single Value

**Problem**: `contentBoxPadding` accepts a single CSS value (e.g., `2rem`). Users expect per-side control (top/right/bottom/left). CSS shorthand like `1rem 2rem` works but is not discoverable.

**Status**: Documented as limitation. P1 will implement BoxSpacingField with per-side controls.

### 2.8 Border Radius — Single Value

**Problem**: All border radius fields (`sectionBorderRadius`, `contentBoxBorderRadius`, `ctaBorderRadius`) accept a single CSS value. Users expect per-corner control. CSS shorthand like `10px 20px 0 0` works but is not discoverable.

**P1 Fix**: CornerRadiusField implemented with per-corner controls, presets (None/Small/Medium/Large/Pill), and linked/unlinked toggle. Applied to section, content box, and CTA border radius.

### 2.9 Content Box Shadow — Preset Only

**Problem**: Content Box shadow used preset-only SelectField (sm/md/lg), while Section Shadow had full custom shadow builder (ShadowField with x/y/blur/spread/color/opacity/inset). Inconsistent capability.

**P2 Fix**: Content Box shadow upgraded to full ShadowField with preset+custom modes, matching section shadow.

---

## 3. Missing Element-Level Controls

### Title (currently available → missing)

| Control | Available | Missing |
|---------|-----------|---------|
| Heading tag (h1/h2/h3) | WORKING | — |
| Color | WORKING (`headlineColor`) | — |
| Font size | WORKING (`headlineSize`) | — |
| Font weight | WORKING (`headlineWeight`) | — |
| Line height | WORKING (P2: `headlineLineHeight`) | — |
| Letter spacing | WORKING (P2: `headlineLetterSpacing`) | — |
| Text transform | WORKING (P2: `headlineTextTransform`) | — |
| Margin per side | — | MISSING (P1 via BoxSpacingField) |
| Padding per side | — | MISSING (FUTURE) |
| Background behind title | — | MISSING (FUTURE) |
| Text shadow | WORKING (P3: `headlineTextShadow`) | — |

### Subtitle (currently available → missing)

| Control | Available | Missing |
|---------|-----------|---------|
| Color | WORKING (P0: `subtitleColor`) | — |
| Font size | WORKING (`subheadlineSize`) | — |
| Font weight | WORKING (P1: `subheadlineWeight`) | — |
| Line height | WORKING (P2: `subheadlineLineHeight`) | — |
| Letter spacing | WORKING (P2: `subheadlineLetterSpacing`) | — |
| Text transform | WORKING (P2: `subheadlineTextTransform`) | — |
| Margin per side | — | MISSING (P1 via BoxSpacingField) |
| Padding per side | — | MISSING (FUTURE) |
| Background behind subtitle | — | MISSING (FUTURE) |

### Content Box (currently available → missing)

| Control | Available | Missing |
|---------|-----------|---------|
| Enabled toggle | WORKING | — |
| Background color | WORKING | — |
| Opacity | WORKING | — |
| Padding (per-side) | WORKING (P1: `contentBoxPadding` via BoxSpacingField) | — |
| Margin per side | — | MISSING (FUTURE) |
| Border width | WORKING | — |
| Border color | WORKING | — |
| Border style | — | MISSING (FUTURE) |
| Border radius (per-corner) | WORKING (P1: `contentBoxBorderRadius` via CornerRadiusField) | — |
| Shadow (preset+custom) | WORKING (P2: upgraded to ShadowField) | — |

### CTA Button (currently available → missing)

| Control | Available | Missing |
|---------|-----------|---------|
| Variant | WORKING | — |
| Size | WORKING | — |
| Alignment | WORKING | — |
| Background color | WORKING | — |
| Text color | WORKING | — |
| Border color | WORKING | — |
| Border width | WORKING | — |
| Border radius (per-corner) | WORKING (P1: `ctaBorderRadius` via CornerRadiusField) | — |
| Margin per side | — | MISSING (FUTURE) |
| Padding per side | — | MISSING (FUTURE — size presets cover most cases) |
| Shadow (preset+custom) | WORKING (P2: `ctaShadow*` via ShadowField) | — |
| Hover colors | WORKING (P3: `ctaHoverBgColor`, `ctaHoverTextColor`, `ctaHoverBorderColor`) | — |

### Whole Hero Block (currently available → missing)

| Control | Available | Missing |
|---------|-----------|---------|
| Background (full system) | WORKING | — |
| Overlay | WORKING | — |
| Section height | WORKING (responsive) | — |
| Vertical position | WORKING | — |
| Text alignment | WORKING (responsive) | — |
| Content max width | WORKING (responsive) | — |
| Border width/color/style | WORKING | — |
| Border radius (per-corner) | WORKING (P1: `sectionBorderRadius` via CornerRadiusField) | — |
| Shadow (preset+custom) | WORKING | — |
| Margin per side | — | MISSING (shared properties handle this via block.style) |
| Padding per side | — | MISSING (shared properties handle this via block.style) |
| Width / max-width | — | MISSING (FUTURE) |
| Responsive preview | WORKING (P3: BuilderCanvas device toggle) | — |

---

## 4. Global Field Requirements

These field components should be implemented once and reused across all blocks.

### 4.1 BoxSpacingField

**Purpose**: Unified padding and margin control with per-side values.

**Fields**:
- `paddingTop`, `paddingRight`, `paddingBottom`, `paddingLeft`
- `marginTop`, `marginRight`, `marginBottom`, `marginLeft`
- Linked/unlinked toggle (all sides same vs independent)
- Unit support: px, rem, em, %
- Clear/reset button
- Helper text showing which element is affected

**Data format**:
```json
{
  "paddingTop": "1rem",
  "paddingRight": "2rem",
  "paddingBottom": "1rem",
  "paddingLeft": "2rem"
}
```

**Backward compatibility**: If a single `padding` value exists, treat it as all-sides shorthand. Per-side keys take precedence.

### 4.2 CornerRadiusField

**Purpose**: Per-corner border radius with presets.

**Fields**:
- `topLeft`, `topRight`, `bottomRight`, `bottomLeft`
- Linked/unlinked toggle
- Preset shortcuts: None (0), Small (0.25rem), Medium (0.5rem), Large (1rem), Pill (50%)
- Helper text: "50% creates a circle/pill shape when applied to a square/rectangle element"

**Data format**:
```json
{
  "topLeft": "10px",
  "topRight": "20px",
  "bottomRight": "0px",
  "bottomLeft": "0px"
}
```

**Backward compatibility**: If a single `borderRadius` string exists, treat it as uniform radius. Per-corner keys take precedence.

### 4.3 ShadowField (already exists)

**Purpose**: Box shadow with preset and custom modes.

**Current implementation**: `resources/admin/src/components/editor/fields/ShadowField.tsx`

**Modes**:
- **Preset**: none, subtle, medium, large, glow (Hero), sm, md, lg (BaseBlock compat)
- **Custom**: x, y, blur, spread, color (hex), opacity (0–100), inset (boolean)

**Status**: WORKING for section shadow, Content Box shadow (P2 upgraded), and CTA shadow (P2 added).

### 4.4 TypographyField

**Purpose**: Unified typography controls for text elements.

**Fields**:
- Tag selector (h1/h2/h3/h4/h5/h6/p/span) — only when relevant
- Font size (text input with unit)
- Font weight (select: 400–900)
- Line height (text input)
- Letter spacing (text input)
- Color (color picker)
- Text alignment (select: left/center/right/justify) — only when relevant
- Text transform (select: none/uppercase/lowercase/capitalize) — only when relevant

**Data format**:
```json
{
  "tag": "h1",
  "fontSize": "2.5rem",
  "fontWeight": "700",
  "lineHeight": "1.2",
  "letterSpacing": "0.02em",
  "color": "#333",
  "textTransform": "none"
}
```

### 4.5 ResponsivePreview

**Purpose**: Preview the editor canvas at different viewport widths.

**Modes**:
- Desktop (default, full canvas width)
- Tablet (1024px max-width)
- Mobile (640px max-width)

**Behavior**:
- Changes the editor canvas width, NOT the stored data
- Responsive data overrides are separate (already working for textAlignment, sectionHeight, contentMaxWidth)
- Visual indicator showing current preview mode

**Status**: WORKING (P3). Implemented in `BuilderCanvas.tsx` with desktop/tablet/mobile toggle. Constrains canvas `max-width` with smooth transition. Does not change stored data.

---

## 5. Recommended Implementation Plan

### P0 — Clarity and Safety (this audit)

- [x] Relabel all Hero Editor section dividers with target element descriptions
- [x] Add helper text to Heading Tag explaining SEO (h1/h2/h3)
- [x] Add `subtitleColor` field (Editor, Preview, Blade, validation)
- [x] Add helper text to `adaptiveTextColor` explaining scope
- [x] Add helper text to spacing/border/shadow controls indicating target element
- [x] Ensure headlineColor works end-to-end (verified: WORKING)
- [x] Document all misleading, dead, and missing controls

### P1 — Spacing and Radius Fields

- [x] Implement `BoxSpacingField` as global reusable component
- [x] Use for Hero Content Box padding (per-side)
- [ ] Use for Hero section margin via shared properties
- [x] Implement `CornerRadiusField` as global reusable component
- [x] Use for Hero section, Content Box, CTA border radius
- [x] Ensure Preview and Blade output match for per-side/per-corner values
- [ ] Add Content Box border style control (solid/dashed/dotted)
- [x] Add subtitle weight control (`subheadlineWeight`)
- [x] Relabel "Headline/Subheadline" to "Title/Subtitle" in Editor
- [x] Add `spacingHelpers.ts` with backward-compatible resolvers
- [x] Add `dimOrArray()` validation with key whitelist

### P2 — Typography and Shadow Enhancement

- [ ] Implement `TypographyField` as global reusable component (individual fields added directly for now)
- [x] Add Hero title line-height, letter-spacing, text-transform
- [x] Add Hero subtitle line-height, letter-spacing, text-transform
- [x] Upgrade Content Box shadow from preset-only to full `ShadowField`
- [x] Add CTA shadow control
- [x] Ensure Preview and Blade parity for all new fields

### P3 — Responsive and Advanced

- [x] Implement responsive editor canvas preview (desktop/tablet/mobile) in BuilderCanvas
- [x] Per-element responsive overrides (headlineSize, subheadlineSize at tablet/mobile breakpoints)
- [ ] Title/subtitle individual background panels
- [x] Text shadow for title (`headlineTextShadow`) and subtitle (`subheadlineTextShadow`)
- [x] CTA hover state controls (`ctaHoverBgColor`, `ctaHoverTextColor`, `ctaHoverBorderColor`) via scoped CSS
- [ ] Full BaseBlock adoption across all 69 blocks (phased rollout — out of scope for Hero pilot)

---

## 6. Data Key Compatibility Notes

- All new fields use `sometimes` validation — old data without these keys remains valid
- `subtitleColor` is nullable and optional — absence means adaptive/inherit behavior
- No existing data keys were renamed or removed
- Legacy `backgroundImage` fallback preserved
- `mediaLoading` and `bg_parallax_speed` remain in validation but are documented as DEAD_CONTROL
- P1 fields (`sectionBorderRadius`, `contentBoxBorderRadius`, `ctaBorderRadius`, `contentBoxPadding`) accept both legacy string and new per-side/per-corner object via `dimOrArray()` validation
- P2 typography fields (`headlineLineHeight`, `headlineLetterSpacing`, `headlineTextTransform`, `subheadlineLineHeight`, `subheadlineLetterSpacing`, `subheadlineTextTransform`) are all optional — absence means browser default
- P2 shadow fields (`contentBoxShadowMode`, `contentBoxShadowCustom`, `ctaShadowMode`, `ctaShadow`, `ctaShadowCustom`) are all optional — old preset-only `contentBoxShadow` values still work
- P3 text shadow fields (`headlineTextShadow`, `subheadlineTextShadow`) are optional CSS text-shadow strings, validated against safe character set
- P3 CTA hover fields (`ctaHoverBgColor`, `ctaHoverTextColor`, `ctaHoverBorderColor`) are optional — rendered via scoped CSS `:hover` rules in Blade, not inline styles
- P3 responsive font size overrides (`responsive.tablet.headlineSize`, `responsive.tablet.subheadlineSize`, `responsive.mobile.headlineSize`, `responsive.mobile.subheadlineSize`) follow existing responsive override pattern
- P3 responsive canvas preview affects editor canvas width only, not stored data
