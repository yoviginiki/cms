// ═══════════════════════════════════════════════════════════════════════════
// DTP API ⇄ magazineStore adapters (extracted from DtpEditorBeta for testing —
// audit W0-6: round-trip integrity is pinned by dtpAdapters.test.ts).
//
// Round-trips: geometry, content/columns/inset, typography (_typography),
// threading (threadId/threadOrder), flow bookkeeping (_autoFlow/_flowHash),
// text wrap (_textWrap), image content-mode fields (offset/scale/rotation/
// filters), element scale (_scaleX/_scaleY), span/position modes.
// Styles and master pages ride in the payload meta (layout_final) — they were
// previously discarded on every save (audit defect #5).
// ═══════════════════════════════════════════════════════════════════════════

import type { MagElement, MagPageData, MagStyleDefinition } from '@/types/magazine';
import {
  DEFAULT_ELEMENT_STYLE,
  DEFAULT_TEXT_WRAP,
  DEFAULT_TYPOGRAPHY,
} from '@/types/magazine';

export const FRAME_TYPE_MAP: Record<string, string> = {
  text: 'text_frame',
  image: 'image_frame',
  quote: 'pullquote_frame',
  pageNumber: 'page_number',
  shape: 'rectangle',
  line: 'line',
  decorative: 'decorative_rule',
  articleReference: 'text_frame',
};

export function dtpApiToPages(apiData: any): MagPageData[] {
  const apiPages = apiData.pages || [];
  const apiFrames = apiData.frames || [];

  if (apiPages.length === 0) {
    // Empty document — create starter page
    return [{
      id: crypto.randomUUID(),
      pageNumber: 1,
      pageSize: { width: 595, height: 842 },
      margins: { top: 36, right: 36, bottom: 36, left: 36 },
      bleed: { top: 9, right: 9, bottom: 9, left: 9 },
      columns: { count: 1, gutter: 12 },
      baselineGrid: { increment: 14, start: 36 },
      isMaster: false,
      masterPageId: null,
      spreadWith: null,
      backgroundColor: '#ffffff',
      backgroundAssetId: null,
      elements: [],
    }];
  }

  const sortedPages = [...apiPages].sort((a: any, b: any) => a.page_index - b.page_index);

  return sortedPages.map((p: any, idx: number) => {
    const pageFrames = apiFrames
      .filter((f: any) => f.page_id === p.id)
      .sort((a: any, b: any) => (a.z_index || 0) - (b.z_index || 0));

    const elements: MagElement[] = pageFrames.map((f: any) => dtpFrameToElement(f, idx + 1));

    return {
      id: p.id,
      pageNumber: idx + 1,
      pageSize: { width: p.width || 595, height: p.height || 842 },
      margins: p.margins || { top: 36, right: 36, bottom: 36, left: 36 },
      bleed: p.bleed || { top: 9, right: 9, bottom: 9, left: 9 },
      columns: { count: 1, gutter: 12 },
      baselineGrid: { increment: 14, start: 36 },
      isMaster: false,
      masterPageId: p.master_page_id || null,
      spreadWith: null,
      backgroundColor: p.background?.color || '#ffffff',
      backgroundAssetId: null,
      elements,
      ...(p.metadata?._guides ? { _guides: p.metadata._guides } : {}),
    };
  });
}

/** merge a possibly-PARTIAL persisted style with defaults — a frame saved
 *  with only { fill } previously crashed panels reading style.cornerRadius.tl */
export function mergeElementStyle(raw: any): MagElement['style'] {
  const d = DEFAULT_ELEMENT_STYLE;
  if (!raw || typeof raw !== 'object' || Object.keys(raw).length === 0) {
    return structuredClone(d);
  }
  return {
    ...d,
    ...raw,
    fill: { ...d.fill, ...(raw.fill || {}) },
    stroke: { ...d.stroke, ...(raw.stroke || {}) },
    cornerRadius: { ...d.cornerRadius, ...(raw.cornerRadius || {}) },
  };
}

/** normalize persisted master pages (meta.masterPages): elements seeded via
 *  API may lack children/textWrap/style — store walkers require them */
