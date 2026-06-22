# EXPERIENCE-MODE-FINDINGS.md
## Phase 0 — Read-Only Analysis & Feasibility Report

---

## 1. Track 0 Status: Section Block Hierarchy

**Verdict: ✅ BUILDABLE**

Top-level Section blocks ARE real structural delimiters:

- **HierarchyValidator** (`app/Support/Blocks/HierarchyValidator.php`) enforces Section → Row → Column → Module containment rules
- **BlockLevel enum** (`app/Domain/Blocks/Enums/BlockLevel.php`) defines `allowedChildLevels()` — only Section at root, Row inside Section, Column inside Row, Module inside Column
- **SyncBlocksRequest** (`app/Http/Requests/SyncBlocksRequest.php`) calls `HierarchyValidator::validate()` before saving — blocks with wrong nesting are rejected
- **BuildPageService** (`app/Domain/Publishing/Services/BuildPageService.php:163-171`) queries root blocks (`whereNull('parent_block_id')`) and renders recursively via `renderBlock()` — each Section renders its `$children`
- **section.blade.php** renders `{!! $children !!}` inside a `<section>` tag with configurable max-width, padding, background

The hierarchy is validated at request time, enforced via `parent_block_id` FK, and rendered recursively. Experience Mode can reliably identify top-level Sections as panels.

---

## 2. `editor_mode` Precedent

**Column:** `editor_mode` VARCHAR(10), default `'block'`, on both `pages` and `posts` tables.

| Aspect | Location | Detail |
|--------|----------|--------|
| Migration | `database/migrations/2026_04_17_000002_add_editor_mode_and_block_style.php` | Adds column to pages (line 12) and posts (line 16) |
| Model | `app/Models/Page.php` line 19, `app/Models/Post.php` line 21 | In `$fillable` array, no explicit cast (treated as string) |
| API Serializer | `app/Http/Resources/V1/PageResource.php` line 17 | `'editor_mode' => $this->editor_mode` |
| UI Toggle | `resources/admin/src/pages/PageEditor.tsx` line 77 | `useState<EditorMode>('block')`, controls canvas switch at line 84 |
| Values | `'block'` or `'magazine'` | Determines which canvas renders (BuilderCanvas vs MagazineCanvas) |

**Pattern to clone:** Add `experience_mode` column identically — VARCHAR, default `'standard'`, fillable, serialized in resource, toggled in page options panel.

---

## 3. ScrollPage Runtime

**File:** `resources/views/blocks/scroll_page.blade.php` (415 lines)

**Runtime stack:** Pure vanilla JS + CSS (NO external libraries):
- IntersectionObserver for scroll-triggered reveals (line 335)
- requestAnimationFrame for mouse tracking (line 409)
- CSS transforms for parallax/zoom
- SVG filters (`feTurbulence`, `feDisplacementMap`) for water/distortion effects

**Extractable primitives:**
- Scroll-reveal IntersectionObserver pattern (lines 330-344)
- Mouse tracking + requestAnimationFrame loop (lines 347-411)
- `prefers-reduced-motion` handling (lines 196-205, 332-333) — disables all effects
- Touch device fallback (lines 369-375)

**What ScrollPage does NOT have:**
- No `localStorage` reader toggle (only OS-level `prefers-reduced-motion`)
- No GSAP / Observer / ScrollTrigger
- No wheel/touch hijack or snap behavior

**Proposal for shared layer:**
Extract a `experience-shared.js` module containing:
1. `ScrollRevealObserver` — reusable IntersectionObserver factory
2. `ReducedMotionGuard` — checks `prefers-reduced-motion` + `localStorage` toggle
3. `MouseTracker` — requestAnimationFrame mouse position tracker

The GSAP-based panel snap (Observer + ScrollTrigger) is NEW — it doesn't exist in ScrollPage. ScrollPage does page-curl/water effects, not panel-to-panel navigation. The shared layer is small: motion guard + observer factory. The GSAP snap engine is additive.

---

## 4. Publish Pipeline Injection Point

**Decision point:** `BuildPageService::build()` lines 45-70

```
$headScripts  = site.settings.head_scripts + content.seo_meta.head_scripts
$bodyScripts  = site.settings.body_scripts + content.seo_meta.body_scripts  
$customCss    = site.settings.custom_css   + content.seo_meta.custom_css
$criticalCss  = buildCriticalCss($themeConfig)
$designTokens = DesignTokenGenerator::generate($site)
```

**Layout template variables** (`layout.blade.php`):
- `{!! $headScripts !!}` — line 30 (head scripts)
- `{!! $customCss !!}` — line 24 (custom CSS in `<style>`)
- `{!! $designTokensCss !!}` — line 14 (design tokens)
- `{!! $bodyScripts !!}` — line 143 (body scripts)
- `{!! $criticalCss !!}` — line 16 (critical CSS)

**Grid layout** (`grid-layout.blade.php`):
- Same variables, injected in `<style>` block (lines 13-17)
- `{!! $hookHeadScripts !!}` — line 29

**Hook system:** `HookDispatcher` provides `page_render` filter (line 231) and action hooks for head/body injection.

**Injection strategy for Experience Mode:**
In `BuildPageService::build()`, check `$content->experience_mode === 'cinematic'`:
- Add `@view-transition` CSS to `$customCss`
- Add `<script defer src="experience-runtime.[hash].js">` to `$bodyScripts`
- Add `data-experience` attributes to section blocks during render

