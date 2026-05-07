# Ensodo CMS Platform

Multi-tenant, block-based content management system with static site generation, magazine editor, and theme engine.

## Overview

- **Laravel 13 backend** with PostgreSQL row-level security for multi-tenancy
- **React 19 / TypeScript admin SPA** with block-based page editor
- **68 block types** with drag-and-drop, inline editing, and style controls
- **Static site generator** — builds HTML, deploys via local copy, SSH rsync, or ZIP download
- **Theme engine** — W3C Design Tokens format, per-site overrides, dark mode
- **CSS Grid layout system** — 6 preset grids with responsive breakpoints
- **Magazine editor** — freeform InDesign-like page layout with flipbook viewer
- **WordPress import** — WXR file parsing with queued background processing
- **Analytics** — privacy-first page view tracking
- **AI assistant** — content generation, rewriting, translation, SEO suggestions

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.3+, Laravel 13, Sanctum auth |
| Frontend | React 19, TypeScript 5.8, Vite 6 |
| UI | TailwindCSS 4, DaisyUI 5, Lucide icons |
| State | Zustand (editor), TanStack Query (API) |
| Database | PostgreSQL with RLS policies |
| Queue | Redis / database driver |
| Testing | PHPUnit, Pest conventions |

## Repository Structure

```
app/
  Domain/              # Domain services (Publishing, Grid, Theme, Magazine, AI, etc.)
  Http/Controllers/    # API v1 controllers (30+)
  Http/Requests/       # Form request validation
  Http/Resources/      # API resource transformers
  Models/              # Eloquent models (33)
  Services/            # Cross-domain services (Theme resolver, compiler, etc.)
  Policies/            # Authorization policies

routes/
  api.php              # ~150 API endpoints
  web.php              # Dynamic site preview, docs, magazine viewer, admin SPA

database/
  migrations/          # 44 migrations (PostgreSQL + RLS policies)
  seeders/             # SystemThemeSeeder, SystemLayoutsSeeder, DatabaseSeeder

resources/
  admin/               # React admin SPA
    src/
      components/
        blocks/        # 68 block components (definition + editor + preview)
        editor/        # Block picker, canvas, settings, toolbar
        layout/        # AdminLayout with sidebar navigation
        magazine/      # Magazine editor canvas and panels
      pages/           # Page components (Dashboard, PageEditor, PostEditor, etc.)
      stores/          # Zustand stores (editorStore, magazineStore)
      lib/api.ts       # API client
      types/           # TypeScript type definitions
  views/
    blocks/            # 69 Blade templates for server-side block rendering
    publishing/        # Layout templates, blog index, archives, error pages
    docs/              # Documentation viewer layout

tests/
  Feature/Api/         # 25 feature test files (~120 tests)
  Feature/             # DynamicSiteTest (URL routing integration tests)

docs/                  # 14 markdown documentation files
config/
  publishing.php       # Deploy strategy, public path, staging path
  system-layouts.php   # 7 system layout definitions
```

## Local Development Setup

```bash
# Clone and install
git clone git@github.com:yoviginiki/cms.git
cd cms

# Full setup (composer install, .env, key, migrate, npm install, build)
composer setup

# Start all services (server + queue + logs + vite)
composer dev
```

Or step by step:

```bash
composer install
cp .env.example .env
php artisan key:generate

# Configure PostgreSQL in .env
# DB_CONNECTION=pgsql
# DB_DATABASE=cms_saas_platform

php artisan migrate --seed    # Creates system themes, layouts
npm install --prefix resources/admin
npm run build --prefix resources/admin
php artisan serve
```

Admin SPA: `http://localhost:8000/admin`

## Available Commands

| Command | Description |
|---------|-------------|
| `composer setup` | Full install (composer, env, migrate, npm, build) |
| `composer dev` | Start server + queue + logs + Vite dev (parallel) |
| `composer test` | Clear config cache and run PHPUnit tests |
| `php artisan db:seed --class=SystemThemeSeeder` | Seed 3 system themes |
| `php artisan db:seed --class=SystemLayoutsSeeder` | Seed 7 system layouts |
| `npm run dev` (in resources/admin) | Vite dev server with HMR |
| `npm run build` (in resources/admin) | Production build to public/admin-assets |

## Admin Interface

The admin SPA is a React app at `resources/admin/src/`.

**Routing:** React Router v7 — routes defined in page components, served by Laravel catch-all `/admin/{any?}`.

**Key pages:**
- `Dashboard.tsx` — site cards with pages/posts counts, ZIP download, view site links
- `PageEditor.tsx` — block editor with drag-and-drop canvas, magazine mode toggle
- `PostEditor.tsx` — post editor with category, tags, excerpt, featured image
- `SiteSettings.tsx` — 8 tabs (General, Front Page, SEO, Deploy, Custom Code, AI, Magazine, Danger Zone)

**Layout:** `AdminLayout.tsx` — collapsible sidebar with site-scoped navigation, publish button, theme toggle.

**State management:**
- `editorStore` (Zustand) — block tree, selection, dirty state, undo history
- `magazineStore` (Zustand) — magazine pages, elements, selection
- TanStack Query — all API data fetching and caching

## Block System

Three-layer architecture:

```
Frontend (React)          Backend (PHP)           Rendering (Blade)
components/blocks/hero/   BlockController          views/blocks/hero.blade.php
  definition.ts           SanitizationService      
  Editor.tsx              BuildPageService         
  Preview.tsx                                      
  index.ts (registers)                             
```

**68 block types** across 11 categories: Typography, Content, Layout, Navigation, Media, Blog, Interactive, Data, Commerce, Forms, Embeds.

