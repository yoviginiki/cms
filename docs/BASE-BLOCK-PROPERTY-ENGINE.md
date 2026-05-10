# Base Block Property Engine

> **Status**: Phase 2 complete.
> **Date**: 2026-05-09
> **Scope**: Shared property engine for all blocks — spacing, border, shadow, animation, visibility, custom classes.

---

## 1. Overview

The shared BaseBlock property engine provides safe, reusable CSS generation for block settings (spacing, border, shadow, animation, visibility, custom classes) in both editor preview and published output. The editor preview wrapper (`SortableBlock.tsx`) applies shared styles to all blocks automatically. For published Blade output, blocks must explicitly call `BlockStyle` helpers — currently only Hero has adopted this pattern. Other blocks will adopt it as they are repaired per the Block Quality Contract.

---

## 2. Architecture

| Layer | File | Role |
|-------|------|------|
| Frontend helpers | `resources/admin/src/lib/blockStyles.ts` | TypeScript helpers that convert shared block properties to React inline styles |
| Backend helper | `app/Support/Blocks/BlockStyle.php` | PHP helper for Blade CSS generation with safe sanitizers |
| Editor wrapper | `resources/admin/src/components/editor/SortableBlock.tsx` | Calls blockStyles helpers for ALL blocks — applies spacing, border, shadow, opacity, animation preview |
| Published rendering | `resources/views/blocks/*.blade.php` | Blade templates call `BlockStyle::buildStyle()`, `BlockStyle::buildClasses()`, etc. |
| Validation | `app/Domain/Blocks/Definitions/*BlockDefinition.php` | Block definitions provide validation rules; `SanitizationService` strips HTML |

### Data flow

```
Editor panel saves → block.style / block.animation / block.advanced / block.responsive
       ↓                                              ↓
  SortableBlock.tsx                          BuildPageService.php
  reads shared props                         extracts shared props
       ↓                                              ↓
  blockStyles.ts                             BlockStyle.php
  generates inline styles                    generates CSS strings
       ↓                                              ↓
  React preview                              Blade published HTML
```

---

## 3. Supported Shared Properties

| Property | Storage Key | Preview | Published | Validation |
|----------|-------------|---------|-----------|------------|
| Padding top | `block.style.spacing.paddingTop` | Yes | Yes | safeDim |
| Padding right | `block.style.spacing.paddingRight` | Yes | Yes | safeDim |
| Padding bottom | `block.style.spacing.paddingBottom` | Yes | Yes | safeDim |
| Padding left | `block.style.spacing.paddingLeft` | Yes | Yes | safeDim |
| Margin top | `block.style.spacing.marginTop` | Yes | Yes | safeDim |
| Margin right | `block.style.spacing.marginRight` | Yes | Yes | safeDim |
| Margin bottom | `block.style.spacing.marginBottom` | Yes | Yes | safeDim |
| Margin left | `block.style.spacing.marginLeft` | Yes | Yes | safeDim |
| Border width | `block.style.visual.borderWidth` | Yes | Yes | safeDim |
| Border color | `block.style.visual.borderColor` | Yes | Yes | safeColor |
| Border style | `block.style.visual.borderStyle` | Yes | Yes | allowlist |
| Border radius | `block.style.visual.borderRadius` | Yes | Yes | safeDim |
| Box shadow | `block.style.visual.boxShadow` | Yes | Yes | allowlist (sm/md/lg) |
| Opacity | `block.style.visual.opacity` | Yes | Yes | 0-1 range |
| Animation entrance | `block.animation.entrance` | Yes | Yes | allowlist |
| Animation duration | `block.animation.duration` | Yes | Yes | 50-3000ms clamp |
| Animation delay | `block.animation.delay` | Yes | Yes | 0-5000ms clamp |
| Custom class | `block.advanced.customClass` | Yes | Yes | safeClass regex |
| HTML ID | `block.advanced.htmlId` | No (preview) | Yes | safeId regex |
| ARIA label | `block.advanced.ariaLabel` | No (preview) | Yes | string |
| Responsive hideOn | `block.responsive.hideOn` | Badges | Media queries | allowlist |

---

## 4. Explicitly Unsupported / Future

These properties are intentionally NOT handled by the shared engine in Phase 2:

- **Typography overrides from global panel** — Phase 2 does not wire TypographyPanel to the wrapper. Blocks apply their own typography via block-specific content fields.
- **Layout overrides from global panel** — Phase 2 does not wire LayoutPanel to the wrapper. Blocks apply their own layout logic.
- **Background from VisualPanel** — blocks use their own BackgroundEditor (e.g., Hero has a dedicated background system).
- **Responsive per-breakpoint cascade** — Phase 4. Currently only hideOn is supported.
- **Design tokens** — Phase 6. Block properties use raw CSS values, not token references.
- **Symbols / master blocks** — Phase 7. Each block is fully independent.
- **Hover interactions** — requires CSS `:hover` rules which cannot be applied via inline styles.
- **On-scroll animation trigger** — requires Intersection Observer JS runtime not yet implemented.
- **Parallax** — requires scroll-driven JS not yet implemented.
- **Custom CSS** — blocked for security; would require CSS scoping to prevent style injection.

