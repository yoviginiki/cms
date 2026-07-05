// ═══════════════════════════════════════════════════════════════════════════
// Document-level flow orchestration — the ONE reflow path (Session C Phase 2).
//
// Every trigger (typing/blur, paste, frame move/resize, column change, style
// change, threading op, page op, save) funnels into runDocumentFlow via
// magazineStore.runFlow(). React components only RENDER the slices it writes.
//
// Data model: a thread's canonical text is its STORY (one HTML string), held
// in the store's `stories` map and lazily derived from the frames' persisted
// slices (joinSlices) when absent — so the persisted format stays the same
// per-frame slices the published viewers already render (backward compatible).
//
// Auto-pagination: overflow creates a continuation frame on the next content
// page (creating the page if needed), inheriting the source frame's column
// settings — then re-flows. Shrink removes only frames/pages this engine
// created (data._autoFlow / page._autoCreated), never manual ones.
// ═══════════════════════════════════════════════════════════════════════════

import type { MagElement, MagPageData } from '@/types/magazine';
import { flowText } from './engine';
import type { FlowExclusion, FlowFrameSpec } from './types';
import {
  parseStory,
  toFlowBlocks,
  sliceStory,
  joinSlices,
  storyWords,
  htmlWords,
  flowHash,
  type StoryBlock,
} from './content';
import { buildWordPrefix } from './types';
import { DomMeasurer } from './domMeasurer';

export const FLOW_TEXT_TYPES = new Set([
  'text_frame',
  'headline_frame',
  'pullquote_frame',
  'caption_frame',
  'footnote_frame',
  'marginalia_frame',
]);

const IMAGE_TYPES = new Set([
  'image_frame', 'circular_image', 'polygon_image', 'fullbleed_image',
  'gallery_frame', 'background_image',
]);

const MAX_CHAIN_FRAMES = 100;
const MAX_FLOW_PASSES = 60;

export interface DocumentFlowOptions {
  /** allow creating/removing auto frames + pages */
  paginate: boolean;
}

export interface DocumentFlowResult {
  pages: MagPageData[];
  stories: Record<string, string>;
  oversetThreads: Record<string, boolean>;
  /** frames/pages were created or removed (callers snapshot undo when true) */
  structuralChange: boolean;
  changed: boolean;
}

interface ThreadFrameRef {
  pageNumber: number;
  elementId: string;
}

function getInset(el: MagElement): { top: number; right: number; bottom: number; left: number } {
  const inset = (el.data as any)?.textInset;
  return inset && typeof inset === 'object'
    ? { top: inset.top ?? 8, right: inset.right ?? 8, bottom: inset.bottom ?? 8, left: inset.left ?? 8 }
    : { top: 8, right: 8, bottom: 8, left: 8 };
}

/** build the engine frame spec (text-area local) incl. runaround exclusions */
function elToSpec(el: MagElement, page: MagPageData): FlowFrameSpec {
  const data = el.data as Record<string, any>;
  const inset = getInset(el);
  const areaX = el.x + inset.left;
  const areaY = el.y + inset.top;
  const width = Math.max(0, el.width - inset.left - inset.right);
  const height = Math.max(0, el.height - inset.top - inset.bottom);

  const exclusions: FlowExclusion[] = [];
  for (const other of page.elements) {
    if (other.id === el.id || !other.visible) continue;
    const wrap = other.textWrap;
    if (!wrap || wrap.type === 'none') continue;
    const mode: FlowExclusion['mode'] = wrap.type === 'jump' ? 'jump' : 'wrap';
    const off = wrap.offset || { top: 0, right: 0, bottom: 0, left: 0 };
    const margin = Math.max(0, off.top ?? 0, off.right ?? 0, off.bottom ?? 0, off.left ?? 0);
    const bands = wrap.type === 'object-shape' ? (wrap.customPath as any)?.bands : null;
    const pushEx = (ex: FlowExclusion) => {
      const R = { x: ex.x - ex.margin, y: ex.y - ex.margin, w: ex.w + 2 * ex.margin, h: ex.h + 2 * ex.margin };
      if (R.x < width && R.x + R.w > 0 && R.y < height && R.y + R.h > 0) exclusions.push(ex);
    };
    if (Array.isArray(bands) && bands.length) {
      // contour ([pro]): one thin rect per traced alpha band — the carving
      // loop composes them into a shaped hole
      for (const b of bands) {
        pushEx({
          x: other.x + b.x0 - areaX,
          y: other.y + b.y0 - areaY,
          w: Math.max(0, b.x1 - b.x0),
          h: Math.max(0, b.y1 - b.y0),
          margin,
          mode,
        });
      }
    } else {
      pushEx({
        x: other.x - areaX,
        y: other.y - areaY,
        w: other.width,
        h: other.height,
        margin,
        mode,
      });
    }
  }
  return { id: el.id, width, height, columns: data.columnsInFrame || 1, columnGap: data.columnGap || 12, exclusions };
}

