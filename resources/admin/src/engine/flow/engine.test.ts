// ═══════════════════════════════════════════════════════════════════════════
// GOLDEN TESTS — magazine flow engine (Session C, Phase 1)
//
// These pin the engine contract. A behavior-changing "fix" must fail here
// before it can regress the editor. All tests run on the deterministic
// FakeMeasurer — no DOM, no fonts, no timing.
// ═══════════════════════════════════════════════════════════════════════════

import { describe, it, expect } from 'vitest';
import { flowText, buildBoxes } from './engine';
import { FakeMeasurer, fakeBlock, DEFAULT_FAKE_METRICS } from './fakeMeasurer';
import type { FlowBlock, FlowFrameSpec } from './types';
import { buildWordPrefix } from './types';

const M = DEFAULT_FAKE_METRICS; // charW 6, spaceW 3 → "word words" ≈ predictable

function frame(partial: Partial<FlowFrameSpec> & { id: string }): FlowFrameSpec {
  return { width: 300, height: 200, columns: 1, columnGap: 12, exclusions: [], ...partial };
}

/** words with fixed 4-char length → wordW = 27px; 10 per 300px line */
function para(nWords: number, src = 0): FlowBlock {
  return fakeBlock(Array.from({ length: nWords }, (_, i) => 'w' + String(i % 100).padStart(3, '0')).join(' '), 'paragraph', src);
}

function totalWords(blocks: FlowBlock[]): number {
  const p = buildWordPrefix(blocks);
  return p[p.length - 1];
}

/** assert frame slices are contiguous and partition [0, consumed) */
function assertPartition(blocks: FlowBlock[], res: ReturnType<typeof flowText>) {
  let cursor = 0;
  for (const f of res.frames) {
    expect(f.slice.from).toBe(cursor);
    expect(f.slice.to).toBeGreaterThanOrEqual(f.slice.from);
    // boxes inside a frame are contiguous too
    let boxCursor = f.slice.from;
    for (const bx of f.boxes) {
      expect(bx.from).toBe(boxCursor);
      expect(bx.to).toBeGreaterThan(bx.from);
      boxCursor = bx.to;
    }
    expect(boxCursor).toBe(f.slice.to);
    cursor = f.slice.to;
  }
  const consumed = res.overflow ? res.overflow.fromWord : totalWords(blocks);
  expect(cursor).toBe(consumed);
}

