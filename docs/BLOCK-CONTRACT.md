# Block Quality Contract

Comprehensive standard for developing, repairing, and evaluating blocks in the Ensodo CMS.

Every block MUST comply with this contract before being considered production-ready. Existing blocks should be repaired toward compliance using the Repair Workflow (Section Q).

---

## Table of Contents

- [A. Block Identity](#a-block-identity)
- [B. Data Contract](#b-data-contract)
- [C. Frontend Structure](#c-frontend-structure)
- [D. Editor UX Standard](#d-editor-ux-standard)
- [E. Inline / In-place Editing Standard](#e-inline--in-place-editing-standard)
  - [E.1 Side Panel and Content Fallback Standard](#dual-editing-modes)
  - [E.2 HTML / Embed Paste Rules](#html--embed-paste-rules)
- [F. Preview Standard](#f-preview-standard)
- [G. Theme-safe Admin Editor Standard](#g-theme-safe-admin-editor-standard)
- [H. Backend Definition Standard](#h-backend-definition-standard)
- [I. Blade Rendering Standard](#i-blade-rendering-standard)
- [J. Security and Sanitization](#j-security-and-sanitization)
- [K. Accessibility Standard](#k-accessibility-standard)
- [L. Responsive Standard](#l-responsive-standard)
- [M. Testing Standard](#m-testing-standard)
- [N. Block Readiness Levels](#n-block-readiness-levels)
- [O. Block Category Requirements](#o-block-category-requirements)
- [P. Block Deprecation and Removal](#p-block-deprecation-and-removal)
- [Q. Repair Workflow](#q-repair-workflow)

---

## A. Block Identity

Every block must declare the following identity metadata in its `definition.ts`:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | yes | Unique kebab-case identifier (e.g., `hero`, `rich-text`, `pricing-card`) |
| `name` / `label` | string | yes | Human-readable display name shown in BlockPicker |
| `category` | string | yes | One of: `content`, `media`, `layout`, `navigation`, `interactive`, `marketing`, `data`, `advanced` |
| `icon` | string | yes | Lucide icon name (e.g., `Layout`, `Type`, `Image`) |
| `description` | string | recommended | Short sentence describing what this block does, shown in BlockPicker tooltip |
| `purpose` | string | recommended | Internal note on when to use this block vs alternatives |
| `tier` | string | optional | `core` (ships with every site), `extended`, `premium`, or `experimental` |
| `allowsChildren` | boolean | yes | Whether this block can contain nested child blocks |

The `type` value must be identical across all three layers: frontend `definition.ts`, backend `BlockDefinition::type()`, and Blade template filename (`{type}.blade.php`).

---

## B. Data Contract

### Canonical Data Shape

Every block must define a single canonical data shape used consistently across:

1. `definition.ts` `defaultData`
2. `Editor.tsx` (reads/writes fields)
3. `Preview.tsx` (reads fields for display)
4. `{type}.blade.php` (reads `$data` for rendering)
5. `BlockDefinition::validationRules()` (validates fields)
6. `BlockDefinition::sanitizationConfig()` (sanitizes fields)

**Same data keys everywhere.** If the editor writes `ctaText`, the preview reads `ctaText`, the Blade reads `$data['ctaText']`, and validation validates `ctaText`. No silent renaming between layers.

### Field Categories

Every data field falls into one of these categories:

| Category | Description | Examples |
|----------|-------------|---------|
| **Content** | User-visible text/media that appears in the published output | `title`, `subtitle`, `content`, `quote`, `caption`, `image` |
| **Design/Settings** | Visual configuration that affects appearance | `backgroundColor`, `textAlign`, `columns`, `spacing`, `gradient` |
| **Link** | Navigation targets | `ctaUrl`, `href`, `linkTarget` |
| **Accessibility** | A11y metadata | `alt`, `ariaLabel`, `headingLevel` |
| **Advanced** | Power-user options | `cssClass`, `anchor`, `customCss` |

### Required vs Optional Fields

```typescript
// In definition.ts defaultData:
defaultData: {
  // Required: meaningful defaults, never undefined
  title: 'Hero Title',
  headingLevel: 'h1',

  // Optional: null or empty string, never undefined
  subtitle: '',
  backgroundImage: null,
  ctaText: '',
  ctaUrl: '',
  alt: '',
}
```

### Standard Field Length Limits

Use these defaults unless the block has a specific reason to deviate:

| Field Type | Max Length | Example Fields |
|-----------|-----------|----------------|
| Title / label | 255 | `title`, `label`, `buttonText`, `ctaText` |
| Subtitle / short text | 500 | `subtitle`, `citation`, `author`, `role` |
| Description / medium text | 2000 | `description`, `caption`, `summary` |
| Rich text / content | 65535 | `content`, `quote`, `body` |
| URL | 2048 | `ctaUrl`, `href`, `src` |
| Asset path | 2048 | `backgroundImage`, `image`, `video` |
| CSS value | 100 | `backgroundColor`, `textAlign`, `fontSize` |
| CSS class | 255 | `cssClass` |
| Anchor / ID | 100 | `anchor`, `id` |
| Alt text | 255 | `alt`, `ariaLabel` |

### Data Versioning (Future)

When a block's data shape changes, increment `schemaVersion` in the definition. Provide a migration function from the previous version. Never silently drop or rename fields.

```typescript
schemaVersion: 2,
migrateData: (oldData, fromVersion) => {
  if (fromVersion === 1) {
    return { ...oldData, headingLevel: oldData.level || 'h1' };
  }
  return oldData;
}
```

---

## C. Frontend Structure

Every block MUST have four files in `resources/admin/src/components/blocks/{type}/`:

| File | Purpose | Required |
|------|---------|----------|
| `definition.ts` | Type metadata, category, icon, defaultData, allowsChildren | yes |
| `Editor.tsx` | Settings panel rendered in BuilderSidebar when block is selected | yes |
| `Preview.tsx` | Canvas preview rendered in the page editor | yes |
| `index.ts` | Registers definition + Preview + Editor with `blockRegistry` | yes |

### BlockDefinition TypeScript Interface

Every `definition.ts` must export an object conforming to the `BlockDefinition` interface defined in `resources/admin/src/types/blocks.ts`:

```typescript
// Source: resources/admin/src/types/blocks.ts
export interface BlockDefinition {
  type: string;
  category: BlockCategory;
  label: string;
  icon: string;
  description?: string;
  defaultData: Record<string, unknown>;
  allowsChildren: boolean;
  maxChildren?: number;
  hasTypography?: boolean;
  tier?: 'core' | 'advanced' | 'pro';
}

// BlockCategory = 'content' | 'media' | 'layout' | 'navigation'
//               | 'interactive' | 'marketing' | 'data' | 'advanced'
```

Related types in the same file: `BlockData`, `BlockComponentProps`, `BlockEditorProps`, `BlockStyleProps`, `ResponsiveOverrides`, `AnimationProps`, `AdvancedProps`.

### definition.ts Requirements

- Must satisfy the `BlockDefinition` interface (use `satisfies BlockDefinition` or type annotation)
- `defaultData` must contain every field the Editor and Preview reference
- Default values must be meaningful: a title should be `'Hero Title'` not `''`
- No field in Editor/Preview should be absent from defaultData (prevents undefined access)

### Editor.tsx Requirements

- Must import from `@/types/blocks` for type safety
- No raw URL input for images; use `ImageField` or future `AssetSelectField`
- No raw CSS gradient strings; use `BackgroundEditor` or future `GradientField`
- All field updates go through `onUpdate({ ...block.data, [field]: value })`

### Preview.tsx Requirements

- Must handle missing/partial data without crashing
- Must show empty state when no content is set
- Must display entered content immediately (not placeholders after data exists)

### index.ts Pattern

```typescript
import { blockRegistry } from '../registry';
import { heroDefinition } from './definition';
import { HeroPreview } from './Preview';
import { HeroEditor } from './Editor';

blockRegistry.register(heroDefinition, HeroPreview, HeroEditor);
```

---

## D. Editor UX Standard

### Use Shared Field Controls

Blocks MUST use the shared field components instead of inline `<input>` elements wherever possible.

**Source:** `resources/admin/src/components/editor/fields/` — barrel-exported from `index.ts`.

| Existing Control | Import | Use For |
|-----------------|--------|---------|
| `TextField` | `@/components/editor/fields` | Short text inputs (titles, labels, URLs) |
| `TextArea` | `@/components/editor/fields` | Multi-line text (descriptions, content) |
| `SelectField` | `@/components/editor/fields` | Dropdowns (heading level, alignment, size) |
| `NumberField` | `@/components/editor/fields` | Numeric values (columns, spacing, dimensions) |
| `ToggleField` | `@/components/editor/fields` | Boolean switches (show/hide, enable/disable) |
| `ColorField` | `@/components/editor/fields` | Color pickers |
| `ImageField` | `@/components/editor/fields` | Image selection with asset picker |

These 7 components are verified to exist and are exported from `@/components/editor/fields`. Any new block editor MUST use them instead of raw HTML inputs.

| Planned Control | Status | Use For |
|----------------|--------|---------|
| `AssetSelectField` | not implemented | Generic asset selection (images, PDFs, videos) |
| `GradientField` | not implemented | Visual gradient builder |
| `LinkField` | not implemented | URL + target + label combined control |
| `DimensionField` | not implemented | Width/height/padding with unit selection |
| `AlignmentField` | not implemented | Text/content alignment picker |
| `TypographyField` | not implemented | Font family/size/weight/style combined control |
| `SpacingField` | not implemented | Margin/padding visual editor |
| `RepeaterField` | not implemented | Dynamic lists (gallery items, accordion items, tabs) |

Until planned controls are implemented, use the existing controls as close approximations (e.g., `TextField` for URLs until `LinkField` exists). For gradients, use `BackgroundEditor` which already provides a visual gradient builder — do not expose raw CSS gradient strings in a plain `TextField` unless the field is explicitly marked as advanced.

### UX Requirements

1. **Helper text**: Every non-obvious field should have a brief description below it
2. **Clear/reset buttons**: Optional fields should allow clearing to default
3. **Empty states**: Editors must be usable when the block has no content yet
4. **Presets**: Complex blocks (hero, pricing, feature grid) should offer preset configurations
5. **Responsive controls**: Fields that affect layout must indicate which breakpoints they affect
6. **Accessibility fields**: Alt text for images, aria labels for interactive elements, heading levels for headings

### Field Labels

- Use `text-[11px] font-medium text-base-content/50 mb-1` for labels (matches existing pattern)
- Use `input-sm` sizing for inputs (matches existing pattern)
- Group related fields with dividers or collapsible sections

---

## E. Inline / In-place Editing Standard

### Principle

Content-first blocks must allow editing content directly in the canvas preview, not only through the settings panel. The goal: what you see in the canvas IS what you edit.

### Field Classification

Every block data field belongs to one of these editing classes:

| Class | Where Edited | Description |
|-------|-------------|-------------|
| **Inline Editable** | Directly in canvas | Text content the user sees in the published output |
| **Quick Editable** | Popover/tooltip on canvas interaction | Short text or toggles that benefit from in-context editing |
| **Settings Panel** | Right sidebar | Layout, design, spacing, media, colors, links |
| **Advanced** | Collapsed/tabbed section in sidebar | CSS classes, anchors, custom code |
| **Accessibility** | Dedicated a11y section in sidebar | Alt text, aria labels, heading levels |

### Inline Editable Fields by Block Type

These fields SHOULD support inline editing when the capability is implemented:

| Block | Inline Editable Fields |
|-------|----------------------|
| `hero` | `title`, `subtitle` |
| `heading` | `content` |
| `paragraph` | `content` |
| `rich-text` | `content` |
| `text` | `content` |
| `pullquote` / `quote` | `quote`, `citation` |
| `caption` | `content` |
| `dropcap` | `content` |
| `button` | `label` |
| `ctabanner` | `title`, `subtitle`, `buttonText` |
| `testimonial` | `quote`, `author`, `role` |
| `pricingcard` | `title`, `price`, `features` |
| `featuregrid` | item `title`, item `description` |
| `accordion` | item `title`, item `content` |
| `tabs` | tab `title`, tab `content` |
| `sidenote` | `content` |
| `footnote` | `content` |
| `runningtext` | `content` |

### Settings Panel Fields (Never Inline)

These always live in the right sidebar:

- Background image/color/gradient (`backgroundImage`, `backgroundColor`, `bgGradient`)
- Layout settings (`columns`, `gap`, `alignment`, `textAlign`)
- Spacing (`padding`, `margin`)
- Media configuration (`aspectRatio`, `objectFit`, `autoplay`)
- Link targets (`ctaUrl`, `href`, `linkTarget`)
- Typography overrides (`fontSize`, `fontWeight`, `fontFamily`)
- Animation settings
- Responsive overrides

### E.1 Side Panel and Content Fallback Standard {#dual-editing-modes}

> **This is a standalone standard.** Inline editing must never remove the right-side settings/content panel. Both editing modes coexist.

### Dual Editing Modes

Inline editing must NOT remove the right-side settings/content panel. The editor supports two complementary editing modes:

**1. Canvas Editing (inline)**
- Used for normal visible content: title, subtitle, paragraph, quote, button label, card text, CTA text
- Gives the user direct visual feedback inside the page body
- Content appears exactly where it will be published

**2. Side Panel Editing (form-based)**
- Remains available as **fallback** for the same content fields edited inline
- Is **required** for settings fields (layout, spacing, colors, backgrounds, media selection)
- Is **required** for advanced fields (CSS classes, anchors, custom code)
- Is **required** for accessibility fields (alt text, aria labels, heading levels)
- Is **required** for link targets and media metadata
- Is **required** for responsive settings
- May include raw HTML or embed input **only** for blocks that explicitly allow it

The side panel is useful for:
- Editing content in form mode (some users prefer it)
- Copying/pasting longer text
- Pasting allowed sanitized HTML into explicit rich-text fields
- Link URL and target configuration
- Media metadata (alt text, caption, dimensions)
- Layout and spacing controls
- Responsive settings
- Accessibility metadata
- Advanced settings (CSS class, anchor ID)

### E.2 HTML / Embed Paste Rules {#html--embed-paste-rules}

Raw HTML input is a special case that must be handled explicitly:

1. **Raw HTML must be an explicit advanced mode** — it must never be silently enabled for every block
2. **Only blocks that explicitly declare HTML/embed support** may accept raw HTML input (e.g., `html-embed`, `socialembed`, and rich-text blocks with HTML mode)
3. **Raw HTML fields must be clearly marked** as advanced/risky in the editor UI (e.g., warning icon, "Advanced: raw HTML" label)
4. **Backend sanitization is mandatory** — all HTML input must pass through `sanitizationConfig()` with appropriate HTMLPurifier rules
5. **The block contract must declare each field's content type**: plain text, rich text, sanitized HTML, embed, or raw HTML
6. **Editor, Preview, Blade, validation, and sanitization must all agree** on the same data field and its content type

| Content Type | Canvas Editing | Side Panel Editing | Backend Handling |
|-------------|---------------|-------------------|-----------------|
| **Plain text** | inline contentEditable (text only) | TextField / TextArea | strip_tags, max length |
| **Rich text** | inline contentEditable (formatted) | TextArea with formatting hints | HTMLPurifier with limited tags |
| **Sanitized HTML** | not inline editable | TextArea with HTML mode toggle | HTMLPurifier with allowed tags |
| **Embed code** | not inline editable | TextArea in explicit advanced section | HTMLPurifier with iframe whitelist |
| **Raw HTML** | not inline editable | TextArea in explicit advanced section, warning shown | HTMLPurifier with strict config, CSP |

### Rules

1. **Content-first**: After the user enters content, the canvas must show that actual content, not a placeholder
2. **No placeholder traps**: Empty fields show readable editable placeholders like "Add hero title", "Write paragraph", "Add quote", "Choose image"
3. **Consistent data keys**: Whether the user edits inline or in the settings panel, the same data key is updated
4. **Immediate preview**: Edits in any location (inline or panel) must immediately reflect in the canvas
5. **Side panel as fallback**: Every inline-editable field must also be editable from the settings panel as a fallback
6. **Normal text content must not live only in the side panel** unless inline editing is impractical for that field
7. **Never enable raw HTML globally**: Raw HTML/embed is opt-in per block/field, never a default behavior
8. **HTML fields require backend sanitization**: Any field accepting HTML must have matching `sanitizationConfig()` rules in the PHP definition

### Inline Editing Implementation (Active)

The inline editing foundation is implemented and available for all blocks. Hero is the first pilot.

See `docs/INLINE-EDITING.md` for the full system documentation and `docs/INLINE-EDITING-ADOPTION-PLAN.md` for the block adoption schedule.

#### Required components

- **`InlineTextField`** (`@/components/editor/fields/InlineTextField.tsx`) — reusable plain-text contentEditable primitive. Reads `textContent` only, strips pasted HTML, emits plain text.
- **`InlineEditingConfig`** (`@/lib/inlineEditing.ts`) — typed contract for declaring which fields in a block are inline-editable.

#### Implementation rules

1. **Use `InlineTextField`** in the block's `Preview.tsx` for visible content fields. Never create raw `contentEditable` elements.

2. **Declare an `InlineEditingConfig`** in the block's `definition.ts` using `defineInlineField()`. This documents which fields are inline-editable and ensures consistent metadata.

3. **Preserve data keys**: The `key` in `InlineEditableField` must match `definition.defaultData`, `Editor.tsx`, `Preview.tsx`, and Blade template. No renames.

4. **Preserve data format**: If the block stores plain text, inline editing must produce plain text. If it stores HTML (rich-text), inline editing must produce valid HTML.

5. **Keyboard behavior** (handled by `InlineTextField`):
   - `Enter` in single-line fields: commit and blur
   - `Shift+Enter` in multi-line fields: insert newline
   - `Escape`: revert to last saved value and blur

6. **Right-side panel fallback**: Every inline-editable field must also be editable from the settings panel. Inline is an enhancement, never a replacement.

7. **Never enable raw HTML in inline editing**: `InlineTextField` produces plain text only. Raw HTML paste is only allowed through the side panel in explicit advanced fields with backend sanitization.

8. **Rich text inline editing** (future): For fields declared as rich text, inline editing will support basic formatting (bold, italic, links) via TipTap, but must sanitize output. The side panel provides the full-featured text input as fallback.

9. **Canvas safety**: `InlineTextField` stops mouse/drag/keyboard propagation to avoid interfering with block selection and drag-and-drop. Block drag handles and toolbar remain functional.

### Field Classification Requirement

Every production-ready block (Level 3+) MUST classify all its data fields into these categories:

| Category | Where Edited | Examples |
|----------|-------------|----------|
| **Inline editable content** | Canvas via `InlineTextField` | `title`, `subtitle`, `content`, `quote`, `ctaText` |
| **Side panel content fallback** | Right sidebar `TextField`/`TextArea` | Same fields as inline, always available as fallback |
| **Settings** | Right sidebar selectors/pickers | Layout, background, colors, URLs, typography |
| **Accessibility** | Sidebar a11y section | Alt text, ARIA labels, heading level |
| **Advanced** | Collapsed sidebar section | CSS classes, HTML IDs, custom code |

A block cannot be Level 3 (Production-ready) unless:
- Visible content fields are inline-editable on the canvas where appropriate, **or**
- The reason they are not inline-editable is documented (e.g., rich text not yet supported, complex structured content)

This classification must be declared via `InlineEditingConfig` in the block's `definition.ts`.

---

## F. Preview Standard

The Preview component (`Preview.tsx`) is rendered in the editor canvas. It must:

1. **Visual fidelity**: Be visually close to the published (Blade) output. Use similar structure, sizing, and proportions.

2. **Same data keys**: Read the exact same field names as Editor and Blade. No mapping, no renaming.

3. **Empty state**: When all content fields are empty, show a clear empty state:
   ```tsx
   if (!title && !subtitle) {
     return (
       <div className="border-2 border-dashed border-base-300 rounded-lg p-8 text-center">
         <p className="text-base-content/40 text-sm">Hero block - click to configure</p>
       </div>
     );
   }
   ```

4. **Partial data resilience**: Must not crash or show errors when only some fields are filled. Use optional chaining and fallbacks.

5. **Media representation**: Images show actual thumbnails, videos show placeholder with play icon, audio shows waveform placeholder.

6. **Typography and spacing**: Reflect heading levels, font sizes, alignment, and spacing reasonably. Does not need pixel-perfect Blade match, but must be representative.

7. **Immediate content reflection**: When the user types in the editor panel (or inline), the preview updates immediately with the entered content. No stale placeholders.

8. **Interactive elements**: Buttons, links, and CTAs are visible but non-functional in preview (no navigation on click).

---

## G. Theme-safe Admin Editor Standard

The admin SPA supports light and dark themes (DaisyUI theme switching). Every editor component and block preview MUST be readable and functional in both themes.

### 1. No Hardcoded Low-Contrast Colors

Forbidden patterns:

```tsx
// BAD: hardcoded colors that break in one theme
<p className="text-gray-400">...</p>           // invisible on dark backgrounds
<div className="bg-white border-gray-200">     // invisible in dark theme
<span style={{ color: '#666' }}>...</span>      // may have insufficient contrast
<div className="bg-gray-100">                   // disappears in dark theme
```

### 2. Use Admin Theme Tokens

Always use DaisyUI/Tailwind theme-aware classes:

| Purpose | Use | Do Not Use |
|---------|-----|------------|
| Editor background | `bg-base-100` | `bg-white`, `bg-gray-50` |
| Surface/card | `bg-base-200`, `bg-base-300` | `bg-gray-100`, `bg-gray-200` |
| Muted surface | `bg-base-200/50` | `bg-gray-50` |
| Primary text | `text-base-content` | `text-black`, `text-gray-900` |
| Muted text | `text-base-content/60` | `text-gray-500`, `text-gray-400` |
| Placeholder text | `text-base-content/40` | `text-gray-300` |
| Borders | `border-base-300` | `border-gray-200`, `border-gray-300` |
| Focus ring | `ring-primary` | `ring-blue-500` |
| Accent/action | `text-primary`, `bg-primary` | `text-blue-600`, `bg-blue-500` |
| Danger | `text-error` | `text-red-500` |
| Warning | `text-warning` | `text-yellow-500` |
| Success | `text-success` | `text-green-500` |

### 3. Preview Contrast Responsibility

Block previews must remain readable regardless of:

- Admin theme (light or dark)
- Block's own background color (light or dark)
- Block using an image or gradient background

Rules:
- Dark block backgrounds default to light text (`text-white` or `text-base-content` with appropriate contrast)
- Light block backgrounds default to dark text
- Image backgrounds must support overlay controls for readability

### 4. Contrast Defaults

When a block has configurable background:
- If background is dark (detected by color or explicitly set), text defaults to light
- If background is light, text defaults to dark
- Image/video backgrounds must render a semi-transparent overlay to ensure text readability

### 5. Admin Theme Test Checklist

Every block must be visually checked in ALL of these states:

- [ ] Light admin theme, empty state
- [ ] Light admin theme, filled state
- [ ] Dark admin theme, empty state
- [ ] Dark admin theme, filled state
- [ ] Light admin theme, block selected/focused
- [ ] Dark admin theme, block selected/focused

### 6. Focus and Selection States

- Selected block outline must be visible in both light and dark admin themes
- Use `ring-primary` or `border-primary` for selection indicators
- Avoid `ring-blue-500` or `border-gray-300` which may be invisible in one theme

---

## H. Backend Definition Standard

Every block MUST have a PHP class in `app/Domain/Blocks/Definitions/` implementing the `BlockDefinition` interface.

### Required Methods (Current Interface)

```php
interface BlockDefinition
{
    public function type(): string;           // Must match frontend type and blade filename
    public function category(): string;       // Must match frontend category
    public function validationRules(): array;  // Laravel validation rules for block data
    public function sanitizationConfig(): array; // HTMLPurifier configuration
    public function allowsChildren(): bool;    // Must match frontend allowsChildren
    public function maxChildren(): ?int;       // null = unlimited
}
```

### Validation Rules

Must cover every field in the block's data contract:

```php
public function validationRules(): array
{
    return [
        'title'            => ['required', 'string', 'max:255'],
        'subtitle'         => ['sometimes', 'nullable', 'string', 'max:500'],
        'backgroundImage'  => ['sometimes', 'nullable', 'string', 'max:2048'],
        'ctaText'          => ['sometimes', 'nullable', 'string', 'max:100'],
        'ctaUrl'           => ['sometimes', 'nullable', 'url', 'max:2048'],
        'alt'              => ['sometimes', 'nullable', 'string', 'max:255'],
    ];
}
```

### Recommended Future Methods

These methods are not yet in the interface but should be added when the interface evolves:

| Method | Return | Purpose |
|--------|--------|---------|
| `defaultData(): array` | Default data shape | Single source of truth for defaults (replaces frontend-only defaultData) |
| `schemaVersion(): int` | Version number | Tracks data shape changes for migration |
| `allowedParents(): ?array` | Array of type strings or null | Restricts which blocks can contain this one |
| `allowedChildren(): ?array` | Array of type strings or null | Restricts which blocks can be nested inside |
| `renderHints(): array` | Key-value hints | Metadata for the rendering pipeline (e.g., `['fullWidth' => true]`) |

---

## I. Blade Rendering Standard

Every block must have a Blade template at `resources/views/blocks/{type}.blade.php`.

### Variables Available

| Variable | Type | Always Present |
|----------|------|----------------|
| `$data` | array | yes |
| `$children` | string | yes (empty string if no children) |
| `$childrenArray` | array | yes (empty array if no children) |
| `$site` | Site model | yes |

### Requirements

1. **Same data keys**: `$data['title']`, `$data['ctaText']`, `$data['backgroundImage']` must match the keys used in `definition.ts`, `Editor.tsx`, and `Preview.tsx`.

2. **Safe escaping**: All user content must be escaped:
   ```blade
   {{-- Plain text: auto-escaped --}}
   <h1>{{ $data['title'] ?? '' }}</h1>

   {{-- Rich HTML: use {!! !!} only after sanitization --}}
   <div class="rich-content">{!! $data['content'] ?? '' !!}</div>

   {{-- URLs in attributes: always escape --}}
   <a href="{{ $data['ctaUrl'] ?? '#' }}">{{ $data['ctaText'] ?? '' }}</a>

   {{-- Background images: escape in style attribute --}}
   <div style="background-image: url('{{ $data['backgroundImage'] ?? '' }}')">
   ```

3. **Null-safe access**: Always use `$data['field'] ?? ''` or `$data['field'] ?? null`. Never assume a field exists.

4. **Semantic HTML**: Use appropriate elements (`<section>`, `<article>`, `<figure>`, `<blockquote>`, `<nav>`, etc.)

5. **Empty/fallback rendering**: If all content fields are empty, render either nothing or a minimal valid HTML structure. Never render broken HTML.

6. **No hidden dependencies**: If the Blade template uses a field, it must be in the data contract. No reading fields that only exist in some blocks or were added informally.

7. **Accessible markup**: Include `alt` attributes on images, `aria-label` on interactive elements, proper heading hierarchy.

8. **External assets with `@once` / `@push`**: Blocks that require external CSS or JS (e.g., `chart`, `map`, `socialembed`, `code` with syntax highlighting) must use `@once` or `@push`/`@pushOnce` to avoid duplicating resources when multiple instances of the same block appear on one page:
   ```blade
   {{-- Load Chart.js only once, even if multiple chart blocks exist --}}
   @pushOnce('scripts')
     <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
   @endPushOnce

   {{-- Block-specific inline script (runs per instance) --}}
   @push('scripts')
     <script>
       document.addEventListener('DOMContentLoaded', () => {
         new Chart(document.getElementById('chart-{{ $data['id'] ?? uniqid() }}'), { /* ... */ });
       });
     </script>
   @endpush
   ```
   The page layout template must include `@stack('styles')` in `<head>` and `@stack('scripts')` before `</body>`.

---

## J. Security and Sanitization

### Data Type Handling

| Data Type | Validation | Sanitization | Storage | Rendering |
|-----------|-----------|--------------|---------|-----------|
| **Plain text** | `string`, `max:N` | Strip HTML tags | Raw string | `{{ }}` (auto-escaped) |
| **Rich text (HTML)** | `string` | HTMLPurifier with allowed tags | Sanitized HTML | `{!! !!}` after sanitization |
| **URL** | `url`, `max:2048` | Validate protocol (http/https) | Raw string | `{{ }}` in href/src attributes |
| **Asset URL** | `string`, `max:2048` | Validate against known asset paths | Asset reference | Resolved by AssetPublisher |
| **CSS color** | `regex:/^#[0-9a-fA-F]{3,8}$/` or named | Whitelist format | Raw string | Inline style or CSS variable |
| **CSS dimension** | `regex:/^\d+(\.\d+)?(px\|rem\|em\|%\|vh\|vw)$/` | Whitelist units | Raw string | Inline style |
| **CSS gradient** | `string` | Parse and validate stops/angles | Structured JSON | Reconstructed in Blade |
| **Embed/custom HTML** | `string` | HTMLPurifier with iframe whitelist | Sanitized HTML | `{!! !!}` with strict CSP |
| **Integer** | `integer`, `min:N`, `max:N` | Cast to int | Integer | Direct output |
| **Boolean** | `boolean` | Cast to bool | Boolean | Conditional rendering |
| **Enum/select** | `in:value1,value2,...` | Validate against allowed values | String | Direct output |

### Sanitization Config Examples

```php
// Plain text only (no HTML allowed)
public function sanitizationConfig(): array
{
    return ['HTML.Allowed' => ''];
}

// Rich text with limited formatting
public function sanitizationConfig(): array
{
    return [
        'HTML.Allowed' => 'p,br,strong,em,a[href|target],ul,ol,li,h2,h3,h4,blockquote,code,pre',
        'URI.AllowedSchemes' => ['http', 'https', 'mailto'],
    ];
}

// Embed content (strict)
public function sanitizationConfig(): array
{
    return [
        'HTML.Allowed' => 'iframe[src|width|height|frameborder|allowfullscreen]',
        'URI.AllowedSchemes' => ['https'],
    ];
}
```

---

## K. Accessibility Standard

### Required Accessibility Features

1. **Alt text for images**: Every block with an image field must have a corresponding `alt` field in the data contract and an alt text input in the editor.

2. **Heading levels**: Heading blocks must allow the user to choose the heading level (h1-h6). The chosen level must be used in both Preview and Blade.

3. **ARIA labels**: Interactive elements (buttons, links, accordions, tabs, modals) must support `aria-label` in the data contract when the visible text may be insufficient.

4. **Keyboard navigation**: Interactive block previews (accordion expand/collapse, tab switching) must be operable via keyboard.

5. **Focus states**: All interactive elements in Preview and Blade must have visible focus indicators.

6. **Semantic HTML**: Blade templates must use semantic elements:
   - `<section>` for page sections (hero, CTA)
   - `<article>` for content blocks (blog post cards)
   - `<figure>` + `<figcaption>` for images with captions
   - `<blockquote>` + `<cite>` for quotes
   - `<nav>` for navigation blocks
   - `<details>` / `<summary>` for accordions (or proper ARIA roles)

7. **Meaningful labels in editor**: Editor field labels must clearly describe what the field controls. "Text" is insufficient; use "Hero Title", "CTA Button Label", "Quote Attribution".

8. **Accessibility fields in editor UI**: Group alt text, aria labels, and heading levels in a clearly labeled "Accessibility" section in the editor sidebar.

---

## L. Responsive Standard

### Layout Expectations

| Viewport | Behavior |
|----------|----------|
| **Desktop** (1024px+) | Full layout: multi-column grids, side-by-side content, full-width heroes |
| **Tablet** (768-1023px) | Reduced columns (3->2, 4->2), adjusted spacing |
| **Mobile** (<768px) | Single column, stacked content, reduced padding/margins |

### Requirements

1. **Column collapse**: Multi-column blocks (columns, grid, feature grid, pricing table) must collapse gracefully at smaller viewports.

2. **Spacing adaptation**: Large desktop padding/margins should reduce on mobile. Blade templates should use responsive CSS or utility classes.

3. **Media aspect ratio**: Images and videos must maintain aspect ratio. Use `aspect-ratio` or padding-based techniques.

4. **Typography scaling**: Heading sizes should scale down on mobile. Use `clamp()` or responsive font-size utilities.

5. **Touch targets**: Interactive elements in Blade output must be at least 44x44px on mobile.

6. **Responsive preview**: The editor preview should approximate responsive behavior. Blocks should not overflow the canvas at narrow widths.

7. **Hide on breakpoint**: Support `__responsive.mobile.hidden`, `__responsive.tablet.hidden` flags for hiding blocks at specific breakpoints (handled by the rendering pipeline).

8. **Prefer container queries where applicable**: Blocks are rendered inside varying layout contexts (full-width sections, columns, sidebars). Media queries respond to viewport width, but a block inside a 3-column grid is effectively narrow even on a wide screen. Use CSS container queries (`@container`) for layout-dependent styles in Blade output:
   ```css
   .block-wrapper { container-type: inline-size; }

   @container (max-width: 400px) {
     .feature-grid { grid-template-columns: 1fr; }
   }
   ```
   Tailwind CSS v3.2+ supports container queries via the `@container` variant (`@container/sm:grid-cols-1`). Use media queries as fallback for browsers without container query support.

---

## M. Testing Standard

### Automated Checks (Current)

These commands must pass for a block to be considered valid:

```bash
# Block layer audit: checks frontend files, blade, PHP definition exist
npm run blocks:audit

# PHP dependency and autoload validation
composer validate

# Vite build: ensures no import errors or missing modules
npm run build:vite
```

### Backend Tests

For every block with a PHP definition, there should be:

1. **Validation test**: Submit block data through the API and verify validation rules are enforced
2. **Sanitization test**: Submit block data with malicious HTML and verify it is sanitized
3. **Blade render test**: Render the Blade template with sample data and verify HTML output

### Frontend Checks (Future)

**Recommended framework:** Vitest + React Testing Library. Vitest integrates natively with the existing Vite build (`resources/admin/vite.config.ts`) and requires minimal configuration. No frontend testing framework is configured yet.

**Setup (when adopted):**
```bash
npm install -D vitest @testing-library/react @testing-library/jest-dom jsdom
```

Add to `resources/admin/vite.config.ts`:
```typescript
/// <reference types="vitest" />
export default defineConfig({
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/test/setup.ts',
  },
});
```

**Minimum tests per block:**

1. **Preview render test — empty data**: Mount Preview with `defaultData`, verify no crash and empty state is shown
2. **Preview render test — complete data**: Mount Preview with filled data, verify content text appears in the DOM
3. **Editor render test**: Mount Editor, simulate field input, verify `onUpdate` is called with correct data keys

**Example test** (`resources/admin/src/components/blocks/hero/__tests__/Preview.test.tsx`):
```tsx
import { render, screen } from '@testing-library/react';
import { HeroPreview } from '../Preview';

const defaultBlock = {
  id: 'test-1',
  type: 'hero',
  data: { title: '', subtitle: '', backgroundImage: null, ctaText: '', ctaUrl: '' },
  style: {},
  order: 0,
};

test('renders empty state when no content', () => {
  render(<HeroPreview block={defaultBlock} isSelected={false} />);
  expect(screen.getByText(/click to configure/i)).toBeInTheDocument();
});

test('renders title when provided', () => {
  const block = { ...defaultBlock, data: { ...defaultBlock.data, title: 'Welcome' } };
  render(<HeroPreview block={block} isSelected={false} />);
  expect(screen.getByText('Welcome')).toBeInTheDocument();
});
```

### Schema Consistency Test (Future)

Automated check that:
- Every key in `definition.ts` `defaultData` has a validation rule in the PHP definition
- Every key validated in PHP exists in `defaultData`
- Every key used in `blade.php` exists in the data contract

### Visual Checks (Manual)

Every block must be visually inspected in:

- [ ] Light admin theme with empty data
- [ ] Light admin theme with filled data
- [ ] Dark admin theme with empty data
- [ ] Dark admin theme with filled data

---

## N. Block Readiness Levels

### Level 0: Placeholder

- Frontend folder exists with minimal/stub files
- May not render anything useful
- Not usable in production

### Level 1: Functional

- All four frontend files present (definition.ts, Editor.tsx, Preview.tsx, index.ts)
- Registered in frontend index
- Blade template exists
- Block renders something meaningful in preview and published output
- May have incomplete fields, no validation, no a11y

### Level 2: Validated

- All Level 1 requirements
- PHP BlockDefinition exists with validation rules and sanitization
- Data keys consistent across all three layers
- Passes `npm run blocks:audit` as COMPLETE
- Empty states handled (no crashes on missing data)

### Level 3: Production-ready

All Level 2 requirements, plus:

- [ ] All data fields classified as inline-editable / settings / accessibility / advanced in `InlineEditingConfig`
- [ ] Visible content fields are inline-editable on the canvas via `InlineTextField` (or reason documented)
- [ ] Side panel fallback remains available for all content fields, settings, and advanced options
- [ ] Inline edits and side panel edits update the same `block.data` keys
- [ ] Preview reflects saved data immediately, not stale placeholders
- [ ] Editor readable in both light and dark admin themes
- [ ] Empty states are readable and informative (not broken layouts)
- [ ] Audit passes (`npm run blocks:audit` reports COMPLETE)
- [ ] Backend definition exists with comprehensive validation rules
- [ ] Blade template uses the same data keys as frontend
- [ ] Semantic HTML in Blade output
- [ ] Alt text / accessibility fields present where applicable
- [ ] No raw URL inputs for assets (uses ImageField or AssetSelectField)
- [ ] Raw HTML/embed input, if supported, is explicitly marked as advanced, uses a dedicated field, and has backend sanitization configured

### Level 4: Premium CMS Block

All Level 3 requirements, plus:

- [ ] Presets available (e.g., "Centered hero", "Left-aligned hero with image")
- [ ] Responsive preview approximation
- [ ] Responsive Blade output with mobile/tablet/desktop breakpoints
- [ ] Full keyboard accessibility in published output
- [ ] ARIA roles and labels for interactive elements
- [ ] Animation support via `__animation` data
- [ ] Performance optimized (lazy loading images, deferred scripts)
- [ ] Documentation in block definition (description, purpose, usage notes)

---

## O. Block Category Requirements

### Layout Blocks (`columns`, `section`, `container`, `group`, `grid`, `tabs`, `accordion`, `fullbleed`, `overlap`, `stickysidebar`, `modal`)

- Must set `allowsChildren: true`
- Must render `$children` or `$childrenArray` in Blade
- Must handle zero children gracefully (empty state)
- Grid/columns blocks must specify responsive column collapse behavior
- Must validate `maxChildren` if applicable

### Text / Editorial Blocks (`paragraph`, `heading`, `rich-text`, `text`, `pullquote`, `quote`, `dropcap`, `caption`, `footnote`, `sidenote`, `runningtext`, `textdivider`, `code`, `list`)

- Content must be inline-editable (or marked for future inline editing)
- Heading blocks must support heading level selection (h1-h6)
- Rich-text blocks must use sanitized HTML rendering in Blade
- Code blocks must support syntax highlighting language selection
- Must use semantic HTML (`<p>`, `<h1>`-`<h6>`, `<blockquote>`, `<code>`, `<pre>`, `<ul>`, `<ol>`)

### Media Blocks (`image`, `imagecaption`, `video`, `audio`, `gallery`, `flipbook`, `beforeafter`, `map`, `socialembed`)

- Must use AssetPicker/ImageField, not raw URL inputs
- Must require alt text for images
- Must handle missing/broken media gracefully
- Video/audio blocks must not autoplay by default
- Gallery blocks must support accessible navigation
- Must preserve aspect ratio

### Marketing Blocks (`hero`, `ctabanner`, `newsletter`, `pricingcard`, `pricingtable`, `featuregrid`, `featurecomparison`, `logostrip`, `testimonial`, `stats`, `timeline`, `chart`, `paywall`, `sharebuttons`)

- Titles and key text should be inline-editable
- Must have strong empty states (these are high-visibility blocks)
- Must be responsive (pricing tables collapse, feature grids reflow)
- CTA buttons must use link fields, not raw URL inputs
- Testimonials must support attribution

### Interactive Blocks (`accordion`, `tabs`, `modal`, `tooltip`, `customform`, `contact-form`)

- Must use proper ARIA roles and states
- Must be keyboard navigable
- Must handle open/closed states in preview
- Forms must validate inputs and show error states

### Data / Dynamic Blocks (`latestposts`, `postgrid`, `postcard`, `relatedposts`, `categorylist`, `authorbox`)

- Must handle "no data" state (no posts, no categories)
- Must show placeholder content in preview (since real data is not available in editor)
- Must indicate data source in editor (which category, how many posts, sort order)

### Navigation Blocks (`menu`, `anchormenu`, `breadcrumbs`, `toc`, `readingprogress`)

- Must use `<nav>` element with `aria-label`
- Must support keyboard navigation in published output
- Must handle empty state (no menu items, no anchors found)

### Advanced / Design Blocks (`html-embed`, `icon`, `divider`, `spacer`, `button`)

- HTML embed must sanitize content aggressively via `sanitizationConfig()`
- HTML embed must present raw HTML input in an explicit advanced section with a warning label
- HTML embed is one of the few blocks where raw HTML paste is allowed in the side panel
- Icon blocks must support accessible labels
- Divider/spacer are visual-only but must render valid HTML
- Button must use LinkField for URL, not raw input

---

## P. Block Deprecation and Removal

### When to Deprecate

A block should be deprecated when:
- It duplicates another block's functionality (e.g., `quote` vs `pullquote`)
- It was experimental and will not be promoted
- Its functionality has been absorbed into another block

### Deprecation Process

1. **Mark as deprecated** in `definition.ts`: add `deprecated: true` and `deprecatedMessage: 'Use {replacement} instead'`
2. **Hide from BlockPicker** — deprecated blocks should not appear when adding new blocks
3. **Keep rendering** — existing pages using the block must continue to render correctly in Blade
4. **Provide migration path** — document which block replaces it and how to convert data
5. **Do not delete files** until all existing content has been migrated

### Removal Process

1. Verify no pages reference the block type (query `blocks` table)
2. Remove frontend files (`definition.ts`, `Editor.tsx`, `Preview.tsx`, `index.ts`)
3. Remove from frontend index imports
4. Remove Blade template
5. Remove PHP BlockDefinition
6. Remove from BlockRegistry
7. Run full audit + build to confirm clean removal

### Known Issue: `quote` vs `pullquote`

The `quote` type has a PHP definition (`QuoteBlockDefinition.php` returning type `quote`) and a Blade template (`quote.blade.php`), but no frontend component. The frontend uses `pullquote` instead.

**Resolution options** (choose one during repair):
- **Option A: Rename `pullquote` to `quote`** — rename frontend folder, update all imports, keep existing PHP + Blade. Requires migrating existing `pullquote` block data in the database to type `quote`.
- **Option B: Add `quote` frontend** — create a new `quote` frontend component matching the existing PHP/Blade, keep `pullquote` as a separate block with different styling.
- **Option C: Deprecate `quote` backend** — remove `QuoteBlockDefinition.php`, rename `quote.blade.php` to `pullquote.blade.php`, add `PullquoteBlockDefinition.php`.

This must be resolved before either block can reach Level 2 (Validated).

---

## Q. Repair Workflow

When repairing an existing block to comply with this contract, follow this order. Do not skip steps. Each step builds on the previous.

### Step 1: Audit

```bash
npm run blocks:audit
```

Identify the block's current status (COMPLETE, MISSING_BACKEND, etc.).

### Step 2: Classify Block

Determine the block's category (Section O) and note category-specific requirements.

### Step 3: Define Data Contract

List every field the block uses across Editor, Preview, and Blade. Identify inconsistencies in field names. Define the canonical field list.

### Step 4: Classify Fields

For each field, assign an editing class (Section E): inline editable, settings panel, advanced, or accessibility.

### Step 5: Align Data Keys

Ensure the same field names are used in:
- `definition.ts` `defaultData`
- `Editor.tsx` field access
- `Preview.tsx` field access
- `{type}.blade.php` `$data` access
- PHP `validationRules()` keys

Fix any mismatches. This is the most common source of bugs.

### Step 6: Preserve Side Panel Fallback

Ensure the right-side settings panel remains available:
- Content fields editable inline MUST also be editable in the side panel as fallback
- Settings, accessibility, and advanced fields remain in the side panel
- If the block supports HTML/embed, ensure it is in an explicit advanced section with backend sanitization
- Do not remove side panel fields when adding inline editing

### Step 7: Add Backend Definition

If missing, create `{Type}BlockDefinition.php` with validation rules for every field in the data contract.

### Step 8: Add Validation and Sanitization

Ensure every field has appropriate validation rules (Section J) and sanitization config. Pay special attention to fields accepting HTML — they must have explicit `sanitizationConfig()` rules.

### Step 9: Improve Editor UX

- Replace raw `<input>` elements with shared field components
- Add helper text, clear buttons, empty states
- Group fields logically (content, design, accessibility, advanced)
- Replace raw URL inputs with ImageField/LinkField

### Step 10: Add Inline Editing Support

Mark fields for inline editing (Section E). Implement inline editing for content fields when the InlineTextField component is available. Keep side panel fallback for all inline-editable fields.

### Step 11: Verify Light/Dark Theme

Check the block in all six states from Section G, Step 5. Fix hardcoded colors.

### Step 12: Add Tests

- Backend validation test
- Blade render test with sample data
- Schema consistency check

### Step 13: Re-run Audit and Build

```bash
npm run blocks:audit    # Must show COMPLETE
composer validate       # Must pass
npm run build:vite      # Must build without errors
```

---

## Appendix: Template Files

Starter templates for new blocks are in `docs/templates/`:

| Template | Purpose |
|----------|---------|
| `block-definition.ts.template` | Frontend block definition with documented fields |
| `block-editor.tsx.template` | Editor component using shared field controls |
| `block-preview.tsx.template` | Preview component with empty state and theme safety |
| `block-index.ts.template` | Registry file |
| `block-blade.blade.php.template` | Blade template with escaping and a11y |
| `block-definition.php.template` | PHP BlockDefinition with validation and sanitization |

---

*This contract is a living document. Update it as the block system evolves, new shared components are created, and inline editing capabilities are implemented.*
