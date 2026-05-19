import { create } from 'zustand';
import type {
  MagElement,
  MagPageData,
  MagStyleDefinition,
  MagElementType,
} from '@/types/magazine';
import {
  DEFAULT_ELEMENT_STYLE,
  DEFAULT_TEXT_WRAP,
  DEFAULT_TYPOGRAPHY,
} from '@/types/magazine';

// ─── Types ───

type ToolType = 'select' | 'text' | 'image' | 'rectangle' | 'ellipse' | 'line';

type ViewMode = 'single' | 'spread' | 'grid';

interface MagazineState {
  pages: MagPageData[];
  currentPageNumber: number;
  selectedIds: string[];
  activeTool: ToolType;
  editingElementId: string | null;
  clipboard: MagElement[] | null;
  undoStack: string[];
  redoStack: string[];
  zoom: number;
  panOffset: { x: number; y: number };
  viewMode: ViewMode;
  gridColumns: number;
  showGrid: boolean;
  showGuides: boolean;
  showBaseline: boolean;
  snapEnabled: boolean;
  isDirty: boolean;
  isSaving: boolean;
  styles: MagStyleDefinition[];
}

interface MagazineActions {
  // Document
  setDocument: (pages: MagPageData[], styles: MagStyleDefinition[]) => void;
  getCurrentPageElements: () => MagElement[];

  // Pages
  setCurrentPage: (n: number) => void;
  addPage: (afterPage: number) => void;
  deletePage: (pageNumber: number) => void;
  updatePage: (pageNumber: number, updates: Partial<MagPageData>) => void;

  // Elements
  addElement: (type: string, x: number, y: number, width: number, height: number) => string;
  updateElement: (id: string, updates: Partial<MagElement>) => void;
  deleteElements: (ids: string[]) => void;
  duplicateElements: (ids: string[]) => void;

  // Selection
  selectElement: (id: string, addToSelection?: boolean) => void;
  clearSelection: () => void;
  selectAll: () => void;

  // Layer order
  bringToFront: (ids: string[]) => void;
  sendToBack: (ids: string[]) => void;

  // Clipboard
  copy: () => void;
  cut: () => void;
  paste: () => void;

  // Undo
  pushSnapshot: () => void;
  undo: () => void;
  redo: () => void;

  // View
  setZoom: (z: number) => void;
  setPan: (offset: { x: number; y: number }) => void;
  setTool: (tool: string) => void;
  toggleGrid: () => void;
  toggleGuides: () => void;
  toggleBaseline: () => void;
  toggleSnap: () => void;
  setViewMode: (mode: ViewMode) => void;
  setGridColumns: (cols: number) => void;

  // Persistence
  setDirty: (d: boolean) => void;
  setSaving: (s: boolean) => void;

  // Styles
  setStyles: (styles: MagStyleDefinition[]) => void;
  addStyle: (style: MagStyleDefinition) => void;
  updateStyle: (id: string, updates: Partial<MagStyleDefinition>) => void;
  deleteStyle: (id: string) => void;
}

// ─── Helpers ───

const MAX_UNDO = 50;

const TOOL_TO_ELEMENT_TYPE: Record<string, MagElementType> = {
  text: 'text_frame',
  image: 'image_frame',
  rectangle: 'rectangle',
  ellipse: 'ellipse',
  line: 'line',
};

const TEXT_ELEMENT_TYPES: Set<string> = new Set([
  'text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame',
  'footnote_frame', 'marginalia_frame',
]);

function getCurrentPage(state: MagazineState): MagPageData | undefined {
  return state.pages.find((p) => p.pageNumber === state.currentPageNumber);
}

function updateCurrentPageElements(
  pages: MagPageData[],
  currentPageNumber: number,
  updater: (elements: MagElement[]) => MagElement[],
): MagPageData[] {
  return pages.map((p) =>
    p.pageNumber === currentPageNumber
      ? { ...p, elements: updater(p.elements) }
      : p,
  );
}

function findElementById(elements: MagElement[], id: string): MagElement | undefined {
  for (const el of elements) {
    if (el.id === id) return el;
    if (el.children.length > 0) {
      const found = findElementById(el.children, id);
      if (found) return found;
    }
  }
  return undefined;
}

function updateElementInList(
  elements: MagElement[],
  id: string,
  updates: Partial<MagElement>,
): MagElement[] {
  return elements.map((el) => {
    if (el.id === id) return { ...el, ...updates };
    if (el.children.length > 0) {
      const updatedChildren = updateElementInList(el.children, id, updates);
      if (updatedChildren !== el.children) return { ...el, children: updatedChildren };
    }
    return el;
  });
}

