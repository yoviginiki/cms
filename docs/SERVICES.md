# Domain Services

All services live under `app/Domain/` organized by bounded context.

---

## Publishing Domain

**Path:** `app/Domain/Publishing/`

### PublishOrchestrator

**File:** `app/Domain/Publishing/Services/PublishOrchestrator.php`

Entry point for all publish operations. Creates a `Deployment` record and dispatches `PublishSiteJob`.

```php
publish(Site $site, User $triggeredBy, string $type = 'partial'): Deployment
rollback(Site $site, Deployment $target, User $triggeredBy): Deployment
```

- Prevents concurrent deployments (advisory lock per site)
- Clears stale deployments older than 5 minutes
- Runs synchronously (`dispatchSync`) for fast publishes

### BuildPageService

**File:** `app/Domain/Publishing/Services/BuildPageService.php`

Renders a single Page or Post into final HTML.

```php
build(Page|Post $content, ?Theme $theme, Site $site): string
buildAndValidate(Page|Post $content, ?Theme $theme, Site $site): array
renderBlock(Block $block, Site $site): string
```

**Rendering pipeline:**
1. Generate SEO head content
2. Resolve design tokens CSS
3. Resolve grid or use standard block rendering
4. Render menu navigation (header/footer)
5. Apply layout wrapper (if non-standard layout)
6. Inject head/body scripts and custom CSS
7. Apply `page_render` filter hook
8. Rewrite asset URLs from API to static paths
9. Minify HTML

**Supports three editor modes:**
- `blocks` -- standard block-based rendering
- `magazine` -- delegates to MagazineRenderer
- Grid-based -- when GridResolver finds a matching grid

### DeployService

**File:** `app/Domain/Publishing/Services/DeployService.php`

Deploys built files to their destination.

```php
deploy(Deployment $deployment, string $stagingPath): void
rollback(Deployment $deployment): void
```

**Deploy strategies:**
- `local` (default) -- copies/symlinks to public path
- `ssh` -- rsync over SSH
- `zip_only` -- no deploy, user downloads ZIP manually

**Strategy classes:**
- `Deploy/SymlinkDeployStrategy.php` -- atomic symlink swap
- `Deploy/RenameDeployStrategy.php` -- atomic rename
- `Deploy/SshDeployStrategy.php` -- rsync to remote server

### PublishSiteJob

**File:** `app/Domain/Publishing/Jobs/PublishSiteJob.php`

Queue job (or sync) that orchestrates the full build cycle:
1. Set RLS context for PostgreSQL
2. Compile theme CSS artifacts
3. Build all published pages
4. Build all published posts
5. Build blog index (paginated), category archives, tag archives, author archives
6. Generate RSS feed
7. Build homepage (page, grid, or blog type)
8. Generate sitemap.xml, robots.txt, 404.html, _redirects
9. Clean unpublished post files
10. Deploy via DeployService
11. Record validation results (Lighthouse checks)
12. Clean old builds (keep last 3)

### Supporting Services

| Service | File | Purpose |
|---------|------|---------|
| `SeoService` | `SeoService.php` | Generate `<head>` SEO tags, structured data |
| `SanitizationService` | `SanitizationService.php` | Sanitize block data (XSS prevention) |
| `HtmlMinifier` | `HtmlMinifier.php` | Minify output HTML |
| `OutputValidator` | `OutputValidator.php` | Validate against Lighthouse constraints |
| `SitemapGenerator` | `SitemapGenerator.php` | Generate sitemap.xml |
| `RobotsGenerator` | `RobotsGenerator.php` | Generate robots.txt |
| `RssFeedGenerator` | `RssFeedGenerator.php` | Generate Atom/RSS feed |
| `StructuredDataService` | `StructuredDataService.php` | JSON-LD structured data |
| `AssetPublisher` | `AssetPublisher.php` | Rewrite API asset URLs to static paths |
| `DiffService` | `DiffService.php` | Visual diff between versions |
| `DependencyGraph` | `DependencyGraph.php` | Page/asset dependency tracking |
| `SmartPublisher` | `SmartPublisher.php` | Incremental publish (only changed) |
| `AutoPublishService` | `AutoPublishService.php` | Auto-publish on content save |
| `BlockStyleResolver` | `BlockStyleResolver.php` | Resolve block-level style tokens |
| `MagazineRenderer` | `MagazineRenderer.php` | Render magazine-mode pages |
| `TextFlowCalculator` | `TextFlowCalculator.php` | Text flow for magazine layouts |

