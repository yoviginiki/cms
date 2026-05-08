# Block Properties Manual Checklist

Use this checklist to manually verify block property behavior in the CMS editor and published pages.

## Prerequisites
- Access to CMS admin panel
- A test page with a Hero block
- Ability to publish and view the page

## Hero Block — Content Properties (Expected: WORKING)

### Background
- [ ] Set bg_type to Color → enter hex color → Preview shows color → Published page shows color
- [ ] Set bg_type to Gradient → configure stops/angle → Preview shows gradient → Published page shows gradient
- [ ] Set bg_type to Image → upload/select image → Preview shows image → Published page shows image
- [ ] Set image overlay → adjust opacity → Preview shows overlay → Published page shows overlay
- [ ] Set image position to Top → Preview reflects → Published page reflects
- [ ] Set image repeat to Repeat → Preview reflects → Published page reflects
- [ ] Set scroll effect to Fixed → Published page has fixed background attachment
- [ ] Set bg_type to None → Preview shows neutral bg → Published page shows neutral bg

### Content
- [ ] Enter title → Preview shows title → Published page shows title
- [ ] Enter subtitle → Preview shows subtitle → Published page shows subtitle
- [ ] Enter CTA text + URL → Preview shows button → Published page shows clickable button
- [ ] Leave CTA empty → No button in preview or published

### Accessibility
- [ ] Enter alt text on image bg → Published page has `role="img" aria-label="..."`

## Global Property Panels (Expected: DEAD CONTROL as of audit date)

### Spacing Panel
- [ ] Set padding to Large → WARNING: NOT expected to work — Preview unchanged, Published unchanged
- [ ] Set margin → WARNING: NOT expected to work

### Visual Panel (Background & Borders)
- [ ] Set background color → WARNING: NOT expected to work (separate from block-specific bg)
- [ ] Set border width + color → WARNING: NOT expected to work
- [ ] Set border radius → WARNING: NOT expected to work
- [ ] Set shadow to LG → WARNING: NOT expected to work
- [ ] Set opacity to 50% → WARNING: NOT expected to work

### Animation Panel
- [ ] Set entrance to Fade → WARNING: NOT expected to work — no visible fade in preview or published
- [ ] Set entrance to Slide Up → WARNING: NOT expected to work
- [ ] Set duration to 1000ms → WARNING: NOT expected to work
- [ ] Set delay to 500ms → WARNING: NOT expected to work
- [ ] Set trigger to On Scroll → WARNING: NOT expected to work
- [ ] Set hover effect → WARNING: NOT expected to work

### Layout Panel
- [ ] Set max-width → WARNING: NOT expected to work
- [ ] Set min-height → WARNING: NOT expected to work
- [ ] Set alignment → WARNING: NOT expected to work

### Responsive Panel
- [ ] Set Hide on Mobile → WARNING: NOT expected to work

### Advanced Panel
- [ ] Enter custom class → WARNING: NOT expected to work
- [ ] Enter custom CSS → WARNING: NOT expected to work
- [ ] Enter HTML ID → WARNING: NOT expected to work
- [ ] Enter ARIA label → WARNING: NOT expected to work

## Theme Readability
- [ ] Switch admin to light theme → Hero editor and preview readable
- [ ] Switch admin to dark theme → Hero editor and preview readable
- [ ] Hero with no bg → text readable in light theme
- [ ] Hero with no bg → text readable in dark theme
- [ ] Hero with dark bg → white text visible
- [ ] Hero empty state → visible and informative in both themes

## Published Output
- [ ] Published hero with bg color renders correctly
- [ ] Published hero with gradient renders correctly
- [ ] Published hero with image + overlay renders correctly
- [ ] Published hero with no bg has readable text
- [ ] Published hero on mobile device renders reasonably
- [ ] CTA button works and navigates to correct URL
- [ ] No javascript: or dangerous URL in CTA href
