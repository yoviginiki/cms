# The Media Library

One place for every image and file a site uses, with automatic optimization built in.

## Uploading

Drag files in or pick them from any image field in the editors. On upload, Stillopress:

- **Deduplicates** — re-uploading an identical file returns the existing asset instead of a copy.
- **Sanitizes SVGs** — scripts and event handlers are stripped at the door; unparseable SVGs are rejected.
- **Generates variants for images**: a square thumbnail, responsive sizes (400 / 800 / 1600 px where the source is large enough), and **WebP** versions of each. Published pages serve the right size per screen via `<picture>`/`srcset`, with intrinsic dimensions so nothing shifts while loading.

## Alt text

Every image has an **alt text** field — fill it once in the library and it flows automatically to published pages that don't set their own. Missing alt text is flagged by the publish-time lint (a nudge, never a block).

## Using images

- Editors reference library assets directly; at publish, references become **content-hashed static files** — perfect cache behavior, no backend needed on your live site.
- Posts have a **featured image** (used in archives, social cards, and Article structured data — set it in the post's settings panel).
- External images pasted into content can be pulled into the library in bulk with the `assets:import-external` command, so old content gets the same optimization as new.

## Housekeeping

- Deleting an asset shows where it's used before you confirm; deleting one that's on published pages flags those pages stale.
- Storage is tenant-isolated per site; nothing is shared across customers.