describe('flow engine — golden tests', () => {
  it('simple fill: short content fits one frame, no overflow', () => {
    const blocks = [para(20)];
    const res = flowText(blocks, [frame({ id: 'A' })], new FakeMeasurer());
    expect(res.overflow).toBeNull();
    expect(res.frames).toHaveLength(1);
    expect(res.frames[0].slice).toEqual({ from: 0, to: 20 });
    assertPartition(blocks, res);
  });

  it('multi-column fill order: left column fills before right', () => {
    // 2 columns of 144px → ~4 words/line (4*27=108 ≤144), 10 lines/200px
    const blocks = [para(200)];
    const res = flowText(
      blocks,
      [frame({ id: 'A', width: 300, height: 200, columns: 2 })],
      new FakeMeasurer(),
    );
    const boxes = res.frames[0].boxes;
    expect(boxes.length).toBe(2);
    expect(boxes[0].rect.x).toBeLessThan(boxes[1].rect.x); // left→right
    expect(boxes[0].to).toBe(boxes[1].from); // contiguous across columns
    expect(res.frames[0].columnBreaks).toEqual([boxes[0].to]);
    assertPartition(blocks, res);
  });

  it('chain overflow: content spills into the second frame, slices contiguous', () => {
    const blocks = [para(120), para(120, 1)];
    const res = flowText(
      blocks,
      [frame({ id: 'A', height: 120 }), frame({ id: 'B', height: 600 })],
      new FakeMeasurer(),
    );
    expect(res.overflow).toBeNull();
    expect(res.frames[0].slice.to).toBeGreaterThan(0);
    expect(res.frames[1].slice.from).toBe(res.frames[0].slice.to);
    expect(res.frames[1].slice.to).toBe(240);
    assertPartition(blocks, res);
  });

  it('overflow reported when the chain is too small; nothing is lost', () => {
    const blocks = [para(500)];
    const res = flowText(blocks, [frame({ id: 'A', height: 100 })], new FakeMeasurer());
    expect(res.overflow).not.toBeNull();
    expect(res.overflow!.fromWord).toBeGreaterThan(0);
    expect(res.overflow!.fromWord).toBeLessThan(500);
    assertPartition(blocks, res);
  });

  it('orphan control: a paragraph never leaves a single line at a box bottom', () => {
    // Box fits exactly 5 lines (h=100, lineH=20). First para = 4 lines + mb 10
    // leaves 10px — the next paragraph would start with <1 line → 0 lines.
    // Force the orphan case: first para 4.5 lines worth, second para long.
    const blocks = [para(42), para(100, 1)]; // 42 words ≈ 5 lines (10/line)
    const res = flowText(blocks, [frame({ id: 'A', height: 130 }), frame({ id: 'B', height: 600 })], new FakeMeasurer());
    // wherever the break fell inside para 2, it must keep ≥2 lines (>= ~11 words)
    const p2Start = 42;
    const consumedOfP2 = res.frames[0].slice.to - p2Start;
    if (consumedOfP2 > 0) {
      // ≥ 2 lines at 10 words/line
      expect(consumedOfP2).toBeGreaterThanOrEqual(11);
    }
    assertPartition(blocks, res);
  });

  it('widow control: a split paragraph carries ≥2 lines into the next box', () => {
    // craft: para whose tail would leave exactly 1 line
    const blocks = [para(105)]; // 10.5 lines at width 300
    const res = flowText(
      blocks,
      [frame({ id: 'A', height: 200 }), frame({ id: 'B', height: 600 })],
      new FakeMeasurer(),
    );
    if (res.frames[1] && res.frames[1].slice.to > res.frames[1].slice.from) {
      const carried = res.frames[1].slice.to - res.frames[1].slice.from;
      expect(carried).toBeGreaterThanOrEqual(11); // ≥ 2 lines worth of words
    }
    assertPartition(blocks, res);
  });

  it('keep-with-next: a heading never ends a box', () => {
    // frame A: fits para(40) [4 lines = 80px] + margins + heading — then full.
    const blocks = [para(40), fakeBlock('Section Heading Here', 'heading', 1), para(100, 2)];
    const res = flowText(
      blocks,
      [frame({ id: 'A', height: 130 }), frame({ id: 'B', height: 600 })],
      new FakeMeasurer(),
    );
    const headingStart = 40;
    const headingEnd = 43;
    const aTo = res.frames[0].slice.to;
    // The heading must not be the last thing in frame A
    expect(aTo === headingEnd && res.frames[1].slice.from === headingEnd).toBe(false);
    if (aTo > headingStart && aTo < totalWords(blocks)) {
      // if A contains the heading it must also contain body after it
      expect(aTo).toBeGreaterThan(headingEnd);
    }
    assertPartition(blocks, res);
  });

  it('quote no-break: a blockquote moves whole to the next box', () => {
    const blocks = [para(45), fakeBlock(Array(30).fill('quote').join(' '), 'quote', 1), para(50, 2)];
    const res = flowText(
      blocks,
      [frame({ id: 'A', height: 120 }), frame({ id: 'B', height: 600 })],
      new FakeMeasurer(),
    );
    const qStart = 45;
    const qEnd = 75;
    for (const f of res.frames) {
      const { from, to } = f.slice;
      const cutsQuote = from < qEnd && to > qStart && (from > qStart || to < qEnd);
      // the quote may be fully inside a frame, but never partially
      if (cutsQuote) {
        expect(from <= qStart && to >= qEnd).toBe(true);
      }
    }
    assertPartition(blocks, res);
  });

  it('atomic force-place: a block taller than every box still makes progress', () => {
    const blocks = [fakeBlock(Array(400).fill('giant').join(' '), 'quote', 0), para(20, 1)];
    const res = flowText(
      blocks,
      [frame({ id: 'A', height: 100 }), frame({ id: 'B', height: 100 })],
      new FakeMeasurer(),
    );
    // quote larger than any box: force-placed (consumes words), never an infinite loop
    expect(res.frames[0].slice.to).toBeGreaterThan(0);
    assertPartition(blocks, res);
  });

  it('exclusion carve-out (wrap): boxes never intersect the inflated exclusion', () => {
    const ex = { x: 0, y: 80, w: 120, h: 60, margin: 10, mode: 'wrap' as const };
    const f = frame({ id: 'A', width: 300, height: 300, columns: 1, exclusions: [ex] });
    const boxes = buildBoxes(f, 40);
    expect(boxes.length).toBeGreaterThanOrEqual(2);
    const R = { x: ex.x - ex.margin, y: ex.y - ex.margin, w: ex.w + 20, h: ex.h + 20 };
    for (const b of boxes) {
      const overlap =
        R.x < b.x + b.w && R.x + R.w > b.x && R.y < b.y + b.h && R.y + R.h > b.y;
      expect(overlap).toBe(false);
    }
    // side-wrap segment must exist (free width 300-140=160 ≥ 45%)
    expect(boxes.some((b) => b.w < 300 && b.w >= 100)).toBe(true);
  });

  it('exclusion carve-out (jump): no side segment even when width allows it', () => {
    const ex = { x: 0, y: 80, w: 120, h: 60, margin: 10, mode: 'jump' as const };
    const boxes = buildBoxes(frame({ id: 'A', width: 300, height: 300, exclusions: [ex] }), 40);
    // only full-width segments above and below
    for (const b of boxes) expect(b.w).toBe(300);
    expect(boxes.length).toBe(2);
  });

  it('exclusion flows text around: no placed box overlaps the exclusion', () => {
    const ex = { x: 100, y: 60, w: 200, h: 80, margin: 8, mode: 'wrap' as const };
    const blocks = [para(300)];
    const res = flowText(
      blocks,
      [frame({ id: 'A', width: 300, height: 400, exclusions: [ex] }), frame({ id: 'B', height: 800 })],
      new FakeMeasurer(),
    );
    for (const bx of res.frames[0].boxes) {
      const R = { x: ex.x - 8, y: ex.y - 8, w: ex.w + 16, h: ex.h + 16 };
      const b = bx.rect;
      const overlap = R.x < b.x + b.w && R.x + R.w > b.x && R.y < b.y + b.h && R.y + R.h > b.y;
      expect(overlap).toBe(false);
    }
    assertPartition(blocks, res);
  });

  it('determinism: identical inputs produce deep-equal output', () => {
    const blocks = [para(80), fakeBlock('A Heading', 'heading', 1), para(150, 2), fakeBlock('quote line here', 'quote', 3), para(90, 4)];
    const frames = [
      frame({ id: 'A', columns: 2, height: 180 }),
      frame({ id: 'B', columns: 3, height: 240, exclusions: [{ x: 50, y: 50, w: 80, h: 60, margin: 6, mode: 'wrap' }] }),
      frame({ id: 'C', height: 500 }),
    ];
    const r1 = flowText(blocks, frames, new FakeMeasurer());
    const r2 = flowText(blocks, frames, new FakeMeasurer());
    expect(r2).toEqual(r1);
  });

  it('losslessness: slices + overflow always cover every word exactly once', () => {
    const cases: Array<{ blocks: FlowBlock[]; frames: FlowFrameSpec[] }> = [
      { blocks: [para(500)], frames: [frame({ id: 'A', height: 100 })] },
      { blocks: [para(60), para(60, 1), para(60, 2)], frames: [frame({ id: 'A', height: 150 }), frame({ id: 'B', height: 150 })] },
      { blocks: [fakeBlock('', 'atomic', 0), para(30, 1)], frames: [frame({ id: 'A', height: 400 })] },
      { blocks: [para(100)], frames: [frame({ id: 'A', columns: 4, height: 120 }), frame({ id: 'B', columns: 2, height: 300 })] },
    ];
    for (const c of cases) {
      const res = flowText(c.blocks, c.frames, new FakeMeasurer());
      assertPartition(c.blocks, res);
    }
  });

  it('word-less atomic block occupies exactly one token and is placeable', () => {
    const blocks = [para(10), fakeBlock('', 'atomic', 1), para(10, 2)];
    expect(totalWords(blocks)).toBe(21);
    const res = flowText(blocks, [frame({ id: 'A', height: 600 })], new FakeMeasurer());
    expect(res.overflow).toBeNull();
    expect(res.frames[0].slice.to).toBe(21);
  });
});
