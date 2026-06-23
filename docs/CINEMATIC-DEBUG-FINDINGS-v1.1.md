# CINEMATIC-DEBUG-FINDINGS v1.1
## Phase 0 — On-disk diagnosis

---

## Current State (verified from disk)

- Published HTML: 42,774 bytes, 5 sections with `data-scene`
- Runtime: `experience-runtime.28498741.js` (123KB), hash-verified
- Wrapper inline script: 3,568 chars with force override + media refresh + nav dots
- Config: `{"preloader":true,"cursor":true,"sound":false,"soundAsset":null}`
- Scenes: reveal, pinned-statement, scroll-gallery, parallax-split, fade-through

## Bug List (ranked)

### BUG A — HIGH: No media-load refresh in RUNTIME
The **wrapper inline script** has `loadeddata`/`fonts.ready`/`img.load` → `ScrollTrigger.refresh()`.
The **runtime JS** does NOT. The runtime creates ScrollTrigger instances at `defer` time when media hasn't loaded. If images/video load AFTER the runtime, pin positions are stale.
**Fix:** Add refresh listeners inside the runtime itself.

### BUG B — MEDIUM: overflow:hidden on sections
The cinematic CSS sets `overflow: visible` but 2 instances of `overflow:hidden` exist in the published HTML (from section inline styles or the wrapper). `overflow:hidden` on a pinned section's ancestor breaks `position:fixed` pinning in some browsers.
**Fix:** Force `overflow:visible !important` on `.cinematic-wrapper` and `.cinematic-wrapper > .section-block`.

### BUG C — MEDIUM: No reserved dimensions for media
Images and video have no explicit `aspect-ratio`/`width`/`height`. Layout shifts after media loads → ScrollTrigger recalculation needed.
**Fix:** Add CSS `aspect-ratio` defaults for images in cinematic sections.

### BUG D — LOW: snap in scroll-gallery may fight scrub
The gallery scene has both `scrub: 1` and no explicit snap-removal for interaction smoothness.
**Fix:** Ensure snap is removed — scrub alone is smoother for scroll-driven crossfade.

## Missing Effects

| Effect | Status | Phase |
|--------|--------|-------|
| Image mask-reveal (clip-path wipe) | ✅ Already in reveal + parallax-split | Done |
| scroll-gallery crossfade | ✅ Implemented but needs Swiper for real galleries | Phase 3 |
| Swiper for grouped images | ❌ Missing | Phase 3 |
| Lenis smooth scroll | ❌ Missing | Phase 4 |
| Cross-document View Transitions | ✅ Already in published CSS | Done |

## Fix Order

1. **Phase 1:** Fix bugs A, B, C, D — runtime refresh + overflow + dimensions
2. **Phase 2:** Already done (mask-reveal exists)
3. **Phase 3:** Add Swiper for real galleries
4. **Phase 4:** Add Lenis smooth scroll

**🛑 STOP. Awaiting confirmation.**
