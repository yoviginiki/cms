# Advanced Shadow Builder Audit

> **Date**: 2026-05-12
> **Status**: Architecture audit complete. Implementation planned as P2.

---

## 1. Current Shadow Inventory

Three separate shadow systems currently exist:

### 1a. Shared/BaseBlock shadows (`block.style.visual.boxShadow`)

| Key | Editor | Preview | Blade | Presets | Status |
|-----|--------|---------|-------|---------|--------|
| `style.visual.boxShadow` | VisualPanel (4 buttons) | `buildBlockWrapperStyle()` | `BlockStyle::buildStyle()` | sm, md, lg | **WORKING** |

CSS values:
- `sm`: `0 1px 2px rgba(0,0,0,0.04)`
- `md`: `0 4px 12px rgba(0,0,0,0.06)`
- `lg`: `0 12px 32px rgba(0,0,0,0.10)`

### 1b. Hero section shadow (`block.data.sectionShadow`)

| Key | Editor | Preview | Blade | Presets | Status |
|-----|--------|---------|-------|---------|--------|
| `data.sectionShadow` | Hero Editor (dropdown) | `sectionShadowMap` | `$secShadowMap` | subtle, medium, large, glow | **WORKING** |

CSS values:
- `subtle`: `0 1px 3px rgba(0,0,0,0.12)`
- `medium`: `0 8px 24px rgba(0,0,0,0.18)`
- `large`: `0 20px 40px rgba(0,0,0,0.24)`
- `glow`: `0 0 30px rgba(255,255,255,0.35)`

### 1c. Hero content box shadow (`block.data.contentBoxShadow`)

| Key | Editor | Preview | Blade | Presets | Status |
|-----|--------|---------|-------|---------|--------|
| `data.contentBoxShadow` | Hero Editor (dropdown) | `shadowMap` | `$cbShadowMap` | sm, md, lg | **WORKING** |

Same CSS values as shared sm/md/lg.

### 1d. Alias and planned keys

| Key | Status | Notes |
|-----|--------|-------|
| `shadow` | **ALIAS** — maps to `boxShadow` in some docs; not a separate stored key | Not stored directly; referenced as canonical name in BACKGROUND-SYSTEM-AUDIT.md |
| `shadowPreset` | **PLANNED / NOT CURRENT** | Proposed unified preset key for P2 schema |
| `shadowMode` | **PLANNED / NOT CURRENT** | Proposed mode toggle (preset/custom) for P2 schema |
| `shadowCustom` | **PLANNED / NOT CURRENT** | Proposed structured custom shadow object for P2 schema |

### Observations

- Three different preset name sets: `sm/md/lg` (shared + content box), `subtle/medium/large/glow` (Hero section)
- Two different CSS value scales: shared presets are very light (0.04–0.10 opacity), Hero section presets are stronger (0.12–0.24)
- No raw CSS shadow input exists — all current shadows are preset-only (safe)
- No custom X/Y/blur/spread/color controls exist

---

## 2. Preset System Status

| System | Preset Names | Safe | Validated | Notes |
|--------|-------------|------|-----------|-------|
| Shared (VisualPanel) | sm, md, lg | Yes (allowlisted map) | `safeShadow()` in TS + PHP | Applied to outer wrapper |
| Hero section | subtle, medium, large, glow | Yes (allowlisted map) | `in:,none,subtle,medium,large,glow` | Applied to section element |
| Hero content box | sm, md, lg | Yes (allowlisted map) | `in:,sm,md,lg` | Applied to content box |

All three systems are **safe** — they map preset names to hardcoded CSS strings. No user-provided CSS is accepted.

---

## 3. Proposed Custom Shadow Schema

### Data model

```typescript
// In block data (Hero-specific) or block.style.visual (shared)
shadowMode: 'preset' | 'custom';
shadowPreset: 'none' | 'subtle' | 'medium' | 'large' | 'glow';
shadowCustom: {
  x: string;       // CSS dimension e.g. '0px', '4px'
  y: string;       // CSS dimension e.g. '8px', '-2px'
  blur: string;    // CSS dimension e.g. '24px'
  spread: string;  // CSS dimension e.g. '0px', '-2px'
  color: string;   // CSS color e.g. '#000000'
  opacity: number; // 0-100 (applied to color alpha)
  inset: boolean;  // false = outer, true = inset
};
```

### Rules

- `shadowMode='preset'` uses `shadowPreset` value to look up a hardcoded CSS string
- `shadowMode='custom'` builds `box-shadow` from individual `shadowCustom` fields
- If `shadowMode` is absent or empty, treat as `'preset'` for backward compatibility
- If `shadowPreset` is absent or empty, treat as `'none'`
- If `shadowCustom` object is entirely absent with `shadowMode='custom'`, fallback to `'none'` (no shadow)
- If `shadowCustom` object exists but individual fields are missing, default safely: `x:'0px', y:'4px', blur:'12px', spread:'0px', color:'#000000', opacity:15, inset:false`
- Legacy `boxShadow` values (sm/md/lg) continue to work through existing `SHADOW_MAP`
- No raw CSS string input by default — shadow is always built from structured values

