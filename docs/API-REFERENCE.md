# API Reference

Base URL: `/api/v1`

All authenticated routes require a valid Sanctum session cookie. Tenant-scoped routes additionally apply the `tenant.scope` middleware.

---

## Authentication

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| POST | `/auth/login` | `AuthController@login` | Login (throttled 5/min) |
| POST | `/auth/logout` | `AuthController@logout` | Logout (auth required) |
| GET | `/auth/me` | `AuthController@me` | Current user + tenant |
| POST | `/auth/forgot-password` | `PasswordResetController@forgotPassword` | Send reset email |
| POST | `/auth/reset-password` | `PasswordResetController@resetPassword` | Reset with token |

## Users (admin+)

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/users` | `UserController@index` | List tenant users |
| POST | `/users/invite` | `UserController@invite` | Invite new user |
| PUT | `/users/{user}/role` | `UserController@updateRole` | Change user role |
| DELETE | `/users/{user}` | `UserController@destroy` | Remove user |

## Sites

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites` | `SiteController@index` | List sites (paginated) |
| POST | `/sites` | `SiteController@store` | Create site |
| GET | `/sites/{site}` | `SiteController@show` | Get site details |
| PUT | `/sites/{site}` | `SiteController@update` | Update site settings |
| DELETE | `/sites/{site}` | `SiteController@destroy` | Soft-delete site |

## Pages

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/pages` | `PageController@index` | List pages |
| POST | `/sites/{site}/pages` | `PageController@store` | Create page |
| GET | `/sites/{site}/pages/{page}` | `PageController@show` | Get page |
| PUT | `/sites/{site}/pages/{page}` | `PageController@update` | Update page |
| DELETE | `/sites/{site}/pages/{page}` | `PageController@destroy` | Delete page |
| POST | `/sites/{site}/pages/reorder` | `PageController@reorder` | Reorder pages |

## Posts

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/posts` | `PostController@index` | List posts (paginated) |
| POST | `/sites/{site}/posts` | `PostController@store` | Create post |
| GET | `/sites/{site}/posts/{post}` | `PostController@show` | Get post |
| PUT | `/sites/{site}/posts/{post}` | `PostController@update` | Update post |
| DELETE | `/sites/{site}/posts/{post}` | `PostController@destroy` | Delete post |

## Categories

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/categories` | `CategoryController@index` | List categories |
| POST | `/sites/{site}/categories` | `CategoryController@store` | Create category |
| PUT | `/sites/{site}/categories/{category}` | `CategoryController@update` | Update |
| DELETE | `/sites/{site}/categories/{category}` | `CategoryController@destroy` | Delete |
| POST | `/sites/{site}/categories/reorder` | `CategoryController@reorder` | Reorder |

## Tags

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/tags` | `TagController@index` | List tags |
| POST | `/sites/{site}/tags` | `TagController@store` | Create tag |
| PUT | `/sites/{site}/tags/{tag}` | `TagController@update` | Update tag |
| DELETE | `/sites/{site}/tags/{tag}` | `TagController@destroy` | Delete tag |
| POST | `/sites/{site}/tags/{tag}/merge` | `TagController@merge` | Merge into another tag |

## Menus

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/menus` | `MenuController@index` | List menus |
| POST | `/sites/{site}/menus` | `MenuController@store` | Create menu |
| PUT | `/sites/{site}/menus/{menu}` | `MenuController@update` | Update menu |
| DELETE | `/sites/{site}/menus/{menu}` | `MenuController@destroy` | Delete menu |
| PUT | `/sites/{site}/menus/{menu}/items` | `MenuController@syncItems` | Sync menu items |

## Blocks

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/blocks/types` | `BlockController@types` | List registered block types |
| GET | `/sites/{site}/pages/{page}/blocks` | `BlockController@indexForPage` | Get page blocks |
| PUT | `/sites/{site}/pages/{page}/blocks` | `BlockController@syncForPage` | Sync page blocks |
| GET | `/sites/{site}/posts/{post}/blocks` | `BlockController@indexForPost` | Get post blocks |
| PUT | `/sites/{site}/posts/{post}/blocks` | `BlockController@syncForPost` | Sync post blocks |

## Assets

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/assets` | `AssetController@index` | List assets |
| POST | `/sites/{site}/assets` | `AssetController@store` | Upload asset |
| GET | `/sites/{site}/assets/{asset}` | `AssetController@show` | Get asset metadata |
| DELETE | `/sites/{site}/assets/{asset}` | `AssetController@destroy` | Delete asset |
| GET | `/sites/{site}/assets/{asset}/serve/{variant?}` | `AssetServeController@serve` | Serve file |

## Grid System

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/grids` | `GridController@index` | List grids |
| POST | `/sites/{site}/grids` | `GridController@store` | Create grid |
| GET | `/sites/{site}/grids/{grid}` | `GridController@show` | Get grid |
| PUT | `/sites/{site}/grids/{grid}` | `GridController@update` | Update grid |
| DELETE | `/sites/{site}/grids/{grid}` | `GridController@destroy` | Delete grid |
| PUT | `/sites/{site}/grids/{grid}/positions` | `GridController@syncPositions` | Sync positions |
| GET | `/sites/{site}/grid-assignments` | `GridController@assignments` | List assignments |
| POST | `/sites/{site}/grid-assignments` | `GridController@storeAssignment` | Create assignment |
| PUT | `/sites/{site}/grid-assignments/{a}` | `GridController@updateAssignment` | Update |
| DELETE | `/sites/{site}/grid-assignments/{a}` | `GridController@destroyAssignment` | Delete |
| POST | `/sites/{site}/grid-positions/{p}/override` | `GridController@storeOverride` | Position override |
| DELETE | `/sites/{site}/position-overrides/{o}` | `GridController@destroyOverride` | Remove override |
| POST | `/sites/{site}/grids/seed-presets` | `GridController@seedPresets` | Seed presets |

