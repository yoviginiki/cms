# CMS Stabilization Sprint 3

**Date:** 2026-06-14

## Goal
Make site creation end-to-end: theme → template → create → starter pages → preview.

## What was implemented

### Starter Template Auto-Application (Tasks 2+3)
- `StarterTemplateService` — creates pages with pre-built block content
- 4 templates: Blank, Blog, Portfolio, Business
- Each template creates appropriate pages:
  - **Blank**: Home
  - **Blog**: Home, About, Contact, Blog (with latestposts block)
  - **Portfolio**: Home, About, Work (with gallery block), Contact
  - **Business**: Home, About, Services (3-column), Team, Contact (with form)
- Idempotent — skips existing page slugs on retry
- Sets homepage_id in site settings automatically
- API: `GET /starter-templates`, `POST /sites/{site}/apply-template`

### Theme Preview Modal (Tasks 4+5)
- ThemeCard screenshot replaced with generated visual preview
  (mini browser chrome with simulated page layout)
- Click to open preview modal with:
  - Theme name, description, modes, status badges
  - Sample layout preview with buttons
  - "Open Editor" action
- Hover shows eye icon overlay

### Site Wizard Polish (Task 6)
- 4 template options: Blank, Blog, Portfolio, Business
- Template auto-applied after site creation via API
- Step 4 success screen with:
  - Success icon + message
  - Template application result
  - "Open Pages" and "Settings" buttons
- Steps indicator hidden on success
- Footer changes to "Close" on success

### Tests (Task 7)
- `starterTemplates.test.ts` — 3 tests for template registry, slug gen, payload
- Total: 21 tests across 3 files, all passing

## Changed files (6)

| File | Change |
|------|--------|
| `app/Domain/Sites/Services/StarterTemplateService.php` | NEW — template definitions + application |
| `routes/api.php` | Added starter-templates + apply-template endpoints |
| `resources/admin/src/pages/Dashboard.tsx` | Wizard: template auto-apply + success screen |
| `resources/admin/src/pages/ThemeEngine.tsx` | Theme preview modal + generated screenshots |
| `resources/admin/src/lib/starterTemplates.test.ts` | NEW — template tests |
| `docs/CMS-STABILIZATION-SPRINT-3.md` | NEW — this report |

## How starter template auto-application works

1. User opens Site Wizard from Dashboard
2. Step 1: enters site name + slug
3. Step 2: selects template (Blank/Blog/Portfolio/Business)
4. Step 3: confirms details
5. On create:
   - `sites.create()` API creates the site + default theme
   - `POST /sites/{id}/apply-template` calls `StarterTemplateService::apply()`
   - Service creates pages with Section→Row→Column→Module block hierarchy
   - Sets homepage_id for the Home page
6. Step 4: success screen with navigation options

## Commands run

```
composer validate              → PASS
composer audit-blocks           → PASS (80/80/81)
npm run build                   → PASS (0 errors, 18s)
npm run test:run                → PASS (21 tests, 3 files)
php -l StarterTemplateService   → PASS
php -l routes/api.php           → PASS
```

## Current limitations

1. **Templates are PHP-only** — no UI to add custom templates
2. **Theme is not selectable in wizard** — uses default theme
3. **No template preview** — pages created sight-unseen
4. **Images not included** — blocks have empty image references
5. **Blog template needs posts** — latestposts block shows empty until posts added

## Recommended Sprint 4

1. **Theme selection in wizard** — Step 2 picks theme, Step 3 picks template
2. **Template preview** — show expected pages/layout before creation
3. **Sample posts** — Blog template creates 2-3 sample posts
4. **Custom template editor** — save/share page configurations as templates
5. **Continue color cleanup** — PageEditor, PostEditor, SiteSettings
6. **More frontend tests** — component tests for wizard, theme gallery
