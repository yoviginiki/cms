/**
 * MAG-DTP-CHECK-1 — Editor vs Saved vs Viewer Consistency Checker.
 *
 * Compares document state across stages:
 *   A. editorDocument — current in-memory state
 *   B. savePayload   — what was sent to the API on save
 *   C. loadedRaw     — raw API response on load
 *   D. normalized    — after normalization
 *   E. viewerModel   — viewer adapter input (partial; full viewer render model not accessible)
 *
 * Pure functions — no React, no store, no side effects.
 */

// ═══════════════════════════════════════
// Result model
// ═══════════════════════════════════════

export type ConsistencyStatus = 'pass' | 'warning' | 'fail';

export interface ConsistencyResult {
  status: ConsistencyStatus;
  checkedAt: number;
  summary: {
    checkedPaths: number;
    failures: number;
    warnings: number;
    lostFields: number;
    viewerMismatches: number;
    savePayloadMismatches: number;
    normalizationMismatches: number;
  };
  issues: ConsistencyIssue[];
  viewerCheckPartial: boolean;
}

export type IssueSeverity = 'error' | 'warning' | 'info';

export type IssueCode =
  | 'field_missing_in_payload'
  | 'field_lost_after_save'
  | 'field_stripped_by_normalizer'
  | 'viewer_render_mismatch'
  | 'layout_mode_mismatch'
  | 'frame_missing_after_load'
  | 'image_missing_after_save'
  | 'style_not_rendered_in_viewer'
  | 'typography_not_rendered_in_viewer'
  | 'page_order_mismatch'
  | 'page_count_mismatch'
  | 'frame_count_mismatch'
  | 'field_changed_after_load';

export interface ConsistencyIssue {
  id: string;
  severity: IssueSeverity;
  code: IssueCode;
  path: string;
  label: string;
  editorValue?: any;
  payloadValue?: any;
  loadedValue?: any;
  normalizedValue?: any;
  viewerValue?: any;
  message: string;
  suggestion: string;
  relatedFrameId?: string | null;
  relatedPageId?: string | null;
}

// ═══════════════════════════════════════
// Human-readable labels
// ═══════════════════════════════════════

const PATH_LABELS: Record<string, string> = {
  'settings.layoutMode': 'Issue layout mode',
  'settings.coverMode': 'Cover mode',
  'settings.readingDirection': 'Reading direction',
  'pageSize.width': 'Default page width',
  'pageSize.height': 'Default page height',
  'page.id': 'Page ID',
  'page.width': 'Page width',
  'page.height': 'Page height',
  'page.margins': 'Page margins',
  'page.backgroundColor': 'Page background color',
  'page.masterPageId': 'Master page assignment',
  'frame.id': 'Frame ID',
  'frame.type': 'Frame type',
  'frame.x': 'Frame X position',
  'frame.y': 'Frame Y position',
  'frame.width': 'Frame width',
  'frame.height': 'Frame height',
  'frame.zIndex': 'Frame Z-order',
  'frame.visible': 'Frame visibility',
  'frame.locked': 'Frame lock state',
  'frame.rotation': 'Frame rotation',
  'content.html': 'Text content',
  'content.text': 'Text content',
  'content.src': 'Image source',
  'content.alt': 'Image alt text',
  'content.caption': 'Image caption',
  'content.showCaption': 'Show caption',
  'content.fitMode': 'Image fit mode',
  'content.focalPoint': 'Image focal point',
  'content.opacity': 'Image opacity',
  'typography.fontFamily': 'Font family',
  'typography.fontSize': 'Font size',
  'typography.fontWeight': 'Font weight',
  'typography.lineHeight': 'Line height',
  'typography.letterSpacing': 'Letter spacing',
  'typography.color': 'Text color',
  'typography.textColor': 'Text color',
  'typography.textAlign': 'Text alignment',
  'style.backgroundColor': 'Background color',
  'style.opacity': 'Style opacity',
  'style.borderWidth': 'Border width',
  'style.borderColor': 'Border color',
  'style.borderStyle': 'Border style',
  'style.borderRadius': 'Border radius',
  'style.shadowPreset': 'Shadow preset',
  'style.boxShadow': 'Box shadow',
  'style.fill': 'Fill',
  'style.stroke': 'Stroke',
  'threadId': 'Text continuation link',
  'threadOrder': 'Text continuation order',
  'positionMode': 'Position mode',
  'spanMode': 'Span mode',
  'masterPageId': 'Master page',
  'editorSettings.guides': 'Guides visibility',
  'editorSettings.grid': 'Grid visibility',
  'editorSettings.snapping': 'Snapping enabled',
};