### Generated CSS

For `shadowMode='custom'` with `opacity=18`:
```css
box-shadow: 0px 8px 24px 0px rgba(0, 0, 0, 0.18);
```

For `shadowMode='custom'` with `inset=true`:
```css
box-shadow: inset 0px 2px 4px 0px rgba(0, 0, 0, 0.10);
```

Color + opacity merge: `color=#3b82f6, opacity=30` → `rgba(59, 130, 246, 0.30)`

---

## 4. Security / Sanitization Rules

| Field | Validation | Sanitizer |
|-------|-----------|-----------|
| `shadowMode` | `in:preset,custom` | allowlist |
| `shadowPreset` | `in:none,subtle,medium,large,glow` | preset map lookup |
| `shadowCustom.x` | `regex:/^-?\d+(\.\d+)?(px\|rem\|em)$/` | `safeDim()` |
| `shadowCustom.y` | same | `safeDim()` |
| `shadowCustom.blur` | `regex:/^\d+(\.\d+)?(px\|rem\|em)$/` (non-negative) | `safeDim()` |
| `shadowCustom.spread` | `regex:/^-?\d+(\.\d+)?(px\|rem\|em)$/` | `safeDim()` |
| `shadowCustom.color` | `regex:/^#[0-9a-fA-F]{3,8}$/` or `rgba?(...)` | `safeColor()` |
| `shadowCustom.opacity` | `integer, min:0, max:100` | clamped |
| `shadowCustom.inset` | `boolean` | strict boolean |
| Legacy `boxShadow` | `in:sm,md,lg,none` | `safeShadow()` preset map |

### Critical rules

1. **No raw `box-shadow` CSS string** is ever accepted from user input
2. The `box-shadow` CSS string is always **generated** from structured values
3. Both TS and PHP generate the same string from the same structured data
4. `safeDim()` rejects `expression()`, `url()`, `javascript:`, and other injection attempts
5. `safeColor()` accepts only hex, rgb/rgba, hsl/hsla, oklch, or named colors
6. Opacity is an integer clamped to 0–100, converted to 0.00–1.00 alpha

---

## 5. Editor UI Plan

### Proposed component

`resources/admin/src/components/editor/fields/ShadowField.tsx`

### Interface

```typescript
interface ShadowFieldProps {
  label: string;
  mode: 'preset' | 'custom';
  preset: string;
  custom: {
    x: string; y: string; blur: string; spread: string;
    color: string; opacity: number; inset: boolean;
  };
  onChangeMode: (mode: 'preset' | 'custom') => void;
  onChangePreset: (preset: string) => void;
  onChangeCustom: (custom: Partial<ShadowCustom>) => void;
}
```

### UI layout

```
┌─ Shadow ────────────────────────────────────┐
│ [Preset ▾] [Custom ▾]  ← mode toggle       │
│                                              │
│ Preset mode:                                 │
│ [None] [Subtle] [Medium] [Large] [Glow]      │
│                                              │
│ Custom mode:                                 │
│ X: [0px]   Y: [8px]                         │
│ Blur: [24px]   Spread: [0px]                │
│ Color: [■ #000000]   Opacity: [18%]          │
│ □ Inset                                      │
│                                              │
│ Preview: ┌──────────────┐                    │
│          │   ▒▒▒▒▒▒▒▒   │  ← shadow preview │
│          └──────────────┘                    │
│                                              │
│ [Reset to preset]                            │
└──────────────────────────────────────────────┘
```

### Features

- Preset mode: same dropdown/button group as current
- Custom mode: individual dimension inputs with safe validation
- Color picker + opacity slider
- Inset toggle
- Optional preview swatch showing shadow visually
- Reset button to return to preset mode
- Light/dark admin theme readable (DaisyUI tokens)
- Keyboard accessible

### Not implemented yet — this is the design only.

---

## 6. Preview / Blade Rendering Plan

### Frontend (TypeScript)

```typescript
// In a shared helper (e.g. blockStyles.ts or a new shadowHelper.ts)
function buildShadowStyle(
  mode: string,
  preset: string,
  custom?: ShadowCustom,
): string | undefined {
  if (mode === 'custom' && custom) {
    const x = safeDim(custom.x) || '0px';
    const y = safeDim(custom.y) || '4px';
    const blur = safeDim(custom.blur) || '12px';
    const spread = safeDim(custom.spread) || '0px';
    const color = safeColor(custom.color) || '#000000';
    const alpha = Math.max(0, Math.min(100, custom.opacity ?? 15)) / 100;
    const rgba = hexToRgba(color, alpha);
    const inset = custom.inset ? 'inset ' : '';
    return `${inset}${x} ${y} ${blur} ${spread} ${rgba}`;
  }
  return SHADOW_PRESET_MAP[preset] || undefined;
}
```

