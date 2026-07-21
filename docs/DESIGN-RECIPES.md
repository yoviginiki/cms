# Design Recipes — Layout & Styling Advice for the Page Builder

Practical, copy-paste recipes for common design goals, using only the controls
that exist in the CMS editor (block settings → Content / Layout / Style tabs).
Each recipe lists exact settings, so anyone editing a site can reproduce them.

---

## 1. Center a section in the middle of the screen (both axes)

Makes a section occupy the full viewport height and centers its content
horizontally and vertically, on every screen size.

Select the block (e.g. a Post Grid) → **Layout** tab:

| Setting     | Value    |
|-------------|----------|
| Min Height  | `100vh`  |
| Display     | `Flex`   |
| Direction   | `Column` |
| Justify     | `Center` |
| Align items | `Center` |

Why it works: `100vh` always equals the visible screen height, `justify-content`
centers along the column axis (top-to-bottom), `align-items` centers across it
(left-to-right).

Notes:
- **Zero the section's own padding.** Section blocks default to 40px top/bottom
  padding, which adds to the 100vh and produces a scrollbar plus empty strips.
  Set the section's Padding Top/Bottom to `0` for full-screen sections.
- **Fixed/sticky header:** the section centers within the *full* viewport, so a
  header visually pushes the middle down. Use `90vh` as a pragmatic fix
  (`calc()` is not accepted by the style sanitizer).
- **Align items: Center** makes the inner content shrink to its natural width.
  For full-width content (e.g. Post Grid in "Stretch" card mode), leave Align
  items at Default and center horizontally with the Alignment buttons instead.
- Live example: monikcreations → page "svetlinni-sklulpturi" (Post Grid block).

## 2. Post Grid: cards exactly as big as their pictures

Post Grid block → **Card Width** → `Fit image (card hugs the picture)`.

- The image's natural aspect ratio at the chosen **Image Height** dictates each
  card's width. Columns are respected (3 columns → 3 cards per row).
- **Cards Alignment** (left/center/right) positions the grid inside the block.
- Add breathing room later with **Card Padding** (Card Border section) — the
  card grows around the image; the image stays its natural size.
- Card surface color: **Card Background** in the same section. Use `transparent`
  to let the section background show through.

## 2b. Post Grid per device — three independent views

The Post Grid editor is **device-aware**. Switch the canvas device
(desktop / tablet / mobile) in the top toolbar, then edit the block settings:

- On **desktop** you edit the base values.
- On **tablet** or **mobile** an orange banner appears and every *visual*
  field you change — **Columns, Gap, Image Height/Width, Card Padding, Cards
  Alignment, Heading size/align/padding/margin, Excerpt size/align/padding/
  margin** — is saved as an override for that device only. Desktop stays
  untouched. The banner's **Reset** button clears all overrides for that device.
- Until you override them, tablet defaults to max 2 columns and mobile to
  1 column; image heights scale down fluidly. Any explicit per-device image
  height switches that device to an exact pixel value.
- Published pages get real media queries (tablet 768–1023px, phone <768px),
  so each device shows its own layout.

Non-visual fields (category, limit, card style, show/hide toggles, effects)
remain global across devices — a post grid shows the same *content*
everywhere, styled per device.

## 3. General design advice for pages built here

**Spacing**
- Pick one spacing scale and stick to it: 8 / 16 / 24 / 32 / 48 / 64 px.
  The Gap and Padding controls accept any value — consistency is what reads
  as "designed".
- White space is a feature. If a section feels cramped, increase spacing
  before adding dividers or borders.

**Typography**
- Maximum 2 font families per site (one for headings, one for body). The
  postgrid/heading Font dropdowns offer many — resist using more.
- Body text 14–18px; headings sized by hierarchy (H2 > H3 > H4), not by taste
  per page. Keep the same heading size for the same role across pages.
- Line length: keep text columns under ~700px (`Max Width` in Layout tab).

**Color**
- Use theme tokens (`var(--color-primary)`, `var(--color-border)`, …) instead of
  hard-coded hex wherever a field accepts them — the site restyles centrally.
- One accent color for links/CTAs. Grays for everything structural.
- Card borders: prefer subtle (`1px solid var(--color-border)`) or none + a
  small shadow. Heavy borders + heavy shadows together look dated.

**Images**
- In grids, uniform Image Height with `cover` fit gives a clean rhythm;
  "Fit image" card mode gives a gallery/masonry feel. Choose one per page,
  don't mix within a section.
- Always let the CMS lazy-load (it does by default) and keep hero images under
  ~300KB.

**Layout**
- One idea per section. Full-screen (`100vh`) sections work best when the page
  has few of them — a landing page of 2–4 full-screen "slides" is striking; ten
  of them is exhausting.
- Check every page at mobile width in the editor's device toggle before
  publishing; use per-breakpoint overrides (Layout tab shows "tablet/mobile
  overrides") rather than separate pages.
- Vertical centering (recipe 1) pairs well with `textAlign: center` typography
  on the same block.

**Motion**
- Entrance animations: max one style per page (e.g. fade-up), 300–600ms.
- Hover effects on cards (Card Effects panel) should be subtle: small lift or
  image reveal — not both plus a filter.

---

*See also: `BLOCK-CONTRACT.md` for how block styles are rendered,
`RESPONSIVE-OVERRIDES.md` for per-breakpoint values, `THEME-SYSTEM.md` for
theme tokens.*