## Redirects

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/redirects` | `RedirectController@index` | List |
| POST | `/sites/{site}/redirects` | `RedirectController@store` | Create |
| PUT | `/sites/{site}/redirects/{r}` | `RedirectController@update` | Update |
| DELETE | `/sites/{site}/redirects/{r}` | `RedirectController@destroy` | Delete |

## Publishing & Deployments

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| POST | `/sites/{site}/publish` | `PublishController@publish` | Trigger publish |
| POST | `/sites/{site}/publish/clear` | `PublishController@clear` | Clear published files |
| GET | `/sites/{site}/download-zip` | `PublishController@downloadZip` | Download ZIP |
| GET | `/sites/{site}/deployments` | `PublishController@history` | Deployment history |
| GET | `/sites/{site}/deployments/{d}` | `PublishController@status` | Deployment status |
| POST | `/sites/{site}/deployments/{d}/rollback` | `PublishController@rollback` | Rollback |

## Theme Engine

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/theme-engine/themes` | `ThemeEngineController@index` | List themes |
| GET | `/sites/{site}/theme-engine/themes/{t}` | `ThemeEngineController@show` | Get theme |
| PUT | `/sites/{site}/theme-engine/themes/{t}` | `ThemeEngineController@update` | Update theme |
| POST | `/sites/{site}/theme-engine/themes/{t}/fork` | `ThemeEngineController@fork` | Fork theme |
| GET | `/sites/{site}/theme-engine/themes/{t}/export` | `ThemeEngineController@export` | Export W3C JSON |
| GET | `/sites/{site}/theme-engine/resolve` | `ThemeEngineController@resolve` | Resolve tokens |
| POST | `/sites/{site}/theme-engine/assign` | `ThemeEngineController@assign` | Assign theme |
| POST | `/sites/{site}/theme-engine/overrides` | `ThemeEngineController@saveOverrides` | Save overrides |
| POST | `/sites/{site}/theme-engine/import` | `ThemeEngineController@import` | Import theme |
| GET | `/sites/{site}/theme-engine/versions` | `ThemeEngineController@versions` | Version history |
| GET | `/sites/{site}/theme-engine/themes/{t}/coverage` | `ThemeEngineController@coverage` | Coverage report |
| GET | `/sites/{site}/theme-engine/studio/frames` | `ThemeEngineController@studioFrames` | Studio frames |
| GET | `/sites/{site}/theme-engine/studio/frame/{slug}` | `ThemeEngineController@studioFrame` | Render frame |

## Preview

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/pages/{page}/preview` | `PreviewController@previewPage` | Preview page |
| GET | `/sites/{site}/posts/{post}/preview` | `PreviewController@previewPost` | Preview post |
| POST | `/sites/{site}/blocks/render` | `PreviewController@renderBlock` | Render single block |
| POST | `/sites/{site}/{type}/{id}/preview-token` | `PreviewController@createPreviewToken` | Create share token |
| GET | `/preview/{token}` | `PreviewController@publicPreview` | Public preview (no auth) |

## Visual Diff

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/pages/{page}/diff` | `DiffController@diffPage` | Page diff |
| GET | `/sites/{site}/posts/{post}/diff` | `DiffController@diffPost` | Post diff |

## Versions

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/pages/{page}/versions` | `VersionController@indexForPage` | List page versions |
| GET | `/sites/{site}/pages/{page}/versions/{v}` | `VersionController@showForPage` | Show version |
| POST | `/sites/{site}/pages/{page}/versions/{v}/restore` | `VersionController@restoreForPage` | Restore |
| GET | `/sites/{site}/posts/{post}/versions` | `VersionController@indexForPost` | List post versions |
| GET | `/sites/{site}/posts/{post}/versions/{v}` | `VersionController@showForPost` | Show version |
| POST | `/sites/{site}/posts/{post}/versions/{v}/restore` | `VersionController@restoreForPost` | Restore |

## Magazines

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/magazines` | `MagazineController@index` | List magazines |
| POST | `/sites/{site}/magazines` | `MagazineController@store` | Create magazine |
| GET | `/sites/{site}/magazines/{m}` | `MagazineController@show` | Get magazine |
| PUT | `/sites/{site}/magazines/{m}` | `MagazineController@update` | Update magazine |
| DELETE | `/sites/{site}/magazines/{m}` | `MagazineController@destroy` | Delete magazine |
| PUT | `/sites/{site}/magazines/{m}/pages` | `MagazineController@savePages` | Save pages |

