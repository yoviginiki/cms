// ═══════════════════════════════════════════════════════════════════════════
// Magazine flow engine — pure core (Session C)
//
// flowText(blocks, frames, measurer) -> FlowResult
//
// Invariants (pinned by engine.test.ts, contract from Session B prototype):
//  1. Pure + deterministic for identical (blocks, frames, measurer) inputs.
//  2. Word-boundary breaking via expand-then-binary-search; measurement only
//     through the injected measurer (no direct DOM access here).
//  3. Fill order: columns left→right, boxes top→bottom, frames in chain order.
//  4. Widow/orphan: a split paragraph keeps ≥2 lines on each side, else the
//     break retreats (widow) or the whole block moves on (orphan).
//  5. Keep-with-next: a heading never ends a box.
//  6. Quote and atomic blocks never split; a block taller than every box is
//     force-placed (progress guarantee), never dropped.
//  7. Exclusions carve column boxes: 'wrap' narrows the column when ≥45% of
//     its width stays free, otherwise (and always for 'jump') the text jumps.
//  8. Losslessness: frame slices partition [0, consumed); the caller writes
//     the overset remainder into the last frame's slice so no content is ever
//     lost (it clips visually and surfaces via the overset indicator).
// ═══════════════════════════════════════════════════════════════════════════

import type {
  FlowBlock,
  FlowFrameSpec,
  FlowMeasurer,
  FlowRect,
  FlowResult,
  FlowFramePlacement,
  FlowPlacementBox,
} from './types';
import { buildWordPrefix, blockAtWord } from './types';

const MIN_BOX_WIDTH = 40;
const SIDE_WRAP_MIN_FRACTION = 0.45;
const EXPAND_TRIES = 8;

export function buildBoxes(frame: FlowFrameSpec, minSegH: number): FlowRect[] {
  const cols = Math.max(1, Math.floor(frame.columns) || 1);
  const gap = Math.max(0, frame.columnGap || 0);
  const colW = (frame.width - gap * (cols - 1)) / cols;
  const boxes: FlowRect[] = [];
  if (colW < MIN_BOX_WIDTH || frame.height <= 0) return boxes;

  for (let c = 0; c < cols; c++) {
    const cx = c * (colW + gap);
    let segs: FlowRect[] = [{ x: cx, y: 0, w: colW, h: frame.height }];

    for (const ex of frame.exclusions || []) {
      const m = ex.margin || 0;
      const R = { x: ex.x - m, y: ex.y - m, w: ex.w + 2 * m, h: ex.h + 2 * m };
      const next: FlowRect[] = [];
      for (const s of segs) {
        const overlapsX = R.x < s.x + s.w && R.x + R.w > s.x;
        const overlapsY = R.y < s.y + s.h && R.y + R.h > s.y;
        if (!overlapsX || !overlapsY) {
          next.push(s);
          continue;
        }
        const topH = R.y - s.y;
        if (topH > 0) next.push({ x: s.x, y: s.y, w: s.w, h: topH });

        // side wrap: only for 'wrap' exclusions with enough free width
        const freeL = R.x - s.x;
        const freeR = s.x + s.w - (R.x + R.w);
        const free = Math.max(freeL, freeR);
        if (ex.mode !== 'jump' && free >= s.w * SIDE_WRAP_MIN_FRACTION && free >= MIN_BOX_WIDTH) {
          const sx = freeL >= freeR ? s.x : R.x + R.w;
          const oy = Math.max(s.y, R.y);
          const oh = Math.min(s.y + s.h, R.y + R.h) - oy;
          if (oh > 0) next.push({ x: sx, y: oy, w: free, h: oh });
        }

        const botY = R.y + R.h;
        const botH = s.y + s.h - botY;
        if (botH > 0) next.push({ x: s.x, y: botY, w: s.w, h: botH });
      }
      segs = next;
    }

    for (const s of segs) {
      if (s.h >= minSegH && s.w >= MIN_BOX_WIDTH) boxes.push(s);
    }
  }
  return boxes;
}

