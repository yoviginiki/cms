import { describe, it, expect } from 'vitest';
import {
  equalSpans, presetToSpans, normalizeSpans, resizeSpans,
  spansToGridTemplate, orderFor, isCustomStackOrder,
} from './columnLayout';

const sum = (a: number[]) => a.reduce((x, y) => x + y, 0);

describe('equalSpans', () => {
  it('splits 12 evenly and always sums to 12', () => {
    expect(equalSpans(2)).toEqual([6, 6]);
    expect(equalSpans(3)).toEqual([4, 4, 4]);
    expect(equalSpans(4)).toEqual([3, 3, 3, 3]);
  });
  it('front-loads the remainder for non-divisors', () => {
    expect(equalSpans(5)).toEqual([3, 3, 2, 2, 2]);
    expect(sum(equalSpans(5))).toBe(12);
    expect(sum(equalSpans(6))).toBe(12);
  });
});

describe('presetToSpans', () => {
  it('maps known presets to 12-grid spans', () => {
    expect(presetToSpans('1/3+2/3', 2)).toEqual([4, 8]);
    expect(presetToSpans('3/4+1/4', 2)).toEqual([9, 3]);
    expect(presetToSpans('1/4+1/4+1/4+1/4', 4)).toEqual([3, 3, 3, 3]);
  });
  it('falls back to an equal split for unknown presets', () => {
    expect(presetToSpans('weird', 3)).toEqual([4, 4, 4]);
    expect(presetToSpans(undefined, 2)).toEqual([6, 6]);
  });
});

describe('normalizeSpans', () => {
  it('coerces malformed input to a valid count-length array summing to 12', () => {
    expect(sum(normalizeSpans('nonsense', 3))).toBe(12);
    expect(normalizeSpans([100, 0], 2).every((v) => v >= 1)).toBe(true);
    expect(sum(normalizeSpans([100, 0], 2))).toBe(12);
  });
  it('keeps a valid array intact', () => {
    expect(normalizeSpans([4, 8], 2)).toEqual([4, 8]);
  });
  it('pads a too-short array', () => {
    expect(sum(normalizeSpans([6], 3))).toBe(12);
    expect(normalizeSpans([6], 3)).toHaveLength(3);
  });
});

describe('resizeSpans', () => {
  it('moves units across a divider, preserving the total', () => {
    expect(resizeSpans([6, 6], 0, 2)).toEqual([8, 4]);
    expect(resizeSpans([6, 6], 0, -2)).toEqual([4, 8]);
  });
  it('never lets a neighbour drop below 1', () => {
    expect(resizeSpans([6, 6], 0, 99)).toEqual([11, 1]);
    expect(resizeSpans([6, 6], 0, -99)).toEqual([1, 11]);
  });
  it('ignores out-of-range dividers', () => {
    expect(resizeSpans([6, 6], 1, 2)).toEqual([6, 6]);
    expect(resizeSpans([4, 4, 4], 1, 1)).toEqual([4, 5, 3]);
  });
});

describe('spansToGridTemplate', () => {
  it('formats spans as fr units', () => {
    expect(spansToGridTemplate([4, 8])).toBe('4fr 8fr');
  });
});

describe('orderFor / isCustomStackOrder', () => {
  it('returns natural order without a permutation', () => {
    expect(orderFor(undefined, 2)).toBe(2);
    expect(orderFor([], 1)).toBe(1);
  });
  it('maps original index to its display position', () => {
    // display order [2,0,1]: col 2 first, col 0 second, col 1 third
    expect(orderFor([2, 0, 1], 2)).toBe(0);
    expect(orderFor([2, 0, 1], 0)).toBe(1);
    expect(orderFor([2, 0, 1], 1)).toBe(2);
  });
  it('detects a genuine reordering', () => {
    expect(isCustomStackOrder([0, 1, 2], 3)).toBe(false);
    expect(isCustomStackOrder([2, 0, 1], 3)).toBe(true);
    expect(isCustomStackOrder([0, 1], 3)).toBe(false); // wrong length
  });
});
