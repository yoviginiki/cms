import { describe, it, expect } from 'vitest';
import {
  formatActivityAction,
  validateBackupManifestFrontend,
  sanitizeMetadata,
} from './releaseHelpers';

describe('formatActivityAction', () => {
  it('formats known actions', () => {
    expect(formatActivityAction('publish.succeeded').label).toBe('Published');
    expect(formatActivityAction('publish.succeeded').color).toBe('badge-success');
  });

  it('formats publish.failed', () => {
    expect(formatActivityAction('publish.failed').color).toBe('badge-error');
  });

  it('handles unknown action gracefully', () => {
    const result = formatActivityAction('unknown.action');
    expect(result.label).toBe('unknown.action');
    expect(result.color).toBe('badge-ghost');
  });
});

describe('validateBackupManifestFrontend', () => {
  it('accepts valid manifest', () => {
    const { valid } = validateBackupManifestFrontend({
      schema_version: '1.0.0',
      exported_at: '2026-06-14T00:00:00Z',
      site: { name: 'Test', slug: 'test' },
    });
    expect(valid).toBe(true);
  });

  it('rejects null', () => {
    expect(validateBackupManifestFrontend(null).valid).toBe(false);
  });

  it('rejects missing schema_version', () => {
    const { errors } = validateBackupManifestFrontend({ exported_at: 'x', site: { name: 'x', slug: 'x' } });
    expect(errors).toContain('Missing schema_version');
  });

  it('detects API keys', () => {
    const { errors } = validateBackupManifestFrontend({
      schema_version: '1', exported_at: 'x', site: { name: 'x', slug: 'x' }, api_key: 'secret',
    });
    expect(errors.some(e => e.includes('API keys'))).toBe(true);
  });

  it('detects path traversal', () => {
    const { errors } = validateBackupManifestFrontend({
      schema_version: '1', exported_at: 'x', site: { name: '../etc', slug: 'x' },
    });
    expect(errors.some(e => e.includes('Path traversal'))).toBe(true);
  });
});

describe('sanitizeMetadata', () => {
  it('removes sensitive keys', () => {
    const result = sanitizeMetadata({ name: 'ok', api_key: 'secret', password: '123' });
    expect(result).toEqual({ name: 'ok' });
  });

  it('truncates long values', () => {
    const long = 'a'.repeat(600);
    const result = sanitizeMetadata({ content: long });
    expect((result.content as string).length).toBeLessThan(510);
    expect((result.content as string).endsWith('...')).toBe(true);
  });

  it('preserves normal data', () => {
    const result = sanitizeMetadata({ title: 'Hello', count: 5 });
    expect(result).toEqual({ title: 'Hello', count: 5 });
  });
});
