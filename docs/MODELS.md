# Models & Database

All models use UUID primary keys (`HasUuids`). Most support soft deletes.

---

## Tenant

**File:** `app/Models/Tenant.php`
**Table:** `tenants`

| Field | Type | Notes |
|-------|------|-------|
| id | uuid | PK |
| name | string | |
| slug | string | |
| plan | string | Subscription plan |
| settings | jsonb | Tenant-wide settings |
| deleted_at | timestamp | Soft delete |

**Relationships:**
- `users()` hasMany User
- `sites()` hasMany Site

---

## User

**File:** `app/Models/User.php`
**Table:** `users`

| Field | Type | Notes |
|-------|------|-------|
| id | uuid | PK |
| tenant_id | uuid | FK to tenants |
| name | string | |
| email | string | Unique |
| password | string | Hashed |
| role | string | `owner`, `admin`, `editor`, `author`, `viewer` |
| last_login_at | datetime | |
| invitation_token | string | For pending invites |
| invitation_expires_at | datetime | |
| invited_by | uuid | FK to users |
| deleted_at | timestamp | Soft delete |

**Relationships:**
- `tenant()` belongsTo Tenant

**Role Hierarchy:** viewer(0) < author(1) < editor(2) < admin(3) < owner(4)

**Methods:**
- `isOwner(): bool`
- `isAdmin(): bool` -- owner or admin
- `isEditor(): bool` -- owner, admin, or editor
- `hasMinimumRole(string $role): bool`

---

## Site

**File:** `app/Models/Site.php`
**Table:** `sites`

| Field | Type | Notes |
|-------|------|-------|
| id | uuid | PK |
| tenant_id | uuid | FK to tenants |
| name | string | |
| slug | string | Used for subdomain: `{slug}.ensodo.eu` |
| custom_domain | string | Optional custom domain |
| seo_defaults | jsonb | Default SEO meta |
| status | string | `active`, `suspended` |
| settings | jsonb | See SITE-SETTINGS.md |
| active_theme_id | uuid | FK to themes (legacy) |
| deleted_at | timestamp | Soft delete |

**Relationships:**
- `tenant()` belongsTo Tenant
- `pages()` hasMany Page
- `posts()` hasMany Post
- `magazines()` hasMany Magazine
- `issues()` hasMany MagazineIssue
- `categories()` hasMany Category
- `tags()` hasMany Tag
- `menus()` hasMany Menu
- `grids()` hasMany Grid
- `gridAssignments()` hasMany GridAssignment (ordered by priority)
- `assets()` hasMany Asset
- `theme()` belongsTo Theme (via active_theme_id)

---

## Page

**File:** `app/Models/Page.php`
**Table:** `pages`

| Field | Type | Notes |
|-------|------|-------|
| id | uuid | PK |
| site_id | uuid | FK to sites |
| parent_id | uuid | FK to pages (self-referencing tree) |
| title | string | |
| slug | string | URL slug |
| layout_id | uuid | FK to layouts |
| status | string | `draft`, `published`, `archived` |
| editor_mode | string | `blocks`, `magazine` |
| seo_meta | jsonb | Per-page SEO overrides |
| sort_order | integer | |
| grid_id | uuid | Direct grid override |
| published_at | datetime | |
| scheduled_at | datetime | Scheduled publish time |
| deleted_at | timestamp | Soft delete |

**Relationships:**
- `site()` belongsTo Site
- `parent()` belongsTo Page
- `children()` hasMany Page
- `blocks()` morphMany Block
- `versions()` hasMany PageVersion

---

## Post

**File:** `app/Models/Post.php`
**Table:** `posts`

| Field | Type | Notes |
|-------|------|-------|
| id | uuid | PK |
| site_id | uuid | FK to sites |
| category_id | uuid | FK to categories |
| author_id | uuid | FK to users |
| title | string | |
| slug | string | |
| excerpt | text | |
| layout_id | uuid | FK to layouts |
| featured_image | string | URL or asset reference |
| status | string | `draft`, `published`, `archived` |
| editor_mode | string | `blocks`, `magazine` |
| seo_meta | jsonb | |
| grid_id | uuid | Direct grid override |
| published_at | datetime | |
| scheduled_at | datetime | |
| deleted_at | timestamp | Soft delete |

