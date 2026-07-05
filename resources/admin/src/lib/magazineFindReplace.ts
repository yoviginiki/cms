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
