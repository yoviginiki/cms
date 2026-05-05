# Flipbook Accessibility Model

## ARIA Structure

```
[role="region" aria-roledescription="flipbook" tabindex="0"]  ← root
  [role="region" aria-label="Page 1"]                         ← visible page
  [role="region" aria-label="Page 2"]                         ← visible page
  [role="region" aria-label="Page 3" aria-hidden="true" inert] ← hidden page
  [aria-live="polite" role="status"]                          ← page change announcements
```

## Keyboard Navigation

| Key | Action |
|-----|--------|
| ArrowRight / PageDown | Flip to next page |
| ArrowLeft / PageUp | Flip to previous page |
| Home | Go to first page |
| End | Go to last page |
| Tab | Move focus through interactive elements within visible pages |

The root element has `tabindex="0"` to receive keyboard focus. A focus ring is visible via `:focus-visible`.

## Hidden Pages

Non-visible pages receive both `aria-hidden="true"` and the `inert` attribute:
- `aria-hidden` prevents screen readers from announcing hidden content
- `inert` prevents keyboard focus from entering hidden pages (links, buttons, etc.)
- Both are removed when a page becomes visible after a flip

## Page Change Announcements

A screen-reader-only `[aria-live="polite"]` element announces page changes:
> "Page 3 of 12"

This fires after each flip completes (not during animation).

## Interactive Content Within Pages

Links, buttons, form fields, and other interactive elements within pages remain fully functional:
- Click events on interactive elements pass through (don't trigger flips)
- Tab order within visible pages is natural
- After a flip, focus does not automatically move to the new page (user retains control)

## No-JS Fallback

When JavaScript is disabled:
- All pages are displayed vertically in reading order
- Content is fully readable and SEO-indexable
- The `ef-enhanced` class is only added after JS initialization
- CSS targets `.ef-root:not(.ef-enhanced) .ef-page` for fallback layout

## Color & Motion

- The flipbook does not rely on color alone to convey information
- Animation respects user preferences via the `flipping_time_ms` setting
- No auto-playing animations — all flips are user-initiated

## Testing

Tested with:
- WAVE accessibility checker (zero errors target)
- VoiceOver (macOS/iOS) — announces page changes
- NVDA (Windows) — announces page changes
- Keyboard-only navigation — all functions accessible
