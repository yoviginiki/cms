# Page Builder UX Guide

**Last updated:** Sprint 5 (2026-06-14)

## Editor Modes

| Mode | Purpose |
|------|---------|
| Visual | Live rendered preview with drag-drop reordering |
| Wireframe | Structural outline view |
| HTML | Raw HTML editor with JSON block import/export |
| Simple | WYSIWYG text editor (auto-converts to rich-text block) |

## Block Hierarchy

```
Page
└── Section (top-level container)
    └── Row (layout grid)
        └── Column (content container)
            └── Module (any content block)
```

Auto-creation: Adding a module without a parent auto-creates Section → Row → Column.

## Canvas Controls

### Top Toolbar (left to right)
1. **Mode toggle** — Visual / Wireframe / HTML / Simple
2. **Undo/Redo** — with step count indicators
3. **Device preview** — Desktop (100%) / Tablet (768px) / Mobile (390px)

### Block Selection
- Click block → blue ring outline + BlockToolbar appears above
- Hover block → light outline
- Escape → deselect

### Block Toolbar (appears above selected block)
- Drag handle (6-dot grip)
- Block icon + label
- Move up / Move down
- Duplicate (copy)
- Save as Template
- Delete

### Empty Canvas
- "Add your first section" with Section Library and Blank Section buttons
- Floating + button in bottom-right corner

### Insert Points
- Between sections: "+ Section" button
- At end: "Add Section" opens PresetBrowser
- Inside containers: "+ Row", "+ Column", or module picker

## Right Sidebar

### When no block selected
- "Select a section to edit its settings" with icon

### When block selected
- **Header**: Block icon, name, description (if available), close button
- **Content**: Block-specific editor (e.g., hero fields, heading text)
- **Typography**: Font, size, weight, line height, alignment, color
- **Spacing**: Margin/padding with presets (None, S, M, L, XL)
- **Background**: Solid color, gradient, image + overlay
- **Borders & Shadow**: Border width/color/style, radius, box shadow
- **Size & Layout**: Width, height, overflow, display, flex/grid
- **Animation**: Entrance animations, hover effects, duration, delay
- **Responsive**: Hide on device (desktop/tablet/mobile)
- **Advanced**: Custom CSS class, custom CSS, HTML ID, ARIA label

## Save/Publish

### Auto-save
- 3-second debounce after any change
- Draft snapshot every 5th save
- Status: Saving... → Saved (with timestamp) or Save failed

### Manual Save
- Ctrl+S or Save button
- Saves page metadata + blocks

### Publish
- Full publish: rebuilds all pages
- Quick publish: only changed pages
- Rollback available from deployment history

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| Ctrl+Z | Undo |
| Ctrl+Shift+Z | Redo |
| Ctrl+S | Save |
| Ctrl+D | Duplicate block |
| Ctrl+C | Copy block |
| Ctrl+V | Paste block |
| Delete / Backspace | Remove block |
| Escape | Deselect block |
| Ctrl+Shift+W | Wireframe mode |
| Ctrl+Shift+V | Visual mode |
| ? | Show shortcuts help |

## Inline Editing

Available for:
- **Hero**: title, subtitle, CTA text
- **Heading**: text

Uses `contentEditable` with plain text only. Updates same block data as settings panel.

## Section Library (Saved Tab)

- Save any block as template via "Save as Template" in block toolbar
- Browse saved templates in PresetBrowser → Saved tab
- Click to insert full block tree with new IDs
- Delete non-system templates

## Responsive Preview

- Desktop: full width (100%)
- Tablet: 768px max-width
- Mobile: 390px max-width
- Rows auto-stack to single column on mobile
- Blocks can be hidden per device via Responsive panel
- Hidden blocks show at 25% opacity with "Hidden on {device}" badge