## Magazine Editor

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/pages/{page}/magazine` | `MagEditorController@show` | Get magazine data |
| PUT | `/sites/{site}/pages/{page}/magazine` | `MagEditorController@sync` | Sync editor state |
| POST | `/sites/{site}/pages/{page}/magazine/pages` | `MagEditorController@addPage` | Add page |
| DELETE | `/sites/{site}/pages/{page}/magazine/pages/{n}` | `MagEditorController@deletePage` | Delete page |

## Magazine Styles

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| CRUD | `/sites/{site}/magazine-styles` | `MagStyleController` | Style presets |

## Magazine Wizard

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| POST | `/magazine/wizard/sessions` | `WizardController@store` | Create session |
| GET | `/magazine/wizard/sessions` | `WizardController@index` | List sessions |
| GET | `/magazine/wizard/sessions/{s}` | `WizardController@show` | Show session |
| DELETE | `/magazine/wizard/sessions/{s}` | `WizardController@destroy` | Delete session |
| POST | `/magazine/wizard/sessions/{s}/messages` | `WizardController@sendMessage` | Send message |
| POST | `/magazine/wizard/sessions/{s}/lock` | `WizardController@lockStep` | Lock step |
| POST | `/magazine/wizard/sessions/{s}/unlock` | `WizardController@unlockStep` | Unlock step |
| POST | `/magazine/wizard/sessions/{s}/provision` | `WizardController@provision` | Provision magazine |

## AI Content Assistant

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| POST | `/ai/generate` | `AiController@generate` | Generate content (20/min) |
| POST | `/ai/rewrite` | `AiController@rewrite` | Rewrite content |
| POST | `/ai/translate` | `AiController@translate` | Translate content |
| POST | `/sites/{site}/pages/{page}/ai/seo` | `AiController@seoSuggest` | SEO suggestions |
| POST | `/sites/{site}/assets/{asset}/ai/alt-text` | `AiController@altText` | Generate alt text |

## WordPress Import

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| POST | `/sites/{site}/import/upload` | `ImportController@upload` | Upload WXR file |
| GET | `/sites/{site}/import/{id}/preview` | `ImportController@preview` | Preview import |
| POST | `/sites/{site}/import/{id}/execute` | `ImportController@execute` | Execute import |
| GET | `/sites/{site}/import/{id}/status` | `ImportController@status` | Import status |

## Site Cloning & Templates

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| POST | `/sites/{site}/clone` | `SiteCloneController@clone` | Clone site |
| POST | `/sites/{site}/export` | `SiteCloneController@export` | Export as template |
| POST | `/sites/{site}/import-template` | `SiteCloneController@importTemplate` | Import template |
| GET | `/templates` | `TemplateController@index` | List templates |
| GET | `/templates/{t}/preview` | `TemplateController@preview` | Preview template |
| POST | `/templates/{t}/install/{site}` | `TemplateController@install` | Install template |

## Editor Presence

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| POST | `/editor/heartbeat` | `EditorPresenceController@heartbeat` | Heartbeat |
| GET | `/editor/presence/{type}/{id}` | `EditorPresenceController@presence` | Who's editing |

## Forms (public)

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| POST | `/sites/{site}/forms/submit` | `FormController@submit` | Submit form (10/min) |

## Analytics

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| POST | `/sites/{site}/t` | `AnalyticsController@track` | Track pixel (60/min, public) |
| GET | `/sites/{site}/analytics` | `AnalyticsController@dashboard` | Analytics dashboard |

## Site Reset

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/sites/{site}/reset/preview` | `SiteResetController@preview` | Preview reset |
| POST | `/sites/{site}/reset/content` | `SiteResetController@resetContent` | Reset content |
| POST | `/sites/{site}/reset/factory` | `SiteResetController@factoryReset` | Factory reset |

## System (admin only)

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/system/updates` | `SystemController@checkUpdate` | Check for updates |
| POST | `/system/updates/apply` | `SystemController@applyUpdate` | Apply update |

## Debug Console (admin only)

| Method | Endpoint | Controller | Description |
|--------|----------|-----------|-------------|
| GET | `/debug` | `DebugController@index` | System info |
| GET | `/debug/logs` | `DebugController@logs` | View logs |
| DELETE | `/debug/logs` | `DebugController@clearLogs` | Clear logs |
| POST | `/debug/retry-failed` | `DebugController@retryFailedJobs` | Retry failed jobs |
| POST | `/debug/flush-failed` | `DebugController@flushFailedJobs` | Flush failed jobs |
| GET | `/debug/cache` | `DebugController@cacheStatus` | Cache status |
| POST | `/debug/clear-cache` | `DebugController@clearCache` | Clear cache |

## Layouts

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/sites/{site}/layouts` | List all available layouts (system + tenant) |

## Dependency Graph

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/sites/{site}/dependency-graph` | Get publishing dependency graph |
