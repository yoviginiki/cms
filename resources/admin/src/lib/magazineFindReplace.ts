// ═══════════════════════════════════════════════════════════════════════════
// Find & Replace (W3): HTML-safe text search across all text-bearing frames.
// Matching and replacing walk TEXT NODES via DOMParser — markup is never
// regex-mangled. Known v1 limits: a match can't span a styling boundary
// (<b>wo</b>rd) or a thread-slice boundary between frames.
// ═══════════════════════════════════════════════════════════════════════════
import type { MagPageData } from '@/types/magazine';

export interface FindMatch {
  pageNumber: number;
  elementId: string;
  /** occurrence index within THIS element (0-based) */
  occurrence: number;
  preview: string;
  /** the match straddles a thread-slice boundary — jump-to only, no replace */
  crossSlice?: boolean;
}

export interface FindOptions {
  matchCase?: boolean;
}

const SEARCHABLE = new Set([
  'text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame',
  'footnote_frame', 'marginalia_frame', 'quote',
]);

const parseBody = (html: string): HTMLElement => {
  const doc = new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html');
  return doc.body;
};

const textNodesOf = (root: HTMLElement): Text[] => {
  const out: Text[] = [];
  const walk = (n: Node) => {
    if (n.nodeType === Node.TEXT_NODE) out.push(n as Text);
    else n.childNodes.forEach(walk);
  };
  walk(root);
  return out;
};

function countInText(text: string, query: string, matchCase: boolean): number {
  if (!query) return 0;
  const h = matchCase ? text : text.toLowerCase();
  const q = matchCase ? query : query.toLowerCase();
  let n = 0;
  let i = h.indexOf(q);
  while (i !== -1) {
    n++;
    i = h.indexOf(q, i + q.length);
  }
  return n;
}

/** all matches across content pages, in reading order */
export function findMatches(pages: MagPageData[], query: string, opts: FindOptions = {}): FindMatch[] {
  if (!query) return [];
  const matchCase = !!opts.matchCase;
  const out: FindMatch[] = [];
  const content = [...pages].filter((p) => !p.isMaster).sort((a, b) => a.pageNumber - b.pageNumber);
  for (const p of content) {
    const els = [...p.elements].sort((a, b) => a.y - b.y || a.x - b.x);
    for (const e of els) {
      if (!SEARCHABLE.has(e.type)) continue;
      const html = String((e.data as any)?.content || '');
      if (!html) continue;
      const body = parseBody(html);
      // per-text-node counting so occurrences align with replaceInHtml
      let occ = 0;
      for (const tn of textNodesOf(body)) {
        const t = tn.textContent || '';
        const count = countInText(t, query, matchCase);
        for (let k = 0; k < count; k++) {
          const h = matchCase ? t : t.toLowerCase();
          const q = matchCase ? query : query.toLowerCase();
          let idx = -1;
          for (let j = 0; j <= k; j++) idx = h.indexOf(q, idx + 1);
          const start = Math.max(0, idx - 24);
          out.push({
            pageNumber: p.pageNumber,
            elementId: e.id,
            occurrence: occ++,
            preview: (start > 0 ? '…' : '') + t.slice(start, idx + query.length + 24).trim() + '…',
          });
        }
      }
    }
  }
  // cross-slice detection: matches that straddle the seam between two
  // frames of a thread are findable (jump-to) though not replaceable
  const threads = new Map<string, Array<{ el: any; page: any }>>();
  for (const p of content) {
    for (const e of p.elements) {
      if ((e as any).threadId && SEARCHABLE.has(e.type)) {
        if (!threads.has((e as any).threadId)) threads.set((e as any).threadId, []);
        threads.get((e as any).threadId)!.push({ el: e, page: p });
      }
    }
  }
  const q = matchCase ? query : query.toLowerCase();
  for (const chain of threads.values()) {
    chain.sort((a, b) => ((a.el.threadOrder ?? 0) - (b.el.threadOrder ?? 0)));
    const texts = chain.map((c) => parseBody(String((c.el.data as any)?.content || '')).textContent || '');
    for (let i = 0; i < texts.length - 1; i++) {
      // window across the seam only — matches fully inside a slice are
      // already reported above
      const tailLen = Math.min(texts[i].length, query.length - 1 + 24);
      const headLen = Math.min(texts[i + 1].length, query.length - 1 + 24);
      const seam = texts[i].slice(-tailLen) + ' ' + texts[i + 1].slice(0, headLen);
      const hay = matchCase ? seam : seam.toLowerCase();
      let idx = hay.indexOf(q);
      while (idx !== -1) {
        const crossesSeam = idx < tailLen && idx + q.length > tailLen;
        if (crossesSeam) {
          out.push({
            pageNumber: chain[i].page.pageNumber,
            elementId: chain[i].el.id,
            occurrence: -1,
            preview: '…' + seam.slice(Math.max(0, idx - 16), idx + query.length + 16).trim() + '… (spans frames)',
            crossSlice: true,
          });
        }
        idx = hay.indexOf(q, idx + 1);
      }
    }
  }
  return out;
}

/**
 * Replace within one element's HTML. occurrence: 0-based nth match to
 * replace; omit to replace ALL. Returns new html + how many were replaced.
 */
export function replaceInHtml(
  html: string,
  query: string,
  replacement: string,
  opts: FindOptions & { occurrence?: number } = {},
): { html: string; replaced: number } {
  if (!query) return { html, replaced: 0 };
  const matchCase = !!opts.matchCase;
  const only = opts.occurrence;
  const body = parseBody(html);
  let seen = 0;
  let replaced = 0;
  for (const tn of textNodesOf(body)) {
    const t = tn.textContent || '';
    const h = matchCase ? t : t.toLowerCase();
    const q = matchCase ? query : query.toLowerCase();
    let out = '';
    let pos = 0;
    let i = h.indexOf(q);
    while (i !== -1) {
      const take = only === undefined || seen === only;
      out += t.slice(pos, i) + (take ? replacement : t.slice(i, i + query.length));
      if (take) replaced++;
      seen++;
      pos = i + query.length;
      i = h.indexOf(q, pos);
    }
    out += t.slice(pos);
    if (out !== t) tn.textContent = out;
  }
  return { html: body.innerHTML, replaced };
}
