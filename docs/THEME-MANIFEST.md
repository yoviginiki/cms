# Theme Manifest Format

## Overview

Each theme can have a `manifest_json` field (stored in the `themes` table) that describes metadata, tokens, templates, and block presets.

## Format

```json
{
  "name": "Minimal Clean",
  "slug": "minimal-clean",
  "version": "1.0.0",
  "author": "Ensodo",
  "description": "A clean, minimal theme with generous whitespace and modern typography.",
  "screenshot": null,
  "category": "starter",
  "tokens": {
    "color-primary": "#2563eb",
    "color-bg": "#ffffff",
    "color-text": "#1e293b",
    "font-family-base": "Inter, sans-serif",
    "font-size-base": "16px",
    "line-height-base": "1.6",
    "container-width": "1200px",
    "border-radius-md": "0.5rem"
  },
  "templates": ["standard", "landing", "blog"],
  "blockPresets": ["hero", "features", "cta"]
}
```

## Fields

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Display name |
| `slug` | string | URL-safe identifier |
| `version` | string | Semver version |
| `author` | string | Creator name |
| `description` | string | Short description |
| `screenshot` | string\|null | URL to preview image |
| `category` | string | `starter`, `blog`, `portfolio`, `commerce`, `magazine` |
| `tokens` | object | CSS custom property overrides |
| `templates` | string[] | Supported layout templates |
| `blockPresets` | string[] | Recommended section presets |

## Starter Themes

### Minimal Clean
- Clean, modern, whitespace-focused
- Tokens: Inter font, blue primary, white background
- Templates: standard, landing
- Presets: hero, features, cta, stats

### Editorial Magazine
- Content-rich, serif headings, warm tones
- Tokens: Playfair Display headings, warm gray background
- Templates: standard, longform, blog
- Presets: hero, testimonials, blog-grid, faq

## W3C Design Tokens (Primary Format)

The `document` column stores W3C Design Tokens JSON. This is the primary token format used by the Theme Editor and Studio.

```json
{
  "$metadata": {
    "name": "Theme Name",
    "version": "1.0.0",
    "modes": ["light", "dark"]
  },
  "primitive": {
    "color": {
      "blue": { "500": { "$type": "color", "$value": "#3B82F6" } }
    },
    "font": {
      "sans": { "$type": "fontFamily", "$value": ["Inter", "system-ui", "sans-serif"] }
    }
  },
  "semantic": {
    "color": {
      "brand": { "$type": "color", "$value": "{primitive.color.blue.500}" },
      "background": { "canvas": { "$type": "color", "$value": "#FFFFFF" } },
      "text": { "body": { "$type": "color", "$value": "#1E293B" } }
    },
    "font": {
      "family": {
        "display": { "$type": "fontFamily", "$value": "{primitive.font.serif}" },
        "body": { "$type": "fontFamily", "$value": "{primitive.font.sans}" }
      }
    }
  }
}
```

## Component Presets (Sprint 6)

Themes may include component preset metadata:

| Key | Values | Description |
|-----|--------|-------------|
| `components.buttonStyle` | `rounded`, `pill`, `square` | Default button border-radius style |
| `components.cardStyle` | `bordered`, `shadow`, `flat` | Default card presentation |
| `components.headerStyle` | `simple`, `centered`, `split` | Header layout preset |
| `components.footerStyle` | `simple`, `columns`, `minimal` | Footer layout preset |

## Token Resolution Priority

1. Block-level overrides (highest)
2. Page-level overrides
3. Site-level overrides
4. Tenant-level overrides
5. Active theme document
6. Parent theme (if inherited)
7. System theme defaults (lowest)
