# Preview Parity Audit — Top 10 Blocks

**Date:** 2026-06-14
**Sprint:** 5

## Summary

All top 10 blocks have complete stacks: React Editor, React Preview, Blade template, and definition with default data.

## Parity Table

| Block | Editor | Preview | Blade | Default Data | Mismatch | Priority |
|-------|--------|---------|-------|-------------|----------|----------|
| hero | YES (517 lines) | YES (403 lines) | YES (390 lines) | YES | Minor: preview uses inline-editing, Blade uses static render. CTA button styling may differ. | P2 |
| paragraph | YES (21 lines) | YES (30 lines) | YES (19 lines) | YES | Low: preview renders TipTap WYSIWYG, Blade renders HTML string. Styling parity good. | P3 |
| heading | YES (200 lines) | YES (71 lines) | YES (41 lines) | YES | Low: inline editing in preview vs static in Blade. Font/color/size all use same data keys. | P3 |
| image | YES (40 lines) | YES (53 lines) | YES (83 lines) | YES | Minor: Blade has lazy loading + srcset, preview shows basic img. No visual mismatch. | P3 |
| imagecaption | YES (62 lines) | YES (65 lines) | YES (41 lines) | YES | Low: layout parity looks good. Caption text renders same way. | P4 |
| featuregrid | YES (131 lines) | YES (66 lines) | YES (59 lines) | YES | Minor: Blade uses CSS grid, preview uses flexbox. Column count may render differently. | P2 |
| ctabanner | YES (52 lines) | YES (45 lines) | YES (66 lines) | YES | Minor: Blade has more background options and responsive padding. Preview is simplified. | P2 |
| gallery | YES (81 lines) | YES (55 lines) | YES (54 lines) | YES | Low: Both use grid layout. Blade has lightbox, preview has placeholder grid. | P3 |
| postgrid | YES (362 lines) | YES (251 lines) | YES (179 lines) | YES | Good: Both use normalizeCardEffects + effects system. Image placeholders vs real images differ. | P3 |
| contact-form | YES (120 lines) | YES (43 lines) | YES (45 lines) | YES | Low: Preview shows form structure, Blade renders functional form with CSRF. Expected difference. | P4 |

## Legend

- **P1** — Visible mismatch that confuses users, fix ASAP
- **P2** — Minor styling difference, fix when touching block
- **P3** — Expected difference (preview limitations), document
- **P4** — No action needed

## Key Findings

1. **No P1 issues** — No blocks have critical parity problems
2. **Inline editing** works for hero and heading in preview, Blade renders static — expected
3. **PostGrid** has best parity thanks to shared BlockEffects system (TS + PHP)
4. **Contact form** preview is intentionally simplified (no live form submission)
5. **Gallery** Blade has lightbox JS, preview shows static grid — expected for editor

## Recommendations

1. **featuregrid**: Align preview grid to use CSS grid like Blade (minor effort)
2. **ctabanner**: Add background image support in preview to match Blade
3. **hero**: Verify CTA button styling matches between preview and Blade
4. **image**: Consider adding lazy loading indicator in preview for awareness
