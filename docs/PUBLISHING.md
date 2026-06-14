# Publishing Pipeline

**Last updated:** Sprint 7 (2026-06-14)

## Overview

The CMS publishes content as **static HTML files**. The pipeline: Editor -> Database -> Build -> Deploy -> Static HTML served by web server.

## Components

| Component | Path | Role |
|-----------|------|------|
| PublishOrchestrator | `app/Domain/Publishing/Services/PublishOrchestrator.php` | Entry point, concurrency control |
| PublishSiteJob | `app/Domain/Publishing/Jobs/PublishSiteJob.php` | Full build orchestration |
| BuildPageService | `app/Domain/Publishing/Services/BuildPageService.php` | Single page/post rendering |
| DeployService | `app/Domain/Publishing/Services/DeployService.php` | Deploy strategy router |
| SymlinkDeployStrategy | `app/Domain/Publishing/Services/Deploy/SymlinkDeployStrategy.php` | Atomic symlink swap |
| RenameDeployStrategy | `app/Domain/Publishing/Services/Deploy/RenameDeployStrategy.php` | Atomic rename |
| SshDeployStrategy | `app/Domain/Publishing/Services/Deploy/SshDeployStrategy.php` | rsync over SSH |

## Publish Flow

```
User clicks "Publish"
    │
    ▼
PublishController@publish
    │
    ▼
PublishOrchestrator::publish(site, user, type)
    ├── Check no active deployment (or clear stale ones >5min)
    ├── Acquire advisory lock: "publish_site_{site_id}"
    ├── Create Deployment record (status: queued)
    └── Dispatch PublishSiteJob (sync mode)
            │
            ▼
      PublishSiteJob::handle()
            ├── Set RLS context (PostgreSQL)
            ├── Create staging directory: storage/app/builds/{deployment_id}/
            ├── Compile theme CSS artifacts (light + dark modes)
            ├── Build all published pages → {slug}/index.html
            ├── Build all published posts → {category}/{slug}/index.html
            ├── Build blog index (paginated): blog/index.html, blog/page/2/index.html
            ├── Build category archives: blog/category/{slug}/index.html
            ├── Build tag archives: blog/tag/{slug}/index.html
            ├── Build author archives: blog/author/{slug}/index.html
            ├── Generate RSS feed: feed.xml
            ├── Build homepage (page, grid, or blog mode)
            ├── Generate sitemap.xml
            ├── Generate robots.txt
            ├── Generate 404.html
            ├── Generate _redirects + .htaccess
            ├── Clean stale post files
            ├── Deploy (via DeployService)
            ├── Update Deployment status → 'live'
            └── Clean old builds (keep 3)
```

## Build Details

### Page Path Resolution

- Homepage (page with `homepage_id` or slug `home`): `index.html`
- Regular page: `{slug}/index.html`
- Post with category: `{category-slug}/{post-slug}/index.html`
- Post without category: `{post-slug}/index.html`

### Homepage Types

| Type | Behavior |
|------|----------|
| `page` | A specific Page is rendered as `index.html` |
| `grid` | A Grid layout is rendered directly as `index.html` |
| `blog` | Blog index (`blog/index.html`) is copied to `index.html` |

### Version Snapshots

On each publish, a `PageVersion` is created for every page/post with:
- Full block tree snapshot
- SEO meta snapshot
- Publisher user and timestamp
- Incrementing version number

### Output Validation

`OutputValidator` checks each built page against Lighthouse-style constraints:
- Warnings are collected but don't block publish
- Results stored in deployment metadata

### Asset URL Rewriting

`AssetPublisher::rewriteHtml()` converts API URLs like:
```
/api/v1/sites/{site}/assets/{asset}/serve/thumb
```
To static paths like:
```
/assets/{checksum}/thumb.{ext}
```

## Deploy Strategies

### Local Deploy (default)

**Config:** `config/publishing.php`

