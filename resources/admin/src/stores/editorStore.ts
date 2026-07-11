import { create } from 'zustand';
import type { BlockData, BlockStyleProps } from '@/types/blocks';
import { blockRegistry } from '@/components/blocks/registry';
import { getPreset } from '@/presets';

// ─── Undo persistence helpers ───
const UNDO_STORAGE_KEY = 'editor_undo_state';

function persistUndoState(blocks: BlockData[], undoStack: BlockData[][], redoStack: BlockData[][]) {
  try {
    // Only keep last 10 undo steps in storage to avoid quota issues
    const payload = JSON.stringify({
      blocks,
      undoStack: undoStack.slice(-10),
      redoStack: redoStack.slice(-10),
    });
    sessionStorage.setItem(UNDO_STORAGE_KEY, payload);
  } catch { /* quota exceeded — ignore */ }
}

function loadUndoState(): { blocks: BlockData[]; undoStack: BlockData[][]; redoStack: BlockData[][] } | null {
  try {
    const raw = sessionStorage.getItem(UNDO_STORAGE_KEY);
    if (!raw) return null;
    return JSON.parse(raw);
  } catch { return null; }
}

/** Granularity for Copy/Paste Style (P4 editor ergonomics). */
export type StylePart = 'all' | 'typography' | 'spacing' | 'colors' | 'borders';

/** Recursively drop undefined leaves + empty objects (for partial style picks). */
function stripUndefined(o: Record<string, any>): Record<string, any> {
  const out: Record<string, any> = {};
  for (const k in o) {
    const v = o[k];
    if (v === undefined) continue;
    if (v && typeof v === 'object' && !Array.isArray(v)) {
      const c = stripUndefined(v);
      if (Object.keys(c).length) out[k] = c;
    } else out[k] = v;
  }
  return out;
}

/** The subset of a block's style to copy for a given granularity. */
function pickStylePart(style: BlockStyleProps, part: StylePart): Partial<BlockStyleProps> {
  const s = style as any;
  switch (part) {
    case 'all': return style;
    case 'typography': return stripUndefined({ typography: s.typography });
    case 'spacing': return stripUndefined({ spacing: s.spacing });
    case 'colors': return stripUndefined({
      visual: { backgroundColor: s.visual?.backgroundColor, borderColor: s.visual?.borderColor },
      typography: { textColor: s.typography?.textColor },
    });
    case 'borders': return stripUndefined({
      visual: {
        borderWidth: s.visual?.borderWidth, borderColor: s.visual?.borderColor,
        borderStyle: s.visual?.borderStyle, borderRadius: s.visual?.borderRadius,
      },
    });
  }
}

/** Merge a partial style over a base, section-by-section (over wins per leaf). */
function mergeStyle(base: BlockStyleProps, over: Partial<BlockStyleProps>): BlockStyleProps {
  const out: any = { ...base };
  for (const k of ['typography', 'spacing', 'visual', 'layout'] as const) {
    if ((over as any)[k]) out[k] = { ...((base as any)[k] || {}), ...(over as any)[k] };
  }
  return out;
}

/** Nearest ancestor-or-self section block containing `id` (for Extend Style scope). */
function findScopeSection(blocks: BlockData[], id: string): BlockData | null {
  const dfs = (list: BlockData[], path: BlockData[]): BlockData[] | null => {
    for (const b of list) {
      const np = [...path, b];
      if (b.id === id) return np;
      const f = dfs(b.children ?? [], np);
      if (f) return f;
    }
    return null;
  };
  const path = dfs(blocks, []);
  if (!path) return null;
  for (let i = path.length - 1; i >= 0; i--) if (path[i].level === 'section') return path[i];
  return null;
}

