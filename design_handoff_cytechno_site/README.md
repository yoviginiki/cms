# Handoff: Cytechno → ensodo — Full Multi-Page Site (Track H CMS)

## Overview
This package expands the single-page cytechno.com into a full multi-page studio site. It is the **visual spec + block backlog** that drives the Track H CMS rebuild. It defines **13 templates** sharing one header/nav + footer, plus a dedicated **Spec / Block Backlog** screen that maps every section to a CMS block and resolves the structured-content question.

**Studio:** Cybertechnology — secure digital infrastructure, Sofia, Bulgaria, est. 2004. Founder & CEO Nikolay Petrov.

## ⚠️ Target stack — non-negotiable
> **Reproduce in the Blade static-publish stack. No SSR. Preserve static publish + PageSpeed 100.**

Every template below must render to flat, statically-published HTML. The React/JSX in this bundle is a **prototype of look + behavior only** — do not ship it. Re-express each template as Blade partials/components + blocks per `THEME-PORTING-GUIDE.md`. This work is gated behind Track 0 (builder hierarchy), blocks, and the theme engine — use this output as the spec that drives the **block backlog**.

## About the Design Files
The files in `design/` are **design references created in HTML/React** — a clickable prototype showing intended look and behavior, **not production code to copy**. The task is to **recreate these designs in the Blade static stack** using the project's established block model and theme engine. The JSX component split (shared / pages / data) is an authoring convenience, not a prescribed architecture.

- `design/Cytechno Site.html` — entry; loads fonts, CSS, and the JSX bundle via Babel (prototype only).
- `design/src/styles.css` — **the real design system.** All tokens, components, and layout rules live here. This is the most directly portable artifact — translate its rules to your theme CSS.
- `design/src/data.jsx` — all content (services, projects, blog, ideas, products) with the exact placeholder copy.
- `design/src/shared.jsx` — Header/nav, Footer, eyebrow, buttons, placeholders, cards, CTA band.
- `design/src/pages-main.jsx` — Home, About, Services landing, Single Service.
- `design/src/pages-work.jsx` — Portfolio listing/single, Blog listing/single, prose renderer.
- `design/src/pages-more.jsx` — Ideas listing/single, Products listing/single, Contacts.
- `design/src/spec.jsx` — the Spec / Block Backlog screen (template→block map + verdict).
- `design/src/app.jsx` — hash router (reference for the sitemap/URL structure).

## Fidelity
**High-fidelity.** Final colors, typography, spacing, hairlines, and interactions are intentional and should be reproduced precisely. Imagery is the deliberate exception: all images are **striped placeholders labelled with what belongs there** (e.g. `PROJECT · INVESTBG · LEAD IMAGE`, `MAP · SOFIA STUDIO LOCATION`). Swap in real assets when supplied.

---

## Design Tokens

### Color
| Token | Hex | Use |
|---|---|---|
| `--red` | `#C81E1E` | Single accent — links/hover, stat numbers, eyebrow dash, primary buttons, category labels |
| `--red-ink` | `#A81818` | Inline `code`, hover-darken |
| `--ink` | `#141414` | Primary text, solid buttons, active nav |
| `--ink-2` | `#5b5b5b` | Body / secondary text |
| `--ink-3` | `#8c8c8c` | Muted labels, meta |
| `--line` | `#e4e3e1` | Hairline borders / dividers (1px) |
| `--line-strong` | `#cdccc9` | Stronger borders (segmented controls, inputs, tags) |
| `--bg` | `#ffffff` | Base background |
| `--bg-alt` | `#f1f1ef` | Alternating section bg + hero bg |
| `--bg-dark` | `#0e0e0e` | Footer + dark CTA band |

No other colors. **Zero border-radius everywhere. No shadows anywhere.** Borders + whitespace do all separation.

### Typography
- **Display:** `Barlow Condensed`, weight **700**, `text-transform: uppercase`, `line-height: .9–.94`, letter-spacing ~`.005em` (tight). Google Fonts weights loaded: 500/600/700.
- **Body / UI:** `Barlow`, weights **400/500/600**. Base `17px`, `line-height 1.62`.
- **Mono (placeholder labels, code):** `ui-monospace, monospace`.

