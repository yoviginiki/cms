import { describe, it, expect } from 'vitest';
import {
  isAiEnabled,
  buildPageContext,
  buildAltTextContext,
  validateAiTextOutput,
  validateAiSeoOutput,
  validateAiBlockPayload,
  reviewPageQuality,
} from './aiHelpers';

describe('isAiEnabled', () => {
  it('returns false for null config', () => {
    expect(isAiEnabled(null)).toBe(false);
  });

  it('returns false when disabled', () => {
    expect(isAiEnabled({ enabled: false, provider: 'anthropic', model: 'claude' })).toBe(false);
  });

  it('returns true when enabled', () => {
    expect(isAiEnabled({ enabled: true, provider: 'anthropic', model: 'claude' })).toBe(true);
  });
});

describe('buildPageContext', () => {
  it('builds context from page data', () => {
    const ctx = buildPageContext({ title: 'About Us', slug: 'about', blocks: [{}, {}] as any });
    expect(ctx.title).toBe('About Us');
    expect(ctx.blockCount).toBe(2);
    expect(ctx.hasContent).toBe(true);
  });

  it('handles empty page', () => {
    const ctx = buildPageContext({});
    expect(ctx.title).toBe('');
    expect(ctx.blockCount).toBe(0);
    expect(ctx.hasContent).toBe(false);
  });
});

describe('buildAltTextContext', () => {
  it('builds context from filename', () => {
    const ctx = buildAltTextContext('team-photo-2024.jpg');
    expect(ctx).toContain('team photo 2024');
  });

  it('includes page title and block context', () => {
    const ctx = buildAltTextContext('hero.png', 'About Us', 'Company overview section');
    expect(ctx).toContain('About Us');
    expect(ctx).toContain('Company overview section');
  });
});

describe('validateAiTextOutput', () => {
  it('passes valid HTML', () => {
    const { valid, sanitized } = validateAiTextOutput('<p>Hello world</p>');
    expect(valid).toBe(true);
    expect(sanitized).toBe('<p>Hello world</p>');
  });

  it('strips script tags', () => {
    const { sanitized, warnings } = validateAiTextOutput('<p>Hi</p><script>alert("x")</script>');
    expect(sanitized).not.toContain('script');
    expect(warnings.length).toBeGreaterThan(0);
  });

  it('strips style tags', () => {
    const { sanitized, warnings } = validateAiTextOutput('<style>body{}</style><p>Hi</p>');
    expect(sanitized).not.toContain('style');
    expect(warnings).toContain('Removed style tags from AI output');
  });

  it('strips event handlers', () => {
    const { sanitized } = validateAiTextOutput('<p onclick="alert(1)">Hi</p>');
    expect(sanitized).not.toContain('onclick');
  });

  it('rejects empty output', () => {
    expect(validateAiTextOutput('').valid).toBe(false);
    expect(validateAiTextOutput('   ').valid).toBe(false);
  });
});

describe('validateAiSeoOutput', () => {
  it('validates good SEO data', () => {
    const { valid, data } = validateAiSeoOutput({
      title: 'About Us',
      description: 'Learn about our company and team.',
      og_title: 'About Our Team',
      og_description: 'Meet the people behind the product.',
    });
    expect(valid).toBe(true);
    expect(data.title).toBe('About Us');
    expect(data.ogTitle).toBe('About Our Team');
  });

  it('rejects null input', () => {
    expect(validateAiSeoOutput(null).valid).toBe(false);
  });

  it('truncates long values', () => {
    const { data } = validateAiSeoOutput({ title: 'A'.repeat(100), description: 'B'.repeat(300) });
    expect(data.title.length).toBeLessThanOrEqual(70);
    expect(data.description.length).toBeLessThanOrEqual(200);
  });

  it('warns for long title', () => {
    const { warnings } = validateAiSeoOutput({ title: 'A'.repeat(65), description: 'OK' });
    expect(warnings.some(w => w.includes('60 characters'))).toBe(true);
  });
});

describe('validateAiBlockPayload', () => {
  it('accepts valid blocks', () => {
    const { valid } = validateAiBlockPayload([
      { id: '1', type: 'heading', data: {} },
      { id: '2', type: 'paragraph', data: {} },
    ]);
    expect(valid).toBe(true);
  });

  it('rejects non-array', () => {
    expect(validateAiBlockPayload('not array' as any).valid).toBe(false);
  });

  it('rejects unsupported block types', () => {
    const { valid, errors } = validateAiBlockPayload([{ id: '1', type: 'evil-block', data: {} }]);
    expect(valid).toBe(false);
    expect(errors[0]).toContain('unsupported type');
  });

  it('rejects missing id', () => {
    const { errors } = validateAiBlockPayload([{ type: 'heading', data: {} }]);
    expect(errors[0]).toContain('missing id');
  });
});

describe('reviewPageQuality', () => {
  it('flags empty page', () => {
    const checks = reviewPageQuality({});
    expect(checks.some(c => c.label === 'Page title')).toBe(true);
    expect(checks.some(c => c.label === 'Content')).toBe(true);
  });

  it('flags missing meta description', () => {
    const checks = reviewPageQuality({ title: 'Hello', blocks: [{ type: 'heading', data: { text: 'Hi', level: 'h1' }, children: [] }] });
    expect(checks.some(c => c.label === 'Meta description')).toBe(true);
  });

  it('flags missing alt text on images', () => {
    const checks = reviewPageQuality({
      title: 'T',
      seo_meta: { description: 'Good desc' },
      blocks: [
        { type: 'image', data: { src: '/img.jpg' }, children: [] },
      ],
    });
    expect(checks.some(c => c.label === 'Image alt text')).toBe(true);
  });

  it('returns empty for well-formed page', () => {
    const checks = reviewPageQuality({
      title: 'About',
      slug: 'about',
      seo_meta: { title: 'About', description: 'A good description that is long enough.', og_image: '/og.jpg' },
      blocks: [
        { type: 'hero', data: {}, children: [] },
        { type: 'heading', data: { text: 'Section', level: 'h2' }, children: [] },
      ],
    });
    // Should have no important or warning items (hero counts as h1)
    const important = checks.filter(c => c.severity === 'important');
    expect(important.length).toBe(0);
  });
});
