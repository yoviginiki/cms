# Spread pattern vocabulary

This file is the bridge between editorial language and Magazine editor block data. Every
flatplan slot names exactly one pattern from this catalog; the generator composes spreads
only from these. Pattern names are a strict vocabulary — the flatplan validator rejects
names not listed here.

## Geometry conventions

- Page: A4 portrait, **595 × 842 pt**, margins 36pt all round, live area 523 × 770 pt.
- Grid: **12 columns per page**, 12pt gutters (column ≈ 32.6pt). Elements are placed in
  column spans; vertical position described in thirds (top / middle / bottom).
- A spread = **verso (left) + recto (right)** page, one canvas. The cover is a single
  recto page.
- Images may bleed to page edges (x/y beyond margins, to 0 or 595/842). Text never bleeds.
- Every element carries `style.layout` (x, y in pt; width/height in pt; rotation; zIndex).

## Publish-safe blocks (the only blocks patterns may emit)

Verified against the palette and `DtpRenderService`:

- **Text family** (publish as text with typography): `text_frame`, `headline_frame`,
  `caption_frame`, `footnote_frame`, `marginalia_frame`, `running_header`.
  Text frames support `columns`/`columnGap` in-frame and thread via `linkedNextFrameId`.
- **Quote**: `pullquote_frame` (html + attribution).
- **Image family**: `image_frame`, `fullbleed_image`, `background_image`,
  `circular_image` (src, alt, caption, fitMode, focalPoint, opacity).
- **Shape family**: `rectangle`, `ellipse`, `gradient_overlay`, `line`, `decorative_rule`.
- **Data**: `table_frame` (rows/columns).
- **Media**: `video_frame`, `audio_player` (only when the material inventory contains them).
- **Structure**: `page_number`, `running_header` (normally supplied by master pages — the
  generator does not place them per spread).
- **Special**: `text_path` (display text on arc/circle/wave — poster openers only).

**Never emit:** `chart_frame`, `gallery_frame`, `infographic_number`,
`progress_indicator`, `accordion_frame`, `slidein_panel`, `tooltip_trigger`, `button`,
`hotspot`, `embed_frame`, `svg_icon`, `component_instance` — these are editor palette
items without a static publish path. Charts become tables or typographic stats; galleries
become grids of individual `image_frame`s.

Every image element must have `alt`; evidence images must have `caption` (use a
`caption_frame` when the caption needs its own position).

## Rhythm weight

Each pattern carries a weight the flatplan uses for pacing: **loud** (visual peak),
**medium**, **quiet** (recovery). Never plan three louds in a row; never three quiets.

---

## Cover treatments (single page, not spreads)

