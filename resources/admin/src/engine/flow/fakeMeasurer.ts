// ═══════════════════════════════════════════════════════════════════════════
// FakeMeasurer — deterministic measurement for Node unit tests. No DOM.
//
// Model: every character is CHAR_W px wide, words are separated by SPACE_W px,
// greedy line breaking at the box width, fixed line heights and block margins
// per block kind. bottomAt/linesTo/estimateFit all derive from the SAME
// simulation, so binary search converges exactly and results are stable.
// ═══════════════════════════════════════════════════════════════════════════

import type { FlowBlock, FlowMeasurer, FlowRect } from './types';
import { buildWordPrefix, blockAtWord } from './types';

export interface FakeMetrics {
  charW: number;
  spaceW: number;
  lineH: Record<string, number>;
  marginTop: Record<string, number>;
  marginBottom: Record<string, number>;
  atomicHeight: number;
}

export const DEFAULT_FAKE_METRICS: FakeMetrics = {
  charW: 6,
  spaceW: 3,
  lineH: { paragraph: 20, heading: 28, quote: 20, atomic: 20 },
  marginTop: { paragraph: 0, heading: 16, quote: 12, atomic: 8 },
  marginBottom: { paragraph: 10, heading: 6, quote: 12, atomic: 8 },
  atomicHeight: 80,
};

export class FakeMeasurer implements FlowMeasurer {
  private blocks: FlowBlock[] = [];
  private prefix: number[] = [0];
  private from = 0;
  private width = 0;

  constructor(private m: FakeMetrics = DEFAULT_FAKE_METRICS) {}

  private wordW(word: string): number {
    return word.length * this.m.charW + this.m.spaceW;
  }

  /** lines a run of words occupies at a width (greedy, ≥1 when any words) */
  private lineCount(words: string[], w0: number, w1: number, width: number): number {
    if (w1 <= w0) return 0;
    let lines = 1;
    let x = 0;
    for (let i = w0; i < w1; i++) {
      const ww = this.wordW(words[i]);
      if (x + ww > width && x > 0) {
        lines++;
        x = 0;
      }
      x += ww;
    }
    return lines;
  }

  /** simulate layout of [start, k) at `width`; returns bottom px of word k-1 */
  private simulateBottom(start: number, k: number, width: number): number {
    if (k <= start) return 0;
    const a = blockAtWord(this.prefix, start);
    const z = blockAtWord(this.prefix, k - 1);
    let y = 0;
    for (let b = a.b; b <= z.b; b++) {
      const blk = this.blocks[b];
      const kind = blk.kind;
      const w0 = b === a.b ? a.w : 0;
      if (b > a.b) y += this.m.marginTop[kind] ?? 0;
      if (kind === 'atomic' && blk.words.length === 0) {
        y += this.m.atomicHeight;
      } else if (kind === 'atomic') {
        y += this.m.atomicHeight;
      } else {
        const w1 = b === z.b ? z.w + 1 : blk.words.length;
        y += this.lineCount(blk.words, w0, w1, width) * (this.m.lineH[kind] ?? 20);
      }
      if (b < z.b) y += this.m.marginBottom[kind] ?? 0;
    }
    return y;
  }

  openWindow(blocks: FlowBlock[], from: number, _hi: number, width: number): void {
    this.blocks = blocks;
    this.prefix = buildWordPrefix(blocks);
    this.from = from;
    this.width = width;
  }

  bottomAt(k: number): number {
    return this.simulateBottom(this.from, k, this.width);
  }

  linesTo(blockIndex: number, wordEndExclusive: number): number {
    const blk = this.blocks[blockIndex];
    if (!blk) return 0;
    const bStart = this.prefix[blockIndex];
    const w0 = Math.max(0, this.from - bStart);
    return this.lineCount(blk.words, w0, wordEndExclusive, this.width);
  }

  remainderLines(blockIndex: number, fromWord: number, width: number): number {
    const blk = this.blocks[blockIndex];
    if (!blk) return 0;
    return this.lineCount(blk.words, fromWord, blk.words.length, width);
  }

  estimateFit(blocks: FlowBlock[], from: number, box: FlowRect): number {
    // exact simulation makes the seed perfect — expansion loop hits first try
    const prefix = buildWordPrefix(blocks);
    const total = prefix[prefix.length - 1];
    const saved = { blocks: this.blocks, prefix: this.prefix, from: this.from, width: this.width };
    this.blocks = blocks;
    this.prefix = prefix;
    let lo = from;
    let hi = total;
    while (lo < hi) {
      const mid = (lo + hi + 1) >> 1;
      if (this.simulateBottom(from, mid, box.w) <= box.h) lo = mid;
      else hi = mid - 1;
    }
    this.blocks = saved.blocks;
    this.prefix = saved.prefix;
    this.from = saved.from;
    this.width = saved.width;
    return Math.max(from + 1, lo);
  }

  minSegmentHeight(): number {
    return 2 * (this.m.lineH.paragraph ?? 20);
  }

  closeWindow(): void {
    // nothing to release
  }
}

/** test helper: build a FlowBlock from plain text */
export function fakeBlock(
  text: string,
  kind: FlowBlock['kind'] = 'paragraph',
  sourceIndex = 0,
): FlowBlock {
  const words = text.split(/\s+/).filter(Boolean);
  const charPrefix = [0];
  for (const w of words) charPrefix.push(charPrefix[charPrefix.length - 1] + w.length);
  return {
    kind,
    words,
    charPrefix,
    keepWithNext: kind === 'heading',
    sourceIndex,
  };
}
