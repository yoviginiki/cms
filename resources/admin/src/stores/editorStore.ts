import { create } from 'zustand';
import type { BlockData, BlockStyleProps } from '@/types/blocks';
import { blockRegistry } from '@/components/blocks/registry';

interface EditorState {
  blocks: BlockData[];
  selectedBlockId: string | null;
  isDirty: boolean;
  isSaving: boolean;
  editorMode: 'block' | 'magazine';
  undoStack: BlockData[][];
  redoStack: BlockData[][];
  maxUndoSteps: number;

  setBlocks: (blocks: BlockData[]) => void;
  setEditorMode: (mode: 'block' | 'magazine') => void;
  addBlock: (type: string, parentId?: string, index?: number) => void;
  updateBlock: (blockId: string, data: Partial<Record<string, unknown>>) => void;
  removeBlock: (blockId: string) => void;
  moveBlock: (activeId: string, overId: string, position: 'before' | 'after' | 'inside') => void;
  duplicateBlock: (blockId: string) => void;
  selectBlock: (blockId: string | null) => void;
  undo: () => void;
  redo: () => void;
  setDirty: (dirty: boolean) => void;
  setSaving: (saving: boolean) => void;
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
    children: block.children.map(deepCloneWithNewIds),
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
    const found = findInTree(blocks[i].children, id);
    if (found) return found;
  }
  return null;
}

function removeFromTree(blocks: BlockData[], id: string): BlockData[] {
  return blocks
    .filter((b) => b.id !== id)
    .map((b) => ({
      ...b,
      children: removeFromTree(b.children, id),
    }));
}

function reorder(blocks: BlockData[]): BlockData[] {
  return blocks.map((b, i) => ({ ...b, order: i }));
}

export const useEditorStore = create<EditorState>((set, get) => ({
  blocks: [],
  selectedBlockId: null,
  isDirty: false,
  isSaving: false,
  editorMode: 'block',
  undoStack: [],
  redoStack: [],
  maxUndoSteps: 50,

  setBlocks: (blocks) => {
    set({ blocks, isDirty: false, undoStack: [], redoStack: [] });
  },

  setEditorMode: (mode) => {
    set({ editorMode: mode });
  },

  addBlock: (type, parentId, index) => {
    const state = get();
    const reg = blockRegistry.get(type);
    if (!reg) return;

    const newBlock: BlockData = {
      id: generateId(),
      type,
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

    if (parentId) {
      const found = findInTree(newBlocks, parentId);
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
      blocks: prev,
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
      blocks: next,
      undoStack: [...state.undoStack, deepClone(state.blocks)],
      redoStack: newRedoStack,
      isDirty: true,
    });
  },

  setDirty: (dirty) => set({ isDirty: dirty }),
  setSaving: (saving) => set({ isSaving: saving }),
}));