### `cover-image`
One photograph owns the page. — Blocks: `background_image` (full page, focal point set to
the image's subject), `gradient_overlay` (bottom or top third, for legibility only),
`headline_frame` (issue title, in the image's quiet zone), up to 3 small `text_frame`
cover lines, stacked, 3-col span. — Use when the inventory has one unmistakably strong
image. Weight: loud.

### `cover-type`
The title is the image. — Blocks: `rectangle` (full-page ground, flat color),
`headline_frame` at poster scale (8–12 col span, up to two lines), one `text_frame`
subtitle, `decorative_rule`. Optional small `image_frame` (≤3 cols) as a stamp. — Use when
no image is strong enough to carry a cover, or the issue's idea is verbal. Weight: loud.

---

## Openers

### `full-bleed-opener` — weight: loud
Feature opener, image-led. Verso+recto: one `fullbleed_image` across the entire spread
(1190 × 842, bleed all edges), `gradient_overlay` only if type must sit on a busy area,
`headline_frame` in the image's quiet zone (4–7 cols), `text_frame` standfirst beneath it
(3–4 cols), `caption_frame` bottom corner. Body text starts next spread. — Dominant:
the image. One gesture: the type stays small and obedient. Use when: the assigned image
is the strongest in inventory and survives 2:1 crop.

### `poster-type-opener` — weight: loud
Feature opener, type-led. Verso: `headline_frame` at poster scale (10–12 cols, top two
thirds, may rotate −4–0°), `text_frame` standfirst bottom third (4 cols). Recto: body
starts — `text_frame` 2×4-col threaded columns, optional small `image_frame` (3–4 cols,
top). *Arc variant (art-culture openers only):* the headline renders as `text_path`
(arc-up/wave), everything else plain. — Dominant: the headline. Use when: no image can
carry the opener, or the title is the strongest material.

### `portrait-profile` — weight: loud
Interview/profile opener. Verso: `image_frame` portrait, 12 cols × full height (bleed
left/top/bottom), subject's eyeline pointing to recto; `caption_frame` bottom. Recto:
`headline_frame` (subject's name or the line, 6–8 cols, top third), `text_frame`
standfirst (4 cols), `text_frame` intro/body (4-col span, threaded onward). — Dominant:
the portrait. Use when: opening any interview or profile with a usable portrait.

---

## Text wells

### `text-well-two-column` — weight: quiet
Essay rhythm. Each page: `text_frame` with `columns: 2` (8-col span, centered, full
height), threaded verso→recto. Optional `marginalia_frame` in the outer 2-col margin
zone, one per spread max. — Dominant: the text block itself. Use for: long-form body
spreads in essays, politics, interviews' intro spreads.

### `text-well-three-column` — weight: medium
News/analysis rhythm. Each page: `text_frame` with `columns: 3` (12-col span, full
height), threaded across the spread; one `pullquote_frame` (4 cols, middle third of one
page) OR one `image_frame` (4–6 cols with `caption_frame`) — never both. `decorative_rule`
under a `text_frame` kicker, top. — Dominant: pull quote or image. Use for: dense feature
body in politics/business.

### `sidebar-feature` — weight: medium
Body + boxed aside. Verso: `text_frame` 2-col body (8 cols) threaded. Recto: `text_frame`
2-col body (8 cols, threaded from verso); sidebar = `rectangle` (4 cols, full height,
tinted ground) + `text_frame` (headline + 100–150 words) + `decorative_rule` on top of it.
— Dominant: the sidebar box. Use for: business features, service asides, "what this
means" panels.

### `quiet-single-column` — weight: quiet
The recovery spread. Each page: one `text_frame`, single column, 5–6 col span, centered,
generous leading; nothing else except an optional `decorative_rule` at top. — Dominant:
whitespace. Use: after any loud spread; for reflective essays; interview intros; endings.

---

## Image spreads

### `artwork-plate` — weight: medium
Gallery-wall spread. Verso: one `image_frame` (artwork, complete, 8–10 cols, centered on
white, NO bleed, NO overlay) + `caption_frame` beneath (artist, title, year, medium,
credit). Recto: either a second plate treated identically, or `text_frame` single column
(5 cols) of wall text. — Dominant: the artwork. Hard rules from art-culture.md apply:
never crop, always credit. Use for: art features, portfolio pieces.

### `image-grid-quartet` — weight: medium
Series spread. Four `image_frame`s in a 2×2 grid across the spread (each ≈5 cols wide,
equal sizes, 12pt gaps), one shared `caption_frame` strip beneath (numbered captions),
`headline_frame` kicker top verso (4 cols). Built from individual image frames — never
`gallery_frame`. — Dominant: the grid as one unit. Use for: portfolios, before/after
series, collections. Needs 4 images of comparable orientation.

### `image-interruption` — weight: loud
The pause in a long text. One `fullbleed_image` across the full spread OR across one full
page + 4 cols of the other; remaining columns empty (air) except a `caption_frame`. No
body text on this spread. — Dominant: the image. Use: once per 3–4 pages of continuous
text in features; atmosphere images only.

### `image-evidence-pair` — weight: medium
Two documents side by side. Verso and recto each: `image_frame` (7–9 cols, top two
thirds, no bleed) + `caption_frame` beneath with full sourcing; `text_frame` (4 cols)
linking commentary on one page only. — Dominant: the pairing (reads as one comparison).
Use for: politics/reportage evidence, comparisons, then-and-now.

---

## Data spreads

### `stat-punch` — weight: loud
One number owns the spread. Verso: the stat as a `headline_frame` at poster scale
(digits, 8–12 cols), `text_frame` beneath it (unit + one-sentence context, 4 cols),
`caption_frame` source line, `decorative_rule`. Recto: `text_frame` 2-col body (8 cols)
carrying the argument. Built typographically — never `infographic_number`. — Dominant:
the number. Use when: one figure genuinely astonishes. One stat only; six stats are a
table.

### `data-evidence` — weight: medium
The numbers spread. One `table_frame` (8–10 cols, middle of one page) with a
`headline_frame` finding-as-title above it ("Margins doubled since 2023") and
`caption_frame` source line beneath; facing page `text_frame` 2–3 col body. Charts from
material render as tables or as a `stat-punch`-style typographic figure — never
`chart_frame`. — Dominant: the table. Use for: business/politics data pieces.

### `document-evidence` — weight: medium
Timeline / dossier spread. A sequence of 3–5 short `text_frame` entries down the spread
(each 4–5 cols: date in bold, 2–3 lines of text), connected by a vertical `line` element,
optional small `image_frame`s (3 cols) beside entries with `caption_frame`s;
`headline_frame` kicker top. — Dominant: the sequence. Use for: chronologies, case
files, how-we-got-here recaps.

---

## Q&A spreads

### `qa-alternating` — weight: medium
The interview workhorse. Each page: `text_frame` with `columns: 2` (10-col span, full
height), threaded across the spread; Q&A formatting inside the text (questions bold,
answers regular — see interview.md). One `pullquote_frame` (5 cols, middle third, one
page) most spreads; every second or third spread swap it for a small `image_frame` +
`caption_frame`. — Dominant: the pull quote (or image). Use for: interview body.

### `qa-rapid-fire` — weight: medium
The fast passage. One page: rapid exchanges as a `text_frame` single column (6 cols),
tight leading, each Q one line bold + A one line regular. Facing page: `image_frame`
portrait detail (8+ cols, may bleed) + `caption_frame`. — Dominant: the portrait.
Use for: short-question sequences; palate cleanser inside long interviews.

### `quote-beat` — weight: loud
The emotional beat. Verso: `image_frame` portrait (8–12 cols, may bleed). Recto: one
`pullquote_frame` at display scale (7–8 cols, middle third), nothing else but air and an
optional `decorative_rule`. — Dominant: the quote (image supports). Use: once per
interview, at its strongest line; also as an interview's closing spread.

---

## Front-of-book / item spreads

### `fob-stack` — weight: medium
The classic FOB page pair. Each page: `headline_frame` section marker (3 cols, top);
3–4 items stacked, each item = `text_frame` (headline bold + 40–80 words, 7 cols) with
optional `image_frame` (4 cols) beside it; `line` rules between items. — Dominant: the
top item (largest image). Use for: news-in-brief, openers of FOB, letters.

### `item-mosaic` — weight: loud
The abundance spread. 5–7 items across the spread at varied scale: one anchor item
(double size: `image_frame` 6–8 cols + `text_frame`) placed top-verso or center, satellite
items (each `image_frame` 3–4 cols + `text_frame` 3–4 cols) around it; `rectangle` tints
behind 1–2 items for variety; `line` rules as needed. — Dominant: the anchor item. Use
for: lifestyle FOB, product/place roundups, "the list".

### `how-to-object` — weight: medium
Service journalism as a designed object. Verso: `image_frame` (the finished thing, 8–12
cols, may bleed) + `caption_frame`. Recto: `headline_frame` (the promise, 6 cols),
numbered steps as a `text_frame` (5–6 cols; each step: bold number + short imperative
sentence), requirements box = `rectangle` (4 cols, tinted) + `text_frame` scannable list;
`caption_frame` time/cost line. — Dominant: the finished-thing image. Use for: recipes,
how-tos, itineraries.

---

## Closers

### `closer-colophon` — weight: quiet
The deliberate ending. One page (usually recto): a single small `image_frame` (4–5 cols,
centered, middle third) OR a final `pullquote_frame`; `text_frame` 3–4 lines beneath
(the final thought / contributor note / colophon); `decorative_rule`. Facing page may be
empty ground (`rectangle`, flat tint) or carry the masthead as a `text_frame` column.
— Dominant: whitespace. Use: the last spread of every issue, no exceptions.