export function labelForPath(path: string): string {
  // Try exact match first
  if (PATH_LABELS[path]) return PATH_LABELS[path];
  // Try suffix match (e.g. "pages[0].frames[1].content.src" → "content.src")
  for (const [key, label] of Object.entries(PATH_LABELS)) {
    if (path.endsWith(key) || path.endsWith(`.${key}`)) return label;
  }
  // Fallback: humanize the last segment
  const last = path.split('.').pop() || path;
  return last.replace(/([A-Z])/g, ' $1').replace(/[_-]/g, ' ').trim();
}

// ═══════════════════════════════════════
// Stage data extractors
// ═══════════════════════════════════════

/** Extract comparable document snapshot from editor store state (MagPageData[]) */
export function extractEditorDoc(pages: any[], issueSettings: any, editorSettings?: { showGrid?: boolean; showGuides?: boolean; snapEnabled?: boolean }): EditorSnapshot {
  const contentPages = pages.filter((p: any) => !p.isMaster);
  return {
    settings: {
      layoutMode: issueSettings?.layoutMode || 'single',
      coverMode: issueSettings?.coverMode || 'standalone',
      readingDirection: issueSettings?.readingDirection || 'ltr',
    },
    editorSettings: {
      guides: editorSettings?.showGuides ?? true,
      grid: editorSettings?.showGrid ?? false,
      snapping: editorSettings?.snapEnabled ?? true,
    },
    pageCount: contentPages.length,
    pages: contentPages.map((p: any, idx: number) => ({
      id: p.id,
      index: idx,
      width: p.pageSize?.width || 595,
      height: p.pageSize?.height || 842,
      margins: p.margins,
      backgroundColor: p.backgroundColor || '#ffffff',
      masterPageId: p.masterPageId || null,
      frames: (p.elements || []).map((e: any) => extractEditorFrame(e)),
    })),
  };
}

interface EditorSnapshot {
  settings: { layoutMode: string; coverMode: string; readingDirection: string };
  editorSettings: { guides: boolean; grid: boolean; snapping: boolean };
  pageCount: number;
  pages: EditorPageSnap[];
}

interface EditorPageSnap {
  id: string;
  index: number;
  width: number;
  height: number;
  margins: any;
  backgroundColor: string;
  masterPageId: string | null;
  frames: EditorFrameSnap[];
}

interface EditorFrameSnap {
  id: string;
  type: string;
  x: number;
  y: number;
  width: number;
  height: number;
  zIndex: number;
  rotation: number;
  visible: boolean;
  locked: boolean;
  content: Record<string, any>;
  typography: Record<string, any> | null;
  style: Record<string, any>;
  threadId: string | null;
  threadOrder: number | null;
  positionMode: string;
  spanMode: string;
}

function extractEditorFrame(e: any): EditorFrameSnap {
  const data = e.data || {};
  return {
    id: e.id,
    type: e.type,
    x: e.x, y: e.y, width: e.width, height: e.height,
    zIndex: e.zIndex, rotation: e.rotation || 0,
    visible: e.visible !== false, locked: e.locked === true,
    content: {
      html: data.content, src: data.src, alt: data.alt,
      caption: data.caption, showCaption: data.showCaption,
      fitMode: data.fit, focalPoint: data.focalPoint,
      opacity: data.opacity,
    },
    typography: e.typography ? { ...e.typography } : null,
    style: e.style ? { ...e.style } : {},
    threadId: e.threadId || null,
    threadOrder: e.threadOrder ?? null,
    positionMode: e.positionMode || 'free',
    spanMode: e.spanMode || 'page',
  };
}

