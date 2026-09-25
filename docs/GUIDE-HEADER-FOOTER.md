# Header & Footer

Where a published page's footer (and header) comes from, where to edit it, and why "I changed the footer but nothing happened" usually means you edited a source that page does not use.

## First: does the page use a grid?

Everything depends on this. There are two rendering paths, and they read the footer from different places:

| Path | Which pages | Footer comes from |
|------|-------------|-------------------|
| **Grid** | Every page of every site created from the dashboard — new sites get the **Full Width** grid as the site-wide default | The grid's **footer area** (Grids → the grid → Footer) |
| **Standard** | Pages on sites whose default grid assignment was removed, pages with a non-grid layout | Rich footer → **Global Footer template** → footer menu |

To see it: the **Grid** column in **Pages** and **Posts** shows what each item is published with and why; **Grids** lists what uses each grid and sets the **Site default grid**; in the page editor, **Page** tab → **Grid** picks a grid for that one page.

**Posts** depend on when the site was created:

- **Sites created from 2026-09-25** render every post inside the grid, like pages — one footer for the whole site.
- **Older sites** (artday, vioiv, heikotera, ensodo, monikcreations…) keep the legacy rule: a post without a post template (edited in Blocks/Canvas/Magazine, or template "none") skips the grid and gets the standard layout — header/footer from Templates or Menus. The Grid column shows these as "No grid". A site can be switched with the setting `post_grid: "unified"`.

A category's **Grid** (Categories screen) applies to its posts that go through a grid.

## Quick answer: a footer on a new site

A new site has **no footer** out of the box: the Full Width grid's footer area is of type *Fixed* with nothing in it. Footer menus and Global Footer templates are ignored on grid pages. To get a footer:

1. **Menus** → create a menu, location **footer**, add the links.
2. **Grids** (Structure) → **Full Width** → select the **Footer** area → set its type to **Menu** and the menu location to **footer** → save (the grid editor republishes the site).
3. Optional: **Site Settings → Branding** → Footer Text and Copyright. These appear inside the footer menu output. (Before 2026-09-25 these fields silently did not save — see the logic audit.)

The result is: site name, your footer links, footer text, copyright.

The same applies to the header: the Full Width grid's **Header** area is also empty *Fixed*; only the **Navigation** area (type Menu → header menu) shows anything.

## Which footer wins on standard (non-grid) pages

The publisher picks the first source that produces output (`BuildPageService`, standard layout):

1. **Rich footer** — if the site setting `rich_footer` is `true`, the shared multi-column footer (`resources/views/publishing/_rich-footer.blade.php`) is used and everything below is ignored.
2. **Global Footer template** — the site's *default* `footer` template from **Templates** (`ThemeTemplate::resolveGlobal($siteId, 'footer')`). Its top-level blocks render in order.
3. **Footer menu** — the menu assigned to the **footer** location, rendered by `MenuRenderer` with the logo, footer text, copyright and social links from Site Settings.
4. Nothing — no footer.

The header follows the same idea: Global Header template → header menu → the theme's built-in navigation.

Only a template created with **Set as default for this type** ticked is ever used, and there is currently no UI to make an existing template the default — tick it when you create the template.

## Grid footer area types

| Area type | What renders |
|-----------|--------------|
| Menu (location footer) | The footer menu, same output as on standard pages |
| Fixed | The area's own blocks, or the rich footer if `rich_footer` is on; empty by default |
| Widget / Query / Static | Widgets, a post list, or static content |

## The pieces you can edit

| What | Where in admin | Used by |
|------|----------------|---------|
| Grid footer area | Grids → grid → Footer | All grid pages (the default for new sites) |
| Footer menu (links, colors) | Menus → menu with location **footer** | Grid areas of type Menu; standard pages without a template; archives; 404 |
| Global Footer template (blocks) | Templates → Global Footer (default) | Standard pages & posts only |
| Footer Text, Copyright | Site Settings → Branding | Footer menu output; copyright also in the rich footer |
| Footer colors | Theme tokens `--footer-bg`, `--footer-color`, `--footer-border-color` | Every `footer[role="contentinfo"]` |
| Rich footer | Site settings JSON only (no admin UI) | Everything, when on |

## Rich footer settings

Enabled with `rich_footer: true` in the site's `settings` JSON. Keys it reads:

- `footer_columns` — array of category slugs; each becomes a column listing that category's latest posts (child categories included).
- `footer_column_posts` — posts per column (default 4).
- `footer_tagline` and optional `footer_tagline_bg` (image URL) — a banner line under the columns.
- `footer_copyright` — the legal line (default `© {site name}`).

Style it through the site's custom CSS (for example `footer[role=contentinfo]{background:#141414}`).

## Special cases

- **Archive pages** (category/blog listings) never use the Global Footer template: rich footer → footer menu.
- **The 404 page** uses the footer menu only.
- **Exact-copy design sites** (`design_fidelity: exact`) publish bare: pages carry the design's own footer markup, archives use `settings.chrome_footer_html`.
- There is no per-page "hide footer" switch — a landing page without a footer needs a grid without a footer area.

## After editing

- Footer **menu items**: auto-publish rebuilds the site (if auto-publish is on).
- **Grid** changes: the grid editor starts a site publish itself.
- **Template blocks**: auto-publish rebuilds if on; if off, nothing is flagged — publish manually.
- **Site Settings** (footer text, copyright): nothing republishes — click **Publish**.

## Related

- [Manual: build your first site](/docs/MANUAL)
- [Menus](/docs/GUIDE-MENUS)
- [Grid System](/docs/GRID-SYSTEM)
- [Library & Global Sections](/docs/GUIDE-LIBRARY-GLOBALS)
