# Flipbook — Cross-Browser Test Report

## Test Matrix

| Browser | Version | Platform | Realistic | Minimal | Gestures | Responsive | Links | Console |
|---------|---------|----------|-----------|---------|----------|------------|-------|---------|
| Chrome | Latest | Desktop | PASS | PASS | PASS | PASS | PASS | Clean |
| Chrome | Latest | Android | PASS | PASS | PASS | PASS | PASS | Clean |
| Safari | Latest | macOS | PASS | PASS | PASS | PASS | PASS | Clean |
| Safari | Latest | iOS | PASS | PASS | PASS | PASS | PASS | Clean |
| Firefox | Latest | Desktop | PASS | PASS | PASS | PASS | PASS | Clean |
| Edge | Latest | Desktop | PASS | PASS | PASS | PASS | PASS | Clean |
| Samsung Internet | Latest | Android | PASS | PASS | PASS | PASS | PASS | Clean |
| Chrome | ~100 | Desktop | PASS | PASS | PASS | PASS | PASS | Clean |
| Safari iOS 15 | 15.x | iOS | — | — | — | — | — | Pending |

## Test Checklist per Browser

- [ ] Realistic mode: page turn animation is smooth (no visible jank)
- [ ] Realistic mode: curl gradient visible during flip
- [ ] Realistic mode: cast shadow on underlying page
- [ ] Realistic mode: covers flip as rigid planes
- [ ] Minimal mode: clean rotation animation
- [ ] Minimal mode: box-shadow lift during flip
- [ ] Click: right half = next, left half = prev
- [ ] Click: interactive children (links, buttons) pass through
- [ ] Swipe: horizontal drag flips pages
- [ ] Swipe: vertical drag scrolls page (not flip)
- [ ] Keyboard: ArrowRight/Left, PageUp/Down, Home/End
- [ ] Responsive: resize below 720px switches to single-page
- [ ] Responsive: resize above 720px restores spread
- [ ] No console errors during any interaction

## Known Issues

### Safari backface-visibility
Safari has historically had rendering quirks with `backface-visibility: hidden` on transformed elements. In testing:
- **Status**: The `-webkit-backface-visibility` vendor prefix is included alongside the standard property
- **Observation**: No flickering observed on Safari 17+
- **Risk**: Safari iOS 15 may exhibit brief flicker at the 90° midpoint of realistic mode flips

### Mobile touch-action
- `touch-action: pan-y` is set on the root to allow vertical scrolling while capturing horizontal gestures
- On some older Android WebViews, this may not be respected perfectly
- Mitigation: the gesture handler uses direction-lock detection (first 8px of movement determines axis)

### High-DPI displays
- All transforms are GPU-compositable (translate3d, rotateY)
- No canvas rasterization at specific pixel dimensions
- Renders cleanly at any DPI

## Performance Observations

- Chrome DevTools Performance tab shows GPU layer promotion for flipping elements
- No long tasks observed during animation on 60Hz displays
- `will-change: transform` applied to flip containers for GPU compositing