| Role | Family / weight | Size | Notes |
|---|---|---|---|
| Home hero H1 | Barlow Condensed 700 | `clamp(2.8rem, 8.4vw, 7.4rem)` | uppercase, `line-height .9`, `max-width 14ch`; "SECURE DIGITAL" in `--red` |
| Interior page-hero H1 | Barlow Condensed 700 | `clamp(2.2rem, 4.8vw, 3.9rem)` | uppercase, `max-width 22ch` |
| Section title | Barlow Condensed 700 | `clamp(2rem, 4.4vw, 3.4rem)` | uppercase |
| Card / row title | Barlow Condensed 700 | `1.32–1.7rem` | uppercase |
| Eyebrow label | Barlow 600 | `.7rem` | `letter-spacing .22em`, uppercase, **leading 26px red rule** (`::before`) |
| Lead paragraph | Barlow 400 | `clamp(1.05rem, 1.6vw, 1.3rem)` | color `--ink-2` |
| Body | Barlow 400 | `~1rem` | color `--ink-2` |
| Prose body | Barlow 400 | `1.08rem` | `line-height 1.72`, color `#2c2c2c`, `max-width 68ch` |
| Stat number | Barlow Condensed 700 | `2.5rem` | color `--red` |
| Button / nav / meta | Barlow 600 | `.72–.78rem` | uppercase, `letter-spacing .13–.16em` |
| Price | Barlow Condensed 700 | `1.6–2.4rem` | `--ink`; `.free` variant = `--red` |

### Spacing / layout
- Content max-width: `--maxw: 1320px`, centered.
- Horizontal page padding: `--pad: clamp(20px, 5vw, 72px)`.
- Section vertical padding: `clamp(56px, 8vw, 118px)` (tight variant `clamp(40px, 5vw, 72px)`).
- Grid gap: `clamp(20px, 2.4vw, 34px)`. Grids: `cols-2/3/4` → collapse to 2 at ≤980px, 1 at ≤640px.
- Sticky header height: `--nav-h: 74px`, white w/ `backdrop-filter: blur(8px)` + 1px bottom border.

### Motion
- Page enter: `.fadein` — `opacity 0→1` + `translateY(10px)→0`, `0.5s ease both`. (For static/Blade: make end-state the base style; gate the entrance on a JS-added class so no-JS/PageSpeed sees content. Respect `prefers-reduced-motion`.)
- Hover transitions: `.16s–.25s ease` on color/background/border/transform. Arrows (`.arw`) translate `+4–5px` on hover. Cards: border `--line`→`--ink`, title→red, image gains a red `multiply` overlay (`opacity 0→1`).

---

## Sitemap & URL structure
Hash routes in the prototype map 1:1 to real paths:

| Prototype route | Real path | Template | CMS type |
|---|---|---|---|
| `#/` | `/` | Home (landing) | Page (block editor) — ⚠ hits BUG-001 on homepage setting |
| `#/about` | `/about` | About | Page |
| `#/services` | `/services` | Services landing | Page (landing) + Services category |
| `#/services/:slug` | `/services/:slug` | Single Service | Post (Services) |
| `#/portfolio` | `/portfolio` | Portfolio listing | Portfolio category |
| `#/portfolio/:slug` | `/portfolio/:slug` | Single Project | Post (Portfolio) |
| `#/blog` | `/blog` | Blog listing | Blog category |
| `#/blog/:slug` | `/blog/:slug` | Single Post | Post (Blog) |
| `#/ideas` | `/ideas` | Ideas listing | Ideas category |
| `#/ideas/:slug` | `/ideas/:slug` | Single Idea | Post (Ideas) |
| `#/products` | `/products` | Products listing | Products category |
| `#/products/:slug` | `/products/:slug` | Single Product | Post (Products) |
| `#/contacts` | `/contacts` | Contacts | Page (block editor) |
| `#/spec` | (internal spec, not a public page) | Block Backlog | — design doc only |

**Nav order:** About · Services · Portfolio · Blog · Ideas · Products · **Contact** (red-outline button). Logo (ring mark + "CYBER TECHNOLOGY" / tagline "Secure · Scalable · Built to Last") links home. Active item = ink color + 2px red underline. Mobile (≤920px): hamburger reveals a full-width stacked menu.

---

## Shared components

### Header (`.site-head`)
Sticky, white/`blur(8px)`, 1px bottom border, 74px tall. Brand left (26px ring `.mark` = a `border-radius:50%` square with two transparent borders rotated −45°), nav right. CTA "Contact" is a red-outline button (fills red on hover).

### Footer (`.site-foot`)
`--bg-dark`, 4-column grid: brand blurb · "Studio" links · "Thinking" links · "Contact" details. Bottom bar: `© 2026 Cybertechnology · Nikolay Petrov, CEO` + a link to the Design Spec & Block Backlog.

