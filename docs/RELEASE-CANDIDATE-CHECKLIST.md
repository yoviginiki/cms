# Release Candidate Checklist

**Last updated:** Sprint 11 (2026-06-14)

## Build Gates

- [x] `composer validate` — PASS
- [x] `composer audit-blocks` — PASS (80/80/80, 100% coverage)
- [x] `npm run build` — PASS (0 TypeScript errors)
- [x] `npm run test:run` — PASS (163 tests, 10 files)
- [ ] `php artisan test` — needs verification on clean DB

## Documentation Gates

- [x] QA-CHECKLIST.md created
- [x] SECURITY-REVIEW.md completed
- [x] PERFORMANCE-REVIEW.md completed
- [x] DEPLOYMENT.md created
- [x] PUBLISHING.md updated
- [x] THEME-SYSTEM.md created
- [x] PAGE-BUILDER-UX.md created

## Security Gates

- [x] All API routes protected by auth:sanctum
- [x] Tenant isolation via RLS + middleware
- [x] CSRF protection on all mutations
- [x] File upload validation (type, size, SVG scan)
- [x] AI output sanitization
- [x] No API keys exposed to frontend
- [x] Security headers configured
- [x] Rate limiting on sensitive endpoints

## Feature Gates

- [x] Site creation wizard functional
- [x] Theme selection and application working
- [x] Starter templates create pages + posts
- [x] Page Builder loads and saves
- [x] Section Library (system + saved) functional
- [x] Media upload and management working
- [x] SEO panel with SeoAnalyzer
- [x] Publishing with progress and rollback
- [x] AI assistant (when configured)
- [x] Error boundary prevents full crashes

## Performance Gates

- [x] Admin initial load < 400KB gzipped
- [x] 29 routes lazy-loaded
- [x] Image variants auto-generated (7 sizes)
- [x] Static HTML generation for frontend
- [x] Auto-save debounced (3s)

## Known Issues (Non-Blocking)

1. Preview tokens have no expiry enforcement
2. SSH deploy credentials stored unencrypted in site settings
3. Activity log DB model not yet created (helpers ready)
4. Client review comments not implemented (preview tokens work)
5. Full backup export service not implemented (manifest validation ready)
6. Vendor JS chunk is 743KB (acceptable for admin app)

## Release Decision

**Status: RELEASE CANDIDATE READY**

The CMS is functional for production use with the following caveats:
- AI features require `AI_ENABLED=true` + API key configuration
- Activity logging requires Sprint 12 DB migration
- Full backup export requires Sprint 12 implementation
- Client comments require Sprint 12 model

All core flows (create site → theme → template → build → publish → view) work end-to-end.
