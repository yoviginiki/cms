# CINEMATIC-LAYOUT-FINDINGS.md
## Phase 0 — Read-Only Analysis & Reconciliation

---

## 1. Reconciliation: Prior `experience_mode` Work

### What exists

| Artifact | Path | Status |
|----------|------|--------|
| Migration | `2026_06_23_000001_add_experience_mode_to_pages_and_posts.php` | Live on production |
| Column `pages.experience_mode` | VARCHAR(20), default `'standard'` | Live |
| Column `posts.experience_mode` | VARCHAR(20), default `'standard'` | Live |
| Model fillable + $attributes | `Page.php`, `Post.php` | `experience_mode` in both |
| Validation | `UpdatePageRequest`, `UpdatePostRequest` | `in:standard,cinematic` |
| API serializer | `PageResource`, `PostResource` | Exposes `experience_mode` |
| Runtime source | `resources/js/experience-runtime.js` (10KB) | GSAP 3.15 + Observer + ScrollTrigger |
| Runtime CSS | `resources/js/experience-runtime.css` (1.9KB) | Nav dots, skip button, reduced-motion |
| Built bundle | `public/assets/experience/experience-runtime.js` (117KB) | GSAP bundled |
| PreviewController injection | Lines 178-185 | Injects when cinematic or `?experience=1` |
| DynamicSiteController injection | Lines 117-123 | Same |
| BuildPageService injection | Lines 59-66 | `@view-transition` CSS + runtime JS/CSS |
| Section blade | `data-experience-transition/enter/pin` attributes | Rendered from block data |
| Section editor | Editor.tsx — Experience Panel | Transition + Enter + Pin dropdowns |
| Section definition | `experienceTransition`, `experienceEnter`, `experiencePin` | In defaultData + validation |
| Page settings toggle | PageEditor.tsx | Standard/Cinematic dropdown |
| Preview button | PageEditor.tsx | "Experience" button when cinematic |
| Tests | `ExperienceModeTest.php` (11 tests) + `ExperienceModePublishTest.php` (7 tests) | 18 tests, 31 assertions — ALL PASS |
| Baseline fixture | `tests/fixtures/standard-page-baseline.html` | Captured |
| GSAP license | `THIRD-PARTY-LICENSES.md` | Recorded |
| wabisabi3 page | `experience_mode = 'cinematic'` | Live, runtime injected |

### Decision: MIGRATE, not replace

The prior build is solid infrastructure. The new prompt's "scene preset" model is an **evolution** of the existing transition presets, not a competing system. The reconciliation:

1. **Keep `experience_mode` column** — it works. The prompt suggests using `layout` field instead, but `layout_id` is a FK to the `layouts` table (UUID), not an enum. Adding 'cinematic' to layouts requires creating a DB row + wrapper blade + grid config. The `experience_mode` column is simpler, already validated, already in the API, already in the UI. **Using it is the right call.**

2. **Rename Section data keys** — change `experienceTransition` → `scene` to match the new preset model. Add the 5 scene presets as values. The old transition values (`fade`, `slide-up`, etc.) become internal implementation details of the scene presets.

3. **Extend the runtime** — replace the simple transition/enter logic with GSAP ScrollTrigger timelines per scene preset. The runtime shell (guards, panel detection, nav dots, skip button) stays.

4. **Add atmosphere** — new page-level meta fields for preloader, cursor, sound.

**No column to retire. No second system. Clean evolution.**

---

## 2. Layout System

**Mechanism:** `pages.layout_id` is a UUID FK to the `layouts` table. Layouts have `slug`, `name`, `wrapper_blade_view`, `config` JSON. The `BuildPageService` resolves layout via `LayoutResolver`, then either uses the layout's wrapper blade or falls back to standard grid rendering.

**Existing layouts** (from prior work): standard, full-bleed, bare, landing, longform, gallery, podcast (7 total — stored as rows in the `layouts` table, not as an enum).

**Why NOT use layout for cinematic:** Adding a "cinematic" layout row would require a wrapper blade view, grid config, and would tie the cinematic behavior to a specific layout structure. But cinematic mode is a **behavioral overlay** — it should work with any page structure (full-bleed, grid, etc). The `experience_mode` column is orthogonal to layout, which is correct.

**Verdict:** Keep `experience_mode` as the flag. Don't touch layouts.

---

## 3. Track 0 — Section Hierarchy

**VERIFIED TRUE.** (Confirmed twice now.)

- `HierarchyValidator` enforces Section → Row → Column → Module
- `section.blade.php` renders as `<section class="section-block">` with `{!! $children !!}`
- Runtime finds panels via `.section-block` or `.pos-main` children (grid layout fallback)

---

## 4. Shared Motion-Runtime Convergence

### Inventory

| Runtime | Library | Scope | Conflict risk |
|---------|---------|-------|---------------|
| ScrollPage block | Vanilla JS (IntersectionObserver, rAF, SVG filters) | Per-block | None — self-contained |
| Flipbook block | Vanilla JS (`flipbook.iife.js`) | Per-block | None — self-contained |
| Experience runtime | GSAP 3.15 (Observer, ScrollTrigger) | Per-page | The one we're extending |