### Reusable blocks
- **Eyebrow** `— LABEL` — 26px red rule + uppercase letter-spaced label.
- **Button variants:** `.btn--primary` (red outline→fill), `.btn--solid` (ink→red), `.btn--light` (for dark bg), `.btn--ghost` (text + arrow). All zero-radius, uppercase, `.16em` tracking.
- **Placeholder** `.ph` — diagonal grayscale stripe pattern + bottom-left monospace label via `data-label`. Ratios: `r1 r43 r32 r169 r219`. `.dark` variant for dark sections.
- **Stat grid** `.stat-grid` — 2×2 bordered cells, big red numbers + uppercase caption.
- **Numbered row-list** `.rowlist`/`.rowitem` — `[num | title | description | arrow]` grid, hairline rows, hover tints row + reddens title + slides arrow.
- **Project/Product cards** `.card` — bordered, image (with hover red-multiply overlay), category label (red), condensed title, body, meta link/price.
- **Article rows** `.artrow` — `[date | title+excerpt | arrow]` for Blog/Ideas listings.
- **Prose** `.prose` — article body: `p`, `h2` (condensed uppercase), `blockquote` (3px red left rule, condensed), `ul` with red-dash bullets, `max-width 68ch`.
- **CTA band** — centered eyebrow + huge condensed headline + buttons; dark or alt-gray.
- **Free-software block** `.fs-block` — bordered callout `[text | button]`, used on About and Single Idea.

---

## Screens / Views (per-template composition)

> Exact placeholder copy lives in `design/src/data.jsx`. Reuse it verbatim unless the studio supplies updates.

1. **Home** — Hero (striped bg, eyebrow, "Engineering **Secure Digital** Infrastructure", lead, 2 buttons) → About teaser (2-col text + 2×2 stat grid `20+ / 150+ / Gov & Private / Long-Term` + Nikolay Petrov attribution) → Core capabilities (numbered row-list, first 3 services) → Selected projects (3-card grid) → Ideas + Blog teasers (two columns of article rows) → dark CTA band.
2. **About** — page-hero → Story & approach (text + image) → Values (4-col, red top-rule cards) → Free-software block → Leadership stats + attribution → CTA.
3. **Services landing** — page-hero → full services row-list (6) → "How we work" 4-step process grid → CTA.
4. **Single Service** — service hero (eyebrow `Service · NN`, title, summary) → "What's included" typed feature-list → Approach (text + diagram placeholder) → Related work (3 cards) → CTA.
5. **Portfolio listing** — page-hero → **sector filter** (segmented control: All + distinct sectors) + live count → project grid (9). Filter is client-side over a taxonomy field.
6. **Single Project** — hero (category, title, meta: Client / Year / Sector) → full-width lead image → Overview + tags / Challenge·Approach·Outcome → gallery (3 screens + full-width) → "Next project" row → CTA.
7. **Blog listing** — page-hero → article rows (date+read / title / excerpt) → CTA.
8. **Single Post** — centered hero (title, date · author · read) → prose body → author attribution → related posts (2).
9. **Ideas listing** — page-hero (visionary framing) → essay rows → CTA (links to Products).
10. **Single Idea** — centered hero → long-form prose → **"Supported by us as free software"** block linking to the relevant Product → author attribution → CTA.
11. **Products listing** — page-hero → **toolbar: filter by price (All / Free / Paid) + sort (Featured / Price ↑ / Price ↓)** + count → product grid (image, name, **price**, short desc). Includes an inline spec-note linking to the backlog. *(Currently visible controls with client-side static result — see structured-fields verdict.)*
12. **Single Product** — hero (category, name, lead image, **price**, CTA button) → Description → **structured feature list** (typed key/value pairs) → typed-CTA band (label/kind switch: "View repo" / "Try it" / "Contact us") → more products (3).
13. **Contacts** — page-hero → contact **form** (name / email / message, client-side validation) + "Direct" details (email, phone, studio, hours, map placeholder). Inline spec-note flags the missing `contact-form` block.

**Spec / Block Backlog screen** (`/spec`, internal) — legend (Exists / Missing backend / Architectural gap) + tallies → per-template section→block table (11 templates, 44 rows) → "New blocks to build" summary → the **(a) vs (b) verdict** panel.

---