function removeElementsFromList(elements: MagElement[], ids: Set<string>): MagElement[] {
  return elements
    .filter((el) => !ids.has(el.id))
    .map((el) =>
      el.children.length > 0
        ? { ...el, children: removeElementsFromList(el.children, ids) }
        : el,
    );
}

function makeDefaultElement(
  type: MagElementType,
  x: number,
  y: number,
  width: number,
  height: number,
  pageNumber: number,
  zIndex: number,
): MagElement {
  const hasTypography = TEXT_ELEMENT_TYPES.has(type);

  // Default data per element type
  const defaultData: Record<string, Record<string, unknown>> = {
    text_frame: { content: '<p>Type your text here</p>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnFill: 'auto', columnRule: false, textInset: { top: 8, right: 8, bottom: 8, left: 8 }, verticalAlign: 'top' },
    headline_frame: { content: '<h1>Headline</h1>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnFill: 'auto', columnRule: false, textInset: { top: 8, right: 8, bottom: 8, left: 8 }, verticalAlign: 'center' },
    pullquote_frame: { content: '<p><em>"Your quote here"</em></p>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnFill: 'auto', columnRule: false, textInset: { top: 16, right: 16, bottom: 16, left: 16 }, verticalAlign: 'center' },
    caption_frame: { content: '<p>Caption text</p>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnFill: 'auto', columnRule: false, textInset: { top: 4, right: 4, bottom: 4, left: 4 }, verticalAlign: 'top' },
    footnote_frame: { content: '<p>Footnote</p>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnFill: 'auto', columnRule: false, textInset: { top: 4, right: 4, bottom: 4, left: 4 }, verticalAlign: 'top' },
    marginalia_frame: { content: '<p>Side note</p>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnFill: 'auto', columnRule: false, textInset: { top: 4, right: 4, bottom: 4, left: 4 }, verticalAlign: 'top' },
    image_frame: { src: '', alt: '', fit: 'cover', focalPoint: { x: 0.5, y: 0.5 }, imageOffsetX: 0, imageOffsetY: 0, imageScale: 1, imageRotation: 0, clipShape: 'rectangle', clipPath: null, filters: { brightness: 100, contrast: 100, saturation: 100, grayscale: false, duotone: null } },
    circular_image: { src: '', alt: '', fit: 'cover', focalPoint: { x: 0.5, y: 0.5 }, imageOffsetX: 0, imageOffsetY: 0, imageScale: 1, imageRotation: 0, clipShape: 'ellipse', clipPath: null, filters: { brightness: 100, contrast: 100, saturation: 100, grayscale: false, duotone: null } },
    rectangle: { fillColor: '#e5e7eb', canContainText: false, textContent: null, sides: 4, innerRadius: 0, cornerRadius: { tl: 0, tr: 0, br: 0, bl: 0 } },
    ellipse: { fillColor: '#e5e7eb', canContainText: false, textContent: null, sides: 0, innerRadius: 0, cornerRadius: { tl: 0, tr: 0, br: 0, bl: 0 } },
    line: { x2: width, y2: 0, strokeWidth: 2, strokeColor: '#1a1a1a', strokeDash: 'solid', startCap: 'none', endCap: 'none' },
    video_frame: { url: '', posterAssetId: null, autoplay: false, aspectRatio: '16:9' },
    audio_player: { url: '', title: 'Audio', artist: '' },
    button: { text: 'Click here', url: '#', variant: 'solid', hoverColor: null },
    hotspot: { action: 'url', url: '#', tooltipContent: 'Click', targetPage: null, cursorStyle: 'pointer' },
    table_frame: { headers: ['Column 1', 'Column 2'], rows: [['Cell', 'Cell']], headerStyle: null, cellStyle: null, stripes: true, borderColor: '#e5e7eb' },
    chart_frame: { chartType: 'bar', data: [{ label: 'A', value: 30, color: null }, { label: 'B', value: 70, color: null }], showLegend: true, animated: false },
    infographic_number: { value: '100', label: 'Metric', prefix: '', suffix: '+', animated: false },
    page_number: { format: 'decimal', prefix: '', suffix: '', startAt: 1 },
    running_header: { source: 'custom', customText: 'Header text' },
    svg_icon: { name: 'star', customSvg: null, color: '#1a1a1a' },
    gradient_overlay: { fillColor: null, fillGradient: { type: 'linear', stops: [{ offset: 0, color: 'rgba(0,0,0,0.5)' }, { offset: 1, color: 'transparent' }], angle: 180 }, canContainText: false, textContent: null, sides: 4, innerRadius: 0, cornerRadius: { tl: 0, tr: 0, br: 0, bl: 0 } },
  };

  // Apply fill color for shapes
  const defaultStyle = { ...DEFAULT_ELEMENT_STYLE };
  if (type === 'rectangle' || type === 'ellipse') {
    defaultStyle.fill = { color: '#e5e7eb', opacity: 1, gradient: null };
  }
  if (type === 'gradient_overlay') {
    defaultStyle.fill = { color: null, opacity: 0.8, gradient: { type: 'linear', angle: 180, stops: [{ offset: 0, color: 'rgba(0,0,0,0.6)' }, { offset: 1, color: 'transparent' }] } };
  }

  return {
    id: crypto.randomUUID(),
    type,
    name: null,
    data: defaultData[type] || {},
    x,
    y,
    width,
    height,
    rotation: 0,
    scaleX: 1,
    scaleY: 1,
    zIndex,
    locked: false,
    visible: true,
    layerName: null,
    style: defaultStyle,
    typography: hasTypography ? { ...DEFAULT_TYPOGRAPHY } : null,
    textWrap: { ...DEFAULT_TEXT_WRAP },
    threadId: null,
    threadOrder: null,
    pageNumber,
    onMaster: false,
    parentId: null,
    children: [],
    responsiveOverrides: {},
  };
}

// ─── Store ───

export const useMagazineStore = create<MagazineState & MagazineActions>((set, get) => ({
  // ─── Initial state ───
  pages: [],
  currentPageNumber: 1,
  selectedIds: [],
  activeTool: 'select',
  editingElementId: null,
  clipboard: null,
  undoStack: [],
  redoStack: [],
  zoom: 1,
  panOffset: { x: 0, y: 0 },
  viewMode: 'single' as ViewMode,
  gridColumns: 2,
  showGrid: false,
  showGuides: true,
  showBaseline: false,
  snapEnabled: true,
  isDirty: false,
  isSaving: false,
  styles: [],

  // ─── Document ───

  setDocument(pages, styles) {
    set({
      pages,
      styles,
      currentPageNumber: pages.length > 0 ? pages[0].pageNumber : 1,
      selectedIds: [],
      editingElementId: null,
      undoStack: [],
      redoStack: [],
      isDirty: false,
    });
  },

  getCurrentPageElements() {
    const state = get();
    const page = getCurrentPage(state);
    return page ? page.elements : [];
  },

  // ─── Pages ───

  setCurrentPage(n) {
    set({ currentPageNumber: n, selectedIds: [], editingElementId: null });
  },

  addPage(afterPage) {
    const state = get();
    get().pushSnapshot();

    // Find the page to copy size/margins from, or use defaults
    const referencePage = state.pages.find((p) => p.pageNumber === afterPage) || state.pages[0];
    const newPageNumber = afterPage + 1;

    // Renumber pages after insertion point
    const renumbered = state.pages.map((p) =>
      p.pageNumber > afterPage ? { ...p, pageNumber: p.pageNumber + 1 } : p,
    );

    const newPage: MagPageData = {
      id: crypto.randomUUID(),
      pageNumber: newPageNumber,
      pageSize: referencePage
        ? { ...referencePage.pageSize }
        : { width: 210, height: 297 },
      margins: referencePage
        ? { ...referencePage.margins }
        : { top: 20, right: 20, bottom: 20, left: 20 },
      bleed: referencePage
        ? { ...referencePage.bleed }
        : { top: 3, right: 3, bottom: 3, left: 3 },
      columns: referencePage
        ? { ...referencePage.columns }
        : { count: 1, gutter: 12 },
      baselineGrid: referencePage
        ? { ...referencePage.baselineGrid }
        : { increment: 12, start: 0 },
      isMaster: false,
      masterPageId: referencePage?.masterPageId || null,
      spreadWith: null,
      backgroundColor: referencePage?.backgroundColor || null,
      backgroundAssetId: null,
      elements: [],
    };

    // Insert at the right position
    const insertIndex = renumbered.findIndex((p) => p.pageNumber === newPageNumber);
    const pages = [...renumbered];
    if (insertIndex === -1) {
      pages.push(newPage);
    } else {
      pages.splice(insertIndex, 0, newPage);
    }

    set({ pages, currentPageNumber: newPageNumber, isDirty: true });
  },

  deletePage(pageNumber) {
    const state = get();
    if (state.pages.length <= 1) return; // Never delete last page
    get().pushSnapshot();

    const remaining = state.pages
      .filter((p) => p.pageNumber !== pageNumber)
      .map((p, i) => ({ ...p, pageNumber: i + 1 }));

    const newCurrent = Math.min(state.currentPageNumber, remaining.length);

    set({
      pages: remaining,
      currentPageNumber: newCurrent,
      selectedIds: [],
      editingElementId: null,
      isDirty: true,
    });
  },

  updatePage(pageNumber, updates) {
    get().pushSnapshot();
    set((state) => ({
      pages: state.pages.map((p) =>
        p.pageNumber === pageNumber ? { ...p, ...updates } : p,
      ),
      isDirty: true,
    }));
  },

  // ─── Elements ───

  addElement(type, x, y, width, height) {
    const state = get();
    get().pushSnapshot();

    const elementType = TOOL_TO_ELEMENT_TYPE[type] || (type as MagElementType);
    const page = getCurrentPage(state);
    const maxZ = page
      ? Math.max(0, ...page.elements.map((e) => e.zIndex))
      : 0;

    const newElement = makeDefaultElement(
      elementType,
      x,
      y,
      width,
      height,
      state.currentPageNumber,
      maxZ + 1,
    );

    set((s) => ({
      pages: updateCurrentPageElements(s.pages, s.currentPageNumber, (els) => [
        ...els,
        newElement,
      ]),
      selectedIds: [newElement.id],
      isDirty: true,
    }));

    return newElement.id;
  },

  updateElement(id, updates) {
    set((state) => ({
      pages: updateCurrentPageElements(state.pages, state.currentPageNumber, (els) =>
        updateElementInList(els, id, updates),
      ),
      isDirty: true,
    }));
  },

  deleteElements(ids) {
    if (ids.length === 0) return;
    get().pushSnapshot();
    const idSet = new Set(ids);
    set((state) => ({
      pages: updateCurrentPageElements(state.pages, state.currentPageNumber, (els) =>
        removeElementsFromList(els, idSet),
      ),
      selectedIds: state.selectedIds.filter((id) => !idSet.has(id)),
      editingElementId:
        state.editingElementId && idSet.has(state.editingElementId)
          ? null
          : state.editingElementId,
      isDirty: true,
    }));
  },

  duplicateElements(ids) {
    if (ids.length === 0) return;
    const state = get();
    get().pushSnapshot();

    const page = getCurrentPage(state);
    if (!page) return;

    const maxZ = Math.max(0, ...page.elements.map((e) => e.zIndex));
    const newElements: MagElement[] = [];
    const newIds: string[] = [];

    ids.forEach((id, i) => {
      const original = findElementById(page.elements, id);
      if (!original) return;

      const dup: MagElement = {
        ...structuredClone(original),
        id: crypto.randomUUID(),
        x: original.x + 10,
        y: original.y + 10,
        zIndex: maxZ + 1 + i,
        name: original.name ? `${original.name} copy` : null,
        children: original.children.map((c) => ({
          ...structuredClone(c),
          id: crypto.randomUUID(),
        })),
      };

      newElements.push(dup);
      newIds.push(dup.id);
    });

    set((s) => ({
      pages: updateCurrentPageElements(s.pages, s.currentPageNumber, (els) => [
        ...els,
        ...newElements,
      ]),
      selectedIds: newIds,
      isDirty: true,
    }));
  },

  // ─── Selection ───

  selectElement(id, addToSelection = false) {
    set((state) => {
      if (addToSelection) {
        const already = state.selectedIds.includes(id);
        return {
          selectedIds: already
            ? state.selectedIds.filter((sid) => sid !== id)
            : [...state.selectedIds, id],
        };
      }
      return { selectedIds: [id] };
    });
  },

  clearSelection() {
    set({ selectedIds: [], editingElementId: null });
  },

  selectAll() {
    const page = getCurrentPage(get());
    if (!page) return;
    set({ selectedIds: page.elements.map((e) => e.id) });
  },

  // ─── Layer order ───

  bringToFront(ids) {
    if (ids.length === 0) return;
    get().pushSnapshot();
    const idSet = new Set(ids);
    set((state) => ({
      pages: updateCurrentPageElements(state.pages, state.currentPageNumber, (els) => {
        const maxZ = Math.max(0, ...els.map((e) => e.zIndex));
        let offset = 1;
        return els.map((el) =>
          idSet.has(el.id) ? { ...el, zIndex: maxZ + offset++ } : el,
        );
      }),
      isDirty: true,
    }));
  },

  sendToBack(ids) {
    if (ids.length === 0) return;
    get().pushSnapshot();
    const idSet = new Set(ids);
    set((state) => ({
      pages: updateCurrentPageElements(state.pages, state.currentPageNumber, (els) => {
        const minZ = Math.min(0, ...els.map((e) => e.zIndex));
        let offset = ids.length;
        return els.map((el) =>
          idSet.has(el.id) ? { ...el, zIndex: minZ - offset-- } : el,
        );
      }),
      isDirty: true,
    }));
  },

  // ─── Clipboard ───

  copy() {
    const state = get();
    const page = getCurrentPage(state);
    if (!page || state.selectedIds.length === 0) return;

    const elements = state.selectedIds
      .map((id) => findElementById(page.elements, id))
      .filter(Boolean) as MagElement[];

    set({ clipboard: structuredClone(elements) });
  },

  cut() {
    const state = get();
    get().copy();
    get().deleteElements(state.selectedIds);
  },

  paste() {
    const state = get();
    if (!state.clipboard || state.clipboard.length === 0) return;
    get().pushSnapshot();

    const page = getCurrentPage(state);
    const maxZ = page ? Math.max(0, ...page.elements.map((e) => e.zIndex)) : 0;

    const pasted: MagElement[] = state.clipboard.map((el, i) => ({
      ...structuredClone(el),
      id: crypto.randomUUID(),
      x: el.x + 20,
      y: el.y + 20,
      pageNumber: state.currentPageNumber,
      zIndex: maxZ + 1 + i,
      children: el.children.map((c) => ({
        ...structuredClone(c),
        id: crypto.randomUUID(),
      })),
    }));

    set((s) => ({
      pages: updateCurrentPageElements(s.pages, s.currentPageNumber, (els) => [
        ...els,
        ...pasted,
      ]),
      selectedIds: pasted.map((e) => e.id),
      isDirty: true,
    }));
  },

  // ─── Undo / Redo ───

  pushSnapshot() {
    set((state) => {
      const snapshot = JSON.stringify(state.pages);
      const undoStack = [...state.undoStack, snapshot];
      if (undoStack.length > MAX_UNDO) {
        undoStack.shift();
      }
      return { undoStack, redoStack: [] };
    });
  },

  undo() {
    const state = get();
    if (state.undoStack.length === 0) return;

    const currentSnapshot = JSON.stringify(state.pages);
    const previousSnapshot = state.undoStack[state.undoStack.length - 1];
    const pages = JSON.parse(previousSnapshot) as MagPageData[];

    set({
      pages,
      undoStack: state.undoStack.slice(0, -1),
      redoStack: [...state.redoStack, currentSnapshot],
      selectedIds: [],
      editingElementId: null,
      isDirty: true,
    });
  },

  redo() {
    const state = get();
    if (state.redoStack.length === 0) return;

    const currentSnapshot = JSON.stringify(state.pages);
    const nextSnapshot = state.redoStack[state.redoStack.length - 1];
    const pages = JSON.parse(nextSnapshot) as MagPageData[];

    set({
      pages,
      undoStack: [...state.undoStack, currentSnapshot],
      redoStack: state.redoStack.slice(0, -1),
      selectedIds: [],
      editingElementId: null,
      isDirty: true,
    });
  },

  // ─── View ───

  setZoom(z) {
    set({ zoom: Math.max(0.1, Math.min(8, z)) });
  },

  setPan(offset) {
    set({ panOffset: offset });
  },

  setTool(tool) {
    set({ activeTool: tool as ToolType });
  },

  toggleGrid() {
    set((s) => ({ showGrid: !s.showGrid }));
  },

  toggleGuides() {
    set((s) => ({ showGuides: !s.showGuides }));
  },

  toggleBaseline() {
    set((s) => ({ showBaseline: !s.showBaseline }));
  },

  toggleSnap() {
    set((s) => ({ snapEnabled: !s.snapEnabled }));
  },

  setViewMode(mode) {
    set({ viewMode: mode });
  },

  setGridColumns(cols) {
    set({ gridColumns: Math.max(1, Math.min(6, cols)) });
  },

  // ─── Persistence ───

  setDirty(d) {
    set({ isDirty: d });
  },

  setSaving(s) {
    set({ isSaving: s });
  },

  // ─── Styles ───

  setStyles(styles) {
    set({ styles });
  },

  addStyle(style) {
    set((state) => ({ styles: [...state.styles, style], isDirty: true }));
  },

  updateStyle(id, updates) {
    set((state) => ({
      styles: state.styles.map((s) => (s.id === id ? { ...s, ...updates } : s)),
      isDirty: true,
    }));
  },

  deleteStyle(id) {
    set((state) => ({
      styles: state.styles.filter((s) => s.id !== id),
      isDirty: true,
    }));
  },
}));
