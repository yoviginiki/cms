/**
 * MAG-P13 — Shared MagazineDocument contract.
 *
 * Canonical document model consumed by:
 *  - DTP editor (MagazineCanvas + magazineStore)
 *  - DTP preview (dtp-preview.blade.php via DtpRenderService)
 *  - Flipbook viewer (magazine.blade.php via MagazineViewController)
 *  - Preflight service (DtpPreflightService)
 *  - Rollout/render health check (DtpRolloutService)
 *
 * All systems should produce or consume this shape (or adapt to it).
 */

// ═══════════════════════════════════════
// Core document
// ═══════════════════════════════════════

export interface MagazineDocument {
  version: 1;
  title: string;
  subtitle?: string;
  pageSize: { width: number; height: number };
  pages: MagazineDocPage[];
  metadata?: Record<string, unknown>;
}

export interface MagazineDocPage {
  id: string;
  index: number;
  name?: string;
  width: number;
  height: number;
  margins: { top: number; right: number; bottom: number; left: number };
  bleed?: { top: number; right: number; bottom: number; left: number };
  backgroundColor?: string;
  frames: MagazineDocFrame[];
}

// ═══════════════════════════════════════
// Frames
// ═══════════════════════════════════════

export type MagazineDocFrameType = 'text' | 'image' | 'shape' | 'quote' | 'pageNumber' | 'line' | 'decorative';

export interface MagazineDocFrame {
  id: string;
  type: MagazineDocFrameType;
  name?: string;
  x: number;
  y: number;
  width: number;
  height: number;
  rotation: number;
  zIndex: number;
  visible: boolean;
  locked: boolean;
  content: MagazineDocFrameContent;
}

// ═══════════════════════════════════════
// Frame content (by type)
// ═══════════════════════════════════════

export type MagazineDocFrameContent =
  | MagazineDocTextContent
  | MagazineDocImageContent
  | MagazineDocShapeContent
  | MagazineDocQuoteContent
  | MagazineDocPageNumberContent
  | MagazineDocEmptyContent;

export interface MagazineDocTextContent {
  type: 'text';
  html: string;
  overflow?: 'visible' | 'hidden' | 'threaded';
  columns?: number;
  columnGap?: number;
  linkedNextFrameId?: string | null;
  typography?: MagazineDocTypography;
}

export interface MagazineDocImageContent {
  type: 'image';
  src: string;
  alt?: string;
  caption?: string;
  fitMode: 'fill' | 'fit' | 'stretch' | 'original';
  focalPoint?: { x: number; y: number };
  opacity?: number;
}

export interface MagazineDocShapeContent {
  type: 'shape';
  fillColor?: string;
  strokeColor?: string;
  strokeWidth?: number;
  cornerRadius?: number;
}

export interface MagazineDocQuoteContent {
  type: 'quote';
  html: string;
  attribution?: string;
}

export interface MagazineDocPageNumberContent {
  type: 'pageNumber';
  format?: 'numeric' | 'roman' | 'alpha';
  prefix?: string;
  suffix?: string;
}

export interface MagazineDocEmptyContent {
  type: 'line' | 'decorative';
}

// ═══════════════════════════════════════
// Typography (shared subset)
// ═══════════════════════════════════════

export interface MagazineDocTypography {
  fontFamily?: string;
  fontSize?: number;
  fontWeight?: number;
  fontStyle?: 'normal' | 'italic';
  lineHeight?: number;
  textAlign?: 'left' | 'center' | 'right' | 'justify';
  textColor?: string;
}

// ═══════════════════════════════════════
// Normalization helpers
// ═══════════════════════════════════════

const DEFAULT_PAGE_SIZE = { width: 595, height: 842 };
const DEFAULT_MARGINS = { top: 36, right: 36, bottom: 36, left: 36 };

/** Safe UUID generator — falls back to Math.random if crypto.randomUUID unavailable. */
function safeUUID(): string {
  try {
    return crypto.randomUUID();
  } catch {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
      const r = (Math.random() * 16) | 0;
      return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
  }
}

/**
 * Create a safe default empty document.
 */
export function createDefaultMagazineDocument(title = 'Untitled'): MagazineDocument {
  return {
    version: 1,
    title,
    pageSize: { ...DEFAULT_PAGE_SIZE },
    pages: [{
      id: safeUUID(),
      index: 0,
      name: 'Page 1',
      width: DEFAULT_PAGE_SIZE.width,
      height: DEFAULT_PAGE_SIZE.height,
      margins: { ...DEFAULT_MARGINS },
      backgroundColor: '#ffffff',
      frames: [],
    }],
  };
}

