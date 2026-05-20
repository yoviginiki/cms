# MAG-P9 DTP Beta Editor UX Polish — Acceptance Checklist

## Purpose
Make the production DTP pipeline (MAG-P3 through P8) testable by a real user without reading code. Add UI entry points, status visibility, and human-readable labels.

## UX Audit Findings (Before P9)

| Area | Before P9 | Issue |
|------|-----------|-------|
| Entry point | No link to DTP editor from Magazine List | User must type URL manually |
| Rollout status | Not visible anywhere in UI | No way to know if DTP is enabled |
| Preview link | Not shown in UI | User can't discover preview |
| Render health | Not shown in UI | No visibility into render capability |
| Preflight | Shown inside DTP editor (prototype panel) | Not visible from list |
| Save/load | Shown in DTP editor toolbar | OK — has save button + dirty state |
| API errors | 404 handled, save errors shown | OK |
| Prototype vs production | BETA badge in editor | No confusion for users who reach it |
| Empty states | No issues: section hidden; No DTP data: "Empty" shown; No rollout: loading | Handled gracefully |

## P0 Polish Implemented

### 1. API Client (api.ts)
- Added `dtpDesigner.getRolloutStatus()` — calls `/dtp-rollout`
- Added `dtpDesigner.runPreflight()` — calls `/dtp-preflight`

### 2. Magazine List — DTP Beta Editor Section
New `DtpIssueSection` component showing all issue composer issues with:
- Issue title and status
- Rollout status badge (Ready / Beta / Legacy)
- DTP Feature: Enabled / Disabled
- Document: spread/page/frame counts or "Empty"
- Preview link: Available / Not available
- Render health: Renderable / Not renderable
- Preflight: Pass / Warnings / Errors with score
- Blocking reasons in red
- **Open DTP Editor** button (disabled when feature flag off, with tooltip)
- **Preview** icon button (shown when preview link available)

### 3. DTP Editor — Status Panel
New collapsible status panel in toolbar:
- Toggle via **Status** button (with chevron indicator)
- Shows rollout status, DTP feature, document stats, preview link, render health, preflight, promotion readiness, blocking reasons, warnings
- **Preview** button in toolbar (shown when preview link available)
- Quick rollout badge in bottom status bar

### 4. Human-Readable Label Mapping

| Technical field | UI label |
|----------------|----------|
| `status: legacy` | Legacy |
| `status: dtp_beta` | Beta |
| `status: dtp_ready` | Ready |
| `capabilities.dtpFeatureEnabled` | DTP Feature: Enabled / Disabled |
| `capabilities.hasDtpDocument` | Document: Saved / Empty |
| `capabilities.previewLinkAvailable` | Preview link: Available / Not available |
| `capabilities.previewRenderable` | Render health: Renderable / Not renderable |
| `preflight.status: pass` | Preflight: Pass |
| `preflight.status: warning` | Preflight: Warnings (count) |
| `preflight.status: error` | Preflight: Errors (blocking count) |
| `canPromote` | Promote: Ready / Not ready |
| `blockingReasons` | Blocked: (human message) |

## Manual Acceptance Checklist

| # | Test | Expected |
|---|------|----------|
| 1 | Navigate to Magazine List | DTP Beta Editor section visible if issues exist |
| 2 | Issue card shows rollout status | Status badge (Ready/Beta/Legacy) visible |
| 3 | Issue card shows DTP Feature enabled/disabled | Green "Enabled" or red "Disabled" |
| 4 | Issue card shows document stats | Spread/page counts or "Empty" |
| 5 | Issue card shows preview link availability | "Available" or "Not available" |
| 6 | Issue card shows render health | "Renderable" or "Not renderable" |
| 7 | Click "Open DTP Editor" when enabled | Opens DTP beta editor |
| 8 | "Open DTP Editor" disabled when flag off | Button disabled, tooltip explains |
| 9 | Preview icon opens preview in new tab | Preview renders if data exists |
| 10 | Open DTP editor, click Status button | Status panel opens with all fields |
| 11 | Status panel shows rollout, feature, document, preview, render, preflight | All labels human-readable |
| 12 | Status panel shows blocking reasons in red | When preflight has errors |
| 13 | Preview button in toolbar opens preview | New tab with rendered preview |
| 14 | Bottom status bar shows rollout badge | Ready/Beta/Legacy indicator |
| 15 | Refresh Status button refetches rollout | Data updates |
| 16 | Old magazine editor still works | Navigate to old editor, unchanged |
| 17 | Feature flag off hides DTP links | dtpEditor null, button disabled |
| 18 | No issues exist | DTP Beta Editor section hidden |
| 19 | Issue has no DTP data | Document shows "Empty", preview disabled with tooltip |
| 20 | Preview disabled tooltip | "Preview not available — save a DTP document first" |
| 21 | Rollout data not yet loaded | Status panel shows nothing until data arrives |
| 22 | Render health absent (no rollout data) | Render health not shown, no false claim |

## Scope
- Frontend only (React components)
- API client additions (fetch methods only)
- No backend changes
- No database changes
- No render/preflight/rollout semantic changes

## Known Limitations
- No DTP status shown on old magazine cards (they use different data model)
- No inline preflight panel in magazine list (must open DTP editor)
- No auto-refresh of rollout status
- No toast notifications for status changes
- Preview link opens raw API HTML (no styled viewer)

## MAG-P10 Candidates
- Inline preflight summary in magazine list cards
- Auto-refresh rollout status via polling
- Toast notifications for save/preflight status changes
- Styled preview viewer (iframe or modal)
- DTP status in old magazine editor if data models merge
- Production promotion UI (requires `editor_mode` column)
