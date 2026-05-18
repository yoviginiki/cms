# Theme / Responsive Audit — 2026-05-18

## Summary

**Status: PARTIAL** — core token system is solid (92% parity), responsive architecture exists but is limited to Hero pilot. P0 fixes applied to align breakpoints, sync canvas device, fix shadow mismatches, add mobile column stacking, and fill missing token defaults.

---

## 1. Current Theme Architecture

The CMS uses a **multi-layered design token system**:

| Layer | File | Purpose |
|-------|------|---------|
| W3C Design Tokens | `Theme.document` (JSON) | Source of truth for theme values |
| DesignTokenGenerator | `app/Domain/Theme/Services/DesignTokenGenerator.php` | Resolves tokens to CSS custom properties |
| Admin DaisyUI themes | `resources/admin/src/index.css` | 4 themes (cms-admin, cms-admin-light, cms-light, cms-dark) |
| Block style builder (TS) | `resources/admin/src/lib/blockStyles.ts` | Generates React.CSSProperties for editor preview |
| Block style builder (PHP) | `app/Support/Blocks/BlockStyle.php` | Generates inline CSS for published Blade output |
| Responsive values | `resources/admin/src/lib/responsiveValues.ts` | Breakpoint-aware data read/write |
| Shadow styles | `resources/admin/src/lib/shadowStyles.ts` | Shadow preset/custom builder |
| Spacing helpers | `resources/admin/src/lib/spacingHelpers.ts` | Per-side/per-corner resolution |

---

## 2. Token Inventory

### Colors — READY
| Token | Default | Source |
|-------|---------|--------|
| color-primary | #3b82f6 | DesignTokenGenerator |
| color-heading | #0f172a | DesignTokenGenerator |
| color-text | #1e293b | DesignTokenGenerator |
| color-text-muted | #64748b | DesignTokenGenerator |
| color-link | #3b82f6 | DesignTokenGenerator |
| color-bg | #ffffff | DesignTokenGenerator |
| color-bg-alt | #f8fafc | DesignTokenGenerator |
| color-bg-raised | #ffffff | DesignTokenGenerator |
| color-bg-inverse | #0f172a | DesignTokenGenerator |
| color-border | #e2e8f0 | DesignTokenGenerator |
| color-border-strong | #94a3b8 | DesignTokenGenerator |
| + 10 more semantic colors | Various | DesignTokenGenerator |

### Typography — READY (after P0 fix)
| Token | Default | Status |
|-------|---------|--------|
| font-heading | Inter + system fallback | READY |
| font-body | Inter + system fallback | READY |
| font-mono | SF Mono + monospace | READY |
| font-size-xs | 0.75rem | READY (P0 added) |
| font-size-sm | 0.875rem | READY |
| font-size-base | clamp(14px, 1vw+12px, 18px) | READY (fluid) |
| font-size-lg | 1.125rem | READY |
| font-size-xl-5xl | 1.25rem-3rem | READY (P0 added 4xl/5xl) |
| font-weight | 400/500/700 | READY |
| line-height | tight(1.25)/normal(1.6)/relaxed(1.8) | READY |

### Spacing — READY
10-step scale: space-1 (4px) through space-16 (64px).

### Radius — READY (after P0 fix)
| Token | Default | Status |
|-------|---------|--------|
| border-radius-none | 0px | READY (P0 added) |
| border-radius-sm | 4px | READY |
| border-radius-md | 8px | READY |
| border-radius-lg | 12px | READY |
| border-radius-xl | 16px | READY (P0 added) |
| border-radius-full | 9999px | READY |

### Shadows — READY (after P0 fix)
| Preset | CSS | TS | PHP | Status |
|--------|-----|-----|------|--------|
| sm | Y | Y | Y | READY |
| md | Y | Y | Y | READY |
| lg | Y | Y | Y | READY |
| subtle | - | Y (P0) | Y | READY |
| medium | - | Y (P0) | Y | READY |
| large | - | Y (P0) | Y | READY |
| glow | - | Y (P0) | Y | READY |

### Containers — READY
| Token | Default |
|-------|---------|
| container-width | 1200px |
| container-padding | 24px |
| grid-gap | 24px |

---

## 3. Breakpoint Inventory

### Canonical breakpoints (P0 aligned)

| Device | Range | Media Query |
|--------|-------|-------------|
| Desktop | >= 1024px | `@media (min-width: 1024px)` |
| Tablet | 768px - 1023px | `@media (max-width: 1023px)` |
| Mobile | <= 767px | `@media (max-width: 767px)` |

### Source: `resources/admin/src/lib/breakpoints.ts`

### Editor canvas widths
| Device | Width |
|--------|-------|
| Desktop | 100% (full) |
| Tablet | 768px |
| Mobile | 390px |

### Files aligned to canonical breakpoints (P0)
- `breakpoints.ts` — source of truth
- `BlockStyle.php` — hideOn media queries
- `hero.blade.php` — responsive overrides
- `row.blade.php` — mobile column stacking
- `layout.blade.php` — global mobile rules
- `index.css` — admin responsive (note: also has `@media(max-width:640px)` for small phone admin UI tweaks — intentionally different from canonical 767px mobile breakpoint)