/**
 * Normalize raw data into a safe MagazineDocument.
 * Fills missing fields with defaults. Never crashes on bad input.
 */
export function normalizeMagazineDocument(raw: unknown): MagazineDocument {
  if (!raw || typeof raw !== 'object') return createDefaultMagazineDocument();

  const doc = raw as Record<string, unknown>;
  const title = typeof doc.title === 'string' ? doc.title : 'Untitled';
  const rawPages = Array.isArray(doc.pages) ? doc.pages : [];

  const pages: MagazineDocPage[] = rawPages.map((p: any, idx: number) => normalizeDocPage(p, idx));

  if (pages.length === 0) {
    return createDefaultMagazineDocument(title);
  }

  return {
    version: 1,
    title,
    pageSize: typeof doc.pageSize === 'object' && doc.pageSize ? doc.pageSize as any : { ...DEFAULT_PAGE_SIZE },
    pages,
    metadata: typeof doc.metadata === 'object' ? doc.metadata as any : undefined,
  };
}

function normalizeDocPage(raw: any, idx: number): MagazineDocPage {
  return {
    id: raw?.id || safeUUID(),
    index: typeof raw?.index === 'number' ? raw.index : idx,
    name: raw?.name || `Page ${idx + 1}`,
    width: Math.max(1, Number(raw?.width) || DEFAULT_PAGE_SIZE.width),
    height: Math.max(1, Number(raw?.height) || DEFAULT_PAGE_SIZE.height),
    margins: raw?.margins || { ...DEFAULT_MARGINS },
    bleed: raw?.bleed,
    backgroundColor: raw?.backgroundColor || '#ffffff',
    frames: Array.isArray(raw?.frames) ? raw.frames.map(normalizeDocFrame) : [],
  };
}

function normalizeDocFrame(raw: any): MagazineDocFrame {
  const type: MagazineDocFrameType = ['text', 'image', 'shape', 'quote', 'pageNumber', 'line', 'decorative'].includes(raw?.type) ? raw.type : 'text';

  return {
    id: raw?.id || safeUUID(),
    type,
    name: raw?.name,
    x: Number(raw?.x) || 0,
    y: Number(raw?.y) || 0,
    width: Math.max(1, Number(raw?.width) || 200),
    height: Math.max(1, Number(raw?.height) || 100),
    rotation: Number(raw?.rotation) || 0,
    zIndex: Number(raw?.zIndex) || 0,
    visible: raw?.visible !== false,
    locked: raw?.locked === true,
    content: normalizeFrameContent(type, raw?.content),
  };
}

function normalizeFrameContent(type: MagazineDocFrameType, raw: any): MagazineDocFrameContent {
  if (!raw || typeof raw !== 'object') raw = {};

  switch (type) {
    case 'text':
      return { type: 'text', html: raw.html || raw.text || '', overflow: raw.overflow, columns: raw.columns, columnGap: raw.columnGap, linkedNextFrameId: raw.linkedNextFrameId || null, typography: raw.typography };
    case 'image':
      return { type: 'image', src: raw.src || '', alt: raw.alt || '', caption: raw.caption, fitMode: raw.fitMode || 'fill', focalPoint: raw.focalPoint || { x: 50, y: 50 }, opacity: raw.opacity ?? 100 };
    case 'shape':
      return { type: 'shape', fillColor: raw.fillColor, strokeColor: raw.strokeColor, strokeWidth: raw.strokeWidth, cornerRadius: raw.cornerRadius };
    case 'quote':
      return { type: 'quote', html: raw.html || raw.text || '', attribution: raw.attribution };
    case 'pageNumber':
      return { type: 'pageNumber', format: raw.format || 'numeric', prefix: raw.prefix || '', suffix: raw.suffix || '' };
    default:
      return { type: type as 'line' | 'decorative' };
  }
}

/**
 * Check if a document is frame-based (has at least one page with frames).
 */
export function isMagazineDocumentFrameBased(doc: MagazineDocument): boolean {
  return doc.pages.some(p => p.frames.length > 0);
}

// ═══════════════════════════════════════
// DTP API adapters
// ═══════════════════════════════════════

/**
 * Convert DTP API response to MagazineDocument.
 * Accepts spreads/pages/frames structure and flattens spreads into page ordering.
 */
