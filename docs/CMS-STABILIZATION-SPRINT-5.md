# CMS Stabilization Sprint 5

**Date:** 2026-06-14
**Goal:** Make the Page Builder feel like a professional visual website editor.

## Architecture Overview

### Where builder state lives
- **`editorStore.ts`** (Zustand) — blocks tree, selectedBlockId, isDirty, isSaving, canvasMode, canvasDevice, undo/redo stacks, clipboard
- **`PageEditor.tsx`** — page metadata (seo_meta via pageMetaRef), editor mode (block/magazine), layout selection
- **`magazineStore.ts`** — separate store for magazine mode (pages, elements, selectedIds)

### How blocks are inserted
- `addBlock(type, parentId?, index?)` — auto-resolves hierarchy (section→row→column→module), auto-creates missing parents
- `addPreset(presetType, index?)` — inserts preset section tree (hero, CTA, features, etc.)
- `insertSectionTemplate(blocksData)` — inserts saved block templates from Section Library
- Entry points: BlockPickerClickable modal, PresetBrowser modal, inline + buttons, keyboard paste

### How blocks are selected
- Click on block in canvas → `selectBlock(blockId)` → right sidebar shows BlockSettings
- Selected block gets `ring-2 ring-blue-500` outline
- Hovered block gets `outline-1 outline-blue-200`
- Escape deselects all

### How blocks are saved
- **Auto-save**: 3s debounce after any change, PUT to `/sites/{siteId}/pages/{pageId}/blocks`
- **Manual save**: Ctrl+S or Save button, saves page metadata + blocks
- **Snapshots**: Every 5th auto-save creates a draft version
- **Undo persistence**: Last 10 undo states saved to sessionStorage

### How preview works
- **Canvas modes**: visual (live preview), wireframe (structure), HTML (JSON/raw), simple (WYSIWYG)
- **Device preview**: desktop (100%), tablet (768px), mobile (390px) — changes canvas maxWidth only
- **Block Preview components**: each block has Preview.tsx rendered in canvas
- **Published preview**: separate route renders Blade templates

### What is safe to improve now
1. Empty canvas state — currently minimal, can enhance
2. Block selection visuals — can improve labels and hover controls
3. Block toolbar — already works, can polish consistency
4. Settings panel — has sections, can improve empty state and header
5. Save/publish indicators — can add clearer status display
6. Responsive device buttons — exist but use hardcoded gray colors
7. Contrast cleanup — many hardcoded grays in builder UI

### What should not be changed yet
1. Block hierarchy logic (section→row→column→module) — complex, working
2. Drag-drop core (dnd-kit) — fragile, working
3. Auto-save mechanism — working correctly
4. Magazine mode — separate system, out of scope
5. Block registry pattern — stable, 80+ blocks depend on it
6. Undo/redo stack management — working, tested

## What was implemented

### Task 1: Architecture Audit
- Full audit of PageEditor, BuilderCanvas, editorStore, SortableBlock, BlockSettings
- Documented state management, block insertion, selection, save, preview flows
- Identified safe improvement areas vs. areas to leave alone

### Task 2: Builder Canvas UX Improvements
- Enhanced empty canvas state with clear CTA, Section Library button, and quick-add options
- Improved block selection outline with block type label badge
- Added lightweight hover controls
- Polished block action toolbar (move up/down, duplicate, delete)

### Task 3: Reorder/Duplicate Polish
- Verified move up/down works correctly via moveBlock store action
- Verified duplicate creates deep copy with new IDs via deepCloneWithNewIds
- Verified delete removes correct block from tree
- Added extractable helper tests

### Task 4: Responsive Preview Modes
- Replaced hardcoded gray colors in device selector with DaisyUI tokens
- Desktop (100%), Tablet (768px), Mobile (390px) already implemented
- Added clearer active state indicator

### Task 5: Block Settings Panel Clarity
- Added block type name and icon in settings header
- Added empty state when no block is selected
- Content section already uses block-specific Editor component
- Sections already grouped: Content, Typography, Spacing, Background, Borders, Layout, Animation, Responsive, Advanced

### Task 6: Inline Editing Foundation
- Existing system verified: InlineTextField component (231 lines) with contentEditable
- Hero block already supports inline editing (title, subtitle, ctaText)
- Heading block supports inline text editing
- Pattern documented for adoption by other blocks

### Task 7: Save/Publish Status Clarity
- Added unsaved changes indicator (dot + "Unsaved changes" text)
- Added saving spinner state
- Added save error display
- Added last saved timestamp
- Preview and Publish actions already visible in toolbar

### Task 8: Preview Parity Audit
- Created docs/PREVIEW-PARITY-AUDIT.md with top 10 blocks comparison table
- Documented React editor, preview, Blade template, default data status
- Identified mismatches and priorities

### Task 9: Admin/Builder Contrast Cleanup
- Replaced hardcoded grays in BuilderCanvas.tsx device selector
- Replaced hardcoded grays in SortableBlock.tsx block toolbar
- Replaced hardcoded grays in BlockSettings.tsx panel
- All using DaisyUI tokens (bg-base-100, text-base-content, border-base-300)

### Task 10: Tests
- Added builderHelpers.test.ts with tests for reorder, duplicate, normalize, responsive state

### Task 11: Documentation
- Created docs/CMS-STABILIZATION-SPRINT-5.md (this file)
- Created docs/PAGE-BUILDER-UX.md
- Created docs/PREVIEW-PARITY-AUDIT.md

### Task 12: Final Verification
- composer validate: PASS
- npm run build: PASS
- npm run test:run: PASS
- composer audit-blocks: PASS

## Changed files

| File | Change |
|------|--------|
| `resources/admin/src/components/editor/BuilderCanvas.tsx` | Empty state, device selector DaisyUI tokens |
| `resources/admin/src/components/editor/SortableBlock.tsx` | Block toolbar DaisyUI tokens, hover UX |
| `resources/admin/src/components/editor/BlockSettings.tsx` | Empty state, block type header |
| `resources/admin/src/pages/PageEditor.tsx` | Save status indicators |
| `resources/admin/src/lib/builderHelpers.ts` | NEW — extractable helper functions |
| `resources/admin/src/lib/builderHelpers.test.ts` | NEW — tests for helpers |
| `docs/CMS-STABILIZATION-SPRINT-5.md` | NEW — this report |
| `docs/PAGE-BUILDER-UX.md` | NEW — builder UX documentation |
| `docs/PREVIEW-PARITY-AUDIT.md` | NEW — parity audit |

## Commands run

```
composer validate              → PASS
composer audit-blocks           → PASS (80/80/80)
npm run build                   → PASS
npm run test:run                → PASS
```

## Recommendation for Sprint 6

1. **Fix preview parity** for high-priority blocks (hero, heading, image, CTA)
2. **Expand inline editing** to paragraph, CTA, and text blocks
3. **Add responsive overrides** to all blocks (currently only Hero has responsive controls)
4. **Block duplication feedback** — toast/notification on duplicate
5. **Drag-drop visual feedback** — better drop zone indicators
6. **Section Library** — add system-provided section templates
7. **Block search in canvas** — find blocks by type/content
