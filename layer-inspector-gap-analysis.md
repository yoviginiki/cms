# Layer-Inspector Gap Analysis (Session 1 — read-only audit)

Date: 2026-07-03 · Scope: shared layer/block inspector end-to-end (editor → schema → sanitizer → Blade/CSS output) against checklist A–H of `slider-block-master-build-v3.md`.

## 0. The headline finding

**There is no single "shared layer inspector."** Three parallel systems exist and do not share types, validation, sanitization, or emitters:

| System | Editor | Schema | Sanitizer | Output |
|---|---|---|---|---|
| **Block inspector** (pages/posts — and slider layers as of `feat/slider-block`) | `BlockSettings.tsx` + panels | `blocks.data.__style/__animation/__responsive/__advanced` (`types/blocks.ts`), NO backend validation of `__*` | `SanitizationService` (HTMLPurifier, top-level strings) + `BlockStyle::safe*` on output | `BlockStyle.php` / `BlockEffects.php` + Blades |
| **Magazine A** (MagazineEditorV2) | `components/magazine/properties/*` | `MagElement` top-level x/y/w/h/rot + `style`/`typography` objects (`types/magazine.ts`) — shallow backend rules (`MagEditorController.php:37-52`) | **NO HTMLPurifier** — `strip_tags` only (`MagazineRenderer.php:124`) | `MagazineRenderer.php` (pt units) |
| **Magazine B** (DTP designer, feature-flagged) | `prototypes/dtp/PropertiesPanel.tsx` (transform+image only) | `MagazineFrame` + untyped `style` JSON | own `sanitizeHtml` (`DtpRenderService.php:21-23`) | `DtpRenderService` (px units) |

The slider build (Phases 1–2, `feat/slider-block`) made the pragmatic call the audit vindicates: **slider layers are BLOCKS** (`data.layout` + `data.animation`, validated by `SliderAnimation`, emitted by `SliderRender`), so the slider's "shared inspector" is the **block inspector** — the most complete of the three. Remediation below targets it; magazine consolidation is a separate (deferred) track.

---

## (a) Classified checklist

Legend: **HAVE** = end-to-end · **PARTIAL** (missing tier stated) · **MISSING** · **CONFLICT**. Citations: E=editor, S=schema/validation, Z=sanitizer, O=output emitter.