export function dtpApiToMagazineDocument(apiData: any): MagazineDocument {
  const apiSpreads = Array.isArray(apiData?.spreads) ? apiData.spreads : [];
  const apiPages = Array.isArray(apiData?.pages) ? apiData.pages : [];
  const apiFrames = Array.isArray(apiData?.frames) ? apiData.frames : [];

  // Use spread ordering to sort pages when spreads exist
  if (apiSpreads.length > 0) {
    const spreadOrder = new Map<string, number>(apiSpreads.map((s: any, i: number) => [s.id as string, i]));
    apiPages.sort((a: any, b: any) => {
      const sa = spreadOrder.get(a.spread_id) ?? 999;
      const sb = spreadOrder.get(b.spread_id) ?? 999;
      if (sa !== sb) return sa - sb;
      return (a.page_index ?? 0) - (b.page_index ?? 0);
    });
  }

  const sortedPages = [...apiPages].sort((a: any, b: any) => (a.page_index ?? 0) - (b.page_index ?? 0));

  const pages: MagazineDocPage[] = sortedPages.map((p: any, idx: number) => {
    const pageFrames = apiFrames
      .filter((f: any) => f.page_id === p.id)
      .sort((a: any, b: any) => (a.z_index ?? 0) - (b.z_index ?? 0));

    return {
      id: p.id,
      index: idx,
      name: `Page ${idx + 1}`,
      width: p.width || 595,
      height: p.height || 842,
      margins: p.margins || { ...DEFAULT_MARGINS },
      bleed: p.bleed,
      backgroundColor: p.background?.color || '#ffffff',
      frames: pageFrames.map((f: any) => dtpFrameToDocFrame(f)),
    };
  });

  return normalizeMagazineDocument({
    title: apiData?.issue?.title || 'Untitled',
    subtitle: apiData?.issue?.subtitle,
    pageSize: pages[0] ? { width: pages[0].width, height: pages[0].height } : DEFAULT_PAGE_SIZE,
    pages,
  });
}

const DTP_TYPE_MAP: Record<string, MagazineDocFrameType> = {
  text: 'text', image: 'image', quote: 'quote', pageNumber: 'pageNumber',
  shape: 'shape', line: 'line', decorative: 'decorative', articleReference: 'text',
};

function dtpFrameToDocFrame(f: any): MagazineDocFrame {
  const type = DTP_TYPE_MAP[f.frame_type] || 'text';
  const c = f.content || {};

  let content: MagazineDocFrameContent;
  switch (type) {
    case 'text':
      content = { type: 'text', html: c.html || c.text || '' };
      break;
    case 'image':
      content = { type: 'image', src: c.src || '', alt: c.alt || '', caption: c.caption, fitMode: c.fitMode || 'fill', focalPoint: c.focalPoint, opacity: c.opacity ?? 100 };
      break;
    case 'quote':
      content = { type: 'quote', html: c.html || c.text || '', attribution: c.attribution };
      break;
    case 'pageNumber':
      content = { type: 'pageNumber', format: c.format || 'numeric' };
      break;
    case 'shape':
      content = { type: 'shape', fillColor: c.fillColor, cornerRadius: c.cornerRadius };
      break;
    default:
      content = { type: type as 'line' | 'decorative' };
  }

  return {
    id: f.id,
    type,
    name: f.name,
    x: f.x || 0,
    y: f.y || 0,
    width: f.width || 200,
    height: f.height || 100,
    rotation: f.rotation || 0,
    zIndex: f.z_index || 0,
    visible: f.visible !== false,
    locked: f.locked === true,
    content,
  };
}

/**
 * Convert MagazineDocument to DTP API save payload.
 */
export function magazineDocumentToDtpApi(doc: MagazineDocument): Record<string, unknown> {
  const spreads: any[] = [];
  const pages: any[] = [];
  const frames: any[] = [];

  const REVERSE_TYPE: Record<string, string> = {
    text: 'text', image: 'image', shape: 'shape', quote: 'quote',
    pageNumber: 'pageNumber', line: 'line', decorative: 'decorative',
  };

  doc.pages.forEach((page, idx) => {
    // Generate UUID-format spread ID from page ID (must be max 36 chars)
    const pid = page.id.replace(/-/g, '');
    const spreadId = [pid.slice(0,8), pid.slice(8,12), '4' + pid.slice(13,16), '8' + pid.slice(17,20), pid.slice(20,32)].join('-');
    spreads.push({ id: spreadId, spread_index: idx, name: page.name || `Spread ${idx + 1}` });

    pages.push({
      id: page.id,
      spread_id: spreadId,
      page_index: idx,
      side: 'single',
      width: page.width,
      height: page.height,
      margins: page.margins,
      bleed: page.bleed,
      background: { color: page.backgroundColor || '#ffffff' },
    });

    page.frames.forEach(frame => {
      const content: Record<string, unknown> = {};
      const c = frame.content;
      if (c.type === 'text') { content.html = c.html; }
      if (c.type === 'image') { content.src = c.src; content.alt = c.alt; content.caption = c.caption; content.fitMode = c.fitMode; content.focalPoint = c.focalPoint; content.opacity = c.opacity; }
      if (c.type === 'quote') { content.html = c.html; content.attribution = c.attribution; }
      if (c.type === 'shape') { content.fillColor = (c as MagazineDocShapeContent).fillColor; content.cornerRadius = (c as MagazineDocShapeContent).cornerRadius; }
      if (c.type === 'pageNumber') { content.format = (c as MagazineDocPageNumberContent).format; }

      frames.push({
        id: frame.id,
        page_id: page.id,
        spread_id: spreadId,
        frame_type: REVERSE_TYPE[frame.type] || 'text',
        name: frame.name || frame.type,
        x: frame.x, y: frame.y, width: frame.width, height: frame.height,
        rotation: frame.rotation, z_index: frame.zIndex,
        visible: frame.visible, locked: frame.locked,
        content, style: {}, metadata: {},
      });
    });
  });

  return { spreads, pages, layers: [], frames, asset_references: [] };
}