### Backend (PHP)

```php
// In BlockStyle.php or a dedicated ShadowHelper
public static function buildShadow(
    string $mode,
    string $preset,
    ?array $custom = null,
): string {
    if ($mode === 'custom' && $custom) {
        $x = self::safeDim($custom['x'] ?? '0px') ?: '0px';
        $y = self::safeDim($custom['y'] ?? '4px') ?: '4px';
        $blur = self::safeDim($custom['blur'] ?? '12px') ?: '12px';
        $spread = self::safeDim($custom['spread'] ?? '0px') ?: '0px';
        $color = self::safeColor($custom['color'] ?? '#000000') ?: '#000000';
        $alpha = max(0, min(100, (int) ($custom['opacity'] ?? 15))) / 100;
        $rgba = self::hexToRgba($color, $alpha);
        $inset = !empty($custom['inset']) ? 'inset ' : '';
        return "{$inset}{$x} {$y} {$blur} {$spread} {$rgba}";
    }
    return self::SHADOW_PRESET_MAP[$preset] ?? '';
}
```

### Unified preset map

Both TS and PHP should use the same presets:

| Preset | CSS Value |
|--------|-----------|
| `none` | (no shadow) |
| `subtle` | `0 1px 3px rgba(0,0,0,0.12)` |
| `sm` | `0 1px 2px rgba(0,0,0,0.04)` |
| `medium` / `md` | `0 8px 24px rgba(0,0,0,0.18)` |
| `large` / `lg` | `0 20px 40px rgba(0,0,0,0.24)` |
| `glow` | `0 0 30px rgba(255,255,255,0.35)` |

**Note**: The current naming inconsistency (sm/md/lg vs subtle/medium/large) should be unified in P2. Both name sets should be accepted for backward compatibility.

---

## 7. Legacy Compatibility

| Legacy Key | Legacy Values | Backward Compatible | Migration Needed |
|-----------|--------------|-------------------|-----------------|
| `style.visual.boxShadow` | sm, md, lg | Yes — continue to work via `safeShadow()` | No |
| `data.sectionShadow` | subtle, medium, large, glow | Yes — continue to work via preset map | No |
| `data.contentBoxShadow` | sm, md, lg | Yes — continue to work via preset map | No |

### Rules

- If `shadowMode` is absent, treat as `'preset'` → existing presets work unchanged
- If `shadowPreset` has a legacy value (sm/md/lg), map it to the correct CSS
- If `shadowCustom` object is entirely absent with `shadowMode='custom'`, fallback to `'none'` (no shadow)
- If `shadowCustom` object exists but has missing fields, use safe defaults per field
- No existing saved data is broken by the new schema

---

## 8. Migration / Adoption Plan

### P2A: Create ShadowField and helpers

- Create `ShadowField.tsx` component
- Create `buildShadowStyle()` helper in TS and PHP
- Unify preset maps across all three current shadow systems
- Keep Hero on presets only initially

### P2B: Apply custom shadow builder to Hero

- Replace Hero section `sectionShadow` dropdown with `ShadowField`
- Replace Hero content box `contentBoxShadow` dropdown with `ShadowField`
- Both support preset + custom modes
- Add validation for `shadowCustom` fields in `HeroBlockDefinition`

### P2C: Apply to BaseBlock shared engine

- Replace VisualPanel shadow buttons with `ShadowField`
- Update `buildBlockWrapperStyle()` to use `buildShadowStyle()`
- Update `BlockStyle::buildStyle()` to use PHP `buildShadow()`

### P2D: Adopt for other blocks

- section, heading, card, image, etc.
- Each block can use shared shadow through BaseBlock inheritance
- Block-specific shadow (like Hero content box) remains separate

---

## 9. Risks

| Risk | Mitigation |
|------|------------|
| Three shadow systems with different naming | Unify preset names, accept both old and new |
| Raw CSS injection via custom shadow fields | All fields validated individually, CSS string generated from structured data |
| Complex UI for simple use case | Default to preset mode; custom mode is opt-in |
| Performance of multiple shadow layers | Single box-shadow per element; no stacking |
| Inconsistent preset values across systems | Document and unify in P2A |

---

## 10. Recommended Next Implementation Prompt

When ready to implement P2A, use:

> "Implement P2A: Create ShadowField component and shared shadow helpers.
> Create `resources/admin/src/components/editor/fields/ShadowField.tsx` with preset and custom modes.
> Create shadow helper functions in `resources/admin/src/lib/blockStyles.ts` (TS) and `app/Support/Blocks/BlockStyle.php` (PHP).
> Unify preset maps. Do not change Hero behavior yet — that is P2B."
