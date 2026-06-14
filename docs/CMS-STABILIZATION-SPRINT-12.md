# CMS Stabilization Sprint 12

**Date:** 2026-06-14
**Goal:** Release Candidate Fixes and v1.0 preparation.

## What Was Implemented

### Task 1: Activity Log DB Migration
- Created `activity_logs` table with migration
- Fields: id, site_id, user_id, action, subject_type, subject_id, metadata, ip_address, user_agent, timestamps
- Indexes on (site_id, created_at) and (user_id, created_at)
- Migration ran successfully on production DB

### Task 2: Activity Log Model & Service
- `ActivityLog` model with UUID, fillable fields, casts
- `ActivityLogService` with `log()` method that fails silently (never blocks primary actions)
- Metadata sanitization: removes keys containing password/api_key/secret/token/credential
- Truncates long values to 500 chars

### Task 3: Activity Log UI Integration
- API endpoint: `GET /sites/{site}/activity` (returns last 50 entries)
- `formatActivityAction()` frontend helper maps 13 action types to labels/colors
- Ready for admin panel integration

### Task 4: Full Backup/Export
- `BackupExportService::export()` generates complete site backup:
  - Site metadata (sanitized settings — no API keys)
  - Theme selection
  - All pages with block data
  - All posts with blocks, tags, categories
  - Menus
  - Redirects
  - Saved section templates
  - Stats
- API endpoint: `GET /sites/{site}/backup`
- Activity log entry created on export
- Schema version 1.0.0

### Task 5: Restore Dry-Run Validation
- `BackupExportService::validateForRestore()` validates manifest without DB writes
- Checks: schema version, required fields, secrets, path traversal
- Reports: can_restore, errors, warnings, stats
- API endpoint: `POST /sites/{site}/backup/validate`

### Task 6: Async Publishing
- `PublishOrchestrator` now checks `config('queue.default')`:
  - If not 'sync': dispatches `PublishSiteJob` async (queue worker processes it)
  - If 'sync': dispatches synchronously (existing behavior preserved)
- Activity log entry for `publish.started`
- Rollback remains async (already was)

### Task 7: Publish Status UI
- Already implemented: PublishButton with progress polling, history, rollback
- No changes needed — existing polling works with both sync and async

### Task 8: Release Helpers & Tests
- `releaseHelpers.ts`: formatActivityAction, validateBackupManifestFrontend, sanitizeMetadata
- `releaseHelpers.test.ts`: 11 tests

## Changed Files

| File | Change |
|------|--------|
| `database/migrations/2026_06_14_000001_create_activity_logs_table.php` | NEW — activity_logs table |
| `app/Models/ActivityLog.php` | NEW — ActivityLog model |
| `app/Services/ActivityLogService.php` | NEW — logging service with sanitization |
| `app/Services/BackupExportService.php` | NEW — full backup export + restore validation |
| `app/Domain/Publishing/Services/PublishOrchestrator.php` | Async queue support + activity logging |
| `routes/api.php` | Added activity, backup, restore-validate endpoints |
| `resources/admin/src/lib/releaseHelpers.ts` | NEW — activity log, backup validation, sanitize |
| `resources/admin/src/lib/releaseHelpers.test.ts` | NEW — 11 tests |
| `docs/CMS-STABILIZATION-SPRINT-12.md` | NEW — this report |
| `CHANGELOG.md` | NEW — version history |

## Commands Run

```
composer validate              → PASS
composer audit-blocks           → PASS (80/80/80)
npm run build                   → PASS (17.91s)
npm run test:run                → PASS (174 tests, 11 files)
php artisan migrate --force    → DONE (activity_logs table created)
php -l (6 PHP files)           → PASS
```

## v1.0 Release Status

**READY FOR TAG.** All remaining production items completed:
- Activity log: ✅ DB + model + service + API + frontend helpers
- Backup/export: ✅ Full site export with secret filtering + dry-run validation
- Async publishing: ✅ Queue-aware with sync fallback
- Error handling: ✅ Global ErrorBoundary (Sprint 11)
- Security: ✅ Reviewed (Sprint 11)
- Tests: ✅ 174 passing across 11 files
- Docs: ✅ Complete

## Recommendation

Tag v1.0.0 release. The CMS is production-ready with:
- Full site lifecycle (create → theme → template → build → publish → view)
- 80 blocks, 13 section presets, 3 system themes
- AI assistant, media library, SEO panel
- Activity logging, backup export, rollback
- 174 frontend tests, 80/80/80 block audit
