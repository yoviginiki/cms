# Site Wizard — a complete website from an existing design

The Site Wizard is the integrated "website creator": give it a design that
already exists — a live website URL, or an uploaded ZIP of exported HTML/CSS
(e.g. a Canva website export) — and it builds a **complete, native site**:

- a **Site** record (named from the design),
- a **Theme** as a real token document, read deterministically from the
  design's computed styles (colors, fonts, radii, shadows, spacing) and set
  as the site's active theme,
- every discovered page as a **draft Page** with a native
  section → row → column → module **block tree** (no raw HTML dumps —
  everything is editable in the page builder),
- a **header Menu** built from the design's navigation, bound to the created
  pages,
- the **homepage** assignment (`settings.homepage_id`),
- referenced images imported into the **media library** (WebP variants,
  dedupe), rewritten to asset serve URLs.

Review the result, then **Create website** (publishes all pages) or
**Discard** (deletes the whole site again).

## Where

- SPA: **Dashboard → "Import a website"**, or `/site-wizard`.
- API: `POST /api/v1/site-wizard/sessions/from-url` `{url, name?, max_pages?}`,
  `POST /api/v1/site-wizard/sessions/from-zip` (multipart `file`, `name?`),
  `GET /api/v1/site-wizard/sessions/{id}` (poll),
  `POST …/accept | …/abandon | …/retry`.

## How it works

```
ingest → create_site → theme → polish* → pages (3/job) → menu → finalize
```

One step (or one 3-page batch) per `BuildSiteJob` invocation on the queue —
the job re-dispatches itself, so any single run stays inside the queue
timeout and the SPA gets granular progress. A failed build is **resumable**
(`retry` re-runs only the failed step). A single unreadable page never sinks
the build — it is marked failed and the site continues.

- **URL mode** crawls same-origin pages breadth-first (entry → nav links →
  body links, depth ≤ 2, capped at `max_pages`, default 15 / max 20). Every
  fetched URL passes the SSRF guard.
- **ZIP mode** extracts entry-by-entry with zip-slip/symlink/extension/size
  guards, then renders each HTML file through a throwaway loopback static
  server so real CSS and geometry apply (external network blocked).
- **Extraction** is `scripts/import-site-page.mjs` (headless Chromium, NO AI):
  the shared page extractor from the Page Wizard plus nav anchors, the link
  frontier, and computed-style theme signals.
- **Theme** is `StyleProfileMapper`: deterministic signals → TokenProfile →
  `TokenProfileCompiler` (same pipeline as the Theme Wizard). A bounded
  repair loop guarantees the profile passes `TokenProfileValidator` even for
  hostile palettes (white-on-white, etc.).
- **`polish`*** is optional and flag-gated (`SITE_WIZARD_AI_POLISH=true` +
  a credited `ANTHROPIC_API_KEY`): one vision pass over a screenshot of the
  reference refines the theme. Any failure marks the step skipped — the
  deterministic theme is always the committed fallback. Everything else in
  the pipeline is AI-free.

## Config (`config/cms.php` → `cms.site_wizard`)

| key | env | default |
|---|---|---|
| max_pages | SITE_WIZARD_MAX_PAGES | 15 (hard max 20) |
| zip_max_mb | SITE_WIZARD_ZIP_MAX_MB | 100 |
| zip_max_files | SITE_WIZARD_ZIP_MAX_FILES | 5000 |
| zip_max_uncompressed_mb | SITE_WIZARD_ZIP_MAX_UNCOMPRESSED_MB | 250 |
| max_images | SITE_WIZARD_MAX_IMAGES | 60 |
| ai_polish | SITE_WIZARD_AI_POLISH | false |

## Ops

- The queue worker shells out to Playwright Chromium (same requirement as the
  Theme/Page wizards) — the worker user needs its own browsers
  (`npx playwright install chromium`).
- `BuildSiteJob` timeout is 180s → run workers with `--timeout=200` or more.
- Workspaces live under `storage/app/site-wizard/{session}`; accept/abandon
  clean up after themselves and `site-wizard:prune` (scheduled daily) sweeps
  anything interrupted builds leave behind.