/** Extract comparable snapshot from save payload (DTP API format) */
export function extractPayloadDoc(payload: any, issueSettings?: any): PayloadSnapshot | null {
  if (!payload) return null;
  const pages = payload.pages || [];
  const frames = payload.frames || [];
  return {
    settings: {
      layoutMode: payload.meta?.issueSettings?.layoutMode || issueSettings?.layoutMode || null,
      coverMode: payload.meta?.issueSettings?.coverMode || issueSettings?.coverMode || null,
    },
    pageCount: pages.length,
    pages: pages.map((p: any, idx: number) => {
      const pageFrames = frames.filter((f: any) => f.page_id === p.id);
      return {
        id: p.id,
        index: idx,
        width: p.width, height: p.height,
        margins: p.margins,
        backgroundColor: p.background?.color || null,
        masterPageId: p.master_page_id || null,
        frames: pageFrames.map((f: any) => ({
          id: f.id,
          type: f.frame_type,
          x: f.x, y: f.y, width: f.width, height: f.height,
          zIndex: f.z_index, rotation: f.rotation || 0,
          visible: f.visible, locked: f.locked,
          content: f.content || {},
          typography: f.metadata?._typography || null,
          style: f.style || {},
          threadId: f.metadata?.threadId || null,
          threadOrder: f.metadata?.threadOrder ?? null,
          positionMode: f.metadata?.positionMode || null,
        })),
      };
    }),
  };
}

interface PayloadSnapshot {
  settings: { layoutMode: string | null; coverMode: string | null };
  pageCount: number;
  pages: any[];
}

/** Extract comparable snapshot from loaded raw API data */
export function extractLoadedDoc(apiData: any): PayloadSnapshot | null {
  if (!apiData) return null;
  return extractPayloadDoc(apiData, apiData.meta?.issueSettings);
}

// ═══════════════════════════════════════
// Core checker
// ═══════════════════════════════════════

let issueCounter = 0;

function makeIssue(
  severity: IssueSeverity,
  code: IssueCode,
  path: string,
  values: {
    editorValue?: any; payloadValue?: any; loadedValue?: any;
    normalizedValue?: any; viewerValue?: any;
  },
  message: string,
  suggestion: string,
  relatedFrameId?: string | null,
  relatedPageId?: string | null,
): ConsistencyIssue {
  return {
    id: `ci-${++issueCounter}-${Date.now()}`,
    severity, code, path,
    label: labelForPath(path),
    ...values,
    message, suggestion,
    relatedFrameId: relatedFrameId || null,
    relatedPageId: relatedPageId || null,
  };
}

function valuesMatch(a: any, b: any): boolean {
  if (a === b) return true;
  if (a == null && b == null) return true;
  if (a == null || b == null) return false;
  if (typeof a !== typeof b) return false;
  if (typeof a === 'object') return JSON.stringify(a) === JSON.stringify(b);
  return false;
}

