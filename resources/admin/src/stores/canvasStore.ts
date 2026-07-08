import { create } from 'zustand';
import type { BlockData } from '@/types/blocks';
import type { CanvasDoc, CanvasElement, CanvasSection, CanvasPageType, Breakpoint, BreakpointLayout, PinX } from '@/types/canvas';
import { DEFAULT_CANVAS_WIDTH, DEFAULT_MOBILE_WIDTH } from '@/types/canvas';
import { blockToCanvas, canvasToBlocks, createElement, createSection, extractPassthrough } from '@/lib/canvasAdapter';

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
    set(s => {
      const section = createSection();
      const idx = afterId ? s.sections.findIndex(x => x.id === afterId) : s.sections.length - 1;
      const sections = [...s.sections];
      sections.splice(idx + 1, 0, section);
      return { sections, activeSectionId: section.id, isDirty: true };
    });
  },

  deleteSection: (id) => {
    get().pushSnapshot();
    set(s => {
      const sections = s.sections.filter(x => x.id !== id);
      return { sections, activeSectionId: sections[0]?.id ?? null, selectedIds: [], isDirty: true };
    });
  },

  moveSection: (id, dir) => {
    get().pushSnapshot();
    set(s => {
      const i = s.sections.findIndex(x => x.id === id);
      const j = dir === 'up' ? i - 1 : i + 1;
      if (i < 0 || j < 0 || j >= s.sections.length) return {};
      const sections = [...s.sections];
      [sections[i], sections[j]] = [sections[j], sections[i]];
      return { sections, isDirty: true };
    });
  },

  updateSectionSettings: (id, patch) => {
    set(s => ({
      sections: s.sections.map(sec => sec.id === id ? { ...sec, settings: { ...sec.settings, ...patch } } : sec),
      isDirty: true,
    }));
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
  },

  clearMobileOverride: (id) => {
    get().pushSnapshot();
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
  },

  setElementPin: (id, pinX) => {
    get().pushSnapshot();
    set(s => ({
      sections: s.sections.map(sec => ({
        ...sec,
        elements: sec.elements.map(e => (e.id === id ? { ...e, pinX } : e)),
      })),
      isDirty: true,
    }));
  },

  setBreakpoint: (bp) => set({ activeBreakpoint: bp, selectedIds: [] }),

  deleteElements: (ids) => {
    get().pushSnapshot();
    const set2 = new Set(ids);
    set(s => ({
      sections: s.sections.map(sec => ({ ...sec, elements: sec.elements.filter(e => !set2.has(e.id)) })),
      selectedIds: s.selectedIds.filter(id => !set2.has(id)),
      isDirty: true,
    }));
  },

  duplicateElements: (ids) => {
    get().pushSnapshot();
    const set2 = new Set(ids);
    const newIds: string[] = [];
    set(s => ({
      sections: s.sections.map(sec => {
        const dupes = sec.elements.filter(e => set2.has(e.id)).map(e => {
          const clone = createElement(e.blockType, e.x + 24, e.y + 24, e.width, e.height);
          clone.data = JSON.parse(JSON.stringify(e.data));
          clone.style = JSON.parse(JSON.stringify(e.style));
          clone.rotation = e.rotation;
          clone.zIndex = e.zIndex + 1;
          newIds.push(clone.id);
          return clone;
        });
        return dupes.length ? { ...sec, elements: [...sec.elements, ...dupes] } : sec;
      }),
      selectedIds: newIds,
      isDirty: true,
    }));
  },

  bringToFront: (ids) => {
    get().pushSnapshot();
    set(s => ({
      sections: s.sections.map(sec => {
        const max = Math.max(0, ...sec.elements.map(e => e.zIndex));
        return { ...sec, elements: sec.elements.map(e => ids.includes(e.id) ? { ...e, zIndex: max + 1 } : e) };
      }),
      isDirty: true,
    }));
  },

  sendToBack: (ids) => {
    get().pushSnapshot();
    set(s => ({
      sections: s.sections.map(sec => {
        const min = Math.min(0, ...sec.elements.map(e => e.zIndex));
        return { ...sec, elements: sec.elements.map(e => ids.includes(e.id) ? { ...e, zIndex: min - 1 } : e) };
      }),
      isDirty: true,
    }));
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
