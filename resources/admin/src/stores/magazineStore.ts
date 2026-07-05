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
import { resolveMasterElements } from '@/lib/magazineFormat';
import {
  runDocumentFlow,
  collectThreads,
  deriveStory,
  FLOW_TEXT_TYPES,
} from '@/engine/flow/storeFlow';

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
  /** per-source snapping toggles (W2-2) — all honored only when snapEnabled */
  snapPrefs: { grid: boolean; guides: boolean; margins: boolean; objects: boolean; baseline: boolean };
  /** preview mode (W2-8): hide ALL editor chrome — see it as a reader */
  previewMode: boolean;
  isDirty: boolean;
  isSaving: boolean;
  styles: MagStyleDefinition[];
  editingMasterId: string | null;
  issueSettings: IssueSettings;
  debugLog: DebugLogEntry[];
  /** canonical story text per threadId (lazily derived from frame slices) */
  stories: Record<string, string>;
  /** threads whose last frame currently oversets (chain-aware badge) */
  oversetThreads: Record<string, boolean>;
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
  /** duplicate selection N times with a fixed offset (W2-6 step-and-repeat) */
  stepAndRepeat: (ids: string[], count: number, dx: number, dy: number) => void;
  /** group/ungroup (W2 group): children keep ABSOLUTE coords; the group is a
   *  bounding-box container element; moving/resizing it translates/scales them */
  groupElements: (ids: string[]) => void;
  ungroupElements: (groupId: string) => void;
  /** footnotes ([pro]): append a numbered note to the page's footnote frame
   *  (created at the page bottom with jump wrap on first use); returns n */
  insertFootnote: (pageNumber: number, noteHtml: string) => number;
  moveElementToPage: (elementId: string, fromPage: number, toPage: number, newX?: number, newY?: number) => void;
  continueTextToNextPage: (elementId: string) => void;

  // Selection
  selectElement: (id: string, addToSelection?: boolean) => void;
  clearSelection: () => void;
  selectAll: () => void;

  // Layer order
  bringToFront: (ids: string[]) => void;
  sendToBack: (ids: string[]) => void;
  bringForward: (id: string) => void;
  sendBackward: (id: string) => void;

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
  toggleSnapPref: (key: 'grid' | 'guides' | 'margins' | 'objects' | 'baseline') => void;
  togglePreview: () => void;
  /** ruler guides (W2-1): page-local positions, persisted via page metadata */
  addGuide: (pageNumber: number, axis: 'v' | 'h', pos: number) => void;
  moveGuide: (pageNumber: number, axis: 'v' | 'h', index: number, pos: number) => void;
  removeGuide: (pageNumber: number, axis: 'v' | 'h', index: number) => void;
  clearGuides: (pageNumber: number) => void;
  setViewMode: (mode: ViewMode) => void;
  setGridColumns: (cols: number) => void;

  // Text flow — the ONE reflow path (engine-backed, Session C)
  runFlow: (opts?: { paginate?: boolean; markDirty?: boolean }) => void;
  requestFlow: () => void;
  /** legacy name kept for save-path compatibility; delegates to runFlow */
  autoFlowText: () => void;

  // Issue settings
  setIssueSettings: (settings: Partial<IssueSettings>) => void;

  // Master pages
  getMasterPages: () => MagPageData[];
  addMasterPage: (name: string) => void;
  assignMaster: (pageNumber: number, masterPageId: string | null) => void;
  assignMasterToAll: (masterPageId: string | null) => void;
  /** copy master elements onto the page as editable elements + unlink (override) */
  detachMaster: (pageNumber: number) => void;
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

/** debounce handle for geometry-triggered reflow (one reflow per gesture) */
let flowDebounceTimer: ReturnType<typeof setTimeout> | null = null;

/** gesture-scoped undo for updateElement: one snapshot per drag/resize/edit
 *  gesture, not per pointermove (audit W0-5 — the highest-frequency mutation
 *  path previously bypassed history entirely) */
let lastUpdateSnapAt = 0;
let lastUpdateSnapKey = '';
const UPDATE_GESTURE_MS = 600;