export function normalizeMasterPages(raw: any[]): MagPageData[] {
  return (Array.isArray(raw) ? raw : []).map((mp: any) => ({
    ...mp,
    isMaster: true,
    elements: (mp.elements || []).map((e: any) => ({
      rotation: 0, scaleX: 1, scaleY: 1, zIndex: 0, locked: false, visible: true,
      layerName: null, threadId: null, threadOrder: null, onMaster: true,
      positionMode: 'free', spanMode: 'page', parentId: null,
      responsiveOverrides: {}, typography: e.typography ?? null, name: e.name ?? null,
      ...e,
      children: Array.isArray(e.children) ? e.children : [],
      textWrap: e.textWrap ? { ...DEFAULT_TEXT_WRAP, ...e.textWrap } : { ...DEFAULT_TEXT_WRAP },
      style: mergeElementStyle(e.style),
      data: e.data || {},
    })),
  }));
}

export function dtpFrameToElement(f: any, pageNumber: number): MagElement {
  const content = f.content || {};
  // metadata._magType restores the SPECIFIC editor type (circular_image,
  // table_frame, …) that REVERSE_TYPE_MAP collapsed for the API — the audit
  // flagged reload flattening clip shapes and tables to their generic type
  const savedMagType = typeof f.metadata?._magType === 'string' ? f.metadata._magType : null;
  const magType = savedMagType || FRAME_TYPE_MAP[f.frame_type] || 'text_frame';

  const data: Record<string, unknown> = {};
  if (['text', 'quote', 'articleReference'].includes(f.frame_type)) {
    data.content = content.html || content.text || '';
    // Restore text frame settings from saved content
    if (content.overflow) data.overflow = content.overflow;
    if (content.columnsInFrame) data.columnsInFrame = content.columnsInFrame;
    if (content.columnGap) data.columnGap = content.columnGap;
    if (content.columnFill) data.columnFill = content.columnFill;
    if (content.columnRule != null) data.columnRule = content.columnRule;
    if (content.autoSize) data.autoSize = content.autoSize;
    if (content.textInset) data.textInset = content.textInset;
    if (content.verticalAlign) data.verticalAlign = content.verticalAlign;
  }
  if (savedMagType === 'table_frame') {
    data.headers = Array.isArray(content.tableHeaders) ? content.tableHeaders : ['Col 1', 'Col 2'];
    data.rows = Array.isArray(content.tableRows) ? content.tableRows : [['', '']];
    data.stripes = content.tableStripes !== false;
    data.borderColor = content.tableBorderColor || '#e5e7eb';
  }
  if (magType === 'page_number') {
    data.format = content.format || 'decimal';
    data.prefix = content.prefix || '';
    data.suffix = content.suffix || '';
    data.startAt = content.startAt ?? 1;
  }
  if (f.frame_type === 'image') {
    data.src = content.src || '';
    data.alt = content.alt || '';
    data.caption = content.caption || '';
    data.showCaption = content.showCaption;
    data.fit = content.fitMode || 'fill';
    data.focalPoint = content.focalPoint || { x: 0.5, y: 0.5 };
    data.opacity = content.opacity ?? 100;
    data.borderRadius = content.borderRadius;
    data.shadowPreset = content.shadowPreset;
    data.shadowCss = content.shadowCss;
    data.backgroundColor = content.backgroundColor;
    // content-mode fields (pan/scale inside frame) — previously dropped
    if (content.imageOffsetX != null) data.imageOffsetX = content.imageOffsetX;
    if (content.imageOffsetY != null) data.imageOffsetY = content.imageOffsetY;
    if (content.imageScale != null) data.imageScale = content.imageScale;
    if (content.imageRotation != null) data.imageRotation = content.imageRotation;
    if (content.filters) data.filters = content.filters;
    if (content.clipShape) data.clipShape = content.clipShape;
  }

  // Restore typography from metadata if saved
  const savedTypography = f.metadata?._typography;
  // Restore flow-engine bookkeeping (auto-created continuation + input hash)
  if (f.metadata?._autoFlow) data._autoFlow = true;
  if (f.metadata?._flowHash) data._flowHash = f.metadata._flowHash;

  return {
    id: f.id,
    type: magType as any,
    name: f.name || null,
    data,
    x: f.x || 0,
    y: f.y || 0,
    width: f.width || 200,
    height: f.height || 100,
    rotation: f.rotation || 0,
    scaleX: f.metadata?._scaleX ?? 1,
    scaleY: f.metadata?._scaleY ?? 1,
    zIndex: f.z_index || 0,
    locked: f.locked === true,
    visible: f.visible !== false,
    layerName: null,
    style: mergeElementStyle(f.style),
    typography: savedTypography ? { ...DEFAULT_TYPOGRAPHY, ...savedTypography } : (['text', 'quote', 'articleReference'].includes(f.frame_type) ? { ...DEFAULT_TYPOGRAPHY } : null),
    textWrap: f.metadata?._textWrap ? { ...DEFAULT_TEXT_WRAP, ...f.metadata._textWrap } : { ...DEFAULT_TEXT_WRAP },
    threadId: f.metadata?.threadId || null,
    threadOrder: f.metadata?.threadOrder ?? null,
    pageNumber,
    onMaster: f.metadata?.onMaster === true,
    positionMode: (f.metadata?.positionMode || 'free') as 'free' | 'fixed',
    spanMode: (f.metadata?.spanMode || 'page') as 'page' | 'spread',
    parentId: null,
    children: [],
    responsiveOverrides: {},
  };
}

