# Changelog

## v1.0.0 (2026-06-14)

### Features
- **Site Creation Wizard** — 5-step flow: name/slug → theme → template → confirm → success
- **Theme System v1** — W3C Design Tokens, 3 system themes (Editorial, Commerce, Bare), fork/edit/activate
- **Starter Templates** — 4 templates (Blank, Blog, Portfolio, Business) with auto-page + sample post creation
- **Page Builder** — 80 blocks, drag-drop, undo/redo, inline editing, auto-save, responsive preview
- **Section Library** — 13 system presets + saved site templates
- **Media Library** — Upload with dedup, 7 image variants (WebP), alt text editing, dimensions
- **SEO** — 10-point SeoAnalyzer, meta tags, OG/Twitter cards, structured data (JSON-LD)
- **Publishing** — Full/partial publish, progress tracking, rollback, deployment history, async queue support
- **AI Assistant** — Generate, rewrite, translate, SEO suggest, vision alt text (Anthropic Claude)
- **Activity Log** — Track 13+ action types, metadata sanitization, fail-safe logging
- **Backup/Export** — Full site JSON export with secret filtering, restore dry-run validation
- **Redirects** — Source/target/status code management
- **Theme Studio** — Live iframe preview with token inspection
- **Error Boundary** — Global crash prevention with retry
- **Toast Notifications** — Success/error/info toasts replacing alert()

### Infrastructure
- Laravel 13 + PostgreSQL with Row-Level Security (multi-tenant)
- React 19 + TypeScript 5.8 + Vite 6 admin SPA
- DaisyUI 5 + TailwindCSS 4 theming
- 29 lazy-loaded routes (code splitting)
- 174 frontend tests (Vitest)
- 80/80/80 block audit (frontend/Blade/PHP definitions)
- CI pipeline (GitHub Actions)

### Security
- Auth: Sanctum + tenant RLS + rate limiting
- Upload: extension blocklist, MIME validation, SVG sanitization
- CSRF: token-based protection on all mutations
- Headers: X-Content-Type-Options, X-Frame-Options, Referrer-Policy

### Known Limitations
- Preview tokens have no expiry enforcement
- Activity log UI panel not yet integrated (API ready)
- Full restore not implemented (dry-run validation only)
- SSH deploy credentials stored unencrypted in site settings
- AI requires external API key configuration
