import { describe, it, expect } from 'vitest';
import {
  derivePublishStatus,
  formatPublishLog,
  formatDuration,
  validateDomainFormat,
  generateVerificationChecklist,
} from './publishHelpers';

describe('derivePublishStatus', () => {
  it('returns never when no deployment', () => {
    expect(derivePublishStatus(null, false)).toBe('never');
  });

  it('returns in_progress for building status', () => {
    expect(derivePublishStatus({ status: 'building' }, false)).toBe('in_progress');
  });

  it('returns in_progress for queued status', () => {
    expect(derivePublishStatus({ status: 'queued' }, false)).toBe('in_progress');
  });

  it('returns failed for failed status', () => {
    expect(derivePublishStatus({ status: 'failed' }, false)).toBe('failed');
  });

  it('returns success for live status without dirty pages', () => {
    expect(derivePublishStatus({ status: 'live' }, false)).toBe('success');
  });

  it('returns unpublished_changes for live with dirty pages', () => {
    expect(derivePublishStatus({ status: 'live' }, true)).toBe('unpublished_changes');
  });
});

describe('formatPublishLog', () => {
  it('formats a deployment into a log entry', () => {
    const dep = {
      id: '123',
      status: 'live',
      type: 'full',
      started_at: '2026-06-14T10:00:00Z',
      completed_at: '2026-06-14T10:00:30Z',
      error_log: null,
      metadata: { pages_total: 5, pages_built: 5 },
    };
    const log = formatPublishLog(dep);
    expect(log.id).toBe('123');
    expect(log.status).toBe('live');
    expect(log.duration).toBe(30);
    expect(log.pagesTotal).toBe(5);
    expect(log.message).toBe('Published successfully');
  });

  it('handles missing metadata', () => {
    const log = formatPublishLog({ id: '1', status: 'failed', error_log: 'oops' });
    expect(log.duration).toBeNull();
    expect(log.message).toBe('oops');
    expect(log.pagesTotal).toBe(0);
  });
});

describe('formatDuration', () => {
  it('returns empty for null', () => {
    expect(formatDuration(null)).toBe('');
  });

  it('formats seconds', () => {
    expect(formatDuration(45)).toBe('45s');
  });

  it('formats minutes and seconds', () => {
    expect(formatDuration(125)).toBe('2m 5s');
  });

  it('formats exact minutes', () => {
    expect(formatDuration(120)).toBe('2m');
  });
});

describe('validateDomainFormat', () => {
  it('accepts valid domain', () => {
    expect(validateDomainFormat('example.com')).toEqual([]);
  });

  it('accepts subdomain', () => {
    expect(validateDomainFormat('blog.example.com')).toEqual([]);
  });

  it('rejects empty', () => {
    expect(validateDomainFormat('')).toContain('Domain is required');
  });

  it('rejects no dot', () => {
    const errors = validateDomainFormat('localhost');
    expect(errors).toContain('Domain must have at least one dot');
  });

  it('rejects leading hyphen', () => {
    const errors = validateDomainFormat('-example.com');
    expect(errors.length).toBeGreaterThan(0);
  });
});

describe('generateVerificationChecklist', () => {
  it('generates pass for complete deployment', () => {
    const checks = generateVerificationChecklist({ pages_total: 5, pages_built: 5 });
    expect(checks[0].status).toBe('pass');
    expect(checks[0].detail).toBe('5/5');
  });

  it('generates fail for zero pages', () => {
    const checks = generateVerificationChecklist({ pages_total: 5, pages_built: 0 });
    expect(checks[0].status).toBe('fail');
  });

  it('generates warn for partial pages', () => {
    const checks = generateVerificationChecklist({ pages_total: 5, pages_built: 3 });
    expect(checks[0].status).toBe('warn');
  });

  it('includes sitemap/robots/feed checks', () => {
    const checks = generateVerificationChecklist({});
    const labels = checks.map(c => c.label);
    expect(labels).toContain('Sitemap generated');
    expect(labels).toContain('Robots.txt generated');
    expect(labels).toContain('RSS feed generated');
  });
});

describe('extractLintResults (F5)', () => {
  const meta = {
    lighthouse_checks: {
      all_passed: true,
      total_warnings: 3,
      results: {
        'page:home': { passed: true, warnings: ['Thin content: only 40 words'], errors: [] },
        'page:about': { passed: true, warnings: [], errors: [] },
        'post:hello': { passed: false, warnings: ['Missing canonical URL'], errors: ['Missing <title> tag'] },
        'site:internal-links': { passed: true, warnings: ['index.html: broken internal link /gone/'], errors: [] },
      },
    },
  };

  it('returns only pages with findings', async () => {
    const { extractLintResults } = await import('./publishHelpers');
    const results = extractLintResults(meta);
    expect(results.map((r) => r.page)).toEqual(['page:home', 'post:hello', 'site:internal-links']);
    expect(results[1].errors).toEqual(['Missing <title> tag']);
  });

  it('returns empty for missing metadata', async () => {
    const { extractLintResults } = await import('./publishHelpers');
    expect(extractLintResults(null)).toEqual([]);
    expect(extractLintResults({})).toEqual([]);
  });

  it('formatPublishLog reads total_warnings and sums errors from results', () => {
    const log = formatPublishLog({ id: '1', status: 'live', metadata: meta });
    expect(log.warningsCount).toBe(3);
    expect(log.errorsCount).toBe(1);
  });

  it('verification checklist warns when total_warnings present', () => {
    const checks = generateVerificationChecklist(meta);
    const html = checks.find((c) => c.label === 'HTML validation');
    expect(html?.status).toBe('warn');
    expect(html?.detail).toBe('3 warnings');
  });
});