/** element keys whose change requires a reflow of the affected thread(s) */
const FLOW_GEOMETRY_KEYS = ['x', 'y', 'width', 'height'] as const;
const FLOW_DATA_KEYS = ['columnsInFrame', 'columnGap', 'textInset', 'verticalAlign'] as const;

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
    if (el.children?.length > 0) {
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
    if (el.children?.length > 0) {
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
      el.children?.length > 0
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
    text_path: { text: 'Text on a path', preset: 'arc-up', startOffset: 0 },
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
  snapPrefs: { grid: true, guides: true, margins: true, objects: true, baseline: false },
  previewMode: false,
  isDirty: false,
  isSaving: false,
  styles: [],
  editingMasterId: null,
  issueSettings: { layoutMode: 'single', coverMode: 'standalone', readingDirection: 'ltr' },
  debugLog: [],
  stories: {},
  oversetThreads: {},

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
      stories: {},
      oversetThreads: {},
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
        : { width: 595, height: 842 },
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

    // Masters v2: a master text frame flagged _primaryFlow instantiates as a
    // REAL editable body frame on every new page created from that master
    if (newPage.masterPageId) {
      const master = state.pages.find((p) => p.isMaster && p.id === newPage.masterPageId);
      const primary = resolveMasterElements(master?.id, get().pages as any).find((e: MagElement) => (e.data as any)?._primaryFlow);
      if (primary) {
        newPage.elements.push({
          ...structuredClone(primary),
          id: crypto.randomUUID(),
          pageNumber: newPageNumber,
          onMaster: false,
          threadId: null,
          threadOrder: null,
          data: { ...primary.data, content: '', _primaryFlow: undefined },
        });
      }
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
    // margins / page size / columns affect continuation placement
    if ('pageSize' in updates || 'margins' in updates || 'columns' in updates) {
      get().requestFlow();
    }
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
    const isDebug = typeof localStorage !== 'undefined' && localStorage.getItem('dtp-debug') === '1';
    // Find the element WHEREVER it lives (audit defect #9: the old
    // current-page-only update silently dropped cross-page thread edits)
    let oldEl: MagElement | undefined;
    for (const p of get().pages) {
      oldEl = findElementById(p.elements, id);
      if (oldEl) break;
    }
    // Only compute expensive diff when debug is on
    let changedPaths: Record<string, { old: any; new: any }> | undefined;
    if (isDebug) {
      if (!oldEl) {
        get().pushDebugLog('frame:update', 'store', { id, error: 'element not found' }, 'error');
      } else {
        changedPaths = {};
        for (const k of Object.keys(updates)) {
          const oldVal = (oldEl as any)[k];
          const newVal = (updates as any)[k];
          if (oldVal !== newVal) changedPaths[k] = { old: oldVal, new: newVal };
        }
      }
    }

    // ── undo: one snapshot per gesture (new element / new key set / pause)
    if (oldEl) {
      const gestureKey = id + '|' + Object.keys(updates).sort().join(',');
      const now = Date.now();
      if (gestureKey !== lastUpdateSnapKey || now - lastUpdateSnapAt > UPDATE_GESTURE_MS) {
        get().pushSnapshot();
      }
      lastUpdateSnapKey = gestureKey;
      lastUpdateSnapAt = now;
    }

    // ── flow triggers: content edits invalidate the thread story; geometry,
    //    column, typography and wrap changes schedule a reflow (debounced)
    let invalidateStoryId: string | null = null;
    let triggerFlow = false;
    if (oldEl) {
      const isText = FLOW_TEXT_TYPES.has(oldEl.type);
      const dataUpd = updates.data as Record<string, any> | undefined;
      if (isText && dataUpd && 'content' in dataUpd && dataUpd.content !== (oldEl.data as any)?.content) {
        if (oldEl.threadId) invalidateStoryId = oldEl.threadId;
        triggerFlow = true;
      }
      if (isText && dataUpd && FLOW_DATA_KEYS.some((k) => k in dataUpd)) triggerFlow = true;
      if (isText && 'typography' in updates) triggerFlow = true;
      const geometryChanged = FLOW_GEOMETRY_KEYS.some(
        (k) => k in updates && (updates as any)[k] !== (oldEl as any)[k],
      );
      if (geometryChanged && (isText || oldEl.textWrap?.type !== 'none')) triggerFlow = true;
      if ('textWrap' in updates) triggerFlow = true;
    }

    // groups: moving translates children; resizing scales them (children
    // keep ABSOLUTE coordinates so publish stays flat and correct)
    let groupPatch: Partial<MagElement> | null = null;
    if (oldEl && (oldEl.type === 'group' || oldEl.type === 'clipping_group') && oldEl.children.length > 0) {
      const nx = (updates.x ?? oldEl.x) as number;
      const ny = (updates.y ?? oldEl.y) as number;
      const nw = (updates.width ?? oldEl.width) as number;
      const nh = (updates.height ?? oldEl.height) as number;
      if (nx !== oldEl.x || ny !== oldEl.y || nw !== oldEl.width || nh !== oldEl.height) {
        const sx = oldEl.width > 0 ? nw / oldEl.width : 1;
        const sy = oldEl.height > 0 ? nh / oldEl.height : 1;
        groupPatch = {
          children: oldEl.children.map((c) => ({
            ...c,
            x: nx + (c.x - oldEl.x) * sx,
            y: ny + (c.y - oldEl.y) * sy,
            width: c.width * sx,
            height: c.height * sy,
          })),
        };
      }
    }

    set((s) => ({
      pages: s.pages.map((p) =>
        findElementById(p.elements, id)
          ? { ...p, elements: updateElementInList(p.elements, id, groupPatch ? { ...updates, ...groupPatch } : updates) }
          : p,
      ),
      ...(invalidateStoryId
        ? { stories: Object.fromEntries(Object.entries(s.stories).filter(([k]) => k !== invalidateStoryId)) }
        : {}),
      isDirty: true,
    }));
    get().pushDebugLog('frame:update', 'store', {
      id, keys: Object.keys(updates), ...(changedPaths ? { changedPaths } : {}),
    });
    if (triggerFlow) get().requestFlow();
  },

  deleteElements(ids) {
    if (ids.length === 0) return;
    get().pushSnapshot();
    const idSet = new Set(ids);
    // Preserve thread stories BEFORE deleting frames: the full story must
    // redistribute into the remaining frames (no text lost with the frame)
    {
      const state = get();
      const threads = collectThreads(state.pages);
      const stories = { ...state.stories };
      let touched = false;
      for (const [tid, refs] of threads) {
        if (refs.some((r) => idSet.has(r.elementId)) && !stories[tid]) {
          stories[tid] = deriveStory(state.pages, refs);
          touched = true;
        }
      }
      if (touched) set({ stories });
    }
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
    get().requestFlow(); // remaining thread frames absorb the deleted frame's text
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

  groupElements(ids) {
    if (ids.length < 2) return;
    const state = get();
    const page = getCurrentPage(state);
    if (!page) return;
    const members = page.elements.filter((e) => ids.includes(e.id) && !e.locked);
    if (members.length < 2) return;
    get().pushSnapshot();
    const x0 = Math.min(...members.map((e) => e.x));
    const y0 = Math.min(...members.map((e) => e.y));
    const x1 = Math.max(...members.map((e) => e.x + e.width));
    const y1 = Math.max(...members.map((e) => e.y + e.height));
    const groupId = crypto.randomUUID();
    const group: MagElement = {
      ...makeDefaultElement('group', x0, y0, x1 - x0, y1 - y0, state.currentPageNumber,
        Math.max(...members.map((e) => e.zIndex))),
      id: groupId,
      name: 'Group',
      children: members.map((m) => ({ ...structuredClone(m), parentId: groupId })),
    };
    set((s2) => ({
      pages: updateCurrentPageElements(s2.pages, s2.currentPageNumber, (els) => [
        ...els.filter((e) => !ids.includes(e.id)),
        group,
      ]),
      selectedIds: [groupId],
      isDirty: true,
    }));
    get().pushDebugLog('group:create', 'store', { groupId, members: members.length });
  },

  ungroupElements(groupId) {
    const state = get();
    const page = getCurrentPage(state);
    const group = page?.elements.find((e) => e.id === groupId && (e.type === 'group' || e.type === 'clipping_group'));
    if (!page || !group || group.children.length === 0) return;
    get().pushSnapshot();
    const freed = group.children.map((c) => ({ ...structuredClone(c), parentId: null }));
    set((s2) => ({
      pages: updateCurrentPageElements(s2.pages, s2.currentPageNumber, (els) => [
        ...els.filter((e) => e.id !== groupId),
        ...freed,
      ]),
      selectedIds: freed.map((c) => c.id),
      isDirty: true,
    }));
    get().pushDebugLog('group:ungroup', 'store', { groupId, freed: freed.length });
  },

  insertFootnote(pageNumber, noteHtml) {
    const state = get();
    const page = state.pages.find((p) => p.pageNumber === pageNumber && !p.isMaster);
    if (!page) return 0;
    get().pushSnapshot();
    let fn = page.elements.find((e) => e.type === 'footnote_frame');
    let created = false;
    if (!fn) {
      const m = page.margins || { top: 36, right: 36, bottom: 36, left: 36 };
      const w = (page.pageSize?.width || 595) - m.left - m.right;
      const h = 84;
      const y = (page.pageSize?.height || 842) - m.bottom - h;
      fn = {
        ...makeDefaultElement('footnote_frame', m.left, y, w, h, pageNumber,
          Math.max(0, ...page.elements.map((e) => e.zIndex)) + 1),
        name: 'Footnotes',
      };
      fn.data = { ...fn.data, content: '<hr>' };
      fn.typography = { ...(fn.typography as any), fontSize: 8.5, lineHeight: 1.45 };
      // jump wrap: body text automatically flows CLEAR of the note block
      fn.textWrap = { type: 'jump', offset: { top: 10, right: 0, bottom: 0, left: 0 }, side: 'both', customPath: null, invert: false };
      created = true;
    }
    const existing = (String((fn.data as any)?.content || '').match(/class="fn"/g) || []).length;
    const n = existing + 1;
    const note = `<p class="fn"><sup>${n}</sup> ${noteHtml}</p>`;
    const content = String((fn.data as any)?.content || '') + note;
    set((s2) => ({
      pages: s2.pages.map((p) =>
        p.pageNumber !== pageNumber || p.isMaster
          ? p
          : {
              ...p,
              elements: created
                ? [...p.elements, { ...fn!, data: { ...fn!.data, content } }]
                : p.elements.map((e) => (e.id === fn!.id ? { ...e, data: { ...e.data, content } } : e)),
            },
      ),
      isDirty: true,
    }));
    get().requestFlow();
    get().pushDebugLog('footnote:insert', 'store', { pageNumber, n, created });
    return n;
  },

  stepAndRepeat(ids, count, dx, dy) {
    if (ids.length === 0 || count < 1) return;
    const state = get();
    const page = getCurrentPage(state);
    if (!page) return;
    get().pushSnapshot();
    const originals = ids
      .map((id) => findElementById(page.elements, id))
      .filter(Boolean) as MagElement[];
    const maxZ = Math.max(0, ...page.elements.map((e) => e.zIndex));
    const copies: MagElement[] = [];
    const n = Math.min(50, Math.floor(count));
    for (let step = 1; step <= n; step++) {
      originals.forEach((orig, i) => {
        copies.push({
          ...structuredClone(orig),
          id: crypto.randomUUID(),
          x: orig.x + dx * step,
          y: orig.y + dy * step,
          zIndex: maxZ + (step - 1) * originals.length + i + 1,
          threadId: null,
          threadOrder: null,
        });
      });
    }
    set((s2) => ({
      pages: updateCurrentPageElements(s2.pages, s2.currentPageNumber, (els) => [...els, ...copies]),
      selectedIds: copies.map((c) => c.id),
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
    if (element.threadId || FLOW_TEXT_TYPES.has(element.type) || element.textWrap?.type !== 'none') {
      get().requestFlow();
    }
  },

  continueTextToNextPage(elementId) {
    // "Pour": make this frame a story and let the engine paginate it.
    // (Replaces the legacy 160-line DOM-measuring splitter — audit Part 1.)
    const state = get();
    let sourceEl: MagElement | undefined;
    for (const p of state.pages) {
      sourceEl = p.elements.find((e) => e.id === elementId);
      if (sourceEl) break;
    }
    if (!sourceEl || !FLOW_TEXT_TYPES.has(sourceEl.type)) return;

    if (!sourceEl.threadId) {
      const tid = crypto.randomUUID();
      set((s) => ({
        pages: s.pages.map((p) =>
          p.elements.some((e) => e.id === elementId)
            ? { ...p, elements: p.elements.map((e) => (e.id === elementId ? { ...e, threadId: tid, threadOrder: 0 } : e)) }
            : p,
        ),
        isDirty: true,
      }));
    }
    get().runFlow({ paginate: true });
    get().pushDebugLog('flow:pour', 'store', { elementId });
  },

  // ─── Text flow — the ONE reflow path (engine-backed, Session C) ───

  runFlow(opts) {
    const paginate = opts?.paginate ?? true;
    const markDirty = opts?.markDirty ?? true;
    const state = get();
    if (state.pages.length === 0) return;
    try {
      const result = runDocumentFlow(state.pages, state.stories, { paginate });
      if (result.structuralChange) get().pushSnapshot();
      if (result.changed) {
        set({
          pages: result.pages,
          stories: result.stories,
          oversetThreads: result.oversetThreads,
          ...(markDirty ? { isDirty: true } : {}),
        });
      } else {
        set({ stories: result.stories, oversetThreads: result.oversetThreads });
      }
      get().pushDebugLog('flow:run', 'engine', {
        paginate,
        changed: result.changed,
        structural: result.structuralChange,
        oversetThreads: Object.keys(result.oversetThreads).filter((k) => result.oversetThreads[k]),
      });
    } catch (err) {
      // The engine must never take the editor down — log and keep last state
      // eslint-disable-next-line no-console
      console.error('[flow] engine error', err);
      get().pushDebugLog('flow:error', 'engine', { error: String(err) }, 'error');
    }
  },

  requestFlow() {
    if (flowDebounceTimer) clearTimeout(flowDebounceTimer);
    flowDebounceTimer = setTimeout(() => {
      flowDebounceTimer = null;
      get().runFlow({ paginate: true });
    }, 120);
  },

  /** legacy save-path hook — now just the engine (name kept for callers) */
  autoFlowText() {
    if (flowDebounceTimer) {
      clearTimeout(flowDebounceTimer);
      flowDebounceTimer = null;
    }
    get().runFlow({ paginate: true });
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

  bringForward(id) {
    get().pushSnapshot();
    set((state) => ({
      pages: updateCurrentPageElements(state.pages, state.currentPageNumber, (els) => {
        const sorted = [...els].sort((a, b) => a.zIndex - b.zIndex);
        const i = sorted.findIndex((e) => e.id === id);
        if (i < 0 || i === sorted.length - 1) return els;
        const a = sorted[i].zIndex;
        const b = sorted[i + 1].zIndex;
        return els.map((e) =>
          e.id === sorted[i].id ? { ...e, zIndex: b === a ? a + 1 : b }
          : e.id === sorted[i + 1].id ? { ...e, zIndex: a }
          : e);
      }),
      isDirty: true,
    }));
  },

  sendBackward(id) {
    get().pushSnapshot();
    set((state) => ({
      pages: updateCurrentPageElements(state.pages, state.currentPageNumber, (els) => {
        const sorted = [...els].sort((a, b) => a.zIndex - b.zIndex);
        const i = sorted.findIndex((e) => e.id === id);
        if (i <= 0) return els;
        const a = sorted[i].zIndex;
        const b = sorted[i - 1].zIndex;
        return els.map((e) =>
          e.id === sorted[i].id ? { ...e, zIndex: b === a ? a - 1 : b }
          : e.id === sorted[i - 1].id ? { ...e, zIndex: a }
          : e);
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
      // snapshot covers pages AND styles (audit W0-5: styles were outside
      // history, so style mutations were unrecoverable)
      const snapshot = JSON.stringify({ p: state.pages, s: state.styles });
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

    const currentSnapshot = JSON.stringify({ p: state.pages, s: state.styles });
    const parsed = JSON.parse(state.undoStack[state.undoStack.length - 1]);
    const pages = (Array.isArray(parsed) ? parsed : parsed.p) as MagPageData[];
    const styles = (Array.isArray(parsed) ? state.styles : parsed.s) as MagStyleDefinition[];

    set({
      pages,
      styles,
      undoStack: state.undoStack.slice(0, -1),
      redoStack: [...state.redoStack, currentSnapshot],
      selectedIds: [],
      editingElementId: null,
      isDirty: true,
      stories: {}, // stories re-derive from the restored slices
      oversetThreads: {},
    });
    get().pushDebugLog('undo', 'store', { pagesRestored: pages.length });
  },

  redo() {
    const state = get();
    if (state.redoStack.length === 0) return;

    const currentSnapshot = JSON.stringify({ p: state.pages, s: state.styles });
    const parsed = JSON.parse(state.redoStack[state.redoStack.length - 1]);
    const pages = (Array.isArray(parsed) ? parsed : parsed.p) as MagPageData[];
    const styles = (Array.isArray(parsed) ? state.styles : parsed.s) as MagStyleDefinition[];

    set({
      pages,
      styles,
      undoStack: [...state.undoStack, currentSnapshot],
      redoStack: state.redoStack.slice(0, -1),
      selectedIds: [],
      editingElementId: null,
      isDirty: true,
      stories: {}, // stories re-derive from the restored slices
      oversetThreads: {},
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

  toggleSnapPref(key) {
    set((s2) => ({ snapPrefs: { ...s2.snapPrefs, [key]: !s2.snapPrefs[key] } }));
  },

  addGuide(pageNumber, axis, pos) {
    get().pushSnapshot();
    set((s2) => ({
      pages: s2.pages.map((p) => {
        if (p.pageNumber !== pageNumber || p.isMaster) return p;
        const g = (p as any)._guides || { v: [], h: [] };
        return { ...p, _guides: { ...g, [axis]: [...g[axis], Math.round(pos * 10) / 10] } } as any;
      }),
      isDirty: true,
    }));
  },

  moveGuide(pageNumber, axis, index, pos) {
    set((s2) => ({
      pages: s2.pages.map((p) => {
        if (p.pageNumber !== pageNumber || p.isMaster) return p;
        const g = (p as any)._guides || { v: [], h: [] };
        const arr = [...g[axis]];
        if (index < 0 || index >= arr.length) return p;
        arr[index] = Math.round(pos * 10) / 10;
        return { ...p, _guides: { ...g, [axis]: arr } } as any;
      }),
      isDirty: true,
    }));
  },

  removeGuide(pageNumber, axis, index) {
    get().pushSnapshot();
    set((s2) => ({
      pages: s2.pages.map((p) => {
        if (p.pageNumber !== pageNumber || p.isMaster) return p;
        const g = (p as any)._guides || { v: [], h: [] };
        return { ...p, _guides: { ...g, [axis]: g[axis].filter((_: number, i: number) => i !== index) } } as any;
      }),
      isDirty: true,
    }));
  },

  clearGuides(pageNumber) {
    get().pushSnapshot();
    set((s2) => ({
      pages: s2.pages.map((p) =>
        p.pageNumber === pageNumber && !p.isMaster ? ({ ...p, _guides: { v: [], h: [] } } as any) : p,
      ),
      isDirty: true,
    }));
  },

  togglePreview() {
    set((s) => ({ previewMode: !s.previewMode, selectedIds: [], editingElementId: null }));
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

  detachMaster(pageNumber) {
    const state = get();
    const page = state.pages.find((p) => p.pageNumber === pageNumber && !p.isMaster);
    const master = page?.masterPageId ? state.pages.find((p) => p.isMaster && p.id === page.masterPageId) : null;
    if (!page || !master) return;
    get().pushSnapshot();
    const maxZ = Math.max(0, ...page.elements.map((e) => e.zIndex));
    const copies = resolveMasterElements(master.id, state.pages as any).map((el: MagElement, i: number) => ({
      ...structuredClone(el),
      id: crypto.randomUUID(),
      pageNumber,
      onMaster: false,
      zIndex: maxZ + 1 + i,
      // resolve master page-number instances to this page's number
      data: el.type === 'page_number' ? { ...el.data, startAt: pageNumber } : structuredClone(el.data),
    }));
    set((s2) => ({
      pages: s2.pages.map((p) =>
        p.pageNumber === pageNumber && !p.isMaster
          ? { ...p, masterPageId: null, elements: [...p.elements, ...copies] }
          : p,
      ),
      isDirty: true,
    }));
    get().pushDebugLog('master:detach', 'store', { pageNumber, copied: copies.length });
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
    // navigation only — no snapshot (audit W0-5: history pollution)
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
    get().pushSnapshot();
    set((state) => ({ styles: [...state.styles, style], isDirty: true }));
  },

  updateStyle(id, updates) {
    get().pushSnapshot();
    set((state) => ({
      styles: state.styles.map((s) => (s.id === id ? { ...s, ...updates } : s)),
      isDirty: true,
    }));
  },

  deleteStyle(id) {
    get().pushSnapshot();
    set((state) => ({
      styles: state.styles.filter((s) => s.id !== id),
      isDirty: true,
    }));
  },
}));
