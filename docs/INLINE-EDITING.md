# Inline Editing System

> **Status**: General foundation implemented. Phase A complete (Hero, Heading, Pullquote, Button, CTA Banner).
> **Date**: 2026-05-10
> **Scope**: Plain text inline editing. Rich text is a future phase.

---

## 1. Overview

Inline editing is a **general CMS feature** that lets content authors click directly on visible text in the editor canvas and edit it in place. It applies to any block that exposes visible content fields — not just the Hero block.

**Core principles:**

1. Visible content fields should be editable directly on the canvas where appropriate.
2. The right-side settings panel **always remains** as a fallback — inline editing is an enhancement, never a replacement.
3. Inline edits and side-panel edits update the **same data keys** — they are always synchronized.
4. Only **plain text** is supported inline for now. Rich text inline editing is a future phase.
5. The system is designed as a general foundation that any block can adopt.

---

## 2. Architecture

### 2.1 Inline Editing Contract

Location: `resources/admin/src/lib/inlineEditing.ts`

Every block that supports inline editing declares an `InlineEditingConfig` alongside its `BlockDefinition`. This config lists which fields are inline-editable and how they should render.

```typescript
import type { InlineEditingConfig } from '@/lib/inlineEditing';
import { defineInlineField } from '@/lib/inlineEditing';

export const heroInlineEditing: InlineEditingConfig = {
  blockType: 'hero',
  fields: [
    defineInlineField({
      key: 'title',
      label: 'Hero Title',
      placeholder: 'Add hero title',
      as: 'h1',
    }),
    defineInlineField({
      key: 'subtitle',
      label: 'Subtitle',
      placeholder: 'Add subtitle',
      as: 'p',
    }),
  ],
};
```

**Types:**

| Type | Purpose |
|------|---------|
| `InlineEditableFieldType` | `'text'` (single-line) or `'multiline'` (Shift+Enter for newlines) |
| `InlineEditableField` | Describes one inline-editable field: `key`, `label`, `type`, `placeholder`, `panelFallback`, `as` |
| `InlineEditingConfig` | Per-block config: `blockType` + ordered list of `InlineEditableField` |
| `defineInlineField()` | Factory with sensible defaults (`type: 'text'`, `panelFallback: true`, `as: 'span'`) |

### 2.2 InlineTextField Component

Location: `resources/admin/src/components/editor/fields/InlineTextField.tsx`

A reusable, safe `contentEditable` primitive for plain text editing on the canvas. This component is block-agnostic — any block Preview can use it.

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `value` | `string` | — | Current text value |
| `placeholder` | `string` | `''` | Shown when empty |
| `onChange` | `(value: string) => void` | — | Called on commit (blur / Enter) |
| `as` | `'span' \| 'p' \| 'h1' \| 'h2' \| 'h3' \| 'h4' \| 'h5' \| 'h6' \| 'div'` | `'span'` | HTML tag to render |
| `className` | `string` | `''` | Additional CSS classes |
| `style` | `React.CSSProperties` | — | Inline styles |
| `multiline` | `boolean` | `false` | Allow Shift+Enter for newlines |
| `preventDrag` | `boolean` | `true` | Stop drag events while editing |

### 2.3 Placeholder Styling

Empty inline fields show a placeholder via CSS `::before` pseudo-element:

```css
.inline-editable[data-placeholder]:empty::before {
  content: attr(data-placeholder);
  opacity: 0.4;
  pointer-events: none;
}
```

Works in both light and dark admin themes by inheriting the parent text color at reduced opacity.

---

## 3. Field Type Classification

Every block data field belongs to one editing class:

| Class | Where Edited | Examples |
|-------|-------------|----------|
| **Inline Editable** | Directly on canvas | `title`, `subtitle`, `content`, `quote`, `ctaText` |
| **Settings Panel** | Right sidebar | Layout, background, colors, URLs, typography controls |
| **Advanced** | Collapsed sidebar section | CSS classes, custom code, anchors |
| **Accessibility** | Sidebar a11y section | Alt text, ARIA labels, heading level |

