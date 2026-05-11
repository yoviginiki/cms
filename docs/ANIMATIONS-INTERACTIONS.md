# Animations & Interactions

> **Status**: Phase 5 — basic entrance animations implemented.
> **Date**: 2026-05-10
> **Scope**: CSS entrance animations for all blocks via shared property engine.

---

## 1. Overview

All blocks support entrance animations via the shared `block.animation` property. Animations are configured in the Animation panel (part of BlockSettings) and rendered by:
- **Editor preview**: `buildAnimationStyle()` in `blockStyles.ts` → inline CSS on SortableBlock wrapper
- **Published output**: `BlockStyle::buildStyle()` in PHP → inline CSS on block element

---

## 2. Supported Animations

| Animation | CSS Keyframe | Description |
|-----------|-------------|-------------|
| `none` | — | No animation (default) |
| `fade` | `block-fade` | Fade in (opacity 0→1) |
| `slide-up` | `block-slide-up` | Slide up + fade (translateY 30px→0) |
| `slide-down` | `block-slide-down` | Slide down + fade (translateY -30px→0) |
| `slide-left` | `block-slide-left` | Slide from left + fade (translateX -30px→0) |
| `slide-right` | `block-slide-right` | Slide from right + fade (translateX 30px→0) |
| `zoom` | `block-zoom` | Zoom in + fade (scale 0.9→1) |
| `scale-in` | `block-scale-in` | Scale in + fade (scale 0.85→1) |

## 3. Timing Controls

| Setting | Key | Default | Range |
|---------|-----|---------|-------|
| Duration | `animation.duration` | 600ms | 50–3000ms |
| Delay | `animation.delay` | 0ms | 0–5000ms |
| Easing | `animation.easing` | `ease-out` | linear, ease, ease-in, ease-out, ease-in-out |

All values are clamped to safe ranges. Invalid values fall back to defaults.

## 4. Data Model

Animations are stored in `block.animation` (shared property, not `block.data`):

```json
{
  "animation": {
    "entrance": "slide-up",
    "duration": 600,
    "delay": 0,
    "easing": "ease-out",
    "trigger": "on-load"
  }
}
```

This applies to ALL block types via the SortableBlock wrapper and BlockStyle helper.

## 5. Reduced Motion

Both admin and published CSS respect `prefers-reduced-motion: reduce`:
- Admin: `resources/admin/src/index.css` sets `animation-duration: 0.01ms !important`
- Published: `resources/css/app.css` targets `[style*="animation-name"]` with same override

Users with reduced motion preferences see no visible animation.

## 6. CSS Locations

| Context | File | Purpose |
|---------|------|---------|
| Admin editor | `resources/admin/src/index.css` | Keyframes for preview |
| Published pages | `resources/css/app.css` | Keyframes for published output |
| Admin TS | `resources/admin/src/lib/blockStyles.ts` | `buildAnimationStyle()` for preview |
| Published PHP | `app/Support/Blocks/BlockStyle.php` | `buildStyle()` for published output |

## 7. Editor UI

The Animation panel in BlockSettings provides:
- Entrance animation dropdown (all 8 options)
- Duration input (ms)
- Delay input (ms)
- Easing dropdown (5 options)
- Trigger selector (on-load only; on-scroll is future)

The panel is available for ALL blocks, not just Hero.

## 8. Security

- Animation names are allowlisted — unknown values are rejected
- Easing values are allowlisted — unknown values fall back to `ease-out`
- Duration/delay are clamped to numeric ranges — no arbitrary CSS
- No user-provided CSS strings are output
- `animation-fill-mode: both` is always used

## 9. Limitations — Not Implemented Yet

| Feature | Status | Notes |
|---------|--------|-------|
| Scroll into view trigger | Future | Needs IntersectionObserver JS on published pages |
| Parallax | Future | Needs scroll position tracking |
| Hover effects | Future | Declared in AnimationProps but not rendered |
| Click interactions | Future | Not in current architecture |
| Text splitting | Future | Needs per-character/word wrapping |
| Stagger animations | Future | Needs child-level delay calculation |
| Timelines | Future | Needs sequencing engine |
| GSAP / Framer Motion | Not planned | CSS animations are sufficient for current scope |

## 10. Future Roadmap

1. **Scroll-triggered animations** — IntersectionObserver on published pages
2. **Hover effects** — opacity, lift, glow (already in AnimationProps type)
3. **Exit animations** — fade out, slide out on scroll past
4. **Stagger** — sequential animation of child elements
5. **Custom easing** — cubic-bezier editor
