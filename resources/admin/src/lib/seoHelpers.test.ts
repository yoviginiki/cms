import { describe, it, expect } from 'vitest';
import {
  checkTitleLength,
  checkDescriptionLength,
  generateOgPreviewData,
  validateOgData,
  validateRedirect,
} from './seoHelpers';

describe('checkTitleLength', () => {
  it('returns error for empty title', () => {
    expect(checkTitleLength('').status).toBe('error');
  });

  it('returns warn for short title', () => {
    expect(checkTitleLength('Hi').status).toBe('warn');
  });

  it('returns good for ideal length', () => {
    expect(checkTitleLength('A Good Page Title For SEO Purposes Here').status).toBe('good');
  });

  it('returns warn for long title', () => {
    expect(checkTitleLength('A'.repeat(65)).status).toBe('warn');
  });
});

describe('checkDescriptionLength', () => {
  it('returns error for empty', () => {
    expect(checkDescriptionLength('').status).toBe('error');
  });

  it('returns warn for short', () => {
    expect(checkDescriptionLength('Too short').status).toBe('warn');
  });

  it('returns good for ideal length', () => {
    const desc = 'This is a good meta description that provides useful information about the page content and is the right length for search engines to display.';
    expect(checkDescriptionLength(desc).status).toBe('good');
  });

  it('returns warn for long', () => {
    expect(checkDescriptionLength('A'.repeat(170)).status).toBe('warn');
  });
});

describe('generateOgPreviewData', () => {
  it('uses seo_meta fields first', () => {
    const og = generateOgPreviewData({
      title: 'Page Title',
      seo_meta: { og_title: 'OG Title', og_description: 'OG Desc', og_image: '/img.jpg' },
    });
    expect(og.title).toBe('OG Title');
    expect(og.description).toBe('OG Desc');
    expect(og.image).toBe('/img.jpg');
  });

  it('falls back to page title', () => {
    const og = generateOgPreviewData({ title: 'Page Title' });
    expect(og.title).toBe('Page Title');
  });

  it('falls back to featured_image', () => {
    const og = generateOgPreviewData({ title: 'T', featured_image: '/hero.jpg' } as any);
    expect(og.image).toBe('/hero.jpg');
  });

  it('builds URL from domain', () => {
    const og = generateOgPreviewData({ title: 'T', slug: 'about' }, 'My Site', 'https://example.com');
    expect(og.url).toBe('https://example.com/about');
    expect(og.siteName).toBe('My Site');
  });
});

describe('validateOgData', () => {
  it('returns warnings for missing fields', () => {
    const w = validateOgData({ title: '', description: '', image: '' });
    expect(w).toContain('Missing OG title — social shares will have no title');
    expect(w).toContain('Missing OG description — social shares will have no preview text');
    expect(w).toContain('Missing OG image — social shares will have no preview image');
  });

  it('returns empty for complete data', () => {
    const w = validateOgData({ title: 'Title', description: 'Desc', image: '/img.jpg' });
    expect(w.length).toBe(0);
  });

  it('warns for long title', () => {
    const w = validateOgData({ title: 'A'.repeat(75), description: 'Desc', image: '/img.jpg' });
    expect(w.length).toBe(1);
    expect(w[0]).toContain('truncated');
  });
});

describe('validateRedirect', () => {
  it('requires source path', () => {
    expect(validateRedirect('', '/target')).toContain('Source path is required');
  });

  it('requires / prefix', () => {
    expect(validateRedirect('old-page', '/new')).toContain('Source path must start with /');
  });

  it('requires target', () => {
    expect(validateRedirect('/old', '')).toContain('Target URL is required');
  });

  it('detects redirect loops', () => {
    expect(validateRedirect('/page', '/page')).toContain('Source and target cannot be the same (redirect loop)');
  });

  it('detects invalid characters', () => {
    expect(validateRedirect('/page<script>', '/new')).toContain('Source path contains invalid characters');
  });

  it('accepts valid redirect', () => {
    expect(validateRedirect('/old-page', '/new-page')).toEqual([]);
  });
});
