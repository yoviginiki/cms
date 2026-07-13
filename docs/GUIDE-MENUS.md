# Menus

Menus control your site's navigation. Each menu belongs to a **location** — header or footer — and publishes as clean static markup inside your theme's navigation area.

## Building a menu

- **Add items** for pages, posts, categories, or custom URLs. Items pointing at CMS content track their target — renaming a page's slug keeps the menu correct and flags affected pages for republish.
- **Drag to reorder**, and **drag onto an item to nest** it as a submenu. Submenus render as dropdowns on desktop and expand inline in the mobile navigation.
- **Draft filtering**: items that point at unpublished (draft) content are automatically excluded from the published menu, so a half-finished page never leaks into navigation.

## Locations

Assign a menu to **header** or **footer**. The published layout renders the header menu in the site's navigation bar (with the theme's overlay or dropdown mobile mode) and the footer menu in the footer area. If a theme ships its own header/footer globals, those templates decide where the menu appears.

## Tips

- Keep the header menu shallow — one submenu level reads best on mobile.
- Custom URL items accept external links; they publish exactly as entered.
- Menu edits flag the whole site stale (navigation appears on every page); with auto-publish on, the republish happens automatically.
