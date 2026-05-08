# Ensodo CMS Platform

Multi-tenant, block-based content management system with static site generation, magazine editor, and theme engine.

## Overview

- **Laravel 13 backend** with PostgreSQL row-level security for multi-tenancy
- **React 19 / TypeScript admin SPA** with block-based page editor
- **68 registered block types** — completeness varies (see [Current State](#current-verified-state))
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
| Testing | PHPUnit (backend only — no frontend tests) |

## Repository Structure

```
app/
  Domain/              # Domain services (Publishing, Grid, Theme, Magazine, AI, etc.)
  Http/Controllers/    # API v1 controllers (32)
  Http/Requests/       # Form request validation
  Http/Resources/      # API resource transformers
  Models/              # Eloquent models (30)
  Services/            # Cross-domain services (Theme resolver, compiler, etc.)
  Policies/            # Authorization policies

routes/
  api.php              # ~150 API endpoints
  web.php              # Dynamic site preview, docs, magazine viewer, admin SPA

database/
  migrations/          # 44 migrations (PostgreSQL + RLS policies)
  seeders/             # SystemThemeSeeder, SystemLayoutsSeeder, DatabaseSeeder

resources/
  admin/               # React admin SPA (separate Vite project)
    src/
      components/
        blocks/        # 68 block folders (definition + editor + preview)
        editor/        # Block picker, canvas, settings, toolbar, shared fields
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
  Feature/Api/         # 25 API feature test files
  Feature/             # DynamicSiteTest, Security tests

docs/                  # 16 markdown documentation files
```

## Local Development Setup

> **Note:** The admin SPA lives in `resources/admin/` with its own `package.json` and `vite.config.ts`. Root `package.json` scripts proxy to `resources/admin/`, so `npm run dev` and `npm run build` work from the project root.

```bash
# Clone and full setup
git clone git@github.com:yoviginiki/cms.git
cd cms
composer setup    # Installs PHP deps, .env, migrate, admin npm install + build
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

# Admin SPA (either from root or from resources/admin/)
npm install --prefix resources/admin
npm run build:vite            # Vite-only build (skips TypeScript check)

# Start
php artisan serve             # Backend at http://localhost:8000
```

Admin SPA: `http://localhost:8000/admin`

For development with HMR:
```bash
composer dev    # Starts server + queue + logs + admin Vite (all parallel)
```

Or manually:
```bash
npm run dev                  # Admin Vite dev server (proxied from root)
php artisan serve            # In another terminal
php artisan queue:work       # For publish jobs
```

### Known Setup Issues

| Issue | Workaround |
|-------|-----------|
| `npm run build` runs `tsc` first which may fail (TypeScript errors in wizard) | Use `npm run build:vite` or `cd resources/admin && npx vite build` |
| `composer dev` requires `concurrently` | Run `npm install` at project root first (root devDependency) |

## Available Commands

| Command | Description | Notes |
|---------|-------------|-------|
| `composer setup` | Full install (composer, env, migrate, admin build) | |
| `composer dev` | Start server + queue + logs + admin Vite (parallel) | Requires root `npm install` first |
| `composer test` | Run PHPUnit tests | Clears config cache first |
| `composer audit-blocks` | Quick block layer check (bash) | Frontend + Blade + PHP definition |
| `npm run blocks:audit` | Full block audit with JSON report | Outputs to storage/app/block-audit.json |
| `npm run dev` | Admin Vite dev server with HMR | Routes to resources/admin/ |
| `npm run build` | Admin production build (tsc + vite) | Currently fails — TypeScript errors in wizard |
| `npm run build:vite` | Admin Vite build (skip tsc) | Passes — use this for asset builds |
| `php artisan db:seed --class=SystemThemeSeeder` | Seed 3 system themes | |
| `php artisan db:seed --class=SystemLayoutsSeeder` | Seed 7 system layouts | |

## Current Verified State

| Item | Count | Notes |
|------|-------|-------|
| Frontend block folders | 68 | All have definition.ts, Editor.tsx, Preview.tsx, index.ts |
| Blade block templates | 69 | 1 orphan (`quote.blade.php` — frontend uses `pullquote`) |
| PHP BlockDefinition classes | 19 concrete + 1 base | **50 blocks lack server-side validation** |
| Feature test files | 41 | Backend only, ~180+ passing assertions |
| API controllers | 32 | |
| Documentation files | 16 | |
| Shared editor field components | 7 | ColorField, ImageField, NumberField, SelectField, TextArea, TextField, ToggleField |
| Shared property panels | 7 | Advanced, Animation, Layout, Responsive, Spacing, Typography, Visual |

### Block Layer Coverage

| Layer | Count | % of 68 |
|-------|-------|---------|
| Frontend (React) | 68 | 100% |
| Rendering (Blade) | 68 | 100% (excluding orphan) |
| Backend (PHP Definition) | 19 (18 match frontend) | **26%** |

## Block System

Three-layer architecture:

```
Frontend (React)          Backend (PHP)           Rendering (Blade)
components/blocks/hero/   Domain/Blocks/Defs/     views/blocks/hero.blade.php
  definition.ts           HeroBlockDefinition.php
  Editor.tsx              (validation + sanitize)
  Preview.tsx
  index.ts (registers)
```

**68 block types** across 11 categories: Typography, Content, Layout, Navigation, Media, Blog, Interactive, Data, Commerce, Forms, Embeds.

**Important:** While all 68 blocks have frontend components and Blade templates, only 19 have PHP backend definitions with validation rules (and one of those, `QuoteBlockDefinition`, maps to an orphan blade with no frontend component). The remaining 50 frontend blocks accept any JSON payload without server-side schema enforcement.

**Registration:** Each block's `index.ts` calls `blockRegistry.register(definition, Preview, Editor)`. All blocks are imported in `components/blocks/index.ts`.

### Block Quality Contract

All future block development and repair must follow the [Block Quality Contract](docs/BLOCK-CONTRACT.md). The contract defines data contracts, editor UX standards, inline editing classification, theme-safe admin UI requirements, backend validation, Blade rendering, security, accessibility, responsive behavior, testing, and block readiness levels (0-4). Starter templates are in [docs/templates/](docs/templates/).

### Block Development Checklist

Every block should have (currently not all do):

**Frontend** (`resources/admin/src/components/blocks/{type}/`):
- `definition.ts` — type, category, label, icon, defaultData, allowsChildren
- `Editor.tsx` — settings panel (props: block, onUpdate)
- `Preview.tsx` — canvas preview (props: block, isSelected)
- `index.ts` — registers with blockRegistry

**Backend** (`app/Domain/Blocks/Definitions/`):
- `{Type}BlockDefinition.php` — validation rules, sanitization config, allowed keys
- *Currently only 19 of 68 blocks have this*

**Rendering**:
- `resources/views/blocks/{type}.blade.php` — server-side HTML
- Variables: `$data`, `$children`, `$childrenArray`, `$site`

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
- Asset picker: `AssetField` / `AssetPicker` components exist for block editors
- Publishing: `AssetPublisher::rewriteHtml()` converts API URLs to static paths
- **Gap:** 6 blocks still use raw URL text inputs instead of AssetPicker (button, ctabanner, customform, newsletter, socialembed, video)

## Testing

```bash
# Run all tests
composer test

# Run specific test file
php artisan test --filter=DynamicSiteTest

# Run specific test
php artisan test --filter="DynamicSiteTest::test_homepage_returns_200"
```

**Backend tests only.** No frontend tests exist.

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

## Known Architectural Gaps

1. **Root scripts route to admin** — root `package.json` `dev`/`build`/`build:vite` all target `resources/admin/`. Root `vite.config.js` (Laravel boilerplate) and its deps are kept but unused — the admin SPA at `resources/admin/` is the source of truth.
2. **Incomplete backend block definitions** — 50/68 frontend blocks have no PHP BlockDefinition. No server-side validation for most block data.
3. **Inconsistent editor field controls** — 7 shared fields exist but many blocks use inline `<input>` elements instead.
4. **Raw URL inputs** — 6 blocks use text/url inputs where AssetPicker should be used (button, ctabanner, customform, newsletter, socialembed, video).
5. **VisualPanel limitations** — BackgroundEditor exists but gradient/background-image controls need visual builders.
6. **Block audit exists but not in CI** — `scripts/block-audit.sh` verifies all layers (run via `composer audit-blocks`) but is not integrated into any CI pipeline.
7. **Missing frontend tests** — 0 React component tests. Backend has ~180+ passing assertions.
8. **No CI/CD** — no automated pipeline for test + build + deploy.
9. **TypeScript errors in wizard module** — `npm run build` fails; must use `npx vite build` to skip type checking.
10. **User role enum mismatch** — controller validates `['viewer','author','editor','admin']` but DB enum is `['owner','admin','editor']`.

## Recommended Development Order

See [Project Recovery Plan](docs/PROJECT-RECOVERY-PLAN.md) for full details.

1. **Fix setup/build scripts** — routing fixed (all scripts target `resources/admin/`), but `npm run build` still fails on TypeScript errors in wizard module. Use `npm run build:vite` for asset builds.
2. ~~**Add block audit script**~~ — done (`scripts/block-audit.sh`, run via `composer audit-blocks`)
3. **Define block quality contract** — what "done" means for a block
4. **Create shared field controls** — AssetSelectField, GradientField, LinkField, DimensionField, AlignmentField
5. **Refactor VisualPanel/ImageField** — use shared controls consistently
6. **Complete backend definitions** — add PHP definitions for 50 missing blocks
7. **Improve blocks by category** — audit and fix each group
8. **Add CI** — GitHub Actions for test + build + block audit

## Documentation

Full documentation at `https://sys.ensodo.eu/docs` (requires login) or in `docs/`:

- [Architecture](docs/ARCHITECTURE.md) — tech stack, folder structure, request lifecycle
- [API Reference](docs/API-REFERENCE.md) — all ~150 API endpoints
- [Models](docs/MODELS.md) — 30 Eloquent models with fields and relationships
- [Services](docs/SERVICES.md) — all domain services with method signatures
- [Blocks](docs/BLOCKS.md) — 68 block types, three-layer model, known gaps, audit script
- [Publishing](docs/PUBLISHING.md) — build and deploy pipeline
- [Theme Engine](docs/THEME-ENGINE.md) — design tokens, resolver, compiler
- [Grid System](docs/GRID-SYSTEM.md) — CSS Grid layouts, positions, presets
- [Auth](docs/AUTH.md) — roles, policies, RLS
- [Admin SPA](docs/ADMIN-SPA.md) — React architecture, stores, routes
- [Site Settings](docs/SITE-SETTINGS.md) — all settings fields reference
- [Magazine Editor](docs/MAGAZINE-EDITOR.md) — freeform layout editor
- [Magazine Wizard](docs/magazine-wizard.md) — AI-powered magazine creation
- [Theme Spec](docs/THEME-SPEC.md) — W3C Design Tokens specification
- [Project Recovery Plan](docs/PROJECT-RECOVERY-PLAN.md) — technical debt and priority phases

## Contributing

1. Create a feature branch from `master`
2. Install: `composer install` + `cd resources/admin && npm install`
3. Make changes
4. Run `composer test` — all tests must pass
5. Run `cd resources/admin && npx vite build` — must build without errors
6. Update docs if adding blocks, endpoints, or settings
7. Commit and push, open PR

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Blank admin page | `php artisan view:clear` + hard refresh (Ctrl+Shift+R) |
| 500 on API calls | Check `storage/logs/laravel.log`, ensure RLS tenant scope is set |
| Missing .env | `cp .env.example .env && php artisan key:generate` |
| DB connection error | Ensure PostgreSQL is running, check `DB_*` in `.env` |
| Vite not loading | `cd resources/admin && npm run dev` or `npx vite build` |
| `npm run build` fails | TypeScript errors in wizard — use `npx vite build` instead |
| Migration fails | Check for RLS-related errors, may need `--env=testing` for test DB |
| Queue jobs not processing | Run `php artisan queue:work` or use `composer dev` |
| Publish fails | Check deploy_method in site settings, verify SSH keys/paths |
| `composer setup` fails at npm | Run admin install manually: `cd resources/admin && npm install && npx vite build` |
