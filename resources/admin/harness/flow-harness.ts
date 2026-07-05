// ═══════════════════════════════════════════════════════════════════════════
// Flow-engine browser harness (Session C) — exercises runDocumentFlow with the
// REAL DomMeasurer in headless Chromium (jsdom cannot do layout).
//
// Build:  npx vite build --config vite.harness.config.ts
// Run:    node harness/run-flow-harness.mjs
// ═══════════════════════════════════════════════════════════════════════════

import { runDocumentFlow } from '../src/engine/flow/storeFlow';
import { htmlWords } from '../src/engine/flow/content';
import type { MagPageData, MagElement } from '../src/types/magazine';
import {
  DEFAULT_ELEMENT_STYLE,
  DEFAULT_TEXT_WRAP,
  DEFAULT_TYPOGRAPHY,
} from '../src/types/magazine';

function makePage(pageNumber: number): MagPageData {
  return {
    id: crypto.randomUUID(),
    pageNumber,
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
  };
}

function makeTextFrame(html: string, pageNumber: number, columns = 2): MagElement {
  return {
    id: crypto.randomUUID(),
    type: 'text_frame',
    name: 'Body',
    data: {
      content: html,
      overflow: 'hidden',
      autoSize: 'none',
      columnsInFrame: columns,
      columnGap: 14,
      columnFill: 'auto',
      columnRule: false,
      textInset: { top: 8, right: 8, bottom: 8, left: 8 },
      verticalAlign: 'top',
    },
    x: 36, y: 36, width: 523, height: 770,
    rotation: 0, scaleX: 1, scaleY: 1, zIndex: 1,
    locked: false, visible: true, layerName: null,
    style: structuredClone(DEFAULT_ELEMENT_STYLE),
    typography: { ...DEFAULT_TYPOGRAPHY },
    textWrap: { ...DEFAULT_TEXT_WRAP },
    threadId: null, threadOrder: null,
    pageNumber, onMaster: false,
    positionMode: 'free', spanMode: 'page',
    parentId: null, children: [],
    responsiveOverrides: {},
  };
}

/** deterministic pseudo-article: paragraphs + h2 every 8 blocks + quote every 12 */
function genHtml(wordTarget: number): string {
  const bank = ['substrate', 'vermilion', 'signature', 'baseline', 'gutter', 'colophon', 'margin', 'quire', 'platen', 'deckle', 'registration', 'impression'];
  let words = 0;
  let i = 0;
  let html = '';
  while (words < wordTarget) {
    i++;
    if (i % 8 === 0) {
      html += `<h2>Heading number ${i}</h2>`;
      words += 3;
    } else if (i % 12 === 0) {
      html += `<blockquote>The grid is the drum the page keeps time on, said compositor ${i}.</blockquote>`;
      words += 13;
    } else {
      const w: string[] = [];
      for (let j = 0; j < 55; j++) w.push(bank[(i * 31 + j * 7) % bank.length]);
      html += `<p>Paragraph ${i}: ${w.join(' ')}.</p>`;
      words += 57;
    }
  }
  return html;
}

interface HarnessSummary {
  ms: number;
  pages: number;
  frames: number;
  srcWords: number;
  placedWords: number;
  lossless: boolean;
  overset: boolean;
}

function summarize(
  res: ReturnType<typeof runDocumentFlow>,
  srcHtml: string,
  ms: number,
): HarnessSummary {
  const placed = res.pages
    .flatMap((p) => p.elements)
    .filter((e) => e.threadId)
    .sort((a, b) => (a.threadOrder ?? 0) - (b.threadOrder ?? 0));
  const placedWords = placed.flatMap((e) => htmlWords(String((e.data as any).content || '')));
  const srcWords = htmlWords(srcHtml);
  return {
    ms: Math.round(ms),
    pages: res.pages.length,
    frames: placed.length,
    srcWords: srcWords.length,
    placedWords: placedWords.length,
    lossless: placedWords.join('') === srcWords.join(''),
    overset: Object.values(res.oversetThreads).some(Boolean),
  };
}

(window as any).runFlowHarness = (wordTarget = 5000): HarnessSummary => {
  const html = genHtml(wordTarget);
  const page = makePage(1);
  page.elements.push(makeTextFrame(html, 1));
  const t0 = performance.now();
  const res = runDocumentFlow([page], {}, { paginate: true });
  return summarize(res, html, performance.now() - t0);
};