export const REVERSE_TYPE_MAP: Record<string, string> = {
  text_frame: 'text', headline_frame: 'text', pullquote_frame: 'quote',
  caption_frame: 'text', footnote_frame: 'text', marginalia_frame: 'text',
  image_frame: 'image', circular_image: 'image', polygon_image: 'image',
  fullbleed_image: 'image', gallery_frame: 'image', background_image: 'image',
  rectangle: 'shape', ellipse: 'shape', line: 'line', polygon: 'shape',
  freeform_path: 'shape', decorative_rule: 'decorative', gradient_overlay: 'shape',
  page_number: 'pageNumber', running_header: 'text', column_guides: 'text',
  video_frame: 'text', audio_player: 'text', embed_frame: 'text', svg_icon: 'shape',
  button: 'text', hotspot: 'shape', tooltip_trigger: 'shape',
  accordion_frame: 'text', slidein_panel: 'text',
  table_frame: 'text', chart_frame: 'text', infographic_number: 'text', progress_indicator: 'shape',
  group: 'text', component_instance: 'text', clipping_group: 'text',
};

/** Remove undefined values from an object to prevent silent JSON data loss */
export function stripUndefined(obj: Record<string, unknown>): Record<string, unknown> {
  const result: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(obj)) {
    if (v !== undefined) result[k] = v;
  }
  return result;
}

export interface DtpMetaExtras {
  styles?: MagStyleDefinition[];
  masterPages?: MagPageData[];
}

