# Security Review

**Last updated:** Sprint 11 (2026-06-14)

## Authentication & Authorization

| Check | Status | Notes |
|-------|--------|-------|
| All API routes require auth | PASS | `auth:sanctum` middleware on all /api/v1 routes |
| Tenant isolation | PASS | `tenant.scope` middleware sets PostgreSQL RLS context |
| Rate limiting | PASS | Login 5/min, AI 20/min, forms 10/min, comments 60/min |
| CSRF protection | PASS | X-CSRF-TOKEN + X-XSRF-TOKEN headers on all requests |
| Session security | PASS | httpOnly cookies, secure flag in production |

## File Upload Security

| Check | Status | Notes |
|-------|--------|-------|
| File type validation | PASS | Allowlist of extensions, blocklist of dangerous types |
| MIME type validation | PASS | getimagesize() for images, MIME check for others |
| Blocked extensions | PASS | php, phtml, sh, exe, bat, etc. blocked |
| SVG sanitization | PASS | Scans for script, foreignObject, event handlers |
| File size limit | PASS | 100MB max per file |
| Deduplication | PASS | SHA256 checksum prevents duplicate storage |

## Data Exposure Prevention

| Check | Status | Notes |
|-------|--------|-------|
| No API keys in frontend | PASS | AI keys read server-side from .env |
| No filesystem paths exposed | PASS | Asset URLs use /api/v1/.../serve route |
| No database credentials in responses | PASS | Only model data returned |
| Backup/export excludes secrets | PASS | validateBackupManifest checks for passwords/keys |
| Theme export excludes secrets | PASS | validateExportManifest checks for api_key |
| Error messages safe | PASS | Production debug=OFF, no stack traces |

## Input Validation

| Check | Status | Notes |
|-------|--------|-------|
| HTML sanitization | PASS | DOMPurify in admin, strip_tags fallbacks in PHP |
| AI output sanitization | PASS | validateAiTextOutput strips script/style/handlers |
| Redirect validation | PASS | validateRedirect checks path format, loops, chars |
| Theme import validation | PASS | validateThemeManifest checks structure |
| Block data validation | PASS | BlockDefinition rules + BlockEffects::validationRules() |
| SEO fields sanitized | PASS | Length limits, no HTML in meta tags |

## Security Headers

| Header | Status | Value |
|--------|--------|-------|
| X-Content-Type-Options | PASS | nosniff |
| X-Frame-Options | PASS | DENY (SAMEORIGIN for preview/studio) |
| Referrer-Policy | PASS | strict-origin-when-cross-origin |
| Permissions-Policy | PASS | camera=(), microphone=(), geolocation=() |

## Remaining Risks (Low Priority)

1. **Preview tokens** — no visible expiry enforcement (tokens stay valid indefinitely)
2. **SSH deploy credentials** — stored in site settings (not encrypted at rest beyond DB)
3. **Custom domain path** — validated with regex but `.` allowed (mitigated by directory existence check)
4. **AI prompts** — user content sent to external API (documented, user-triggered only)
5. **SVG uploads** — sanitized but complex SVGs could potentially bypass simple checks

## Recommendations for Future

1. Add preview token expiry (e.g., 7 days)
2. Encrypt sensitive site settings at rest
3. Add Content-Security-Policy header for admin
4. Add Subresource Integrity (SRI) for vendor scripts
5. Regular dependency audit (`composer audit`, `npm audit`)
