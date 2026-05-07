# Project Recovery Plan

> Generated: 2026-05-06 | Scope: Ensodo CMS Platform (Laravel 13 + React 19 SPA)

---

## Current Diagnosis

### Backend (Laravel 13, PHP 8.3)
- **Status: Functional but incomplete.** 32 API controllers under `Api/V1/`, domain services for Sites, Posts, Publishing, Theme, Magazine, Blocks. Sanctum auth, Spatie permissions, PostgreSQL multi-tenancy. Horizon for queues.
- **Risk:** Only 19 of 68 blocks have PHP `BlockDefinition` classes (and only 18 of those map to a frontend component -- `QuoteBlockDefinition` maps to the orphan `quote.blade.php`). The `BlockRegistry` and `BlockService` exist but operate on an incomplete definition set. This means server-side validation and rendering defaults are missing for 50 block types.

### Frontend SPA (React 19, Vite, TailwindCSS 4, DaisyUI 5)
- **Status: Feature-rich but inconsistent.** 68 block editor components with Preview/Editor pairs. Zustand state management. TanStack Query for API calls. TipTap WYSIWYG. dnd-kit drag-and-drop. Magazine freeform editor. Theme engine UI.
- **Risk:** Many blocks use inline raw HTML inputs instead of shared field components. Only 7 shared fields exist (TextField, TextArea, SelectField, NumberField, ToggleField, ColorField, ImageField). Blocks like `gallery`, `logostrip`, `testimonial` use raw URL text inputs instead of `AssetPicker`. No frontend tests exist.

### Block Editor System
- **Status: Largest subsystem, highest entropy.** 68 frontend components, 69 blade templates, 19 PHP definitions.
- **Risk:** The three layers (React editor, Blade render, PHP definition) are not synchronized. Key mismatches exist (see below). No automated audit ensures all three layers agree on data keys, defaults, or required fields.

### Publishing Pipeline
- **Status: Working.** Local, SSH, and ZIP deploy strategies. Diff system for change tracking. Version control.
- **Risk:** Published output depends on blade templates. If blade templates reference data keys that the frontend editor never sets, output will be broken silently.

### Theme Engine
- **Status: Implemented.** W3C Design Token format, resolver, compiler, overrides.
- **Risk:** Low, relatively self-contained.

### Media / Assets
- **Status: Working.** Spatie MediaLibrary integration. `AssetPicker` component exists and is used by ~10 blocks.
- **Risk:** Many blocks bypass `AssetPicker` and use raw URL text fields (see list below).

### Magazine Module
- **Status: MVP complete.** Freeform InDesign-like editor, flipbook viewer, AI wizard.
- **Risk:** Separate complexity center. Has its own editor canvas, styles, and properties system.

---

## Verified Counts

| Layer | Count | Location |
|-------|-------|----------|
| Frontend block components | **68** | `resources/admin/src/components/blocks/` |
| Blade templates | **69** | `resources/views/blocks/*.blade.php` |
| PHP BlockDefinition classes | **19** concrete + 1 base | `app/Domain/Blocks/Definitions/` |
| API controllers | **32** | `app/Http/Controllers/Api/V1/` |
| Test files | **41** | `tests/` |
| Documentation files | **16** | `docs/*.md` |
| Shared editor fields | **7** | `resources/admin/src/components/editor/fields/` |
| Shared property panels | **7** | `resources/admin/src/components/editor/properties/` |
| Block-related services | **3** | `app/Domain/Blocks/Services/` |

### Shared Editor Fields
`TextField`, `TextArea`, `SelectField`, `NumberField`, `ToggleField`, `ColorField`, `ImageField`

### Shared Property Panels
`AdvancedPanel`, `AnimationPanel`, `LayoutPanel`, `ResponsivePanel`, `SpacingPanel`, `TypographyPanel`, `VisualPanel`

