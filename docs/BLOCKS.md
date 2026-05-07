# Block System

## Overview

Blocks are the fundamental content units. Each page/post stores a tree of blocks (polymorphic via `blockable_type`/`blockable_id`). Blocks can be nested (parent/child via `parent_block_id`).

## Three-Layer Model

Every block type can exist across three independent layers. Not all blocks are complete across all layers.

| Layer | Location | Count | Purpose |
|-------|----------|-------|---------|
| Frontend (React) | `resources/admin/src/components/blocks/{type}/` | **68** | Editor UI + canvas preview |
| Rendering (Blade) | `resources/views/blocks/{type}.blade.php` | **69** | Server-side HTML for publishing |
| Backend (PHP) | `app/Domain/Blocks/Definitions/{Type}BlockDefinition.php` | **19** concrete + 1 base | Validation rules + sanitization |

### Coverage Summary

- **18 blocks** are fully complete across all three layers.
- **50 blocks** have frontend + blade but NO PHP definition (no server-side validation).
- **1 orphan blade** (`quote.blade.php`) has no matching frontend component (frontend uses `pullquote` instead).

Run `composer audit-blocks` or `bash scripts/block-audit.sh` to check current layer coverage.

## Architecture

### Backend Components

| Component | Path | Responsibility |
|-----------|------|----------------|
| Block model | `app/Models/Block.php` | Eloquent model, stores type/data/style/order |
| BlockDefinition interface | `app/Domain/Blocks/Definitions/BlockDefinition.php` | Contract for block types |
| BlockRegistry | `app/Domain/Blocks/Services/BlockRegistry.php` | Registry of all definitions |
| BlockService | `app/Domain/Blocks/Services/BlockService.php` | Sync/read block trees |
| Blade templates | `resources/views/blocks/*.blade.php` | HTML rendering for publish |
| BuildPageService | `app/Domain/Publishing/Services/BuildPageService.php` | Orchestrates rendering |

### Frontend Components

| Component | Path | Responsibility |
|-----------|------|----------------|
| Block editors | `resources/admin/src/components/blocks/{type}/Editor.tsx` | Edit UI |
| Block previews | `resources/admin/src/components/blocks/{type}/Preview.tsx` | In-editor preview |
| Block definitions | `resources/admin/src/components/blocks/{type}/definition.ts` | Type metadata |
| Block registry | `resources/admin/src/components/blocks/registry.ts` | Frontend registry |

## BlockDefinition Interface

```php
interface BlockDefinition
{
    public function type(): string;           // e.g., 'hero', 'text', 'image'
    public function category(): string;       // e.g., 'content', 'media', 'layout'
    public function validationRules(): array;  // Laravel validation rules for data
    public function sanitizationConfig(): array; // HTMLPurifier config
    public function allowsChildren(): bool;    // Can contain nested blocks
    public function maxChildren(): ?int;       // Null = unlimited
}
```

## Block Types

### Backend Definitions (registered in BlockRegistry)

| Type | Category | Children | File |
|------|----------|----------|------|
| `accordion` | layout | yes | `AccordionBlockDefinition.php` |
| `button` | content | no | `ButtonBlockDefinition.php` |
| `code` | content | no | `CodeBlockDefinition.php` |
| `columns` | layout | yes | `ColumnsBlockDefinition.php` |
| `contact-form` | interactive | no | `ContactFormBlockDefinition.php` |
| `divider` | content | no | `DividerBlockDefinition.php` |
| `flipbook` | media | no | `FlipbookBlockDefinition.php` |
| `heading` | content | no | `HeadingBlockDefinition.php` |
| `hero` | content | no | `HeroBlockDefinition.php` |
| `html-embed` | content | no | `HtmlEmbedBlockDefinition.php` |
| `image` | media | no | `ImageBlockDefinition.php` |
| `quote` | content | no | `QuoteBlockDefinition.php` |
| `rich-text` | content | no | `RichTextBlockDefinition.php` |
| `scroll_page` | layout | yes | `ScrollPageBlockDefinition.php` |
| `section` | layout | yes | `SectionBlockDefinition.php` |
| `spacer` | content | no | `SpacerBlockDefinition.php` |
| `tabs` | layout | yes | `TabsBlockDefinition.php` |
| `text` | content | no | `TextBlockDefinition.php` |
| `video` | media | no | `VideoBlockDefinition.php` |

### All Blade Templates (69 files)

These are the renderable block types in `resources/views/blocks/`. Note that 68 of these map to frontend components; 1 (`quote`) is an orphan with no frontend counterpart:

**Content:** paragraph, heading, rich-text, text, quote, pullquote, code, button, divider, spacer, list, dropcap, caption, footnote, sidenote, textdivider, runningtext

**Media:** image, imagecaption, video, audio, gallery, flipbook, beforeafter, map, socialembed

**Layout:** columns, section, container, group, grid, tabs, accordion, fullbleed, overlap, stickysidebar, modal

**Navigation:** menu, anchormenu, breadcrumbs, toc, readingprogress

**Dynamic Content:** latestposts, postgrid, postcard, relatedposts, categorylist, authorbox

**Marketing:** hero, ctabanner, newsletter, pricingcard, pricingtable, featuregrid, featurecomparison, logostrip, testimonial, stats, timeline, chart, paywall, sharebuttons

**Special:** scroll_page (multi-section scroll), icon, table, tooltip, customform

## Block Data Structure

Each block in the database:

```json
{
  "id": "uuid",
  "type": "hero",
  "data": {
    "title": "Welcome",
    "subtitle": "A great site",
    "background_image": "/assets/hero.jpg",
    "cta_text": "Learn More",
    "cta_url": "/about",
    "__style": { "padding": "4rem 2rem", "textAlign": "center" },
    "__animation": { "type": "fadeIn", "delay": 200 },
    "__responsive": { "mobile": { "hidden": false } },
    "__advanced": { "cssClass": "my-hero", "anchor": "top" }
  },
  "style": { "padding": "4rem 2rem" },
  "order": 0,
  "children": []
}
```

The `__style`, `__animation`, `__responsive`, `__advanced` keys are stored within `data` JSON and extracted by `BlockService` when returning to the frontend.

## Rendering Pipeline

### At Publish Time

1. `BuildPageService::renderBlock(Block $block, Site $site)` is called for each top-level block
2. Block data is sanitized via `SanitizationService`
3. Children are recursively rendered
4. Special enrichment for `image` blocks (resolves asset URLs, dimensions, variants)
5. Special enrichment for `flipbook` blocks (copies PDF to public path)
6. The Blade template `blocks.{type}` is rendered with: `$data`, `$children` (HTML string), `$childrenArray`, `$site`
7. If template doesn't exist: outputs `<!-- Unknown block type: {type} -->`

### Blade Template Variables

Every block Blade template receives:

| Variable | Type | Description |
|----------|------|-------------|
| `$data` | array | Sanitized block data |
| `$children` | string | Pre-rendered children HTML |
| `$childrenArray` | array | Individual child HTML strings |
| `$site` | Site | Current site model |

### Example: Hero Block Template

```blade
{{-- resources/views/blocks/hero.blade.php --}}
<section class="hero-section" style="background-image: url('{{ $data['background_image'] ?? '' }}')">
  <div class="hero-overlay"></div>
  <div class="hero-content">
    <h1>{{ $data['title'] ?? '' }}</h1>
    @if(!empty($data['subtitle']))
      <p class="hero-subtitle">{{ $data['subtitle'] }}</p>
    @endif
    @if(!empty($data['cta_text']))
      <a href="{{ $data['cta_url'] ?? '#' }}" class="hero-cta">{{ $data['cta_text'] }}</a>
    @endif
  </div>
</section>
```

## Block Sync (API)

The `PUT /sites/{site}/pages/{page}/blocks` endpoint replaces the entire block tree:

```json
{
  "blocks": [
    {
      "id": "optional-uuid",
      "type": "hero",
      "order": 0,
      "data": { "title": "Hello" },
      "style": {},
      "children": [
        { "type": "text", "order": 0, "data": { "content": "..." } }
      ]
    }
  ]
}
```

`BlockService::syncBlocks()` deletes all existing blocks and recreates from the payload within a transaction.

## Known Gaps

### 50 Blocks Missing PHP Definitions

The following frontend blocks have no corresponding `BlockDefinition` PHP class. This means no server-side validation or sanitization rules exist for them:

`anchormenu`, `audio`, `authorbox`, `beforeafter`, `breadcrumbs`, `caption`, `categorylist`, `chart`, `container`, `ctabanner`, `customform`, `dropcap`, `featurecomparison`, `featuregrid`, `footnote`, `fullbleed`, `gallery`, `grid`, `group`, `icon`, `imagecaption`, `latestposts`, `list`, `logostrip`, `map`, `menu`, `modal`, `newsletter`, `overlap`, `paragraph`, `paywall`, `postcard`, `postgrid`, `pricingcard`, `pricingtable`, `pullquote`, `readingprogress`, `relatedposts`, `runningtext`, `sharebuttons`, `sidenote`, `socialembed`, `stats`, `stickysidebar`, `table`, `testimonial`, `textdivider`, `timeline`, `toc`, `tooltip`

### Naming Mismatch: quote vs pullquote

- `quote.blade.php` exists but has no frontend component.
- The frontend uses `pullquote` instead.
- `QuoteBlockDefinition.php` exists in PHP but maps to the orphan blade.

### Block Audit Scripts

Two audit tools are available:

**Node.js audit** (recommended — detailed JSON report):
```bash
npm run blocks:audit           # Full table + JSON report
node scripts/audit-blocks.mjs --json-only   # JSON only
node scripts/audit-blocks.mjs --no-color    # CI-friendly
```

**Bash audit** (quick summary):
```bash
composer audit-blocks          # summary
composer audit-blocks-verbose  # per-block detail
```

#### Statuses

| Status | Meaning |
|--------|---------|
| `COMPLETE` | All 3 layers present (frontend + blade + PHP definition) |
| `MISSING_BACKEND` | Frontend + blade exist, but no PHP BlockDefinition class |
| `MISSING_BLADE` | Frontend exists, but no blade template |
| `MISSING_FRONTEND_FILE` | Frontend folder exists but missing required files |
| `NOT_REGISTERED` | Frontend folder exists but not imported in blocks/index.ts |
| `ORPHAN_BLADE` | Blade template exists with no matching frontend component |
| `ORPHAN_BACKEND` | PHP definition exists with no matching frontend component |

#### JSON Output

Written to `storage/app/block-audit.json` with structure:
```json
{
  "generatedAt": "2026-05-08T...",
  "summary": { "total": 69, "complete": 18, "incomplete": 51, "byStatus": {...} },
  "blocks": [{ "type": "hero", "status": "COMPLETE", ... }]
}
```

#### Exit Code

`npm run blocks:audit` exits with code 1 if any block is not COMPLETE. This is intentional — it can be used as a CI gate once all blocks are complete.

#### Current State (as of last audit)

- 18 COMPLETE
- 50 MISSING_BACKEND
- 1 ORPHAN_BACKEND (`quote` — PHP def exists but frontend uses `pullquote`)
