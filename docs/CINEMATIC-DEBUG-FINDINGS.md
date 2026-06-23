# CINEMATIC-DEBUG-FINDINGS.md
## Phase 0 — Diagnose the Real Artifact

---

## Root Cause: Two Competing Navigation Systems

The cinematic layout page (`wabisabi4`) loads **two separate systems** that fight each other:

### System A: Cinematic Wrapper (inline JS in `cinematic.blade.php`)
- Sections are `position: absolute`, stacked, `height: 100vh`
- Wheel/touch/keyboard events are **intercepted and preventDefault'd**
- Panel transitions via inline style manipulation (transform/opacity)
- **No real document scroll happens** — the page is locked at 100vh

### System B: Experience Runtime (`experience-runtime.js`, 122KB)
- Uses GSAP ScrollTrigger with `pin: true` and `scrub: true`
- **Requires real document scroll** to calculate trigger positions and drive scrub
- Creates timelines that depend on scroll progress
- **Cannot function** when scroll is prevented by System A

**Result:** System B is dead code. ScrollTrigger creates instances that never trigger because System A prevents all scroll events. The scene presets (pinned-statement, scroll-gallery, etc.) exist in the runtime but have zero effect on the page.

---

## Bug List (ranked)

### BUG #1 — CRITICAL: Dual navigation systems
- **What:** cinematic.blade.php inline JS + experience-runtime.js both try to control page navigation
- **Impact:** ScrollTrigger scenes are inert — no pin, no scrub, no scroll-driven effects
- **Fix:** Unify into ONE system. Two options:
  - **Option A (recommended):** Rewrite cinematic.blade.php to use a tall scrollable page where each section is 100vh. Let ScrollTrigger handle all pinning/scrubbing/transitions. The cinematic wrapper becomes a CSS shell, not a JS navigator.
  - **Option B:** Keep the panel navigator but replace ScrollTrigger scenes with GSAP timeline animations triggered by the wrapper's `goTo()` function (no scroll needed).

### BUG #2 — HIGH: No media-load refresh
- **What:** `ScrollTrigger.refresh()` not called after video `loadeddata`, image `load`, or `document.fonts.ready`
- **Impact:** Even if BUG #1 is fixed, pin positions will be wrong until media loads
- **Fix:** Add refresh listeners for all media events, debounced

### BUG #3 — HIGH: No reserved media dimensions
- **What:** Images and video have no explicit `width`/`height`/`aspect-ratio`
- **Impact:** Layout shifts after media loads → stale ScrollTrigger positions
- **Fix:** Add `aspect-ratio` or explicit dimensions to media blocks in cinematic context

### BUG #4 — MEDIUM: Missing effects
- **No image mask-reveal** (clip-path wipe) — a core MiSO signature
- **No Swiper** for grouped images
- **No Lenis** smooth scroll
- `scroll-gallery` scene code exists but can't work due to BUG #1

### BUG #5 — LOW: Duplicated reduced-motion logic
- Both inline JS and runtime check `prefers-reduced-motion` independently
- No conflict, but cleanup opportunity when unified

---

## Recommended Fix Order

1. **Phase 1:** Fix BUG #1 — unify to Option A (scrollable page + ScrollTrigger as sole engine). This unlocks everything else.
2. **Phase 1 cont:** Fix BUGs #2 + #3 — media refresh + reserved dimensions
3. **Phase 2:** Add image mask-reveal (clip-path wipe)
4. **Phase 3:** Fix scroll-gallery + add Swiper
5. **Phase 4:** Add Lenis smooth scroll (if pins cooperate)

## Option A Architecture (recommended)

Replace the cinematic wrapper's absolute-positioned panel navigator with:

```
<div class="cinematic-wrapper" style="/* normal flow, not overflow:hidden */">
  <section data-scene="reveal" style="min-height:100vh">...</section>
  <section data-scene="pinned-statement" style="min-height:100vh">...</section>
  <section data-scene="scroll-gallery" style="min-height:100vh">...</section>
  ...
</div>
```

- Page scrolls normally (tall document)
- Each section has `min-height: 100vh`
- ScrollTrigger pins sections when they reach the top
- Scrub drives content animation within pinned sections
- No wheel hijacking needed — native scroll + ScrollTrigger does everything
- Nav dots update based on ScrollTrigger progress
- The cinematic wrapper CSS only hides nav/footer and styles sections full-viewport

This is how MiSO TONE actually works — it's a tall scrollable page with GSAP ScrollTrigger pinning, not a panel switcher.

---

## GO / NO-GO

**GO** — proceed with Option A unification in Phase 1.

**🛑 STOP. Awaiting operator confirmation of fix order before any changes.**
