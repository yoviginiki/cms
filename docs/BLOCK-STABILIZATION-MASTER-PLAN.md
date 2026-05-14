# Block Stabilization Master Plan

> **Date**: 2026-05-14
> **Status**: Audit complete. Implementation not started.
> **Scope**: Roadmap to bring all 69 Ensodo CMS blocks to COMPLETE status.

---

## 1. Summary

The Ensodo CMS has 69 block types. Only 18 are fully COMPLETE (frontend + Blade + PHP definition). 50 blocks are MISSING_BACKEND (no PHP `BlockDefinition`), and 1 is an ORPHAN_BACKEND (quote — PHP exists but frontend uses pullquote). This plan defines the safest order to fix all blocks without breaking the CMS.

**Current counts:**
| Status | Count |
|--------|-------|
| COMPLETE | 18 |
| MISSING_BACKEND | 50 |
| ORPHAN_BACKEND | 1 |
| **Total** | **69** |

**Reference block**: Hero — the most fully developed block with P0-P3 controls, inline editing, responsive overrides, and full Codex review coverage.

---

## 2. Full Block Inventory

### Unified Block Inventory (all 69 blocks)

| Block | FE | Reg | Blade | PHP | Status | Group | Risk | Priority | Action |
|-------|:--:|:---:|:-----:|:---:|--------|-------|------|----------|--------|
| accordion | Y | Y | Y | Y | COMPLETE | interactive | — | — | Done |
| anchormenu | Y | Y | Y | N | MISSING_BACKEND | navigation | LOW | P2 | Add PHP def |
| audio | Y | Y | Y | N | MISSING_BACKEND | media | LOW | P1 | Add PHP def |
| authorbox | Y | Y | Y | N | MISSING_BACKEND | marketing | LOW | P2 | Add PHP def |
| beforeafter | Y | Y | Y | N | MISSING_BACKEND | media | MED | P2 | Add PHP def |
| breadcrumbs | Y | Y | Y | N | MISSING_BACKEND | navigation | LOW | P2 | Add PHP def |
| button | Y | Y | Y | Y | COMPLETE | content | — | — | Done |
| caption | Y | Y | Y | N | MISSING_BACKEND | content | LOW | P0 | Add PHP def |
| categorylist | Y | Y | Y | N | MISSING_BACKEND | dynamic | MED | P3 | Add PHP def |
| chart | Y | Y | Y | N | MISSING_BACKEND | dynamic | MED | P3 | Add PHP def |
| code | Y | Y | Y | Y | COMPLETE | content | — | — | Done |
| columns | Y | Y | Y | Y | COMPLETE | layout | — | — | Done |
| contact-form | Y | Y | Y | Y | COMPLETE | interactive | — | — | Done |
| container | Y | Y | Y | N | MISSING_BACKEND | layout | LOW | P1 | Add PHP def |
| ctabanner | Y | Y | Y | N | MISSING_BACKEND | marketing | LOW | P1 | Add PHP def |
| customform | Y | Y | Y | N | MISSING_BACKEND | dynamic | HIGH | P3 | Add PHP def |
| divider | Y | Y | Y | Y | COMPLETE | content | — | — | Done |
| dropcap | Y | Y | Y | N | MISSING_BACKEND | content | LOW | P0 | Add PHP def |
| featurecomparison | Y | Y | Y | N | MISSING_BACKEND | marketing | MED | P2 | Add PHP def |
| featuregrid | Y | Y | Y | N | MISSING_BACKEND | marketing | MED | P2 | Add PHP def |
| flipbook | Y | Y | Y | Y | COMPLETE | media | — | — | Done |
| footnote | Y | Y | Y | N | MISSING_BACKEND | content | LOW | P0 | Add PHP def |
| fullbleed | Y | Y | Y | N | MISSING_BACKEND | layout | MED | P1 | Add PHP def |
| gallery | Y | Y | Y | N | MISSING_BACKEND | media | LOW | P1 | Add PHP def |
| grid | Y | Y | Y | N | MISSING_BACKEND | layout | LOW | P1 | Add PHP def |
| group | Y | Y | Y | N | MISSING_BACKEND | layout | LOW | P1 | Add PHP def |
| heading | Y | Y | Y | Y | COMPLETE | content | — | — | Done |
| hero | Y | Y | Y | Y | COMPLETE | marketing | — | — | Done (pilot) |
| html-embed | Y | Y | Y | Y | COMPLETE | content | — | — | Done |
| icon | Y | Y | Y | N | MISSING_BACKEND | media | LOW | P1 | Add PHP def |
| image | Y | Y | Y | Y | COMPLETE | media | — | — | Done |
| imagecaption | Y | Y | Y | N | MISSING_BACKEND | media | LOW | P1 | Add PHP def |
| latestposts | Y | Y | Y | N | MISSING_BACKEND | dynamic | MED | P3 | Add PHP def |
| list | Y | Y | Y | N | MISSING_BACKEND | content | LOW | P0 | Add PHP def |
| logostrip | Y | Y | Y | N | MISSING_BACKEND | marketing | LOW | P1 | Add PHP def |
| map | Y | Y | Y | N | MISSING_BACKEND | dynamic | MED | P3 | Add PHP def |
| menu | Y | Y | Y | N | MISSING_BACKEND | navigation | LOW | P2 | Add PHP def |
| modal | Y | Y | Y | N | MISSING_BACKEND | interactive | MED | P2 | Add PHP def |
| newsletter | Y | Y | Y | N | MISSING_BACKEND | dynamic | MED | P3 | Add PHP def |
| overlap | Y | Y | Y | N | MISSING_BACKEND | layout | MED | P1 | Add PHP def |
| paragraph | Y | Y | Y | N | MISSING_BACKEND | content | LOW | P0 | Add PHP def |
| paywall | Y | Y | Y | N | MISSING_BACKEND | dynamic | HIGH | P3 | Add PHP def |
| postcard | Y | Y | Y | N | MISSING_BACKEND | marketing | MED | P2 | Add PHP def |
| postgrid | Y | Y | Y | N | MISSING_BACKEND | marketing | MED | P2 | Add PHP def |
| pricingcard | Y | Y | Y | N | MISSING_BACKEND | marketing | MED | P2 | Add PHP def |
| pricingtable | Y | Y | Y | N | MISSING_BACKEND | marketing | MED | P2 | Add PHP def |
| pullquote | Y | Y | Y | N | MISSING_BACKEND | content | LOW | P0 | Resolve quote orphan |
| quote | N | N | Y | Y | ORPHAN_BACKEND | content | MED | P0 | Merge into pullquote |
| readingprogress | Y | Y | Y | N | MISSING_BACKEND | navigation | LOW | P2 | Add PHP def |
| relatedposts | Y | Y | Y | N | MISSING_BACKEND | dynamic | MED | P3 | Add PHP def |
| rich-text | Y | Y | Y | Y | COMPLETE | content | — | — | Done |
| runningtext | Y | Y | Y | N | MISSING_BACKEND | content | LOW | P1 | Add PHP def |
| scroll_page | Y | Y | Y | Y | COMPLETE | layout | — | — | Done |
| section | Y | Y | Y | Y | COMPLETE | layout | — | — | Done |
| sharebuttons | Y | Y | Y | N | MISSING_BACKEND | dynamic | LOW | P3 | Add PHP def |
| sidenote | Y | Y | Y | N | MISSING_BACKEND | content | LOW | P1 | Add PHP def |
| socialembed | Y | Y | Y | N | MISSING_BACKEND | dynamic | MED | P3 | Add PHP def |
| spacer | Y | Y | Y | Y | COMPLETE | content | — | — | Done |
| stats | Y | Y | Y | N | MISSING_BACKEND | marketing | LOW | P1 | Add PHP def |
| stickysidebar | Y | Y | Y | N | MISSING_BACKEND | layout | MED | P2 | Add PHP def |
| table | Y | Y | Y | N | MISSING_BACKEND | content | MED | P2 | Add PHP def |
| tabs | Y | Y | Y | Y | COMPLETE | interactive | — | — | Done |
| testimonial | Y | Y | Y | N | MISSING_BACKEND | marketing | LOW | P1 | Add PHP def |
| text | Y | Y | Y | Y | COMPLETE | content | — | — | Done |
| textdivider | Y | Y | Y | N | MISSING_BACKEND | content | LOW | P1 | Add PHP def |
| timeline | Y | Y | Y | N | MISSING_BACKEND | content | MED | P2 | Add PHP def |
| toc | Y | Y | Y | N | MISSING_BACKEND | navigation | LOW | P2 | Add PHP def |
| tooltip | Y | Y | Y | N | MISSING_BACKEND | content | LOW | P2 | Add PHP def |
| video | Y | Y | Y | Y | COMPLETE | media | — | — | Done |

