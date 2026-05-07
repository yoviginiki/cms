# Theme Engine

## Overview

The theme engine manages visual appearance through **design tokens** (CSS custom properties). It supports:

- System themes (read-only, installable)
- Forked themes (editable copies of system themes)
- W3C Design Tokens format (document-based)
- Multi-mode support (light/dark)
- Hierarchical overrides (tenant > site > page > block)
- Theme compilation with CSS artifact generation
- Theme Studio for live preview

## Architecture

### Backend

| Component | Path | Role |
|-----------|------|------|
| Theme model | `app/Models/Theme.php` | Theme storage |
| ThemeAssignment model | `app/Models/ThemeAssignment.php` | Theme-to-site mapping per mode |
| ThemeOverride model | `app/Models/ThemeOverride.php` | Token overrides at various scopes |
| ThemeVersion model | `app/Models/ThemeVersion.php` | Compiled CSS artifacts |
| ThemeEngineController | `app/Http/Controllers/Api/V1/ThemeEngineController.php` | API endpoints |
| DesignTokenGenerator | `app/Domain/Theme/Services/DesignTokenGenerator.php` | Legacy token CSS generation |
| SystemThemeSeeder | `app/Domain/Theme/Services/SystemThemeSeeder.php` | Install system themes |
| ThemeResolver | `app/Services/Theme/ThemeResolver.php` | Resolves active theme tokens |
| ThemeCompiler | `app/Services/Theme/ThemeCompiler.php` | Compiles to CSS artifacts |
| TokenMerger | `app/Services/Theme/TokenMerger.php` | Merges multiple token sources |
| ReferenceResolver | `app/Services/Theme/ReferenceResolver.php` | Resolves `{color.primary}` refs |
| FrameRegistry | `app/Services/Theme/Studio/FrameRegistry.php` | Theme Studio frame definitions |
| FrameRenderer | `app/Services/Theme/Studio/FrameRenderer.php` | Renders preview frames |
| ThemeCoverageService | `app/Services/Theme/Coverage/ThemeCoverageService.php` | Coverage analysis |

### Theme Document Format (W3C Design Tokens)

```json
{
  "$metadata": {
    "name": "My Theme",
    "modes": ["light", "dark"]
  },
  "color": {
    "primary": {
      "$value": "#3b82f6",
      "$type": "color",
      "$modes": { "dark": "#60a5fa" }
    },
    "text": {
      "$value": "#1e293b",
      "$type": "color",
      "$modes": { "dark": "#f8fafc" }
    }
  },
  "typography": {
    "heading": {
      "fontFamily": { "$value": "'Inter', sans-serif", "$type": "fontFamily" }
    }
  }
}
```

## Token Resolution

### Legacy System (DesignTokenGenerator)

```
Defaults (hardcoded) → Theme config.tokens → theme_customizations table
```

Generated as `:root { --color-primary: #3b82f6; ... }`

### New System (ThemeResolver)

```
System theme document → Fork document → ThemeOverrides (tenant → site → page → block)
```

Resolution via `ThemeResolver::resolveFresh(ResolveRequest)`:
1. Locate theme via ThemeAssignment for site+mode
2. Load theme document
3. If theme has parent, merge parent document first
4. Apply ThemeOverrides in scope order
5. Resolve references (`{color.primary}` -> actual value)
6. Flatten to token map
7. Return ResolvedTheme with CSS variables and content hash

## Default Design Tokens (50+)

### Colors (17 tokens)
`color-primary`, `color-primary-dark`, `color-primary-light`, `color-secondary`, `color-accent`, `color-text`, `color-text-muted`, `color-text-inverse`, `color-bg`, `color-bg-alt`, `color-bg-inverse`, `color-border`, `color-border-light`, `color-success`, `color-warning`, `color-danger`, `color-info`

### Typography (14 tokens)
`font-heading`, `font-body`, `font-mono`, `font-size-base` (clamp-responsive), `font-size-sm/lg/xl/2xl/3xl`, `font-weight-normal/medium/bold`, `line-height-tight/normal/relaxed`, `letter-spacing-tight/normal/wide`

### Spacing (8 tokens)
`space-1` through `space-16` (4px base grid)

### Layout (3 tokens)
`container-width`, `container-padding`, `grid-gap`

### Effects (8 tokens)
`border-radius-sm/md/lg/full`, `shadow-sm/md/lg/xl`, `transition-fast/base/slow`

## Theme Lifecycle

### Creating a Theme

1. **System themes** are stored in `storage/app/themes/system/{slug}/theme.json`
2. `SystemThemeSeeder::seed(Site)` reads manifests and creates Theme records
3. System themes have `is_system = true` and `site_id = null`

### Activating a Theme

- Legacy: `Site.active_theme_id` points to a Theme
- New: `ThemeAssignment` maps theme to site for a mode

### Forking a Theme

```
POST /sites/{site}/theme-engine/themes/{theme}/fork
```

Creates a site-specific copy with `parent_theme_id` set. The fork is editable.

### Editing a Theme

```
PUT /sites/{site}/theme-engine/themes/{theme}
```

Only non-system themes can be edited. Updates the `document` JSON.

### Token Overrides

```
POST /sites/{site}/theme-engine/overrides
```

Body:
```json
{
  "scope": "site",    // tenant | site | page | block
  "mode": "light",
  "overrides": [
    { "token_path": "color.primary", "value": "#ff0000" }
  ]
}
```

### Compilation

`ThemeCompiler::compile(siteId, mode)`:
1. Resolves all tokens for site+mode
2. Generates CSS with all custom properties
3. Stores as `ThemeVersion` with `css_artifact_path`
4. CSS artifact is copied to build output during publish

### Import/Export

- **Export:** Returns the theme document as W3C JSON
- **Import:** Accepts a W3C document and creates a new Theme

## Theme Studio

Interactive preview environment for theme editing.

- `GET /sites/{site}/theme-engine/studio/frames` -- lists available preview frames
- `GET /sites/{site}/theme-engine/studio/frame/{slug}?theme_id=X&mode=light` -- renders frame HTML

Frames are sample layouts (hero section, blog post, card grid) that show how tokens affect real content.

## Theme Coverage

```
GET /sites/{site}/theme-engine/themes/{theme}/coverage?mode=light
```

Analyzes which block types are properly covered by the theme's tokens. Reports missing or incomplete token mappings.

## Font Handling

Themes can specify fonts in two ways:

1. **Google Fonts** (auto-imported): `DesignTokenGenerator` detects non-system fonts in `font-heading`/`font-body` and generates `@import` URLs
2. **Self-hosted WOFF2**: Theme ZIP includes font files, referenced in `theme.json` `fonts` array with `woff2_url`

Font preloads are generated as `<link rel="preload" as="font">` tags with `font-display: swap`.

## Theme ZIP Structure

```
my-theme/
  theme.json          ← manifest with tokens and metadata
  style.css           ← full stylesheet (lazy-loaded)
  critical.css        ← above-fold CSS (inlined in <head>)
  screenshot.png      ← preview image
  fonts/              ← self-hosted font files
  assets/             ← images/icons used in CSS
```

See `docs/THEME-SPEC.md` for the complete theme.json specification.