---

## 5. No Dead Controls Rule

**Rule**: If a panel control saves data, at least one of preview or published must render it. Controls that save but don't render must be documented and either implemented or removed.

This prevents confusion where users configure a setting that has no visible effect. Every property must be one of:

- **Rendered** — applied in preview, published, or both
- **Documented as deferred** — explicitly listed below with rationale
- **Removed** — control removed from the panel UI

---

## 6. Current Dead Controls

| Panel | Control | Issue |
|-------|---------|-------|
| AnimationPanel | `hoverEffect` | Saved but no CSS/JS renders it. Requires CSS `:hover` rules (Phase 5). |
| AnimationPanel | `trigger` (on-scroll) | Saved but no Intersection Observer JS runtime. Panel saves value, nothing reads it at render time (Phase 5). |
| AdvancedPanel | `customCss` | Saved but blocked for security. Requires CSS scoping before enabling (no planned phase). |
| TypographyPanel | All fields | Saved but not applied by the wrapper. Blocks handle their own typography via content-specific fields. |
| LayoutPanel | Most fields | Saved but not applied by the wrapper. Blocks handle their own layout logic. |

---

## 7. Security Rules

- **Dimensions** validated via `safeDim` regex — only valid CSS dimension values pass (e.g., `10px`, `2rem`, `50%`)
- **Colors** validated via `safeColor` regex — hex, rgb(), rgba(), hsl(), hsla(), and named colors only
- **Shadows** allowlisted to `sm`, `md`, `lg` presets only — no arbitrary shadow strings
- **Animation names** allowlisted — only `fade`, `slide-up`, `slide-left`, `slide-right`, `zoom` accepted
- **Duration** clamped to 50-3000ms range
- **Delay** clamped to 0-5000ms range
- **Custom classes** restricted to `[a-zA-Z0-9_\-\s]` — no special characters
- **HTML IDs** restricted to `[a-zA-Z0-9_\-]` — no special characters
- **No raw CSS passthrough** — `customCss` field is blocked entirely
- **URLs** validated separately by block-specific logic (e.g., Hero's `safeUrl()` rejects `javascript:`, `data:`, `vbscript:` schemes)

---

## 8. How to Adopt for Other Blocks

Block authors can enable shared properties with minimal effort:

### Step 1: Backend (already done for you)

`BuildPageService.php` already passes `$blockStyle`, `$blockAnimation`, `$blockAdvanced`, and `$blockResponsive` to ALL Blade templates. No backend changes needed.

### Step 2: Blade template

In your block's Blade template, use the `BlockStyle` helper:

```blade
@php
    use App\Support\Blocks\BlockStyle;

    $wrapperStyle = BlockStyle::buildStyle($blockStyle, $blockAnimation);
    $wrapperClass = BlockStyle::buildClasses($blockAdvanced, 'your-block-class');
    $animAttr     = BlockStyle::animationAttr($blockAnimation);
    $htmlId       = BlockStyle::safeId($blockAdvanced['htmlId'] ?? '');
    $ariaLabel    = e($blockAdvanced['ariaLabel'] ?? '');
@endphp

<section
    @if($htmlId) id="{{ $htmlId }}" @endif
    class="{{ $wrapperClass }}"
    style="{{ $wrapperStyle }}"
    @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
    {!! $animAttr !!}
>
    {{-- Your block content here --}}
</section>

{{-- Responsive hiding --}}
{!! BlockStyle::buildHideOnCss($blockResponsive, $htmlId) !!}
```

### Step 3: Preview (already done for you)

`SortableBlock.tsx` automatically applies shared style properties (spacing, border, shadow, opacity, animation) to every block's preview wrapper. No per-block frontend work needed.

### Reference implementation

See `resources/views/blocks/hero.blade.php` for the complete reference implementation.

---

## 9. Testing

| Test file | Coverage |
|-----------|----------|
| `tests/Unit/Support/BlockStyleTest.php` | Unit tests for all sanitizers (`safeDim`, `safeColor`, `safeClass`, `safeId`) and builders (`buildStyle`, `buildClasses`, `animationAttr`, `buildHideOnCss`) |
| `tests/Unit/Blocks/HeroValidationTest.php` | Validation rules for Hero content fields, shared property validation |

### Manual verification

1. Set spacing (padding/margin) on a block — verify preview matches published output
2. Set border (width, color, style, radius) — verify preview matches published output
3. Set box shadow (sm/md/lg) — verify preview matches published output
4. Set entrance animation — verify preview plays animation, published plays on load
5. Set custom class — verify class appears in published HTML
6. Set HTML ID — verify ID appears in published HTML
7. Set responsive hideOn — verify badge in preview, media query in published CSS
8. Attempt XSS via custom class or HTML ID — verify sanitization strips invalid characters
