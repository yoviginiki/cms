import { describe, it, expect } from 'vitest';
import { proportions } from './RowLayoutPicker';
import { LAYOUT_GRID, LAYOUT_LABELS } from './definition';

describe('RowLayoutPicker proportions', () => {
  it('parses equal splits', () => {
    expect(proportions('1fr 1fr')).toEqual([1, 1]);
    expect(proportions('1fr 1fr 1fr')).toEqual([1, 1, 1]);
  });

  it('parses weighted splits', () => {
    expect(proportions('1fr 2fr')).toEqual([1, 2]);
    expect(proportions('3fr 1fr')).toEqual([3, 1]);
  });

  it('falls back to a single full-width column', () => {
    expect(proportions('1fr')).toEqual([1]);
    expect(proportions('')).toEqual([1]);
    expect(proportions('garbage')).toEqual([1]);
  });

  it('produces a valid diagram for every offered layout', () => {
    for (const layout of Object.keys(LAYOUT_LABELS)) {
      const cols = proportions(LAYOUT_GRID[layout] || '1fr');
      expect(cols.length).toBeGreaterThan(0);
      expect(cols.every((n) => Number.isFinite(n) && n > 0)).toBe(true);
    }
  });
});
