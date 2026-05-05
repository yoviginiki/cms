# Ensodo CMS theme specification

## Overview

A theme is a ZIP file that controls the visual appearance of a published site.
It contains a manifest, CSS, and optionally fonts and images.

When installed, the CMS reads the manifest and applies the theme's design tokens
as CSS custom properties on every published page.

## ZIP structure

```
my-theme/
  theme.json          ← required: manifest with tokens and metadata
  style.css           ← optional: full stylesheet (lazy-loaded)
  critical.css        ← optional: above-fold CSS (inlined in <head>)
  screenshot.png      ← optional: preview image (800x600 recommended)
  fonts/              ← optional: self-hosted font files
    inter.woff2
    fraunces.woff2
  assets/             ← optional: images, icons used in CSS
    logo.svg
```

## theme.json format

```json
{
  "name": "Apple Minimal",
  "version": "1.0.0",
  "description": "Clean, spacious design inspired by Apple.com",
  "author": "Your Name",
  "screenshot": "screenshot.png",
  "lang": "en",

  "fonts": [
    {
      "family": "Inter",
      "weight": "100 900",
      "style": "normal",
      "woff2_url": "fonts/inter.woff2",
      "display": "swap"
    }
  ],

  "tokens": {
    "color-primary": "#4F46E5",
    "color-primary-dark": "#4338CA",
    "color-primary-light": "#818CF8",
    "color-secondary": "#64748B",
    "color-accent": "#0EA5E9",
    "color-text": "#0F172A",
    "color-text-muted": "#64748B",
    "color-text-inverse": "#FFFFFF",
    "color-bg": "#FFFFFF",
    "color-bg-alt": "#F8FAFC",
    "color-bg-inverse": "#0F172A",
    "color-border": "#E2E8F0",
    "color-border-light": "#F1F5F9",
    "color-success": "#22C55E",
    "color-warning": "#F59E0B",
    "color-danger": "#EF4444",
    "color-info": "#3B82F6",

    "font-heading": "'Inter', system-ui, sans-serif",
    "font-body": "'Inter', system-ui, sans-serif",
    "font-mono": "'JetBrains Mono', 'Fira Code', monospace",

    "font-size-base": "18px",
    "font-size-sm": "14px",
    "font-size-lg": "20px",
    "font-size-xl": "24px",
    "font-size-2xl": "32px",
    "font-size-3xl": "48px",

    "font-weight-normal": "400",
    "font-weight-medium": "500",
    "font-weight-bold": "600",

    "line-height-tight": "1.2",
    "line-height-normal": "1.6",
    "line-height-relaxed": "1.8",

    "letter-spacing-tight": "-0.02em",
    "letter-spacing-normal": "0",
    "letter-spacing-wide": "0.05em",

    "space-1": "4px",
    "space-2": "8px",
    "space-3": "12px",
    "space-4": "16px",
    "space-6": "24px",
    "space-8": "32px",
    "space-12": "48px",
    "space-16": "64px",
    "space-24": "96px",
    "space-32": "128px",

    "container-width": "1200px",
    "container-padding": "24px",
    "grid-gap": "24px",

    "border-radius-sm": "6px",
    "border-radius-md": "10px",
    "border-radius-lg": "16px",
    "border-radius-full": "9999px",

    "shadow-sm": "0 1px 2px rgba(0,0,0,0.04)",
    "shadow-md": "0 4px 12px rgba(0,0,0,0.06)",
    "shadow-lg": "0 12px 32px rgba(0,0,0,0.10)",
    "shadow-xl": "0 24px 48px rgba(0,0,0,0.12)",

    "transition-fast": "150ms ease-out",
    "transition-base": "250ms ease-out",
    "transition-slow": "400ms ease-out"
  },

  "critical_css": "/* inline critical CSS here or use critical.css file */",
  "css_file": "style.css"
}
```

## Token categories reference

### Colors (17 tokens)

| Token | Purpose | Example |
|-------|---------|---------|
| `color-primary` | Main accent color — buttons, links, active states | `#4F46E5` |
| `color-primary-dark` | Hover state for primary | `#4338CA` |
| `color-primary-light` | Backgrounds, highlights | `#818CF8` |
| `color-secondary` | Secondary actions, muted elements | `#64748B` |
| `color-accent` | Call-to-action highlights, badges | `#0EA5E9` |
| `color-text` | Main body text | `#0F172A` |
| `color-text-muted` | Captions, meta text, placeholders | `#64748B` |
| `color-text-inverse` | Text on dark backgrounds | `#FFFFFF` |
| `color-bg` | Page background | `#FFFFFF` |
| `color-bg-alt` | Cards, sections, alternating rows | `#F8FAFC` |
| `color-bg-inverse` | Dark sections (footer, hero) | `#0F172A` |
| `color-border` | Input borders, dividers | `#E2E8F0` |
| `color-border-light` | Subtle separators | `#F1F5F9` |
| `color-success` | Success messages, publish status | `#22C55E` |
| `color-warning` | Warnings, draft status | `#F59E0B` |
| `color-danger` | Errors, delete actions | `#EF4444` |
| `color-info` | Information, links | `#3B82F6` |

### Typography (14 tokens)

