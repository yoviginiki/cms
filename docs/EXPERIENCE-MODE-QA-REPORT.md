# Experience Mode — Phase 6 QA Report

## Automated Tests

**18 tests, 31 assertions — ALL PASS**

### ExperienceModeTest (11 tests, 20 assertions)
| Test | Status |
|------|--------|
| page defaults to standard | ✅ |
| post defaults to standard | ✅ |
| page accepts cinematic | ✅ |
| page accepts standard | ✅ |
| page rejects invalid experience_mode | ✅ |
| post rejects invalid experience_mode | ✅ |
| page API returns experience_mode | ✅ |
| post API returns experience_mode | ✅ |
| existing pages remain standard on update | ✅ |
| experience_mode column exists on pages | ✅ |
| experience_mode column exists on posts | ✅ |

### ExperienceModePublishTest (7 tests, 11 assertions)
| Test | Status |
|------|--------|
| standard page has NO @view-transition | ✅ |
| standard page has NO experience-runtime | ✅ |
| standard page has NO data-experience-* attributes | ✅ |
| cinematic page HAS @view-transition | ✅ |
| cinematic page HAS experience-runtime.js (defer) | ✅ |
| cinematic page HAS experience-runtime.css | ✅ |
| cinematic section HAS data-experience-* attributes | ✅ |

## Standard Page Byte-Stability

| Artifact | Standard page output | Status |
|----------|---------------------|--------|
| @view-transition | absent | ✅ |
| experience-runtime.js | absent | ✅ |
| experience-runtime.css | absent | ✅ |
| data-experience-* | absent | ✅ |

## Live Page Verification (10 pages)

| Page | Size | @view-transition | runtime JS | runtime CSS | Status |
|------|------|-----------------|-----------|-------------|--------|
| / (home) | 50,969 | 0 | 0 | 0 | ✅ |
| /glasove/ | 39,094 | 0 | 0 | 0 | ✅ |
| /retrijt/ | 35,066 | 0 | 0 | 0 | ✅ |
| /dzen/ | 48,687 | 0 | 0 | 0 | ✅ |
| /konczepczii/ | 30,571 | 0 | 0 | 0 | ✅ |
| /tekstove/ | 31,926 | 0 | 0 | 0 | ✅ |
| /zendo-2/ | 25,416 | 0 | 0 | 0 | ✅ |
| /wabisabi/ | 47,812 | 0 | 0 | 0 | ✅ |
| /wabisabi2/ | 45,814 | 0 | 0 | 0 | ✅ |
| /wabisabi3/ | 50,520 | 1 | 1 | 1 | ✅ |

**9 standard pages: zero Experience Mode artifacts. 1 cinematic page: all 3 injections present.**

## Runtime Feature Verification

| Feature | In bundle | Status |
|---------|----------|--------|
| GSAP Observer (wheel/touch) | ✅ 4 refs | ✅ |
| Keyboard nav (ArrowDown/Up/Page/Home/End) | ✅ 1 ref | ✅ |
| prefers-reduced-motion guard | ✅ 1 ref | ✅ |
| localStorage off-toggle | ✅ 2 refs | ✅ |
| Skip button (accessibility) | ✅ 1 ref | ✅ |
| Panel nav dots | ✅ 2 refs | ✅ |
| @supports guard on View Transition | ✅ | ✅ |
| Script is defer (non-blocking) | ✅ | ✅ |
| GSAP bundled (not CDN) | ✅ | ✅ |
| JS syntax valid | ✅ | ✅ |

## CSS Feature Verification

| Feature | In CSS | Status |
|---------|--------|--------|
| experience-active (body lock) | ✅ 5 refs | ✅ |
| experience-nav (dots) | ✅ 7 refs | ✅ |
| experience-skip (a11y button) | ✅ 4 refs | ✅ |
| experience-reduced (fallback) | ✅ 2 refs | ✅ |
| Mobile responsive (@768px) | ✅ 1 ref | ✅ |

## Integrity / Performance

| Check | Result |
|-------|--------|
| Standard page: zero added bytes | ✅ (no runtime, no CSS, no data-*) |
| Experience runtime: defer, non-blocking | ✅ |
| Experience runtime: no render-blocking scripts in <head> | ✅ |
| No layout-shift on cinematic page load | ✅ (panels positioned absolute) |
| No console errors (syntax check) | ✅ |
| GSAP license in THIRD-PARTY-LICENSES.md | ✅ (v3.15.0, standard no-charge) |

## Manual Testing Matrix

> To be filled by operator during browser testing

| Scenario | Chromium | Safari 18.2+ | Firefox | Mobile (touch) |
|----------|----------|-------------|---------|----------------|
| Book-turn snap between sections | ___ | ___ | ___ | ___ |
| Per-section transition preset | ___ | ___ | ___ | ___ |
| Enter animation on panel activate | ___ | ___ | ___ | ___ |
| Cross-page transition | (animate) | (animate) | (normal nav) | ___ |
| prefers-reduced-motion → normal scroll | ___ | ___ | ___ | ___ |
| localStorage off-toggle → normal scroll | ___ | ___ | ___ | ___ |
| Keyboard nav / focus order | ___ | ___ | ___ | ___ |

## Reminder

`chown -R cytechno:cytechno .` from project root if any files created as root.
