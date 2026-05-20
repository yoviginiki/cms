# MAG-P10 Production DTP Editor — Manual Acceptance Protocol

**Status: PARTIAL** — Initial browser testing revealed missing frame tools and 500 errors from route model binding. Fixed in MAG-P11 session. Re-testing required. See [magazine-p11-production-dtp-canvas-gap.md](magazine-p11-production-dtp-canvas-gap.md).

## 1. Purpose

This is the structured manual acceptance run for the production Magazine DTP editor pipeline completed through MAG-P6 to MAG-P9:

| Slice | Scope |
|-------|-------|
| MAG-P6 | Production DTP preflight service (16 checks, score 0–100) |
| MAG-P7 | Controlled rollout status API (legacy/dtp_beta/dtp_ready/dtp_production) |
| MAG-P7 hotfix | Four-state model, frame-only fix, capabilities, rollback docs, tests |
| MAG-P8 | Real preview render health check (DtpRenderService + Blade view) |
| MAG-P9 | DTP beta editor UX polish (status panel, issue cards, preview gating) |
| MAG-P9 hotfix | Status panel preview gated on capability + link |

This protocol verifies the full user-facing workflow in the browser and via API.

---

## 2. Preconditions

| Precondition | How to verify |
|-------------|---------------|
| master branch, clean tree | `git checkout master && git pull && git status --short` |
| Migrations current | `php artisan migrate --pretend` (no pending) |
| Admin build available | `npm run build --prefix resources/admin` succeeds |
| Authenticated admin user | Log into admin at `/admin` |
| Feature flag controllable | `.env` has `MAGAZINE_DTP_DESIGNER_ENABLED=true` (or `false` for disabled tests) |
| At least one magazine issue exists | Check Magazine List or create via AI Wizard |
| One issue WITH DTP data (if possible) | Open DTP editor and save at least one spread/page |
| One issue WITHOUT DTP data | Create issue via wizard but don't save DTP document |

---

## 3. Commands Checklist

Run before manual testing. All must pass.

```bash
cd /srv/jail/cytechno/home/cytechno/web/ensodo.eu/private/cms-platform
git checkout master
git pull origin master
git status --short           # must be clean
composer validate            # ./composer.json is valid
npm run build:vite           # ✓ built
npm run build --prefix resources/admin  # ✓ built
php artisan test --filter=Magazine      # 41 passed
php artisan test --filter=DtpRollout    # 18 passed
npm run blocks:audit         # JSON report generated
```

---

## 4. API Acceptance Checklist

Test with `curl` or browser dev tools. Replace `{site}` and `{issue}` with real UUIDs.

### 4.1 Rollout Status

```
GET /api/v1/sites/{site}/magazine-issues/{issue}/dtp-rollout
```

| Check | Expected | PASS/FAIL | Notes |
|-------|----------|-----------|-------|
| Response 200 | JSON with `data` wrapper | | |
| `data.status` | One of: `legacy`, `dtp_beta`, `dtp_ready` | | |
| `data.canOpenDtp` | Boolean matching feature flag | | |
| `data.canPromote` | Boolean (true only when ready + no blocking) | | |
| `data.hasDtpData` | Boolean matching document existence | | |
| `data.dtpStats` | Object with spreads/pages/frames counts or null | | |
| `data.preflight` | Object with status/score/counts or null | | |
| `data.blockingReasons` | Array of human-readable strings | | |
| `data.warnings` | Array of human-readable strings | | |
| `data.links.legacyEditor` | Always present, points to old editor | | |
| `data.links.dtpEditor` | Present when flag on, null when off (SPA route) | | |
| `data.links.dtpPreview` | Present when flag on + DTP data, null otherwise | | |
| `data.links.preflight` | Present when flag on, null when off | | |
| `data.capabilities.dtpFeatureEnabled` | Boolean | | |
| `data.capabilities.hasDtpDocument` | Boolean | | |
| `data.capabilities.hasSpreadOrPage` | Boolean | | |
| `data.capabilities.previewLinkAvailable` | Boolean (flag + document) | | |
| `data.capabilities.previewRenderable` | Boolean (flag + document + render service + Blade view) | | |
| `data.capabilities.legacyFallbackAvailable` | Always true | | |
| `data.capabilities.productionStatePersisted` | Always false (reserved) | | |
| `previewLinkAvailable ≠ previewRenderable` | They are separate checks | | |

