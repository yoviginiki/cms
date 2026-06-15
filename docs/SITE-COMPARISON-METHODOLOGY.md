# Site Comparison Methodology

## Purpose
Systematic process for comparing a reference website against CMS output to identify and fix all visual, structural, and functional differences.

## Tool
```bash
./scripts/site-compare.sh <reference_url> <cms_url> [output_file]
```

## Comparison Categories

### 1. Structure (DOM)
- Element counts: `<section>`, `<article>`, `<h1>`-`<h3>`, `<img>`, `<a>`, `<button>`, `<form>`
- Nesting depth
- Header/footer presence
- Semantic landmarks

### 2. Typography
- Font families used (extracted from inline styles + CSS)
- Font sizes (all values)
- Font weights
- Letter-spacing / tracking
- Text-transform (uppercase, etc.)
- Line-height

### 3. Colors
- Hex color frequency
- CSS variable usage (`var(--*)`)
- Primary/accent color match
- Text color hierarchy (heading, body, muted)
- Border colors
- Background colors

### 4. Spacing & Layout
- max-width values (container widths)
- padding values (section, card, nav)
- gap values (grid, flex)
- margin values
- Grid template columns

### 5. Border Radius
- All border-radius values
- Whether blocks use CSS variables or hardcoded values
- Zero-radius themes must have `--border-radius-md: 0`

### 6. Shadows
- box-shadow count
- Shadow types (soft, hard, none)

### 7. CSS Variables
- Which `var(--*)` variables are used
- Whether blocks default to sensible fallbacks
- Whether the theme sets all required variables

### 8. Images
- Image count
- Image treatment (grayscale, overlay, hover effects)
- Aspect ratios
- Loading strategy (lazy, eager)

### 9. Interactive Elements
- Navigation structure and styling
- Button variants and hover effects
- Form styling
- Animation/transitions

### 10. Responsive
- Breakpoints used
- Mobile menu behavior
- Grid collapse behavior
- Font size adjustments

## Fix Classification

When a difference is found, classify it:

| Category | Description | Where to Fix |
|----------|-------------|-------------|
| **THEME_TOKEN** | Theme CSS variable needs to be set/changed | Theme document (W3C tokens) or site settings |
| **BLOCK_FIX** | Block Blade template uses hardcoded value instead of CSS variable | `resources/views/blocks/*.blade.php` |
| **LAYOUT_FIX** | Publishing layout has wrong defaults | `resources/views/publishing/layout.blade.php` |
| **MENU_FIX** | Navigation renderer has wrong defaults | `app/Domain/Menus/Services/MenuRenderer.php` |
| **CONTENT_FIX** | Block data (content) needs updating | Admin → Page Editor → block settings |
| **NEW_FEATURE** | CMS doesn't support this pattern yet | Needs development |
| **CSS_BRIDGE** | Temporary CSS override until source is fixed | Site custom_css (last resort) |

## Process

### Phase 1: Automated Comparison
```bash
./scripts/site-compare.sh https://reference.com https://cms-site.com
```
Review the generated report.

### Phase 2: Classify Each Difference
For every ⚠️ in the report, assign a fix category.

### Phase 3: Fix in Priority Order
1. **THEME_TOKEN** — set CSS variables in the theme (fast, safe)
2. **BLOCK_FIX** — update Blade templates to use CSS variables (affects all sites)
3. **LAYOUT_FIX** — update layout defaults (affects all sites)
4. **MENU_FIX** — update MenuRenderer defaults (affects all sites)
5. **CONTENT_FIX** — update block data in admin
6. **CSS_BRIDGE** — temporary override (document for removal)
7. **NEW_FEATURE** — add to roadmap

### Phase 4: Republish & Verify
1. Publish the site
2. Re-run the comparison
3. Verify structural differences reduced
4. Manual visual check for remaining issues

### Phase 5: Document
- What was fixed
- What CSS variables were added
- What blocks were updated
- What remains as CSS_BRIDGE (tech debt)
- What needs NEW_FEATURE

## CSS Variable Naming Convention

Blocks should use these CSS variable names with sensible fallbacks:

### Colors
- `var(--color-primary, #3b82f6)` — primary/accent
- `var(--color-text, #1e293b)` — body text
- `var(--color-text-muted, #64748b)` — secondary text
- `var(--color-border, #e2e8f0)` — borders
- `var(--color-bg, #ffffff)` — background
- `var(--color-bg-alt, #f5f5f5)` — alternate background

### Typography
- `var(--font-heading, sans-serif)` — heading/display font
- `var(--font-body, sans-serif)` — body font

### Navigation
- `var(--nav-padding, 14px 0)` — nav inner padding
- `var(--nav-gap, 28px)` — nav link spacing
- `var(--nav-font-size, 12px)` — nav link size
- `var(--nav-font-weight, 500)` — nav link weight
- `var(--nav-tracking, 0.12em)` — nav letter-spacing
- `var(--nav-transform, uppercase)` — nav text-transform
- `var(--nav-logo-size, 14px)` — logo text size
- `var(--nav-logo-weight, 600)` — logo text weight
- `var(--nav-logo-tracking, 0.1em)` — logo letter-spacing

### Buttons
- `var(--btn-font-weight, 600)` — button weight
- `var(--btn-tracking, 0.12em)` — button letter-spacing
- `var(--btn-transform, uppercase)` — button text-transform

### Layout
- `var(--container-width, 1200px)` — max content width
- `var(--border-radius-md, 0.5rem)` — default border radius
- `var(--border-radius-sm, 0.25rem)` — small radius

## Anti-Patterns (Never Do)

1. **Don't hardcode px values in blocks** — use CSS variables with fallbacks
2. **Don't use `!important` in block Blade templates** — that's what CSS overrides are for
3. **Don't inline font-family without var()** — themes must be able to change fonts
4. **Don't default border-radius to anything other than a CSS variable** — zero-radius themes break
5. **Don't hardcode colors as hex in blocks** — use `var(--color-*)` with hex fallback
6. **Don't set opacity on text containers** — let the content be fully visible
