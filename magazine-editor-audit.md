# MAGAZINE EDITOR — ARCHITECTURAL AUDIT & FULL GAP CLASSIFICATION

Session A deliverable (read-only audit, 2026-07-04). No files modified, no DB touched.
All paths relative to repo root. Line numbers verified against the current `master` working tree.

---

## EXECUTIVE SUMMARY

**Flow-engine verdict: REBUILD as a pure module behind the existing data model.** The
surrounding system (store CRUD, canvas interaction layer, API adapters, per-frame-slice
persistence contract) is sound and stays. Justification in Part 3.7.

**Why paste-large-text fails (one paragraph):** there is no paste handler at all; pasted
HTML lands in a contentEditable and is silently clipped by `overflow:hidden`. Flow only
runs on manual "Pour"/"Auto-flow" or on save (DTP editor only — the legacy editor wires
no flow whatsoever). When it does run, `autoFlowText` (a) skips every frame that already
has a `threadId` (a guard added in commit `7ef8d89` to stop duplicate text — it also
permanently exempts every poured frame from ever flowing again), (b) performs exactly one
split per save over a stale page snapshot, so the continuation frame receives ALL
remaining text, is born with a `threadId`, and is therefore skipped forever — text beyond
page 2 can never flow, and (c) can't split single-block content at all. The root cause is
the data model: content is **destructively sharded** across frames with no canonical
story text, so reflow-after-edit, cascading pagination, and un-thread merge are
*unrepresentable* in the current shape. That is why every fix loops.

**Structural finding:** the "magazine editor" is five surfaces, three persistence
stacks, and four divergent published renderers, sharing code unevenly. The self-declared
canonical contract (`types/magazineDocument.ts`, "MAG-P13") is dead code. The dominant
failure pattern across the whole checklist is **UI-first development**: panels exist and
write state that is then lost at one of four tiers (render → store → save payload →
published output). Of ~70 [core] checklist leaves, **only ~6 are HAVE end-to-end**.

---

## 0. SYSTEM MAP (read this before any fix)

Five editing surfaces:

| # | Surface | Route | Persistence | Published via |
|---|---------|-------|-------------|---------------|
| 1 | `pages/DtpEditorBeta.tsx` — **the live DTP editor** | `/sites/:siteId/magazine-issues/:issueId/dtp-editor` (App.tsx:113) | DTP tables (`magazine_spreads/dtp_pages/frames/layers`, migration `2026_05_19_000001`) via `DtpDocumentService` (atomic delete-and-recreate) | `MagazineViewController::showDtpIssue` → `DtpRenderService` → `dtp-preview.blade.php` |
| 2 | `pages/MagazineEditorV2.tsx` — legacy, still routed | `/sites/:siteId/magazines/:magazineId/edit` (App.tsx:84) | `magazines/magazine_pages/magazine_elements`; V2 state smuggled in `content._v2*` keys, coords converted pt→% (MagazineEditorV2.tsx:239-261) | `MagazineViewController::show` → `magazine.blade.php` (two internal JS branches: standard + flipbook, which disagree with each other) |
| 3 | PageEditor magazine mode (`pages/PageEditor.tsx:399-437`) | pages with `editor_mode='magazine'` | `mag_pages/mag_elements` (migration `2026_04_17_000003`) — the fullest schema | `MagazineViewController::showPage` (same blade) AND static `app/Domain/Publishing/Services/MagazineRenderer.php` via `BuildPageService.php:122-141` |
| 4 | `components/magazine/prototypes/dtp/DtpPrototypeShell.tsx` | `/sites/:siteId/magazine/dtp-prototype` (App.tsx:110) | none — mock data only (mockDocument.ts:5) | none |
| 5 | `components/editor/MagazineEditorCanvas.tsx` | PostEditor block canvas (PostEditor.tsx:456) | block pipeline — **not a magazine editor**, unrelated despite the name | block pipeline |

Surfaces 1–3 share ONE stack: `stores/magazineStore.ts` (1,340 ln), `MagazineCanvas.tsx`,
`MagElementRenderer.tsx`, `MagSelectionEngine.tsx`, `components/magazine/properties/*`.

