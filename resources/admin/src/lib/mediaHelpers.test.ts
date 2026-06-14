import { describe, it, expect } from 'vitest';
import {
  normalizeImageMetadata,
  isAltTextMissing,
  altTextWarning,
  formatFileSize,
  formatDimensions,
  focalPointToObjectPosition,
  isImageMimeType,
} from './mediaHelpers';

describe('normalizeImageMetadata', () => {
  it('returns defaults for null', () => {
    const m = normalizeImageMetadata(null);
    expect(m.altText).toBe('');
    expect(m.focalPointX).toBe(50);
    expect(m.focalPointY).toBe(50);
  });

  it('extracts alt_text field', () => {
    expect(normalizeImageMetadata({ alt_text: 'A photo' }).altText).toBe('A photo');
  });

  it('extracts dimensions from nested object', () => {
    const m = normalizeImageMetadata({ dimensions: { width: 800, height: 600 } });
    expect(m.width).toBe(800);
    expect(m.height).toBe(600);
  });

  it('clamps focal point to 0-100', () => {
    const m = normalizeImageMetadata({ focal_point_x: -10, focal_point_y: 200 });
    expect(m.focalPointX).toBe(0);
    expect(m.focalPointY).toBe(100);
  });

  it('uses original_name as title fallback', () => {
    expect(normalizeImageMetadata({ original_name: 'photo.jpg' }).title).toBe('photo.jpg');
  });
});

describe('isAltTextMissing', () => {
  it('returns true for empty alt text', () => {
    expect(isAltTextMissing({ alt_text: '' })).toBe(true);
    expect(isAltTextMissing({ alt_text: '  ' })).toBe(true);
    expect(isAltTextMissing({})).toBe(true);
  });

  it('returns false for present alt text', () => {
    expect(isAltTextMissing({ alt_text: 'A photo' })).toBe(false);
  });
});

describe('altTextWarning', () => {
  it('returns warning for missing alt text', () => {
    expect(altTextWarning({ alt_text: '' })).toContain('Missing alt text');
  });

  it('returns null for present alt text', () => {
    expect(altTextWarning({ alt_text: 'Good alt' })).toBeNull();
  });

  it('returns null for null asset', () => {
    expect(altTextWarning(null)).toBeNull();
  });
});

describe('formatFileSize', () => {
  it('formats bytes', () => {
    expect(formatFileSize(0)).toBe('0 B');
    expect(formatFileSize(500)).toBe('500 B');
  });

  it('formats KB', () => {
    expect(formatFileSize(1024)).toBe('1.0 KB');
    expect(formatFileSize(2560)).toBe('2.5 KB');
  });

  it('formats MB', () => {
    expect(formatFileSize(1048576)).toBe('1.0 MB');
  });
});

describe('formatDimensions', () => {
  it('formats known dimensions', () => {
    expect(formatDimensions(800, 600)).toBe('800 × 600');
  });

  it('returns Unknown for null', () => {
    expect(formatDimensions(null, null)).toBe('Unknown');
  });
});

describe('focalPointToObjectPosition', () => {
  it('returns center for defaults', () => {
    expect(focalPointToObjectPosition(50, 50)).toBe('50% 50%');
  });

  it('returns clamped values', () => {
    expect(focalPointToObjectPosition(0, 100)).toBe('0% 100%');
  });
});

describe('isImageMimeType', () => {
  it('returns true for images', () => {
    expect(isImageMimeType('image/jpeg')).toBe(true);
    expect(isImageMimeType('image/png')).toBe(true);
  });

  it('returns false for non-images', () => {
    expect(isImageMimeType('application/pdf')).toBe(false);
    expect(isImageMimeType('')).toBe(false);
  });
});