### Which fields should be inline editable?

- Text content visible in the published output: **YES** (where practical)
- CTA/button URLs: **YES** via `InlineLinkPopover` (popover near the element)
- Colors, dimensions, enum selectors: **NO** — settings panel
- Alt text, ARIA labels: **NO** — accessibility section
- Custom CSS, HTML IDs: **NO** — advanced section

### Which field types are supported inline?

| Field Type | Inline Support | Notes |
|------------|---------------|-------|
| Plain text (single-line) | **Supported now** | `type: 'text'` |
| Plain text (multi-line) | **Supported now** | `type: 'multiline'` |
| Rich text (bold/italic/links) | **Future** | Requires TipTap integration |
| CTA/button URLs | **Supported now** | Via `InlineLinkPopover` popover |
| Colors | No | ColorField in settings panel |
| Images | No | ImageField in settings panel |
| Enums/selectors | No | SelectField in settings panel |

---

## 4. Safety Rules

### 4.1 No Raw HTML

- `InlineTextField` reads `textContent` only — never `innerHTML`
- `dangerouslySetInnerHTML` is never used
- Pasted content is stripped to `text/plain`
- Only plain text strings flow through `onChange`
- Admin-only component — never rendered in published Blade output

### 4.2 Data Key Preservation

- Inline fields MUST use the exact same data keys as `definition.ts` `defaultData`
- Inline fields MUST use the same keys as the side-panel `Editor.tsx`
- No schema-breaking renames — a field named `title` stays `title`
- If a key must change, it requires a database migration (see ULTIMATE-BLOCK-SYSTEM.md warnings)

### 4.3 Published Output

Published Blade templates continue to use `{{ $data['field'] }}` with Blade escaping. `InlineTextField` is an admin-only React component that never appears in published HTML. The `safeUrl` helper remains for CTA links.

---

## 5. Data Flow and Side Panel Sync

```
InlineTextField.onChange(text)
  -> block.onUpdate({ ...block.data, [field]: text })
    -> editorStore updates block data
      -> Side panel re-renders with new value (same key)
      -> Preview re-renders with new value
      -> Auto-save persists to backend
```

Both inline edits and side panel edits write to the same `block.data[key]`, so they stay synchronized automatically. There is no separate inline state — the editor store is the single source of truth.

---

## 6. Drag, Selection, and Canvas Safety

### How inline editing avoids breaking block behavior

1. **Mouse events**: `InlineTextField` calls `e.stopPropagation()` on `mouseDown` to prevent block drag initiation while clicking into text
2. **Drag events**: `onDragStart` is prevented on the editable element
3. **Keyboard events**: `onKeyDown` stops propagation so editor shortcuts don't fire while typing
4. **Block selection**: Clicking outside the editable text areas still selects the block normally
5. **Drag handles**: Block drag handles in the toolbar remain functional — they are outside the `InlineTextField` elements

### What is NOT affected

- Block selection via wrapper click
- Drag-and-drop reordering via drag handle
- Block toolbar (delete, duplicate, move up/down)
- Keyboard navigation between blocks
- `BuilderCanvas` click-to-deselect behavior

---

## 7. Keyboard Behavior

| Key | Single-line (`multiline: false`) | Multi-line (`multiline: true`) |
|-----|----------------------------------|-------------------------------|
| **Enter** | Commits and blurs | Commits and blurs |
| **Shift+Enter** | Commits and blurs | Inserts newline |
| **Escape** | Cancels, restores original, blurs | Cancels, restores original, blurs |
| **Tab** | Browser default (next element) | Browser default |
| **Cmd/Ctrl+Z** | Browser contentEditable undo | Browser contentEditable undo |

---

## 8. Theme Readability