> FE = frontend folder, Reg = registered in index.ts, Blade = .blade.php exists, PHP = BlockDefinition exists

### Complete Blocks (18)

| Block | Category | Children | Notes |
|-------|----------|----------|-------|
| accordion | interactive | yes (20) | Collapsible sections |
| button | content | no | CTA button with link |
| code | content | no | Code snippet with language |
| columns | layout | yes (12) | Multi-column layout |
| contact-form | interactive | no | Form with fields |
| divider | content | no | Horizontal rule |
| flipbook | media | no | Page-flip magazine |
| heading | content | no | H1-H6 heading |
| hero | marketing | no | Hero section (pilot block) |
| html-embed | content | no | Raw HTML/embed |
| image | media | no | Image with caption |
| rich-text | content | no | WYSIWYG content |
| scroll_page | layout | yes | Scroll-based pages |
| section | layout | yes (20) | Generic container |
| spacer | content | no | Vertical space |
| tabs | interactive | yes (20) | Tabbed content |
| text | content | no | Plain text block |
| video | media | no | Video embed |

### MISSING_BACKEND Blocks (50)

| Block | Category | Risk | Priority | Purpose |
|-------|----------|------|----------|---------|
| paragraph | content | LOW | P0 | Basic paragraph text |
| list | content | LOW | P0 | Ordered/unordered list |
| caption | content | LOW | P0 | Image/figure caption |
| dropcap | content | LOW | P0 | Drop capital letter |
| footnote | content | LOW | P0 | Footnote reference |
| pullquote | content | LOW | P0 | Pull quote (see quote resolution) |
| sidenote | content | LOW | P1 | Marginal note |
| runningtext | content | LOW | P1 | Continuous text block |
| textdivider | content | LOW | P1 | Text with divider line |
| container | layout | LOW | P1 | Generic wrapper |
| grid | layout | LOW | P1 | CSS grid layout |
| group | layout | LOW | P1 | Grouping wrapper |
| fullbleed | layout | MEDIUM | P1 | Full-width section |
| overlap | layout | MEDIUM | P1 | Overlapping layers |
| stickysidebar | layout | MEDIUM | P2 | Sticky sidebar layout |
| gallery | media | LOW | P1 | Image gallery |
| audio | media | LOW | P1 | Audio player |
| imagecaption | media | LOW | P1 | Image with caption layout |
| icon | media | LOW | P1 | Icon display |
| beforeafter | media | MEDIUM | P2 | Before/after image slider |
| ctabanner | marketing | LOW | P1 | Call-to-action banner |
| testimonial | marketing | LOW | P1 | Customer testimonial |
| logostrip | marketing | LOW | P1 | Logo/partner strip |
| stats | marketing | LOW | P1 | Statistics display |
| featuregrid | marketing | MEDIUM | P2 | Feature grid cards |
| featurecomparison | marketing | MEDIUM | P2 | Feature comparison table |
| pricingcard | marketing | MEDIUM | P2 | Pricing card |
| pricingtable | marketing | MEDIUM | P2 | Pricing table |
| postcard | marketing | MEDIUM | P2 | Post preview card |
| postgrid | marketing | MEDIUM | P2 | Post grid layout |
| authorbox | marketing | LOW | P2 | Author bio box |
| anchormenu | navigation | LOW | P2 | Anchor link menu |
| breadcrumbs | navigation | LOW | P2 | Breadcrumb trail |
| toc | navigation | LOW | P2 | Table of contents |
| menu | navigation | LOW | P2 | Navigation menu |
| readingprogress | navigation | LOW | P2 | Reading progress bar |
| table | content | MEDIUM | P2 | Data table |
| timeline | content | MEDIUM | P2 | Timeline display |
| tooltip | content | LOW | P2 | Tooltip popup |
| modal | interactive | MEDIUM | P2 | Modal dialog |
| latestposts | dynamic | MEDIUM | P3 | Latest posts feed |
| relatedposts | dynamic | MEDIUM | P3 | Related posts |
| categorylist | dynamic | MEDIUM | P3 | Category listing |
| socialembed | dynamic | MEDIUM | P3 | Social media embed |
| map | dynamic | MEDIUM | P3 | Map embed |
| chart | dynamic | MEDIUM | P3 | Chart/graph |
| newsletter | dynamic | MEDIUM | P3 | Newsletter signup |
| customform | dynamic | HIGH | P3 | Custom form builder |
| paywall | dynamic | HIGH | P3 | Paywall gate |
| sharebuttons | dynamic | LOW | P3 | Social share buttons |

