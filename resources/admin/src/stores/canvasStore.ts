import { create } from 'zustand';
import type { BlockData } from '@/types/blocks';
import type { CanvasDoc, CanvasElement, CanvasSection, CanvasPageType, Breakpoint, BreakpointLayout, PinX, CanvasAnim, CanvasOp } from '@/types/canvas';
import { DEFAULT_CANVAS_WIDTH, DEFAULT_MOBILE_WIDTH } from '@/types/canvas';
import { blockToCanvas, canvasToBlocks, createElement, createSection, extractPassthrough } from '@/lib/canvasAdapter';
import { invertOp } from '@/lib/collabOps';

const MAX_UNDO = 50;

interface Snapshot { sections: CanvasSection[]; }

interface CanvasState {
  width: number;
  pageType: CanvasPageType;
  sections: CanvasSection[];
  passthrough: BlockData[];   // non-section top-level blocks, carried verbatim
  selectedIds: string[];      // selected element ids
  activeSectionId: string | null;
  activeBreakpoint: Breakpoint;
  mobileWidth: number;
  snapEnabled: boolean;
  gridSize: number;           // 12-col grid line spacing derived from width
  zoom: number;
  previewMobile: boolean;
  isDirty: boolean;
  undoStack: Snapshot[];
  redoStack: Snapshot[];
  // Collab: local mutations call this sink with the op AND its inverse (for
  // per-client undo); remote ops arrive via applyOp (which never re-emits).
  _localOp: ((op: CanvasOp, inverse: CanvasOp[]) => void) | null;

  setLocalOpSink: (fn: ((op: CanvasOp, inverse: CanvasOp[]) => void) | null) => void;
  applyOp: (op: CanvasOp) => void;

  // lifecycle
  loadFromBlocks: (blocks: BlockData[], meta: { pageType?: CanvasPageType; width?: number }) => void;
  toBlocks: () => BlockData[];
  markClean: () => void;
  setPageType: (t: CanvasPageType) => void;
  setWidth: (w: number) => void;

  // sections
  addSection: (afterId?: string) => void;
  deleteSection: (id: string) => void;
  moveSection: (id: string, dir: 'up' | 'down') => void;
  updateSectionSettings: (id: string, patch: Partial<CanvasSection['settings']>) => void;

  // elements
  addElement: (sectionId: string, blockType: string, x: number, y: number, w?: number, h?: number) => string;
  updateElement: (id: string, patch: Partial<CanvasElement>) => void;
  updateElements: (updates: Array<{ id: string; patch: Partial<CanvasElement> }>) => void;
  // Breakpoint-aware position write: desktop → base, mobile → bp.mobile override.
  updateElementLayout: (id: string, patch: BreakpointLayout, bp: Breakpoint) => void;
  clearMobileOverride: (id: string) => void;
  setElementPin: (id: string, pinX: PinX) => void;
  setElementAnim: (id: string, anim: CanvasAnim) => void;
  setBreakpoint: (bp: Breakpoint) => void;
  deleteElements: (ids: string[]) => void;
  duplicateElements: (ids: string[]) => void;
  bringToFront: (ids: string[]) => void;
  sendToBack: (ids: string[]) => void;

  // selection
  select: (id: string | null, add?: boolean) => void;
  selectMany: (ids: string[]) => void;
  clearSelection: () => void;
  setActiveSection: (id: string | null) => void;

  // undo / view
  pushSnapshot: () => void;
  undo: () => void;
  redo: () => void;
  toggleSnap: () => void;
  setZoom: (z: number) => void;
  setPreviewMobile: (v: boolean) => void;
}

const findSectionOf = (sections: CanvasSection[], elId: string): CanvasSection | undefined =>
  sections.find(s => s.elements.some(e => e.id === elId));