### BackgroundEditor
A dedicated `BackgroundEditor` component exists with full gradient builder (linear/radial, color stops, angle), image backgrounds with AssetPicker, overlay, and scroll effects (fixed/parallax/zoom). Used by: `section`, `container`, `ctabanner`, `hero`.

---

## Frontend/Backend/Blade Block Mismatch

### Blocks with blade template but NO frontend component
| Block | Notes |
|-------|-------|
| `quote` | Blade exists at `quote.blade.php`. Frontend has `pullquote` instead. Likely a naming inconsistency. |

### Blocks with frontend component but NO blade template
_(None found -- all 68 frontend blocks have corresponding blade templates.)_

### Anomalies
- `scroll_page` has BOTH a `scroll_page.blade.php` file AND a `scroll_page/` directory (with `pages/` subfolder). This is unusual and may cause rendering conflicts.

### Blocks missing PHP BlockDefinition (50 of 68 frontend blocks)
The following blocks have frontend + blade but NO PHP definition class:

`anchormenu`, `audio`, `authorbox`, `beforeafter`, `breadcrumbs`, `caption`, `categorylist`, `chart`, `container`, `ctabanner`, `customform`, `dropcap`, `featurecomparison`, `featuregrid`, `footnote`, `fullbleed`, `gallery`, `grid`, `group`, `icon`, `imagecaption`, `latestposts`, `list`, `logostrip`, `map`, `menu`, `modal`, `newsletter`, `overlap`, `paragraph`, `paywall`, `postcard`, `postgrid`, `pricingcard`, `pricingtable`, `pullquote`, `readingprogress`, `relatedposts`, `runningtext`, `sharebuttons`, `sidenote`, `socialembed`, `stats`, `stickysidebar`, `table`, `testimonial`, `textdivider`, `timeline`, `toc`, `tooltip`

*(The 19 PHP definitions are: accordion, button, code, columns, contact-form, divider, flipbook, heading, hero, html-embed, image, quote, rich-text, scroll_page, section, spacer, tabs, text, video. Of these, `QuoteBlockDefinition` maps to the orphan `quote.blade.php` -- the frontend uses `pullquote` instead, so only 18 definitions align with a frontend component.)*

---

## Blocks Using Raw URL Inputs Instead of AssetPicker

These blocks accept URLs as plain text strings instead of using the `AssetPicker` or `ImageField` components:

| Block | Field | Current Input |
|-------|-------|---------------|
| `gallery` | Image URLs | Raw textarea (one URL per line) |
| `logostrip` | Logo URLs | Raw textarea (one URL per line) |
| `testimonial` | Avatar URL | Raw text input |
| `button` | Link URL | `TextField` (correct for links, not assets) |
| `customform` | Endpoint URL | Raw text input |
| `newsletter` | Endpoint URL | Raw text input |
| `video` | Video URL | `TextField` (but also has AssetPicker) |
| `socialembed` | Embed URL | Raw text input |
| `ctabanner` | Button URL | Raw text input |
| `hero` | CTA URL | Raw text input |
| `paywall` | CTA URL | Raw text input |
| `pricingcard` | CTA URL | Raw text input |
| `pricingtable` | CTA URL | Raw text input |

**Distinction:** Link URLs (button, CTA, endpoint) are correctly plain text. Image/media URLs (gallery, logostrip, testimonial avatar) should use `AssetPicker` instead.

---

## Blocks Using BackgroundEditor (Gradient Visual Builder)

These blocks use the proper `BackgroundEditor` component: `section`, `container`, `ctabanner`, `hero`.

All other blocks that render visual backgrounds use hardcoded CSS gradient strings in their Preview components only (not user-editable). This is acceptable for decorative preview elements but means no other blocks offer user-controlled gradients.

---

## Main Problems

