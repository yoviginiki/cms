# Architecture Overview

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13 (PHP 8.3+) |
| Database | PostgreSQL (primary) / MySQL (supported) |
| Auth | Laravel Sanctum (cookie-based SPA auth) |
| Queue | Database or Redis (configurable via `config/cms.php`) |
| Cache | File or Redis |
| Frontend Admin | React 19 SPA (TypeScript 5.8, Vite 6) — separate project in `resources/admin/` |
| State Management | Zustand 5 (stores), TanStack Query 5 (API) |
| CSS (admin) | TailwindCSS 4, DaisyUI 5, Lucide icons |
| Publishing Output | Static HTML files (SSG-style) |
| Real-time | Laravel Reverb / SSE (optional) |
| AI Integration | Anthropic Claude API |

## Folder Structure

```
cms-platform/
├── app/
│   ├── Domain/                   ← Domain services (DDD-lite)
│   │   ├── Ai/Services/
│   │   ├── Assets/Services/
│   │   ├── Blocks/
│   │   │   ├── Definitions/      ← Block type contracts
│   │   │   └── Services/         ← BlockRegistry, BlockService
│   │   ├── Categories/Services/
│   │   ├── Concerns/             ← TenantScoped, SiteScoped traits
│   │   ├── Database/             ← RlsManager, AdvisoryLock
│   │   ├── Forms/Services/
│   │   ├── Grid/Services/        ← GridResolver, GridRenderer, GridCssGenerator
│   │   ├── Hooks/                ← Hook system (filters + actions)
│   │   ├── Import/               ← WordPress import pipeline
│   │   ├── IssueComposer/        ← Magazine issue AI composer
│   │   ├── Magazine/             ← Magazine editor models + services
│   │   ├── Menus/Services/
│   │   ├── Pages/Services/
│   │   ├── Posts/Services/
│   │   ├── Publishing/           ← Build, deploy, SEO, sitemap, RSS
│   │   │   ├── Jobs/
│   │   │   └── Services/
│   │   │       └── Deploy/       ← Strategy pattern deploy adapters
│   │   ├── Sites/Services/
│   │   ├── System/Services/
│   │   ├── Tags/Services/
│   │   └── Theme/Services/       ← DesignTokenGenerator, SystemThemeSeeder
│   ├── Http/
│   │   ├── Controllers/Api/V1/   ← All API controllers
│   │   ├── Middleware/           ← TenantScope, SetTenantFromAuth, SecurityHeaders, EnsureRole
│   │   ├── Requests/            ← Form request validation
│   │   └── Resources/           ← API resource transformers
│   ├── Models/                   ← Eloquent models (UUIDs, soft deletes)
│   ├── Policies/                ← Authorization policies
│   ├── Providers/
│   └── Services/
│       ├── Layout/              ← LayoutResolver
│       └── Theme/               ← ThemeResolver, ThemeCompiler, TokenMerger, etc.
├── bootstrap/
│   └── app.php                  ← Middleware registration, route config
├── config/
│   ├── cms.php                  ← CMS version, Redis, AI, DB flags
│   ├── publishing.php           ← Deploy strategy, paths
│   ├── magazine_templates.php
│   └── system-layouts.php
├── database/
│   ├── migrations/              ← 44 migrations
│   └── seeders/
├── docs/                        ← This documentation
├── resources/
│   ├── admin/src/               ← React SPA source
│   │   ├── components/
│   │   │   ├── blocks/          ← 68 block editor components
│   │   │   ├── editor/          ← Builder canvas, sidebar, block picker
│   │   │   ├── layout/          ← AdminLayout shell
│   │   │   ├── magazine/
│   │   │   └── ui/              ← Reusable UI components
│   │   ├── hooks/               ← Custom React hooks
│   │   ├── lib/                 ← API client, utilities
│   │   ├── pages/               ← Route-level page components
│   │   ├── stores/              ← Zustand stores
│   │   └── types/               ← TypeScript type definitions
│   └── views/
│       ├── blocks/              ← 69 Blade templates for published output
│       └── publishing/          ← Layout wrappers (layout.blade.php, grid-layout.blade.php)
├── routes/
│   ├── api.php                  ← All API routes (prefixed /api/v1/)
│   ├── web.php                  ← SPA entry + dynamic site serving
│   └── console.php              ← Artisan commands
├── storage/
│   └── app/
│       ├── builds/              ← Staging area for publishes
│       └── themes/system/       ← System theme JSON manifests
├── tests/
│   └── Feature/Api/             ← Feature tests
└── public/
    └── build/                   ← Compiled Vite assets
```

## Two Vite Contexts

This project has two separate npm/Vite setups. They are independent and should not be confused:

| Context | Location | Purpose | Vite Version |
|---------|----------|---------|--------------|
| Root | `package.json` + `vite.config.js` | Laravel default asset pipeline (CSS/JS for public-facing pages) | Vite 8 |
| Admin SPA | `resources/admin/package.json` + `resources/admin/vite.config.ts` | React 19 admin application | Vite 6 |

The admin SPA is a fully self-contained React project. Its `vite.config.ts` builds to `public/admin-assets/` and uses `@vitejs/plugin-react`. The root Vite config uses `laravel-vite-plugin` for standard Laravel asset compilation.

When developing the admin SPA, always run `npm` commands from `resources/admin/`, not from the project root.

## Request Lifecycle

1. Request arrives at `/api/v1/*`
2. `EnsureFrontendRequestsAreStateful` handles Sanctum cookie auth
3. `SetTenantFromAuth` middleware reads user's `tenant_id` and sets PostgreSQL RLS variable (`app.current_tenant_id`)
4. `TenantScope` middleware (on nested routes) double-checks tenant access
5. Controller handles request, delegates to domain services
6. Models use `TenantScoped` / `SiteScoped` global scopes for application-level isolation

## Multi-Tenancy Model

- **Tenant** is the top-level isolation boundary (organization)
- Each User belongs to exactly one Tenant
- Each Site belongs to exactly one Tenant
- PostgreSQL Row-Level Security (RLS) policies enforce isolation at DB level
- Application-level global scopes (`TenantScoped` trait) provide a second layer
- MySQL mode uses only application-level scoping (no RLS)

## Publishing Model

The CMS generates **static HTML** files. Content is edited via the admin SPA and stored in the database. When published:

1. `PublishOrchestrator` creates a `Deployment` record
2. `PublishSiteJob` builds all pages/posts into a staging directory
3. `BuildPageService` renders each page using Blade templates + theme tokens
4. `DeployService` copies the built files to the public web root (local, SSH, or ZIP)
5. The published site is pure static HTML, no backend required to serve it

## Configuration

Key environment variables:

| Variable | Purpose |
|----------|---------|
| `DB_CONNECTION` | `pgsql` or `mysql` |
| `DEPLOY_STRATEGY` | `auto`, `symlink`, or `rename` |
| `PUBLISH_PATH` | Where static files are deployed |
| `REDIS_ENABLED` | Enables Redis for cache/queue/session |
| `AI_ENABLED` | Enables AI content assistant |
| `ANTHROPIC_API_KEY` | Claude API key for AI features |
