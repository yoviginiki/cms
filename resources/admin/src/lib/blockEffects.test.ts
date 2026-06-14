import { describe, it, expect } from 'vitest';
import { normalizeCardEffects, buildImageFilterCss, isRevealEnabled } from './blockEffects';
import { labelForPath } from './dtpConsistencyChecker';

describe('normalizeCardEffects', () => {
  it('returns disabled for null input', () => {
    expect(normalizeCardEffects(null).enabled).toBe(false);
  });

  it('returns disabled for empty object', () => {
    expect(normalizeCardEffects({}).enabled).toBe(false);
  });

  it('preserves enabled flag', () => {
    const result = normalizeCardEffects({ enabled: true });
    expect(result.enabled).toBe(true);
  });

  it('clamps scale to valid range', () => {
    const result = normalizeCardEffects({
      enabled: true,
      hover: { enabled: true, scale: 5 },
    });
    expect(result.hover!.scale).toBeLessThanOrEqual(1.2);
  });
});

describe('buildImageFilterCss', () => {
  it('returns empty when disabled', () => {
    expect(buildImageFilterCss({ enabled: false })).toBe('');
  });

  it('returns grayscale for grayscale preset', () => {
    const css = buildImageFilterCss({
      enabled: true,
      imageFilter: { enabled: true, preset: 'grayscale' },
    });
    expect(css).toContain('grayscale(100%)');
  });

  it('returns sepia for sepia preset', () => {
    const css = buildImageFilterCss({
      enabled: true,
      imageFilter: { enabled: true, preset: 'sepia' },
    });
    expect(css).toContain('sepia(80%)');
  });
});

describe('isRevealEnabled', () => {
  it('returns false when effects disabled', () => {
    expect(isRevealEnabled({ enabled: false })).toBe(false);
  });

  it('returns true when filter + reveal both enabled', () => {
    expect(isRevealEnabled({
      enabled: true,
      imageFilter: { enabled: true, preset: 'grayscale' },
      imageHoverReveal: { enabled: true, mode: 'fade' },
    })).toBe(true);
  });
});

describe('labelForPath', () => {
  it('maps known paths to human labels', () => {
    expect(labelForPath('settings.layoutMode')).toBe('Issue layout mode');
    expect(labelForPath('typography.color')).toBe('Text color');
    expect(labelForPath('content.src')).toBe('Image source');
  });
});
