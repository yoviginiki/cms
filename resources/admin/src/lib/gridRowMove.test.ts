import { describe, it, expect } from 'vitest';
import { moveRowOrder, rowBand } from './gridRowMove';

const cells = [['header', 'header'], ['main', 'side'], ['main', 'side'], ['footer', 'footer']];

describe('grid row move', () => {
  it('moves a single row past its neighbour', () => {
    expect(moveRowOrder(cells, 0, 1)).toEqual([1, 2, 0, 3]); // header jumps the whole main/side band
    expect(moveRowOrder(cells, 3, -1)).toEqual([0, 3, 1, 2]);
  });
  it('keeps a multi-row area together', () => {
    expect(rowBand(cells, 1)).toEqual([1, 2]);
    expect(moveRowOrder(cells, 2, -1)).toEqual([1, 2, 0, 3]);
  });
  it('stops at the edges', () => {
    expect(moveRowOrder(cells, 0, -1)).toBeNull();
    expect(moveRowOrder(cells, 3, 1)).toBeNull();
  });
});
