import { describe, it, expect } from 'vitest';
import { gridTemplateForColumns } from './columnLayout';

describe('gridTemplateForColumns — track count follows real columns, not the preset', () => {
  it('renders ONE track for a single column even when layout preset says 1/2+1/2', () => {
    // Regression: a "Simple Post" template row had layout 1/2+1/2 (or none,
    // which defaulted to 1/2+1/2) but only ONE column block. The editor drew a
    // second, empty, undroppable phantom column — you could not drag anything
    // into it. The track count must follow the real column count.
    expect(gridTemplateForColumns(undefined, '1/2+1/2', 1)).toBe('12fr');
    expect(gridTemplateForColumns(undefined, undefined, 1)).toBe('12fr');
  });

  it('renders two even tracks for a real two-column 1/2+1/2 row', () => {
    expect(gridTemplateForColumns(undefined, '1/2+1/2', 2)).toBe('6fr 6fr');
  });

  it('honours the layout preset ratios (1/3 + 2/3)', () => {
    expect(gridTemplateForColumns(undefined, '1/3+2/3', 2)).toBe('4fr 8fr');
  });

  it('honours explicit col_spans over the preset', () => {
    expect(gridTemplateForColumns([3, 9], '1/2+1/2', 2)).toBe('3fr 9fr');
  });

  it('never emits more tracks than columns (extra col_spans are truncated + rebalanced)', () => {
    // Three spans but only one real column -> a single full-width track.
    expect(gridTemplateForColumns([4, 4, 4], '1/3+1/3+1/3', 1)).toBe('12fr');
  });

  it('pads missing widths when there are more columns than spans', () => {
    const tpl = gridTemplateForColumns([6, 6], '1/2+1/2', 3);
    expect(tpl.split(' ')).toHaveLength(3);
  });

  it('clamps a zero/negative column count to a single track', () => {
    expect(gridTemplateForColumns(undefined, '1/2+1/2', 0)).toBe('12fr');
  });
});
