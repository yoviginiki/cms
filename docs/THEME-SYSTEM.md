# Theme System Documentation

**Last updated:** Sprint 6 (2026-06-14)

## Architecture Overview

The CMS uses a **W3C Design Tokens-based theme system** with multi-layer resolution, system themes, site-specific forking, and per-scope overrides.

## Where Themes Are Stored

- **Database**: `themes` table with UUID primary key
- **System themes**: `site_id = NULL`, `is_system = true` (Editorial, Commerce, Bare)
- **Site themes**: forked from system themes, `site_id` set to owning site
- **Token storage**: `document` column (JSONB) stores W3C Design Tokens JSON

## How Active Theme Is Stored

- **Site level**: `sites.active_theme_id` (UUID foreign key)
- **Per-page override**: `theme_assignments` table (tenant_id, site_id, theme_id, mode)
- **Override cascade**: Block → Page → Site → Tenant → Active Theme → Parent Theme → System Theme

## Token Schema

### W3C Design Tokens Format
```json
{
  "$metadata": { "name": "Theme Name", "version": "1.0.0", "modes": ["light", "dark"] },
  "primitive": {
    "color": { "blue": { "500": { "$type": "color", "$value": "#3B82F6" } } },
    "font": { "sans": { "$type": "fontFamily", "$value": ["Inter", "system-ui", "sans-serif"] } },
    "size": { "4": { "$type": "dimension", "$value": "1rem" } }
  },
  "semantic": {
    "color": {
      "brand": { "$type": "color", "$value": "{primitive.color.blue.500}" },
      "background": { "canvas": { "$type": "color", "$value": "{primitive.color.neutral.50}" } },
      "text": { "body": { "$type": "color", "$value": "{primitive.color.neutral.900}" } }
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

### CSS Variables Generated

The system generates two layers of CSS variables:

1. **Semantic variables** (from W3C path): `--semantic-color-brand`, `--semantic-font-family-body`
2. **Legacy aliases** (for Blade templates): `--color-primary`, `--font-heading`, `--color-text`

### Key CSS Variables Used in Blade Templates

| Variable | Purpose | Fallback |
|----------|---------|----------|
| `--color-primary` | Primary/brand color | `#3b82f6` |
| `--color-text` | Body text color | `#1a1a1a` |
| `--color-text-muted` | Muted/secondary text | `#9ca3af` |
| `--color-bg` | Background color | `#ffffff` |
| `--color-border` | Default border color | `#e2e8f0` |
| `--font-heading` | Heading font family | `Georgia, serif` |
| `--font-body` | Body font family | `sans-serif` |
| `--container-width` | Max content width | `1400px` |
| `--container-padding` | Container padding | `40px` |
| `--border-radius-md` | Medium border radius | `0.5rem` |
| `--shadow-md` | Medium box shadow | (system default) |

## Resolution Pipeline

```
ThemeResolver::resolve(ResolveRequest)
  → ThemeLoader::loadLayers()     # Build ordered layer stack
  → TokenMerger::merge()          # Deep-merge layers (later wins)
  → ReferenceResolver::flatten()  # Resolve {token.path} references
  → ResolvedTheme                 # Flat path → value map
  → toCssVariables()              # --semantic-color-brand: #3B82F6
```

## How Blade Templates Use Theme Variables

```blade
{{-- In layout.blade.php --}}
<style>{!! $designTokensCss !!}</style>

{{-- In block templates --}}
<div style="color: var(--color-text, #1a1a1a); font-family: var(--font-body, sans-serif);">
  ...
</div>

{{-- In blog templates --}}
<a style="color: var(--color-primary, #3b82f6);">Read more</a>
```

### Best Practice for Block Blade Templates
```blade
{{-- Always include a sensible fallback --}}
color: var(--color-text, #1e293b);
background: var(--color-bg, #ffffff);
font-family: var(--font-body, system-ui, sans-serif);
border-color: var(--color-border, #e5e7eb);
```

## How to Create a New Theme

1. Fork a system theme in the Theme Engine admin page
2. Edit tokens in the Theme Editor (token tree, color pickers, font selectors)
3. Preview in Theme Studio (live iframe with element inspection)
4. Activate for a site via Theme Gallery or API

### Via API
```
POST /api/v1/sites/{siteId}/theme-engine/themes/{themeId}/fork
  → { name: "My Custom Theme" }

PUT /api/v1/sites/{siteId}/theme-engine/themes/{newThemeId}
  → { document: { ... W3C tokens ... } }

POST /api/v1/sites/{siteId}/theme-engine/assign
  → { theme_id: "new-theme-uuid" }
```

## Import/Export

- **Export**: `GET /api/v1/sites/{siteId}/theme-engine/themes/{themeId}/export` → JSON
- **Import**: `POST /api/v1/sites/{siteId}/theme-engine/import` → `{ document: {...} }`

## Header/Footer

Theme templates support `type = 'header'` and `type = 'footer'` via `ThemeTemplate` model. Resolution priority: post_format+category > category > post_format > default.

## System Themes

| Theme | Slug | Brand Color | Heading Font | Body Font |
|-------|------|-------------|--------------|-----------|
| Editorial | editorial | #3B82F6 (blue) | Fraunces (serif) | Inter (sans-serif) |
| Commerce | commerce | #8B5CF6 (violet) | Inter | Inter |
| Bare | bare | #3B82F6 (blue) | system-ui | system-ui |

## Current Limitations

1. Header/footer presets are metadata-only — no full header/footer visual builder yet
2. Dark mode requires manual token editing (no auto-generation)
3. Responsive token overrides not yet implemented
4. Block-level theme overrides are available but no UI for them yet