```php
'deploy_strategy' => env('DEPLOY_STRATEGY', 'auto'), // auto, symlink, rename
'public_path' => env('PUBLISH_PATH', public_path('sites')),
```

- `auto`: tests if symlinks work, falls back to rename
- `symlink`: atomic symlink swap (zero-downtime)
- `rename`: atomic directory rename

For sites with `custom_domain`: files are copied directly to the `public_path` root.
For subdomain sites: files go to `public_path/{slug}/`.

### SSH Deploy

Triggered when `settings.deploy_method = 'ssh'`. Uses rsync.

Required site settings:
- `deploy_ssh_host`
- `deploy_ssh_user`
- `deploy_ssh_path`
- `deploy_ssh_port` (default: 22)
- `deploy_ssh_key` (path to private key)

### ZIP-Only

When `settings.deploy_method = 'zip_only'`: builds but doesn't deploy. User downloads the ZIP via `GET /sites/{site}/download-zip`.

## Rollback

```
POST /sites/{site}/deployments/{deployment}/rollback
```

1. Creates a new Deployment with type `rollback`
2. Uses the target deployment's artifact path
3. DeployService re-deploys from the old build directory

## Related Jobs

| Job | Path | Trigger |
|-----|------|---------|
| `PublishSiteJob` | `app/Domain/Publishing/Jobs/PublishSiteJob.php` | Manual publish, auto-publish |
| `ProcessScheduledContentJob` | `app/Domain/Publishing/Jobs/ProcessScheduledContentJob.php` | Cron: publishes scheduled content |

## Auto-Publish

When `settings.auto_publish = true`:
- `AutoPublishService` triggers a publish whenever content is saved
- Debounced to avoid excessive builds

## Deployment Record

Status lifecycle: `queued` -> `building` -> `deploying` -> `live` (or `failed`)

Metadata tracks:
- `pages_total` / `pages_built` -- progress
- `current_step` -- for UI progress display
- `lighthouse_checks` -- validation results
- `deploy_method` -- which strategy was used

## Generated Files

| File | Purpose |
|------|---------|
| `index.html` | Homepage |
| `{page-slug}/index.html` | Static pages |
| `{cat}/{post}/index.html` | Blog posts |
| `blog/index.html` | Blog listing (paginated) |
| `blog/category/{slug}/index.html` | Category archives |
| `blog/tag/{slug}/index.html` | Tag archives |
| `blog/author/{slug}/index.html` | Author archives |
| `feed.xml` | RSS/Atom feed |
| `sitemap.xml` | XML sitemap |
| `robots.txt` | Robots directives |
| `404.html` | Error page |
| `_redirects` | Netlify/CF Pages format redirects |
| `.htaccess` | Apache redirect rules |
| `themes/site-{id}/*.css` | Compiled theme CSS |

## Sprint 7 Additions

### Publish Status Derivation (Frontend)
`publishHelpers.ts` provides `derivePublishStatus()`:
- `never` — no deployments exist
- `in_progress` — queued, building, or deploying
- `success` — last deployment is live, no dirty pages
- `failed` — last deployment failed
- `unpublished_changes` — last deployment live but content changed since
- `warnings` — published with validation warnings

### Verification Checklist
`generateVerificationChecklist()` checks:
- Pages generated count vs expected
- HTML validation (Lighthouse checks)
- Sitemap generated
- Robots.txt generated
- RSS feed generated

### Domain Validation
`validateDomainFormat()` checks:
- Non-empty
- Valid characters (alphanumeric, hyphens, dots)
- Has at least one dot
- No leading/trailing hyphens
- Max 253 characters

### Current Limitations
1. Publishing is synchronous — blocks for up to 5 minutes
2. DependencyGraph service exists but is not integrated
3. No webhook/email notifications on publish completion
4. SSH strategy has no rollback support
5. Preview tokens have no visible expiry

### Sprint 8 Recommendations
1. Integrate DependencyGraph for incremental publishing
2. Add async publish via queue
3. Add webhook notifications
4. Add broken internal link checker
5. Add publish scheduling
