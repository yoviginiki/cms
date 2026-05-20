# MAG-P8 Preview Render Health Check — Acceptance Checklist

## Goal
Implement a real preview render availability check so `previewRenderable` reflects actual render pipeline capability, not just preview link presence.

## Definitions

### `previewLinkAvailable`
True when:
- DTP feature flag is enabled
- DTP document exists (at least one spread or page)

Means: a preview link URL can be shown to the user.

### `previewRenderable`
True when ALL of:
- DTP feature flag is enabled
- DTP document exists (at least one spread or page)
- `DtpRenderService` is resolvable from the Laravel service container
- Blade view `dtp-preview` exists

Means: the system can actually render a preview if requested.

These are **independent checks**. `previewRenderable` is NOT derived from `previewLinkAvailable`. Both happen to share the first two conditions, but `previewRenderable` additionally verifies the render pipeline.

## Fail-Closed Behavior
If any component is missing or throws during resolution, `previewRenderable` returns `false`. No exception is propagated — the rollout report remains safe to call even if render dependencies are broken.

## Implementation

### `DtpRolloutService::canRenderPreview()`
Private method that checks:
1. Feature flag enabled
2. DTP document exists
3. `app()->make(DtpRenderService::class)` succeeds (wrapped in try/catch)
4. `view()->exists('dtp-preview')` returns true

Returns `bool`. Used to set `capabilities.previewRenderable`.

## Manual Tests

| # | Test | Expected |
|---|------|----------|
| 1 | Flag on, DTP data, render service available | previewRenderable: true |
| 2 | Flag off, DTP data present | previewRenderable: false |
| 3 | Flag on, no DTP data | previewRenderable: false |
| 4 | Flag on, DTP data, Blade view missing | previewRenderable: false |
| 5 | Flag on, DTP data, render service unresolvable | previewRenderable: false |
| 6 | previewLinkAvailable true, previewRenderable true | Both true independently |
| 7 | previewLinkAvailable false (flag off) | previewRenderable also false |

## Automated Tests
- `test_preview_renderable_true_when_render_pipeline_available`
- `test_preview_renderable_false_when_feature_flag_off`
- `test_preview_renderable_false_when_no_dtp_document`
- `test_preview_link_available_but_renderable_independent`
- `test_preview_renderable_false_when_render_service_unresolvable` — proves divergence: link=true, render=false
- `test_preview_renderable_false_when_blade_view_missing` — proves divergence: link=true, render=false

## Response Shape (capabilities section)
```json
{
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
```

## Scope
- No database changes
- No migrations
- No old editor removal
- No export/publish pipeline changes
- No frontend changes
- Service-level render health check only

## Limitations
- Does not perform a full dry-run render (no document loading/rendering)
- Checks service resolvability and view existence, not render output correctness
- Does not verify external dependencies (image CDN, fonts, etc.)