### 4.2 Rollout — Feature Flag Off

Set `MAGAZINE_DTP_DESIGNER_ENABLED=false` in `.env`, clear config cache.

| Check | Expected | PASS/FAIL | Notes |
|-------|----------|-----------|-------|
| `status` | `legacy` | | |
| `canOpenDtp` | `false` | | |
| `links.dtpEditor` | `null` | | |
| `links.dtpPreview` | `null` | | |
| `capabilities.dtpFeatureEnabled` | `false` | | |
| `capabilities.previewLinkAvailable` | `false` | | |
| `capabilities.previewRenderable` | `false` | | |
| `blockingReasons` | Contains "DTP Designer feature flag is disabled." | | |

### 4.3 Preflight

```
GET /api/v1/sites/{site}/magazine-issues/{issue}/dtp-preflight
```

| Check | Expected | PASS/FAIL | Notes |
|-------|----------|-----------|-------|
| Feature flag on | 200 with preflight result | | |
| Feature flag off | 404 | | |
| `data.status` | One of: `pass`, `warning`, `error` | | |
| `data.score` | 0–100 integer | | |
| `data.counts` | Object with errors/warnings/info/blocking | | |
| `data.items` | Array of check items with human messages | | |
| Each item has `severity` | `error`, `warning`, or `info` | | |
| Each item has `message` | Human-readable string | | |

### 4.4 Preview

```
GET /api/v1/sites/{site}/magazine-issues/{issue}/dtp-preview
```

| Check | Expected | PASS/FAIL | Notes |
|-------|----------|-----------|-------|
| Feature flag on, DTP data exists | 200, HTML page renders | | |
| Feature flag off | 404 | | |
| No DTP data | Renders with "No DTP content" message | | |

---

## 5. Browser Acceptance Checklist

### 5.1 Entry Point & Visibility

| ID | Action | Expected | Actual | PASS/FAIL | Notes |
|----|--------|----------|--------|-----------|-------|
| B01 | Navigate to admin Magazine area | Magazine list loads | | | |
| B02 | Find "DTP Beta Editor" section | Section visible if issues exist | | | |
| B03 | Section hidden when no issues | `DtpIssueSection` returns null | | | |
| B04 | Old magazine cards still present | Cards with Edit/Delete/View buttons | | | |
| B05 | Click old magazine Edit | Old MagazineEditorV2 opens | | | |
| B06 | "AI compose issue" button works | Navigates to wizard | | | |

### 5.2 DTP Issue Cards

| ID | Action | Expected | Actual | PASS/FAIL | Notes |
|----|--------|----------|--------|-----------|-------|
| B07 | Issue card shows title | Issue title or "Untitled Issue" | | | |
| B08 | Issue card shows status | Capitalized status (Draft/Published) | | | |
| B09 | Rollout status badge | Ready (green), Beta (amber), or Legacy (gray) | | | |
| B10 | DTP Feature row | "Enabled" (green) or "Disabled" (red) | | | |
| B11 | Document row (with DTP data) | "X spreads, Y pages" | | | |
| B12 | Document row (no DTP data) | "Empty" | | | |
| B13 | Preview link row (available) | "Available" (green) | | | |
| B14 | Preview link row (not available) | "Not available" (gray) | | | |
| B15 | Render health row (renderable) | "Renderable" (green) | | | |
| B16 | Render health row (not renderable) | "Not renderable" (amber) | | | |
| B17 | Preflight row (pass) | "Pass (100/100)" (green) | | | |
| B18 | Preflight row (errors) | "Errors (N blocking)" (red) | | | |
| B19 | Blocking reasons text | Red text with human message | | | |