function findEl(pages: MagPageData[], ref: ThreadFrameRef): MagElement | undefined {
  const page = pages.find((p) => p.pageNumber === ref.pageNumber);
  return page?.elements.find((e) => e.id === ref.elementId);
}

function setElData(pages: MagPageData[], elementId: string, patch: Partial<MagElement>): MagPageData[] {
  return pages.map((p) =>
    p.elements.some((e) => e.id === elementId)
      ? { ...p, elements: p.elements.map((e) => (e.id === elementId ? { ...e, ...patch } : e)) }
      : p,
  );
}

/** create a continuation frame after `lastRef`, creating a page when needed */
function addContinuation(
  pages: MagPageData[],
  lastRef: ThreadFrameRef,
  threadId: string,
  nextOrder: number,
): { pages: MagPageData[]; ref: ThreadFrameRef } | null {
  const lastEl = findEl(pages, lastRef);
  const sourcePage = pages.find((p) => p.pageNumber === lastRef.pageNumber);
  if (!lastEl || !sourcePage) return null;

  const contentPages = pages.filter((p) => !p.isMaster).sort((a, b) => a.pageNumber - b.pageNumber);
  const idx = contentPages.findIndex((p) => p.pageNumber === sourcePage.pageNumber);
  let targetPage = idx >= 0 && idx < contentPages.length - 1 ? contentPages[idx + 1] : null;
  let out = pages;

  if (!targetPage) {
    const nextPageNumber = sourcePage.pageNumber + 1;
    targetPage = {
      id: crypto.randomUUID(),
      pageNumber: nextPageNumber,
      pageSize: { ...sourcePage.pageSize },
      margins: { ...sourcePage.margins },
      bleed: { ...sourcePage.bleed },
      columns: { ...sourcePage.columns },
      baselineGrid: { ...sourcePage.baselineGrid },
      isMaster: false,
      masterPageId: sourcePage.masterPageId,
      spreadWith: null,
      backgroundColor: sourcePage.backgroundColor,
      backgroundAssetId: null,
      elements: [],
    };
    (targetPage as any)._autoCreated = true;
    out = out.map((p) => (p.pageNumber >= nextPageNumber && !p.isMaster ? { ...p, pageNumber: p.pageNumber + 1 } : p));
    out = [...out, targetPage];
  }

  // placement: full text column between margins, below any images (the
  // image-avoidance behavior the audit flagged as worth preserving)
  const mg = targetPage.margins || { top: 36, right: 36, bottom: 36, left: 36 };
  const pw = targetPage.pageSize?.width || 595;
  const ph = targetPage.pageSize?.height || 842;
  let contY = mg.top || 36;
  const contX = mg.left || 36;
  const contW = pw - (mg.left || 36) - (mg.right || 36);
  for (const img of targetPage.elements) {
    if (!IMAGE_TYPES.has(img.type) || !img.visible) continue;
    const bottom = img.y + img.height + 8;
    if (contY < bottom && contX < img.x + img.width && contX + contW > img.x) contY = bottom;
  }
  const contH = Math.max(100, ph - contY - (mg.bottom || 36));
  const maxZ = Math.max(0, ...targetPage.elements.map((e) => e.zIndex));

  const continuation: MagElement = {
    ...structuredClone(lastEl),
    id: crypto.randomUUID(),
    pageNumber: targetPage.pageNumber,
    x: contX,
    y: contY,
    width: contW,
    height: contH,
    threadId,
    threadOrder: nextOrder,
    zIndex: maxZ + 1,
    data: { ...lastEl.data, content: '', _autoFlow: true },
  };

  out = out.map((p) =>
    p.pageNumber === targetPage!.pageNumber && !p.isMaster
      ? { ...p, elements: [...p.elements, continuation] }
      : p,
  );
  return { pages: out, ref: { pageNumber: targetPage.pageNumber, elementId: continuation.id } };
}