interface EditorState {
  blocks: BlockData[];
  selectedBlockId: string | null;
  isDirty: boolean;
  isSaving: boolean;
  editorMode: 'simple' | 'block' | 'magazine' | 'canvas';
  canvasMode: 'visual' | 'wireframe' | 'html' | 'simple';
  canvasDevice: 'desktop' | 'tablet' | 'mobile';
  rawHtml: string;
  undoStack: BlockData[][];
  redoStack: BlockData[][];
  maxUndoSteps: number;

  setRawHtml: (html: string) => void;
  setBlocks: (blocks: BlockData[]) => void;
  setEditorMode: (mode: 'simple' | 'block' | 'magazine' | 'canvas') => void;
  setCanvasMode: (mode: 'visual' | 'wireframe' | 'html' | 'simple') => void;
  setCanvasDevice: (device: 'desktop' | 'tablet' | 'mobile') => void;
  addBlock: (type: string, parentId?: string, index?: number) => void;
  addPreset: (presetType: string, index?: number) => void;
  insertSectionTemplate: (blocksData: BlockData[]) => void;
  updateBlock: (blockId: string, data: Partial<Record<string, unknown>>) => void;
  removeBlock: (blockId: string) => void;
  moveBlock: (activeId: string, overId: string, position: 'before' | 'after' | 'inside') => void;
  duplicateBlock: (blockId: string) => void;
  selectBlock: (blockId: string | null) => void;
  undo: () => void;
  redo: () => void;
  clipboard: BlockData | null;
  copyBlock: (blockId: string) => void;
  pasteBlock: (parentId?: string) => void;
  styleClipboard: BlockStyleProps | null;
  copyStyle: (blockId: string) => void;
  pasteStyle: (blockId: string, part: StylePart) => void;
  /** Count of same-type blocks Extend Style would affect in a scope. */
  extendStyleCount: (blockId: string, scope: 'section' | 'page') => number;
  /** Apply a block's style to all same-type blocks in scope. Returns the count. */
  extendStyle: (blockId: string, scope: 'section' | 'page', part: StylePart) => number;
  setDirty: (dirty: boolean) => void;
  setSaving: (saving: boolean) => void;
  restoreUndoState: () => void;
}

function generateId(): string {
  return crypto.randomUUID();
}

function deepClone<T>(obj: T): T {
  return JSON.parse(JSON.stringify(obj));
}

function deepCloneWithNewIds(block: BlockData): BlockData {
  return {
    ...block,
    id: generateId(),
    data: deepClone(block.data),
    children: (block.children ?? []).map(deepCloneWithNewIds),
  };
}

function findInTree(
  blocks: BlockData[],
  id: string,
): { block: BlockData; parent: BlockData[]; index: number } | null {
  for (let i = 0; i < blocks.length; i++) {
    if (blocks[i].id === id) {
      return { block: blocks[i], parent: blocks, index: i };
    }
    const found = findInTree(blocks[i].children ?? [], id);
    if (found) return found;
  }
  return null;
}

function removeFromTree(blocks: BlockData[], id: string): BlockData[] {
  return blocks
    .filter((b) => b.id !== id)
    .map((b) => ({
      ...b,
      children: removeFromTree(b.children ?? [], id),
    }));
}

function reorder(blocks: BlockData[]): BlockData[] {
  return blocks.map((b, i) => ({ ...b, order: i }));
}

/** Ensure every block has children:[] (API may return undefined/null) */
function normalizeBlocks(blocks: unknown[]): BlockData[] {
  if (!Array.isArray(blocks)) return [];
  return blocks.map((b: any) => ({
    ...b,
    children: normalizeBlocks(b.children ?? []),
    data: b.data ?? {},
  }));
}

