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
type LayoutMode = 'single' | 'book' | 'presentation';

interface IssueSettings {
  layoutMode: LayoutMode;
  coverMode: 'standalone' | 'spread';
  readingDirection: 'ltr' | 'rtl';
}

type DebugSeverity = 'info' | 'warn' | 'error';

interface DebugLogEntry {
  ts: number;
  action: string;
  severity: DebugSeverity;
  source: string;
  selectedId?: string | null;
  elementType?: string | null;
  pageNumber?: number | null;
  pageId?: string | null;
  detail?: any;
}

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
  editingMasterId: string | null;
  issueSettings: IssueSettings;
  debugLog: DebugLogEntry[];
}

interface MagazineActions {
  // Document
  setDocument: (pages: MagPageData[], styles: MagStyleDefinition[]) => void;
  getCurrentPageElements: () => MagElement[];

  // Pages
  setCurrentPage: (n: number) => void;
  addPage: (afterPage: number) => void;
  deletePage: (pageNumber: number) => void;
  duplicatePage: (pageNumber: number) => void;
  reorderPages: (fromIndex: number, toIndex: number) => void;
  updatePage: (pageNumber: number, updates: Partial<MagPageData>) => void;

  // Elements
  addElement: (type: string, x: number, y: number, width: number, height: number) => string;
  updateElement: (id: string, updates: Partial<MagElement>) => void;
  deleteElements: (ids: string[]) => void;
  duplicateElements: (ids: string[]) => void;
  moveElementToPage: (elementId: string, fromPage: number, toPage: number, newX?: number, newY?: number) => void;
  continueTextToNextPage: (elementId: string) => void;

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

  // Text auto-flow
  autoFlowText: () => void;

  // Issue settings
  setIssueSettings: (settings: Partial<IssueSettings>) => void;

  // Master pages
  getMasterPages: () => MagPageData[];
  addMasterPage: (name: string) => void;
  assignMaster: (pageNumber: number, masterPageId: string | null) => void;
  assignMasterToAll: (masterPageId: string | null) => void;
  setEditingMaster: (masterPageId: string | null) => void;
  editingMasterId: string | null;