### 1. Setup/Build Script Issues
- `composer setup` has been fixed to use `--prefix resources/admin` for npm commands, so it correctly targets the admin SPA.
- `composer dev` uses `npx concurrently` which requires `concurrently` to be installed at root (`npm install` in project root).
- `composer dev` runs `npm run dev` which uses the root Vite config (not the admin SPA). To develop the admin SPA, run `npm run dev` from `resources/admin/` separately.
- No `.nvmrc` or `engines` field to pin Node version.

### 2. README / Documentation Accuracy
- ~~`docs/BLOCKS.md` claims "69 block types"~~ — **Fixed.** Now correctly documents 68 frontend + 69 blade + 19 PHP defs.
- ~~No documentation covers the block definition gap~~ — **Fixed.** `docs/BLOCKS.md` and `README.md` now document the 50-block gap.
- No documentation mentions which blocks lack `AssetPicker` integration (6 blocks identified in README but not in BLOCKS.md).

### 3. Frontend/Backend/Blade Block Mismatch
- `quote` blade vs `pullquote` frontend -- naming inconsistency.
- `scroll_page` has both a file and directory in `views/blocks/`.
- 50 frontend blocks have no PHP definition class.

### 4. Incomplete Backend Block Definitions
- Only 19/68 blocks have `BlockDefinition` PHP classes.
- Server-side validation, default values, and schema enforcement are missing for 72% of blocks.
- The `BlockRegistry` cannot properly validate or transform data for undefined blocks.

### 5. Inconsistent Editor Fields
- Blocks use a mix of: shared `TextField`/`SelectField` components, raw `<input>` elements, raw `<textarea>` elements, and raw `<select>` elements.
- No `UrlField`, `TextareaField`, or `LinkField` shared components exist.
- No consistent pattern for which blocks use shared vs inline fields.

### 6. Raw URL Fields Instead of AssetPicker
- `gallery`, `logostrip`, and `testimonial` accept image URLs as raw text instead of using `AssetPicker`.
- These blocks cannot benefit from the media library (upload, browse, optimize).

### 7. Raw CSS Gradient Fields Instead of Visual Builder
- The `BackgroundEditor` component is well-built but only used by 4 blocks.
- Other blocks that could benefit (e.g., `fullbleed`, `overlap`, `stats`) have no background configuration.
- Not a critical bug, but a missed consistency opportunity.

### 8. Block Audit Script Exists but Not in CI
- `scripts/block-audit.sh` exists and verifies all three layers (React, Blade, PHP). Run via `composer audit-blocks`.
- However, it is not integrated into any CI pipeline or pre-commit hook.
- Block data keys can still drift between layers without automated detection on every push.

### 9. Missing Frontend Tests
- Zero frontend test files. No Jest, Vitest, or React Testing Library configured.
- `package.json` has no `test` script.
- 68 block components and complex editor logic are entirely untested on the frontend.

### 10. Too Many Features Without Quality Gates
- 68 blocks, magazine editor, theme engine, AI assistant, publishing pipeline, grid system, multi-tenancy -- all built without per-feature quality contracts.
- No CI pipeline visible. No pre-commit hooks. No lint configuration beyond Pint (PHP only).
- TypeScript is enabled but no `strict` mode verification was found in tsconfig.

---

## Priority Order

### Phase 1: Setup & Documentation Fix (1-2 days) -- PARTIALLY DONE
- [x] `composer setup` fixed to use `--prefix resources/admin`.
- [x] Block counts corrected in `docs/BLOCKS.md` and `README.md`.
- [x] "Known Gaps" / "Known Problems" sections added to README.
- [ ] Add `.nvmrc` with required Node version.
- [ ] Fix `composer dev` to also start admin SPA Vite (currently starts root Vite only).
- [ ] Verify `composer dev` works end-to-end on a fresh checkout.

### Phase 2: Block Audit Script (1 day) -- DONE
- [x] `scripts/block-audit.sh` exists and checks all three layers.
- [x] Available via `composer audit-blocks` and `composer audit-blocks-verbose`.
- [ ] Integrate into CI pipeline (when CI exists).

