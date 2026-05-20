# MAG-P7 Controlled Rollout — Acceptance Checklist

## Endpoint
`GET /api/v1/sites/{site}/magazine-issues/{issue}/dtp-rollout`
Always available (not behind DTP feature flag — reports status even when flag is off).

## Rollout States

Four states define the rollout lifecycle. Three are computed; one is reserved for persisted promotion.

| State | Condition | Computed? |
|-------|-----------|-----------|
| legacy | Feature flag off OR no usable DTP document (no spreads/pages) | Yes |
| dtp_beta | Has DTP document but preflight has blocking errors | Yes |
| dtp_ready | Has DTP document and preflight passes (no blocking errors) | Yes |
| dtp_production | Explicit production promotion via persisted `editor_mode` field | **Reserved — requires MAG-P8** |

**Note:** `dtp_production` is not returned by the current implementation. It requires adding an `editor_mode` column to `magazine_issues` (future MAG-P8). Until then, the highest computed state is `dtp_ready`.

### DTP Document Definition
A usable DTP document requires at least one DTP **spread** or **page**. Frames alone are not sufficient — orphan frames without a page/spread container do not constitute a renderable document.

## Response Shape
```json
{
  "data": {
    "status": "legacy | dtp_beta | dtp_ready",
    "canOpenDtp": true,
    "canPromote": false,
    "hasDtpData": true,
    "dtpStats": { "spreads": 3, "pages": 5, "frames": 16 },
    "preflight": { "status": "warning", "score": 85, "counts": {...} },
    "blockingReasons": ["Preflight has blocking errors (2)."],
    "warnings": [],
    "links": {
      "legacyEditor": "/admin/sites/.../magazines/.../edit",
      "dtpEditor": "/admin/sites/.../magazine-issues/.../dtp-editor",
      "dtpPreview": "/api/v1/sites/.../magazine-issues/.../dtp-preview",
      "preflight": "/api/v1/sites/.../magazine-issues/.../dtp-preflight"
    },
    "capabilities": {
      "dtpFeatureEnabled": true,
      "hasDtpDocument": true,
      "hasSpreadOrPage": true,
      "previewLinkAvailable": true,
      "previewRenderable": true,
      "legacyFallbackAvailable": true,
      "productionStatePersisted": false
    }
  }
}
```

### Link Types
- `links.legacyEditor` — Laravel admin route (always present)
- `links.dtpEditor` — **React SPA route** rendered by `resources/admin/src/App.tsx`, not a Laravel route. Null when feature flag off.
- `links.dtpPreview` — Laravel API route (MAG-P5). Null when no DTP data.
- `links.preflight` — Laravel API route (MAG-P6). Null when feature flag off.

### Capabilities
- `previewLinkAvailable` — true when feature flag on and DTP document exists (link can be shown to the user)
- `previewRenderable` — true when the full render pipeline is available: feature flag on, DTP document exists, `DtpRenderService` resolvable, and `dtp-preview` Blade view exists. Fails closed (false if any component missing). Implemented in MAG-P8. Does NOT simply mirror `previewLinkAvailable`.
- `productionStatePersisted` — false until `editor_mode` column is added (MAG-P8)
- `legacyFallbackAvailable` — always true (legacy editor is never removed)

## Manual Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Rollout with flag OFF, no DTP data | status: legacy, canOpenDtp: false |
| 2 | Rollout with flag ON, no DTP data | status: legacy, canOpenDtp: true, warnings: "No DTP document" |
| 3 | Rollout with flag ON, frames only (no spread/page) | status: legacy, hasDtpData: false |
| 4 | Rollout with flag ON, DTP data, blocking errors | status: dtp_beta, canPromote: false |
| 5 | Rollout with flag ON, DTP data, clean preflight | status: dtp_ready, canPromote: true |
| 6 | Legacy editor link always present | links.legacyEditor set regardless |
| 7 | DTP editor link null when flag off | links.dtpEditor: null |
| 8 | DTP preview link null when no data | links.dtpPreview: null |
| 9 | Old magazine editor still opens | Not affected |
| 10 | Wrong site → 404 | Issue ownership enforced |
| 11 | capabilities.productionStatePersisted | false (until MAG-P8) |

## Promotion
Deferred — no `editor_mode` DB field. Status is fully computed from feature flag + DTP document existence + preflight result. The `dtp_production` state requires adding an `editor_mode` column to `magazine_issues` (future MAG-P8). `canPromote` indicates readiness but does not trigger promotion.

## Fallback
- Legacy editor link ALWAYS available in response
- DTP links null/hidden when feature flag off
- No forced editor switch — user chooses which editor to open
- Legacy fallback capability always reported as true

## Rollback Instructions

If the DTP beta editor causes issues, follow these steps:

1. **Disable feature flag** — set `MAGAZINE_DTP_DESIGNER_ENABLED=false` in `.env` or site config. This immediately:
   - Hides DTP editor link (`dtpEditor` → null)
   - Hides DTP preview link (`dtpPreview` → null)
   - Sets rollout status to `legacy`
   - Sets `canOpenDtp` to false
2. **Open legacy editor** — use the `legacyEditor` link (always present in rollout response)
3. **Do NOT delete DTP data** — DTP spreads/pages/frames are preserved for future re-enablement
4. **Clear production preference** — if `editor_mode` column exists (MAG-P8), reset to null/legacy
5. **Verify old editor/publish still works** — test magazine list, old editor, preview, publish pipeline
6. **Revert hotfix commit** — if code-level rollback is needed, revert the MAG-P7 commits (the rollout endpoint is non-destructive and can safely remain)

## Limitations
- No DB field for editor mode (computed only)
- No promotion persistence (no "mark as production" action) — requires MAG-P8
- `dtp_production` state reserved but not returned until MAG-P8
- No auto-migration of old issues
- No UI integration in magazine list (API-only for now)
- PDF/export not implemented
- `previewRenderable` checks render service + Blade view availability (implemented in MAG-P8)
- `dtpEditor` link is a React SPA route, not a Laravel server route
