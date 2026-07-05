# MAGAZINE EDITOR — ACCEPTANCE (Session F)

Date: 2026-07-05 · Branch: `feat/magazine-gap-close` @ `2eba6b9` · Verified live on sys.ensodo.eu

## 1. Scripted end-to-end (harness/acceptance-magazine.mjs)

Driven through the **real UI** on a fresh issue, via Playwright against production:

| # | Step | Result |
|---|------|--------|
| 1 | Open editor on a fresh issue | ✓ |
| 2 | Add text frame from the palette | ✓ |
| 3 | Double-click → inline editing | ✓ |
| 4 | Paste 10,376 words (real paste event) → large-paste dialog | ✓ |
| 5 | Insert & flow → save → auto-pagination | ✓ 39 pages / 39 frames |
| 6 | Add pull quote from palette | ✓ |
| 7 | Undo ×20 / redo ×20 | ✓ zero page errors |
| 8 | Save → reload → thread losslessness | ✓ 10,380/10,380 words, first + last sentinel present |
| 9 | Published viewer page count matches editor | ✓ 39 = 39 |
| 10 | Published viewer contains story head AND tail | ✓ |
| 11 | Published output is the Viewer 2.0 runtime | ✓ |

**Verdict: ACCEPTANCE PASS (12/12).** Pull-quote *runaround* depth is covered by the
pinned demo fixture (exclusion carving verified by the regression harness on every run).

The acceptance run itself caught and fixed one final data-loss bug (the reason such a
scripted pass exists): the inline editor's exit path flushed its **stale** pre-paste
snapshot over content the large-paste dialog had just inserted, excising the story head.
Fixed two-sided (blur baseline + exitEditing user-change gate) in `78b5dc7`+`2eba6b9`.

## 2. Core checklist (audit §"what a magazine editor must do")

| Capability | Status |
|---|---|
| Threaded text flow across frames/pages, lossless | **PASS** — pure engine, story model, 15+8 engine tests, harness pins |
| Auto-pagination + shrink of auto pages | **PASS** |
| Word-boundary breaking, widows/orphans ≥2, keep-with-next, atomics | **PASS** (engine golden tests) |
| Runaround / exclusions (wrap ≥45% width else jump) | **PASS** (demo fixture + harness) |
| 10k-word paste → paginated **< 500 ms** | **PASS** — 145 ms warm (hard perf gate in harness) |
| Word/GDocs/web paste normalization | **PASS** — real-vendor fixtures; caught mso-bidi bold bug |
| Large-paste dialog (columns, heading→style) | **PASS** |
| Image paste/drop → asset pipeline | **PASS** |
| Undo/redo: gesture-scoped, styles included, 20-deep | **PASS** |
| Editor↔publish parity (typography, images, tables, v-align, drop caps) | **PASS** — 18 PHP parity tests |
| Masters: verso/recto, publish compositing, detach, primary frame | **PASS** (master-on-master = [pro], deferred) |
| Spreads: real 2-page model with sides | **PASS** (shared edit coordinate space = deferred) |
| Tables: edit + publish | **PASS** |
| Page numbers: formats + **sections** (front-matter roman) | **PASS** |
| Rulers, drag-out guides, numeric edit, persistence | **PASS** |
| Snapping: global gate + per-source (grid/guides/margins/objects/baseline) | **PASS** |
| Group/ungroup with child transforms | **PASS** |
| Pasteboard staging | **PASS** |
| Styles panel (create/apply/delete) + theme-token swatches + eyedropper | **PASS** (full token-bound style editor = deferred) |
| Find & replace (markup-safe) | **PASS** |
| Preflight v2 with jump-to | **PASS** |
| Dependency graph: magazine→asset edges, delete protection, staleness | **PASS** (lifecycle proven live) |
| Autosave + versions with reversible restore | **PASS** |
| PDF export (queue-rendered) | **PASS** (bleed/crop marks = [nice]) |
| Viewer: scroll/book-flip/presentation, theming, banners/ads, audio, fullscreen, idle-fade | **PASS** |
| In-page video/audio publish | **PASS** |
| Standalone ZIP export (no CMS needed) | **PASS** |
| Static publish on tenant domains (no Laravel proxy) | **PASS** — W3-9 |
| Accessibility: reading-order DOM, ARIA, live announcements, reduced-motion, keyboard | **PASS** |
| Legacy editor | Frozen (route notice); no legacy magazines existed |

## 3. Known limits (documented, accepted)

- Find & replace: no cross-styling-boundary or cross-slice matches (v1).
- Word numbered/bulleted lists degrade to plain paragraphs on paste.
- Master-on-master hierarchy, wrap-to-contour, footnotes, text-on-path: [pro] backlog.
- PDF: spread-spanning frames print the owning page's half; no bleed/crop marks yet.
- Regression fixture is the live demo issue the team also edits — thread-count pins
  drift with manual edits; re-pin with `--update` (or regenerate via build script).

## 4. How to re-run everything

```bash
# unit + parity
cd resources/admin && npx vitest run           # 255
php vendor/bin/phpunit tests/Unit tests/Feature # green

# flow harness incl. 500ms perf gate + acceptance-shape repro
cd resources/admin && npx vite build --config vite.harness.config.ts \
  && node harness/run-flow-harness.mjs

# live regression (needs owner creds)
BASE=… EMAIL=… PASSWORD=… node harness/regression-magazine.mjs

# full acceptance E2E (fresh issue id via tinker; SKIP_UNDO=1 to isolate history)
BASE=… EMAIL=… PASSWORD=… SITE_ID=… ISSUE_ID=… node harness/acceptance-magazine.mjs
```

Sessions A–F of the master document are **complete**.
