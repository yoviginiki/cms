# Known Issues

**Last updated:** Sprint 11 (2026-06-14)

## Production Blockers

None identified. All core flows functional.

## Important But Not Blocking

| Issue | Impact | Area | Fix | Priority |
|-------|--------|------|-----|----------|
| Preview tokens no expiry | Security (low) | Client preview | Add expiry column + check | P2 |
| SSH credentials unencrypted | Security (low) | Deploy settings | Encrypt at rest | P3 |
| Publishing is synchronous | Performance | Large sites | Switch to queue dispatch | P2 |
| DependencyGraph unused | Performance | Publishing | Integrate for incremental builds | P3 |

## UX Polish

| Issue | Impact | Area | Fix | Priority |
|-------|--------|------|-----|----------|
| Vendor chunk 743KB | Load time | Admin | manualChunks config | P3 |
| No toast on block duplicate | Feedback | Builder | Add toast in duplicateBlock | P4 |
| Drag-drop lacks clear zone indicator | Usability | Builder | Better visual drop target | P3 |
| No focal point click-to-set UI | Media UX | Assets | Visual picker component | P3 |
| OG preview component not in editor | SEO UX | PageEditor | Integrate component | P3 |

## Technical Debt

| Issue | Impact | Area | Fix | Priority |
|-------|--------|------|-----|----------|
| Remaining hardcoded grays | Theme consistency | Various | Gradual DaisyUI migration | P4 |
| Activity log model missing | Audit trail | Monitoring | Create migration + service | P2 |
| Backup export not implemented | Data safety | Admin | Aggregate JSON export | P2 |
| Full page-from-brief UI missing | AI feature | Builder | Wizard component | P3 |
| No AI streaming in builder | AI UX | Builder | SSE/streaming response | P3 |

## Future Features

| Feature | Description | Priority |
|---------|-------------|----------|
| Client comments | Review feedback on preview links | P3 |
| Publish scheduling | Site-level scheduled publish | P3 |
| Webhook notifications | Publish events to external services | P4 |
| DNS validation | Custom domain DNS record check | P3 |
| Multi-site dashboard | Agency overview across sites | P3 |
| CDN integration | Edge caching for static sites | P4 |
| A/B testing | Landing page variant testing | P4 |
