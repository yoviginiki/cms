# MAG-P6 DTP Preflight — Acceptance Checklist

## Endpoint
`GET /api/v1/sites/{site}/magazine-issues/{issue}/dtp-preflight`
Feature flag: `MAGAZINE_DTP_DESIGNER_ENABLED=true`

## Checks Implemented

| Code | Severity | Blocking | Rule |
|------|----------|----------|------|
| NO_PAGES | error | yes | Issue has no DTP pages |
| INVALID_PAGE_SIZE | error | yes | Page width/height < 1 |
| DUPLICATE_PAGE_INDEX | warning | no | Duplicate page indexes |
| INVALID_FRAME_TYPE | error | yes | Frame type not in FrameType enum |
| INVALID_FRAME_SIZE | error | yes | Frame width/height < 1 |
| INVALID_FRAME_POSITION | error | yes | Non-finite x/y |
| ORPHAN_FRAME | error | yes | Frame references non-existent page |
| HIDDEN_FRAME | info | no | Frame is hidden |
| EMPTY_TEXT_FRAME | warning | no | Text/quote frame with no content |
| MISSING_IMAGE | error | yes | Image frame with no src |
| UNSAFE_IMAGE_URL | error | yes | Image URL not http/https |
| MISSING_ALT_TEXT | warning | no | Image has src but no alt |
| INVALID_FIT_MODE | warning | no | fitMode not in allowlist |
| INVALID_OPACITY | warning | no | Opacity out of 0-100 |
| FRAME_OUTSIDE_PAGE | error/warning | yes (if full) | Frame outside page bounds |
| FRAME_OUTSIDE_SAFE_AREA | info | no | Frame extends beyond margins |

## Response Shape
```json
{
  "data": {
    "status": "pass | warning | error",
    "score": 85,
    "counts": { "errors": 0, "warnings": 2, "info": 1, "blocking": 0 },
    "items": [
      {
        "severity": "warning",
        "code": "EMPTY_TEXT_FRAME",
        "message": "Body Text: empty text content.",
        "frame_id": "uuid",
        "page_id": null,
        "spread_id": null,
        "blocking": false
      }
    ]
  }
}
```

## Manual Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Preflight empty issue | error: NO_PAGES |
| 2 | Preflight with missing image | error: MISSING_IMAGE (blocking) |
| 3 | Preflight with javascript: URL | error: UNSAFE_IMAGE_URL (blocking) |
| 4 | Preflight with empty text frame | warning: EMPTY_TEXT_FRAME |
| 5 | Frame completely outside page | error: FRAME_OUTSIDE_PAGE (blocking) |
| 6 | Frame partially outside page | warning: FRAME_OUTSIDE_PAGE |
| 7 | Frame beyond margins | info: FRAME_OUTSIDE_SAFE_AREA |
| 8 | Valid issue, all images placed | status: pass, score: 100 |
| 9 | Feature flag off | 404 |
| 10 | Wrong site | 404 |
| 11 | Old magazine editor | Not affected |

## Publish Enforcement
Deferred — no DTP publish route exists yet. Preflight API is available for beta editor integration.

## Limitations
- No text overflow detection (requires client-side DOM measurement)
- No image DPI/resolution checks
- No CMYK/ICC color validation
- No PDF/X checks
- No old publish pipeline replacement
