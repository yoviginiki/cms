# CMS Stabilization Sprint 2

**Date:** 2026-06-14

## What was implemented

### Task 1: Theme Gallery Polish
- ThemeCard component upgraded with DaisyUI tokens
- Screenshot placeholder added
- Badges for active/system/modes using DaisyUI `badge`
- All hardcoded gray colors replaced in ThemeEngine.tsx

### Task 2: Theme Manifest Format
- Created `docs/THEME-MANIFEST.md` documenting the manifest schema
- Fields: name, slug, version, author, description, tokens, templates, blockPresets
- Documented 2 starter theme profiles (Minimal Clean, Editorial Magazine)

### Task 3: Site Creation Wizard
- 3-step modal wizard replaces `window.prompt`
- Step 1: Site name + auto-generated slug
- Step 2: Template selection (Blank, Blog, Portfolio)
- Step 3: Confirmation + create
- All DaisyUI tokens, no hardcoded colors
- Error handling with clear messages

### Task 4: Admin Contrast Cleanup (continued)
- Menus.tsx: 7 hardcoded colors replaced
- Templates.tsx: 19 hardcoded colors replaced
- ThemeEngine.tsx: ~25 hardcoded colors replaced (Task 1)
- Sprint 1 + Sprint 2 total: ~130 hardcoded colors fixed

### Task 5: Vitest Foundation
- Added vitest 4.1, @testing-library/react, jsdom
- Added `npm run test` and `npm run test:run` scripts
- Vitest config in vite.config.ts with jsdom environment
- 2 test files, 18 tests:
  - `blockEffects.test.ts` — normalize, filter CSS, reveal, labels
  - `blockStyles.test.ts` — safeDim, safeColor validation

### Task 6: CI Pipeline
- Created `.github/workflows/ci.yml`
- Steps: composer validate, npm ci, npm run build, npm run test:run, block audit
- Runs on push to master and PRs

### Task 7: Code Splitting
- Converted 28 page imports from static to React.lazy()
- Added Suspense wrapper with loading spinner
- **Bundle reduction: 2,091 KB → 757 KB initial load (64% smaller)**
- Individual pages load on demand (3-65 KB each)

## Changed files (13)

| File | Change |
|------|--------|
| `resources/admin/src/App.tsx` | React.lazy code splitting for 28 pages |
| `resources/admin/src/pages/Dashboard.tsx` | Site creation wizard |
| `resources/admin/src/pages/ThemeEngine.tsx` | DaisyUI tokens + screenshot placeholder |
| `resources/admin/src/pages/Menus.tsx` | Color cleanup |
| `resources/admin/src/pages/Templates.tsx` | Color cleanup |
| `resources/admin/src/components/layout/AdminLayout.tsx` | Nav split main/advanced |
| `resources/admin/package.json` | Vitest + test scripts |
| `resources/admin/vite.config.ts` | Vitest config |
| `resources/admin/src/test/setup.ts` | Test setup (new) |
| `resources/admin/src/lib/blockEffects.test.ts` | Tests (new) |
| `resources/admin/src/lib/blockStyles.test.ts` | Tests (new) |
| `.github/workflows/ci.yml` | CI pipeline (new) |
| `docs/THEME-MANIFEST.md` | Theme manifest format (new) |

## Commands run

```
composer validate                    → PASS
composer audit-blocks                → PASS (80/80/81)
npm run build                        → PASS (16.50s, 0 errors)
npm run test:run                     → PASS (18 tests, 2.17s)
```

## Bundle size (before/after)

| Metric | Before | After | Change |
|--------|--------|-------|--------|
| Initial JS | 2,091 KB | 757 KB | **-64%** |
| Gzip initial | 550 KB | 200 KB | **-64%** |
| Lazy chunks | 1 (21 KB) | 87 chunks | On-demand |

## Remaining issues

1. **~2500 hardcoded colors** — 130 fixed so far, many remain
2. **No PHP tests in CI** — requires PostgreSQL service
3. **Template application** — wizard creates site but doesn't apply template content yet
4. **Theme screenshots** — placeholder shown, no real screenshots
5. **No E2E tests** — Playwright/Cypress not configured

## Recommended Sprint 3

1. **Template content application** — auto-create pages/posts when user picks Blog/Portfolio
2. **Theme screenshots** — generate or capture preview images
3. **More frontend tests** — component tests for editor, store tests
4. **PHP tests in CI** — add PostgreSQL service to GitHub Actions
5. **Accessibility audit** — ARIA labels, focus management, keyboard nav
6. **Continue color cleanup** — remaining pages (PageEditor, SiteSettings, PostEditor)
