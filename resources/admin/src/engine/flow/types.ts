// ═══════════════════════════════════════════════════════════════════════════
// Magazine flow engine — types (Session C)
//
// The engine is a PURE module: flowText(blocks, frames, measurer) -> placements.
// It never reads React/Zustand state or the live editor DOM. All measurement
// goes through the injected FlowMeasurer:
//   - browser: DomMeasurer (hidden off-screen node + Range probes)
//   - tests:   FakeMeasurer (deterministic char-width tables, no DOM)
//
// Contract and invariants are pinned by magazine-flow-prototype.html (Session B)
// and the golden tests in engine.test.ts.
// ═══════════════════════════════════════════════════════════════════════════

export interface FlowRect {
  x: number;
  y: number;
  w: number;
  h: number;
}

export interface FlowExclusion extends FlowRect {
  /** inflation margin applied to all sides */
  margin: number;
  /** 'wrap' allows side wrap when enough width remains; 'jump' always cuts */
  mode: 'wrap' | 'jump';
}

/**
 * One frame in a thread chain. Coordinates are TEXT-AREA-LOCAL: the caller
 * already subtracted the frame inset, so (0,0) is the top-left of the text
 * area and width/height are the inset-adjusted dimensions.
 */
export interface FlowFrameSpec {
  id: string;
  width: number;
  height: number;
  columns: number;
  columnGap: number;
  exclusions: FlowExclusion[];
}

export type FlowBlockKind = 'paragraph' | 'heading' | 'quote' | 'atomic';

export interface FlowBlock {
  kind: FlowBlockKind;
  /** words split on whitespace; empty for word-less atomic blocks */
  words: string[];
  /** cumulative raw char length of words (len === words.length + 1) */
  charPrefix: number[];
  keepWithNext: boolean;
  /** index into the source story block list (adapters use it for slicing) */
  sourceIndex: number;
}

export interface FlowPlacementBox {
  rect: FlowRect;
  from: number;
  to: number;
}

export interface FlowFramePlacement {
  frameId: string;
  /** global word-index range consumed by this frame (from inclusive, to exclusive) */
  slice: { from: number; to: number };
  /** word indices where a box/column transition falls inside this frame */
  columnBreaks: number[];
  boxes: FlowPlacementBox[];
}

export interface FlowResult {
  frames: FlowFramePlacement[];
  /** null when everything placed; otherwise the first unplaced global word index */
  overflow: { fromWord: number } | null;
  totalWords: number;
}

/**
 * Measurement provider. A window is one rendered fragment [from, hi) at a
 * given width; all probes are cheap reads against that layout.
 * Implementations MUST be deterministic for identical inputs.
 */
export interface FlowMeasurer {
  openWindow(blocks: FlowBlock[], from: number, hi: number, width: number): void;
  /** px bottom (window-local) of the layout up to and including word k-1 */
  bottomAt(k: number): number;
  /**
   * line count of block `blockIndex`'s fragment inside the current window,
   * from the window's fragment start up to word `wordEndExclusive` (block-local)
   */
  linesTo(blockIndex: number, wordEndExclusive: number): number;
  /** advisory estimate of lines remaining in a block after `fromWord`, at `width` */
  remainderLines(blockIndex: number, fromWord: number, width: number): number;
  /** seed estimate of how many words fit a box starting at `from` (advisory) */
  estimateFit(blocks: FlowBlock[], from: number, box: FlowRect): number;
  /** smallest usable segment height (≈ 2 body lines) */
  minSegmentHeight(): number;
  closeWindow(): void;
}

export function buildWordPrefix(blocks: FlowBlock[]): number[] {
  const prefix: number[] = [0];
  for (const b of blocks) {
    // word-less atomic blocks still occupy 1 token so slices can address them
    prefix.push(prefix[prefix.length - 1] + Math.max(1, b.words.length));
  }
  return prefix;
}

export function blockAtWord(prefix: number[], g: number): { b: number; w: number } {
  let lo = 0;
  let hi = prefix.length - 2;
  let b = 0;
  while (lo <= hi) {
    const mid = (lo + hi) >> 1;
    if (prefix[mid] <= g) {
      b = mid;
      lo = mid + 1;
    } else {
      hi = mid - 1;
    }
  }
  return { b, w: g - prefix[b] };
}
