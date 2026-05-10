# Responsive Overrides

> **Status**: Foundation implemented. Hero is the pilot block.
> **Date**: 2026-05-10
> **Phase**: 4

---

## 1. Overview

Responsive overrides allow blocks to use different visual settings for desktop, tablet, and mobile breakpoints. Desktop is the base; smaller breakpoints can override selected values.

### Data model

```
block.data = {
  textAlignment: 'center',        // ← desktop/base value
  sectionHeight: 'md',
  contentMaxWidth: '800px',
  responsive: {                    // ← optional overrides
    tablet: {
      textAlignment: 'left',       // ← overrides desktop
      sectionHeight: 'sm',
    },
    mobile: {
      textAlignment: 'center',    // ← overrides tablet
      sectionHeight: 'auto',
    },
  },
}
```

### Inheritance chain

```
mobile → tablet → desktop (base)
```

- If mobile has no override, it inherits from tablet
- If tablet has no override, it inherits from desktop
- Desktop always uses the top-level base value

### Backward compatibility

- The `responsive` key is optional — old blocks without it work unchanged
- Existing top-level keys (textAlignment, sectionHeight, etc.) remain the desktop/base values
- No data migration required
- No existing keys renamed or moved

---

## 2. Supported Properties (Hero Pilot)

| Property | Desktop Key | Tablet Override | Mobile Override |
|----------|------------|-----------------|-----------------|
| Text Alignment | `textAlignment` | `responsive.tablet.textAlignment` | `responsive.mobile.textAlignment` |
| Section Height | `sectionHeight` | `responsive.tablet.sectionHeight` | `responsive.mobile.sectionHeight` |
| Content Max Width | `contentMaxWidth` | `responsive.tablet.contentMaxWidth` | `responsive.mobile.contentMaxWidth` |

Future properties (not yet implemented):
- headlineSize, subheadlineSize
- padding/spacing
- background position
- mobile-specific background image

---

## 3. Frontend Helpers

Location: `resources/admin/src/lib/responsiveValues.ts`

| Function | Purpose |
|----------|---------|
| `getResponsiveValue(data, key, breakpoint)` | Get effective value with inheritance |
| `setResponsiveValue(data, key, breakpoint, value)` | Set value at correct breakpoint level |
| `clearResponsiveValue(data, key, breakpoint)` | Remove override, revert to inherited |
| `hasResponsiveOverride(data, key, breakpoint)` | Check if explicit override exists |

### ResponsiveField component

Location: `resources/admin/src/components/editor/fields/ResponsiveField.tsx`

A reusable wrapper that adds breakpoint selector icons (Desktop/Tablet/Mobile) and an override indicator to any field. Shows:
- Which breakpoint is being edited
- Whether the current value is overridden or inherited
- "Reset override" button to clear tablet/mobile values

---

## 4. Editor UI

The Hero Editor shows responsive controls for pilot properties:
- Breakpoint icons (Desktop / Tablet / Mobile) appear next to responsive-enabled fields
- Override state is indicated by icon color (primary = active, warning = has override, muted = inherited)
- "Reset override" clears the tablet/mobile value so it inherits from the larger breakpoint

The editor canvas always shows the desktop/base view. Responsive overrides take effect in published output only.

---

## 5. Published Output (Blade)

Responsive overrides are rendered as scoped CSS media queries in the Blade template:

```html
<style>
@media(max-width:1024px){.hero-resp-a1b2c3d4 .hero-content{text-align:left}}
@media(max-width:640px){.hero-resp-a1b2c3d4{min-height:auto}}
</style>
<section class="hero-section hero-resp-a1b2c3d4" style="...">
```

### Breakpoints

| Breakpoint | Media Query | Scope |
|-----------|-------------|-------|
| Tablet | `max-width: 1024px` | Overrides desktop |
| Mobile | `max-width: 640px` | Overrides tablet + desktop |

### Scoping

Each Hero instance gets a unique class (`hero-resp-XXXXXXXX`) to prevent CSS collisions between multiple Hero blocks on the same page.

### Security

- All CSS values are sanitized through the same `$cssVal` / `$cssDim` helpers used for base styles
- Only allowlisted properties can be overridden
- No raw user CSS is output
- The scoped class name is derived from a hash, not user input

---

## 6. Backend Validation

`HeroBlockDefinition.php` validates responsive overrides with the same rules as base values:

```php
'responsive'                        => ['sometimes', 'nullable', 'array'],
'responsive.tablet'                 => ['sometimes', 'nullable', 'array'],
'responsive.tablet.textAlignment'   => ['sometimes', 'in:left,center,right'],
'responsive.tablet.sectionHeight'   => ['sometimes', 'in:auto,sm,md,lg,fullscreen'],
'responsive.tablet.contentMaxWidth' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:...'],
// same for mobile
```

---

## 7. Limitations

- **Hero only** — other blocks do not support responsive overrides yet
- **3 properties** — only textAlignment, sectionHeight, contentMaxWidth are responsive-enabled
- **Editor shows desktop only** — the editor canvas always renders the desktop view
- **No responsive preview toggle** — future enhancement to preview tablet/mobile in the editor
- **No design tokens** — responsive values are direct CSS, not token references
- **No responsive typography** — headlineSize/subheadlineSize not yet responsive-enabled
- **No responsive padding** — future property
- **No mobile background image** — future property

---

## 8. Future Roadmap

1. **Responsive preview toggle** — editor canvas switches between desktop/tablet/mobile views
2. **More responsive properties** — typography, padding, background position, CTA size
3. **Mobile background image** — separate image optimized for mobile
4. **Adopt for other blocks** — heading, image, CTA banner, etc.
5. **Design token binding** — responsive values reference design tokens instead of raw CSS