  // Debug
  pushDebugLog: (action: string, source: string, detail?: any, severity?: DebugSeverity) => void;
  clearDebugLog: () => void;

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
    polygon_image: { src: '', alt: '', fit: 'cover', focalPoint: { x: 0.5, y: 0.5 }, imageOffsetX: 0, imageOffsetY: 0, imageScale: 1, imageRotation: 0, clipShape: 'polygon', clipPath: null, filters: { brightness: 100, contrast: 100, saturation: 100, grayscale: false, duotone: null } },
    fullbleed_image: { src: '', alt: '', fit: 'cover', focalPoint: { x: 0.5, y: 0.5 }, imageOffsetX: 0, imageOffsetY: 0, imageScale: 1, imageRotation: 0, clipShape: 'rectangle', clipPath: null, filters: { brightness: 100, contrast: 100, saturation: 100, grayscale: false, duotone: null } },
    gallery_frame: { images: [], layout: 'grid', columns: 2, gap: 8 },
    background_image: { src: '', alt: '', fit: 'cover', focalPoint: { x: 0.5, y: 0.5 }, imageOffsetX: 0, imageOffsetY: 0, imageScale: 1, imageRotation: 0, clipShape: 'rectangle', clipPath: null, filters: { brightness: 100, contrast: 100, saturation: 100, grayscale: false, duotone: null } },
    rectangle: { fillColor: '#e5e7eb', canContainText: false, textContent: null, sides: 4, innerRadius: 0, cornerRadius: { tl: 0, tr: 0, br: 0, bl: 0 } },
    ellipse: { fillColor: '#e5e7eb', canContainText: false, textContent: null, sides: 0, innerRadius: 0, cornerRadius: { tl: 0, tr: 0, br: 0, bl: 0 } },
    polygon: { fillColor: '#e5e7eb', canContainText: false, textContent: null, sides: 6, innerRadius: 0, cornerRadius: { tl: 0, tr: 0, br: 0, bl: 0 } },
    freeform_path: { fillColor: null, strokeColor: '#1a1a1a', strokeWidth: 2, path: '' },
    line: { x2: width, y2: 0, strokeWidth: 2, strokeColor: '#1a1a1a', strokeDash: 'solid', startCap: 'none', endCap: 'none' },
    decorative_rule: { strokeColor: '#999', strokeWidth: 2, strokeStyle: 'solid', ornament: 'none' },
    video_frame: { url: '', posterAssetId: null, autoplay: false, aspectRatio: '16:9' },
    audio_player: { url: '', title: 'Audio', artist: '' },
    embed_frame: { html: '', sandbox: true },
    button: { text: 'Click here', url: '#', variant: 'solid', hoverColor: null },
    hotspot: { action: 'url', url: '#', tooltipContent: 'Click', targetPage: null, cursorStyle: 'pointer' },
    tooltip_trigger: { tooltipContent: 'Tooltip text', position: 'top' },
    accordion_frame: { sections: [{ title: 'Section 1', content: 'Content here' }], openFirst: true },
    slidein_panel: { direction: 'right', triggerLabel: 'Open panel', content: '<p>Panel content</p>' },
    table_frame: { headers: ['Column 1', 'Column 2'], rows: [['Cell', 'Cell']], headerStyle: null, cellStyle: null, stripes: true, borderColor: '#e5e7eb' },
    chart_frame: { chartType: 'bar', data: [{ label: 'A', value: 30, color: null }, { label: 'B', value: 70, color: null }], showLegend: true, animated: false },
    infographic_number: { value: '100', label: 'Metric', prefix: '', suffix: '+', animated: false },
    progress_indicator: { value: 60, max: 100, label: 'Progress', showLabel: true, color: null },
    page_number: { format: 'decimal', prefix: '', suffix: '', startAt: 1 },
    running_header: { source: 'custom', customText: 'Header text' },
    column_guides: { columns: 3, gutter: 12, showLabels: false },
    svg_icon: { name: 'star', customSvg: null, color: '#1a1a1a' },
    gradient_overlay: { fillColor: null, fillGradient: { type: 'linear', stops: [{ offset: 0, color: 'rgba(0,0,0,0.5)' }, { offset: 1, color: 'transparent' }], angle: 180 }, canContainText: false, textContent: null, sides: 4, innerRadius: 0, cornerRadius: { tl: 0, tr: 0, br: 0, bl: 0 } },
    group: { label: 'Group' },
    clipping_group: { label: 'Clipping group', clipShape: 'rectangle' },
    component_instance: { componentId: '', overrides: {} },
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
    positionMode: 'free',
    spanMode: 'page',
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
  editingMasterId: null,
  issueSettings: { layoutMode: 'single', coverMode: 'standalone', readingDirection: 'ltr' },
  debugLog: [],

  // ─── Debug ───

  pushDebugLog(action, source, detail, severity = 'info') {
    // No-op when debug mode is off — avoid perf cost of logging in production
    if (typeof localStorage !== 'undefined' && localStorage.getItem('dtp-debug') !== '1') return;
    const state = get();
    const selectedId = state.selectedIds[0] || null;
    const selectedEl = selectedId
      ? state.pages.flatMap(p => p.elements).find(e => e.id === selectedId)
      : null;
    const page = state.pages.find(p => p.pageNumber === state.currentPageNumber);
    set(s => {
      const entry: DebugLogEntry = {
        ts: Date.now(), action, severity, source, detail,
        selectedId, elementType: selectedEl?.type || null,
        pageNumber: state.currentPageNumber, pageId: page?.id || null,
      };
      const log = [...s.debugLog, entry];
      if (log.length > 500) log.splice(0, log.length - 500);
      return { debugLog: log };
    });
  },