---

## Blocks Domain

**Path:** `app/Domain/Blocks/`

### BlockRegistry

**File:** `app/Domain/Blocks/Services/BlockRegistry.php`

Registry of all block type definitions.

```php
register(BlockDefinition $definition): void
has(string $type): bool
get(string $type): ?BlockDefinition
validate(string $type, array $data): bool
getAllTypes(): array
getByCategory(string $category): array
```

### BlockService

**File:** `app/Domain/Blocks/Services/BlockService.php`

CRUD operations for the block tree.

```php
syncBlocks(Model $blockable, array $blocksData): array
getBlockTree(Model $blockable): array
```

- `syncBlocks` replaces the entire block tree atomically (transaction)
- Supports nested children, style, animation, responsive, advanced metadata

### EditorPresenceService

**File:** `app/Domain/Blocks/Services/EditorPresenceService.php`

Real-time collaboration tracking.

---

## Grid Domain

**Path:** `app/Domain/Grid/`

### GridResolver

**File:** `app/Domain/Grid/Services/GridResolver.php`

Determines which grid layout to use for content.

```php
resolve(Page|Post $content, Site $site): ?Grid
```

**Resolution priority:**
1. Direct `grid_id` on the page/post
2. Exact page/post assignment
3. Category assignment (posts only)
4. Post type assignment (`post` or `page`)
5. URL pattern rule (fnmatch)
6. Site default assignment

### GridRenderer

**File:** `app/Domain/Grid/Services/GridRenderer.php`

Renders a grid into HTML + CSS.

```php
render(Grid $grid, Page|Post $content, Site $site): array  // ['css' => ..., 'html' => ...]
```

### GridCssGenerator

**File:** `app/Domain/Grid/Services/GridCssGenerator.php`

Generates CSS Grid styles from grid configuration.

```php
generate(Grid $grid): string
```

Outputs: grid container, position areas, responsive breakpoints, backgrounds, overlays, mobile ordering.

### PositionRenderer

**File:** `app/Domain/Grid/Services/PositionRenderer.php`

Renders content within a grid position (blocks assigned to that area).

### GridPresetSeeder

**File:** `app/Domain/Grid/Services/GridPresetSeeder.php`

Seeds common grid layout presets (e.g., sidebar+content, full-width hero, etc.)

---

## Theme Domain

**Path:** `app/Domain/Theme/`

### DesignTokenGenerator

**File:** `app/Domain/Theme/Services/DesignTokenGenerator.php`

Generates CSS custom properties from theme tokens.

```php
generate(Site $site): string
getDefaults(): array
```

**Token merge chain:** defaults -> theme config tokens -> `theme_customizations` table

### SystemThemeSeeder

**File:** `app/Domain/Theme/Services/SystemThemeSeeder.php`

Installs/updates system themes from `storage/app/themes/system/` directory.

```php
seed(Site $site): void
listSystemThemes(): array
```

---

## Sites Domain

**Path:** `app/Domain/Sites/`

### SiteService

**File:** `app/Domain/Sites/Services/SiteService.php`

CRUD for sites with business logic (slug generation, default page creation).

### SiteCloneService

**File:** `app/Domain/Sites/Services/SiteCloneService.php`

Deep clone sites including pages, posts, blocks, assets, themes, menus.