**Dead/duplicated infrastructure (the bug factory):**
- `types/magazineDocument.ts` declares itself canonical ("All systems should produce or
  consume this shape", :1-12) with four adapters (:264-505) — imported ONLY by the debug
  tool `lib/dtpConsistencyChecker.ts`. DtpEditorBeta re-implements the same adapters
  divergently (`dtpApiToPages` :57-105, `pagesToDtpApi` :209-304; these preserve
  typography/metadata/threads, the magazineDocument.ts versions drop them). Latent bug:
  `safeUUID()` recurses into itself (magazineDocument.ts:139-148) — "works" only by
  overflowing the stack into its own catch.
- **Three flow implementations** (two dead): `components/magazine/TextThreading.ts`
  (disabled in commit `89d5841`, zero importers), `lib/textThreading.ts` (zero importers),
  and the live pair `continueTextToNextPage`/`autoFlowText` in magazineStore.ts (700-860,
  864-1029) — copy-pasted near-duplicates of each other.
- **Three snap/smart-guide engines:** `lib/smartGuides.ts` (live), `prototypes/dtp/snapEngine.ts`
  (richer, prototype-only), inline `computeGuides` in MagazineEditorCanvas.tsx:67-98.
- `lib/textWrap.ts` is a dead placeholder whose `generateWrapCSS` returns only CSS
  **comments** (:63-73). Zero importers.
- **Two toolbars, two tool states in the same editor:** `MagazineToolbar` writes
  `store.activeTool/showGrid/showGuides/showBaseline/snapEnabled` (magazineStore.ts:54-57,
  1207-1221) — **no canvas reads any of them**. `MagazineCanvas` uses `useMagSelection`'s
  private `activeTool`/`snapEnabled` (MagSelectionEngine.tsx:44-46) and its own local
  overlay toggles (MagazineCanvas.tsx:87-89) with a second embedded toolbar (:364-456).
  The top-toolbar tool buttons and Grid/Guides/Baseline toggles are UI no-ops.
- Server-side `TextFlowCalculator.php` exists with zero callers.

---

# PART 1 — FLOW ENGINE DEEP DIVE

## 1.1 Inventory

| File | Role | Reads | Writes |
|---|---|---|---|
| `stores/magazineStore.ts` :700-860 (`continueTextToNextPage`, "Pour") and :864-1029 (`autoFlowText`) | The two LIVE flow paths — near-duplicate copies of one algorithm | Zustand state + **live DOM** (hidden measurer div appended to `document.body`, `scrollHeight` reads: :719-759, :871-931) | Destructively rewrites source frame `data.content`, creates continuation frame, may create one page (:780-859, :939-1027) |
| `components/magazine/TextThreading.ts` | Original threading engine | live DOM | **DEAD** — zero importers; disabled in `89d5841`; comments at MagazineCanvas.tsx:6,337 |
| `lib/textThreading.ts` | Pure char-count-heuristic splitter, word-boundary | nothing | **DEAD** — zero importers |
| `lib/textWrap.ts` | Runaround exclusion calc | nothing | **DEAD** — emits CSS comments only |
| `MagazineCanvas.tsx` | contentEditable lifecycle (`editingId`, exitEditing flush :142-178, blur-save :233-244); passes `threadedContent={undefined}` (:645) — threading render path permanently off | React state, refs, `document.querySelector('[data-editing-id]')` | store via `onUpdateElement` |
| `MagElementRenderer.tsx` | Text frames `overflow:hidden` when not editing (:113,179-180) — **CSS clipping is the only overflow behavior**; overflow badge via `scrollHeight` + `setTimeout(500)` (:35-54); blur save deferred `setTimeout(100)` (:85-96); thread badges (:689-712) | live DOM | store via `onContentChange` |
| `DtpEditorBeta.tsx` | Save: flush contentEditable → `store.autoFlowText()` → serialize → PUT (:459-489); Pour/Auto-flow buttons (:842,848,777) | store, React Query | store, API |
| `MagazineEditorV2.tsx` | **Wires NO flow at all**: `<MagazineCanvas>` call omits `onContinueText` (:342-362) → the "Pour to Next Page" button rendered on every selected text frame (MagElementRenderer.tsx:675-682) is a silent no-op; save never calls `autoFlowText` | store, API | store, API |
| `properties/TextFramePanel.tsx` | UI for overflow/autoSize/columns/inset/verticalAlign + manual thread buttons (:144-191) | props | store — **overflow, autoSize, verticalAlign, columnRule are stored and never rendered/enforced anywhere** |
| Backend `DtpDocumentService.php` :108-136 | Atomic transactional delete-and-recreate save; max 200 pages/500 frames | — | DTP tables. No server-side flow |

## 1.2 Characterization

**a. Single path or emergent?** Since `89d5841`, flow is NOT emergent/reactive. Exactly
two imperative one-shot store actions (Pour, autoFlowText) — one algorithm, three
implementations (one dead), two live entry points. So the v1 hypothesis ("flow lives in
React effects") is **outdated**: the loop-causing mechanism today is the self-disabling
one-shot algorithm plus destructive persistence, not render-timing races.

**b. Measurement:** live-DOM hidden measurer (`position:fixed;top:-9999px` div,
`scrollHeight`). Columns approximated as *single-column height ≤ (frameH − padV) × cols*
(:731-735, :899-900) — never measures real column layout. Measurer copies only
fontFamily/fontSize/fontWeight/lineHeight (:737-740, :902-905); the renderer additionally
applies letterSpacing/textAlign/textTransform (MagElementRenderer.tsx:153-168) →
**measurer ≠ renderer drift**, off-by-lines splits. Font-loading state makes measurement
non-reproducible across sessions.

**c. Sync/async:** the flow math itself is fully synchronous within one store action.
Async hops sit *around* it in the edit-capture chain: blur `setTimeout(100)`
(MagElementRenderer.tsx:85-96); overflow badge `setTimeout(500)` (:52); save-time flush
awaits dynamic `import('dompurify')` (DtpEditorBeta.tsx:464-469); inline-image debounce
`setTimeout(300)` (:601); AssetPicker insert `requestAnimationFrame` (:1292); post-save
consistency check `setTimeout(100)` (:496). No ResizeObserver/rAF in flow math.

**d. Break unit & search:** **top-level block elements only** (paragraph granularity),
binary search on block count vs `scrollHeight` (:763-777, :927-937). Consequences:
a single oversized `<p>` can never split (Pour bails at :782; autoFlow `allBlocks.length
< 2 → continue` at :924); **top-level bare text nodes are silently deleted** during
split — `keepHtml`+`moveHtml` are rebuilt from element blocks only (:780-781, :939-940).
Never word-boundary.

**e. Persistence & idempotency:** per-frame slices ARE persisted, but flow is
**destructive** — the source frame's content is truncated and the remainder becomes the
continuation frame's own content (:847-857, :1000-1017). No canonical story text exists,
so re-editing frame 1 of a thread can never push text into frame 2. Recompute is
idempotent only by *avoidance*: `autoFlowText` skips every frame with a `threadId`
(:888). `autoFlowText` pushes **no undo snapshot** (contrast Pour at :702) — save-time
splitting is not undoable.

**f. Reflow triggers.** Present: Pour button, Auto-flow button, Save — DtpEditorBeta
only. **Missing:** typing, paste, frame move/resize, column change, typography change,
inset change, page ops, master assignment, undo/redo, unthreading. Hazardous: Auto-flow
+ Save = double run; unthreading a poured frame then saving re-splits it while the old
continuation still holds the moved text → **duplicated text** (nothing merges threads
back). Bonus defect: `updateElement` only touches the **current page**
(`updateCurrentPageElements`, :597-601), so `handleUnthread`'s cross-page renumber
(DtpEditorBeta.tsx:543-548) and the cross-page exitEditing flush
(MagazineCanvas.tsx:159-171) silently drop updates for off-page frames.

**g. Widow/orphan/keep-with-next/hyphenation:** modeled (`types/magazine.ts:115,123-124`)
and editable (MagTypographyPanel.tsx:246,366-378) but **inert** — zero
`hyphens/widows/orphans` CSS anywhere in renderer, measurer, or either publish path.
Keep-with-next: absent entirely. Blockquotes don't split across frames (atomic block) but
CAN split across CSS columns inside a frame (no `break-inside:avoid`).

## 1.3 Paste-10k-words trace — exact break points

1. Double-click → `editingId` set (MagazineCanvas.tsx:222-231); contentEditable mounted.
2. Ctrl+V: **no paste handler exists anywhere** (grep `onPaste`/`'paste'` in magazine
   components: zero hits). Browser default inserts raw clipboard HTML. No normalization,
   no sanitize until blur.
3. Blur: after `setTimeout(100)`, sanitize → `updateElement` → full text in ONE frame →
   re-render with `overflow:hidden` → **silent clipping**; only signal is the tiny red
   "+" badge.
4. Nothing paginates until Pour/Auto-flow/save (DtpEditorBeta). In MagazineEditorV2 there
   is **no path at all** (break point A).
5. On save, `autoFlowText` runs once. Break points in impact order:
   - **B — threaded frames never flow:** `if (frame.threadId) continue`
     (magazineStore.ts:888). Any previously-poured frame is permanently exempt. This is
     the `7ef8d89` guard against duplicate text — the fix that created the loop.
   - **C — no cascade:** one split per frame per save, iterating a stale `contentPages`
     snapshot (:877) while `pages` is rebuilt by `.map()` (:1001-1017). The continuation
     gets ALL remaining ~9k words, sized to one page (:982), born with a `threadId`
     (:994) → skipped forever by B. Text beyond page 2 can never flow, this save or any
     future one. Two overflowing frames on one page → **overlapping** continuations at
     the same Y.
   - **D — single-block content can't split** (:924, :776-782): one huge paragraph =
     permanent clip.
   - **E — top-level text nodes deleted** during split (§1.2d) — pasted plain-text
     fragments vanish. This is the pasted-text-loss bug class.
   - **F — measurement drift** (§1.2b): off-by-lines splits; multi-column fit
     over-estimated.
6. Viewer: `DtpRenderService.php` renders frames `overflow:hidden` (:328-335) — whatever
   the editor clipped, the public output clips identically. No server reflow.

**Symptom class verdict:** not a state race, not an untriggered effect. It is (A) missing
wiring in one editor + (B+C) an intentionally self-disabling, non-cascading, single-hop
split + (D) paragraph-only granularity + (E) genuine data loss.

---

# PART 2 — FULL CHECKLIST CLASSIFICATION

Legend: **HAVE** = works end-to-end (UI → store → persistence → published output where
applicable). **PARTIAL** = exists in some tier, broken/incomplete (missing tier named).
**MISSING** = absent. **CONFLICT** = divergent implementations. Citations abbreviated;
`mS` = magazineStore.ts, `MER` = MagElementRenderer.tsx, `MC` = MagazineCanvas.tsx,
`DEB` = DtpEditorBeta.tsx, `MEV2` = MagazineEditorV2.tsx, `DRS` = DtpRenderService.php,
`blade` = magazine.blade.php.

## M-A. DOCUMENT & PAGES

| Item | Verdict | Evidence |
|---|---|---|
| Page size presets / orientation / margins / bleed / column grid | **PARTIAL** + unit bug | Full PagePanel UI (PagePanel.tsx:9-176) wired both editors. **Presets are mm values (A4=210×297) injected into a points canvas (595×842)** — selecting "A4" yields a ~74×105mm page (MEV2:81-82 vs PagePanel.tsx:9-14; same mismatch in `mS.addPage` fallback :406-408). Bleed persisted on all 3 paths but **never rendered anywhere**. Page `columns` dropped by DTP save/load (DEB:95, :229-240). No px/mm unit selector. |
| Facing pages / spreads first-class | **PARTIAL/CONFLICT** | View-mode only: `viewMode 'spread'` + book pairing + spine (MC:487-534), `spanMode:'spread'` image overlays (MC:544-598). NOT a data model: persisted `side` always `'single'`, spreads are 1-page wrappers (DEB:225-240); pairing recomputed heuristically at render (DRS:131-168); no shared coordinate space; `spanMode` unpersisted on Path 1 and toggles unwired there (MEV2:342-362). Published spread images **clipped**: `buildPageStyle` ignores its `$hasSpreadImage` param, always `overflow:hidden` (DRS:328-335). Prototype models real spreads (SpreadCanvas.tsx:28-181) — divergent. |
| Add/delete/duplicate/rearrange pages; panel w/ thumbnails + drag-reorder | **PARTIAL/CONFLICT** | Store actions complete incl. duplicate w/ thread relink (mS:390-532); PageNavigator implements drag-reorder/duplicate/context menu/templates. **DtpEditorBeta wires all of it (DEB:736-755); MagazineEditorV2 wires only add/delete** — drag-reorder silently no-ops, template apply is a no-op (MEV2:332-338, PageNavigator.tsx:158). Thumbnails are abstract blobs, not live previews (PageNavigator.tsx:241-254). |
| Master/parent pages | **PARTIAL — destroyed on round-trip in BOTH live paths** | Store + canvas solid (mS:1261-1321; read-only 0.6-opacity composite MC:610-635). DTP: masters **filtered out of save** (DEB:478-479), recreated client-side with new UUIDs every load → `master_page_id` dangles; server never remaps it (DtpDocumentService.php:168). V2: `isMaster` not saved, load hardcodes `false` (MEV2:95) → **saved master reloads as a content page**; PagePanel master dropdown hardcoded to "None" (PagePanel.tsx:197-207). Verso/recto application, overrides, master-on-master: MISSING everywhere. Published output never composites masters; Path 3 would render them as extra pages (no `is_master` filter in MagazineViewController.php:78 / MagazineRenderer.php:21). |
| Sections & page numbering | **PARTIAL→MISSING** | `page_number` element with format/prefix/suffix/startAt (types/magazine.ts:236-241); editor ignores `format` (MER:586-587); master instances resolve per page (MC:616-619). Published: Path 1 maps `page_number`→`'shape'` (MEV2:201-208) → **black rectangle** (blade:511-514); Path 2 always bare decimal (DRS:295-299); Path 3 reads `$format` then never uses it (MagazineRenderer.php:196-201). No roman converter exists in the repo. Sections/start-at: MISSING. Viewer-level page chrome is a separate parallel feature (blade:519-580) — can double up with element numbering. |
| Per-document defaults | **PARTIAL→MISSING** | Base style = hardcoded `DEFAULT_TYPOGRAPHY` (types/magazine.ts:325-352). **Styles never persisted: both editors load `setDocument(pages, [])` (MEV2:122, DEB:394) and neither save payload includes `store.styles`.** Baseline settings per-page, persisted Paths 1/3, lost on Path 2. Default frame inset hardcoded (mS:228-233). |

## M-B. CANVAS, NAVIGATION & GUIDES

| Item | Verdict | Evidence |
|---|---|---|
| Zoom / pan | **PARTIAL** | %-steps, ctrl+wheel, fit-page, Alt/middle-drag pan (MC:38,249-254,305-333). Fit-spread/fit-width/zoom-to-selection/pinch: MISSING. Space-drag: MISSING in magazine canvas (exists in the block canvas — divergent interaction models). Top-toolbar "Fit" actually sets 100% (MagazineToolbar.tsx:91-97). |
| Rulers, drag-out guides, guide lock/clear/numeric | **MISSING** (live) / CONFLICT | No rulers in MagazineCanvas at all; prototype and block canvas each have their own (SpreadCanvas.tsx:49-92; MagazineEditorCanvas.tsx:404-423). No guide array exists in any model. No units. |
| Smart guides | **PARTIAL**, triplicated | Live: center/margin/edge snapping, 4px threshold (lib/smartGuides.ts:17-73; MagSelectionEngine.tsx:139-147). First-selected element only; **zero snapping on resize** (:156-177); equal-spacing hints MISSING; **cannot be disabled** (`snapEnabled` gates only the 8px grid snap :134-137). Prototype snapEngine.ts is better and unused. |
| Snapping toggles | **PARTIAL/CONFLICT** | One global toggle (Ctrl+;) gating only fixed 8px grid. Per-source toggles, baseline snap, guide snap: MISSING. `store.snapEnabled` vs `useMagSelection.snapEnabled` — two parallel flags, store one unread by canvas. |
| Baseline grid | **PARTIAL→broken** | Settings UI + persistence exist; canvas computes `baselineLines` (MC:352-359) **but never renders them** — no JSX consumer. Snapping: MISSING. |
| Preview mode | **MISSING** (live) | No in-canvas toggle; selection chrome can't be globally hidden. Prototype has a real mode switch (DtpPrototypeShell.tsx:43,253-256). DEB links out to server preview. |
| Pasteboard | **MISSING** (live) | Pages `overflow:hidden`; dragging past edge triggers move-to-adjacent-page (MagSelectionEngine.tsx:201-218). Prototype-only (SpreadCanvas.tsx:22-102). |
| Layout grids (modular) | **PARTIAL→broken** | Column guides computed (MC:340-349) **never rendered**; Columns toggle does nothing visible. Row grids MISSING. Store dot-grid renders nowhere in this canvas; toolbar tooltip promises snapping it can't do (MagazineToolbar.tsx:144). |

## M-C. OBJECTS, GEOMETRY & ORGANIZATION

| Item | Verdict | Evidence |
|---|---|---|
| Object types | **PARTIAL** | Palette = **38 types** (not 44; MagElementPalette.tsx:21-100). All render *something* in editor and round-trip data. Publish: text OK-ish, plain image OK; circular/polygon clip collapses to plain image (clip lost on reload too — `FRAME_TYPE_MAP` ignores `metadata._magType`, DEB:118-120 vs :297); shapes publish **black** on Path 1 (viewer reads `content.fill`, V2 writes `fillColor`, blade:511-514) and **default grey** on DTP (save writes no shape content, DEB:246-272 vs DRS:302-307); ellipses publish as rectangles; line ignores stroke props (DRS:224); video publishes empty (viewer wants `content.videoId`, editor saves `data.url`, blade:500-505); ~30 other types collapse to shape/empty-text. Pen/bezier: no pen tool (`freeform_path` default `path:''`). |
| Move/resize/rotate + numeric transform | **HAVE** (editor tier) | 8 handles, shift-aspect, rotate w/ 15° shift-snap, 1/10px nudges (MagSelectionEngine.tsx:111-195,263-274); TransformPanel x/y/w/h/rotation + proportion lock. Math entry ("+10"): MISSING. scaleX/scaleY in model, never editable/rendered. |
| Transform origin / 9-point proxy | **PARTIAL (cosmetic)** | Radio grid is local `useState` never read by any computation (TransformPanel.tsx:12-16,109-125). All transforms top-left. |
| Duplicate / step-and-repeat | **PARTIAL** | Ctrl+D + offset dup (MagSelectionEngine.tsx:251; mS:626-667). Alt-drag dup MISSING (alt-drag = pan). Step-and-repeat MISSING. |
| Align & distribute | **PARTIAL**, triplicated | To selection + to page (toggle) + distribute ≥3 (MEV2:462-497); to margins MISSING; numeric spacing MISSING. Duplicated verbatim in DEB (~:1047) and prototype snapEngine.ts:161-198. |
| Group/ungroup | **MISSING functionally** | `group`/`clipping_group` render as empty dashed placeholders (MER:629-643); `children[]/parentId` modeled, **no action ever creates children**; no group command anywhere. |
| Lock/hide | **PARTIAL** | Editor solid (toggles, badges, pointer-events); persisted both paths. DTP publish respects `visible` (DRS:84); **legacy publish renders hidden elements** (blade never checks). |
| Layers / z-arrange | **PARTIAL** | Flat per-page list w/ visibility/lock (MagLayersPanel.tsx:205-256); named layers absent (DTP `layers` API array passed through untouched). Only toFront/toBack exist (mS:1059-1089); one-step forward/backward MISSING; panel buttons labeled "Move up/down" actually call toFront/toBack (MEV2:610-613). |
| Multi-select / marquee / select-behind | **PARTIAL** | Shift-click, Ctrl+A, multi-drag HAVE. Marquee: state + render exist, nothing sets it ("future", MC:681-695). Select-behind MISSING. |
| Corner options | **PARTIAL** | Per-corner UI + editor render + persisted (FillStrokePanel.tsx:197-223; MER:130-133); dropped by both publish paths (style JSON unread by blade; DRS `buildFrameStyle` emits pos/size/z/rot only). |
| Object search/outline | **MISSING** | Palette search only. |

## M-D. TEXT & TYPOGRAPHY

| Item | Verdict | Evidence |
|---|---|---|
| Threaded frames w/ ports, navigate, add-linked, break/relink | **PARTIAL/CONFLICT** | Manual Start/Continue/Remove thread (TextFramePanel.tsx:144-191); one-shot Pour (see Part 1). Badges are non-interactive — no click-to-navigate (MER:689-712). Relink arbitrary order MISSING. **Pour button is a silent no-op in MagazineEditorV2** (unwired). Editing frame 1 never reflows frame 2 (destructive slices). |
| Columns + gutter; inset; vertical align | Columns/inset **HAVE end-to-end** (TextFramePanel.tsx:56-125; MER:163,171-175; blade:491-494; DRS ~:200); vertical align **PARTIAL** — persisted, **rendered nowhere**, no justify option. |
| Frame auto-size | **PARTIAL** | UI + persistence; no implementation reads it. |
| Overset indicator | **PARTIAL** | Red "+" badge per frame (MER:32-54,684-686) — not chain-aware (doesn't check last frame of thread). Auto-paginate affordance only in DEB. |
| Paragraph styles | **PARTIAL + internal CONFLICT** | Two competing systems: (a) 5 hardcoded presets (MagTypographyPanel.tsx:26-60), (b) `MagStyleDefinition` store + StylesPanel — created styles have `properties:{}`, **no editing UI**, `updateStyle` never called from UI, and **styles are never persisted** (see M-A). Panel covers font/size/leading/spacing/align/indents; renderer applies a subset (no indent, no space-before/after, no wordSpacing) (MER:153-168). Keep options: inputs exist, zero enforcement. Hyphenation checkbox: **zero `hyphens` CSS anywhere**. Theme tokens: **zero references in all magazine code** — hardcoded CURATED_FONTS (MagTypographyPanel.tsx:13-18). CONFLICT with the shared page-builder TypographyPanel (components/editor/properties/) — full duplicate, no sharing. |
| Character styles | **PARTIAL→MISSING** | Type exists; dropdown contains only "None" (MagTypographyPanel.tsx:408-417); all typography is frame-level; char-level only via execCommand B/I/U/S. baselineShift/case: model-only. |
| Style management (redefine, "+" indicator, copy/paste style) | **MISSING** | |
| H&J quality | **MISSING** | grep `hyphens|text-wrap|font-feature` in magazine code: zero. |
| Drop caps | **PARTIAL** | UI + persistence (lines/font/color; char-count missing); **never rendered** in editor or any publish path. |
| Lists | **PARTIAL/CONFLICT** | execCommand lists work **only in DEB** (RichTextToolbar not imported by MEV2 — V2 has no bold/list/heading UI at all). In styles: MISSING. |
| Text variables | **PARTIAL→broken on publish** | page_number: see M-A. running_header: custom text only, `source: page-title|section-name` never resolved (MER:590-592); publishes empty (DTP) or as shape (legacy). Issue/section variable MISSING. |
| Find & replace | **MISSING** | |
| OpenType / optical margin | **PARTIAL / MISSING** | Checkboxes persisted, never applied (no font-feature-settings anywhere). Optical margin: model field only. |
| Tabs/glyphs/placeholder/story editor/spell check | **MISSING** | |
| Footnotes / text on path | **MISSING** | `footnote_frame`/`marginalia_frame` are just preset text frames. |
| Content storage | — | Rich **HTML string** per frame (`TextFrameData.content`, types/magazine.ts:139-149), contentEditable + DOMPurify; V2 smuggles `_v2*` in content JSON; DTP puts typography in `metadata._typography`. Not structured runs. |
| Published typography parity | **BROKEN 3 ways** | Legacy blade drops ALL typography → published text is default Inter 16px black (blade:489-494). mag_pages path emits fontSize in **pt** where editor means **px** → ~33% larger (MagazineViewController.php:131-151). DTP path best parity but omits transform/spacing/indent/dropCap/verticalAlign and ignores frame `style` entirely; **no webfonts loaded in any viewer** (blade:50-52 Inter only; dtp-preview none) — chosen fonts silently fall back. |

## M-E. FLOW & LAYOUT AUTOMATION

| Item | Verdict | Evidence |
|---|---|---|
| Deterministic pure flow engine | **MISSING** (see Part 1) | Live engine impure, DOM-coupled, block-granular, destructive, self-disabling. No `(content, frameChain, styles) → placements` API anywhere. |
| Auto-pagination grow/shrink | **PARTIAL** | Grow: one page + one hop per invocation, DEB only (mS:948-966, :791-805). Cascade: MISSING (break point C). Shrink: **MISSING entirely** — nothing ever removes auto-created frames/pages. |
| Widow/orphan, keep-with-next, quote no-break | **PARTIAL (data-only)** | Zero enforcement (Part 1.2g). |
| Text wrap / runaround | **PARTIAL + CONFLICT — an illusion** | Full TextWrapPanel UI; **DTP save never serializes `el.textWrap`, load hardcodes default** (DEB:170, :242-299) — every edit discarded; **no renderer reads textWrap** (grep zero); lib/textWrap.ts dead. V2 *does* persist wrap (`_v2textWrap`) — neither renders it. What exists: continuation frames placed below images (mS:816-830, :974-981) — vertical avoidance, not runaround. "Fix Position" tooltip claims wrap it doesn't do (DEB:862). |
| Anchored/inline objects | **PARTIAL** | Inline `<img>` via RichTextToolbar → float/width styles in frame HTML; survives publish (DRS:39-59). DEB-only. No captions on anchored images, no anchored frames. |
| Primary text frame on masters | **MISSING** | Masters unpersisted; `addPage` copies `masterPageId` only, never instantiates frames (mS:390-440). |
| Tables | **PARTIAL (near-missing)** | Type + ≤3-row read-only preview (MER:482-492); no editing UI; publishes as **empty text frame** (DEB:196,246-247); data survives nowhere outside the session. |

## M-F. IMAGES, GRAPHICS & COLOR

| Item | Verdict | Evidence |
|---|---|---|
| Place image; frame-vs-content | **PARTIAL + 3-way CONFLICT** | Asset picker solid (auto-opens on add, DEB:558-561). Canvas drag-drop/paste: MISSING. **Fit semantics conflict:** editor renders raw value as CSS (`objectFit: data.fit || 'cover'`, MER:292 — `'fill'`=stretch, `'fit'`=invalid), publish maps `fill→cover, fit→contain` (DRS:263-264), prototype has a third map (FrameRenderer.tsx:179-181). **Focal point scale bug:** editor 0-1 (×100 at MER:293), save/load default `{x:50,y:50}` (DEB:141,266 — editor would render 5000%), publish int-casts 0-1 → `0% 0%` top-left (DRS:265-266). Pan/scale-in-frame: UI only — renderer ignores, DTP save drops (V2 persists — CONFLICT). Double-click content mode: MISSING. |
| Strokes & fills | **PARTIAL** | Full panel + editor render + persisted; **publish drops all of it** (`buildFrameStyle` DRS:308-326 emits only pos/size/z/rot; blade never reads `style`). Stroke alignment never applied anywhere. |
| Swatches from theme tokens | **MISSING** | No swatches panel; raw color inputs; zero theme-token imports in magazine code. |
| Gradients | **PARTIAL** | UI (2 fixed stops) + editor render + persisted; publish drops; `gradient_overlay` publishes as solid shape. |
| Opacity / blend / effects | **PARTIAL** | Panel complete; editor renders opacity/shadow/blend but **innerShadow and blur never render even in editor**; publish drops all except image content-opacity. |
| SVG placement | **PARTIAL** | Upload sanitization solid (UploadAssetRequest.php:118-124 + SvgSanitizer.php); editor DOMPurify. `svg_icon` custom markup publishes as a solid rectangle (DEB:193 → shape). SVG assets in image frames publish fine. |
| Eyedropper | **MISSING** | |
| Asset link health / entity_references | **MISSING** | Magazine/DTP excluded from the dependency graph: no extractor in ReferenceExtractorRegistry.php:25-161 (only a flipbook *block*); DtpDocumentController never calls ReferenceRecorder; `magazine_asset_references` is client-echoed, no FK, not linked to entity_references → **asset deletion protection cannot see magazine usage**. Preflight checks src format only, not existence. |
| Captions from metadata / duotone / overlays | **MISSING / PARTIAL** | Asset model has alt only. Manual caption renders differently in editor (light bar, shrinks image, MER:291-314) vs published (black overlay, full image, DRS:276-279). Duotone: model-only. |
| Image URL/variant handling | defect | Published rewrites to hardcoded `https://sys.ensodo.eu/media/...` (MagazineViewController.php:231-236); **WebP variants never used by any magazine path** — always originals. |

## M-G. OUTPUT & INTEGRITY

| Item | Verdict | Evidence |
|---|---|---|
| Static viewer via publish pipeline | **CONFLICT/MISSING — architecture violation** | Magazine reading routes (`/magazine/{slug}`, `/issue/{slug}`, `/magazine/dtp/{id}`, routes/web.php:65-69) are **dynamic Laravel routes** resolving tenants via `X-Original-Host` proxy (MagazineViewController.php:284) — violates "tenant domains = static only, never proxy to Laravel". No generator writes magazine viewer HTML to `public_html`. "Publish" for a Magazine = status-flag flip (MEV2:316-319). Only editor_mode='magazine' Pages are statically built, via a *different* renderer (BuildPageService.php:122-141 → MagazineRenderer.php). |
| Reader needs no JS | **MISSING / PARTIAL** | magazine.blade.php builds ALL page DOM client-side in JS (`renderPage` blade:477-527, :923-959); empty `#viewport` without JS, no noscript. dtp-preview is server-rendered (readable in single mode); nav needs JS. |
| First spread eager, rest lazy | **MISSING** | All imgs `loading='lazy'` incl. first spread; scroll mode renders everything; flipbook builds all pages upfront. |
| Preflight panel | **PARTIAL/CONFLICT** | Server `DtpPreflightService` checks empty frames/missing src/unsafe URLs/alt/bounds. **No overset check, no asset-existence check, no font check.** DEB shows only status+score (DEB:718-725) — no issue list, no jump-to. The clickable jump-to PreflightPanel exists **only in the mock prototype** (prototypes/dtp/PreflightPanel.tsx). Legacy stack: nothing. |
| PDF export | **MISSING** (honestly stubbed) | "No PDF export — HTML preview only" (DRS:13); prototype ExportPanel alerts "no files are generated". |
| Bleed/crop marks | **MISSING** | Bleed only suppresses out-of-bounds preflight warnings (DtpPreflightService.php:115-118). |

**Viewer parity bottom line (Part 2.6):** the published output is **four independent
re-implementations** (blade standard branch, blade flipbook branch — which disagree with
each other on shape fills, blade:511-514 vs :949-954 —, DtpRenderService PHP,
MagazineRenderer PHP), none consuming the editor's layout computation. Thread metadata is
ignored by all viewers (slices are baked — the one good decision). The consistency
checker knows: issue codes `style_not_rendered_in_viewer`,
`typography_not_rendered_in_viewer`, `field_lost_after_save`
(lib/dtpConsistencyChecker.ts:47-49,123-124).

## M-H. PRODUCTIVITY & RELIABILITY

**Undo/redo per mutation type (Part 2.5).** Mechanism: whole-document JSON snapshots of
`pages` only (MAX 50) — `pushSnapshot` (mS:1144-1153); styles/issueSettings/viewerSettings
are **outside the snapshot** and unrecoverable.

| Mutation | Covered? |
|---|---|
| Page ops (add/delete/duplicate/reorder/updatePage) | YES (mS:392,445,465,518,535) |
| Element add/delete/duplicate/moveToPage/paste | YES (mS:548,610,629,672,1114) |
| Pour (manual) | YES (mS:702) |
| **Geometry: drag-move, resize, rotate, nudge, TransformPanel** | **NO — `updateElement` (mS:579-606) never snapshots.** Worse than not-undoable: Undo after a drag reverts to the previous snapshotted op, silently discarding all intermediate moves |
| **Style apply (Fill/Effects/Typography/StylesPanel)** | **NO** (routes through updateElement) |
| **Text edits (contentEditable blur, execCommand, inline images)** | **NO** |
| **Thread ops (start/continue/unthread)** | **NO** (pure updateElement) |
| **autoFlowText (save-time restructure of the whole document)** | **NO** |
| Style CRUD add/update/delete | NO snapshot AND outside snapshot payload |
| bringToFront/sendToBack, master assign, template apply | YES |
| setEditingMaster | snapshots on mere navigation — history pollution (mS:1311) |

**No Ctrl+Z/Ctrl+Shift+Z binding exists anywhere** — undo is toolbar-only, while tooltips
advertise the shortcuts (MagazineToolbar.tsx:159,166). `store.copy/cut/paste`
(mS:1093-1140) are dead code — zero callers.

| Item | Verdict | Evidence |
|---|---|---|
| Robust undo/redo | **PARTIAL→BROKEN** | Above: the single highest-frequency mutation path is outside history. |
| Keyboard shortcuts + cheat-sheet | **PARTIAL** | One handler (MagSelectionEngine.tsx:242-280): Esc, Del, Ctrl+D/A, v/t/i/r/e/l tools, arrows, Ctrl+;. No undo/clipboard/zoom keys. Cheat-sheet MISSING. Shortcut conflicts with block canvas (Ctrl+; means different things; tool letter "I" vs "F"). |
| Context menus | **PARTIAL** | Pages yes (PageNavigator.tsx:237); canvas objects none. |
| Autosave + versions | **MISSING** | Manual save only; no timer, no beforeunload guard. `page_versions` never used by magazines. Legacy save is a destructive delete-and-recreate (MagazineController.php:136-180). No restore anywhere. |
| Templates | **PARTIAL/CONFLICT** | 4 hardcoded *page* templates, genuine copies (PageNavigator.tsx:31-105) — wired only in DEB; apply is a no-op in MEV2. Document-level starters MISSING; TemplateGallery prototype-only. |
| Snippets / stats / history panel | **MISSING** (stats prototype-only) |
| Comments / share-preview | **MISSING** | No tokenized preview links; only public URLs for published items. |

## M-I. ACCESSIBILITY & SEMANTICS

| Item | Verdict | Evidence |
|---|---|---|
| Reading order from thread order | **MISSING** | All viewers emit z-index order (MagazineViewController.php:27,79; DRS:106); thread metadata ignored. |
| Semantic HTML | **PARTIAL** | Headings survive sanitization inside absolutely-positioned div soup; flipbook wraps pages in `<article>`; DRS quote → real `<blockquote>` (:283). No landmarks. |
| Alt text | **PARTIAL** | Per-placement alt persisted + rendered in all viewers; preflight warns. No asset-level fallback (default `''`). |
| Keyboard-navigable viewer | **PARTIAL** | Standard mode: arrows/space/esc/f (blade:714-719). **Flipbook: Escape/f only — no arrow keys** (blade:1030-1033). Controls auto-hide on mouse only (blade:702-711). |
| prefers-reduced-motion | **MISSING** | Zero hits in both viewers; page-flip animations unconditional. |

---

# PART 3 — VERDICT & ROADMAP INPUT

## 3.7 Flow-engine verdict: **REBUILD as a pure module** (behind the existing data model)

Refactor-in-place fails all three of its own criteria in spirit:

1. *Single computation path?* Technically two live near-duplicate copies + one dead
   engine + one dead lib. Git history is the proof of unpatchability: 16 of the last 25
   commits touching these files are Pour/Auto-flow/threading fix-ups
   (`61f550c`→`4ae766f`), including the fix (`7ef8d89`) that created the current
   permanent-clip behavior while fixing duplicate text.
2. *Can be made off-DOM/synchronous with bounded changes?* Measurement could stay
   hidden-DOM, but correctness cannot be bounded: the required behaviors —
   reflow-after-edit, cascade, shrink, un-thread merge, idempotent recompute — are
   **unrepresentable** because content is destructively sharded with no canonical story
   text. Removing the `threadId` skip (mS:888) without a story model reintroduces
   `7ef8d89`'s duplicate-text bug. This is a data-model gap, not a code-quality gap.
3. *Persisted format can stay?* **Yes — and it should.** Per-frame slices as the render
   contract is the one good architectural decision (viewers stay dumb; engine runs at
   edit/publish time, never reader runtime — exactly Session B/C's contract). The
   rebuild adds a story-level source of truth **additively** (`metadata.storyContent` or
   a story table; `SaveDtpDocumentRequest` already accepts arbitrary metadata) and keeps
   writing slices for the viewers.

Rebuild shape (= Session C): pure `flowText(content, frameChain, styles) → placements`
with an injected measurer (hidden-DOM in browser, fake char-width table in Node tests),
word-boundary binary search, one `recomputeFlow(threadId)` store entry point called by
every mutation in the Part 1.2f trigger list, cascade + shrink, and the losslessness
assertion. Delete: `TextThreading.ts`, `lib/textThreading.ts`, `lib/textWrap.ts`, both
in-store algorithm copies, `TextFlowCalculator.php`.

## 3.8 Top 10 defects

| # | Defect | Mechanism | User-visible symptom |
|---|---|---|---|
| 1 | Flow self-disables and can't cascade | `threadId` skip (mS:888) + one-hop split over stale snapshot (mS:877,982,994) + destructive sharding | Paste an article → page 2 fills, everything beyond is invisible forever; re-edits never reflow |
| 2 | No paste pipeline | No `onPaste` handler anywhere; raw clipboard HTML; blur-time sanitize only | Word/GDocs garbage markup; silent clipping; bare text nodes **deleted** during split (data loss) |
| 3 | Published output ≠ editor (4 renderers) | blade ×2 / DRS / MagazineRenderer re-implement rendering; `style` JSON unread; typography dropped (legacy) or partial (DTP); no webfonts in any viewer | Published magazine looks nothing like the editor: default fonts, black/grey shapes, black rectangles for page numbers, clipped spread heroes |
| 4 | Undo hole at the highest-frequency path | `updateElement` never snapshots; no Ctrl+Z binding; snapshot covers `pages` only | Undo skips/destroys work; advertised shortcuts do nothing |
| 5 | Styles + masters destroyed on round-trip | `setDocument(pages, [])` on load, styles absent from save; masters filtered from DTP save / `isMaster` dropped by V2; `master_page_id` never remapped server-side | Define styles or masters, save, reload → gone (or dangling) |
| 6 | Inert-control epidemic | textWrap not serialized+not rendered; autoSize/verticalAlign/overflow/hyphenation/widows/orphans/dropCap/OpenType/9-point proxy/innerShadow/blur: stored, never enforced | Panels full of switches that do nothing — "lacks excellence" made concrete |
| 7 | Unit chaos | A4 preset mm→pt canvas (PagePanel.tsx:9-14 vs 595×842); fontSize px (editor) vs pt (mag_pages publish); focal point 0-1 vs 0-100 | "A4" page is ~35% size; published text ~33% larger; published images pinned top-left |
| 8 | Dual/dead state wiring | store activeTool/showGrid/showGuides/showBaseline/snapEnabled unread by canvas; baseline+column overlays computed, never rendered; marquee never set; copy/paste dead | Top toolbar half no-op; toggles that visibly do nothing |
| 9 | `updateElement` is current-page-only | mS:597-601 | Cross-page thread renumber and exit-editing flush silently dropped → corrupt thread metadata |
| 10 | Publish architecture violation + JS-only reader | Dynamic Laravel routes on tenant hosts via X-Original-Host; blade builds DOM in JS; no static magazine generator | Conflicts with static-tenant rule; reader requires JS; no real "publish" step |

**Root-cause clusters:** (a) parallel implementations with no enforced shared contract;
(b) UI-first feature development with no end-to-end definition of done; (c) destructive
content sharding instead of a story model; (d) no regression harness, so each fix trades
one pinned-nowhere behavior for another.

## 3.9 What is GOOD and must be preserved

- **Data model vocabulary:** `threadId/threadOrder` linkage (round-trips both formats);
  `TextFrameData` (columns/inset/columnFill), `MagTypography` (incl. the inert
  widow/orphan/hyphenation fields — ready for a real engine), `MagPageData`
  margins/bleed/columns/baseline. The `mag_pages` schema (Path 3) is the fullest.
- **Per-frame-slice render contract** — recompute in editor, ship slices, dumb viewers.
- **Canvas interaction layer:** selection/drag/resize/rotate with dead-zones and
  aspect-lock; contentEditable lifecycle hardening (single-init innerHTML, double-save
  guard, page-change flush); Pour's image-avoidance placement.
- **Binary-search fit** — right idea, wrong inputs; port into the pure module.
- **Sanitization discipline:** DOMPurify at every ingest; two-layer SVG sanitizer
  (UploadAssetRequest + SvgSanitizer).
- **Atomic transactional DTP save** (DtpDocumentService.php:110-136).
- **Debug/consistency stack:** dtpConsistencyChecker + DtpDebugPanel with honest issue
  codes — a ready-made harness seed for Session C/D verification.
- **Store page CRUD + snapshot undo core** (coverage must widen, mechanism is fine).
- Thread badges/overflow badge UX; PageNavigator interaction design; the prototype's
  snapEngine and jump-to-issue PreflightPanel as *reference implementations* to absorb.

## 3.10 GAP-CLOSE ORDER — dependency-ordered vertical slices (≤1 day each)

Annotation: **[E]** depends on the Session C engine rebuild; **[I]** independent, can
land before/parallel to Session C; **[P]** depends on publish-path consolidation (slice
W0-4). Order within waves is the build order. This list drives Session D; W0 is a
precondition for Session C landing cleanly.

**WAVE 0 — stop the bug factory (all [I], do before/during Session C)**
- W0-1. **One editor.** Decommission MagazineEditorV2 route → redirect to DTP editor (or
  freeze read-only); pick DtpEditorBeta as the single live editor; migrate/adapter for
  legacy magazines. Delete prototype after harvesting snapEngine + PreflightPanel
  patterns as reference. Rename `components/editor/MagazineEditorCanvas.tsx` (post-block
  canvas) to stop the name collision.
- W0-2. **Delete dead code:** TextThreading.ts, lib/textThreading.ts, lib/textWrap.ts,
  TextFlowCalculator.php, store copy/cut/paste dead paths (or wire them — see W2-6);
  fix `safeUUID` recursion; either adopt or delete types/magazineDocument.ts (decide:
  adopt as the real contract in Session C Phase 2).
- W0-3. **One state source:** make MagazineToolbar drive the same activeTool/snap/overlay
  state the canvas reads; delete the parallel store flags or wire them; render the
  already-computed baseline + column overlays (two small JSX consumers).
- W0-4. **One publish path decision:** DTP stack (DtpRenderService) becomes the only
  magazine renderer; blade standard/flipbook branches and MagazineRenderer scheduled for
  replacement in Session C Phase 3. Document the target: engine slices → server-rendered
  static HTML → tenant `public_html` (fixes the architecture violation), no JS for
  reading, first spread eager.
- W0-5. **Undo repair:** snapshot in `updateElement` (debounced per gesture: one snapshot
  per drag/resize/edit transaction, not per pixel); include `styles` in snapshots; bind
  Ctrl+Z/Ctrl+Shift+Z/Ctrl+C/X/V; remove setEditingMaster history pollution. (Undo for
  *new* mutations is covered per-slice by the Session D definition of done.)
- W0-6. **Round-trip integrity:** persist `store.styles`; persist masters (DTP: stop
  filtering, remap `master_page_id` server-side in DtpDocumentService::saveDocument);
  persist textWrap/image offsets/filters/scaleX/scaleY; fix `updateElement`
  current-page-only bug (mS:597-601). Add a consistency-checker CI assertion: save→load
  deep-equal (the tool already exists — promote from debug panel to test).
- W0-7. **Unit sanity:** page presets in pt (or a real unit layer); one focal-point scale
  (0-1) across editor/save/publish; fontSize px everywhere; document the canvas unit.

**SESSION C lands here** (pure engine + golden tests + editor integration + viewer
parity + regression harness). Everything below assumes harness green.

**WAVE 1 — editorial credibility**
- W1-1. [E] Chain-aware overset badge + auto-paginate affordance on last frame of chain.
- W1-2. [E] Cascade + shrink auto-pagination (engine contract); losslessness assertion in
  dev after every flow.
- W1-3. [E] Paste pipeline minimal: onPaste handler → normalize (strip mso/spans) →
  insert at caret → reflow. (Full pipeline incl. large-paste dialog = Session E.)
- W1-4. [I] Spreads first-class: real 2-page spreads with shared coordinate space +
  gutter in the model (side verso/recto persisted); span-the-gutter images publish
  unclipped (fix `buildPageStyle` `$hasSpreadImage`).
- W1-5. [E] Thread ports UX: clickable in/out ports, click-to-navigate, add-linked-frame,
  break/relink; kill the manual thread buttons' current semantics.
- W1-6. [E] Per-frame columns geometry inheritance for continuations; vertical align +
  auto-size enforcement (engine measures; renderer applies).
- W1-7. [I→E] Paragraph/character styles v1: ONE style system (kill hardcoded presets),
  editable properties, persisted, wired to THEME TOKENS via the shared inspector
  primitives; renderer + measurer read the same style-builder. Keep options/hyphenation
  become engine inputs ([E]).
- W1-8. [E] Drop caps + lists in styles, rendered editor+publish.
- W1-9. [E] Pull-quote exclusion element: engine rect exclusions carve columns; wrap
  panel now writes data the engine actually consumes; delete the fake wrap UI states.
- W1-10. [E] Image runaround (bounding-box + margin) + jump-object mode via same
  exclusion model.
- W1-11. [E] Anchored image with caption in the text stream (figure/figcaption),
  flows with story, publishes.
- W1-12. [I] Frame-vs-content image mode: double-click content mode, pan/scale applied in
  renderer AND publish; one fit-semantics map shared editor/publish; WebP variant
  selection at publish.

**WAVE 2 — professional canvas**
- W2-1. [I] Rulers + drag-out guides (page + spread), guide model persisted, lock/clear,
  numeric edit; snap-to-guides.
- W2-2. [I] Snapping consolidation: adopt prototype snapEngine (page edges, zoom-corrected
  tolerance), per-source toggles (guides/margins/baseline/grid/objects), global on/off
  honored by smart guides too; snapping on resize.
- W2-3. [E] Baseline grid: render overlay (already computed) + real line-height snapping
  for body styles via engine metrics.
- W2-4. [I] Align/distribute to margins + numeric spacing; equal-spacing smart hints.
- W2-5. [I] Layers panel v2: named layers with per-layer lock/visibility; one-step
  bring/send forward/backward; fix mislabeled buttons.
- W2-6. [I] Marquee select (state exists, wire it), select-behind (alt-click cycle),
  alt-drag duplicate (move pan to space-drag), step-and-repeat.
- W2-7. [I] Numeric transform: math entry ("+10"), working 9-point reference proxy.
- W2-8. [I] Preview mode ("W"): hide all chrome; pasteboard staging area.
- W2-9. [I] Pages panel: live thumbnails (render engine output small), drag-reorder wired
  everywhere, duplicate/template apply in the single editor.
- W2-10. [E] Master pages v2: verso/recto application, revertible overrides, primary text
  frame instantiated+threaded on page-from-master, masters composited at publish.
- W2-11. [P] Page-number variable end-to-end (formats incl. roman — write the converter),
  sections + start-at; running header resolving nearest heading; kill the parallel
  viewer-chrome numbering or make it the same system.
- W2-12. [I] Group/ungroup for real (children created, transforms compose, nested).

**WAVE 3 — reliability & excellence**
- W3-1. [I] Undo/redo completion sweep: every mutation type from Part 2.5 table covered +
  tests; history panel optional.
- W3-2. [I] Find & replace (document-wide, across frames/threads).
- W3-3. [I] Swatches panel from theme tokens (primitive→semantic) + doc-local swatches +
  tints; gradient stop editor; eyedropper (EyeDropper API + format sampling).
- W3-4. [P] Preflight v2 in-editor: overset anywhere (engine knows), missing/replaced
  assets (wire entity_references: add magazine extractor to
  ReferenceExtractorRegistry + ReferenceRecorder on save — closes the dependency-graph
  hole), missing fonts, empty frames — clickable jump-to (port prototype panel).
- W3-5. [I] Autosave (debounced) + versions on the existing page_versions pattern +
  restore UI; beforeunload guard; stop delete-and-recreate data races (soft-version the
  save).
- W3-6. [I] Shortcut set completion + cheat-sheet overlay; object context menus.
- W3-7. [I] Document templates (Feature opener / Interview / Photo essay) as
  presets-by-copy; snippets library optional.
- W3-8. [P] Accessibility pass: reading order from thread order in published DOM order,
  semantic headings/landmarks, asset-level alt fallback, keyboard nav in all viewer
  modes, prefers-reduced-motion.
- W3-9. [P] Static publish integration: magazine build step writes static viewer HTML to
  tenant public_html via the existing publish pipeline; first spread eager/rest lazy;
  remove X-Original-Host dependency for readers.

Deferred to backlog ([pro]/[nice]/[later]): tables track, footnotes, text-on-path, PDF
export (brochure engine track — currently honestly stubbed, keep it that way), OpenType
feature rendering, optical margin alignment, story editor, spell check, comments/review,
share-preview links, snippets, duotone presets, dash-style niceties.

---

*End of Session A audit. Nothing was fixed. Next: manual gate — read the verdict and the
classified checklist, then Session B (flow-engine reference prototype, standalone HTML).*