**Registration:** Each block's `index.ts` calls `blockRegistry.register(definition, Preview, Editor)`. All blocks are imported in `components/blocks/index.ts`.

### Block Development Checklist

Every new block needs:

**Frontend** (`resources/admin/src/components/blocks/{type}/`):
- `definition.ts` — type, category, label, icon, defaultData, allowsChildren
- `Editor.tsx` — settings panel (props: block, onUpdate)
- `Preview.tsx` — canvas preview (props: block, isSelected)
- `index.ts` — registers with blockRegistry

**Backend**:
- Add to `resources/views/blocks/{type}.blade.php` — server-side HTML rendering
- Variables available: `$data`, `$children`, `$childrenArray`, `$site`
- Sanitization handled by `SanitizationService` automatically

**Register**: Import in `resources/admin/src/components/blocks/index.ts`

## Publishing Pipeline

```
Publish button → PublishOrchestrator → Deployment record → PublishSiteJob (queued)
  → BuildPageService renders each page/post
  → Generates sitemap.xml, feed.xml, robots.txt, 404.html, _redirects
  → DeployService dispatches to strategy:
      local  → copy/symlink to public_path
      ssh    → rsync over SSH
      zip_only → keep build for download
```

Post URLs: `/{category-slug}/{post-slug}/index.html`
Page URLs: `/{page-slug}/index.html`
Homepage: `index.html` (from configured homepage_id or slug "home")

## Media and Assets

- Upload: `AssetController` handles multipart uploads with variant generation
- Storage: local disk with `/api/v1/sites/{id}/assets/{id}/serve` endpoint
- Asset picker: `AssetField` / `AssetPicker` components for block editors
- Publishing: `AssetPublisher::rewriteHtml()` converts API URLs to static paths

## Testing

```bash
# Run all tests
composer test

# Run specific test file
php artisan test --filter=DynamicSiteTest

# Run specific test
php artisan test --filter="DynamicSiteTest::test_homepage_returns_200"
```

**25 test files** covering: Auth, Sites, Pages, Posts, Blocks, Categories, Tags, Menus, Redirects, Users, Grids, MagStyles, Versions, Analytics, Debug, EditorPresence, ThemeEngine, Diff, Preview, SiteReset, SiteClone, AI, Import, MagEditor, Magazines, DynamicSite URL routing.

**Test database:** `cms_saas_platform_test` (PostgreSQL). Tests use `RefreshDatabase` trait.

**Base TestCase** provides: `$tenant`, `$owner`, `actingAsOwner()`, `actingAsAdmin()`, `actingAsEditor()`, `setTenantScope()`.

## Deploy Configuration

Per-site deploy method in Settings → Deploy:

| Method | Setting | Description |
|--------|---------|-------------|
| Local copy | `local` | Copies to `PUBLISH_PATH` (default) |
| SSH rsync | `ssh` | Syncs to remote server via SSH key |
| ZIP only | `zip_only` | No auto-deploy, manual ZIP download |

ZIP download always available at: `GET /api/v1/sites/{id}/download-zip`

## Documentation

Full documentation available at `https://sys.ensodo.eu/docs` (requires login) or in the `docs/` folder:

- [Architecture](docs/ARCHITECTURE.md) — tech stack, folder structure, request lifecycle
- [API Reference](docs/API-REFERENCE.md) — all ~150 API endpoints
- [Models](docs/MODELS.md) — 33 Eloquent models with fields and relationships
- [Services](docs/SERVICES.md) — all domain services with method signatures
- [Blocks](docs/BLOCKS.md) — 69 block types, rendering pipeline
- [Publishing](docs/PUBLISHING.md) — build and deploy pipeline
- [Theme Engine](docs/THEME-ENGINE.md) — design tokens, resolver, compiler
- [Grid System](docs/GRID-SYSTEM.md) — CSS Grid layouts, positions, presets
- [Auth](docs/AUTH.md) — roles, policies, RLS
- [Admin SPA](docs/ADMIN-SPA.md) — React architecture, stores, routes
- [Site Settings](docs/SITE-SETTINGS.md) — all settings fields reference
- [Magazine Editor](docs/MAGAZINE-EDITOR.md) — freeform layout editor
- [Magazine Wizard](docs/magazine-wizard.md) — AI-powered magazine creation
- [Theme Spec](docs/THEME-SPEC.md) — W3C Design Tokens specification

## Known Gaps

- No frontend tests (React components untested)
- Some blocks may lack complete Editor/Preview/Blade coverage
- No CI/CD pipeline configured
- `composer dev` requires `concurrently` npm package globally
- User role validation mismatch: controller accepts `['viewer','author','editor','admin']` but DB enum is `['owner','admin','editor']`
- Magazine Issue Composer routes registered but controller methods are stubs

## Contributing

1. Create a feature branch from `master`
2. Run `composer setup` if fresh clone
3. Make changes
4. Run `composer test` — all tests must pass
5. Run `npm run build --prefix resources/admin` — must build without errors
6. Update docs if adding blocks, endpoints, or settings
7. Commit and push, open PR

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Blank admin page | `php artisan view:clear` + hard refresh (Ctrl+Shift+R) |
| 500 on API calls | Check `storage/logs/laravel.log`, ensure RLS tenant scope is set |
| Missing .env | `cp .env.example .env && php artisan key:generate` |
| DB connection error | Ensure PostgreSQL is running, check `DB_*` in `.env` |
| Vite not loading | Run `npm run dev --prefix resources/admin` or `npm run build` |
| Migration fails | Check for RLS-related errors, may need `--env=testing` for test DB |
| Queue jobs not processing | Run `php artisan queue:work` or use `composer dev` |
| Publish fails | Check deploy_method in site settings, verify SSH keys/paths |