/** thread map: threadId -> ordered frame refs */
export function collectThreads(pages: MagPageData[]): Map<string, ThreadFrameRef[]> {
  const map = new Map<string, Array<{ ref: ThreadFrameRef; order: number }>>();
  for (const p of pages) {
    if (p.isMaster) continue;
    for (const e of p.elements) {
      if (!e.threadId || !FLOW_TEXT_TYPES.has(e.type)) continue;
      if (!map.has(e.threadId)) map.set(e.threadId, []);
      map.get(e.threadId)!.push({ ref: { pageNumber: p.pageNumber, elementId: e.id }, order: e.threadOrder ?? 0 });
    }
  }
  const out = new Map<string, ThreadFrameRef[]>();
  for (const [tid, list] of [...map.entries()].sort(([a], [b]) => a.localeCompare(b))) {
    list.sort((a, b) => a.order - b.order || a.ref.pageNumber - b.ref.pageNumber);
    out.set(tid, list.map((x) => x.ref));
  }
  return out;
}

export function deriveStory(pages: MagPageData[], refs: ThreadFrameRef[]): string {
  return joinSlices(refs.map((r) => String((findEl(pages, r)?.data as any)?.content || '')));
}

function flowOneThread(
  pagesIn: MagPageData[],
  tid: string,
  refsIn: ThreadFrameRef[],
  story: string,
  opts: DocumentFlowOptions,
): { pages: MagPageData[]; overset: boolean; structural: boolean; changed: boolean } {
  let pages = pagesIn;
  let refs = [...refsIn];
  let structural = false;
  let changed = false;

  const storyBlocks: StoryBlock[] = parseStory(story);
  const flowBlocks = toFlowBlocks(storyBlocks);
  const totalWords = buildWordPrefix(flowBlocks)[flowBlocks.length];
  const firstEl = findEl(pages, refs[0]);
  if (!firstEl) return { pages, overset: false, structural, changed };

  const measurer = new DomMeasurer(storyBlocks, firstEl.typography);
  let res: ReturnType<typeof flowText> | null = null;
  try {
    for (let pass = 0; pass < MAX_FLOW_PASSES; pass++) {
      const specs: FlowFrameSpec[] = [];
      for (const r of refs) {
        const el = findEl(pages, r);
        const page = pages.find((p) => p.pageNumber === r.pageNumber);
        if (el && page) specs.push(elToSpec(el, page));
      }
      if (specs.length === 0) break;
      res = flowText(flowBlocks, specs, measurer);
      if (res.overflow && opts.paginate && refs.length < MAX_CHAIN_FRAMES) {
        // batch continuation creation: estimate how many frames the remainder
        // needs from this pass's consumption rate, so a 10k-word paste
        // converges in ~2-3 passes instead of one full re-flow per new page
        const consumed = res.overflow.fromWord;
        const filled = res.frames.filter((f) => f.slice.to > f.slice.from).length;
        const perFrame = Math.max(40, Math.floor(consumed / Math.max(1, filled)));
        const remaining = totalWords - consumed;
        const need = Math.min(
          20,
          MAX_CHAIN_FRAMES - refs.length,
          Math.max(1, Math.ceil(remaining / perFrame)),
        );
        let addedAny = false;
        for (let n = 0; n < need; n++) {
          const lastRef = refs[refs.length - 1];
          const lastEl = findEl(pages, lastRef);
          const added = addContinuation(pages, lastRef, tid, (lastEl?.threadOrder ?? refs.length - 1) + 1);
          if (!added) break;
          pages = added.pages;
          refs.push(added.ref);
          addedAny = true;
        }
        if (!addedAny) break;
        structural = true;
        changed = true;
        continue;
      }
      break;
    }
  } finally {
    measurer.dispose();
  }
  if (!res) return { pages, overset: false, structural, changed };

  // ── write slices (losslessness rule: the LAST frame absorbs any overset
  //    remainder so no content is ever dropped — it clips + badges instead)
  const consumedByFrame = res.frames;
  for (let i = 0; i < refs.length && i < consumedByFrame.length; i++) {
    const placement = consumedByFrame[i];
    const isLast = i === Math.min(refs.length, consumedByFrame.length) - 1;
    const to = isLast && res.overflow ? totalWords : placement.slice.to;
    const html = sliceStory(storyBlocks, placement.slice.from, to);
    const el = findEl(pages, refs[i]);
    if (!el) continue;
    const data = el.data as Record<string, any>;
    const hash = flowHash([story.length, placement.slice.from, to, el.width, el.height, data.columnsInFrame, data.columnGap]);
    if (data.content !== html || data._flowHash !== hash || el.threadId !== tid) {
      pages = setElData(pages, el.id, {
        threadId: tid,
        threadOrder: el.threadOrder ?? i,
        data: { ...data, content: html, _flowHash: hash },
      });
      changed = true;
    }
  }

  // ── shrink: drop trailing AUTO-created frames that received nothing
  if (opts.paginate && !res.overflow) {
    for (let i = refs.length - 1; i > 0; i--) {
      const el = findEl(pages, refs[i]);
      if (!el) continue;
      const placement = consumedByFrame[i];
      const empty = !placement || placement.slice.to === placement.slice.from;
      if (empty && (el.data as any)?._autoFlow) {
        pages = pages.map((p) =>
          p.pageNumber === refs[i].pageNumber
            ? { ...p, elements: p.elements.filter((e) => e.id !== el.id) }
            : p,
        );
        refs.splice(i, 1);
        structural = true;
        changed = true;
      } else {
        break; // only trailing auto frames are removable
      }
    }
  }

  // ── dev losslessness assertion (the pasted-text-loss bug class, pinned)
  if ((import.meta as any).env?.DEV) {
    const placed = refs
      .map((r) => htmlWords(String((findEl(pages, r)?.data as any)?.content || '')))
      .flat();
    const src = storyWords(storyBlocks);
    if (placed.join('') !== src.join('')) {
      // eslint-disable-next-line no-console
      console.error('[flow] LOSSLESSNESS FAIL', { tid, src: src.length, placed: placed.length });
    }
  }

  return { pages, overset: !!res.overflow, structural, changed };
}