- Focus ring uses `ring-primary/40` (DaisyUI token — works in light and dark themes)
- Placeholder opacity is `0.4` of the inherited text color — readable in both themes
- No hardcoded colors in `InlineTextField` — all colors come from the block's own styling or theme tokens

---

## 9. How Future Blocks Should Adopt Inline Editing

### Step 1: Declare inline fields

In the block's `definition.ts`, create an `InlineEditingConfig`:

```typescript
import type { InlineEditingConfig } from '@/lib/inlineEditing';
import { defineInlineField } from '@/lib/inlineEditing';

export const headingInlineEditing: InlineEditingConfig = {
  blockType: 'heading',
  fields: [
    defineInlineField({
      key: 'text',
      label: 'Heading Text',
      placeholder: 'Add heading',
      as: 'h2', // or derive from data.level
    }),
  ],
};
```

### Step 2: Use InlineTextField in Preview

Replace static text rendering with `InlineTextField`:

```tsx
import { InlineTextField } from '@/components/editor/fields';

// In Preview component:
<InlineTextField
  as={headingTag}
  value={text}
  placeholder="Add heading"
  onChange={(v) => update('text', v)}
/>
```

### Step 3: Keep side panel fields

The Editor component's `TextField` for the same key remains — it's the fallback:

```tsx
<TextField
  label="Text"
  value={data.text || ''}
  onChange={(v) => update('text', v)}
/>
```

### Step 4: Verify

- Same data key in `definition.defaultData`, `Editor.tsx`, `Preview.tsx`, and Blade template
- Empty state shows readable placeholder
- Typing in canvas updates the side panel
- Typing in side panel updates the canvas
- Block selection/drag still works
- Light and dark theme readable
- No raw HTML saved

See `docs/INLINE-EDITING-ADOPTION-PLAN.md` for the full block adoption schedule.

---

## 10. Blocks with Inline Editing

### Hero (Pilot)

| Field | Data Key | Inline Tag | Side Panel Field |
|-------|----------|-----------|-----------------|
| Title | `data.title` | `<h1>` (configurable) | `TextField` |
| Subtitle | `data.subtitle` | `<p>` | `TextField` |
| CTA Text | `data.ctaText` | `<span>` | `TextField` |

### Heading

| Field | Data Key | Inline Tag | Side Panel Field |
|-------|----------|-----------|-----------------|
| Text | `data.text` | `<h1>`–`<h6>` (from `level`) | `<input>` |

### Pullquote

| Field | Data Key | Inline Tag | Side Panel Field |
|-------|----------|-----------|-----------------|
| Quote text | `data.text` | `<p>` (multiline) | `<textarea>` |
| Attribution | `data.attribution` | `<span>` | `<input>` |

### Button

| Field | Data Key | Inline Tag | Side Panel Field |
|-------|----------|-----------|-----------------|
| Button text | `data.text` | `<span>` | `TextField` |

### CTA Banner

| Field | Data Key | Inline Tag | Side Panel Field |
|-------|----------|-----------|-----------------|
| Heading | `data.heading` | `<h3>` | `<input>` |
| Description | `data.text` | `<p>` (multiline) | `<textarea>` |
| Button text | `data.buttonText` | `<span>` | `<input>` |

All `InlineEditingConfig` exports live in the block's `definition.ts`.

### Blocks NOT yet inline-editable

| Block | Reason |
|-------|--------|
| Paragraph, Rich Text, Text | Stores HTML — requires TipTap (SKIPPED_RICH_TEXT) |
| Feature Grid, Testimonial | Array item fields — pattern not established (SKIPPED_COMPLEX_SCHEMA) |
| Accordion, Tabs | Array items + rich text content (SKIPPED_COMPLEX_SCHEMA) |
| Gallery | Array item captions (SKIPPED_COMPLEX_SCHEMA) |

See `docs/INLINE-EDITING-ADOPTION-PLAN.md` for the full adoption schedule.

