# Inline Editing Adoption Plan

> **Date**: 2026-05-10
> **Foundation**: `InlineTextField` component + `InlineEditingConfig` contract
> **Pilot**: Hero block (implemented)
> **Scope**: Plain text inline editing. Rich text is a separate initiative.

---

## Overview

This document defines the adoption order for inline editing across all content blocks. Each block is listed with its likely inline-editable fields, the required field type, and known risks or blockers.

**Rules for adoption:**

1. Use `InlineTextField` from `@/components/editor/fields` — no custom contentEditable
2. Declare `InlineEditingConfig` in the block's `definition.ts`
3. Same data keys in `definition.defaultData`, `Editor.tsx`, `Preview.tsx`, and Blade
4. Side panel fields remain as fallback — never remove them
5. Plain text fields can be adopted now; rich text fields must wait for TipTap integration

---

## Adoption Order

### 0. Hero (Pilot) — IMPLEMENTED

| Field | Data Key | Type | Tag |
|-------|----------|------|-----|
| Title | `title` | `text` | `h1` (configurable) |
| Subtitle | `subtitle` | `text` | `p` |
| CTA Text | `ctaText` | `text` | `span` |

**Status**: Implemented. All three fields are inline-editable on the canvas with side panel fallback. CTA URL is editable via `InlineLinkPopover` on the canvas.

---

### 1. Heading — IMPLEMENTED

| Field | Data Key | Type | Tag |
|-------|----------|------|-----|
| Text | `text` | `text` | Derived from `level` (`h1`–`h6`) |

**Status**: Implemented. Single inline-editable field. The `as` tag is dynamically derived from `data.level`. `InlineTextField` supports `h1`–`h6`. Side panel fallback preserved in Editor.tsx.

---

### 2. Paragraph / Text — SKIPPED_RICH_TEXT

| Field | Data Key | Type | Tag |
|-------|----------|------|-----|
| Content | `content` | rich text | `div` |

**Reason**: `paragraph` stores HTML (`<p>Start writing...</p>`). `text` block also stores HTML and uses `WysiwygEditor` + `dangerouslySetInnerHTML`. Both already have inline WYSIWYG editing when selected. Replacing with `InlineTextField` would strip HTML formatting.

**Decision**: BLOCKED until rich text inline editing (TipTap) is available. These blocks already have functional inline editing via WysiwygEditor — they just need migration to the `InlineEditingConfig` contract later.

---

### 3. Rich Text — SKIPPED_RICH_TEXT

| Field | Data Key | Type | Tag |
|-------|----------|------|-----|
| Content | `content` | rich text | `div` |

**Reason**: Same as paragraph — stores HTML (`<p></p>`). Requires TipTap.

**Decision**: BLOCKED until rich text inline editing is available.

---

### 4. Pullquote — IMPLEMENTED

| Field | Data Key | Type | Tag |
|-------|----------|------|-----|
| Quote text | `text` | `multiline` | `p` |
| Attribution | `attribution` | `text` | `span` |

**Status**: Implemented. Both fields are inline-editable on the canvas. Quote text supports multiline (Shift+Enter). Attribution always visible with placeholder. Style selector remains in side panel. Side panel fallback preserved in Editor.tsx.

---

### 5. Button — IMPLEMENTED

| Field | Data Key | Type | Tag |
|-------|----------|------|-----|
| Button text | `text` | `text` | `span` |

**Status**: Implemented. Button text inline-editable, URL editable via `InlineLinkPopover`. Style, size, and target remain in side panel. Side panel fallback preserved in Editor.tsx.

---

### 6. CTA Banner — IMPLEMENTED

| Field | Data Key | Type | Tag |
|-------|----------|------|-----|
| Heading | `heading` | `text` | `h3` |
| Description | `text` | `multiline` | `p` |
| Button text | `buttonText` | `text` | `span` |

**Status**: Implemented. Three inline-editable fields. Description supports multiline. Button URL and background settings remain in side panel. Side panel fallback preserved in Editor.tsx.

---

### 7. Feature Grid — SKIPPED_COMPLEX_SCHEMA

| Field | Data Key | Type | Tag |
|-------|----------|------|-----|
| Item title | `items[n].title` | `text` | `h3` |
| Item description | `items[n].description` | `text` | `p` |

**Reason**: Fields are inside an `items` array. Inline editing for array item fields requires an update pattern (`update('items', modifiedArray)`) that has not been established yet.

**Future requirement**: Establish array item inline editing convention, then adopt.

---

### 8. Testimonial — SKIPPED_COMPLEX_SCHEMA