### A. Content & layout
| Item | Status | Detail |
|---|---|---|
| A1 rich inline content (b/i/link) [core] | HAVE | E per-block editors; Z HTMLPurifier rich profile (`SanitizationService:27-36`); O text blades |
| A1 alignment L/C/R/justify [core] | HAVE | E `TypographyPanel:132-138`; O `BlockStyle:302-314` |
| A1 wrap/nowrap [core] | MISSING (no control/emit) |
| A1 inline icons / dynamic tags [nice] | MISSING |
| A2 image asset + alt [core] | HAVE | E `image/Editor.tsx:18`; O `image.blade:58` |
| A2 object-fit + object-position [core] | **PARTIAL/CONFLICT** — object-fit exists ONLY on `post-image` (E/S/O); generic image block lacks it; object-position ABSENT everywhere; other blades hardcode `cover` |
| A2 explicit vs intrinsic dims [core] | PARTIAL — O emits width/height attrs (`image.blade:59-60`); no editor fields on generic image |
| A2 external URL source [nice] | HAVE |
| A3 video asset/YouTube/Vimeo + poster [core] | HAVE (`video.blade:22-23,82`) |
| A3 autoplay/loop/muted [core] | HAVE (E toggles) — **controls/preload/playsinline hardcoded, no controls** (PARTIAL, editor tier missing) |
| A3 start-end time [nice] | MISSING |
| A4 audio: controls/volume/loop/preload [core] | PARTIAL — `<audio controls preload=metadata>` hardcoded (`audio.blade:29`); volume/loop/preload have NO editor/schema/emit. No autoplay anywhere ✓ (slider runtime additionally pauses on slide change) |
| A5 SVG sanitized at upload [core] | **MISSING — SECURITY**: `AssetService.php:46` stores raw SVG bytes, explicitly skips processing; no server-side scrubbing |
| A5 inline vs img [nice] | MISSING |
| A6 group layer [core] | PARTIAL — group block exists; children move together in slider canvas via layout; no child-alignment control (`alignItems` typed but not emitted) |
| A6 row/column + gap + collapse [nice] | HAVE hierarchy (`BlockLevel.php`) + gap; collapse is content-heuristic not breakpoint toggle |
| A7 x/y/w/h/rotation/zIndex [core] | **PARTIAL/CONFLICT** — three conventions: MagElement top-level (pt), DTP frame (px), slider blocks `data.layout` (px/%, validated `SliderAnimation`, emitted `SliderRender::wrapLayer`). Block inspector has NO x/y/rotation UI (`blocks.ts:70-75` typed, unexposed) — **slider Phase 3 must add a Transform panel bound to `data.layout`** |
| A7 9-point anchors [core] | MISSING (Magazine TransformPanel has a reference-point grid — pattern to port) |
| A7 min/max width text [core] | HAVE (`LayoutPanel:82-85` → `BlockStyle:319-325`) |
| A7 HTML tag choice [core] | PARTIAL — heading h1–h6 only; no p/div/span selector on text |
| A8 per-breakpoint x/y/w/h/font/padding/visibility [core] | PARTIAL — spacing/layout/visibility have full E+O; **typography per-breakpoint: emitter ready (`BlockStyle:507`), NO UI**; x/y (layer layout) not per-breakpoint yet (slider Phase 5 scope) |
| A8 stepped vs smooth scaling [core] | PARTIAL — font "Scalable" clamp() mode exists (`TypographyPanel:39-103`); no global mode switch |
| A8 copy-layout-from-device [core] | MISSING (only per-device reset) — slider Phase 5 builds it |

### B. Core style
| Item | Status | Detail |
|---|---|---|
| B1 typography family/size/weight/italic/lineHeight/letterSpacing/transform/color [token] | PARTIAL — all controls exist (`TypographyPanel`), **but zero token awareness: hex-only pickers, and `safeColor`/`safeDim` DROP `var(--…)` values** — the [token] requirement fails at both E and Z tiers, system-wide |
| B1 text-decoration [token] | MISSING |
| B1 paragraph spacing [token] | MISSING (typed `paragraphSpacingAfter`, no UI, no emit) |
| B2 SVG fill/stroke [core][token] | MISSING (icon block has color only; hardcoded) |
| B3 background color/transparent [token] | HAVE control; token tier MISSING |
| B3 gradient linear/radial multi-stop [core] | HAVE (`BackgroundEditor:124-193` → `BlockStyle:191-203`) |
| B3 bg image size/position/repeat [nice] | HAVE (repeat honored by emitter, not surfaced in UI) |
| B3 text clip-to-background [nice] | MISSING |
| B4 border width per-side [core] | MISSING (uniform only); **silent bug: width without color emits nothing** (`BlockStyle:237`) |
| B4 radius per-corner [core] | HAVE (also force-adds `overflow:hidden` — surprising side effect) |
| B5 padding/margin per-side per-breakpoint [core] | HAVE (`SpacingPanel` + responsive emitters) |

### C. Advanced style
| Item | Status |
|---|---|
| base opacity [core] | **PARTIAL — SILENT BUG: slider exists (`VisualPanel:131-138`) but `buildStyle()` never emits opacity. Control does nothing.** |
| base transform scale/skew/rot/perspective/origin [core] | MISSING in block system (motion-runtime contract "animations compose FROM base values" currently only honors inline `rotate()` — `SliderRender` wrapper + runtime `baseRotation()`) |
| blend mode [core] | PARTIAL — overlay-only, and only via `CardEffectsPanel` which is wired into just 3 block types |
| filters [core] | PARTIAL — grayscale/sepia/brightness/contrast/saturate on 3 block types; no blur/hue-rotate; not in shared inspector |
| backdrop-filter [nice] | MISSING |
| box-shadow MULTIPLE [core] | PARTIAL — single shadow only (preset or one custom) |
| text-shadow multiple [core] | PARTIAL — single only |
| text-stroke [nice] | MISSING |
| overlay pattern [nice] | MISSING (solid overlays only) |