// ═══════════════════════════════════════
// Legacy magazine adapter
// ═══════════════════════════════════════

/**
 * Convert legacy magazine viewer data (pages + elements) to MagazineDocument.
 * Uses percentage-based coordinates → absolute points conversion.
 */
export function legacyMagazineToDocument(
  magazine: { title: string; page_width?: number; page_height?: number },
  pages: Array<{ id: string; elements?: Array<any>; background_color?: string }>,
): MagazineDocument {
  const pageW = (magazine.page_width || 210) * 2.83; // mm → pt
  const pageH = (magazine.page_height || 297) * 2.83;

  const docPages: MagazineDocPage[] = pages.map((p, idx) => ({
    id: p.id,
    index: idx,
    name: `Page ${idx + 1}`,
    width: pageW,
    height: pageH,
    margins: { ...DEFAULT_MARGINS },
    backgroundColor: p.background_color || '#ffffff',
    frames: (p.elements || []).map((el: any) => legacyElementToFrame(el, pageW, pageH)),
  }));

  return normalizeMagazineDocument({
    title: magazine.title,
    pageSize: { width: pageW, height: pageH },
    pages: docPages,
  });
}

function legacyElementToFrame(el: any, pageW: number, pageH: number): MagazineDocFrame {
  const type: MagazineDocFrameType = el.type === 'image' ? 'image' : el.type === 'shape' ? 'shape' : 'text';

  let content: MagazineDocFrameContent;
  const c = el.content || {};
  if (type === 'image') {
    content = { type: 'image', src: c.src || '', alt: c.alt || '', fitMode: c.fit || 'fill', focalPoint: c.focalPoint };
  } else if (type === 'shape') {
    content = { type: 'shape', fillColor: c.fillColor };
  } else {
    content = { type: 'text', html: c.html || c.text || '' };
  }

  return {
    id: el.id || safeUUID(),
    type,
    name: el.name,
    x: (el.x / 100) * pageW,
    y: (el.y / 100) * pageH,
    width: (el.width / 100) * pageW,
    height: (el.height / 100) * pageH,
    rotation: el.rotation || 0,
    zIndex: el.z_index || 0,
    visible: true,
    locked: false,
    content,
  };
}

/**
 * Convert MagazineDocument back to legacy viewer input.
 * For rendering in magazine.blade.php which expects percentage-based coordinates.
 */
export function magazineDocumentToViewerInput(doc: MagazineDocument): {
  pages: Array<{ id: string; background_color: string; elements: any[] }>;
} {
  return {
    pages: doc.pages.map(page => ({
      id: page.id,
      background_color: page.backgroundColor || '#ffffff',
      elements: page.frames.map(frame => {
        const content: Record<string, unknown> = {};
        if (frame.content.type === 'text') { content.html = frame.content.html; }
        if (frame.content.type === 'image') { content.src = frame.content.src; content.alt = frame.content.alt; content.fit = frame.content.fitMode; content.focalPoint = frame.content.focalPoint; }
        if (frame.content.type === 'shape') { content.fillColor = (frame.content as MagazineDocShapeContent).fillColor; }

        return {
          id: frame.id,
          type: frame.content.type === 'image' ? 'image' : frame.content.type === 'shape' ? 'shape' : 'text',
          content,
          x: page.width > 0 ? (frame.x / page.width) * 100 : 0,
          y: page.height > 0 ? (frame.y / page.height) * 100 : 0,
          width: page.width > 0 ? (frame.width / page.width) * 100 : 0,
          height: page.height > 0 ? (frame.height / page.height) * 100 : 0,
          rotation: frame.rotation,
          z_index: frame.zIndex,
          style: {},
        };
      }),
    })),
  };
}