export function pagesToDtpApi(
  pages: MagPageData[],
  apiLayers: any[],
  apiAssetRefs: any[],
  issueSettings?: any,
  viewerSettings?: any,
  extras?: DtpMetaExtras,
): Record<string, unknown> {
  const spreads: any[] = [];
  const outPages: any[] = [];
  const frames: any[] = [];

  // Each frame saves its own data.content directly (the flow engine's slices)

  // W1-4: spreads are FIRST-CLASS — pages pair into real 2-page spreads with
  // persisted verso(left)/recto(right) sides; the cover stays standalone
  // unless coverMode is 'spread'. (Previously every spread was a 1-page
  // wrapper with side:'single' and pairing was recomputed heuristically at
  // render time — audit M-A conflict.)
  const coverStandalone = (issueSettings?.coverMode ?? 'standalone') !== 'spread';
  const groups: MagPageData[][] = [];
  let gi = 0;
  if (coverStandalone && pages.length > 0) {
    groups.push([pages[0]]);
    gi = 1;
  }
  while (gi < pages.length) {
    groups.push(pages.slice(gi, gi + 2));
    gi += 2;
  }
  // stable spread id derived from the group's FIRST page id
  const stableSpreadId = (pageId: string) => {
    const pid = pageId.replace(/-/g, '');
    return [pid.slice(0, 8), pid.slice(8, 12), '4' + pid.slice(13, 16), '8' + pid.slice(17, 20), pid.slice(20, 32)].join('-');
  };
  const pageSpread = new Map<string, { id: string; side: string }>();
  groups.forEach((group, sIdx) => {
    const spreadId = stableSpreadId(group[0].id);
    spreads.push({ id: spreadId, spread_index: sIdx, name: sIdx === 0 && coverStandalone ? 'Cover' : `Spread ${sIdx + 1}` });
    group.forEach((pg, pIdx) => {
      pageSpread.set(pg.id, { id: spreadId, side: group.length === 1 ? 'single' : pIdx === 0 ? 'left' : 'right' });
    });
  });

  pages.forEach((page, idx) => {
    const sp = pageSpread.get(page.id)!;
    const spreadId = sp.id;

    outPages.push({
      id: page.id,
      spread_id: spreadId,
      page_index: idx,
      side: sp.side,
      width: page.pageSize.width,
      height: page.pageSize.height,
      margins: page.margins,
      bleed: page.bleed,
      background: { color: page.backgroundColor || '#ffffff' },
      master_page_id: page.masterPageId || null,
      metadata: stripUndefined({ _guides: (page as any)._guides }),
    });

    page.elements.forEach(el => {
      const frameType = REVERSE_TYPE_MAP[el.type] || 'text';
      const content: Record<string, unknown> = {};

      if (el.type === 'table_frame') {
        content.tableHeaders = (el.data as any)?.headers || ['Col 1', 'Col 2'];
        content.tableRows = (el.data as any)?.rows || [['', '']];
        content.tableStripes = (el.data as any)?.stripes !== false;
        content.tableBorderColor = (el.data as any)?.borderColor || '#e5e7eb';
      } else if (el.type === 'page_number') {
        content.format = (el.data as any)?.format || 'decimal';
        content.prefix = (el.data as any)?.prefix || '';
        content.suffix = (el.data as any)?.suffix || '';
        content.startAt = (el.data as any)?.startAt ?? 1;
      } else if (['text', 'quote'].includes(frameType)) {
        content.html = (el.data as any)?.content || '';
        // Preserve text frame settings
        content.overflow = (el.data as any)?.overflow;
        content.columnsInFrame = (el.data as any)?.columnsInFrame;
        content.columnGap = (el.data as any)?.columnGap;
        content.columnFill = (el.data as any)?.columnFill;
        content.columnRule = (el.data as any)?.columnRule;
        content.autoSize = (el.data as any)?.autoSize;
        content.textInset = (el.data as any)?.textInset;
        content.verticalAlign = (el.data as any)?.verticalAlign;
      }
      if (frameType === 'image') {
        const imgSrc = (el.data as any)?.src || '';
        // Allow http(s) URLs and relative paths (/storage/...), reject empty and unsafe schemes
        content.src = imgSrc && (/^https?:\/\//i.test(imgSrc) || imgSrc.startsWith('/')) ? imgSrc : null;
        content.alt = (el.data as any)?.alt || '';
        content.caption = (el.data as any)?.caption || '';
        content.showCaption = (el.data as any)?.showCaption;
        content.fitMode = (el.data as any)?.fit || 'fill';
        content.focalPoint = (el.data as any)?.focalPoint || { x: 0.5, y: 0.5 };
        content.opacity = (el.data as any)?.opacity ?? 100;
        content.borderRadius = (el.data as any)?.borderRadius;
        content.shadowPreset = (el.data as any)?.shadowPreset;
        content.shadowCss = (el.data as any)?.shadowCss;
        content.backgroundColor = (el.data as any)?.backgroundColor;
        // content-mode fields — previously lost on save (audit M-F)
        content.imageOffsetX = (el.data as any)?.imageOffsetX;
        content.imageOffsetY = (el.data as any)?.imageOffsetY;
        content.imageScale = (el.data as any)?.imageScale;
        content.imageRotation = (el.data as any)?.imageRotation;
        content.filters = (el.data as any)?.filters;
        content.clipShape = (el.data as any)?.clipShape;
      }

      frames.push({
        id: el.id,
        page_id: page.id,
        spread_id: spreadId,
        frame_type: frameType,
        name: el.name || el.type.replace(/_/g, ' '),
        x: el.x,
        y: el.y,
        width: el.width,
        height: el.height,
        rotation: el.rotation,
        z_index: el.zIndex,
        visible: el.visible,
        locked: el.locked,
        content: stripUndefined(content),
        style: stripUndefined(el.style as unknown as Record<string, unknown> || {}),
        metadata: stripUndefined({
          onMaster: el.onMaster || false,
          positionMode: el.positionMode || 'free',
          spanMode: el.spanMode || 'page',
          _typography: el.typography || null,
          threadId: el.threadId || null,
          threadOrder: el.threadOrder ?? null,
          _magType: el.type,
          _autoFlow: (el.data as any)?._autoFlow ? true : undefined,
          _flowHash: (el.data as any)?._flowHash,
          _textWrap: el.textWrap && el.textWrap.type !== 'none' ? el.textWrap : undefined,
          _scaleX: el.scaleX !== 1 ? el.scaleX : undefined,
          _scaleY: el.scaleY !== 1 ? el.scaleY : undefined,
        }),
      });
    });
  });

  return {
    spreads,
    pages: outPages,
    layers: apiLayers,
    frames,
    asset_references: apiAssetRefs,
    meta: {
      issueSettings,
      viewerSettings,
      // styles + masters were previously discarded on every save (audit #5)
      styles: extras?.styles ?? [],
      masterPages: extras?.masterPages ?? [],
    },
  };
}