### ORPHAN_BACKEND (1)

| Block | Issue | Resolution |
|-------|-------|------------|
| quote | PHP + Blade exist, no frontend. Frontend uses `pullquote` with different data keys. | See Section 8. |

---

## 3. Block Groups

### Core Content (11 blocks)
paragraph, list, caption, dropcap, footnote, pullquote, sidenote, runningtext, textdivider, table, tooltip

**Status**: 0 complete. All MISSING_BACKEND.
**Pattern**: Simple leaf blocks, 1-3 fields, no children. Easiest to batch.

### Layout (6 blocks)
container, grid, group, fullbleed, overlap, stickysidebar

**Status**: 0 complete (columns, section, scroll_page already done).
**Pattern**: Container blocks with `allowsChildren`. Moderate validation.

### Media (5 blocks)
gallery, audio, imagecaption, icon, beforeafter

**Status**: 0 complete (image, video, flipbook already done).
**Pattern**: Asset-based blocks with URLs, dimensions, alt text.

### Marketing (11 blocks)
ctabanner, testimonial, logostrip, stats, featuregrid, featurecomparison, pricingcard, pricingtable, postcard, postgrid, authorbox

**Status**: 0 complete (hero already done).
**Pattern**: Items arrays, rich styling, varied complexity.

### Navigation/Content Structure (5 blocks)
anchormenu, breadcrumbs, toc, menu, readingprogress

