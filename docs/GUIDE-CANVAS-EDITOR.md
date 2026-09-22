# The Canvas Editor

The canvas editor is the third editing mode (alongside the block editor and the Magazine editor), chosen per page or post at creation. It is for **freeform, visually composed layouts** — position, rotate, and layer blocks anywhere, like a design tool.

## How a canvas page is structured

A canvas page is **not** one giant canvas. It is a vertical stack of **Sections**, and each section is its own freeform canvas. Sections stack, the page scrolls, and the width comes from your theme (contained or full-bleed per section). Two page types:

- **website** — sections stack and scroll; this is the main mode.
- **single** — one fixed-height canvas with no scrolling; ideal for landing pages and poster-style pages.

## Working on the canvas

- **Add sections** and set each one's height (fixed pixels or auto), bleed, and background — in the section bar or in the right-hand panel when nothing is selected.
- **Insert blocks** from the palette on the left: **drag a tile onto any section** (it lands centred under the pointer, at a sensible starting size) or click it to add to the active section — on an empty page a click also creates the first section. Then **drag, resize, rotate, and layer** blocks freely. The same blocks and the same JSON as the block editor — nothing proprietary.
- **Edit in place**: click a block to select it, click it again (or double-click) to edit its content — type straight into text and headings, pick an image on image blocks. Escape steps back out to the selection. Every block's full settings form is always in the right-hand panel under *Content*.
- **Movement is free-flow**: there is no grid snapping. With snapping on, edges and centres softly attract to sibling edges and the section centre (a few px); hold **Alt** while dragging to bypass. Nudge with the arrow keys (Shift = 10px); multi-select with shift-click.
- **Layers**: *Back / Down / Up / Front* in the panel, or Ctrl+[ / Ctrl+] one step and with Shift all the way. **Opacity** is a slider in the same group and publishes on the element (also per phone override).
- **Right-hand panel**: one element → Content, Layer & opacity, Position & size (X/Y/W/H/rotation, pin in fluid sections), Phone (hide on phones, reset phone layout), Animation. Several elements → layer and duplicate/delete. Nothing → Page (type, design width, phone width) and the active Section.
- **Preview**: the split-pane preview renders through the real static-publish endpoint, with a mobile-width toggle — what you see is exactly what publishes.

## How the canvas meets the visitor's screen

Page setting **Screen** (right panel, nothing selected; stored in `seo_meta.canvas.fit`):

- **Scale to screen width** (default) — the canvas *is* the screen. Each section breaks out of the theme container and scales to the viewport width, up and down, so a block placed at the left edge of the canvas sits at the left edge of the browser and the page looks exactly like the editor at any window size. Implemented by a `.cv-fit` box per section (holds the scaled layout height) + a tiny script publishing `--cv-vw` / `--cv-s`; without JS it degrades to a centred column.
- **Centred column** — legacy: a fixed design-width column centred in the container; below the design width the blocks auto-stack.

## Mobile behavior

On phones (≤767px) sections **auto-stack** in both modes: children flow vertically in reading order (top-to-bottom, left-to-right), full width, natural heights. The stacking is computed at publish into real markup order — good for SEO and screen readers — while desktop positions apply via CSS at wide viewports. A per-element phone layout (phone view in the editor) replaces the stack for that section.

## Which blocks are in the palette

Deliberately a small, verified set — galleries and the basics: **Text** (heading, text, paragraph, pullquote, list), **Media** (image, image + caption, gallery, linear gallery, logo strip, before/after, video, audio, icon), **Elements** (button, divider, shape, testimonial, stats, map, social embed, HTML embed — admins only). The list lives in `resources/admin/src/lib/canvasBlocks.ts`; two tests keep it honest — every entry must render its Preview + Editor (`canvasBlocks.render.test.tsx`) and publish inside a canvas section (`CanvasPaletteBlocksTest.php`, which also asserts its list mirrors the TS one). Structural blocks, post/collection-bound blocks and page chrome (menus, breadcrumbs…) are left out on purpose.

## Storage & validation

A canvas section is a normal `section` block whose `data.canvas` holds the section settings; its children are ordinary module blocks carrying `style.layout` (`x, y, width, height, rotation, zIndex, opacity, locked, pinX, anim, bp.mobile`). The 4-level hierarchy validator recognises a canvas section by that `data.canvas` key and lets it hold modules directly (Section → Module, no Row/Column); a plain section still requires rows.

## Switching modes

- **Block → Canvas** is lossless: the flow order becomes starting positions.
- **Canvas → Block** is lossy (positions flatten to reading order). You get an explicit warning, and a version snapshot is taken automatically before converting — restore it from the Revisions panel if you change your mind.