---

## Pages / Posts / Categories / Tags

| Service | File | Purpose |
|---------|------|---------|
| `PageService` | `app/Domain/Pages/Services/PageService.php` | Page CRUD, reordering, slug generation |
| `PostService` | `app/Domain/Posts/Services/PostService.php` | Post CRUD, reading time calc |
| `ReadingTimeService` | `app/Domain/Posts/Services/ReadingTimeService.php` | Estimate reading time |
| `CategoryService` | `app/Domain/Categories/Services/CategoryService.php` | Category CRUD, tree |
| `TagService` | `app/Domain/Tags/Services/TagService.php` | Tag CRUD, merge |

---

## Menus Domain

**Path:** `app/Domain/Menus/`

### MenuService

**File:** `app/Domain/Menus/Services/MenuService.php`

Menu CRUD and item synchronization.

### MenuRenderer

**File:** `app/Domain/Menus/Services/MenuRenderer.php`

Renders menu HTML for published pages.

```php
renderByLocation(Site $site, string $location): string
```

---

## Assets Domain

### AssetService

**File:** `app/Domain/Assets/Services/AssetService.php`

Upload, variant generation (thumbnails, responsive sizes), deletion.

---

## Import Domain

**Path:** `app/Domain/Import/`

| Service | Purpose |
|---------|---------|
| `WordPressImporter` | Parses WXR export files |
| `GutenbergParser` | Converts Gutenberg blocks to CMS blocks |
| `ContentRewriter` | Rewrites URLs and references |
| `AttachmentImporter` | Downloads and stores remote media |
| `ExecuteImportJob` | Background job for large imports |

---

## AI Domain

### ContentAssistant

**File:** `app/Domain/Ai/Services/ContentAssistant.php`

AI-powered content generation, rewriting, translation, SEO suggestions, and alt-text generation. Uses Anthropic Claude API.

---

## Magazine Domain

**Path:** `app/Domain/Magazine/`

### MagazineService

**File:** `app/Domain/Magazine/Services/MagazineService.php`

Magazine CRUD, page management, element positioning.

---

## Forms Domain

### FormSubmissionService

**File:** `app/Domain/Forms/Services/FormSubmissionService.php`

Handles contact form submissions (email delivery, storage).

---

## Hooks Domain

**Path:** `app/Domain/Hooks/`

### HookDispatcher

**File:** `app/Domain/Hooks/HookDispatcher.php`

WordPress-style hooks system for extensibility.

- `collectAction(string $name, ...$args): string` -- collect output from all registered action handlers
- `applyFilter(string $name, $value, ...$args): mixed` -- pipe value through filter chain

### Hook

**File:** `app/Domain/Hooks/Hook.php`

Hook registration and management.

---

## Database Domain

### RlsManager

**File:** `app/Domain/Database/RlsManager.php`

Manages PostgreSQL Row-Level Security policies.

```php
static enable(): void   // Creates RLS policies on all tenant tables
static disable(): void  // Removes RLS policies
static isSupported(): bool  // Only true for PostgreSQL
```

### AdvisoryLock

**File:** `app/Domain/Database/AdvisoryLock.php`

PostgreSQL advisory locks for concurrency control (e.g., preventing concurrent publishes).

---

## System Domain

### UpdateService

**File:** `app/Domain/System/Services/UpdateService.php`

Self-update mechanism. Checks update server (`https://updates.ensodo.eu`) and applies patches.

---

## Concerns (Traits)

| Trait | File | Purpose |
|-------|------|---------|
| `TenantScoped` | `app/Domain/Concerns/TenantScoped.php` | Global scope filtering by tenant_id + auto-fill on create |
| `SiteScoped` | `app/Domain/Concerns/SiteScoped.php` | Global scope filtering by site_id |
| `AuthorizesWithTenant` | `app/Domain/Concerns/AuthorizesWithTenant.php` | Policy helper |
