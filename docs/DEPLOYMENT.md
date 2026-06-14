# Deployment Guide

**Last updated:** Sprint 11 (2026-06-14)

## Server Requirements

- PHP 8.3+
- PostgreSQL 15+ (with RLS support)
- Node.js 20+ (for build only)
- Composer 2.x
- GD or Imagick PHP extension (for image processing)
- Cron support (for scheduler)
- 512MB+ RAM

## Environment Variables

Copy `.env.example` to `.env` and configure:

```env
# Application
APP_NAME="CMS Platform"
APP_ENV=production
APP_KEY=                    # Generate: php artisan key:generate
APP_DEBUG=false
APP_URL=https://sys.ensodo.eu

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cms
DB_USERNAME=
DB_PASSWORD=

# Publishing
PUBLISH_PATH=/path/to/sites         # Where static sites are generated
DEPLOY_STRATEGY=auto                # auto, symlink, rename
TENANT_BASE_PATH=/home/cytechno/web # Custom domain sites root

# AI (optional)
AI_ENABLED=false
ANTHROPIC_API_KEY=                  # Required if AI_ENABLED=true
AI_MODEL=claude-sonnet-4-20250514

# Queue
QUEUE_CONNECTION=database

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Cache
CACHE_STORE=file
```

## Installation

```bash
# 1. Install PHP dependencies
composer install --no-dev --optimize-autoloader

# 2. Install Node dependencies and build admin
cd resources/admin
npm ci
npm run build
cd ../..

# 3. Generate app key (first time only)
php artisan key:generate

# 4. Run migrations
php artisan migrate --force

# 5. Seed system themes (first time only)
php artisan db:seed --class=SystemThemeSeeder

# 6. Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Set storage permissions
chmod -R 775 storage bootstrap/cache
```

## Scheduler (Cron)

Add to crontab:
```cron
* * * * * cd /path/to/cms-platform && php artisan schedule:run >> /dev/null 2>&1
```

This runs:
- Queue worker (processes jobs)
- Scheduled content publishing
- Wizard session cleanup (daily)
- Editor presence cleanup

## Post-Deploy Verification

```bash
# Verify build
composer validate
php artisan about

# Verify admin loads
curl -s -o /dev/null -w "%{http_code}" https://your-domain/admin/login
# Expected: 200

# Verify API responds
curl -s -o /dev/null -w "%{http_code}" https://your-domain/api/v1/available-themes
# Expected: 302 (auth required)
```

## Backup Before Deploy

```bash
# Database backup
pg_dump -U postgres cms > backup_$(date +%Y%m%d_%H%M%S).sql

# Generated sites backup (optional)
tar -czf sites_backup.tar.gz /path/to/sites/
```

## Rollback Plan

1. **Code rollback:** `git checkout <previous-tag>`
2. **Database rollback:** `php artisan migrate:rollback` (if migration was run)
3. **Asset rollback:** Previous build assets in git history
4. **Published site rollback:** Via admin Publish → Rollback button

## Troubleshooting

| Issue | Solution |
|-------|----------|
| 500 error after deploy | Check `storage/logs/laravel.log`, ensure `APP_KEY` set |
| Admin shows blank page | Rebuild: `cd resources/admin && npm run build` |
| Publish fails | Check `PUBLISH_PATH` permissions, disk space |
| AI returns 503 | Set `AI_ENABLED=true` and `ANTHROPIC_API_KEY` in .env |
| Theme not applying | Run `php artisan config:cache` after env changes |
| Queue jobs stuck | `php artisan queue:restart` |

## Directory Structure

```
cms-platform/
├── app/                    # PHP application code
├── config/                 # Configuration files
├── database/               # Migrations, seeders, factories
├── public/                 # Web root (admin assets here)
├── resources/
│   ├── admin/             # React admin SPA source
│   └── views/             # Blade templates (blocks, layouts)
├── routes/                 # API routes
├── storage/               # Logs, cache, builds
│   └── app/builds/        # Staging for publish builds
├── docs/                   # Documentation
└── .env                    # Environment config (not in git)
```