### Phase 3: Quality Contract (1 day)
- Define "Definition of Done" for blocks (see below).
- Create a `BLOCK-CHECKLIST.md` template.
- Add a Vitest config to `resources/admin/` with a single passing test.
- Add a PHPUnit test that verifies BlockRegistry contains all blade template names.

### Phase 4: Shared Field Components (2-3 days)
- Create missing shared fields: `UrlField`, `LinkField`, `TextareaField`, `AssetListField` (multi-image picker).
- Ensure all shared fields follow the same `{ label, value, onChange, placeholder }` interface.
- Document the field component API.

### Phase 5: Refactor Block Editor Panels (3-5 days, incremental)
- Refactor blocks one-by-one to use shared fields instead of raw inputs.
- Priority: `gallery` and `logostrip` (switch to `AssetListField`), `testimonial` (switch avatar to `AssetPicker`).
- Each refactor is a single commit, tested manually.

### Phase 6: Backend Block Definitions (5-7 days, incremental)
- Create `BlockDefinition` classes for all 50 missing blocks.
- Each definition specifies: type key, default data, validation rules, allowed children.
- Register all in `BlockRegistry`.
- Add PHPUnit test per definition.

### Phase 7: Per-Category Block Audit (3-5 days)
- Group blocks by category (text, media, layout, interactive, data, navigation).
- For each category: verify data key consistency between React editor, Blade template, and PHP definition.
- Fix any key mismatches found.
- Resolve the `quote`/`pullquote` naming conflict.
- Clean up `scroll_page` file/directory duplication.

### Phase 8: Testing & CI (3-5 days)
- Add Vitest + React Testing Library for critical block editor tests.
- Add PHPUnit feature tests for block CRUD API endpoints.
- Set up GitHub Actions (or equivalent) CI pipeline: lint, type-check, test (PHP + JS).
- Add pre-commit hook for lint + type-check.

---

## Definition of Done

A block is considered **complete and working** when ALL of the following are true:

1. **Frontend component exists** in `resources/admin/src/components/blocks/{name}/` with at least `Editor.tsx`, `Preview.tsx`, and `index.ts`.
2. **Blade template exists** at `resources/views/blocks/{name}.blade.php`.
3. **PHP BlockDefinition exists** in `app/Domain/Blocks/Definitions/{Name}BlockDefinition.php` with:
   - Correct type key matching the folder/blade name.
   - Default data values for all fields.
   - Validation rules for all fields.
4. **Block is registered** in `BlockRegistry`.
5. **Data keys match** across all three layers (React state keys = Blade variable names = PHP definition keys).
6. **Shared field components** are used in the editor (no raw `<input>` for fields that have a shared equivalent).
7. **AssetPicker** is used for any image/media/file URL fields (not raw text input).
8. **PHPUnit test exists** verifying the block can be created, updated, and rendered.
9. **Block audit script** passes with no warnings for this block.

---

## Claude Code Execution Rules

When working on recovery phases:

1. **Small phases only.** Each task is a single phase or sub-phase. Never combine unrelated changes.
2. **No unrelated changes.** If you notice something outside the current phase scope, note it but do not fix it.
3. **Return files.** Every response must list the exact files created or modified (absolute paths).
4. **Run tests.** After every change, run `composer test` and verify no regressions. If frontend changes, run `npx tsc --noEmit` from `resources/admin/`.
5. **Wait for approval.** Do not proceed to the next phase until the current phase is reviewed and approved.
6. **Commit messages.** Use conventional commits: `fix(blocks):`, `feat(audit):`, `refactor(editor):`, `docs:`, `test:`.
7. **No bulk rewrites.** Refactor blocks one at a time, not all at once.
8. **Preserve behavior.** Refactoring must not change rendered output or editor UX. If behavior must change, flag it explicitly.
