import { describe, it, expect } from 'vitest';
import { extractColorSwatches, DEFAULT_SWATCHES } from './themeSwatches';

describe('extractColorSwatches (W3)', () => {
  it('reads flat tokens and W3C document tokens, dedupes values', () => {
    const sw = extractColorSwatches({
      tokens: {
        'color-primary': '#E63B2E',
        'semantic.color.accent': { $type: 'color', $value: '#2563eb' },
        'color-duplicate': '#e63b2e',        // same as primary → deduped
        'font-heading': 'Inter',             // not a color key
        'color-broken': 'not-a-hex',         // invalid value
      },
    });
    expect(sw).toHaveLength(2);
    expect(sw[0]).toEqual({ name: 'primary', value: '#E63B2E' });
    expect(sw[1].value).toBe('#2563eb');
  });

  it('returns empty for missing/invalid config (caller falls back to defaults)', () => {
    expect(extractColorSwatches(null)).toEqual([]);
    expect(extractColorSwatches({})).toEqual([]);
    expect(DEFAULT_SWATCHES.length).toBeGreaterThan(4);
  });
});