**Relationships:**
- `site()` belongsTo Site
- `category()` belongsTo Category
- `author()` belongsTo User
- `grid()` belongsTo Grid
- `tags()` morphToMany Tag (via `taggables`)
- `blocks()` morphMany Block
- `versions()` hasMany PageVersion

**Accessors:**
- `url_path` -- computed: `/{category-slug}/{post-slug}` or `/{post-slug}`

---

## Block

**File:** `app/Models/Block.php`
**Table:** `blocks`

| Field | Type | Notes |
|-------|------|-------|
| id | uuid | PK |
| blockable_id | uuid | Polymorphic owner ID |
| blockable_type | string | `App\Models\Page` or `App\Models\Post` |
| parent_block_id | uuid | FK to blocks (nested tree) |
| type | string | Block type identifier |
| data | jsonb | Block content data |
| style | jsonb | Visual styling overrides |
| order | integer | Sort order within parent |

**Relationships:**
- `blockable()` morphTo (Page or Post)
- `parent()` belongsTo Block
- `children()` hasMany Block (ordered by `order`)

---

## Category

**File:** `app/Models/Category.php`
**Table:** `categories`

| Field | Type | Notes |
|-------|------|-------|
| id | uuid | PK |
| site_id | uuid | FK to sites |
| parent_id | uuid | Self-referencing tree |
| name | string | |
| slug | string | |
| description | text | |
| sort_order | integer | |
| is_public | boolean | |
| grid_id | uuid | Grid override for category archive |

**Relationships:**
- `site()`, `parent()`, `children()`, `posts()`, `grid()`

---

## Tag

**File:** `app/Models/Tag.php`
**Table:** `tags`

| Field | Type | Notes |
|-------|------|-------|
| id | uuid | PK |
| site_id | uuid | FK |
| name | string | |
| slug | string | |

**Relationships:**
- `site()`, `posts()` morphedByMany

**Pivot table:** `taggables` (tag_id, taggable_id, taggable_type)

---

## Menu / MenuItem

**File:** `app/Models/Menu.php`, `app/Models/MenuItem.php`
**Tables:** `menus`, `menu_items`

**Menu fields:** id, site_id, name, slug, location

**MenuItem fields:** id, menu_id, parent_id, label, url, page_id, post_id, category_id, target, css_class, icon, sort_order

MenuItem can link to a Page, Post, Category, or arbitrary URL. `resolveUrl()` computes the final URL.

---

## Asset

**File:** `app/Models/Asset.php`
**Table:** `assets`

| Field | Type | Notes |
|-------|------|-------|
| id | uuid | PK |
| site_id | uuid | FK |
| original_name | string | Uploaded filename |
| storage_path | string | Path on `assets` disk |
| mime_type | string | |
| file_size | integer | Bytes |
| dimensions | jsonb | `{width, height}` for images |
| variants | jsonb | `{thumb: path, medium: path, ...}` |
| checksum | string | Content hash |
| alt_text | string | Accessibility text |

---

## PageVersion

**File:** `app/Models/PageVersion.php`
**Table:** `page_versions`

| Field | Type | Notes |
|-------|------|-------|
| id | uuid | PK |
| page_id | uuid | FK (nullable) |
| post_id | uuid | FK (nullable) |
| blocks_snapshot | jsonb | Full block tree snapshot |
| seo_snapshot | jsonb | SEO meta at publish time |
| published_by | uuid | FK to users |
| published_at | datetime | |
| version_number | integer | Auto-incremented per content |

Created automatically on each publish.

---

## Deployment / DeployArtifact

**File:** `app/Models/Deployment.php`, `app/Models/DeployArtifact.php`

**Deployment fields:** id, site_id, type (`partial`, `full`, `rollback`), status (`queued`, `building`, `deploying`, `live`, `failed`), artifact_path, triggered_by, started_at, completed_at, error_log, metadata (jsonb)

**DeployArtifact fields:** id, deployment_id, page_id, post_id, output_path, content_hash

---

## Theme

**File:** `app/Models/Theme.php`
**Table:** `themes`

