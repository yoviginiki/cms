// ═══════════════════════════════════════════════════════════════════════════
// DomMeasurer — browser measurement for the flow engine.
//
// Strategy (ported from magazine-flow-prototype.html, the Session B spec):
// ONE off-screen render per fill window (a single forced layout), then every
// break-point probe is a Range.getBoundingClientRect() read against that
// clean layout — no relayout per binary probe.
//
// Styling comes from buildMeasurerCss (the SAME builder the renderer uses),
// so measurement can never drift from what MagElementRenderer paints.
// ═══════════════════════════════════════════════════════════════════════════

import type { MagTypography } from '@/types/magazine';
import type { FlowBlock, FlowMeasurer, FlowRect } from './types';
import { buildWordPrefix, blockAtWord } from './types';
import type { StoryBlock } from './content';
import { buildMeasurerCss, bodyLineHeightPx } from './textStyle';

interface WindowEntry {
  el: HTMLElement;
  blockIndex: number;
  /** first block-local word rendered in this window */
  w0: number;
  /** raw chars trimmed from the front of the source element */
  trimStartCh: number;
}

const HEADING_SCALE: Record<string, number> = {
  H1: 2, H2: 1.5, H3: 1.17, H4: 1, H5: 0.83, H6: 0.67,
};

export class DomMeasurer implements FlowMeasurer {
  private host: HTMLElement;
  private hostTop = 0;
  private entries: WindowEntry[] = [];
  private winBlocks: FlowBlock[] = [];
  private winPrefix: number[] = [0];
  private winFromBlock = 0;
  private ctx: CanvasRenderingContext2D | null = null;
  private wordWidthCache = new Map<string, number>();
  private fontPx: number;
  private lineHpx: number;

