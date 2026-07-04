// ═══════════════════════════════════════════════════════════════════════════
// Story content model — HTML ⇄ flow blocks ⇄ per-frame slices.
//
// A THREAD's canonical text is its STORY: one HTML string. The engine flows
// the story; this module slices it back into per-frame HTML (the persisted
// render contract the viewers already consume).
//
// LOSSLESSNESS RULES (pins audit break point E — pasted text loss):
//  - bare text nodes at the root are wrapped in <p>, never dropped
//  - partial blocks are sliced with DOM Ranges (inline markup survives)
//  - concatWords(slices) === concatWords(story) is asserted by callers in dev
// ═══════════════════════════════════════════════════════════════════════════

import type { FlowBlock, FlowBlockKind } from './types';
import { buildWordPrefix, blockAtWord } from './types';

const HEADING_TAGS = new Set(['H1', 'H2', 'H3', 'H4', 'H5', 'H6']);
const ATOMIC_TAGS = new Set(['UL', 'OL', 'TABLE', 'FIGURE', 'IMG', 'HR', 'IFRAME', 'VIDEO', 'PRE']);

export interface StoryBlock {
  el: Element;
  kind: FlowBlockKind;
  words: string[];
  /** raw char offsets of each word inside el.textContent */
  wordOffsets: Array<{ start: number; end: number }>;
  keepWithNext: boolean;
}

function makeBlock(el: Element): StoryBlock {
  const text = el.textContent || '';
  const words: string[] = [];
  const wordOffsets: Array<{ start: number; end: number }> = [];
  const re = /\S+/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(text))) {
    words.push(m[0]);
    wordOffsets.push({ start: m.index, end: m.index + m[0].length });
  }
  const tag = el.tagName;
  const kind: FlowBlockKind = HEADING_TAGS.has(tag)
    ? 'heading'
    : tag === 'BLOCKQUOTE'
      ? 'quote'
      : ATOMIC_TAGS.has(tag)
        ? 'atomic'
        : 'paragraph';
  return { el, kind, words, wordOffsets, keepWithNext: kind === 'heading' };
}

/** parse a story HTML string into blocks; bare root text is wrapped, not dropped */
export function parseStory(html: string): StoryBlock[] {
  const doc = new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html');
  let root: Element = doc.body;
  // unwrap legacy single-wrapper divs
  while (
    root.childNodes.length === 1 &&
    root.children.length === 1 &&
    root.children[0].tagName === 'DIV'
  ) {
    root = root.children[0];
  }
  const blocks: StoryBlock[] = [];
  for (const node of Array.from(root.childNodes)) {
    if (node.nodeType === Node.TEXT_NODE) {
      const t = node.textContent || '';
      if (t.trim()) {
        const p = doc.createElement('p');
        p.textContent = t;
        blocks.push(makeBlock(p));
      }
      continue;
    }
    if (node.nodeType === Node.ELEMENT_NODE) {
      const el = node as Element;
      if (el.tagName === 'BR') continue;
      blocks.push(makeBlock(el));
    }
  }
  return blocks;
}

export function toFlowBlocks(story: StoryBlock[]): FlowBlock[] {
  return story.map((b, i) => {
    const charPrefix = [0];
    for (const w of b.words) charPrefix.push(charPrefix[charPrefix.length - 1] + w.length);
    return {
      kind: b.kind,
      words: b.words,
      charPrefix,
      keepWithNext: b.keepWithNext,
      sourceIndex: i,
    };
  });
}

/** find (textNode, offset) for a raw char position inside an element */
function pointAtChar(rootEl: Node, charPos: number): { node: Node; offset: number } {
  const doc = rootEl.ownerDocument as Document;
  const walker = doc.createTreeWalker(rootEl, NodeFilter.SHOW_TEXT);
  let acc = 0;
  let node = walker.nextNode();
  let last: Node | null = null;
  while (node) {
    const len = node.textContent?.length ?? 0;
    if (charPos <= acc + len) return { node, offset: charPos - acc };
    acc += len;
    last = node;
    node = walker.nextNode();
  }
  return last
    ? { node: last, offset: last.textContent?.length ?? 0 }
    : { node: rootEl, offset: 0 };
}

/** delete the char range [startCh, endCh) inside el, preserving inline structure */
function deleteCharRange(el: Element, startCh: number, endCh: number): void {
  if (endCh <= startCh) return;
  const doc = el.ownerDocument as Document;
  const a = pointAtChar(el, startCh);
  const b = pointAtChar(el, endCh);
  const r = doc.createRange();
  r.setStart(a.node, a.offset);
  r.setEnd(b.node, b.offset);
  r.deleteContents();
}

