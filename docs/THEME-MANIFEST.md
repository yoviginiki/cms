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
