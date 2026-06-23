# Cinematic Layout — Phase 6 QA Report

## 1. Automated Tests

**25 tests, 47 assertions — ALL PASS**

### ExperienceModeTest (11 tests, 20 assertions)
| Test | Status |
|------|--------|
| page defaults to standard | PASS |
| post defaults to standard | PASS |
| page accepts cinematic | PASS |
| page accepts standard | PASS |
| page rejects invalid experience_mode | PASS |
| post rejects invalid experience_mode | PASS |
| page API returns experience_mode | PASS |
| post API returns experience_mode | PASS |
| existing pages remain standard on update | PASS |
| experience_mode column exists on pages | PASS |
| experience_mode column exists on posts | PASS |

### ExperienceModePublishTest (14 tests, 27 assertions)
| Test | Status |
|------|--------|
| standard page has NO @view-transition | PASS |
| standard page has NO experience-runtime | PASS |
| standard page has NO experience data attributes | PASS |
| cinematic page HAS @view-transition | PASS |
| cinematic page HAS experience-runtime.js (defer) | PASS |
| cinematic page HAS experience-runtime.css | PASS |
| cinematic section HAS legacy data attributes | PASS |
| section with scene preset HAS data-scene | PASS |
| section without scene has NO data-scene | PASS |
| standard page with scene still NO runtime | PASS |
| standard page has NO atmosphere config | PASS |
| cinematic page HAS atmosphere config | PASS |
| standard page ZERO cinematic artifacts | PASS |
| cinematic page HAS ALL artifacts | PASS |

---

## 2. Standard Page Byte-Stability

| Artifact | glasove (standard) | Status |
|----------|-------------------|--------|
| @view-transition | absent | PASS |
| experience-runtime.js | absent | PASS |
| experience-runtime.css | absent | PASS |
| experience-config | absent | PASS |
| data-scene | absent | PASS |
| **Verdict** | **ZERO ARTIFACTS** | **PASS** |

---

## 3. Live Page Verification (11 pages)

| Page | Size | vt | js | css | cfg | scene | Status |
|------|------|----|----|-----|-----|-------|--------|
| / (home) | 50,969 | 0 | 0 | 0 | 0 | 0 | CLEAN |
| /glasove/ | 39,094 | 0 | 0 | 0 | 0 | 0 | CLEAN |
| /retrijt/ | 35,066 | 0 | 0 | 0 | 0 | 0 | CLEAN |
| /dzen/ | 48,687 | 0 | 0 | 0 | 0 | 0 | CLEAN |
| /konczepczii/ | 30,571 | 0 | 0 | 0 | 0 | 0 | CLEAN |
| /tekstove/ | 31,926 | 0 | 0 | 0 | 0 | 0 | CLEAN |
| /zendo-2/ | 25,416 | 0 | 0 | 0 | 0 | 0 | CLEAN |
| /wabisabi/ | 47,812 | 0 | 0 | 0 | 0 | 0 | CLEAN |
| /wabisabi2/ | 45,814 | 0 | 0 | 0 | 0 | 0 | CLEAN |
| /wabisabi3/ | 50,651 | 1 | 1 | 1 | 1 | 0 | CINEMATIC |
| /cinematic-test/ | 28,537 | 1 | 1 | 1 | 1 | 4 | CINEMATIC |

**9 standard pages: ZERO artifacts. 2 cinematic pages: ALL artifacts present.**

---

## 4. Runtime Feature Verification (bundle)

| Feature | In bundle | Status |
|---------|----------|--------|
| ScrollTrigger | 3 refs | PASS |
| splitText helper | in source (minified) | PASS |
| pinned-statement scene | 1 ref | PASS |
| scroll-gallery scene | 1 ref | PASS |
| reveal scene | 1 ref | PASS |
| parallax-split scene | 1 ref | PASS |
| fade-through scene | 1 ref | PASS |
| prefers-reduced-motion | 1 ref | PASS |
| localStorage off-toggle | 1 ref | PASS |
| Skip button | 1 ref | PASS |
| Preloader | 1 ref | PASS |
| Custom cursor | 1 ref | PASS |
| Ambient sound | 1 ref | PASS |
| Atmosphere config reader | 1 ref | PASS |
| pin + scrub (ScrollTrigger) | 1 ref | PASS |
| "Disable animations" label | 1 ref | PASS |

## 5. CSS Feature Verification

| Feature | Refs | Status |
|---------|------|--------|
| Preloader styles | 2 | PASS |
| Cursor dot + ring | 1 | PASS |
| Cursor hover scale | 1 | PASS |
| Sound toggle | 4 | PASS |
| Skip button | 4 | PASS |
| Pinned sections 100vh | 2 | PASS |
| Reduced-motion fallback | 6 | PASS |
| Mobile responsive @768px | 1 | PASS |
| Cursor hidden on mobile | 5 | PASS |

---

## 6. Integrity / Performance

| Check | Result |
|-------|--------|
| Standard pages: zero added bytes | PASS |
| Runtime: `defer`, non-blocking | PASS |
| No render-blocking scripts in `<head>` | PASS |
| JS syntax valid | PASS |
| GSAP 3.15.0 license in THIRD-PARTY-LICENSES.md | PASS |
| Bundle: 119.5 KB JS + 4.5 KB CSS | PASS |

---

## 7. Manual Testing Matrix

> To be filled by operator during browser testing at https://ensodo.eu/cinematic-test/

| Scenario | Chromium | Safari | Firefox | Mobile |
|----------|----------|--------|---------|--------|
| `pinned-statement` pins + scrubs | ___ | ___ | ___ | ___ |
| `scroll-gallery` crossfade + progress dots | ___ | ___ | ___ | ___ |
| `reveal` split-text + stagger | ___ | ___ | ___ | ___ |
| `parallax-split` counter-motion | ___ | ___ | ___ | ___ |
| `fade-through` | ___ | ___ | ___ | ___ |
| Preloader (counter 0→100) | ___ | ___ | ___ | ___ |
| Custom cursor (dot + ring) | ___ | ___ | ___ | ___ |
| Sound toggle (if asset set) | ___ | ___ | ___ | ___ |
| `prefers-reduced-motion` → static | ___ | ___ | ___ | ___ |
| `localStorage` off-toggle → static | ___ | ___ | ___ | ___ |
| Keyboard / focus order, no traps | ___ | ___ | ___ | ___ |
| Cross-page View Transition (Chromium) | ___ | n/a | n/a | ___ |

---

## 8. Reminder

`chown -R cytechno:cytechno .` from project root if any files created as root.