/**
 * slice a word range of one block to an HTML string.
 * Continued fragments get margin/indent reset + data-flow-cont markers
 * (inline styles so the published sanitizers keep them).
 */
function sliceBlockHtml(block: StoryBlock, w0: number, w1: number): string {
  const n = block.words.length;
  if (n === 0 || (w0 <= 0 && w1 >= n)) return block.el.outerHTML;
  const clone = block.el.cloneNode(true) as HTMLElement;
  const textLen = (block.el.textContent || '').length;
  // trailing first so leading offsets stay valid
  if (w1 < n) deleteCharRange(clone, block.wordOffsets[w1 - 1].end, textLen);
  if (w0 > 0) deleteCharRange(clone, 0, block.wordOffsets[w0].start);
  const flags: string[] = [];
  if (w0 > 0) {
    flags.push('in');
    clone.style.marginTop = '0';
    clone.style.textIndent = '0';
  }
  if (w1 < n) {
    flags.push('out');
    clone.style.marginBottom = '0';
  }
  clone.setAttribute('data-flow-cont', flags.join('-')); // 'in' | 'out' | 'in-out'
  return clone.outerHTML;
}

/** slice the story to HTML for the global word range [fromWord, toWord) */
export function sliceStory(story: StoryBlock[], fromWord: number, toWord: number): string {
  if (toWord <= fromWord) return '';
  const flow = toFlowBlocks(story);
  const prefix = buildWordPrefix(flow);
  const a = blockAtWord(prefix, fromWord);
  const z = blockAtWord(prefix, toWord - 1);
  let html = '';
  for (let b = a.b; b <= z.b; b++) {
    const block = story[b];
    const n = block.words.length;
    const w0 = b === a.b ? a.w : 0;
    const w1 = b === z.b ? Math.min(n, z.w + 1) : n;
    if (n === 0) {
      // word-less atomic block (its single token is inside the range)
      html += block.el.outerHTML;
    } else {
      html += sliceBlockHtml(block, w0, w1);
    }
  }
  return html;
}

/** normalized word stream of a story (for losslessness assertions) */
export function storyWords(story: StoryBlock[]): string[] {
  return story.flatMap((b) => b.words);
}

/** normalized word stream of an HTML string */
export function htmlWords(html: string): string[] {
  return storyWords(parseStory(html));
}

/** derive a thread's story by concatenating its frames' slices in order */
export function joinSlices(slices: string[]): string {
  // merge adjacent continued fragments of the SAME block back together:
  // an element marked data-flow-cont="out" followed by data-flow-cont="in"
  // of the same tag is re-joined so re-flow does not accumulate split points.
  const doc = new DOMParser().parseFromString(`<body>${slices.join('')}</body>`, 'text/html');
  const root = doc.body;
  const contOf = (el: Element | null) => (el?.getAttribute('data-flow-cont') || '');
  const clearFlags = (el: HTMLElement) => {
    el.removeAttribute('data-flow-cont');
    el.style.removeProperty('margin-top');
    el.style.removeProperty('margin-bottom');
    el.style.removeProperty('text-indent');
    if (!el.getAttribute('style')) el.removeAttribute('style');
  };
  let node = root.firstElementChild;
  while (node) {
    const next = node.nextElementSibling;
    if (
      next &&
      contOf(node).includes('out') &&
      contOf(next).includes('in') &&
      node.tagName === next.tagName
    ) {
      const nextContinuesOut = contOf(next).includes('out');
      node.appendChild(doc.createTextNode(' '));
      while (next.firstChild) node.appendChild(next.firstChild);
      next.remove();
      if (nextContinuesOut) {
        node.setAttribute('data-flow-cont', contOf(node).includes('in') ? 'in-out' : 'out');
      } else if (contOf(node).includes('in')) {
        node.setAttribute('data-flow-cont', 'in');
        (node as HTMLElement).style.removeProperty('margin-bottom');
      } else {
        clearFlags(node as HTMLElement);
      }
      continue; // merged node may join with the following fragment too
    }
    if (node.hasAttribute('data-flow-cont')) clearFlags(node as HTMLElement);
    node = next;
  }
  return root.innerHTML;
}

/** stable content hash of engine inputs (djb2) — persisted per frame */
export function flowHash(parts: unknown[]): string {
  const s = JSON.stringify(parts);
  let h = 5381;
  for (let i = 0; i < s.length; i++) h = ((h << 5) + h + s.charCodeAt(i)) | 0;
  return (h >>> 0).toString(36);
}