export const useEditorStore = create<EditorState>((set, get) => ({
  blocks: [],
  selectedBlockId: null,
  isDirty: false,
  isSaving: false,
  clipboard: null,
  editorMode: 'block',
  canvasMode: 'visual' as 'visual' | 'wireframe' | 'html' | 'simple',
  canvasDevice: 'desktop' as 'desktop' | 'tablet' | 'mobile',
  rawHtml: '',
  undoStack: [],
  redoStack: [],
  maxUndoSteps: 50,

  setRawHtml: (html) => {
    set({ rawHtml: html, isDirty: true });
  },

  setBlocks: (blocks) => {
    set({ blocks: normalizeBlocks(blocks), isDirty: false, undoStack: [], redoStack: [] });
  },

  setEditorMode: (mode) => {
    set({ editorMode: mode });
  },

  setCanvasMode: (mode) => {
    set({ canvasMode: mode });
  },

  setCanvasDevice: (device) => {
    set({ canvasDevice: device });
  },

  addBlock: (type, parentId, index) => {
    const state = get();
    const reg = blockRegistry.get(type);
    if (!reg) return;

    // Auto-resolve parent for hierarchy blocks when no explicit parent given
    const requiredParent: Record<string, string> = {
      row: 'section',
      column: 'row',
      module: 'column',
    };
    const blockLevel = reg.definition.level || 'module';
    const neededParentLevel = requiredParent[blockLevel];
    let resolvedParentId = parentId;

    if (neededParentLevel) {
      if (!resolvedParentId) {
        // Auto-resolve: use selected block if it matches, otherwise find first match
        const selected = state.selectedBlockId
          ? findInTree(state.blocks, state.selectedBlockId)
          : null;
        if (selected?.block.level === neededParentLevel) {
          resolvedParentId = selected.block.id;
        } else {
          // Search tree for first block at the needed level
          const findFirst = (blocks: BlockData[]): string | undefined => {
            for (const b of blocks) {
              if (b.level === neededParentLevel) return b.id;
              const found = findFirst(b.children);
              if (found) return found;
            }
            return undefined;
          };
          resolvedParentId = findFirst(state.blocks);
        }
      }
      // Verify resolved parent has the correct level
      if (resolvedParentId) {
        const parent = findInTree(state.blocks, resolvedParentId);
        if (parent && parent.block.level !== neededParentLevel) return;
      }
      if (!resolvedParentId) {
        // Auto-create section → row → column hierarchy, then add block inside
        const sectionReg = blockRegistry.get('section');
        const rowReg = blockRegistry.get('row');
        const columnReg = blockRegistry.get('column');
        if (!sectionReg || !rowReg || !columnReg) return;

        const newBlock: BlockData = {
          id: generateId(), type, level: reg.definition.level,
          data: deepClone(reg.definition.defaultData), children: [], order: 0,
        };
        const column: BlockData = { id: generateId(), type: 'column', level: 'column', data: deepClone(columnReg.definition.defaultData), children: [newBlock], order: 0 };
        const rowData = deepClone(rowReg.definition.defaultData);
        rowData.layout = '1/1'; // Single column — not the default 1/2+1/2
        const row: BlockData = { id: generateId(), type: 'row', level: 'row', data: rowData, children: [column], order: 0 };
        const section: BlockData = { id: generateId(), type: 'section', level: 'section', data: deepClone(sectionReg.definition.defaultData), children: [row], order: 0 };

        const undoStack = [...state.undoStack.slice(-(state.maxUndoSteps - 1)), deepClone(state.blocks)];
        set({ blocks: [...state.blocks, section], selectedBlockId: newBlock.id, undoStack, redoStack: [], isDirty: true });
        return;
      }
    }

    const newBlock: BlockData = {
      id: generateId(),
      type,
      level: reg.definition.level,
      data: deepClone(reg.definition.defaultData),
      children: [],
      order: 0,
    };

    // In magazine mode, auto-assign freeform positioning
    if (state.editorMode === 'magazine') {
      // Cascade position so new blocks don't stack on top of each other
      const existingCount = state.blocks.length;
      const offsetX = 40 + (existingCount % 5) * 30;
      const offsetY = 40 + (existingCount % 5) * 30;

      newBlock.style = {
        layout: {
          position: 'absolute',
          x: offsetX,
          y: offsetY,
          width: '300px',
          minHeight: '100px',
          zIndex: existingCount + 1,
        },
      };
    }

    const undoStack = [
      ...state.undoStack.slice(-(state.maxUndoSteps - 1)),
      deepClone(state.blocks),
    ];

    let newBlocks = deepClone(state.blocks);

    if (resolvedParentId) {
      const found = findInTree(newBlocks, resolvedParentId);
      if (found) {
        const insertAt = index ?? found.block.children.length;
        found.block.children.splice(insertAt, 0, newBlock);
        found.block.children = reorder(found.block.children);
      }
    } else {
      const insertAt = index ?? newBlocks.length;
      newBlocks.splice(insertAt, 0, newBlock);
      newBlocks = reorder(newBlocks);
    }

    set({
      blocks: newBlocks,
      undoStack,
      redoStack: [],
      isDirty: true,
      selectedBlockId: newBlock.id,
    });
  },

  addPreset: (presetType, index) => {
    const preset = getPreset(presetType);
    if (!preset) return;

    const state = get();
    const undoStack = [
      ...state.undoStack.slice(-(state.maxUndoSteps - 1)),
      deepClone(state.blocks),
    ];

    const presetTree = preset.build();
    const newBlocks = deepClone(state.blocks);
    const insertAt = index ?? newBlocks.length;
    newBlocks.splice(insertAt, 0, presetTree);

    set({
      blocks: reorder(newBlocks),
      undoStack,
      redoStack: [],
      isDirty: true,
      selectedBlockId: presetTree.id,
    });
  },

  insertSectionTemplate: (blocksData) => {
    if (!blocksData || blocksData.length === 0) return;
    const state = get();
    const undoStack = [
      ...state.undoStack.slice(-(state.maxUndoSteps - 1)),
      deepClone(state.blocks),
    ];
    const newBlocks = deepClone(state.blocks);
    const cloned = blocksData.map(deepCloneWithNewIds);
    newBlocks.push(...cloned);
    set({
      blocks: reorder(newBlocks),
      undoStack,
      redoStack: [],
      isDirty: true,
      selectedBlockId: cloned[0]?.id ?? null,
    });
  },

  updateBlock: (blockId, data) => {
    const state = get();
    const undoStack = [
      ...state.undoStack.slice(-(state.maxUndoSteps - 1)),
      deepClone(state.blocks),
    ];

    const newBlocks = deepClone(state.blocks);
    const found = findInTree(newBlocks, blockId);
    if (!found) return;

    // Handle special __style, __animation, __responsive, __advanced keys from property panels
    if (data.__style) {
      found.block.style = data.__style as BlockStyleProps;
      delete data.__style;
    }
    if (data.__animation) {
      found.block.animation = data.__animation as BlockData['animation'];
      delete data.__animation;
    }
    if (data.__responsive) {
      found.block.responsive = data.__responsive as BlockData['responsive'];
      delete data.__responsive;
    }
    if (data.__advanced) {
      found.block.advanced = data.__advanced as BlockData['advanced'];
      delete data.__advanced;
    }

    // Merge remaining keys into block.data
    if (Object.keys(data).length > 0) {
      found.block.data = { ...found.block.data, ...data };
    }

    set({ blocks: newBlocks, undoStack, redoStack: [], isDirty: true });
  },

  removeBlock: (blockId) => {
    const state = get();
    const undoStack = [
      ...state.undoStack.slice(-(state.maxUndoSteps - 1)),
      deepClone(state.blocks),
    ];

    const newBlocks = reorder(removeFromTree(state.blocks, blockId));

    set({
      blocks: newBlocks,
      undoStack,
      redoStack: [],
      isDirty: true,
      selectedBlockId:
        state.selectedBlockId === blockId ? null : state.selectedBlockId,
    });
  },

  moveBlock: (activeId, overId, position) => {
    const state = get();
    const undoStack = [
      ...state.undoStack.slice(-(state.maxUndoSteps - 1)),
      deepClone(state.blocks),
    ];

    let newBlocks = deepClone(state.blocks);
    const activeFound = findInTree(newBlocks, activeId);
    if (!activeFound) return;

    // Enforce hierarchy containment on move
    const moveParentReq: Record<string, string> = {
      row: 'section',
      column: 'row',
      module: 'column',
    };
    const activeLevel = activeFound.block.level;
    if (activeLevel && moveParentReq[activeLevel]) {
      const neededParent = moveParentReq[activeLevel];
      if (position === 'inside') {
        const target = findInTree(newBlocks, overId);
        if (!target || target.block.level !== neededParent) return;
      } else {
        // before/after — target must be same level (reorder among siblings)
        const target = findInTree(newBlocks, overId);
        if (!target || target.block.level !== activeLevel) return;
      }
    }

    const movingBlock = deepClone(activeFound.block);
    newBlocks = removeFromTree(newBlocks, activeId);

    if (position === 'inside') {
      const target = findInTree(newBlocks, overId);
      if (target) {
        target.block.children.push(movingBlock);
        target.block.children = reorder(target.block.children);
      }
    } else {
      const target = findInTree(newBlocks, overId);
      if (target) {
        const insertIndex =
          position === 'after' ? target.index + 1 : target.index;
        target.parent.splice(insertIndex, 0, movingBlock);
        const parentArr = target.parent;
        for (let i = 0; i < parentArr.length; i++) {
          parentArr[i] = { ...parentArr[i], order: i };
        }
      }
    }

    newBlocks = reorder(newBlocks);

    set({ blocks: newBlocks, undoStack, redoStack: [], isDirty: true });
  },

  duplicateBlock: (blockId) => {
    const state = get();
    const undoStack = [
      ...state.undoStack.slice(-(state.maxUndoSteps - 1)),
      deepClone(state.blocks),
    ];

    const newBlocks = deepClone(state.blocks);
    const found = findInTree(newBlocks, blockId);
    if (!found) return;

    const clone = deepCloneWithNewIds(found.block);

    // In magazine mode, offset the duplicate so it's visible
    if (state.editorMode === 'magazine' && clone.style?.layout) {
      clone.style.layout = {
        ...clone.style.layout,
        x: ((clone.style.layout.x as number) ?? 0) + 20,
        y: ((clone.style.layout.y as number) ?? 0) + 20,
      };
    }

    found.parent.splice(found.index + 1, 0, clone);

    for (let i = 0; i < found.parent.length; i++) {
      found.parent[i] = { ...found.parent[i], order: i };
    }

    set({
      blocks: newBlocks,
      undoStack,
      redoStack: [],
      isDirty: true,
      selectedBlockId: clone.id,
    });
  },

  selectBlock: (blockId) => {
    set({ selectedBlockId: blockId });
  },

  undo: () => {
    const state = get();
    if (state.undoStack.length === 0) return;

    const prev = state.undoStack[state.undoStack.length - 1];
    const newUndoStack = state.undoStack.slice(0, -1);

    set({
      blocks: normalizeBlocks(prev),
      undoStack: newUndoStack,
      redoStack: [...state.redoStack, deepClone(state.blocks)],
      isDirty: true,
    });
  },

  redo: () => {
    const state = get();
    if (state.redoStack.length === 0) return;

    const next = state.redoStack[state.redoStack.length - 1];
    const newRedoStack = state.redoStack.slice(0, -1);

    set({
      blocks: normalizeBlocks(next),
      undoStack: [...state.undoStack, deepClone(state.blocks)],
      redoStack: newRedoStack,
      isDirty: true,
    });
  },

  copyBlock: (blockId) => {
    const state = get();
    const found = findInTree(deepClone(state.blocks), blockId);
    if (found) set({ clipboard: found.block });
  },

  styleClipboard: null,

  copyStyle: (blockId) => {
    const found = findInTree(deepClone(get().blocks), blockId);
    if (found) set({ styleClipboard: (found.block.style ?? {}) as BlockStyleProps });
  },

  pasteStyle: (blockId, part) => {
    const state = get();
    if (!state.styleClipboard) return;
    const picked = pickStylePart(state.styleClipboard, part);
    const undoStack = [...state.undoStack.slice(-(state.maxUndoSteps - 1)), deepClone(state.blocks)];
    const newBlocks = deepClone(state.blocks);
    const found = findInTree(newBlocks, blockId);
    if (!found) return;
    found.block.style = mergeStyle((found.block.style ?? {}) as BlockStyleProps, picked);
    set({ blocks: newBlocks, undoStack, redoStack: [], isDirty: true });
  },

  extendStyleCount: (blockId, scope) => {
    const state = get();
    const found = findInTree(state.blocks, blockId);
    if (!found) return 0;
    const section = scope === 'section' ? findScopeSection(state.blocks, blockId) : null;
    const root = scope === 'section' ? (section ? [section] : []) : state.blocks;
    let n = 0;
    const walk = (list: BlockData[]) => { for (const b of list) { if (b.type === found.block.type && b.id !== blockId) n++; walk(b.children ?? []); } };
    walk(root);
    return n;
  },

  extendStyle: (blockId, scope, part) => {
    const state = get();
    const found = findInTree(state.blocks, blockId);
    if (!found) return 0;
    const picked = pickStylePart((found.block.style ?? {}) as BlockStyleProps, part);
    const type = found.block.type;
    const undoStack = [...state.undoStack.slice(-(state.maxUndoSteps - 1)), deepClone(state.blocks)];
    const newBlocks = deepClone(state.blocks);
    const section = scope === 'section' ? findScopeSection(newBlocks, blockId) : null;
    const root = scope === 'section' ? (section ? [section] : []) : newBlocks;
    let count = 0;
    const walk = (list: BlockData[]) => {
      for (const b of list) {
        if (b.type === type && b.id !== blockId) { b.style = mergeStyle((b.style ?? {}) as BlockStyleProps, picked); count++; }
        walk(b.children ?? []);
      }
    };
    walk(root);
    if (count > 0) set({ blocks: newBlocks, undoStack, redoStack: [], isDirty: true });
    return count;
  },

  pasteBlock: (parentId) => {
    const state = get();
    if (!state.clipboard) return;

    const undoStack = [...state.undoStack.slice(-(state.maxUndoSteps - 1)), deepClone(state.blocks)];
    const newBlocks = deepClone(state.blocks);

    // Re-generate IDs for the pasted block + all children
    function reId(b: BlockData): BlockData {
      return { ...b, id: generateId(), children: (b.children || []).map(reId) };
    }
    const pasted = reId(deepClone(state.clipboard));

    if (parentId) {
      const parent = findInTree(newBlocks, parentId);
      if (parent) parent.block.children.push(pasted);
    } else {
      newBlocks.push(pasted);
    }

    set({ blocks: newBlocks, undoStack, redoStack: [], isDirty: true, selectedBlockId: pasted.id });
  },

  setDirty: (dirty) => set({ isDirty: dirty }),
  setSaving: (saving) => set({ isSaving: saving }),

  restoreUndoState: () => {
    const saved = loadUndoState();
    if (saved && saved.blocks.length > 0) {
      set({
        blocks: normalizeBlocks(saved.blocks),
        undoStack: saved.undoStack,
        redoStack: saved.redoStack,
      });
    }
  },
}));

// Persist undo state to sessionStorage on changes (debounced)
let persistTimer: ReturnType<typeof setTimeout> | undefined;
useEditorStore.subscribe(
  (state) => {
    if (persistTimer) clearTimeout(persistTimer);
    persistTimer = setTimeout(() => {
      persistUndoState(state.blocks, state.undoStack, state.redoStack);
    }, 1000);
  }
);