| Token | Purpose | Default |
|-------|---------|---------|
| `font-heading` | H1-H6 font family | `'Inter', system-ui, sans-serif` |
| `font-body` | Body text font family | `'Inter', system-ui, sans-serif` |
| `font-mono` | Code blocks, monospace | `monospace` |
| `font-size-base` | Body text size | `18px` (public), `14px` (admin) |
| `font-size-sm` | Small text, captions | `14px` |
| `font-size-lg` | Subheadings | `20px` |
| `font-size-xl` | Section titles | `24px` |
| `font-size-2xl` | Page titles | `32px` |
| `font-size-3xl` | Hero headlines | `48px` |
| `font-weight-normal` | Body text | `400` |
| `font-weight-medium` | Emphasis, labels | `500` |
| `font-weight-bold` | Headings | `600` |
| `line-height-tight` | Headings | `1.2` |
| `line-height-normal` | Body text | `1.6` |

### Spacing (10 tokens)

Based on 4px grid. Used for padding, margin, gap.

| Token | Value | Use case |
|-------|-------|----------|
| `space-1` | `4px` | Tiny gaps, icon padding |
| `space-2` | `8px` | Inline spacing |
| `space-3` | `12px` | Input padding |
| `space-4` | `16px` | Card padding, list gaps |
| `space-6` | `24px` | Section padding |
| `space-8` | `32px` | Between sections |
| `space-12` | `48px` | Major section gaps |
| `space-16` | `64px` | Hero vertical padding |
| `space-24` | `96px` | Full section breathing room |
| `space-32` | `128px` | Maximum vertical spacing |

### Layout (4 tokens)

| Token | Value | Purpose |
|-------|-------|---------|
| `container-width` | `1200px` | Max width of content container |
| `container-padding` | `24px` | Left/right gutter |
| `grid-gap` | `24px` | Default gap between grid items |

### Effects (7 tokens)

| Token | Value | Purpose |
|-------|-------|---------|
| `border-radius-sm` | `6px` | Inputs, small elements |
| `border-radius-md` | `10px` | Cards, buttons |
| `border-radius-lg` | `16px` | Modals, large containers |
| `shadow-sm` | `0 1px 2px ...` | Subtle depth |
| `shadow-md` | `0 4px 12px ...` | Cards hover |
| `shadow-lg` | `0 12px 32px ...` | Modals, popovers |
| `transition-fast` | `150ms ease-out` | Hover, focus |

## How tokens are applied

The CMS generates a `:root {}` CSS block with all tokens as custom properties:

```css
:root {
  --color-primary: #4F46E5;
  --color-text: #0F172A;
  --font-heading: 'Inter', system-ui, sans-serif;
  --font-size-base: 18px;
  --space-4: 16px;
  --border-radius-md: 10px;
  --shadow-md: 0 4px 12px rgba(0,0,0,0.06);
  /* ... all 50+ tokens */
}
```

Block templates (Blade) reference these variables:

```css
.hero-section {
  padding: var(--space-24) var(--container-padding);
  font-family: var(--font-heading);
}
.hero-section h1 {
  font-size: var(--font-size-3xl);
  font-weight: var(--font-weight-bold);
  line-height: var(--line-height-tight);
  color: var(--color-text);
}
```

## critical.css guidelines

The `critical.css` file should contain styles needed for above-the-fold rendering:

- Navigation bar styles
- Hero section styles
- Typography base
- Grid/flexbox layout fundamentals
- Color definitions (background, text)

Keep it under 14KB (fits in first TCP round-trip).

Do NOT include:
- Footer styles
- Below-fold section styles
- Animations
- Complex selectors

## style.css guidelines

The optional `style.css` is lazy-loaded after critical CSS renders.

It should contain:
- Full component styles (cards, buttons, forms)
- Below-fold sections
- Hover/focus states
- Print styles
- Responsive breakpoints

## Creating a theme with AI

Give AI this prompt:

```
Create a website theme ZIP for the Ensodo CMS platform.

The theme must contain:
1. theme.json — manifest with all design tokens (see specification below)
2. critical.css — above-fold styles (under 14KB)
3. style.css — full stylesheet

Design reference: [paste URL or describe the design you want]

Token specification: [paste the token categories table from this document]

Requirements:
- All styles must use CSS custom properties: var(--color-primary), var(--font-heading), etc.
- No hardcoded colors in CSS — always reference tokens
- Mobile-first responsive design
- Navigation: sticky top bar, logo left, menu right
- Hero: large heading, subtitle, CTA button
- Content: readable prose with 65ch max-width
- Cards: subtle shadow, rounded corners
- Footer: minimal, centered
- Dark mode support via prefers-color-scheme
- Lighthouse score 100
- Self-hosted fonts only (include WOFF2 files or use system fonts)
- No JavaScript dependencies
- All images need width/height attributes

Output as a ZIP file with the structure:
  theme-name/
    theme.json
    critical.css
    style.css
    screenshot.png (optional)
```

## Installing a theme

1. Go to admin → Themes
2. Drag and drop the ZIP file
3. Click "Activate" on the new theme
4. Customize tokens in Theme Customizer if needed
5. Publish the site to apply changes

## Theme inheritance

Themes can extend a parent theme:
- Set `parent_theme_id` in the database
- Child theme tokens override parent tokens
- Missing tokens fall back to parent → then to defaults

## Per-site customizations

After activating a theme, individual tokens can be overridden per site:
- Go to Theme Customizer
- Change any token value
- These are stored in `theme_customizations` table
- Override chain: defaults → theme → customizations
