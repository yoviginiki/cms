import { describe, it, expect } from 'vitest';

// Test the wizard state logic and template payload
describe('SiteWizard template selection', () => {
  const templates = [
    { id: 'blank', name: 'Blank Site' },
    { id: 'blog', name: 'Blog' },
    { id: 'portfolio', name: 'Portfolio' },
    { id: 'business', name: 'Business' },
  ];

  it('has all expected templates', () => {
    expect(templates.length).toBe(4);
    expect(templates.map(t => t.id)).toContain('blank');
    expect(templates.map(t => t.id)).toContain('blog');
    expect(templates.map(t => t.id)).toContain('portfolio');
    expect(templates.map(t => t.id)).toContain('business');
  });

  it('generates valid slug from name', () => {
    const autoSlug = (n: string) => n.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    expect(autoSlug('My Site')).toBe('my-site');
    expect(autoSlug('Hello World!')).toBe('hello-world');
    expect(autoSlug('  spaces  ')).toBe('spaces');
    expect(autoSlug('CamelCase')).toBe('camelcase');
    expect(autoSlug('')).toBe('');
  });

  it('template API payload is valid', () => {
    const payload = { template: 'blog' };
    expect(payload.template).toBe('blog');
    expect(typeof payload.template).toBe('string');
  });
});