  constructor(
    private story: StoryBlock[],
    typography: MagTypography | null,
  ) {
    this.host = document.createElement('div');
    this.host.setAttribute('lang', document.documentElement.lang || 'en');
    this.host.style.cssText =
      'position:fixed;left:-12000px;top:0;visibility:hidden;contain:layout style;overflow:hidden;' +
      buildMeasurerCss(typography);
    document.body.appendChild(this.host);
    this.fontPx = typography?.fontSize || 14;
    this.lineHpx = bodyLineHeightPx(typography);
    const canvas = document.createElement('canvas');
    this.ctx = canvas.getContext('2d');
    if (this.ctx) {
      const fam = (typography?.fontFamily || 'Inter').split(',')[0].replace(/['"]/g, '');
      this.ctx.font = `${typography?.fontWeight || 400} ${this.fontPx}px ${fam}, sans-serif`;
    }
  }

  dispose(): void {
    this.host.remove();
  }

  // ── window management ──────────────────────────────────────────────────

  private pointAtChar(rootEl: Node, charPos: number): { node: Node; offset: number } {
    const walker = document.createTreeWalker(rootEl, NodeFilter.SHOW_TEXT);
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
    return last ? { node: last, offset: last.textContent?.length ?? 0 } : { node: rootEl, offset: 0 };
  }

  private deleteCharRange(el: Element, startCh: number, endCh: number): void {
    if (endCh <= startCh) return;
    const a = this.pointAtChar(el, startCh);
    const b = this.pointAtChar(el, endCh);
    const r = document.createRange();
    r.setStart(a.node, a.offset);
    r.setEnd(b.node, b.offset);
    r.deleteContents();
  }

  openWindow(blocks: FlowBlock[], from: number, hi: number, width: number): void {
    this.winBlocks = blocks;
    this.winPrefix = buildWordPrefix(blocks);
    const a = blockAtWord(this.winPrefix, from);
    const z = blockAtWord(this.winPrefix, Math.max(from, hi - 1));
    this.winFromBlock = a.b;
    this.host.style.width = `${Math.max(20, width)}px`;
    this.host.textContent = '';
    this.entries = [];

    for (let b = a.b; b <= z.b; b++) {
      const src = this.story[blocks[b].sourceIndex];
      if (!src) continue;
      const clone = src.el.cloneNode(true) as HTMLElement;
      const w0 = b === a.b ? a.w : 0;
      let trimStartCh = 0;
      const n = src.words.length;
      // trim tail beyond hi (perf: don't lay out words we never probe)
      if (b === z.b && n > 0 && z.w + 1 < n) {
        this.deleteCharRange(clone, src.wordOffsets[z.w].end, (src.el.textContent || '').length);
      }
      if (w0 > 0 && n > 0) {
        trimStartCh = src.wordOffsets[w0].start;
        this.deleteCharRange(clone, 0, trimStartCh);
        clone.style.marginTop = '0';
        clone.style.textIndent = '0';
      }
      this.host.appendChild(clone);
      this.entries.push({ el: clone, blockIndex: b, w0, trimStartCh });
    }
    this.hostTop = this.host.getBoundingClientRect().top;
  }

  closeWindow(): void {
    this.host.textContent = '';
    this.entries = [];
  }

  private entry(blockIndex: number): WindowEntry | undefined {
    return this.entries[blockIndex - this.winFromBlock];
  }

  // ── probes ────────────────────────────────────────────────────────────

  bottomAt(k: number): number {
    const p = blockAtWord(this.winPrefix, k - 1);
    const ent = this.entry(p.b);
    if (!ent) return Number.POSITIVE_INFINITY;
    const blk = this.winBlocks[p.b];
    const src = this.story[blk.sourceIndex];
    if (blk.kind === 'atomic' || !src || src.words.length === 0) {
      return ent.el.getBoundingClientRect().bottom - this.hostTop;
    }
    const chEnd = src.wordOffsets[p.w].end - ent.trimStartCh;
    const pt = this.pointAtChar(ent.el, Math.max(0, chEnd));
    const r = document.createRange();
    r.setStart(this.host, 0);
    r.setEnd(pt.node, pt.offset);
    return r.getBoundingClientRect().bottom - this.hostTop;
  }

  linesTo(blockIndex: number, wordEndExclusive: number): number {
    const ent = this.entry(blockIndex);
    if (!ent) return 0;
    const blk = this.winBlocks[blockIndex];
    const src = this.story[blk.sourceIndex];
    if (!src || src.words.length === 0 || wordEndExclusive <= ent.w0) return 0;
    const j = Math.min(wordEndExclusive, src.words.length);
    const chEnd = src.wordOffsets[j - 1].end - ent.trimStartCh;
    const start = this.pointAtChar(ent.el, 0);
    const end = this.pointAtChar(ent.el, Math.max(0, chEnd));
    const r = document.createRange();
    r.setStart(start.node, start.offset);
    r.setEnd(end.node, end.offset);
    const cs = window.getComputedStyle(ent.el);
    const lh = parseFloat(cs.lineHeight) || this.lineHpx;
    return Math.max(1, Math.round(r.getBoundingClientRect().height / lh));
  }

  // ── arithmetic estimates (seed only — never authoritative) ─────────────

  private wordW(word: string, scale: number): number {
    if (!this.ctx) return (word.length * this.fontPx * 0.55 + this.fontPx * 0.28) * scale;
    const key = scale + '|' + word;
    let w = this.wordWidthCache.get(key);
    if (w === undefined) {
      w = (this.ctx.measureText(word + ' ').width || this.fontPx * 3) * scale;
      this.wordWidthCache.set(key, w);
    }
    return w;
  }

  private blockScale(blk: FlowBlock): number {
    if (blk.kind !== 'heading') return 1;
    const src = this.story[blk.sourceIndex];
    return HEADING_SCALE[src?.el.tagName || 'H2'] || 1.5;
  }

  remainderLines(blockIndex: number, fromWord: number, width: number): number {
    const blk = this.winBlocks[blockIndex];
    if (!blk) return 0;
    const scale = this.blockScale(blk);
    let x = 0;
    let lines = 1;
    for (let i = fromWord; i < blk.words.length; i++) {
      const ww = this.wordW(blk.words[i], scale);
      if (x + ww > width && x > 0) {
        lines++;
        x = 0;
      }
      x += ww;
    }
    return blk.words.length > fromWord ? lines : 0;
  }

  estimateFit(blocks: FlowBlock[], from: number, box: FlowRect): number {
    const prefix = buildWordPrefix(blocks);
    const total = prefix[prefix.length - 1];
    let g = from;
    let y = 0;
    while (g < total && y < box.h) {
      const p = blockAtWord(prefix, g);
      const blk = blocks[p.b];
      const scale = this.blockScale(blk);
      const lineH = this.lineHpx * scale;
      if (blk.kind === 'atomic') {
        y += 90; // rough figure/list allowance; window verify corrects
        g = prefix[p.b + 1];
        if (y > box.h && g > from) return Math.max(from + 1, g - 1);
        continue;
      }
      if (p.w === 0 && g !== from) y += this.fontPx * 0.6; // block spacing guess
      let x = 0;
      y += lineH;
      let wi = p.w;
      while (wi < blk.words.length) {
        const ww = this.wordW(blk.words[wi], scale);
        if (x + ww > box.w && x > 0) {
          y += lineH;
          if (y > box.h) return Math.max(from + 1, prefix[p.b] + wi);
          x = 0;
        }
        x += ww;
        wi++;
      }
      g = prefix[p.b] + Math.max(1, blk.words.length);
    }
    return Math.max(from + 1, Math.min(g, total));
  }

  minSegmentHeight(): number {
    return 2 * this.lineHpx;
  }
}