---

## 4. Editor Viewport Behavior

### Before P0
- Canvas device was **local component state** in BuilderCanvas (disconnected from block editors)
- Clicking Mobile in canvas did NOT switch Hero responsive breakpoint
- User saw mobile canvas but edited desktop values

### After P0
- Canvas device moved to **editorStore** (`canvasDevice` state)
- Hero Editor auto-syncs its responsive breakpoint with canvas device
- Switching to mobile in canvas automatically shows mobile values in Hero fields
- Other blocks still edit base values only (see P1 roadmap)

### Responsive data model
```
block.data = {
  textAlignment: 'center',        // desktop (base)
  responsive: {
    tablet: { textAlignment: 'left' },
    mobile: { textAlignment: 'center' }
  }
}
```

Inheritance: mobile -> tablet -> desktop (cascading fallback)

---

## 5. Preview vs Blade / Frontend Parity

### Overall: 92% parity

| Aspect | Preview (TS) | Blade (PHP) | Match |
|--------|-------------|-------------|-------|
| Spacing (padding/margin) | buildBlockWrapperStyle | BlockStyle::buildStyle | YES |
| Border/radius/shadow | buildBlockWrapperStyle | BlockStyle::buildStyle | YES |
| Animation (entrance) | buildAnimationStyle | BlockStyle::buildStyle | YES |
| Background (bg_*) | buildBackgroundFromData | inline in each blade | YES |
| Overlay | buildOverlayFromData | buildOverlayHtml | YES |
| Shadow presets | 7 presets (P0 fix) | 7 presets | YES (fixed) |
| Text shadow | 5 presets | 5 presets | YES |
| Dimension sanitization | safeDim (auto/0) | safeDim (auto/0) | YES |
| Responsive media queries | NOT rendered | Hero only (5 fields) | NO |
| CTA hover state | NOT rendered | Scoped :hover CSS | NO |
| Row mobile stacking | Uses canvasDevice (P0) | @media(max-width:767px) | YES (fixed) |

### Known mismatches (not fixable without P1+ work)
1. Editor preview does not render responsive CSS media queries
2. CTA hover state not visible in editor
3. Only Hero has responsive field support; other blocks static

---

## 6. Mobile Layout Behavior

### Row/Column system: EXISTS

### Before P0
- Rows used fixed CSS grid regardless of viewport
- Two-column layouts stayed side-by-side on mobile (cramped)
- No media queries in row.blade.php

### After P0
- **Blade**: Scoped `@media(max-width:767px)` forces `grid-template-columns: 1fr` on all rows
- **Preview**: Row Preview reads `canvasDevice` from store, uses `1fr` when mobile
- **Desktop**: Columns remain side-by-side as configured
- **Mobile**: Columns stack vertically automatically

---

## 7. P0 Fixes Implemented

| Fix | Files Changed | Risk |
|-----|---------------|------|
| Canonical breakpoints.ts | NEW: `lib/breakpoints.ts` | None (new file) |
| Canvas device in editorStore | `stores/editorStore.ts`, `BuilderCanvas.tsx` | Low (additive) |
| Hero auto-sync with canvas device | `blocks/hero/Editor.tsx` | Low (additive) |
| Shadow preset parity (added subtle/medium/large/glow) | `lib/blockStyles.ts` | None (backward compatible) |
| Missing token defaults (xs, 4xl, 5xl, none, xl) | `DesignTokenGenerator.php` | None (additive) |
| Mobile column stacking | `row.blade.php`, `row/Preview.tsx` | Low (additive CSS) |
| Breakpoint alignment (1024/1023/767) | `BlockStyle.php`, `hero.blade.php`, `layout.blade.php` | Low (1px shift) |

---

## 8. P1 Roadmap (Next)

- Global ResponsiveField wrapper for SpacingPanel, LayoutPanel, VisualPanel
- Theme token file (single source for all blocks)
- Shared StyleBuilder parity tool
- Global BoxSpacingField, CornerRadiusField, TypographyField with responsive support

## 9. P2 Roadmap

- BaseBlock adoption across core blocks
- Responsive media query output for heading, text, section blocks
- Theme token integration in Blade/static frontend

## 10. P3 Roadmap

- Full responsive editor canvas (render media queries in preview)
- Hover/sticky state preview
- Theme presets and switching
- Default cytechno/ensodo brand theme

---

## Manual Acceptance Checklist

1. **Editor viewport**: Switch Desktop/Tablet/Mobile — canvas width changes visibly
2. **Hero font size**: Set desktop title=72px, switch mobile, set 36px — switch back desktop, still 72px
3. **Hero preview parity**: Switch to mobile — preview shows mobile font size, switch to desktop — shows desktop size
4. **Reset override**: Reset mobile value — mobile inherits desktop again
5. **Frontend**: Publish page — mobile media query affects only mobile
6. **Columns**: Create row with 2 columns — desktop side-by-side, mobile stacked
7. **Note**: Content-box padding is not yet responsive (base value only) — planned for P1