// Session F repro: tiny 300x150 2-col starting frame, placeholder para +
// <h1> + big story — mirrors the acceptance E2E's large-paste-into-default-
// frame path that lost the h1 + first words of p0.
(window as any).runAcceptanceRepro = (): HarnessSummary & { hasFirst: boolean; hasLast: boolean; headOfSlice1: string } => {
  const html = '<p>Type your text here</p><h1>ALPHAOPEN Acceptance Story</h1>' +
    Array.from({ length: 170 }, (_, i) => `<p>p${i} ${'substrate colophon vermilion quire deckle gutter impression margin platen baseline registration signature '.repeat(5)}</p>`).join('') +
    '<p>closing paragraph OMEGAEND</p>';
  const page = makePage(1);
  const frame = makeTextFrame(html, 1);
  frame.x = 60; frame.y = 60; frame.width = 300; frame.height = 150;
  page.elements.push(frame);
  const t0 = performance.now();
  const res = runDocumentFlow([page], {}, { paginate: true });
  const base = summarize(res, html, performance.now() - t0);
  const allHtml = res.pages.flatMap((p) => p.elements).map((e) => String((e.data as any)?.content || '')).join(' ');
  const first = res.pages.flatMap((p) => p.elements).find((e) => (e as any).threadOrder === 0 || (e as any).threadId);
  return {
    ...base,
    hasFirst: allHtml.includes('ALPHAOPEN'),
    hasLast: allHtml.includes('OMEGAEND'),
    headOfSlice1: String((first?.data as any)?.content || '').replace(/<[^>]+>/g, ' ').trim().slice(0, 90),
  };
};

// contour wrap ([pro]): triangle-silhouette bands must carve losslessly
(window as any).runContourHarness = (): HarnessSummary => {
  const html = genHtml(3000);
  const page = makePage(1);
  const tf = makeTextFrame(html, 1, 1);
  page.elements.push(tf);
  const img: MagElement = { ...makeTextFrame('', 1, 1), id: crypto.randomUUID(), type: 'image_frame' };
  img.x = 320; img.y = 120; img.width = 200; img.height = 240;
  img.data = { src: '' } as any;
  img.textWrap = {
    type: 'object-shape',
    offset: { top: 6, right: 6, bottom: 6, left: 6 },
    side: 'both',
    invert: false,
    customPath: { bands: Array.from({ length: 12 }, (_, i) => ({
      y0: i * 20, y1: (i + 1) * 20, x0: 200 - (i + 1) * (200 / 12), x1: 200,
    })) } as any,
  };
  page.elements.push(img);
  const t0 = performance.now();
  const res = runDocumentFlow([page], {}, { paginate: true });
  return summarize(res, html, performance.now() - t0);
};

(window as any).runShrinkHarness = (): {
  grow: HarnessSummary;
  shrink: HarnessSummary;
  pagesAfterGrow: number;
  pagesAfterShrink: number;
} => {
  const bigHtml = genHtml(4000);
  const page = makePage(1);
  page.elements.push(makeTextFrame(bigHtml, 1));
  const t0 = performance.now();
  const res1 = runDocumentFlow([page], {}, { paginate: true });
  const grow = summarize(res1, bigHtml, performance.now() - t0);

  // simulate replacing the story with a short one (the edit path invalidates
  // the story cache and re-derives; here we hand the new story directly)
  const tid = Object.keys(res1.oversetThreads)[0]
    || res1.pages.flatMap((p) => p.elements).find((e) => e.threadId)?.threadId
    || '';
  const shortHtml = '<p>Just a short story now with only a handful of words left.</p>';
  const t1 = performance.now();
  const res2 = runDocumentFlow(res1.pages, { [tid]: shortHtml }, { paginate: true });
  const shrink = summarize(res2, shortHtml, performance.now() - t1);
  return { grow, shrink, pagesAfterGrow: res1.pages.length, pagesAfterShrink: res2.pages.length };
};

(window as any).runExclusionHarness = (): { boxes: number; overlaps: number } => {
  // one frame + a wrap exclusion element; assert no placed slice box logic is
  // needed here — we check via the engine's own placements indirectly by
  // ensuring flow succeeds and content is lossless with the exclusion present
  const html = genHtml(800);
  const page = makePage(1);
  const frame = makeTextFrame(html, 1);
  page.elements.push(frame);
  const pull: MagElement = {
    ...makeTextFrame('<p>PULL QUOTE</p>', 1, 1),
    id: crypto.randomUUID(),
    type: 'pullquote_frame',
    x: 150, y: 300, width: 280, height: 120,
    textWrap: { type: 'bounding-box', offset: { top: 12, right: 12, bottom: 12, left: 12 }, side: 'both', customPath: null, invert: false },
  };
  page.elements.push(pull);
  const res = runDocumentFlow([page], {}, { paginate: true });
  const placed = res.pages.flatMap((p) => p.elements).filter((e) => e.threadId);
  const placedWords = placed.flatMap((e) => htmlWords(String((e.data as any).content || '')));
  return { boxes: placed.length, overlaps: placedWords.length === htmlWords(html).length ? 0 : 1 };
};