### D. Hover & interaction
| Item | Status |
|---|---|
| hover per layer, pure CSS [core] | PARTIAL/CONFLICT — two systems: `block-hover-{x}` preset classes (global stylesheet) and `BlockEffects::cardHoverCss` (scoped, 3 blocks). No free-form per-property hover editor |
| cursor style [core] | MISSING |
| focus-visible mirrors hover [core] | MISSING (no :focus-visible emission anywhere in block output) |
| hover filters / toggled text [nice] | MISSING |

### E. Parallax
- slide-background parallax [core-thin]: HAVE flag (`SlideBlockDefinition:26`) + Section/Hero scroll parallax exists (`section.blade:60-84`); **CONFLICT: `BackgroundEditor.tsx:262-266` tells users parallax is "not yet implemented" while section.blade implements it**
- scroll/mouse parallax per layer [nice]: MISSING

### F. Visibility
- show/hide per device [core]: HAVE (`ResponsivePanel` → `buildHideOnCss`, display:none in @media) ✓
- visible-only-on-hover [nice]: MISSING

### G. Attributes & a11y
| Item | Status |
|---|---|
| id/class allowlisted [core] | HAVE (`safeClass`/`safeId`) — **but `__advanced.customCss` is a SILENT DROP: collected in UI, never emitted** |
| link on any layer href/target/rel/nofollow [core] | PARTIAL — per-block links only (heading/button); no universal link, no nofollow control |
| alt/aria-label [core] | HAVE · aria-hidden/role [core] MISSING as controls |
| carousel a11y [core] | HAVE (slider Phase 2: roledescription, slide X of N, pause button, keyboard, reduced-motion) ✓ |
| data-* allowlist [nice] | MISSING |

### H. Module & slide level
All HAVE via slider Phases 1–2: per-breakpoint height ✓, autoplay + per-slide duration ✓, nav/bullets (token-driven chrome via `--color-*` vars in `motion-runtime.css`) ✓, loop/initial/touch/keyboard ✓, progress+counter ✓, first-slide eager / later lazy ✓, fixed-height reserved space (no CLS) ✓. Thumbnails pagination: MISSING [nice].

---

## (b) CONFLICT list (future bug factories)

1. **Three typography models** — `MagTypography` (33 props), `MagazineDocTypography`, block `TypographyProps`; disjoint fields, disjoint emitters. Magazine emits pt, blocks px/em.
2. **Three transform/position conventions** — MagElement top-level (pt) · DTP frame (px) · slider `data.layout` (px/% + widthPct). `positionMode/spanMode` are dead TS fields (no DB column, no renderer).
3. **Two rich-text sanitize paths** — blocks: HTMLPurifier; magazine A+B: bare `strip_tags` (attributes like `onclick`, `href="javascript:"` SURVIVE — **XSS exposure in published magazine output**).
4. **Two hover systems** (preset classes vs BlockEffects scoped CSS) and **two `safeColor` implementations** (`BlockStyle:19-27` accepts named/hsl/oklch; `BlockEffects:285-290` hex/rgb only, defaults `#000`).
5. **Breakpoint definitions disagree** — editor copy says tablet ≤1024/mobile ≤640 (`ResponsivePanel:37`); emitted media queries are ≤1023/≤767; hideOn uses a third scheme (desktop ≥1024). Slider runtime uses 1024/640 (`motion-runtime.css`).
6. **Editor preview ≠ published output** in Magazine A: canvas renders all 34 element types; `MagazineRenderer` renders ~10 (24 types publish as empty divs); gradients/blend/innerShadow/scale render in canvas, dropped at publish.
7. `BackgroundEditor` parallax copy contradicts the working Section/Hero implementation.
8. object-fit exists on `post-image` but not the generic image block (two image control sets).

