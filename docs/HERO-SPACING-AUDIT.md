# Hero Spacing Audit

> **Date**: 2026-05-11
> **Status**: Audit complete. P0 Preview/Blade parity mismatches fixed.

---

## 1. Summary

The Hero block has **minimal spacing controls**. Most spacing is hardcoded in CSS classes (Preview) and inline styles (Blade). The shared SpacingPanel exists and works for the outer block wrapper, but Hero-specific inner element spacing is not configurable.

| Category | Status |
|----------|--------|
| Whole section padding/margin (shared) | **WORKING** — via SpacingPanel → `block.style.spacing` |
| Content box padding | **WORKING** — `contentBoxPadding` field in Editor |
| Content max width | **WORKING** — `contentMaxWidth` field in Editor |
| Title margin-bottom | **HARDCODED** — `mb-2` (Preview) / `1rem` (Blade) |
| Subtitle margin-bottom | **HARDCODED** — `mb-5` (Preview) / `2rem` (Blade) |
| Content container padding | **HARDCODED** — `2rem 1.5rem` (Preview) / `2rem` (Blade) |
| CTA group margin-top | **MISSING** — no control |
| CTA button padding | **PARTIAL** — via `ctaSize` preset only (sm/md/lg) |
| Gap between elements | **MISSING** — no control |
| Responsive spacing | **MISSING** — not in responsive overrides |

---

## 2. Whole Hero Section Spacing

The **shared SpacingPanel** (available for ALL blocks in BlockSettings) manages `block.style.spacing`:

| Property | Editor UI | Key | Preview | Blade | Validated | Status |
|----------|----------|-----|---------|-------|-----------|--------|
| Padding Top | SpacingPanel | `style.spacing.paddingTop` | SortableBlock wrapper | `BlockStyle::buildStyle` | `safeDim()` | **WORKING** |
| Padding Right | SpacingPanel | `style.spacing.paddingRight` | SortableBlock wrapper | `BlockStyle::buildStyle` | `safeDim()` | **WORKING** |
| Padding Bottom | SpacingPanel | `style.spacing.paddingBottom` | SortableBlock wrapper | `BlockStyle::buildStyle` | `safeDim()` | **WORKING** |
| Padding Left | SpacingPanel | `style.spacing.paddingLeft` | SortableBlock wrapper | `BlockStyle::buildStyle` | `safeDim()` | **WORKING** |
| Margin Top | SpacingPanel | `style.spacing.marginTop` | SortableBlock wrapper | `BlockStyle::buildStyle` | `safeDim()` | **WORKING** |
| Margin Right | SpacingPanel | `style.spacing.marginRight` | SortableBlock wrapper | `BlockStyle::buildStyle` | `safeDim()` | **WORKING** |
| Margin Bottom | SpacingPanel | `style.spacing.marginBottom` | SortableBlock wrapper | `BlockStyle::buildStyle` | `safeDim()` | **WORKING** |
| Margin Left | SpacingPanel | `style.spacing.marginLeft` | SortableBlock wrapper | `BlockStyle::buildStyle` | `safeDim()` | **WORKING** |
| Gap | SpacingPanel | `style.spacing.gap` | NOT APPLIED | NOT APPLIED | — | **DEAD_CONTROL** |

**Note**: SpacingPanel spacing is applied to the **outer SortableBlock wrapper** div, which wraps the Hero Preview. This means padding/margin affects the space AROUND the Hero section, not inside it. Gap is defined in the panel but never consumed by `buildBlockWrapperStyle()` or `BlockStyle::buildStyle()`.

---

## 3. Inner Element Spacing

### 3a. Content Container (hero-content div)

| Property | Editor UI | Key | Preview Value | Blade Value | Status |
|----------|----------|-----|---------------|-------------|--------|
| Padding (contentBox enabled) | TextField | `contentBoxPadding` | `contentBoxPadding` var | `$cbPadding` | **WORKING** |
| Padding (contentBox disabled) | None | — | `2rem 1.5rem` (hardcoded) | `2rem` (hardcoded) | **HARDCODED** |
| Max Width | TextField | `contentMaxWidth` | inline `maxWidth` | inline `max-width` | **WORKING** |
| Margin | None | — | — | — | **MISSING** |

**Preview/Blade mismatch**: When contentBox is disabled, Preview uses `2rem 1.5rem` (different vertical/horizontal) but Blade uses `2rem` (uniform). This is a minor visual difference.

### 3b. Eyebrow

No eyebrow field exists in the Hero block. **N/A**.

### 3c. Title / Headline