/** does a single unthreaded frame overflow? (promotion check for pagination) */
function frameOverflows(el: MagElement, page: MagPageData): boolean {
  const html = String((el.data as any)?.content || '');
  if (!html || html.length < 10) return false;
  const storyBlocks = parseStory(html);
  if (storyBlocks.length === 0) return false;
  const measurer = new DomMeasurer(storyBlocks, el.typography);
  try {
    const res = flowText(toFlowBlocks(storyBlocks), [elToSpec(el, page)], measurer);
    return !!res.overflow;
  } finally {
    measurer.dispose();
  }
}

export function runDocumentFlow(
  pagesIn: MagPageData[],
  storiesIn: Record<string, string>,
  opts: DocumentFlowOptions,
): DocumentFlowResult {
  if (typeof document === 'undefined') {
    return { pages: pagesIn, stories: storiesIn, oversetThreads: {}, structuralChange: false, changed: false };
  }
  let pages = pagesIn.map((p) => ({ ...p, elements: [...p.elements] }));
  const stories: Record<string, string> = { ...storiesIn };
  const oversetThreads: Record<string, boolean> = {};
  let structuralChange = false;
  let changed = false;

  // 1. promote unthreaded overflowing text frames into threads (pagination)
  if (opts.paginate) {
    for (const page of pages) {
      if (page.isMaster) continue;
      for (const el of page.elements) {
        if (!FLOW_TEXT_TYPES.has(el.type) || el.threadId || !el.visible || el.onMaster) continue;
        // only the main body type auto-paginates; captions/pullquotes just badge
        if (el.type !== 'text_frame') continue;
        if (frameOverflows(el, page)) {
          const tid = crypto.randomUUID();
          pages = setElData(pages, el.id, { threadId: tid, threadOrder: 0 });
          changed = true;
        }
      }
    }
  }

  // 2. flow every thread from its canonical story
  const threads = collectThreads(pages);
  for (const [tid, refs] of threads) {
    if (refs.length === 0) continue;
    const story = stories[tid] ?? deriveStory(pages, refs);
    const result = flowOneThread(pages, tid, refs, story, opts);
    pages = result.pages;
    stories[tid] = story;
    oversetThreads[tid] = result.overset;
    structuralChange = structuralChange || result.structural;
    changed = changed || result.changed;
  }

  // 2b. autoSize 'grow-height' (W1-6): unthreaded text frames grow to fit
  //     their content — measured with the SAME DomMeasurer as the engine
  for (const page of pages) {
    if (page.isMaster) continue;
    for (const el of page.elements) {
      if (!FLOW_TEXT_TYPES.has(el.type) || el.threadId || !el.visible) continue;
      if ((el.data as any)?.autoSize !== 'grow-height') continue;
      const html = String((el.data as any)?.content || '');
      if (!html) continue;
      const storyBlocks = parseStory(html);
      if (storyBlocks.length === 0) continue;
      const flowBlocks = toFlowBlocks(storyBlocks);
      const total = buildWordPrefix(flowBlocks)[flowBlocks.length];
      const inset = getInset(el);
      const cols = Math.max(1, (el.data as any)?.columnsInFrame || 1);
      const gap = (el.data as any)?.columnGap || 12;
      const colW = (el.width - inset.left - inset.right - gap * (cols - 1)) / cols;
      if (colW < 40) continue;
      const measurer = new DomMeasurer(storyBlocks, el.typography);
      let contentH = 0;
      try {
        measurer.openWindow(flowBlocks, 0, total, colW);
        contentH = measurer.bottomAt(total);
      } finally {
        measurer.dispose();
      }
      if (contentH <= 0) continue; // jsdom/tests: no layout, leave untouched
      const needed = Math.ceil((cols > 1 ? contentH / cols : contentH) + inset.top + inset.bottom) + 2;
      const maxH = (page.pageSize?.height || 842) - el.y - 8;
      const target = Math.max(24, Math.min(needed, maxH));
      if (Math.abs(target - el.height) > 2) {
        pages = setElData(pages, el.id, { height: target });
        changed = true;
      }
    }
  }

  // 3. shrink: remove auto-created pages that ended up empty
  if (opts.paginate) {
    const removable = pages.filter((p) => (p as any)._autoCreated && !p.isMaster && p.elements.length === 0);
    if (removable.length > 0) {
      const removeIds = new Set(removable.map((p) => p.id));
      pages = pages.filter((p) => !removeIds.has(p.id));
      // renumber content pages sequentially; masters keep their numbers
      const content = pages.filter((p) => !p.isMaster).sort((a, b) => a.pageNumber - b.pageNumber);
      content.forEach((p, i) => {
        const target = i + 1;
        if (p.pageNumber !== target) {
          p.pageNumber = target;
          p.elements = p.elements.map((e) => ({ ...e, pageNumber: target }));
          changed = true;
        }
      });
      structuralChange = true;
      changed = true;
    }
  }

  return { pages, stories, oversetThreads, structuralChange, changed };
}