### 5.3 DTP Issue Card Actions

| ID | Action | Expected | Actual | PASS/FAIL | Notes |
|----|--------|----------|--------|-----------|-------|
| B20 | Click "Open DTP Editor" (flag on) | Navigates to DTP editor | | | |
| B21 | "Open DTP Editor" (flag off) | Button disabled | | | |
| B22 | Disabled button tooltip | Tooltip: "DTP feature flag is disabled" | | | |
| B23 | Preview icon (available) | Opens preview in new tab | | | |
| B24 | Preview icon (not available) | Icon not shown | | | |

### 5.4 DTP Beta Editor

| ID | Action | Expected | Actual | PASS/FAIL | Notes |
|----|--------|----------|--------|-----------|-------|
| B25 | Editor loads | Dark canvas with toolbar, BETA badge | | | |
| B26 | Title shown | Issue title in toolbar | | | |
| B27 | BETA badge visible | Blue "BETA" pill in toolbar | | | |
| B28 | Save button (no changes) | Grayed out, disabled | | | |
| B29 | Make edit, Save button | Blue, enabled, "Save" | | | |
| B30 | Click Save | "Saving..." then success, button grays out | | | |
| B31 | Save error (if simulatable) | Red error message in toolbar | | | |
| B32 | "Unsaved" indicator | Shows when dirty, hides after save | | | |

### 5.5 DTP Editor — Preview Button

| ID | Action | Expected | Actual | PASS/FAIL | Notes |
|----|--------|----------|--------|-----------|-------|
| B33 | Preview button (data + flag on) | Blue "Preview" link, opens new tab | | | |
| B34 | Preview button (no data) | Grayed "Preview" with tooltip | | | |
| B35 | Preview tooltip (disabled) | "Preview not available — save a DTP document first" | | | |
| B36 | Preview page renders | Dark page with spreads/pages/frames | | | |

### 5.6 DTP Editor — Status Panel

| ID | Action | Expected | Actual | PASS/FAIL | Notes |
|----|--------|----------|--------|-----------|-------|
| B37 | Click "Status" button | Status panel expands below toolbar | | | |
| B38 | Click again | Status panel collapses | | | |
| B39 | Rollout status | Ready/Beta/Legacy badge | | | |
| B40 | DTP Feature | "Enabled" or "Disabled" | | | |
| B41 | Document stats | "Saved" with counts or "Empty" | | | |
| B42 | Preview link (available) | "Available" as blue link | | | |
| B43 | Preview link (not available) | "Not available" in gray | | | |
| B44 | Render health | "Renderable" (green) or "Not renderable" (amber) | | | |
| B45 | Preflight status | Pass/Warnings/Errors with score | | | |
| B46 | Promote readiness | "Ready" or "Not ready" | | | |
| B47 | Blocking reasons | Red text when present | | | |
| B48 | Warnings | Amber text when present | | | |
| B49 | "Refresh status" button | Refetches rollout data | | | |

### 5.7 DTP Editor — Bottom Status Bar

| ID | Action | Expected | Actual | PASS/FAIL | Notes |
|----|--------|----------|--------|-----------|-------|
| B50 | Spread counter | "Spread 1/N" | | | |
| B51 | Frame counter | "N frames" | | | |
| B52 | Unsaved indicator | "Unsaved changes" in amber when dirty | | | |
| B53 | Selected frame info | Frame name and position in blue | | | |
| B54 | Zoom indicator | "Zoom 50%" | | | |
| B55 | Rollout badge | Ready/Beta/Legacy/Unknown pill | | | |

### 5.8 DTP Editor — Right Panel Tabs