**Status**: 0 complete.
**Pattern**: Auto-generated content, minimal stored data.

### Advanced/Dynamic (10 blocks)
latestposts, relatedposts, categorylist, socialembed, map, chart, newsletter, customform, paywall, sharebuttons

**Status**: 0 complete (contact-form already done).
**Pattern**: External data sources, API integrations, complex logic.

---

## 4. Priority Plan

### P0 — Critical Fixes (6 blocks)
Must be done first. Fixes orphans and core content gaps.

1. **quote/pullquote resolution** — reconcile the orphan (see Section 8)
2. **paragraph** — most basic content block, heavily used
3. **list** — fundamental content block
4. **caption** — simple, pairs with media
5. **dropcap** — simple typography block
6. **footnote** — simple reference block

**Estimated effort**: 1-2 hours. All are simple leaf blocks with 1-3 fields.

### P1 — Layout, Media, and Simple Content (16 blocks)
Primary focus: layout containers, media blocks, and simple content that is frequently used.

- **Layout**: container, grid, group, fullbleed, overlap (5)
- **Media**: gallery, audio, imagecaption, icon (4)
- **Content**: sidenote, runningtext, textdivider (3)
- **Marketing** (high-use): ctabanner, testimonial, logostrip, stats (4)

**Estimated effort**: 3-4 hours. Mix of simple and moderate complexity.

### P2 — Marketing, Navigation, and Interactive (17 blocks)
Secondary focus: remaining marketing blocks, navigation, and content blocks with complex data structures.

- **Marketing**: featuregrid, featurecomparison, pricingcard, pricingtable, postcard, postgrid, authorbox (7)
- **Navigation**: anchormenu, breadcrumbs, toc, menu, readingprogress (5)
- **Content** (complex): table, timeline, tooltip (3)
- **Interactive/Layout**: modal, stickysidebar (2)

**Estimated effort**: 4-6 hours. Some have complex item arrays.

### P3 — Dynamic Blocks (10 blocks)
Data-source blocks needing backend queries or API integrations.

- latestposts, relatedposts, categorylist, socialembed, map, chart, newsletter, customform, paywall, sharebuttons

**Estimated effort**: 6-8 hours. Require data source configuration.