## Interactions & Behavior
- **Routing:** hash → path table above. On route change, scroll to top instantly. `<main>` remounts per route (keyed) so entrance animation replays.
- **Nav:** active state by current section; mobile hamburger toggles `.nav.open` (slides down). Menu closes on navigation.
- **Cards/rows:** hover = border→ink, title→red, arrow slides, image red-multiply overlay.
- **Portfolio filter:** segmented buttons set active sector; list filters by the project's primary sector; count updates.
- **Products sort/filter:** `band ∈ {all, free, paid}` filters by price>0; `sort ∈ {featured, low, high}` orders by numeric price. **Static/client-side in the prototype** — see verdict for the real-stack implication.
- **Contact form validation (client-side):** name required; email must match `^[^@\s]+@[^@\s]+\.[^@\s]+$`; message ≥10 chars. Invalid fields get `.field.err` (red border) + a red message; valid submit swaps the form for a success panel addressed to the first name + email. In production this needs a real `contact-form` block + server endpoint/spam guard.

---

## State Management (prototype → production note)
- `route {section, slug}` from hash → becomes static routing/SSG paths.
- Portfolio: `filter` (sector string).
- Products: `sort`, `band` → **see verdict**: real price sort/filter requires a queryable typed field, not client JS over a static list.
- Contacts: `{name,email,message}`, `errors`, `sent` → real form needs backend submission.
- Listings (`SERVICES, PROJECTS, BLOG, IDEAS, PRODUCTS`) are static arrays in `data.jsx` → become category/post queries in the CMS.

---

## Block Backlog (the actionable output)

Status legend: **Exists/generic** (maps to a current block) · **Missing backend** (`MISSING_BACKEND` — build it) · **Architectural** (needs model/DB-level change).

**New blocks to build**
| Block | Status | Notes |
|---|---|---|
| `contact-form` | **MISSING_BACKEND** | Name/email/message + validation + spam guard + submission endpoint. Needed on Contacts. No equivalent exists today. |
| `product-card` / `product-hero` | **MISSING_BACKEND** | Renders image, name, price, short description. The *display* half of the Products problem. |
| `post-meta (typed)` | **ARCHITECTURAL** | Project client/year/sector and post author/date as **queryable fields**, not free text. |
| `relation field` | **ARCHITECTURAL** | Idea → Product link ("supported as free software"). Cross-type reference the model can query. |

Per template, the full section→block map is rendered on `/spec` (and authored in `design/src/spec.jsx`). Cross-check each against `THEME-PORTING-GUIDE.md`'s component-to-block mapping; anything unmapped goes on the backlog.

### Structured-fields verdict — (a) block vs (b) content type
Products carry typed fields — **price, features[], cta** — that generic blocks don't model.

- **(a) Product-card block:** a block whose `data` JSON holds `{price, features[], cta}`. Fits the existing block-as-JSON model; no schema change. **Sufficient only if you just need to *display* the fields.**
- **(b) Structured content type — ✅ the verdict.** The listing must **sort and filter by price**, and editors must fill typed fields in the admin. That requires price to be a first-class **queryable field at the model/DB level**, with field schema, validation, and admin field-editing UI. This does **not exist** in today's `pages · posts · categories · blocks` model — it is the big architectural gap, gated behind Track 0.

**Recommended sequencing:** ship the display surfaces (cards/hero/feature-list) as block **(a)** immediately; promote Products to a structured content type **(b)** on the roadmap to satisfy the price sort/filter + admin field-editing requirement.

---

## Assets
- **Fonts:** Google Fonts — `Barlow` (400/500/600/700) + `Barlow Condensed` (500/600/700). Self-host in production for PageSpeed.
- **Images:** none yet — all are labelled striped placeholders (hero background, project lead images + gallery screens, product images, contact map). Replace `.ph[data-label]` elements with real media; labels state the intended content.
- **Logo:** the ring mark is pure CSS (`.mark`) — a rotated square with two transparent borders. Replace with the official wordmark/logo if one exists.
- **Icons:** none — arrows are text glyphs (`→`, `←`).

## Files
- `design/Cytechno Site.html` — entry (prototype)
- `design/src/styles.css` — **design system / source of truth for tokens & components**
- `design/src/data.jsx` — content + copy
- `design/src/shared.jsx`, `pages-main.jsx`, `pages-work.jsx`, `pages-more.jsx`, `spec.jsx`, `app.jsx` — templates + router (reference)

Open `Cytechno Site.html` in a browser to navigate the live prototype while implementing.