| Field | Data Key | Type | Tag |
|-------|----------|------|-----|
| Quote | `items[n].quote` | `multiline` | `p` |
| Author | `items[n].author` | `text` | `span` |
| Role | `items[n].role` | `text` | `span` |

**Reason**: Same array item pattern as Feature Grid. Multiple items may be rendered in a carousel or grid layout.

**Future requirement**: Depends on array item editing pattern from Feature Grid.

---

### 9. Accordion / Tabs — SKIPPED_COMPLEX_SCHEMA

| Field | Data Key | Type | Tag |
|-------|----------|------|-----|
| Accordion: Item title | `items[n].title` | `text` | `span` |
| Accordion: Item content | `items[n].content` | rich text | `div` |
| Tabs: Tab label | `tab_labels[n]` | `text` | `span` |

**Reason**: Array items + accordion content stores HTML. Partial adoption (titles/labels only) possible but deferred until array pattern is established.

**Decision**: BLOCKED — wait for array item pattern (titles) and TipTap (content).

---

### 10. Gallery Captions — SKIPPED_COMPLEX_SCHEMA

| Field | Data Key | Type | Tag |
|-------|----------|------|-----|
| Image caption | `images[n].caption` | `text` | `span` |
| Image alt text | `images[n].alt` | `text` | — (a11y, side panel) |

**Reason**: Array item pattern. Alt text should stay in side panel (accessibility field).

**Future requirement**: Depends on array item editing pattern.

---

### Blocks Not Found

| Block | Status |
|-------|--------|
| Callout | SKIPPED_MISSING_BLOCK — directory does not exist |
| CTA (standalone) | SKIPPED_MISSING_BLOCK — directory does not exist (`ctabanner` exists instead) |
| Card | SKIPPED_MISSING_BLOCK — directory does not exist (`postcard` exists but has no text content fields) |
| Quote | SKIPPED_MISSING_BLOCK — orphan backend only, no frontend component |

---

## Adoption Phases

### Phase A: Plain text flat fields — COMPLETE

| Block | Fields | Status |
|-------|--------|--------|
| Hero | `title`, `subtitle`, `ctaText` | IMPLEMENTED |
| Heading | `text` | IMPLEMENTED |
| Pullquote | `text`, `attribution` | IMPLEMENTED |
| Button | `text` | IMPLEMENTED |
| CTA Banner | `heading`, `text`, `buttonText` | IMPLEMENTED |

### Phase B: Array item fields (needs pattern)

| Block | Fields | Status |
|-------|--------|--------|
| Feature Grid | `items[n].title`, `items[n].description` | PENDING |
| Testimonial | `items[n].quote`, `items[n].author`, `items[n].role` | PENDING |
| Gallery | `images[n].caption` | PENDING |
| Accordion | `items[n].title` (title only) | PENDING |
| Tabs | `tab_labels[n]` | PENDING |

### Phase C: Rich text fields (requires TipTap)

| Block | Fields | Status |
|-------|--------|--------|
| Paragraph | `content` | BLOCKED |
| Rich Text | `content` | BLOCKED |
| Text | `content` | BLOCKED |
| Accordion | `items[n].content` | BLOCKED |

---

## Prerequisites for Each Phase

### Phase A
- [x] `InlineTextField` component
- [x] `InlineEditingConfig` contract
- [x] Hero pilot validated
- [x] Add `h4`/`h5`/`h6` to `InlineTextField` `as` prop (for heading block)
- [x] Heading, Pullquote, Button, CTA Banner implemented

### Phase B
- [ ] Establish array item inline editing pattern (helper or convention)
- [ ] Test with Feature Grid as first array-based pilot

### Phase C
- [ ] TipTap integration for rich text inline editing
- [ ] `InlineRichTextField` component (or extend `InlineTextField`)
- [ ] HTML sanitization for inline rich text output
- [ ] Undo/redo integration with editor undo stack

---

## Out of Scope

These blocks are unlikely to benefit from inline text editing:

| Block | Reason |
|-------|--------|
| Image | Content is media, not text — use inline image replacement (future) |
| Video | Content is embed URL — settings panel |
| Map | Content is coordinates — settings panel |
| Spacer / Divider | No text content |
| Code | Requires code editor, not contentEditable |
| HTML Embed | Raw HTML — must stay in side panel with sanitization |
| Social Embed | URL-based — settings panel |
| Contact Form / Custom Form | Form builder, not text content |
| Chart | Data-driven — settings panel |
| Table | Structured grid — requires specialized table editor |
| Postcard | Data-driven (postId) — no user-editable text fields |