---

## 5. Completion Level Model

Each block should progress through these levels:

| Level | Name | Requirements |
|-------|------|-------------|
| 0 | Registered | Block type exists in registry, placeholder only |
| 1 | Frontend | Editor.tsx + Preview.tsx + definition.ts work |
| 2 | Publishable | Blade template renders correctly |
| 3 | Validated | PHP BlockDefinition with validation + sanitization |
| 4 | Styled | BaseBlock shared properties (padding, margin, border, radius, shadow, animation) applied |
| 5 | Inline | Inline editing via InlineTextField where appropriate |
| 6 | Parity | Preview and Blade output visually match |
| 7 | Tested | Unit tests + audit pass + documentation |

**Current state of MISSING_BACKEND blocks**: Level 2 (frontend + Blade work, no backend validation).
**Target for P0-P1**: Level 3 minimum (add PHP definitions).
**Target for production**: Level 6 (parity verified).

---

## 6. Shared Prerequisites

These shared systems should be stable before mass block fixes:

### Already Complete
| System | Status | Location |
|--------|--------|----------|
| BlockDefinition interface | DONE | `app/Domain/Blocks/Definitions/BlockDefinition.php` |
| BlockStyle PHP helper | DONE | `app/Support/Blocks/BlockStyle.php` |
| blockStyles.ts (frontend) | DONE | `resources/admin/src/lib/blockStyles.ts` |
| ShadowField | DONE | `resources/admin/src/components/editor/fields/ShadowField.tsx` |
| BoxSpacingField | DONE | `resources/admin/src/components/editor/fields/BoxSpacingField.tsx` |
| CornerRadiusField | DONE | `resources/admin/src/components/editor/fields/CornerRadiusField.tsx` |
| InlineTextField | DONE | `resources/admin/src/components/editor/fields/InlineTextField.tsx` |
| InlineLinkPopover | DONE | `resources/admin/src/components/editor/fields/InlineLinkPopover.tsx` |
| InlineMediaReplace | DONE | `resources/admin/src/components/editor/fields/InlineMediaReplace.tsx` |
| SortableBlock wrapper | DONE | Applies shared properties to all block previews |
| BuildPageService | DONE | Passes shared properties to Blade |
| Audit script | DONE | `scripts/audit-blocks.mjs` |

### Not Yet Built (not blockers for P0-P1)
| System | Priority | Notes |
|--------|----------|-------|
| TypographyField (composite) | P2 | Individual fields work; composite component is optional |
| AssetSelectField | P2 | Currently uses ImageField + AssetPicker |
| LinkField (composite) | P2 | Currently uses TextField + urlHelpers |
| BaseBlock validation trait | P2 | Would reduce boilerplate but not required |
| Block render test pattern | P1 | Should be established with first batch |
| Block fixture pattern | P2 | JSON fixtures for testing |

**Conclusion**: All prerequisites for P0-P1 backend definitions are already in place. No blockers.

---

## 7. Quote vs Pullquote Resolution

### Current State

| Aspect | quote | pullquote |
|--------|-------|-----------|
| Backend PHP | QuoteBlockDefinition (type='quote') | NONE |
| Blade | quote.blade.php | pullquote.blade.php |
| Frontend | NONE | pullquote/ (Editor, Preview, definition) |
| Data keys | content, citation | text, attribution, style |
| Category | content | typography |
| HTML | Sanitized HTML allowed | Plain text only |
| Audit status | ORPHAN_BACKEND | MISSING_BACKEND |

### Recommendation: Option C — Map quote backend to pullquote

**Safest resolution:**

1. **Rename** `QuoteBlockDefinition.php` → `PullquoteBlockDefinition.php`
2. **Change** `type()` return from `'quote'` to `'pullquote'`
3. **Update** validation rules to match pullquote data keys: `text`, `attribution`, `style`
4. **Keep** `quote.blade.php` as legacy fallback (in case old data references it)
5. **Add migration** to update `blocks.type` from `'quote'` to `'pullquote'` for existing data
6. **Result**: pullquote becomes COMPLETE; quote.blade.php preserved for safety

**Why not Option A (rename frontend to quote)**:
- Would require changing the frontend registry, component imports, and all references
- More files to change, higher risk

