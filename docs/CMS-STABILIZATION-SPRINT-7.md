# CMS Stabilization Sprint 7

**Date:** 2026-06-14
**Goal:** Make publishing reliable and production-ready.

## Architecture Audit Summary

The publishing system is already mature:
- **PublishOrchestrator** → **PublishSiteJob** → **BuildPageService** → **DeployService** pipeline
- **3 deploy strategies**: Symlink (atomic swap), Rename (atomic per-file), SSH (rsync)
- **Deployment model** with status lifecycle: queued → building → deploying → live/failed
- **Rollback** via restoring previous deployment artifacts
- **OutputValidator** with Lighthouse-style checks
- **SitemapGenerator**, **RobotsGenerator**, **RssFeedGenerator** all functional
- **Preview** via authenticated and token-based routes
- **PublishButton** component with real-time progress, history, and rollback

## What Was Implemented

### Task 1: Publishing System Audit
- Full audit of SmartPublisher, BuildPageService, DeployService, deploy strategies
- Documented synchronous publish flow, file output structure, rollback behavior
- Identified that system is production-ready, needs frontend helpers not backend rewrites

### Task 2: Publish Status Model
- Already implemented via `deployments` table with status, timestamps, metadata
- Created `publishHelpers.ts` with `derivePublishStatus()` for frontend status derivation
- Supports: never, in_progress, success, failed, unpublished_changes, warnings

### Task 3: Publish Logs UI
- Already implemented in PublishButton dropdown with deployment history
- `formatPublishLog()` helper normalizes deployment data for display
- `formatDuration()` formats seconds to human-readable (45s, 2m 5s)
- History shows status, type, timestamp, page count, rollback buttons

### Task 4: Preview URL Per Site
- Already implemented: `PreviewController` with auth and token-based preview
- `BuildPageService::build(..., isPreview=true)` preserves API URLs
- Preview link visible in PageEditor toolbar and PublishButton dropdown

### Task 5: Publish Action UX
- Already implemented in PublishButton with split button (Full/Quick), progress bar, success/error
- Real-time polling every 2s via `useDeploymentStatus` hook
- Progress: "Building 3/5 (60%)" → "Deploying..." → "Published!"
- Auto-dismiss success after 5s
- No alert() used

### Task 6: Rollback Foundation
- Already implemented: `POST /sites/{site}/deployments/{id}/rollback`
- Symlink strategy: restores previous symlink target
- Rename strategy: copies backup files from rollback directory
- Creates new Deployment record (type: rollback)
- UI: rollback buttons on live deployments in history

### Task 7: Publish Verification Checks
- `OutputValidator` already runs Lighthouse-style checks during publish
- Created `generateVerificationChecklist()` for frontend display
- Checks: pages generated, HTML validation, sitemap, robots.txt, RSS feed

### Task 8: Broken Media/Link Checker
- `OutputValidator` already checks image attributes, link presence
- `AssetPublisher::rewriteHtml()` rewrites API URLs to static paths
- Full broken link crawler deferred to Sprint 8 (requires file system access)

### Task 9: Sitemap/Robots/RSS Validation
- All three generators functional and integrated into PublishSiteJob
- Verification checklist includes status for each
- No issues found in current implementation

### Task 10: Custom Domain Checks
- Created `validateDomainFormat()` with 5 validation rules
- Tests cover valid domains, subdomains, empty, no dot, leading hyphen
- DNS lookup deferred to Sprint 8 (requires network access)

### Task 11: Deployment Health Screen
- PublishButton dropdown already serves as deployment health panel
- Shows: last status, timestamp, page count, rollback availability
- Preview and live site links available
- Verification checklist helper ready for integration

### Task 12: Tests
- `publishHelpers.test.ts`: 18 tests for status derivation, log formatting, duration, domain validation, verification checklist
- Total: 71 tests across 7 files, all passing

### Task 13: Documentation
- Updated `docs/PUBLISHING.md` with Sprint 7 additions
- Created `docs/CMS-STABILIZATION-SPRINT-7.md` (this report)

## Changed Files

| File | Change |
|------|--------|
| `resources/admin/src/lib/publishHelpers.ts` | NEW — status derivation, log formatting, domain validation, verification checklist |
| `resources/admin/src/lib/publishHelpers.test.ts` | NEW — 18 tests |
| `docs/PUBLISHING.md` | Updated — Sprint 7 additions, status model, verification, limitations |
| `docs/CMS-STABILIZATION-SPRINT-7.md` | NEW — this report |

## Commands Run

```
composer validate              → PASS
composer audit-blocks           → PASS (80/80/80)
npm run build                   → PASS
npm run test:run                → PASS (71 tests, 7 files)
```

## Current Limitations

1. Publishing is synchronous (dispatchSync) — blocks for up to 5 min
2. DependencyGraph exists but not integrated — all publishes are full rebuilds
3. No webhook/email notifications on publish completion
4. SSH deploy strategy has no rollback
5. Broken link crawler not yet implemented (only OutputValidator checks)
6. DNS lookup for custom domains not yet implemented

## Recommendation for Sprint 8

1. **Async publishing** — switch to queue-based dispatch for large sites
2. **Incremental publishing** — integrate DependencyGraph for partial rebuilds
3. **Broken link crawler** — post-publish file system scan for dead links
4. **DNS validation** — check custom domain DNS records
5. **Publish notifications** — webhook or email on publish events
6. **Publish scheduling** — schedule site-level publishes for specific times
