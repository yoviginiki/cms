# The Canvas Editor

The canvas editor is the third editing mode (alongside the block editor and the Magazine editor), chosen per page or post at creation. It is for **freeform, visually composed layouts** — position, rotate, and layer blocks anywhere, like a design tool.

## How a canvas page is structured

A canvas page is **not** one giant canvas. It is a vertical stack of **Sections**, and each section is its own freeform canvas. Sections stack, the page scrolls, and the width comes from your theme (contained or full-bleed per section). Two page types:

- **website** — sections stack and scroll; this is the main mode.
- **single** — one fixed-height canvas with no scrolling; ideal for landing pages and poster-style pages.

## Working on the canvas

- **Add sections** and set each one's height (fixed pixels or auto), bleed, and background.
- **Insert blocks** from the palette, then **drag, resize, rotate, and layer** them freely. The same blocks and the same JSON as the block editor — nothing proprietary.
- **Snapping**: blocks snap to the 12-column grid and to sibling edges. Nudge with the arrow keys; multi-select with shift or a drag rectangle.
- **Preview**: the split-pane preview renders through the real static-publish endpoint, with a mobile-width toggle — what you see is exactly what publishes.

## Mobile behavior

Below the theme's mobile breakpoint, published canvas sections **auto-stack**: children flow vertically in reading order (top-to-bottom, left-to-right), full width, natural heights. The stacking is computed at publish into real markup order — good for SEO and screen readers — while desktop positions apply via CSS at wide viewports.

## Switching modes

- **Block → Canvas** is lossless: the flow order becomes starting positions.
- **Canvas → Block** is lossy (positions flatten to reading order). You get an explicit warning, and a version snapshot is taken automatically before converting — restore it from the Revisions panel if you change your mind.
