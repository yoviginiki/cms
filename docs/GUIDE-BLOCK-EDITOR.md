# The Block Editor

The block editor is where most pages and posts are built. A page is a tree of blocks with four levels: **Section → Row → Column → Module**. Sections stack down the page, rows split a section horizontally, columns divide a row on the 12-column grid, and modules are the actual content — text, headings, images, galleries, buttons, forms, and ~80 more.

## Building a page

- **Add blocks** with the **+ Add** tab in the right sidebar, or the floating **+** button on mobile. Drop a module into a column; drop a section anywhere on the canvas.
- **Row layouts**: pick a split visually (1, ½+½, ⅓+⅔, ⅓×3, ¼×4 …). Switching a layout redistributes existing modules — nothing is deleted; overflow moves to the last column.
- **Resize columns** in the row's settings panel on the 12-column grid, with live fraction readout. Each row can also set a **mobile stacking order**.
- **Section width**: contained, wide, or full-bleed, plus gap and vertical alignment tied to the theme's spacing tokens.

## Navigating and editing

- **Tree tab**: the collapsible page structure (sections → rows → columns → modules). Drag to reorder, rename labels, toggle visibility.
- **Right-click any block** for the context menu: Duplicate, Delete, Copy/Paste, **Copy Style / Paste Style** (with granularity — all, typography, spacing, colors, borders), **Save to Library**, **Apply Preset**, **Extend Style** (apply this block's style to all blocks of its type in the section, page, or site), Move to.
- **Keyboard**: ⌘C/⌘V/⌘D for copy/paste/duplicate, ⌘Z/⇧⌘Z for undo/redo.
- **Multi-select** with shift-click for bulk delete, duplicate, preset application, or spacing edits.
- **Settings search**: the filter field at the top of the block settings panel jumps to any option.
- **Spacing handles**: drag margins and padding directly on the canvas — values snap to the theme's spacing scale with a live readout.
- **Find & Replace (design)**: search a color, font, or size across the page or site and replace everywhere — site-wide color replaces offer "convert to token" so future changes are one edit.

## Responsive controls

Every block offers per-breakpoint **visibility toggles**, plus per-breakpoint overrides for **spacing and font size**. Everything compiles to static CSS at publish — there is no runtime JavaScript on your published site.

## Saving and safety

- **Autosave** keeps a draft as you work (interval + on blur) and never touches your published pages.
- **Save** persists the page; if the page is published and auto-publish is on (the default), saving also republished it.
- **Revisions**: the History panel lists every saved version; restoring takes a safety snapshot first.

## SEO panel

Each page and post has an SEO section: title and description with a live Google-style snippet preview and length hints, social image, canonical override, and robots toggles. See [SEO in Stillopress](SEO-IN-STILLOPRESS.md).