Both layout paths (standard layout.blade.php and grid-layout.blade.php) receive these variables, so both deploy targets are covered.

---

## 5. Preview Endpoint

**Routes:**
- `GET /api/v1/sites/{site}/pages/{page}/preview` → auth required
- `GET /preview/{token}` → public, token-based (24h expiry)

**Controller:** `app/Http/Controllers/Api/V1/PreviewController.php`

**Key differences from publish:**
- Passes `isPreview=true` to `BuildPageService::build()`
- Skips `AssetPublisher::rewriteHtml()` (API serve URLs work with auth cookie)
- Sets `X-Robots-Tag: noindex`, `Cache-Control: no-store`
- Injects `postMessage` listener for live iframe updates

**Strategy for "Preview as Experience":**
Add a query param `?experience=1` to the preview route. In `PreviewController`, pass this flag to `BuildPageService` which then injects the Experience runtime regardless of `experience_mode` column — allowing preview before the mode is saved.

---

## 6. `style.layout` / Section Meta Shape

**Storage:** `blocks.style` column (JSONB, cast to array in `app/Models/Block.php` line 25)

**Current shape for standard blocks:**
```json
{
  "layout": {
    "maxWidth": "1200px",
    "minHeight": "100px", 
    "alignment": "center",
    "display": "flex",
    "flexDirection": "column",
    "overflow": "hidden"
  },
  "typography": { ... },
  "spacing": { ... },
  "visual": { ... }
}
```

**Magazine mode extensions (MagElement):**
```json
{
  "layout": {
    "position": "absolute",
    "x": 100, "y": 50,
    "width": "300px",
    "rotation": 0,
    "zIndex": 5
  }
}
```

**Read/write path:**
- **Write:** Editor store (`editorStore.ts:329-331`) extracts `__style` from block data, stores in `block.style`
- **Read:** `BuildPageService::renderBlock()` line 285: `$blockStyle = $block->style ?? $sanitizedData['__style'] ?? []`
- **Render:** `BlockStyle::buildStyle($blockStyle, ...)` converts layout properties to inline CSS

**Strategy for Experience per-section settings:**
Add to Section block's `style.layout`:
```json
{
  "layout": {
    "experienceTransition": "fade",
    "experienceEnter": "fade-up",
    "experiencePin": false
  }
}
```
No new columns. Uses existing `style` JSON path. Read in Blade via `$blockStyle['layout']['experienceTransition'] ?? 'fade'`.

---

## 7. Asset Bundling

**Admin assets:** Vite (`resources/admin/vite.config.ts`)
- Output: `public/admin-assets/`
- Manifest: `public/admin-assets/.vite/manifest.json`
- Content-hashed filenames

**Published site assets:**
- Theme CSS: referenced via `$cssFile` from theme config (line 19-21 of layout.blade.php)
- Design tokens: inline `<style>` (not a file)
- Critical CSS: inline `<style>`
- No Vite integration in published output — files are direct paths or inline

**Strategy for Experience runtime:**
- Build `experience-runtime.js` as a standalone file (not part of admin Vite)
- Place in `public/assets/experience/experience-runtime.[hash].js`
- Reference in Blade via direct path: `<script defer src="/assets/experience/experience-runtime.[hash].js"></script>`
- Content-hash via manual filename or a simple build script
- Only injected on `experience_mode === 'cinematic'` pages

---

## 8. Regression Anchor

**Verdict: ⚠️ NO GOLDEN SNAPSHOT TESTS EXIST**

- `tests/Feature/Publishing/PublishTest.php` — ALL 6 tests are stubs (`markTestIncomplete()`)
- `tests/Feature/Api/PreviewTest.php` — basic status code tests only, no HTML content assertions
- No snapshot testing infrastructure
- No HTML output regression tests

**Action required in Phase 5:**
Before any pipeline changes, capture a baseline golden snapshot of a standard page's published HTML output. Assert byte-stability against this baseline after pipeline changes.

---

## GO / NO-GO Decision

### ✅ GO — All assumptions verified

| Assumption | Status | Notes |
|-----------|--------|-------|
| §1.1 Track 0 hierarchy enforced | ✅ VERIFIED | HierarchyValidator + BlockLevel enum + SyncBlocksRequest |
| §1.2 Per-page asset injection point | ✅ VERIFIED | BuildPageService lines 45-70, both layout templates |
| §1.3 Preview endpoint reusable | ✅ VERIFIED | Same BuildPageService, can add query param |
| §1.4 `editor_mode` precedent | ✅ VERIFIED | VARCHAR column, fillable, serialized, UI toggle — clone pattern |
| §1.5 ScrollPage runtime extractable | ✅ VERIFIED | Vanilla JS, small shared layer (motion guard + observer factory) |
| §1.6 `style.layout` for per-section settings | ✅ VERIFIED | Existing JSON path, no new columns needed |

**No assumptions failed. Proceed to Phase 1.**

### Risks to flag:
1. **No regression tests** — Phase 5 must create the baseline before touching the pipeline
2. **ScrollPage has no GSAP** — the panel-snap engine is entirely new code, not an extraction
3. **No Vite integration for published assets** — Experience runtime needs its own build/hash strategy
4. **GSAP license** — free-to-use but NOT MIT; must be recorded in THIRD-PARTY/NOTICE file
