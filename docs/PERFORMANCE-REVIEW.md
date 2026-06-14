# Performance Review

**Last updated:** Sprint 11 (2026-06-14)

## Admin Bundle Analysis

| Chunk | Size | Gzipped | Purpose |
|-------|------|---------|---------|
| index (vendor) | 743 KB | ~200 KB | React, react-query, dnd-kit, lucide, shared libs |
| index (app) | 372 KB | ~118 KB | App shell, routes, shared components |
| ImagePanel | 155 KB | ~43 KB | Heavy image editing panel |
| BlockSettings | 99 KB | ~24 KB | Block settings panels |
| DtpPrototypeShell | 65 KB | ~16 KB | Magazine prototype |
| DtpEditorBeta | 56 KB | ~16 KB | Magazine editor |
| PageEditor | 53 KB | ~14 KB | Page builder |
| GridEditor | 52 KB | ~13 KB | Grid editor |
| SiteSettings | 51 KB | ~11 KB | Site settings |

**Total initial load:** ~1.1 MB raw, ~318 KB gzipped (vendor + app chunks)

## Code Splitting Status

- **29 React.lazy() routes** — all non-critical pages lazy-loaded
- **Eagerly loaded:** Dashboard, Login only
- **Split pages:** PageEditor, PostEditor, ThemeEngine, ThemeEditor, ThemeStudio, Assets, Categories, Tags, etc.
- **Vendor chunk:** Cannot be split further without manual chunk configuration

## Current Optimizations

| Optimization | Status |
|-------------|--------|
| Route-level code splitting | YES (29 lazy routes) |
| Tree shaking | YES (Vite/Rollup) |
| CSS purging | YES (Tailwind + DaisyUI) |
| Image variants (7 sizes) | YES (thumb, small, medium, large, WebP) |
| Auto-save debounce (3s) | YES |
| React Query caching | YES (staleTime configured) |
| Undo state sessionStorage | YES (limited to 10 steps) |
| Asset deduplication (SHA256) | YES |

## Generated Frontend Performance

| Metric | Status |
|--------|--------|
| Static HTML files | YES (no server rendering needed) |
| Theme CSS compiled | YES (single CSS file per site) |
| Images with responsive variants | YES (srcset in Blade) |
| Image lazy loading | YES (loading="lazy" in Blade) |
| CSS above-fold optimization | YES (criticalCss injection) |
| Minified HTML output | YES (BuildPageService minifies) |
| Sitemap for SEO crawling | YES |

## Improvement Opportunities (Not Blocking)

1. **Vendor chunk** (743 KB) — could split react-query and dnd-kit into separate chunks via manualChunks
2. **ImagePanel** (155 KB) — only loaded when editing image-heavy blocks, already lazy
3. **Font loading** — Google Fonts imported in theme CSS (render-blocking unless async)
4. **Admin TTI** — ~318 KB gzipped initial load is acceptable for an admin app
5. **CDN** — static assets could benefit from CDN headers (future infrastructure)

## Conclusion

Performance is acceptable for a CMS admin application. The 29-route code splitting keeps initial load manageable. Generated frontend is fully static with optimized images and minified HTML. No critical performance issues blocking release.