function binaryFit(
  blocks: FlowBlock[],
  total: number,
  s: number,
  box: FlowRect,
  measurer: FlowMeasurer,
): number {
  if (s >= total) return s;
  const est = measurer.estimateFit(blocks, s, box);
  let hi = Math.min(total, Math.max(s + 8, Math.ceil(s + (est - s) * 1.35) + 16));

  for (let tries = 0; tries < EXPAND_TRIES; tries++) {
    measurer.openWindow(blocks, s, hi, box.w);
    if (measurer.bottomAt(hi) <= box.h) {
      if (hi >= total) return total; // everything fits
      hi = Math.min(total, s + Math.ceil((hi - s) * 1.7) + 16);
      continue;
    }
    let lo = s;
    let h2 = hi;
    while (lo < h2 - 1) {
      const mid = (lo + h2) >> 1;
      if (measurer.bottomAt(mid) <= box.h) lo = mid;
      else h2 = mid;
    }
    return lo;
  }
  return s;
}

function fitBox(
  blocks: FlowBlock[],
  prefix: number[],
  total: number,
  s: number,
  box: FlowRect,
  measurer: FlowMeasurer,
): number {
  let e = binaryFit(blocks, total, s, box, measurer);
  const raw = e;
  if (e <= s) return s;

  if (e < total) {
    const pe = blockAtWord(prefix, e);
    if (pe.w > 0) {
      // break falls INSIDE block pe.b (window for this box is still open)
      const b = pe.b;
      const bStart = prefix[b];
      const blk = blocks[b];
      if (blk.kind === 'quote' || blk.kind === 'atomic') {
        // atomic: push the whole block — unless it started this box, in which
        // case force-place it (progress guarantee; overset badge surfaces it)
        if (bStart > s) e = bStart;
      } else {
        const fl = measurer.linesTo(b, pe.w);
        const rem = measurer.remainderLines(b, pe.w, box.w);
        if (fl < 2 && bStart > s) {
          e = bStart; // orphan: push block to next box
        } else if (rem === 1) {
          // widow: retreat the break so ≥2 lines carry over
          const target = fl - 1;
          if (target >= 2) {
            const startW = Math.max(s, bStart) - bStart;
            let lo2 = startW + 1;
            let hi2 = pe.w;
            let best: number | null = null;
            while (lo2 <= hi2) {
              const mid = (lo2 + hi2) >> 1;
              if (measurer.linesTo(b, mid) <= target) {
                best = mid;
                lo2 = mid + 1;
              } else {
                hi2 = mid - 1;
              }
            }
            if (best !== null && measurer.linesTo(b, best) >= 2) e = bStart + best;
            else if (bStart > s) e = bStart;
          } else if (bStart > s) {
            e = bStart;
          }
        }
      }
    }

    // keep-with-next: a heading may not be the last content in a box
    const pe2 = blockAtWord(prefix, e);
    if (e > s && e < total && pe2.w === 0 && pe2.b > 0) {
      const prev = blocks[pe2.b - 1];
      if (prev.keepWithNext && prefix[pe2.b - 1] > s) e = prefix[pe2.b - 1];
    }
  }

  if (e <= s) e = raw; // progress guarantee: rules yield to progress
  return e;
}

export function flowText(
  blocks: FlowBlock[],
  frames: FlowFrameSpec[],
  measurer: FlowMeasurer,
): FlowResult {
  const prefix = buildWordPrefix(blocks);
  const total = prefix[prefix.length - 1];
  let s = 0;
  const placements: FlowFramePlacement[] = [];
  const minSegH = measurer.minSegmentHeight();

  try {
    for (const frame of frames) {
      const from = s;
      const columnBreaks: number[] = [];
      const boxes: FlowPlacementBox[] = [];
      for (const box of buildBoxes(frame, minSegH)) {
        if (s >= total) break;
        const e = fitBox(blocks, prefix, total, s, box, measurer);
        if (e > s) {
          boxes.push({ rect: box, from: s, to: e });
          columnBreaks.push(e);
        }
        s = e;
      }
      columnBreaks.pop();
      placements.push({
        frameId: frame.id,
        slice: { from, to: s },
        columnBreaks,
        boxes,
      });
    }
  } finally {
    measurer.closeWindow();
  }

  return {
    frames: placements,
    overflow: s < total ? { fromWord: s } : null,
    totalWords: total,
  };
}
