/**
 * Canonical breakpoints — single source of truth for the entire CMS.
 *
 * Desktop: >= 1024px
 * Tablet:  768px - 1023px
 * Mobile:  <= 767px
 *
 * These values are mirrored in:
 *  - BlockStyle.php  (buildHideOnCss)
 *  - hero.blade.php  (responsive media queries)
 *  - row.blade.php   (mobile column stacking)
 *  - layout.blade.php (global responsive overrides)
 *  - index.css       (admin responsive rules)
 */

export type Breakpoint = 'desktop' | 'tablet' | 'mobile';

/** Media query thresholds (max-width) */
export const BREAKPOINTS = {
  tablet: 1023,  // max-width for tablet (desktop starts at 1024)
  mobile: 767,   // max-width for mobile (tablet starts at 768)
} as const;

/** Editor canvas widths for device preview */
export const CANVAS_WIDTHS: Record<Breakpoint, string> = {
  desktop: '100%',
  tablet: '768px',
  mobile: '390px',
};

/** CSS media query strings */
export const MEDIA_QUERIES = {
  desktop: `@media (min-width: ${BREAKPOINTS.tablet + 1}px)`,    // >= 1024px
  tablet:  `@media (max-width: ${BREAKPOINTS.tablet}px)`,        // <= 1023px
  mobile:  `@media (max-width: ${BREAKPOINTS.mobile}px)`,        // <= 767px
  tabletOnly: `@media (min-width: ${BREAKPOINTS.mobile + 1}px) and (max-width: ${BREAKPOINTS.tablet}px)`,
} as const;