| Property | Editor UI | Key | Preview Value | Blade Value | Status |
|----------|----------|-----|---------------|-------------|--------|
| Margin Bottom | None | — | `mb-2` (Tailwind = `0.5rem`) | `margin-bottom:1rem` | **HARDCODED + MISMATCH** |
| Margin Top | None | — | — | — | **MISSING** |
| Padding | None | — | — | — | **MISSING** |

**Preview/Blade mismatch**: Preview uses Tailwind `mb-2` (0.5rem), Blade uses `margin-bottom:1rem`. Title has different spacing in editor vs published output.

### 3d. Subtitle / Subheadline

| Property | Editor UI | Key | Preview Value | Blade Value | Status |
|----------|----------|-----|---------------|-------------|--------|
| Margin Bottom | None | — | `mb-5` (Tailwind = `1.25rem`) | `margin-bottom:2rem` | **HARDCODED + MISMATCH** |
| Margin Top | None | — | — | — | **MISSING** |
| Padding | None | — | — | — | **MISSING** |

**Preview/Blade mismatch**: Preview uses `mb-5` (1.25rem), Blade uses `margin-bottom:2rem`. Subtitle has different bottom spacing in editor vs published output.

### 3e. CTA / Button Group

| Property | Editor UI | Key | Preview Value | Blade Value | Status |
|----------|----------|-----|---------------|-------------|--------|
| Margin Top | None | — | — | — | **MISSING** |
| Gap between buttons | None | — | — | — | **MISSING** (only one CTA supported) |
| Alignment | SelectField | `ctaAlign` | wrapper text-align class | wrapper `text-align` | **WORKING** |

### 3f. Individual CTA Button

| Property | Editor UI | Key | Preview Value | Blade Value | Status |
|----------|----------|-----|---------------|-------------|--------|
| Padding (via size) | SelectField | `ctaSize` | Tailwind classes | inline `padding` | **PARTIAL** |
| Padding X (sm) | — | — | `px-3` (0.75rem) | `0.375rem 1rem` | **MISMATCH** |
| Padding Y (sm) | — | — | `py-1.5` (0.375rem) | (combined) | **MISMATCH** |
| Padding X (md) | — | — | `px-5` (1.25rem) | `0.75rem 2rem` | **MISMATCH** |
| Padding Y (md) | — | — | `py-2.5` (0.625rem) | (combined) | **MISMATCH** |
| Padding X (lg) | — | — | `px-7` (1.75rem) | `1rem 2.5rem` | **MISMATCH** |
| Padding Y (lg) | — | — | `py-3.5` (0.875rem) | (combined) | **MISMATCH** |
| Custom padding | None | — | — | — | **MISSING** |
| Border Radius | TextField | `ctaBorderRadius` | inline style | inline style | **WORKING** |
| Border Width | TextField | `ctaBorderWidth` | inline style | inline style | **WORKING** |

**CTA padding mismatch**: Preview uses Tailwind utility classes which produce different values than Blade's inline `padding`. For example, `md` size:
- Preview: `px-5` = 1.25rem horizontal, `py-2.5` = 0.625rem vertical
- Blade: `padding: 0.75rem 2rem` = 0.75rem vertical, 2rem horizontal

### 3g. Content Box

| Property | Editor UI | Key | Preview | Blade | Validated | Status |
|----------|----------|-----|---------|-------|-----------|--------|
| Padding | TextField | `contentBoxPadding` | Yes | Yes | regex dim | **WORKING** |
| Border Radius | TextField | `contentBoxBorderRadius` | Yes | Yes | regex dim | **WORKING** |
| Border Width | TextField | `contentBoxBorderWidth` | Yes | Yes | regex dim | **WORKING** |
| Margin | None | — | — | — | — | **MISSING** |

---

## 4. Responsive Spacing

| Property | Responsive Override | Status |
|----------|-------------------|--------|
| Section padding/margin | Not in responsive | **MISSING** |
| Content box padding | Not in responsive | **MISSING** |
| Content max width | Yes (`responsive.tablet/mobile.contentMaxWidth`) | **WORKING** |
| Title margin | Not configurable | **MISSING** |
| Subtitle margin | Not configurable | **MISSING** |

---

## 5. Preview vs Blade Mismatches — P0 FIXED

All P0 mismatches have been aligned. Preview now uses the same values as Blade:

