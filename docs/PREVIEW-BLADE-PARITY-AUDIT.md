# Preview/Blade Parity Audit

> **Date**: 2026-05-14
> **Status**: Medium fixes applied. Known intentional gaps documented.

## Fixed Issues (Medium Priority)

| Block | Issue | Fix |
|-------|-------|-----|
| paragraph | Missing text color — Preview had `text-base-content/80`, Blade had none | Added `color:rgba(0,0,0,0.8)` to Blade prose wrapper |
| text | Duplicate `text-block` class on inner wrapper | Removed inner `text-block` class, kept only outer Level 4 wrapper |
| divider | Default color `#ccc` vs Preview `#d1d5db` | Aligned Blade default to `#d1d5db` |
| code | No line numbers, no language header, duplicate inner wrapper | Added line numbers support (`show_line_numbers`), language header bar, removed duplicate inner wrapper |
| columns | Duplicate `columns-block` class on inner wrapper | Removed inner class, kept outer Level 4 wrapper |

## Known Intentional Gaps (High Priority — By Design)

These are **not bugs** — the Preview is a WYSIWYG approximation, the Blade is the production output.

| Block | Gap | Reason |
|-------|-----|--------|
| image | Blade has responsive srcset, webp, lazy loading; Preview shows simple img | Image optimization is server-side only |
| accordion | Blade uses semantic `<details>/<summary>` with native collapse; Preview shows static items | Interactive HTML5 elements can't be previewed in editor |
| section | Blade has parallax, zoom, overlay background system; Preview has basic bg | Complex scroll effects need actual viewport |
| tabs | Blade has inline JS for tab switching + ARIA; Preview shows static layout | Interactive JS can't run in editor canvas |
| video | Blade embeds actual YouTube/Vimeo iframes or `<video>` tags; Preview shows placeholder | Embedding live video in editor would be distracting |

## Recently Fixed Gaps

| Block | Gap | Fix |
|-------|-----|-----|
| heading | Blade rendered bare heading tags without sizing | Added font-size map (h1-h6), font-weight, font-family, line-height, color via theme CSS variables |
| button | Blade omitted base `btn` class, DaisyUI styling broken | Added `btn` base class, removed duplicate inner wrapper |

## Open Gaps (Low Priority)

| Block | Gap | Priority |
|-------|-----|----------|
| code | Preview shows language header even when empty (fallback 'javascript'); Blade hides header when language is empty | Low — minor UX gap |

## Low Priority Differences (Styling Approach)

| Block | Difference | Impact |
|-------|------------|--------|
| pullquote | Preview uses Tailwind; Blade uses BEM classes | Visually equivalent with CSS |
| testimonial | Avatar 32px in Preview vs 40px in Blade | Minor size difference |
| ctabanner | Preview shows empty placeholders; Blade has default text fallbacks | UX difference, not visual bug |
