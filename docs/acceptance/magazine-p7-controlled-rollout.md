# MAG-P7 Controlled Rollout — Acceptance Checklist

## Endpoint
`GET /api/v1/sites/{site}/magazine-issues/{issue}/dtp-rollout`
Always available (not behind DTP feature flag — reports status even when flag is off).

## Rollout States (computed, no DB field)
| State | Condition |
|-------|-----------|
| legacy | Feature flag off OR no DTP data |
| dtp_beta | Has DTP data but preflight has blocking errors |
| dtp_ready | Has DTP data and preflight passes (no blocking errors) |

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
    }
  }
}
```

## Manual Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Rollout with flag OFF, no DTP data | status: legacy, canOpenDtp: false |
| 2 | Rollout with flag ON, no DTP data | status: legacy, canOpenDtp: true, warnings: "No DTP data" |
| 3 | Rollout with flag ON, DTP data, blocking errors | status: dtp_beta, canPromote: false |
| 4 | Rollout with flag ON, DTP data, clean preflight | status: dtp_ready, canPromote: true |
| 5 | Legacy editor link always present | links.legacyEditor set regardless |
| 6 | DTP editor link null when flag off | links.dtpEditor: null |
| 7 | DTP preview link null when no data | links.dtpPreview: null |
| 8 | Old magazine editor still opens | Not affected |
| 9 | Wrong site → 404 | Issue ownership enforced |

## Promotion
Deferred — no `editor_mode` DB field. Status is fully computed from feature flag + DTP data existence + preflight result. Explicit promotion requires adding an `editor_mode` column to `magazine_issues` (future MAG-P8).

## Fallback
- Legacy editor link ALWAYS available in response
- DTP links null/hidden when feature flag off
- No forced editor switch — user chooses which editor to open

## Limitations
- No DB field for editor mode (computed only)
- No promotion persistence (no "mark as production" action)
- No auto-migration of old issues
- No UI integration in magazine list (API-only for now)
- PDF/export not implemented