  clearDebugLog() {
    set({ debugLog: [] });
  },

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
    get().pushDebugLog('load:success', 'store', { pageCount: pages.length, styleCount: styles.length });
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
    get().pushDebugLog('page:add', 'store', { afterPage, newPageNumber });
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
    get().pushDebugLog('page:delete', 'store', { pageNumber });
  },

  duplicatePage(pageNumber) {
    const state = get();
    get().pushSnapshot();

    const sourcePage = state.pages.find(p => p.pageNumber === pageNumber);
    if (!sourcePage) return;

    // Deep clone page with new IDs for page and all elements (recursive)
    const newPageId = crypto.randomUUID();
    function reIdElement(el: MagElement): MagElement {
      return {
        ...structuredClone(el),
        id: crypto.randomUUID(),
        children: el.children.map(c => reIdElement(c)),
      };
    }
    const newElements = sourcePage.elements.map(el => reIdElement(el));

    // Relink thread references within the duplicated page
    newElements.forEach(el => {
      if (el.threadId) {
        // Only keep thread link if both source and target are on this page
        const threadOnPage = newElements.some(other => other.id !== el.id && other.threadId === el.threadId);
        if (!threadOnPage) {
          el.threadId = null;
          el.threadOrder = null;
        }
      }
    });

    const newPage: MagPageData = {
      ...structuredClone(sourcePage),
      id: newPageId,
      elements: newElements,
    };

    // Insert after source page and renumber
    const insertAfter = pageNumber;
    const pages = state.pages.map(p =>
      p.pageNumber > insertAfter ? { ...p, pageNumber: p.pageNumber + 1 } : p
    );
    const newPageNumber = insertAfter + 1;
    newPage.pageNumber = newPageNumber;
    newElements.forEach(el => { el.pageNumber = newPageNumber; });

    const insertIdx = pages.findIndex(p => p.pageNumber === newPageNumber);
    if (insertIdx === -1) pages.push(newPage);
    else pages.splice(insertIdx, 0, newPage);

    set({ pages, currentPageNumber: newPageNumber, selectedIds: [], isDirty: true });
  },

  reorderPages(fromIndex, toIndex) {
    if (fromIndex === toIndex) return;
    const state = get();
    get().pushSnapshot();

    const sorted = [...state.pages].sort((a, b) => a.pageNumber - b.pageNumber);
    const [moved] = sorted.splice(fromIndex, 1);
    sorted.splice(toIndex, 0, moved);

    // Renumber all pages
    const renumbered = sorted.map((p, i) => ({
      ...p,
      pageNumber: i + 1,
      elements: p.elements.map(el => ({ ...el, pageNumber: i + 1 })),
    }));

    set({ pages: renumbered, isDirty: true });
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

    get().pushDebugLog('frame:add', 'store', { type: elementType, x, y, width, height, id: newElement.id });
    return newElement.id;
  },

  updateElement(id, updates) {
    const state = get();
    const page = getCurrentPage(state);
    const oldEl = page ? findElementById(page.elements, id) : undefined;
    if (!oldEl) {
      get().pushDebugLog('frame:update', 'store', { id, error: 'element not found on current page' }, 'error');
    }
    const changedPaths: Record<string, { old: any; new: any }> = {};
    if (oldEl) {
      for (const k of Object.keys(updates)) {
        const oldVal = (oldEl as any)[k];
        const newVal = (updates as any)[k];
        if (oldVal !== newVal) changedPaths[k] = { old: oldVal, new: newVal };
      }
    }
    set((s) => ({
      pages: updateCurrentPageElements(s.pages, s.currentPageNumber, (els) =>
        updateElementInList(els, id, updates),
      ),
      isDirty: true,
    }));
    get().pushDebugLog('frame:update', 'store', {
      id, type: oldEl?.type, keys: Object.keys(updates), changedPaths,
    });
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
    get().pushDebugLog('frame:delete', 'store', { ids });
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

  moveElementToPage(elementId, fromPage, toPage, newX, newY) {
    if (fromPage === toPage) return;
    const state = get();
    get().pushSnapshot();

    const srcPage = state.pages.find(p => p.pageNumber === fromPage);
    const dstPage = state.pages.find(p => p.pageNumber === toPage);
    if (!srcPage || !dstPage) return;

    const element = srcPage.elements.find(e => e.id === elementId);
    if (!element) return;

    const moved: MagElement = {
      ...structuredClone(element),
      pageNumber: toPage,
      x: (newX != null && Number.isFinite(newX)) ? newX : (Number.isFinite(element.x) ? element.x : 0),
      y: (newY != null && Number.isFinite(newY)) ? newY : (Number.isFinite(element.y) ? element.y : 0),
    };

    set({
      pages: state.pages.map(p => {
        if (p.pageNumber === fromPage) return { ...p, elements: p.elements.filter(e => e.id !== elementId) };
        if (p.pageNumber === toPage) return { ...p, elements: [...p.elements, moved] };
        return p;
      }),
      currentPageNumber: toPage,
      selectedIds: [elementId],
      isDirty: true,
    });
  },

  continueTextToNextPage(elementId) {
    const state = get();
    get().pushSnapshot();

    // Find the element across all pages
    let sourcePage: MagPageData | undefined;
    let sourceElement: MagElement | undefined;
    for (const p of state.pages) {
      const el = p.elements.find(e => e.id === elementId);
      if (el) { sourcePage = p; sourceElement = el; break; }
    }
    if (!sourcePage || !sourceElement) return;

    const sourceHtml = (sourceElement.data as any)?.content || '';
    if (!sourceHtml || sourceHtml.length < 10) return;

    // ─── Measure what fits in the source frame ───
    // Strategy: measure as SINGLE column (no CSS columns complications),
    // then the available height = frame.height * numberOfColumns
    const measure = document.createElement('div');
    const typo = sourceElement.typography;
    const data = sourceElement.data as Record<string, any>;
    const pageW = sourcePage.pageSize?.width || 595;
    const visibleW = Math.min(sourceElement.width, pageW - sourceElement.x);
    const cols = data.columnsInFrame || 1;
    const colGap = data.columnGap || 12;
    const inset = data.textInset || { top: 8, right: 8, bottom: 8, left: 8 };

    // Single column width = (visibleW - padding - gaps) / cols
    const padH = (inset.left || 0) + (inset.right || 0);
    const padV = (inset.top || 0) + (inset.bottom || 0);
    const singleColW = cols > 1
      ? (visibleW - padH - (cols - 1) * colGap) / cols
      : visibleW - padH;
    // Total available text height across all columns
    const availableHeight = (sourceElement.height - padV) * cols;

    measure.style.cssText = `position:fixed;top:-9999px;left:-9999px;visibility:hidden;
      width:${Math.max(50, singleColW)}px;height:auto;overflow:visible;
      font-family:${typo?.fontFamily || 'Inter'};font-size:${typo?.fontSize || 14}px;
      font-weight:${typo?.fontWeight || 400};line-height:${typo?.lineHeight || 1.5};`;
    document.body.appendChild(measure);

    // Parse blocks
    const parser = new DOMParser();
    const doc = parser.parseFromString('<body>' + sourceHtml + '</body>', 'text/html');
    let root: Element = doc.body;
    while (root.children.length === 1) {
      const tag = root.children[0].tagName;
      if (['P','H1','H2','H3','H4','BLOCKQUOTE','UL','OL','LI'].includes(tag)) break;
      root = root.children[0];
    }
    const allBlocks: HTMLElement[] = [];
    for (let i = 0; i < root.childNodes.length; i++) {
      if (root.childNodes[i].nodeType === 1) allBlocks.push(root.childNodes[i] as HTMLElement);
    }

    // Check if ALL content fits
    measure.innerHTML = sourceHtml;
    if (measure.scrollHeight <= availableHeight + 4) { measure.remove(); return; }

    // Binary search: single-column height <= availableHeight means it fits
    let fitCount = allBlocks.length;
    if (allBlocks.length >= 2) {
      let lo = 1, hi = allBlocks.length;
      fitCount = allBlocks.length;
      while (lo <= hi) {
        const mid = Math.floor((lo + hi) / 2);
        measure.innerHTML = allBlocks.slice(0, mid).map(b => b.outerHTML).join('');
        if (measure.scrollHeight <= availableHeight) {
          fitCount = mid;
          lo = mid + 1;
        } else {
          hi = mid - 1;
        }
      }
      fitCount = Math.max(1, Math.min(fitCount, allBlocks.length - 1));
    }
    measure.remove();

    const keepHtml = allBlocks.slice(0, fitCount).map(b => b.outerHTML).join('');
    const moveHtml = allBlocks.slice(fitCount).map(b => b.outerHTML).join('');
    if (!moveHtml) return;

    // ─── Find or create next page ───
    let pages = [...state.pages];
    const contentPages = pages.filter(p => !p.isMaster).sort((a, b) => a.pageNumber - b.pageNumber);
    const sourceIdx = contentPages.findIndex(p => p.pageNumber === sourcePage.pageNumber);
    let targetPage = sourceIdx >= 0 && sourceIdx < contentPages.length - 1 ? contentPages[sourceIdx + 1] : null;
    const nextPageNumber = targetPage ? targetPage.pageNumber : sourcePage.pageNumber + 1;

    if (!targetPage) {
      targetPage = {
        id: crypto.randomUUID(), pageNumber: nextPageNumber,
        pageSize: { ...sourcePage.pageSize }, margins: { ...sourcePage.margins },
        bleed: { ...sourcePage.bleed }, columns: { ...sourcePage.columns },
        baselineGrid: { ...sourcePage.baselineGrid },
        isMaster: false, masterPageId: sourcePage.masterPageId,
        spreadWith: null, backgroundColor: sourcePage.backgroundColor,
        backgroundAssetId: null, elements: [],
      };
      pages = pages.map(p => p.pageNumber >= nextPageNumber ? { ...p, pageNumber: p.pageNumber + 1 } : p);
      const insertIdx = pages.findIndex(p => p.pageNumber > nextPageNumber);
      if (insertIdx === -1) pages.push(targetPage);
      else pages.splice(insertIdx, 0, targetPage);
    }

    // ─── Calculate continuation position avoiding ALL images on target page ───
    const mg = targetPage.margins || { top: 36, right: 36, bottom: 36, left: 36 };
    let contY = mg.top || 36;
    const tPageW = targetPage.pageSize?.width || 595;
    const tPageH = targetPage.pageSize?.height || 842;
    const contX = mg.left || 36;
    const contWidth = tPageW - (mg.left || 36) - (mg.right || 36);

    // Avoid ALL image frames on target page (not just fixed)
    const IMAGE_TYPES = ['image_frame', 'circular_image', 'polygon_image', 'fullbleed_image', 'gallery_frame', 'background_image'];
    const imagesOnTarget = targetPage.elements.filter(e => IMAGE_TYPES.includes(e.type) && e.visible);
    // Also check spread images from previous page
    const prevPage = contentPages[contentPages.findIndex(p => p.pageNumber === nextPageNumber) - 1];
    if (prevPage) {
      imagesOnTarget.push(...prevPage.elements.filter(e => IMAGE_TYPES.includes(e.type) && e.spanMode === 'spread' && e.visible));
    }

    for (const img of imagesOnTarget) {
      const imgBottom = img.y + img.height + 8;
      if (contY < imgBottom && contX < img.x + img.width && contX + contWidth > img.x) {
        contY = imgBottom;
      }
    }
    const contHeight = Math.max(100, tPageH - contY - (mg.bottom || 36));

    // ─── Create continuation and update source ───
    const threadId = sourceElement.threadId || crypto.randomUUID();
    const continuationId = crypto.randomUUID();
    const maxZ = Math.max(0, ...targetPage.elements.map(e => e.zIndex));

    const continuation: MagElement = {
      ...structuredClone(sourceElement),
      id: continuationId, pageNumber: nextPageNumber,
      x: contX, y: contY, width: contWidth, height: contHeight,
      threadId, threadOrder: (sourceElement.threadOrder ?? 0) + 1,
      zIndex: maxZ + 1,
      data: { ...sourceElement.data, content: moveHtml },
    };

    // Update source frame: keep only what fits
    pages = pages.map(p => ({
      ...p,
      elements: p.elements.map(e =>
        e.id === elementId ? { ...e, threadId, threadOrder: e.threadOrder ?? 0, data: { ...e.data, content: keepHtml } } : e
      ),
    }));

    // Add continuation to target page
    pages = pages.map(p =>
      p.pageNumber === nextPageNumber ? { ...p, elements: [...p.elements, continuation] } : p
    );

    set({ pages, currentPageNumber: nextPageNumber, selectedIds: [continuationId], isDirty: true });
  },

  // ─── Auto-flow text (run before save) ───

  autoFlowText() {
    const state = get();
    let pages = [...state.pages];
    const TEXT_TYPES = ['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'];
    let changed = false;

    // Measure which text frames overflow using a hidden div
    const measure = document.createElement('div');
    measure.style.cssText = 'position:fixed;top:-9999px;left:-9999px;visibility:hidden;';
    document.body.appendChild(measure);

    try {
      // Process each content page
      const contentPages = pages.filter(p => !p.isMaster).sort((a, b) => a.pageNumber - b.pageNumber);

      for (const page of contentPages) {
        const textFrames = page.elements.filter(e => TEXT_TYPES.includes(e.type) && e.visible);

        for (const frame of textFrames) {
          const data = frame.data as Record<string, any>;
          const html = data?.content || '';
          if (!html || html.length < 10) continue;

          // Skip all threaded frames — Pour manages those manually
          if (frame.threadId) continue;

          // Measure using single-column approach (same as Pour)
          const typo = frame.typography;
          const pageW = page.pageSize?.width || 595;
          const visibleW = Math.min(frame.width, pageW - frame.x);
          const cols = data.columnsInFrame || 1;
          const colGap = data.columnGap || 12;
          const inset = data.textInset || { top: 8, right: 8, bottom: 8, left: 8 };
          const padH = (inset.left || 0) + (inset.right || 0);
          const padV = (inset.top || 0) + (inset.bottom || 0);
          const singleColW = cols > 1 ? (visibleW - padH - (cols - 1) * colGap) / cols : visibleW - padH;
          const availH = (frame.height - padV) * cols;

          measure.style.cssText = `position:fixed;top:-9999px;left:-9999px;visibility:hidden;
            width:${Math.max(50, singleColW)}px;height:auto;overflow:visible;
            font-family:${typo?.fontFamily || 'Inter'};font-size:${(typo?.fontSize || 14)}px;
            font-weight:${typo?.fontWeight || 400};line-height:${typo?.lineHeight || 1.5};`;
          measure.innerHTML = html;
          const overflows = measure.scrollHeight > availH + 4;
          if (!overflows) continue;

          // Parse blocks
          const parser = new DOMParser();
          const doc = parser.parseFromString('<body>' + html + '</body>', 'text/html');
          let root: Element = doc.body;
          while (root.children.length === 1) {
            const child = root.children[0];
            const tag = child.tagName;
            if (tag === 'P' || tag === 'H1' || tag === 'H2' || tag === 'H3' || tag === 'BLOCKQUOTE' || tag === 'UL' || tag === 'OL') break;
            root = child;
          }
          const allBlocks: Element[] = [];
          for (let i = 0; i < root.childNodes.length; i++) {
            if (root.childNodes[i].nodeType === 1) allBlocks.push(root.childNodes[i] as Element);
          }
          if (allBlocks.length < 2) continue;

          // Binary search: single-column height <= availH means it fits
          let lo = 1, hi = allBlocks.length - 1, fitCount = 1;
          while (lo <= hi) {
            const mid = Math.floor((lo + hi) / 2);
            measure.innerHTML = allBlocks.slice(0, mid).map(b => (b as HTMLElement).outerHTML).join('');
            if (measure.scrollHeight <= availH) {
              fitCount = mid;
              lo = mid + 1;
            } else {
              hi = mid - 1;
            }
          }

          const keepHtml = allBlocks.slice(0, fitCount).map(b => (b as HTMLElement).outerHTML).join('');
          const moveHtml = allBlocks.slice(fitCount).map(b => (b as HTMLElement).outerHTML).join('');
          if (!moveHtml) continue;

          // Find or create next content page
          const cpIdx = contentPages.findIndex(p => p.pageNumber === page.pageNumber);
          let targetPage = cpIdx < contentPages.length - 1 ? contentPages[cpIdx + 1] : null;
          const nextPageNum = targetPage ? targetPage.pageNumber : page.pageNumber + 1;

          if (!targetPage) {
            targetPage = {
              id: crypto.randomUUID(),
              pageNumber: nextPageNum,
              pageSize: { ...page.pageSize },
              margins: { ...page.margins },
              bleed: { ...page.bleed },
              columns: { ...page.columns },
              baselineGrid: { ...page.baselineGrid },
              isMaster: false,
              masterPageId: page.masterPageId,
              spreadWith: null,
              backgroundColor: page.backgroundColor,
              backgroundAssetId: null,
              elements: [],
            };
            pages.push(targetPage);
            contentPages.push(targetPage);
          }

          // Create continuation frame — avoid ALL images on target page
          const margins = targetPage.margins || { top: 36, right: 36, bottom: 36, left: 36 };
          const tPageW = targetPage.pageSize?.width || 595;
          const tPageH = targetPage.pageSize?.height || 842;
          const contW = tPageW - (margins.left || 36) - (margins.right || 36);
          let contStartY = margins.top || 36;
          const IMG_TYPES = ['image_frame', 'circular_image', 'polygon_image', 'fullbleed_image', 'gallery_frame', 'background_image'];
          const imgsOnTarget = targetPage.elements.filter(e => IMG_TYPES.includes(e.type) && e.visible);
          const prevCp = contentPages[contentPages.findIndex(p => p.pageNumber === nextPageNum) - 1];
          if (prevCp) imgsOnTarget.push(...prevCp.elements.filter(e => IMG_TYPES.includes(e.type) && e.spanMode === 'spread' && e.visible));
          for (const fx of imgsOnTarget) {
            const fxBottom = fx.y + fx.height + 8;
            if (contStartY < fxBottom) contStartY = fxBottom;
          }
          const contH = Math.max(100, tPageH - contStartY - (margins.bottom || 36));
          const threadId = frame.threadId || crypto.randomUUID();
          const contId = crypto.randomUUID();

          const continuation: MagElement = {
            ...structuredClone(frame),
            id: contId,
            pageNumber: nextPageNum,
            x: margins.left || 36,
            y: contStartY,
            width: contW,
            height: contH,
            threadId,
            threadOrder: (frame.threadOrder ?? 0) + 1,
            zIndex: 1,
            data: { ...frame.data, content: moveHtml },
          };

          // Update source frame
          pages = pages.map(p => ({
            ...p,
            elements: p.elements.map(e => {
              if (e.id === frame.id) {
                return { ...e, threadId, threadOrder: e.threadOrder ?? 0, data: { ...e.data, content: keepHtml } };
              }
              return e;
            }),
          }));

          // Add continuation to target page
          pages = pages.map(p => {
            if (p.pageNumber === nextPageNum) {
              return { ...p, elements: [...p.elements, continuation] };
            }
            return p;
          });

          changed = true;
        }
      }
    } finally {
      measure.remove();
    }

    if (changed) {
      set({ pages, isDirty: true });
    }
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
    get().pushDebugLog('undo', 'store', { pagesRestored: pages.length });
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
    get().pushDebugLog('redo', 'store', { pagesRestored: pages.length });
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

  // ─── Issue Settings ───

  setIssueSettings(settings) {
    const old = get().issueSettings;
    get().pushSnapshot();
    set(state => ({
      issueSettings: { ...state.issueSettings, ...settings },
      isDirty: true,
    }));
    get().pushDebugLog('settings:change', 'store', { changed: Object.keys(settings), old, new: { ...old, ...settings } });
  },

  // ─── Styles ───

  setStyles(styles) {
    set({ styles });
  },

  // ─── Master Pages ───

  getMasterPages() {
    return get().pages.filter(p => p.isMaster);
  },

  addMasterPage(name) {
    const state = get();
    get().pushSnapshot();

    const masterPage: MagPageData = {
      id: crypto.randomUUID(),
      pageNumber: -(state.pages.filter(p => p.isMaster).length + 1), // Negative numbers for masters
      pageSize: state.pages[0]?.pageSize || { width: 595, height: 842 },
      margins: state.pages[0]?.margins || { top: 36, right: 36, bottom: 36, left: 36 },
      bleed: state.pages[0]?.bleed || { top: 9, right: 9, bottom: 9, left: 9 },
      columns: { count: 1, gutter: 12 },
      baselineGrid: { increment: 14, start: 36 },
      isMaster: true,
      masterPageId: null,
      spreadWith: null,
      backgroundColor: '#ffffff',
      backgroundAssetId: null,
      elements: [],
    };
    // Store name in a safe way — use the id prefix approach
    (masterPage as any)._masterName = name;

    set({ pages: [...state.pages, masterPage], isDirty: true });
  },

  assignMaster(pageNumber, masterPageId) {
    get().pushSnapshot();
    set(state => ({
      pages: state.pages.map(p =>
        p.pageNumber === pageNumber ? { ...p, masterPageId } : p
      ),
      isDirty: true,
    }));
  },

  assignMasterToAll(masterPageId) {
    get().pushSnapshot();
    set(state => ({
      pages: state.pages.map(p =>
        p.isMaster ? p : { ...p, masterPageId }
      ),
      isDirty: true,
    }));
  },

  setEditingMaster(masterPageId) {
    get().pushSnapshot();
    if (masterPageId) {
      const master = get().pages.find(p => p.id === masterPageId && p.isMaster);
      if (master) {
        set({ editingMasterId: masterPageId, currentPageNumber: master.pageNumber, selectedIds: [] });
      }
    } else {
      const firstContent = get().pages.find(p => !p.isMaster);
      set({ editingMasterId: null, currentPageNumber: firstContent?.pageNumber || 1, selectedIds: [] });
    }
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