| Field | Type | Notes |
|-------|------|-------|
| id | uuid | PK |
| site_id | uuid | Null for system themes |
| name | string | |
| slug | string | |
| version | string | Semver |
| description | string | |
| manifest_json | jsonb | Legacy manifest |
| config | jsonb | Theme configuration (tokens, fonts, CSS) |
| template_path | string | |
| document | jsonb | W3C design tokens document |
| modes | jsonb | `["light", "dark"]` |
| schema_version | string | |
| is_system | boolean | System themes are read-only |
| is_active | boolean | |
| parent_theme_id | uuid | FK to themes (inheritance) |
| created_by | uuid | FK to users |
| deleted_at | timestamp | |

**Relationships:**
- `site()`, `parent()`, `assignments()`, `versions()`

---

## ThemeAssignment

**File:** `app/Models/ThemeAssignment.php`
**Table:** `theme_assignments`

Fields: id, tenant_id, site_id, theme_id, mode (`light`, `dark`)

Maps a theme to a site for a specific mode.

---

## ThemeOverride

**File:** `app/Models/ThemeOverride.php`
**Table:** `theme_overrides`

Fields: id, tenant_id, site_id, page_id, block_id, scope (`tenant`, `site`, `page`, `block`), mode, token_path, value (jsonb)

Allows overriding individual design tokens at different scopes.

---

## ThemeVersion

**File:** `app/Models/ThemeVersion.php`
**Table:** `theme_versions`

Fields: id, tenant_id, theme_id, site_id, mode, resolved_document (jsonb), content_hash, css_artifact_path, css_artifact_size, created_at

Stores compiled CSS artifacts for each theme/mode combination.

---

## Grid / GridPosition / GridAssignment / GridPositionBlock / PositionOverride

See [GRID-SYSTEM.md](GRID-SYSTEM.md) for full details.

---

## Layout

**File:** `app/Models/Layout.php`
**Table:** `layouts`

Fields: id, tenant_id, parent_layout_id, slug, name, description, wrapper_blade_view, supports (jsonb), allowed_block_types (jsonb), promoted_block_types (jsonb), default_block_stack (jsonb), assets (jsonb), config (jsonb), is_system (boolean), created_by

System layouts cannot be edited or deleted (enforced in model boot).

---

## Magazine / MagazinePage / MagazineElement

**File:** `app/Models/Magazine.php`, `app/Models/MagazinePage.php`, `app/Models/MagazineElement.php`

Magazine fields: id, site_id, title, slug, description, cover_image, status, page_width, page_height, settings (jsonb), published_at

MagazinePage: id, magazine_id, sort_order, background_color, background_image

MagazineElement: id, magazine_page_id, type, data (jsonb), position (jsonb), dimensions (jsonb)

---

## Redirect

**File:** `app/Models/Redirect.php`
**Table:** `redirects`

Fields: id, site_id, source_path, target_url, status_code (301/302), is_regex (boolean), hit_count

---

## ActiveEditor

**File:** `app/Models/ActiveEditor.php`

Tracks real-time editor presence (who is editing what).

---

## SiteTemplate

**File:** `app/Models/SiteTemplate.php`
**Table:** `site_templates`

Pre-built site templates for cloning.

---

## Migration History

Migrations are in `database/migrations/` and follow this sequence:

1. `tenants` -- multi-tenancy foundation
2. `users` -- with tenant_id FK
3. `sites` -- with tenant_id FK
4. `categories` -- with site_id FK
5. `pages` -- with site_id, parent_id, layout_id
6. `posts` -- with site_id, category_id, author_id
7. `blocks` -- polymorphic (blockable_id/type), parent_block_id
8. `block_templates` -- template library
9. `assets` -- file storage metadata
10. `page_versions` -- version snapshots
11. `deployments` / `deploy_artifacts` -- publish history
12. `themes` -- with site_id (nullable for system)
13. RLS policies (PostgreSQL only)
14. `site_templates`
15. `active_editors` -- editor tracking
16. `tags` / `taggables` -- polymorphic tagging
17. `menus` / `menu_items`
18. `redirects`
19. Grid system tables (`grids`, `grid_positions`, `grid_assignments`, `grid_position_blocks`, `position_overrides`)
20. Theme engine tables (`theme_assignments`, `theme_overrides`, `theme_versions`, `theme_customizations`)
21. Analytics tables
22. Magazine tables
23. Magazine editor tables (`mag_pages`, `mag_elements`, `mag_styles`)
24. Issue composer tables
25. Wizard tables
26. `layouts` system
