import { describe, it, expect } from 'vitest';
import { defaultSize, dropPosition, CANVAS_BLOCKS } from './canvasBlocks';

describe('canvasBlocks', () => {
  it('gives every palette block a starting size', () => {
    for (const t of CANVAS_BLOCKS) {
      const s = defaultSize(t);
      expect(s.width).toBeGreaterThan(0);
      expect(s.height).toBeGreaterThan(0);
    }
    expect(defaultSize('unknown-thing')).toEqual({ width: 260, height: 120 });
  });

  it('centres the dropped block on the pointer in canvas px, undoing the zoom', () => {
    const size = { width: 200, height: 100 };
    expect(dropPosition(500, 300, { left: 100, top: 50 }, 1, size)).toEqual({ x: 300, y: 200 });
    // zoom 0.5: screen 200px right of the edge = 400 canvas px
    expect(dropPosition(300, 150, { left: 100, top: 50 }, 0.5, size)).toEqual({ x: 300, y: 150 });
  });

  it('never places a block past the top/left edge', () => {
    expect(dropPosition(10, 5, { left: 0, top: 0 }, 1, { width: 200, height: 100 })).toEqual({ x: 0, y: 0 });
  });
});