### Convergence plan

The Experience runtime IS the shared motion-runtime. It already:
- Bootstraps GSAP + Observer + ScrollTrigger
- Has `prefers-reduced-motion` guard
- Has `localStorage` off-toggle (`ensodo:experience:off`)
- Has scene-preset registry pattern (transition types)
- Has keyboard/focus accessibility

**What to add for scene presets:**
- Replace simple `getTransitionTimeline()` with a `SceneRegistry` that maps preset names to GSAP timeline factories
- Port the `split()` helper from the reference prototype (character-level text animation)
- Add ScrollTrigger `pin: true` + `scrub: true` support for pinned-statement and scroll-gallery
- Add parallax counter-motion for parallax-split

**ScrollPage and Flipbook stay as-is** — they're block-scoped vanilla JS, don't conflict, and serve different purposes. No forced convergence needed.

---

## 5. Publish Pipeline Injection

**Unchanged from prior Phase 0.** `BuildPageService` lines 59-66 handle conditional injection. Both layout.blade.php and grid-layout.blade.php receive `$headScripts`, `$bodyScripts`, `$customCss`.

---

## 6. Preview Endpoint

**Unchanged.** PreviewController and DynamicSiteController both inject runtime when `experience_mode === 'cinematic'` or `?experience=1`.

---

## 7. Section Meta Shape

**Current:** Section blocks store `experienceTransition`, `experienceEnter`, `experiencePin` in block data.

**Migration for scene presets:** Rename/replace with:
- `scene`: one of `pinned-statement`, `scroll-gallery`, `reveal`, `parallax-split`, `fade-through`
- Default: `fade-through`
- The old `experienceTransition`/`experienceEnter` values become internal to each scene preset

**Page-level atmosphere:** Store in `seo_meta` JSON (same as other page-level settings):
- `experience_preloader`: boolean (default false)
- `experience_cursor`: boolean (default false)  
- `experience_sound`: boolean (default false)
- `experience_sound_asset`: string (media asset ID)
- `experience_smooth_scroll`: boolean (defer to v2)

---

## 8. Asset Bundling

**Unchanged.** Experience runtime is built with esbuild to `public/assets/experience/`, copied to `public_html/assets/experience/` during deploy. Content-hashing is manual (filename-based). Referenced via direct path in `$bodyScripts`.

---

## 9. Smooth-Scroll Feasibility

**GSAP ScrollSmoother is available** in the installed GSAP 3.15.

**Conflict analysis:**
- Experience runtime uses `Observer.create({ preventDefault: true })` which hijacks wheel events
- ScrollSmoother also hijacks scroll for smooth inertia
- **Direct conflict on cinematic pages** — both fight for scroll control

**Recommendation: DEFER TO v2.** Smooth-scroll via ScrollSmoother or Lenis conflicts with the Observer-based panel navigation on cinematic pages. For non-cinematic pages, smooth-scroll could be added later as a separate feature. For v1, native scroll is fine.

---

## 10. Regression Anchor

**EXISTS:**
- `tests/Feature/ExperienceModePublishTest.php` — 7 tests asserting standard pages have no artifacts
- `tests/fixtures/standard-page-baseline.html` — golden snapshot
- All 18 tests pass

---

## 11. Reference File

**`wabisabi-experience-reference.html` NOT FOUND on disk.**

The user needs to `scp` this file to the VPS, or we use the existing `wabisabi3` page as the behavioral reference (it already has the runtime active). The 5 scene presets will be built to match the descriptions in the prompt, using GSAP ScrollTrigger patterns.

**Action:** Proceed without the reference file — use the prompt's scene preset descriptions + standard GSAP ScrollTrigger patterns as the spec. The wabisabi3 page serves as the existing proof-of-concept.

---

## GO / NO-GO

### ✅ GO

| Gate | Status |
|------|--------|
| Prior work reconciliation | ✅ MIGRATE — extend existing, no second system |
| Layout system | ✅ Keep `experience_mode` column (orthogonal to layout) |
| Track 0 hierarchy | ✅ VERIFIED |
| Shared runtime | ✅ Experience runtime IS the shared runtime; extend it |
| Publish pipeline | ✅ VERIFIED, conditional injection working |
| Preview endpoint | ✅ VERIFIED, `?experience=1` flag working |
| Section meta | ✅ Rename `experienceTransition` → `scene` |
| Regression tests | ✅ 18 tests exist and pass |
| Smooth-scroll | ⏸️ DEFERRED to v2 (conflicts with Observer) |
| Reference file | ⚠️ NOT on disk — proceed with prompt spec + wabisabi3 as reference |

### Migration plan summary

1. **Keep** `experience_mode` column, models, validation, serializers, tests
2. **Rename** Section `experienceTransition`/`experienceEnter` → `scene` (one field, preset name)
3. **Extend** runtime with 5 scene presets (ScrollTrigger timelines) replacing simple transitions
4. **Add** atmosphere toggles to page `seo_meta`
5. **Add** `split()` text helper (ported, no SplitText plugin)
6. **Keep** all existing guards (reduced-motion, localStorage, keyboard a11y)
