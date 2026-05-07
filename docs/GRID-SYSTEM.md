# Grid System

## Overview

The grid system provides CSS Grid-based page layouts. Instead of linear block stacking, content can be placed into named grid areas with full responsive control.

## Models

### Grid

**File:** `app/Models/Grid.php`
**Table:** `grids`

| Field | Type | Description |
|-------|------|-------------|
| id | uuid | PK |
| site_id | uuid | FK to sites |
| name | string | Display name |
| slug | string | CSS identifier |
| description | string | |
| col_tracks | string | CSS `grid-template-columns` value |
| row_tracks | string | CSS `grid-template-rows` value |
| areas | string | CSS `grid-template-areas` value |
| gap_x | string | Column gap (CSS value) |
| gap_y | string | Row gap (CSS value) |
| container_width | string | Max width (e.g., `1200px`) |
| container_padding | string | Horizontal padding |
| min_height | string | Minimum grid height |
| align_items | string | CSS `align-items` |
| justify_items | string | CSS `justify-items` |
| overflow_x | string | Overflow handling |
| layout_mode | string | `default`, `horizontal-scroll`, `snap-sections` |
| background_json | jsonb | `{color, gradient, image, overlay}` |
| full_bleed | boolean | Full viewport width (ignores container) |
| is_preset | boolean | Pre-built template |
| breakpoints_json | jsonb | Responsive overrides (see below) |

### GridPosition

**File:** `app/Models/GridPosition.php`
**Table:** `grid_positions`

| Field | Type | Description |
|-------|------|-------------|
| id | uuid | PK |
| grid_id | uuid | FK to grids |
| area_name | string | Matches a named area in grid |
| label | string | Human-readable label |
| type | string | Position type |
| config_json | jsonb | Position-specific config |
| scope | string | Scope of content |
| is_overridable | boolean | Can be overridden per-page |
| mobile_order | integer | Order on mobile (for reordering) |
| min_height | string | CSS min-height |
| align_self | string | CSS align-self |
| justify_self | string | CSS justify-self |
| max_width | string | CSS max-width |
| overflow | string | CSS overflow |
| background_json | jsonb | `{color, gradient, image, overlay}` |
| padding_json | jsonb | `{top, right, bottom, left}` |
| border_json | jsonb | `{width, color, style, radius}` |
| shadow | string | CSS box-shadow |
| css_class | string | Additional CSS classes |
| full_bleed | boolean | Position breaks out of grid to full width |

### GridAssignment

**File:** `app/Models/GridAssignment.php`
**Table:** `grid_assignments`

| Field | Type | Description |
|-------|------|-------------|
| id | uuid | PK |
| site_id | uuid | FK |
| grid_id | uuid | FK to grids |
| assignable_type | string | `page`, `post`, `category`, `post_type`, `rule`, `default` |
| assignable_id | string | Target ID or pattern |
| priority | integer | Lower = higher priority |
| is_active | boolean | |

### PositionOverride

**File:** `app/Models/PositionOverride.php`
**Table:** `position_overrides`

Per-page/post overrides for grid position content.

### GridPositionBlock

**File:** `app/Models/GridPositionBlock.php`
**Table:** `grid_position_blocks`

Maps blocks to specific grid positions.

## Grid Resolution

**Service:** `app/Domain/Grid/Services/GridResolver.php`

When rendering a page/post, GridResolver determines which grid to use:

```
1. Direct grid_id on the page/post         (highest priority)
2. Exact page/post assignment              (assignable_type = 'page'/'post', assignable_id = content.id)
3. Category assignment                     (posts: assignable_type = 'category', assignable_id = category.id)
4. Post type assignment                    (assignable_type = 'post_type', assignable_id = 'post'/'page')
5. URL pattern rule                        (assignable_type = 'rule', fnmatch pattern)
6. Site default                            (assignable_type = 'default')
```

Returns `null` if no grid matches (falls back to standard block rendering).

## CSS Generation

**Service:** `app/Domain/Grid/Services/GridCssGenerator.php`

Generates complete CSS for a grid including:

1. **Grid wrapper** (if `full_bleed`): full viewport width with background
2. **Grid container** (`.site-grid`): columns, rows, areas, gaps, alignment
3. **Layout modes**:
   - `horizontal-scroll`: horizontal scrolling with snap points
   - `snap-sections`: vertical scroll-snap (full-page sections)
4. **Position classes** (`.pos-{area_name}`): area assignment + all position styling
5. **Background overlays**: pseudo-elements for color/gradient overlays
6. **Responsive breakpoints**: tablet (1024px) and mobile (768px) overrides
7. **Mobile ordering**: reorder positions on small screens

### Breakpoints JSON Structure

```json
{
  "tablet": {
    "col_tracks": "1fr 1fr",
    "row_tracks": "auto auto auto",
    "areas": "\"header header\" \"sidebar content\" \"footer footer\"",
    "gap_x": "16px",
    "gap_y": "16px",
    "container_padding": "16px"
  },
  "mobile": {
    "col_tracks": "1fr",
    "areas": "\"header\" \"content\" \"sidebar\" \"footer\"",
    "gap_x": "12px",
    "container_padding": "12px"
  }
}
```

## Grid Rendering

**Service:** `app/Domain/Grid/Services/GridRenderer.php`

```php
render(Grid $grid, Page|Post $content, Site $site): ['css' => string, 'html' => string]
```

Output structure:
```html
<!-- grid: Magazine Layout (uuid) -->
<div class="site-grid-wrap" data-grid="magazine-layout">  <!-- if full_bleed -->
  <div class="site-grid">
    <div class="pos-header">...rendered position content...</div>
    <div class="pos-sidebar">...</div>
    <div class="pos-content">...</div>
    <div class="pos-footer">...</div>
  </div>
</div>
```

### PositionRenderer

**Service:** `app/Domain/Grid/Services/PositionRenderer.php`

Renders the content within each grid position:
- Blocks assigned to the position (via GridPositionBlock)
- Position overrides per page
- Falls back to position's default content

## Presets

**Service:** `app/Domain/Grid/Services/GridPresetSeeder.php`

Seeds common grid layouts:
- Two-column (sidebar + content)
- Three-column
- Full-width hero + content
- Magazine spread
- Dashboard grid

Triggered via `POST /sites/{site}/grids/seed-presets`.

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/sites/{site}/grids` | List grids |
| POST | `/sites/{site}/grids` | Create grid |
| GET | `/sites/{site}/grids/{grid}` | Get grid with positions |
| PUT | `/sites/{site}/grids/{grid}` | Update grid |
| DELETE | `/sites/{site}/grids/{grid}` | Delete grid |
| PUT | `/sites/{site}/grids/{grid}/positions` | Sync positions |
| GET | `/sites/{site}/grid-assignments` | List assignments |
| POST | `/sites/{site}/grid-assignments` | Create assignment |
| PUT | `/sites/{site}/grid-assignments/{a}` | Update assignment |
| DELETE | `/sites/{site}/grid-assignments/{a}` | Delete assignment |
| POST | `/sites/{site}/grid-positions/{p}/override` | Create position override |
| DELETE | `/sites/{site}/position-overrides/{o}` | Delete override |
| POST | `/sites/{site}/grids/seed-presets` | Seed preset grids |

## Integration with Publishing

In `BuildPageService::build()`:

1. Check if content has an explicit non-standard layout -> skip grid
2. Call `GridResolver::resolve(content, site)`
3. If grid found: use `grid-layout.blade.php` with generated CSS + HTML
4. If no grid: fall back to standard block rendering

The grid CSS is inlined in the page `<style>` tag for zero extra requests.
