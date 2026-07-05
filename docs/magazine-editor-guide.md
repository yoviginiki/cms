# Stillopress Magazine Editor — User Guide

The magazine editor is a desktop-publishing surface: fixed pages measured in
points, frames you place freely, and body text that *flows* through linked
frames like InDesign — with publishing to web, PDF and a standalone ZIP.

Open it from a magazine issue → **DTP Editor**.

---

## 1. The basics

**Pages** live in the left navigator (schematic thumbnails show real layouts).
**Elements** come from the **+ Add** tab: text frames, headlines, pull quotes,
captions, footnotes, images (rectangular / circular / polygon / full-bleed),
shapes, lines, tables, video, audio, text-on-path, page numbers and more.

- Click to select · drag to move · 8 handles resize · double-click text to edit
- **Marquee**: drag on empty canvas to select many
- **Alt+click**: select the element *behind* the one under the cursor
- **Alt+drag**: duplicate while dragging
- **Right-click**: context menu (copy/paste, group, z-order, lock, delete)
- **?** shows the full keyboard cheat-sheet at any time

Everything is undoable: **Ctrl+Z / Ctrl+Y**, one step per gesture, 50 deep.

## 2. Text that flows

Body text lives in **threads**: chains of linked text frames. Type or paste
into a frame — when it overflows, saving auto-creates continuation frames and
pages. The engine breaks at word boundaries, keeps at least two lines together
across breaks (no widows/orphans), keeps headings attached to their text, and
never loses a word — the whole story is always reconstructable from its frames.

- The **red badge** on a frame means overset text; click the **port arrows** to
  jump along the chain.
- **Pasting from Word / Google Docs / the web** is cleaned automatically:
  junk markup dies, bold/italic/headings/lists/figures survive (Word's fake
  numbered lists become real lists).
- **Big pastes** (1,500+ words) open a dialog: choose columns and map
  H1/H2/H3 onto your paragraph styles, then *Insert & flow* — a 10,000-word
  article paginates in well under a second.
- **Images**: drag a file onto the page or paste one — it uploads through the
  asset library (WebP variants included) and lands as a frame.

## 3. Runaround (text wrap)

Select any element and set **Text Wrap** in Props:

| Type | Effect |
|---|---|
| Bounding box | text wraps the rectangle (side-wrap when ≥45% width remains) |
| **Object shape** | text hugs the image's actual silhouette — the editor traces the alpha channel (use PNG/WebP cutouts) |
| Jump | text always skips below the object |

Offsets inflate the wrap. Contour tracing shows band count and a *Re-trace*
button after you swap the image.

## 4. Masters, sections & page furniture

**Masters** (left panel) are template pages: folios, running heads, background
art. Assign per page; *verso/recto* masters apply only to left/right pages.

- **Based on…** — a master can inherit another master's elements (base
  renders underneath). Change the base folio once, every derived master updates.
- **Detach master to this page** copies everything locally for one-off pages.
- A master text frame marked **Primary text frame** is auto-instantiated on
  new pages and joins the body thread.

**Page numbers** resolve per page automatically. In the page panel, **Start a
numbering section here** restarts numbering with its own format — front matter
in `i, ii, iii`, body restarting at `1`, exactly like print.

## 5. Precision: guides, grids, snapping

- **Rulers**: drag *out of* a ruler to drop a guide; drag a guide to move it,
  off the page to delete it; edit positions numerically in the page panel.
- **Snapping** (magnet icon): one global switch, per-source toggles in the ▾
  menu — grid, guides, margins, other objects, baseline grid.
- **Pasteboard**: park elements on the dark apron beside the page; they never
  publish and never accidentally jump to the next page.
- **Transform panel** accepts math: `+10`, `*2`, `/3`; the 9-point proxy sets
  which corner stays fixed when you resize numerically.
- **Group/ungroup**: Ctrl+G / Ctrl+Shift+G — moving moves all, resizing scales
  children proportionally.
- **Step & repeat** (Props): N copies at a fixed offset.

## 6. Editorial tools

- **Find & Replace** — Ctrl+F. Markup-safe; matches that span two frames of a
  story are found and flagged (*spans frames*) with replace disabled.
- **Footnotes** — while editing text press **Ctrl+Alt+F**: numbered marker at
  the caret, note added to a page-bottom block the body text flows around.
- **Styles** — create paragraph/character styles from a selection; big pastes
  can map headings onto them.
- **Color** everywhere offers your **theme-token swatches**, recent colors and
  a screen **eyedropper** (💧).
- **Preflight** (Issue tab) — overset stories, empty frames, missing images or
  alt text, parked elements: every row jumps to the problem.
- **Preview mode** — press **W** to see the page exactly as readers will.

## 7. Never lose work

- **Autosave** runs every 30 s when there are changes (see “auto HH:MM” in the
  toolbar); it stays out of your way while you type.
- **Versions** (Issue tab): every save snapshots the previous state (last 20).
  *Restore* any of them — restoring snapshots the current state first, so even
  a restore is reversible.
- Deleting a library asset that a magazine uses warns you first — magazines
  are wired into the CMS dependency graph like every other content type.

## 8. The reader (Viewer)

Configure in the **Issue tab**:

- **Mode**: Book (page-flip with paper curl), Vertical scroll, Presentation —
  readers can switch; you choose the default.
- **Colors**: viewer background + arrows/controls color.
- **Side banners**: branding or **paid ads** — image + click-through link
  (`rel=sponsored`); click counts appear right below as *Banner clicks*.
- **Audio player**: optional playlist readers can play while reading (never
  autostarts).

Reader UX built in: fading top bar and controls when idle, fullscreen (**F**),
thumbnail strip (**G**), keyboard + swipe, page-turn announcements for screen
readers, reading-order narration, reduced-motion support, no-JS fallback in
scroll mode.

**In-page media**: video frames (YouTube/Vimeo → privacy embeds, or direct
`.mp4`) and audio frames publish as real players.

## 9. Publishing & export

| Output | How | Notes |
|---|---|---|
| Site publish | normal site deploy | published issues become **static** `/magazine/…` pages on your domain (in the sitemap) |
| **PDF** | toolbar → PDF | queue-rendered, WYSIWYG |
| **Print PDF** | toolbar → *+marks* | bleed sheets + crop marks; spread images print both halves |
| **ZIP** | toolbar → ZIP | fully standalone: extract on *any* website, works with zero CMS — including ad-click reporting back to you |

## 10. Keyboard reference

Press **?** in the editor. Highlights: `V T I R E L` tools · `W` preview ·
`Ctrl+Z/Y` undo/redo · `Ctrl+D` duplicate · `Ctrl+G` group · `Ctrl+F` find ·
`Ctrl+Alt+F` footnote · arrows nudge (Shift = 10pt) · `Del` delete.
Reader: `←/→` pages · `F` fullscreen · `G` thumbnails.

---

*Developer docs: `magazine-editor-acceptance.md` (capability matrix + how to
run every test gate) and `magazine-editor-audit.md` (architecture history).*