---

## 11. Limitations

- **Plain text only** — no bold, italic, links, or formatting within inline fields
- **Rich text inline editing** — future phase, likely via TipTap integration
- **Array item fields** — not yet supported (feature grid, testimonial, accordion, gallery)
- **No inline editing for settings** — layout, background, typography controls stay in side panel
- **No undo/redo** — relies on browser's built-in contentEditable undo (Cmd+Z / Ctrl+Z)
- **No character count** — validation limits enforced server-side only
- **No inline image replacement** — future enhancement
---

## 12. Inline Link Editing

URL editing for CTA/button elements is done through an `InlineLinkPopover` component that appears near the element on the editor canvas.

### How it works

1. A small link icon appears next to inline-editable CTA/button text
2. Clicking the icon opens a compact popover with a URL input field
3. The popover validates URLs in real-time against the same scheme policy as the backend:
   - **Allowed**: `https://`, `http://`, `mailto:`, `tel:`, `/relative`, `#anchor`, `./`, `../`
   - **Rejected**: `javascript:`, `data:`, `vbscript:` (including obfuscated variants with whitespace/control chars) — shows error, refuses to save
4. Enter commits the URL; Escape cancels
5. "Remove" clears the URL; "Open" tests external URLs in a new tab
6. The right-side panel URL field remains available and stays synced (same data key)

### Blocks with inline link editing

| Block | URL Key | Status |
|-------|---------|--------|
| Hero CTA | `data.ctaUrl` | Implemented |
| Button | `data.url` | Implemented |

### Components

- **`InlineLinkPopover`** (`@/components/editor/fields/InlineLinkPopover.tsx`) — reusable popover component
- **`urlHelpers.ts`** (`@/components/editor/fields/urlHelpers.ts`) — `isSafeUrl()`, `getUrlError()`, `isExternalUrl()`, `normalizeUrl()`

### What inline link editing is NOT

- It is NOT rich text link editing (inserting `<a>` tags inside paragraphs). That requires TipTap and is a future phase.
- It is NOT inline image replacement. That is also future.
- The popover edits the URL of a block-level link/button element, not inline text links.

---

### Testing

The admin React app does not currently have a frontend test runner (no vitest/jest setup). URL safety is validated:
- **Frontend**: `urlHelpers.ts` strips control characters and whitespace before scheme detection, matching the backend pattern
- **Backend**: `HeroBlockDefinition.php` uses `not_regex:/^(javascript|data|vbscript):/i` on `ctaUrl`
- **Blade**: `$safeUrl()` blocks dangerous schemes in published output (Hero and Button)
- **Gap**: Frontend URL helper tests should be added when a test runner is set up

---

## 13. Limitations

- **Plain text only** — no bold, italic, or formatting within inline fields
- **Rich text inline editing** — future phase, likely via TipTap integration
- **Rich text link editing** — inserting links inside paragraphs/rich text is future (TipTap)
- **Array item fields** — not yet supported (feature grid, testimonial, accordion, gallery)
- **No inline editing for settings** — layout, background, typography controls stay in side panel
- **No undo/redo** — relies on browser's built-in contentEditable undo (Cmd+Z / Ctrl+Z)
- **No character count** — validation limits enforced server-side only
- **No inline image replacement** — future enhancement

---

## 14. Future Roadmap

1. **Rich text inline editing** — TipTap integration for `paragraph`, `rich-text`, and similar blocks
2. **Rich text link editing** — inline `<a>` insertion inside rich text (requires TipTap)
3. **Inline image replacement** — click to swap images on canvas
4. **Undo/redo integration** — editor-level undo stack for inline edits
5. **Character count overlay** — visual indicator approaching server-side limits
6. **Extend inline link popover** — target (_blank), rel attributes, page/anchor picker
7. **Adopt across all content blocks** — see `docs/INLINE-EDITING-ADOPTION-PLAN.md`