export function runConsistencyCheck(
  editorDoc: EditorSnapshot,
  payloadDoc: PayloadSnapshot | null,
  loadedDoc: PayloadSnapshot | null,
  viewerModel: any | null,
): ConsistencyResult {
  issueCounter = 0;
  const issues: ConsistencyIssue[] = [];
  let checkedPaths = 0;

  // ─── Settings checks ───
  if (payloadDoc) {
    checkedPaths++;
    if (editorDoc.settings.layoutMode && payloadDoc.settings.layoutMode &&
        editorDoc.settings.layoutMode !== payloadDoc.settings.layoutMode) {
      issues.push(makeIssue('error', 'layout_mode_mismatch', 'settings.layoutMode',
        { editorValue: editorDoc.settings.layoutMode, payloadValue: payloadDoc.settings.layoutMode },
        `Layout mode "${editorDoc.settings.layoutMode}" in editor but "${payloadDoc.settings.layoutMode}" in save payload`,
        'Check that issueSettings are included in the save payload meta',
      ));
    }
    if (!payloadDoc.settings.layoutMode && editorDoc.settings.layoutMode) {
      issues.push(makeIssue('error', 'field_missing_in_payload', 'settings.layoutMode',
        { editorValue: editorDoc.settings.layoutMode, payloadValue: null },
        'Layout mode exists in editor but missing from save payload',
        'Ensure pagesToDtpApi includes issueSettings in meta',
      ));
    }
  }

  if (loadedDoc && payloadDoc) {
    checkedPaths++;
    if (payloadDoc.settings.layoutMode && !loadedDoc.settings.layoutMode) {
      issues.push(makeIssue('error', 'field_lost_after_save', 'settings.layoutMode',
        { payloadValue: payloadDoc.settings.layoutMode, loadedValue: null },
        'Layout mode was saved but missing after reload',
        'Backend may not persist meta.issueSettings',
      ));
    }
  }

  // ─── Editor settings checks (editor-only, not saved to API — verify they survive reload) ───
  if (editorDoc.editorSettings) {
    const es = editorDoc.editorSettings;
    checkedPaths += 3;
    // Editor settings are not saved to the API payload, so we only log info-level
    // if they deviate from defaults after a reload (guides default=true, grid=false, snap=true)
    if (loadedDoc && es.guides !== true) {
      issues.push(makeIssue('info', 'field_missing_in_payload', 'editorSettings.guides',
        { editorValue: es.guides },
        'Guide visibility is editor-local and not persisted to API',
        'Editor settings are not saved — this is expected behavior',
      ));
    }
    if (loadedDoc && es.grid !== false) {
      issues.push(makeIssue('info', 'field_missing_in_payload', 'editorSettings.grid',
        { editorValue: es.grid },
        'Grid visibility is editor-local and not persisted to API',
        'Editor settings are not saved — this is expected behavior',
      ));
    }
    if (loadedDoc && es.snapping !== true) {
      issues.push(makeIssue('info', 'field_missing_in_payload', 'editorSettings.snapping',
        { editorValue: es.snapping },
        'Snapping state is editor-local and not persisted to API',
        'Editor settings are not saved — this is expected behavior',
      ));
    }
  }

  // ─── Page count checks ───
  if (payloadDoc) {
    checkedPaths++;
    if (editorDoc.pageCount !== payloadDoc.pageCount) {
      issues.push(makeIssue('error', 'page_count_mismatch', 'pages.length',
        { editorValue: editorDoc.pageCount, payloadValue: payloadDoc.pageCount },
        `Editor has ${editorDoc.pageCount} pages but payload has ${payloadDoc.pageCount}`,
        'Check that master pages are correctly filtered before save',
      ));
    }
  }
  if (loadedDoc && payloadDoc) {
    checkedPaths++;
    if (payloadDoc.pageCount !== loadedDoc.pageCount) {
      issues.push(makeIssue('error', 'page_count_mismatch', 'pages.length',
        { payloadValue: payloadDoc.pageCount, loadedValue: loadedDoc.pageCount },
        `Saved ${payloadDoc.pageCount} pages but loaded ${loadedDoc.pageCount}`,
        'Backend may not persist all pages',
      ));
    }
  }

  // ─── Page-level checks ───
  const maxPages = Math.max(
    editorDoc.pages.length,
    payloadDoc?.pages.length || 0,
    loadedDoc?.pages.length || 0,
  );

  for (let pi = 0; pi < maxPages; pi++) {
    const edPage = editorDoc.pages[pi];
    const plPage = payloadDoc?.pages[pi];
    const ldPage = loadedDoc?.pages[pi];
    const pageId = edPage?.id || plPage?.id || ldPage?.id || null;

    // Page order check
    if (edPage && plPage) {
      checkedPaths++;
      if (edPage.id !== plPage.id) {
        issues.push(makeIssue('warning', 'page_order_mismatch', `pages[${pi}].id`,
          { editorValue: edPage.id, payloadValue: plPage.id },
          `Page ${pi + 1} ID differs between editor and payload`,
          'Page ordering may have changed during save',
          null, pageId,
        ));
      }
    }

    if (!edPage) continue;

    // Page properties
    const pageChecks: Array<{ path: string; edVal: any; plVal: any; ldVal: any }> = [
      { path: `pages[${pi}].width`, edVal: edPage.width, plVal: plPage?.width, ldVal: ldPage?.width },
      { path: `pages[${pi}].height`, edVal: edPage.height, plVal: plPage?.height, ldVal: ldPage?.height },
      { path: `pages[${pi}].backgroundColor`, edVal: edPage.backgroundColor, plVal: plPage?.backgroundColor, ldVal: ldPage?.backgroundColor },
      { path: `pages[${pi}].masterPageId`, edVal: edPage.masterPageId, plVal: plPage?.masterPageId, ldVal: ldPage?.masterPageId },
    ];

    for (const chk of pageChecks) {
      checkedPaths++;
      if (plPage && chk.edVal != null && !valuesMatch(chk.edVal, chk.plVal)) {
        issues.push(makeIssue('warning', 'field_missing_in_payload', chk.path,
          { editorValue: chk.edVal, payloadValue: chk.plVal },
          `${labelForPath(chk.path)} differs: editor="${JSON.stringify(chk.edVal)}" vs payload="${JSON.stringify(chk.plVal)}"`,
          'Verify the field is serialized in pagesToDtpApi',
          null, pageId,
        ));
      }
      if (plPage && ldPage && chk.plVal != null && !valuesMatch(chk.plVal, chk.ldVal)) {
        issues.push(makeIssue('error', 'field_lost_after_save', chk.path,
          { payloadValue: chk.plVal, loadedValue: chk.ldVal },
          `${labelForPath(chk.path)} was saved but changed after reload`,
          'Backend may strip or transform this field',
          null, pageId,
        ));
      }
    }

    // ─── Frame-level checks ───
    const edFrames = edPage.frames;
    const plFrames = plPage?.frames || [];
    const ldFrames = ldPage?.frames || [];

    // Frame count
    if (plPage) {
      checkedPaths++;
      if (edFrames.length !== plFrames.length) {
        issues.push(makeIssue('warning', 'frame_count_mismatch', `pages[${pi}].frames.length`,
          { editorValue: edFrames.length, payloadValue: plFrames.length },
          `Page ${pi + 1}: editor has ${edFrames.length} frames but payload has ${plFrames.length}`,
          'Some frames may not be serialized',
          null, pageId,
        ));
      }
    }

    for (const edFrame of edFrames) {
      const plFrame = plFrames.find((f: any) => f.id === edFrame.id);
      const ldFrame = ldFrames.find((f: any) => f.id === edFrame.id);
      const fPrefix = `pages[${pi}].frames[${edFrame.id.slice(0, 6)}]`;

      // Frame missing in payload
      if (payloadDoc && !plFrame) {
        checkedPaths++;
        issues.push(makeIssue('error', 'frame_missing_after_load', `${fPrefix}`,
          { editorValue: edFrame.type },
          `Frame "${edFrame.type}" (${edFrame.id.slice(0, 8)}) exists in editor but missing from save payload`,
          'Frame may not be serialized by pagesToDtpApi',
          edFrame.id, pageId,
        ));
        continue;
      }

      // Frame missing after load
      if (loadedDoc && plFrame && !ldFrame) {
        checkedPaths++;
        issues.push(makeIssue('error', 'frame_missing_after_load', `${fPrefix}`,
          { payloadValue: plFrame.type },
          `Frame "${edFrame.type}" (${edFrame.id.slice(0, 8)}) was saved but missing after reload`,
          'Backend may not persist this frame',
          edFrame.id, pageId,
        ));
      }

      if (!plFrame) continue;

      // Geometry
      const geoChecks: Array<{ key: string; edVal: any; plVal: any; ldVal: any }> = [
        { key: 'x', edVal: edFrame.x, plVal: plFrame.x, ldVal: ldFrame?.x },
        { key: 'y', edVal: edFrame.y, plVal: plFrame.y, ldVal: ldFrame?.y },
        { key: 'width', edVal: edFrame.width, plVal: plFrame.width, ldVal: ldFrame?.width },
        { key: 'height', edVal: edFrame.height, plVal: plFrame.height, ldVal: ldFrame?.height },
        { key: 'zIndex', edVal: edFrame.zIndex, plVal: plFrame.zIndex, ldVal: ldFrame?.zIndex ?? ldFrame?.z_index },
        { key: 'visible', edVal: edFrame.visible, plVal: plFrame.visible, ldVal: ldFrame?.visible },
        { key: 'locked', edVal: edFrame.locked, plVal: plFrame.locked, ldVal: ldFrame?.locked },
      ];

      for (const gc of geoChecks) {
        checkedPaths++;
        if (gc.edVal != null && !valuesMatch(gc.edVal, gc.plVal)) {
          issues.push(makeIssue('warning', 'field_missing_in_payload', `${fPrefix}.${gc.key}`,
            { editorValue: gc.edVal, payloadValue: gc.plVal },
            `${labelForPath(`frame.${gc.key}`)} differs between editor and payload`,
            'Check serialization', edFrame.id, pageId,
          ));
        }
        if (ldFrame && gc.plVal != null && !valuesMatch(gc.plVal, gc.ldVal)) {
          issues.push(makeIssue('error', 'field_changed_after_load', `${fPrefix}.${gc.key}`,
            { payloadValue: gc.plVal, loadedValue: gc.ldVal },
            `${labelForPath(`frame.${gc.key}`)} changed after save/reload`,
            'Backend may transform this value', edFrame.id, pageId,
          ));
        }
      }

      // Content checks
      const contentPairs: Array<{ path: string; edVal: any; plVal: any; ldVal: any; code: IssueCode }> = [];
      const ec = edFrame.content;
      const pc = plFrame.content || {};
      const lc = ldFrame?.content || {};

      // Text content
      if (ec.html != null) {
        contentPairs.push({ path: `${fPrefix}.content.html`, edVal: ec.html, plVal: pc.html, ldVal: lc.html, code: 'field_missing_in_payload' });
      }
      // Image source — critical
      if (ec.src != null && ec.src !== '') {
        contentPairs.push({ path: `${fPrefix}.content.src`, edVal: ec.src, plVal: pc.src, ldVal: lc.src, code: 'image_missing_after_save' });
      }
      // Image settings
      if (ec.fitMode != null) contentPairs.push({ path: `${fPrefix}.content.fitMode`, edVal: ec.fitMode, plVal: pc.fitMode, ldVal: lc.fitMode, code: 'field_missing_in_payload' });
      if (ec.focalPoint != null) contentPairs.push({ path: `${fPrefix}.content.focalPoint`, edVal: ec.focalPoint, plVal: pc.focalPoint, ldVal: lc.focalPoint, code: 'field_missing_in_payload' });
      if (ec.opacity != null) contentPairs.push({ path: `${fPrefix}.content.opacity`, edVal: ec.opacity, plVal: pc.opacity, ldVal: lc.opacity, code: 'field_missing_in_payload' });
      if (ec.alt != null) contentPairs.push({ path: `${fPrefix}.content.alt`, edVal: ec.alt, plVal: pc.alt, ldVal: lc.alt, code: 'field_missing_in_payload' });
      if (ec.caption != null) contentPairs.push({ path: `${fPrefix}.content.caption`, edVal: ec.caption, plVal: pc.caption, ldVal: lc.caption, code: 'field_missing_in_payload' });

      for (const cp of contentPairs) {
        checkedPaths++;
        if (cp.edVal != null && cp.edVal !== '' && !valuesMatch(cp.edVal, cp.plVal)) {
          const sev = cp.code === 'image_missing_after_save' ? 'error' : 'warning';
          issues.push(makeIssue(sev as IssueSeverity, cp.code, cp.path,
            { editorValue: cp.edVal, payloadValue: cp.plVal },
            `${labelForPath(cp.path)} in editor but differs/missing in payload`,
            'Check save serialization', edFrame.id, pageId,
          ));
        }
        if (ldFrame && cp.plVal != null && cp.plVal !== '' && !valuesMatch(cp.plVal, cp.ldVal)) {
          issues.push(makeIssue('error', 'field_lost_after_save', cp.path,
            { payloadValue: cp.plVal, loadedValue: cp.ldVal },
            `${labelForPath(cp.path)} was saved but lost/changed after reload`,
            'Backend may not persist this content field', edFrame.id, pageId,
          ));
        }
      }

      // Typography checks
      if (edFrame.typography) {
        const et = edFrame.typography;
        const pt = plFrame.typography || {};
        const lt = ldFrame?.typography || {};
        const typoKeys = ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'color', 'textColor', 'textAlign'];
        for (const tk of typoKeys) {
          if (et[tk] == null) continue;
          checkedPaths++;
          if (!valuesMatch(et[tk], pt[tk])) {
            issues.push(makeIssue('warning', 'field_missing_in_payload', `${fPrefix}.typography.${tk}`,
              { editorValue: et[tk], payloadValue: pt[tk] },
              `${labelForPath(`typography.${tk}`)} exists in editor but differs in payload`,
              'Typography may not be fully serialized', edFrame.id, pageId,
            ));
          }
          if (ldFrame && pt[tk] != null && !valuesMatch(pt[tk], lt[tk])) {
            issues.push(makeIssue('error', 'typography_not_rendered_in_viewer', `${fPrefix}.typography.${tk}`,
              { payloadValue: pt[tk], loadedValue: lt[tk] },
              `${labelForPath(`typography.${tk}`)} lost after save/reload`,
              'Typography serialization may be incomplete', edFrame.id, pageId,
            ));
          }
        }
      }

      // Style checks (skip default/empty values to avoid false positives)
      if (edFrame.style) {
        const es = edFrame.style;
        const ps = plFrame.style || {};
        const styleKeys = ['fill', 'stroke', 'opacity', 'borderRadius', 'shadow', 'blur', 'backdropBlur'];
        const STYLE_DEFAULTS: Record<string, any> = { opacity: 1, borderRadius: 0, blur: 0, backdropBlur: 0 };
        for (const sk of styleKeys) {
          if (es[sk] == null) continue;
          // Skip if editor value is the default and payload doesn't have it
          if (STYLE_DEFAULTS[sk] !== undefined && es[sk] === STYLE_DEFAULTS[sk] && ps[sk] == null) continue;
          checkedPaths++;
          if (!valuesMatch(es[sk], ps[sk])) {
            issues.push(makeIssue('warning', 'field_missing_in_payload', `${fPrefix}.style.${sk}`,
              { editorValue: es[sk], payloadValue: ps[sk] },
              `${labelForPath(`style.${sk}`)} in editor but missing/different in payload`,
              'Style field may not be serialized', edFrame.id, pageId,
            ));
          }
        }
      }

      // Thread/link checks
      if (edFrame.threadId) {
        checkedPaths++;
        if (!valuesMatch(edFrame.threadId, plFrame.threadId)) {
          issues.push(makeIssue('warning', 'field_missing_in_payload', `${fPrefix}.threadId`,
            { editorValue: edFrame.threadId, payloadValue: plFrame.threadId },
            'Text continuation link missing from payload',
            'Check metadata.threadId serialization', edFrame.id, pageId,
          ));
        }
      }
      if (edFrame.threadOrder != null) {
        checkedPaths++;
        if (!valuesMatch(edFrame.threadOrder, plFrame.threadOrder)) {
          issues.push(makeIssue('warning', 'field_missing_in_payload', `${fPrefix}.threadOrder`,
            { editorValue: edFrame.threadOrder, payloadValue: plFrame.threadOrder },
            'Text continuation order missing from payload',
            'Check metadata.threadOrder serialization', edFrame.id, pageId,
          ));
        }
      }
    }
  }

  // ─── Viewer model comparison (partial) ───
  let viewerMismatches = 0;
  const viewerCheckPartial = !viewerModel;

  if (viewerModel && viewerModel.pages) {
    const vPages = viewerModel.pages;
    for (let vi = 0; vi < Math.min(editorDoc.pages.length, vPages.length); vi++) {
      const edPage = editorDoc.pages[vi];
      const vPage = vPages[vi];
      const vElements = vPage.elements || [];

      for (const edFrame of edPage.frames) {
        const vEl = vElements.find((v: any) => v.id === edFrame.id);
        if (!vEl) {
          checkedPaths++;
          viewerMismatches++;
          issues.push(makeIssue('warning', 'viewer_render_mismatch', `viewer.pages[${vi}].frames[${edFrame.id.slice(0, 6)}]`,
            { editorValue: edFrame.type, viewerValue: null },
            `Frame "${edFrame.type}" exists in editor but not in viewer model`,
            'Viewer adapter may not include all frame types', edFrame.id, edPage.id,
          ));
          continue;
        }

        // Check content fields in viewer
        const vc = vEl.content || {};
        if (edFrame.content.src && !vc.src) {
          checkedPaths++;
          viewerMismatches++;
          issues.push(makeIssue('error', 'style_not_rendered_in_viewer', `viewer.pages[${vi}].frames[${edFrame.id.slice(0, 6)}].content.src`,
            { editorValue: edFrame.content.src, viewerValue: vc.src },
            'Image source exists in editor but missing from viewer',
            'Check magazineDocumentToViewerInput adapter', edFrame.id, edPage.id,
          ));
        }
        if (edFrame.content.html && !vc.html && !vc.text) {
          checkedPaths++;
          viewerMismatches++;
          issues.push(makeIssue('warning', 'viewer_render_mismatch', `viewer.pages[${vi}].frames[${edFrame.id.slice(0, 6)}].content.html`,
            { editorValue: '(html present)', viewerValue: null },
            'Text content exists in editor but missing from viewer',
            'Check viewer text rendering', edFrame.id, edPage.id,
          ));
        }

        // Check style presence in viewer
        if (edFrame.style && vEl.style) {
          const edStyleKeys = Object.keys(edFrame.style).filter(k => edFrame.style[k] != null);
          const vStyleKeys = Object.keys(vEl.style);
          for (const sk of edStyleKeys) {
            if (!vStyleKeys.includes(sk) && edFrame.style[sk] != null) {
              checkedPaths++;
              viewerMismatches++;
              issues.push(makeIssue('warning', 'style_not_rendered_in_viewer', `viewer.pages[${vi}].frames[${edFrame.id.slice(0, 6)}].style.${sk}`,
                { editorValue: edFrame.style[sk], viewerValue: undefined },
                `${labelForPath(`style.${sk}`)} in editor but not in viewer`,
                'Viewer may not support this style property', edFrame.id, edPage.id,
              ));
            }
          }
        }
      }
    }
  }

  // ─── Compute summary ───
  const failures = issues.filter(i => i.severity === 'error').length;
  const warnings = issues.filter(i => i.severity === 'warning').length;
  const lostFields = issues.filter(i => ['field_lost_after_save', 'image_missing_after_save', 'frame_missing_after_load'].includes(i.code)).length;
  const savePayloadMismatches = issues.filter(i => i.code === 'field_missing_in_payload').length;

  return {
    status: failures > 0 ? 'fail' : warnings > 0 ? 'warning' : 'pass',
    checkedAt: Date.now(),
    summary: {
      checkedPaths,
      failures,
      warnings,
      lostFields,
      viewerMismatches,
      savePayloadMismatches,
      normalizationMismatches: 0,
    },
    issues,
    viewerCheckPartial,
  };
}