export const useCanvasStore = create<CanvasState>((set, get) => ({
  width: DEFAULT_CANVAS_WIDTH,
  pageType: 'website',
  sections: [],
  passthrough: [],
  selectedIds: [],
  activeSectionId: null,
  activeBreakpoint: 'desktop',
  mobileWidth: DEFAULT_MOBILE_WIDTH,
  snapEnabled: true,
  gridSize: DEFAULT_CANVAS_WIDTH / 12,
  zoom: 1,
  previewMobile: false,
  isDirty: false,
  undoStack: [],
  redoStack: [],
  _localOp: null,

  setLocalOpSink: (fn) => set({ _localOp: fn }),

  // Apply a peer op directly (no undo snapshot, no re-emit). LWW ordering is
  // decided by the caller (useCanvasCollab) before this runs.
  applyOp: (op) => set(s => {
    let sections = s.sections;
    if (op.t === 'layout') {
      sections = s.sections.map(sec => ({
        ...sec,
        elements: sec.elements.map(e => {
          if (e.id !== op.id) return e;
          if (op.bp === 'mobile') return { ...e, bp: { ...e.bp, mobile: { ...(e.bp?.mobile ?? {}), ...op.patch } } };
          return { ...e, ...op.patch };
        }),
      }));
    } else if (op.t === 'add') {
      sections = s.sections.map(sec => sec.id === op.sectionId
        ? { ...sec, elements: [...sec.elements.filter(e => e.id !== op.element.id), op.element] }
        : sec);
    } else if (op.t === 'del') {
      const ids = new Set(op.ids);
      sections = s.sections.map(sec => ({ ...sec, elements: sec.elements.filter(e => !ids.has(e.id)) }));
    } else if (op.t === 'z') {
      sections = s.sections.map(sec => {
        const zs = sec.elements.map(e => e.zIndex);
        const val = op.mode === 'front' ? Math.max(0, ...zs) + 1 : Math.min(0, ...zs) - 1;
        return { ...sec, elements: sec.elements.map(e => op.ids.includes(e.id) ? { ...e, zIndex: val } : e) };
      });
    } else if (op.t === 'pin') {
      sections = s.sections.map(sec => ({ ...sec, elements: sec.elements.map(e => e.id === op.id ? { ...e, pinX: op.pinX } : e) }));
    } else if (op.t === 'anim') {
      sections = s.sections.map(sec => ({ ...sec, elements: sec.elements.map(e => e.id === op.id ? { ...e, anim: op.anim } : e) }));
    } else if (op.t === 'mobileClear') {
      sections = s.sections.map(sec => ({
        ...sec,
        elements: sec.elements.map(e => {
          if (e.id !== op.id || !e.bp?.mobile) return e;
          const rest = { ...e.bp }; delete rest.mobile;
          const next = { ...e } as typeof e;
          if (Object.keys(rest).length) next.bp = rest; else delete next.bp;
          return next;
        }),
      }));
    } else if (op.t === 'secAdd') {
      if (!s.sections.some(x => x.id === op.section.id)) {
        const idx = op.afterId ? s.sections.findIndex(x => x.id === op.afterId) : s.sections.length - 1;
        const arr = [...s.sections]; arr.splice(idx + 1, 0, op.section); sections = arr;
      }
    } else if (op.t === 'secDel') {
      sections = s.sections.filter(x => x.id !== op.id);
    } else if (op.t === 'secMove') {
      const i = s.sections.findIndex(x => x.id === op.id);
      const j = op.dir === 'up' ? i - 1 : i + 1;
      if (i >= 0 && j >= 0 && j < s.sections.length) { const arr = [...s.sections]; [arr[i], arr[j]] = [arr[j], arr[i]]; sections = arr; }
    } else if (op.t === 'secSettings') {
      sections = s.sections.map(sec => sec.id === op.id ? { ...sec, settings: { ...sec.settings, ...op.patch } } : sec);
    } else if (op.t === 'restoreElement') {
      sections = s.sections.map(sec => {
        if (sec.id !== op.sectionId) {
          return { ...sec, elements: sec.elements.filter(e => e.id !== op.element.id) };
        }
        const has = sec.elements.some(e => e.id === op.element.id);
        return { ...sec, elements: has ? sec.elements.map(e => e.id === op.element.id ? op.element : e) : [...sec.elements, op.element] };
      });
    }
    return { sections, isDirty: true };
  }),

  loadFromBlocks: (blocks, meta) => {
    const doc: CanvasDoc = blockToCanvas(blocks, meta);
    set({
      width: doc.width,
      pageType: doc.pageType,
      sections: doc.sections,
      passthrough: extractPassthrough(blocks),
      gridSize: doc.width / 12,
      selectedIds: [],
      activeSectionId: doc.sections[0]?.id ?? null,
      activeBreakpoint: 'desktop',
      undoStack: [],
      redoStack: [],
      isDirty: false,
    });
  },

  toBlocks: () => canvasToBlocks({ pageType: get().pageType, width: get().width, sections: get().sections }, get().passthrough),
  markClean: () => set({ isDirty: false }),
  setPageType: (t) => set({ pageType: t, isDirty: true }),
  setWidth: (w) => { const width = Math.max(320, Math.min(3000, Math.round(w))); set({ width, gridSize: width / 12, isDirty: true }); },

  addSection: (afterId) => {
    get().pushSnapshot();
    const section = createSection();
    set(s => {
      const idx = afterId ? s.sections.findIndex(x => x.id === afterId) : s.sections.length - 1;
      const sections = [...s.sections];
      sections.splice(idx + 1, 0, section);
      return { sections, activeSectionId: section.id, isDirty: true };
    });
    const secAddOp: CanvasOp = { t: 'secAdd', section, afterId };
    get()._localOp?.(secAddOp, invertOp(secAddOp, get().sections));
  },

  deleteSection: (id) => {
    get().pushSnapshot();
    const op: CanvasOp = { t: 'secDel', id };
    const inverse = invertOp(op, get().sections); // capture the section before removal
    set(s => {
      const sections = s.sections.filter(x => x.id !== id);
      return { sections, activeSectionId: sections[0]?.id ?? null, selectedIds: [], isDirty: true };
    });
    get()._localOp?.(op, inverse);
  },

  moveSection: (id, dir) => {
    get().pushSnapshot();
    const op: CanvasOp = { t: 'secMove', id, dir };
    const inverse = invertOp(op, get().sections);
    set(s => {
      const i = s.sections.findIndex(x => x.id === id);
      const j = dir === 'up' ? i - 1 : i + 1;
      if (i < 0 || j < 0 || j >= s.sections.length) return {};
      const sections = [...s.sections];
      [sections[i], sections[j]] = [sections[j], sections[i]];
      return { sections, isDirty: true };
    });
    get()._localOp?.(op, inverse);
  },

  updateSectionSettings: (id, patch) => {
    const op: CanvasOp = { t: 'secSettings', id, patch };
    const inverse = invertOp(op, get().sections);
    set(s => ({
      sections: s.sections.map(sec => sec.id === id ? { ...sec, settings: { ...sec.settings, ...patch } } : sec),
      isDirty: true,
    }));
    get()._localOp?.(op, inverse);
  },

  addElement: (sectionId, blockType, x, y, w, h) => {
    get().pushSnapshot();
    const el = createElement(blockType, x, y, w, h);
    // stack above the section's current top element
    const section = get().sections.find(s => s.id === sectionId);
    el.zIndex = section ? Math.max(0, ...section.elements.map(e => e.zIndex)) + 1 : 1;
    set(s => ({
      sections: s.sections.map(sec => sec.id === sectionId ? { ...sec, elements: [...sec.elements, el] } : sec),
      selectedIds: [el.id],
      activeSectionId: sectionId,
      isDirty: true,
    }));
    const addOp: CanvasOp = { t: 'add', sectionId, element: el };
    get()._localOp?.(addOp, invertOp(addOp, get().sections));
    return el.id;
  },

  updateElement: (id, patch) => {
    set(s => ({
      sections: s.sections.map(sec => ({
        ...sec,
        elements: sec.elements.map(e => e.id === id ? { ...e, ...patch } : e),
      })),
      isDirty: true,
    }));
  },

  updateElements: (updates) => {
    const map = new Map(updates.map(u => [u.id, u.patch]));
    set(s => ({
      sections: s.sections.map(sec => ({
        ...sec,
        elements: sec.elements.map(e => map.has(e.id) ? { ...e, ...map.get(e.id)! } : e),
      })),
      isDirty: true,
    }));
  },

  updateElementLayout: (id, patch, bp) => {
    const op: CanvasOp = { t: 'layout', id, patch, bp };
    const inverse = invertOp(op, get().sections);
    set(s => ({
      sections: s.sections.map(sec => ({
        ...sec,
        elements: sec.elements.map(e => {
          if (e.id !== id) return e;
          if (bp === 'mobile') {
            return { ...e, bp: { ...e.bp, mobile: { ...(e.bp?.mobile ?? {}), ...patch } } };
          }
          return { ...e, ...patch };
        }),
      })),
      isDirty: true,
    }));
    get()._localOp?.(op, inverse);
  },

  clearMobileOverride: (id) => {
    get().pushSnapshot();
    const op: CanvasOp = { t: 'mobileClear', id };
    const inverse = invertOp(op, get().sections);
    set(s => ({
      sections: s.sections.map(sec => ({
        ...sec,
        elements: sec.elements.map(e => {
          if (e.id !== id || !e.bp?.mobile) return e;
          const rest = { ...e.bp };
          delete rest.mobile;
          const next = { ...e } as typeof e;
          if (Object.keys(rest).length) next.bp = rest; else delete next.bp;
          return next;
        }),
      })),
      isDirty: true,
    }));
    get()._localOp?.(op, inverse);
  },

  setElementPin: (id, pinX) => {
    get().pushSnapshot();
    const op: CanvasOp = { t: 'pin', id, pinX };
    const inverse = invertOp(op, get().sections);
    set(s => ({
      sections: s.sections.map(sec => ({
        ...sec,
        elements: sec.elements.map(e => (e.id === id ? { ...e, pinX } : e)),
      })),
      isDirty: true,
    }));
    get()._localOp?.(op, inverse);
  },

  setElementAnim: (id, anim) => {
    get().pushSnapshot();
    const op: CanvasOp = { t: 'anim', id, anim };
    const inverse = invertOp(op, get().sections);
    set(s => ({
      sections: s.sections.map(sec => ({
        ...sec,
        elements: sec.elements.map(e => (e.id === id ? { ...e, anim } : e)),
      })),
      isDirty: true,
    }));
    get()._localOp?.(op, inverse);
  },

  setBreakpoint: (bp) => set({ activeBreakpoint: bp, selectedIds: [] }),

  deleteElements: (ids) => {
    get().pushSnapshot();
    const op: CanvasOp = { t: 'del', ids };
    const inverse = invertOp(op, get().sections); // capture elements before removal
    const set2 = new Set(ids);
    set(s => ({
      sections: s.sections.map(sec => ({ ...sec, elements: sec.elements.filter(e => !set2.has(e.id)) })),
      selectedIds: s.selectedIds.filter(id => !set2.has(id)),
      isDirty: true,
    }));
    get()._localOp?.(op, inverse);
  },

  duplicateElements: (ids) => {
    get().pushSnapshot();
    const set2 = new Set(ids);
    const newIds: string[] = [];
    const created: Array<{ sectionId: string; element: CanvasElement }> = [];
    set(s => ({
      sections: s.sections.map(sec => {
        const dupes = sec.elements.filter(e => set2.has(e.id)).map(e => {
          const clone = createElement(e.blockType, e.x + 24, e.y + 24, e.width, e.height);
          clone.data = JSON.parse(JSON.stringify(e.data));
          clone.style = JSON.parse(JSON.stringify(e.style));
          clone.rotation = e.rotation;
          clone.zIndex = e.zIndex + 1;
          newIds.push(clone.id);
          created.push({ sectionId: sec.id, element: clone });
          return clone;
        });
        return dupes.length ? { ...sec, elements: [...sec.elements, ...dupes] } : sec;
      }),
      selectedIds: newIds,
      isDirty: true,
    }));
    const emit = get()._localOp;
    if (emit) created.forEach(({ sectionId, element }) => {
      const op: CanvasOp = { t: 'add', sectionId, element };
      emit(op, invertOp(op, get().sections));
    });
  },

  bringToFront: (ids) => {
    get().pushSnapshot();
    const op: CanvasOp = { t: 'z', ids, mode: 'front' };
    const inverse = invertOp(op, get().sections);
    set(s => ({
      sections: s.sections.map(sec => {
        const max = Math.max(0, ...sec.elements.map(e => e.zIndex));
        return { ...sec, elements: sec.elements.map(e => ids.includes(e.id) ? { ...e, zIndex: max + 1 } : e) };
      }),
      isDirty: true,
    }));
    get()._localOp?.(op, inverse);
  },

  sendToBack: (ids) => {
    get().pushSnapshot();
    const op: CanvasOp = { t: 'z', ids, mode: 'back' };
    const inverse = invertOp(op, get().sections);
    set(s => ({
      sections: s.sections.map(sec => {
        const min = Math.min(0, ...sec.elements.map(e => e.zIndex));
        return { ...sec, elements: sec.elements.map(e => ids.includes(e.id) ? { ...e, zIndex: min - 1 } : e) };
      }),
      isDirty: true,
    }));
    get()._localOp?.(op, inverse);
  },

  select: (id, add = false) => set(s => {
    if (id === null) return { selectedIds: [] };
    const sec = findSectionOf(s.sections, id);
    return {
      selectedIds: add ? (s.selectedIds.includes(id) ? s.selectedIds.filter(x => x !== id) : [...s.selectedIds, id]) : [id],
      activeSectionId: sec?.id ?? s.activeSectionId,
    };
  }),
  selectMany: (ids) => set({ selectedIds: ids }),
  clearSelection: () => set({ selectedIds: [] }),
  setActiveSection: (id) => set({ activeSectionId: id }),

  pushSnapshot: () => set(s => {
    const undoStack = [...s.undoStack, { sections: JSON.parse(JSON.stringify(s.sections)) }];
    if (undoStack.length > MAX_UNDO) undoStack.shift();
    return { undoStack, redoStack: [] };
  }),

  undo: () => set(s => {
    if (!s.undoStack.length) return {};
    const prev = s.undoStack[s.undoStack.length - 1];
    return {
      sections: prev.sections,
      undoStack: s.undoStack.slice(0, -1),
      redoStack: [...s.redoStack, { sections: JSON.parse(JSON.stringify(s.sections)) }],
      selectedIds: [],
      isDirty: true,
    };
  }),

  redo: () => set(s => {
    if (!s.redoStack.length) return {};
    const next = s.redoStack[s.redoStack.length - 1];
    return {
      sections: next.sections,
      redoStack: s.redoStack.slice(0, -1),
      undoStack: [...s.undoStack, { sections: JSON.parse(JSON.stringify(s.sections)) }],
      selectedIds: [],
      isDirty: true,
    };
  }),

  toggleSnap: () => set(s => ({ snapEnabled: !s.snapEnabled })),
  setZoom: (z) => set({ zoom: Math.max(0.25, Math.min(2, z)) }),
  setPreviewMobile: (v) => set({ previewMobile: v }),
}));
