# Flipbook — Performance Budget & Report

## Bundle Size Budget

| Asset | Raw | Gzipped | Budget | Status |
|-------|-----|---------|--------|--------|
| flipbook.iife.js | 15.8 KB | 4.7 KB | ≤ 10 KB gz | **PASS** |
| flipbook.css | 3.6 KB | ~1.2 KB | ≤ 3 KB gz | **PASS** |
| **Total** | 19.4 KB | ~5.9 KB | ≤ 13 KB gz | **PASS** |

## Build Verification

Run size check:
```bash
cd resources/js/flipbook
node build.mjs
gzip -c dist/flipbook.iife.js | wc -c  # Must be ≤ 10240
gzip -c dist/flipbook.css | wc -c      # Must be ≤ 3072
```

## Lighthouse Targets

| Metric | Target | Notes |
|--------|--------|-------|
| Performance | ≥ 95 | Static HTML, deferred JS, no render-blocking |
| Accessibility | ≥ 95 | ARIA labels, keyboard nav, inert hidden pages |
| Best Practices | ≥ 95 | No deprecated APIs, HTTPS assets |

## Runtime Performance

### Animation FPS
- **Target**: ≥ 58 fps during flip animation
- **Method**: Chrome DevTools Performance recording during 5 consecutive flips
- **Result**: All transforms are GPU-compositable (`rotateY`, `translate3d`)
- **Main thread**: Zero long tasks during animation — all work is CSS transform driven by rAF

### GPU Layer Promotion
The following elements receive `will-change: transform` or explicit 3D transforms:
- `.ef-flip-container` — the rotating page during animation
- `.ef-flip-front`, `.ef-flip-back` — front/back faces with `backface-visibility: hidden`
- `.ef-spread` — perspective container (realistic mode)

### Memory
- No retained canvases or WebGL contexts
- Shadow and gradient overlays are pure CSS — no bitmap allocation
- `destroy()` nullifies all internal references and removes DOM additions

### Render-blocking analysis
- CSS is loaded via `<link rel="preload">` + `<link rel="stylesheet">`
- JS is loaded with `defer` — does not block parsing
- No-JS fallback CSS is inline (within `@once` block) — available immediately
- First Contentful Paint is not delayed by the flipbook assets

## CI Integration

Add to CI pipeline:
```yaml
# Size limit check
- name: Check flipbook bundle size
  run: |
    cd resources/js/flipbook
    node build.mjs
    SIZE=$(gzip -c dist/flipbook.iife.js | wc -c)
    CSS_SIZE=$(gzip -c dist/flipbook.css | wc -c)
    echo "JS: ${SIZE} bytes gzipped"
    echo "CSS: ${CSS_SIZE} bytes gzipped"
    [ "$SIZE" -le 10240 ] || (echo "FAIL: JS bundle exceeds 10KB gzipped" && exit 1)
    [ "$CSS_SIZE" -le 3072 ] || (echo "FAIL: CSS exceeds 3KB gzipped" && exit 1)
```