| ID | Action | Expected | Actual | PASS/FAIL | Notes |
|----|--------|----------|--------|-----------|-------|
| B56 | Props tab | Properties panel for selected frame | | | |
| B57 | Layers tab | Layer list with visibility/lock | | | |
| B58 | Check tab (preflight) | Preflight results with click-to-select | | | |
| B59 | Export tab | Export readiness summary | | | |

### 5.9 Error & Edge States

| ID | Action | Expected | Actual | PASS/FAIL | Notes |
|----|--------|----------|--------|-----------|-------|
| B60 | Feature flag off, navigate to DTP editor URL | "DTP Designer Not Available" error page | | | |
| B61 | 404 error page has "Go Back" button | Returns to previous page | | | |
| B62 | Network error during load | "Failed to Load" with error message | | | |
| B63 | No rollout data loaded yet | Status panel/badge hidden until data arrives | | | |

### 5.10 Prototype vs Production Clarity

| ID | Action | Expected | Actual | PASS/FAIL | Notes |
|----|--------|----------|--------|-----------|-------|
| B64 | DTP Prototype route | `/sites/{id}/magazine/dtp-prototype` — separate from beta | | | |
| B65 | DTP Beta Editor route | `/sites/{id}/magazine-issues/{id}/dtp-editor` — clearly distinct | | | |
| B66 | Magazine list labels | "DTP Beta Editor" section with BETA pill | | | |
| B67 | Old magazine cards | Navigate to `/magazines/{id}/edit` — unchanged | | | |

---

## 6. Result Summary

| Area | Total Checks | Pass | Fail | Notes |
|------|-------------|------|------|-------|
| API — Rollout | 20 | | | | |
| API — Flag Off | 8 | | | | |
| API — Preflight | 8 | | | | |
| API — Preview | 3 | | | | |
| Browser — Entry | 6 | | | | |
| Browser — Cards | 13 | | | | |
| Browser — Card Actions | 5 | | | | |
| Browser — Editor | 8 | | | | |
| Browser — Preview Button | 4 | | | | |
| Browser — Status Panel | 13 | | | | |
| Browser — Status Bar | 6 | | | | |
| Browser — Right Panel | 4 | | | | |
| Browser — Errors | 4 | | | | |
| Browser — Clarity | 4 | | | | |
| **Total** | **106** | | | | |

---

## 7. Sign-off

| Field | Value |
|-------|-------|
| Tester | | |
| Date | | |
| Environment | | |
| Browser | | |
| Feature flag tested ON | yes / no |
| Feature flag tested OFF | yes / no |
| DTP data issue tested | yes / no |
| No-DTP-data issue tested | yes / no |
| **Result** | **PASS / PARTIAL / FAIL** |
| Blocking issues | | |
| Non-blocking issues | | |
| Next recommended slice | | |

---

## 8. Rollback Instructions

If critical issues are found:

1. Set `MAGAZINE_DTP_DESIGNER_ENABLED=false` in `.env`
2. Clear config cache: `php artisan config:clear`
3. Old magazine editor continues to work at existing routes
4. DTP data is preserved (not deleted)
5. Revert commits if code-level rollback needed

---

## 9. Known Limitations

- `dtp_production` state reserved but not returned (requires `editor_mode` column)
- No DTP status on old magazine cards (different data model)
- No inline preflight in magazine list (must open editor)
- No auto-refresh of rollout status
- Preview opens raw API HTML (no styled viewer)
- `previewRenderable` checks service resolvability + Blade view existence, not full render output
- `dtpEditor` link is a React SPA route, not a Laravel route

---

## 10. Recommended Next Slices

| Slice | Scope |
|-------|-------|
| MAG-P11 | `editor_mode` column + `dtp_production` state persistence |
| MAG-P12 | DTP status on old magazine cards (cross-model query) |
| MAG-P13 | Styled preview viewer (iframe/modal) |
| MAG-P14 | Auto-refresh rollout status + toast notifications |
| MAG-P15 | Production promotion UI |