**Why not Option B (keep both)**:
- Maintaining two nearly-identical blocks is wasteful
- Users will be confused by quote vs pullquote

**Risk**: LOW — only changes backend definition type string and validation rules. Frontend and Blade already work.

**Database migration**: Required but simple — `UPDATE blocks SET type = 'pullquote' WHERE type = 'quote'`

---

## 8. Backend Definition Batch Plan

### Batch 1: P0 Core Content (5 blocks)
**After** quote/pullquote resolution.

| Block | Fields to Validate | Sanitization | Notes |
|-------|-------------------|-------------|-------|
| paragraph | content (required, string) | HTML.Allowed: p,br,strong,em,u,a[href\|target],span | Rich text content |
| list | content (required, string), listType (in: ordered,unordered) | HTML.Allowed: ul,ol,li,strong,em,a[href\|target] | List content |
| caption | text (required, string, max:500) | HTML.Allowed: '' | Simple text |
| dropcap | content (required, string) | HTML.Allowed: p,br,strong,em | First letter emphasis |
| footnote | content (required, string), reference (sometimes, string, max:50) | HTML.Allowed: '' | Reference marker |

**Files to create**:
```
app/Domain/Blocks/Definitions/ParagraphBlockDefinition.php
app/Domain/Blocks/Definitions/ListBlockDefinition.php
app/Domain/Blocks/Definitions/CaptionBlockDefinition.php
app/Domain/Blocks/Definitions/DropcapBlockDefinition.php
app/Domain/Blocks/Definitions/FootnoteBlockDefinition.php
```

**Checkpoint**: Run `npm run blocks:audit` — expect 24 COMPLETE (18 + pullquote + 5 new).

### Batch 2: P1 Layout + Media (8 blocks)

| Block | Fields | Children | Notes |
|-------|--------|----------|-------|
| container | background, maxWidth, padding, centered | yes (20) | Generic wrapper |
| grid | columns, gap, minWidth | yes (20) | CSS grid |
| group | — | yes (20) | Simple grouping |
| fullbleed | background | yes (10) | Full-width |
| gallery | images[] (array of {url, alt, caption}) | no | Image gallery |
| audio | src, title, autoplay, loop | no | Audio player |
| imagecaption | image, caption, alt, position | no | Image + caption |
| icon | name, size, color | no | Icon display |

**Checkpoint**: Run audit — expect 32 COMPLETE.

### Batch 3: P1 Marketing + Content (8 blocks)

| Block | Fields | Notes |
|-------|--------|-------|
| ctabanner | heading, text, buttonText, buttonUrl, variant | CTA section |
| testimonial | quote, author, role, avatar, rating | Customer quote |
| logostrip | logos[] (array of {url, alt, link}), columns | Partner logos |
| stats | items[] (array of {value, label, description}) | Statistics |
| sidenote | content, type | Marginal note |
| runningtext | content | Continuous text |
| textdivider | text, style | Divider with text |
| overlap | — (children only) | Overlapping layout |

**Checkpoint**: Run audit — expect 40 COMPLETE.

### Batch 4: P2 Navigation + Feature (12 blocks)

| Block | Fields | Notes |
|-------|--------|-------|
| anchormenu | items[], style | Anchor navigation |
| breadcrumbs | separator, showHome | Auto-generated |
| toc | maxDepth, style | Table of contents |
| menu | menuId, style, sticky, showLogo | Menu reference |
| readingprogress | color, height, position | Progress bar |
| featuregrid | items[], columns | Feature cards |
| featurecomparison | items[], columns | Comparison table |
| pricingcard | title, price, period, features[], buttonText, buttonUrl, highlighted | Pricing card |
| pricingtable | plans[] | Pricing table |
| table | headers[], rows[], striped, compact | Data table |
| timeline | items[], layout | Timeline |
| tooltip | text, content, position | Tooltip |

**Checkpoint**: Run audit — expect 52 COMPLETE.

### Batch 5: P2-P3 Remaining (10 blocks)

| Block | Fields | Notes |
|-------|--------|-------|
| postcard | postId, layout | Post preview |
| postgrid | count, category, columns | Post grid |
| authorbox | authorId, layout | Author bio |
| modal | trigger, size | Modal dialog |
| stickysidebar | — (children) | Sticky layout |
| latestposts | count, category, layout | Dynamic posts |
| relatedposts | count, layout | Dynamic related |
| categorylist | layout, showCount | Dynamic categories |
| socialembed | url, platform | Social embed |
| map | lat, lng, zoom, style | Map embed |