## Silent sanitizer-strip bugs (editor sets it → output loses it)
- `__advanced.customCss` — stored, never rendered (G)
- `visual.opacity` slider — never emitted (C)
- `var(--token)` colors/dims — dropped by `safeColor`/`safeDim` (B1/[token])
- border width without color — dropped (B4)
- `calc()/clamp()/vmin/ch/dvh` dims — dropped by `safeDim` (spacing/layout)
- `layout.alignItems`, `typography.paragraphSpacingAfter`, `maxCharactersPerLine`, animation `trigger:on-scroll` — typed, never emitted
- Magazine: `fontStyle` italic, `fill.gradient`, `stroke.style` dashed/dotted, `innerShadow`, `blendMode`, `blur`, `scaleX/Y` + ~24 element types — render in canvas, dropped at publish

---

## (c) Remediation plan — dependency-ordered vertical slices (≤ ½ day each)

| # | Slice | Tiers touched | Depends on |
|---|---|---|---|
| S1 | **Token pipeline**: `safeColor`/`safeDim` accept allowlisted `var(--[a-z0-9-]+)` (with fallback syntax); token-aware color picker (swatches from `themeTokens.ts`) replacing bare hex inputs in TypographyPanel/VisualPanel/BackgroundEditor | E+Z+O | — |
| S2 | **Emit-fix bundle**: emit `visual.opacity`; emit `alignItems`; border defaults color to `currentColor` when width-only; remove or emit `customCss` (decision: emit scoped `<style>` with allowlist, else delete UI); fix breakpoint label copy to match emitters | E+O | — |
| S3 | **Generic image block parity**: object-fit + object-position + explicit w/h controls (editor + rules + blade), consolidating with post-image's implementation | E+S+O | — |
| S4 | **Media layer controls**: video `controls/preload/playsinline` toggles; audio `loop/volume/preload` (+ keep no-autoplay invariant) | E+S+O | — |
| S5 | **Layer Transform panel**: x/y/w/h/rotation/zIndex + 9-point anchor bound to `data.layout` for blocks inside slide/absolute canvases (port Magazine TransformPanel UX; `SliderAnimation` rules already validate; `SliderRender::wrapLayer` already emits) | E (S,O exist) | S1 |
| S6 | **Typography per-breakpoint UI**: wire font-size/line-height/letter-spacing into the responsive override flow (emitter exists) + text-decoration control/emit | E(+O for decoration) | S1 |
| S7 | **SVG upload sanitization** (server-side scrub in `AssetService::upload`, e.g. enshrined/svg-sanitizer) | Z | — |
| S8 | **Magazine XSS fix**: route `MagazineRenderer`/`DtpRenderService` text HTML through `SanitizationService` rich profile instead of `strip_tags` | Z | — |
| S9 | **safeColor consolidation**: BlockEffects delegates to BlockStyle::safeColor | Z/O | S1 |
| S10 | **Interactive a11y**: cursor control + focus-visible mirroring hover on linked/interactive blocks | E+O | — |
| S11 | Hover per-property editor (color/bg/border/opacity/transform + duration/ease, scoped pure CSS) | E+S+O | S1, S9 |
| S12+ | Deferred: multi box-shadow/text-shadow, text-stroke, backdrop-filter, block-level blend/filters in shared inspector, copy-layout-from-device (slider Phase 5 builds it), collapse-breakpoint toggle, universal link control + nofollow, aria-hidden/role/data-* controls, magazine renderer fidelity (24 empty types), magazine token adoption, Magazine A/B convergence | — | — |

## (d) Blocking vs follow for the Slider block editor

**MUST land before slider Phase 3 (editor):** S1 (tokens — [token] principle), S2 (trust: opacity/emit fixes), S3 (image layers), S4 (video/audio layers), S5 (transform panel — the canvas itself).
**Before slider Phase 5 (responsive):** S6.
**Before slider Phase 6 (audit/public):** S7, S8 (security), S10 (a11y).
**Can follow:** S9, S11, S12+.