| Element | Previous Preview | Blade | Aligned To | Status |
|---------|-----------------|-------|-----------|--------|
| Content padding (no box) | `2rem 1.5rem` | `2rem` | `2rem` | **FIXED** |
| Title margin-bottom | `mb-2` (0.5rem) | `1rem` | `1rem` (inline style) | **FIXED** |
| Subtitle margin-bottom | `mb-5` (1.25rem) | `2rem` | `2rem` (inline style) | **FIXED** |
| CTA sm padding | `px-3 py-1.5` | `0.375rem 1rem` | `0.375rem 1rem` (inline) | **FIXED** |
| CTA md padding | `px-5 py-2.5` | `0.75rem 2rem` | `0.75rem 2rem` (inline) | **FIXED** |
| CTA lg padding | `px-7 py-3.5` | `1rem 2.5rem` | `1rem 2.5rem` (inline) | **FIXED** |

Changes made in `Preview.tsx` only. No Blade, schema, or validation changes.

---

## 6. Dead Controls

| Control | Location | Issue |
|---------|----------|-------|
| SpacingPanel "Gap" input | SpacingPanel.tsx | Value saved but never consumed by `buildBlockWrapperStyle()` or `BlockStyle::buildStyle()` |

---

## 7. Hardcoded Spacing Values

| Location | Value | What it controls |
|----------|-------|-----------------|
| Preview.tsx | `'2rem'` | Content area default padding (no content box) — aligned with Blade |
| Preview.tsx | `marginBottom: '1rem'` | Title bottom margin — aligned with Blade |
| Preview.tsx | `marginBottom: '2rem'` | Subtitle bottom margin — aligned with Blade |
| Preview.tsx | `0.375rem 1rem` / `0.75rem 2rem` / `1rem 2.5rem` | CTA button padding by size — aligned with Blade |
| hero.blade.php | `padding:2rem` | Content area default padding |
| hero.blade.php | `margin-bottom:1rem` | Title bottom margin |
| hero.blade.php | `margin-bottom:2rem` | Subtitle bottom margin |
| hero.blade.php | `0.375rem 1rem` / `0.75rem 2rem` / `1rem 2.5rem` | CTA button padding by size |

---

## 8. Validation / Sanitization

| Field | Validated | Sanitizer | Safe |
|-------|-----------|-----------|------|
| `contentBoxPadding` | `regex:/^\d+(\.\d+)?(px\|rem\|em\|%)$/` | PHP regex | Yes |
| `contentBoxBorderWidth` | `regex:/^\d+(\.\d+)?(px\|rem\|em)$/` | PHP regex | Yes |
| `contentBoxBorderRadius` | `regex:/^\d+(\.\d+)?(px\|rem\|em\|%)$/` | PHP regex | Yes |
| `ctaBorderWidth` | `regex:/^\d+(\.\d+)?(px\|rem\|em)$/` | PHP regex | Yes |
| `ctaBorderRadius` | `regex:/^\d+(\.\d+)?(px\|rem\|em\|%)$/` | PHP regex | Yes |
| Shared spacing (SpacingPanel) | `safeDim()` in blockStyles.ts + BlockStyle.php | allowlist regex | Yes |
| Hardcoded values | N/A — not user input | — | Yes (safe strings) |

No unsafe raw CSS dimension inputs found.

---

## 9. Recommended Implementation Plan

### P0: Fix Preview/Blade Mismatches (no schema change needed)

These are pure CSS alignment fixes — no new data keys or validation required:

1. **Title margin-bottom**: Align Preview `mb-2` with Blade `1rem` → use consistent `1rem`
2. **Subtitle margin-bottom**: Align Preview `mb-5` with Blade `2rem` → use consistent `2rem`
3. **Content default padding**: Align Preview `2rem 1.5rem` with Blade `2rem` → use consistent `2rem`
4. **CTA padding by size**: Align Preview Tailwind classes with Blade inline values → use consistent values

### P1: Add Missing Essential Spacing Controls

These require new data keys + validation + Editor/Preview/Blade updates:

5. **Title margin-bottom control** — `titleMarginBottom` (default: `1rem`)
6. **Subtitle margin-bottom control** — `subtitleMarginBottom` (default: `2rem`)
7. **CTA group margin-top control** — `ctaMarginTop` (default: `0`)
8. **Content area padding control** (when contentBox disabled) — could reuse `contentBoxPadding` or add separate key

### P2: Refinements

9. **CTA custom padding** — `ctaPaddingX`, `ctaPaddingY` (override size presets)
10. **Content gap** — `contentGap` for vertical spacing between title/subtitle/CTA
11. **Responsive padding** — add `contentBoxPadding` to responsive overrides

### P3: Advanced

12. **Per-side content box margin**
13. **Individual element padding controls** (title padding, subtitle padding)
14. **SpacingPanel gap fix** — consume gap value in wrapper styles
15. **Full responsive spacing** — all spacing properties in responsive overrides