**Checkpoint**: Run audit — expect 62 COMPLETE.

### Batch 6: P3 Complex (5 blocks)

| Block | Fields | Notes |
|-------|--------|-------|
| chart | type, data, options | Chart rendering |
| newsletter | provider, listId, buttonText | Email signup |
| customform | fields[], action, method | Form builder |
| paywall | content, gate, plan | Access control |
| sharebuttons | platforms[], style | Social sharing |
| beforeafter | beforeImage, afterImage, orientation | Slider comparison |

**Checkpoint**: Run audit — expect 68 COMPLETE (all except any intentionally excluded).

---

## 9. Implementation Roadmap

### Phase 0: Quote/Pullquote Resolution
1. Rename QuoteBlockDefinition → PullquoteBlockDefinition
2. Change type() to 'pullquote'
3. Update validation to match pullquote data keys
4. Create database migration
5. Run audit → pullquote becomes COMPLETE
6. **Do NOT delete** quote.blade.php (legacy safety)

### Phase 1: Batch 1 — Core Content (5 definitions)
1. Create 5 PHP BlockDefinition files
2. Register in BlockDefinitionRegistry (if manual registration needed)
3. Run audit → verify 24 COMPLETE
4. Run `php artisan test`

### Phase 2: Batch 2 — Layout + Media (8 definitions)
1. Create 8 PHP files
2. Pay special attention to container/grid/group `allowsChildren`
3. Run audit → verify 32 COMPLETE

### Phase 3: Batch 3 — Marketing + Content (8 definitions)
1. Create 8 PHP files
2. Items-array validation pattern for testimonial, logostrip, stats
3. Run audit → verify 40 COMPLETE

### Phase 4: Batch 4 — Navigation + Feature (12 definitions)
1. Create 12 PHP files
2. Complex array validation for pricing, table, timeline
3. Run audit → verify 52 COMPLETE

### Phase 5: Batch 5-6 — Remaining (15 definitions)
1. Create remaining PHP files
2. Dynamic blocks may need special service integration
3. Run audit → verify 67-68 COMPLETE

### Phase 6: Level 4+ Improvements
1. Apply BaseBlock shared properties to all block Blade templates
2. Add inline editing where appropriate
3. Verify Preview/Blade parity
4. Add tests

---

## 10. Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Validation too strict breaks existing data | HIGH | All fields use `sometimes` + `nullable`. Never `required` unless absolutely essential. |
| Wrong field names in definition | MEDIUM | Read each block's definition.ts defaultData before writing PHP rules. |
| Quote migration breaks existing content | LOW | Keep quote.blade.php as fallback. Migration is simple type rename. |
| Mass backend generation introduces bugs | MEDIUM | Batch in groups of 5-8, audit after each batch. |
| Container blocks with wrong maxChildren | LOW | Check frontend definition.ts for allowsChildren config. |
| Sanitization strips needed HTML | MEDIUM | Default to `HTML.Allowed => ''` for non-rich blocks. Only add HTML for blocks that use {!! !!} in Blade. |

---

## 11. Commands and Checkpoints

Run after **every batch**:

```bash
# Validate PHP
composer validate

# Build frontend
npm run build:vite

# Run block audit
npm run blocks:audit

# Run tests
php artisan test

# Fix ownership
chown -R cytechno:cytechno /srv/jail/cytechno/home/cytechno/web/ensodo.eu/private/cms-platform
```

**Success criteria per batch**: Audit COMPLETE count increases by the batch size. No test regressions. Build passes.

---

## 12. File Structure Reference

### PHP Definition Template
```php
<?php

namespace App\Domain\Blocks\Definitions;

class {Type}BlockDefinition implements BlockDefinition
{
    public function type(): string { return '{type}'; }
    public function category(): string { return '{category}'; }

    public function validationRules(): array
    {
        return [
            // Fields from definition.ts defaultData
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
```

### Audit Script
```bash
npm run blocks:audit          # Full audit with JSON report
cat storage/app/block-audit.json | jq '.summary'  # Quick status check
```
