import { describe, it, expect } from 'vitest';
import {
  validateSectionTemplate,
  validateInsertionPayload,
  validateExportManifest,
  validatePreviewToken,
  formatActivityEntry,
  hasMinimumRole,
  getRolePermissions,
  validateBackupManifest,
  generateReleaseChecklist,
} from './agencyHelpers';

describe('validateSectionTemplate', () => {
  it('accepts valid template', () => {
    const { valid } = validateSectionTemplate({ name: 'Hero', category: 'layout', blocks_data: [{ type: 'section' }] });
    expect(valid).toBe(true);
  });

  it('rejects missing name', () => {
    const { errors } = validateSectionTemplate({ blocks_data: [{ type: 'section' }] });
    expect(errors).toContain('Template name is required');
  });

  it('rejects empty blocks', () => {
    const { errors } = validateSectionTemplate({ name: 'X', blocks_data: [] });
    expect(errors).toContain('Template must contain at least one block');
  });

  it('rejects long name', () => {
    const { errors } = validateSectionTemplate({ name: 'A'.repeat(101), blocks_data: [{}] });
    expect(errors).toContain('Template name too long (max 100 characters)');
  });
});

describe('validateInsertionPayload', () => {
  it('accepts valid blocks', () => {
    expect(validateInsertionPayload([{ type: 'section', children: [] }]).valid).toBe(true);
  });

  it('rejects non-array', () => {
    expect(validateInsertionPayload('x' as any).valid).toBe(false);
  });

  it('rejects missing type', () => {
    const { errors } = validateInsertionPayload([{ data: {} }]);
    expect(errors[0]).toContain('missing type');
  });
});

describe('validateExportManifest', () => {
  it('accepts valid manifest', () => {
    const { valid } = validateExportManifest({
      version: '1.0', exportedAt: '2026-01-01', site: { name: 'Test', slug: 'test' },
    });
    expect(valid).toBe(true);
  });

  it('rejects null', () => {
    expect(validateExportManifest(null).valid).toBe(false);
  });

  it('detects sensitive data', () => {
    const { errors } = validateExportManifest({
      version: '1', exportedAt: 'now', site: { name: 'T', slug: 't' }, api_key: 'secret',
    });
    expect(errors.some(e => e.includes('API keys'))).toBe(true);
  });
});

describe('validatePreviewToken', () => {
  it('accepts UUID format', () => {
    expect(validatePreviewToken('550e8400-e29b-41d4-a716-446655440000')).toBe(true);
  });

  it('rejects empty', () => {
    expect(validatePreviewToken('')).toBe(false);
  });

  it('rejects short strings', () => {
    expect(validatePreviewToken('abc')).toBe(false);
  });
});

describe('formatActivityEntry', () => {
  it('formats known action', () => {
    const result = formatActivityEntry({ action: 'publish.succeeded', subjectType: 'site', timestamp: '' });
    expect(result.label).toBe('Published');
    expect(result.color).toBe('text-success');
  });

  it('handles unknown action', () => {
    const result = formatActivityEntry({ action: 'unknown.thing' as any, subjectType: 'x', timestamp: '' });
    expect(result.label).toBe('unknown.thing');
  });
});

describe('hasMinimumRole', () => {
  it('owner has all permissions', () => {
    expect(hasMinimumRole('owner', 'admin')).toBe(true);
    expect(hasMinimumRole('owner', 'editor')).toBe(true);
  });

  it('editor cannot admin', () => {
    expect(hasMinimumRole('editor', 'admin')).toBe(false);
  });

  it('client has limited access', () => {
    expect(hasMinimumRole('client', 'editor')).toBe(false);
  });
});

describe('getRolePermissions', () => {
  it('admin can publish and manage themes', () => {
    const perms = getRolePermissions('admin');
    expect(perms.canPublish).toBe(true);
    expect(perms.canManageThemes).toBe(true);
    expect(perms.canDeleteSite).toBe(false);
  });

  it('editor can edit and publish', () => {
    const perms = getRolePermissions('editor');
    expect(perms.canEditContent).toBe(true);
    expect(perms.canPublish).toBe(true);
    expect(perms.canManageUsers).toBe(false);
  });
});

describe('validateBackupManifest', () => {
  it('accepts valid manifest', () => {
    const { valid } = validateBackupManifest({
      version: '1', exportedAt: '2026-01-01', site: { name: 'My Site' },
    });
    expect(valid).toBe(true);
  });

  it('detects path traversal', () => {
    const { errors } = validateBackupManifest({
      version: '1', exportedAt: 'now', site: { name: '../etc/passwd' },
    });
    expect(errors.some(e => e.includes('Path traversal'))).toBe(true);
  });
});

describe('generateReleaseChecklist', () => {
  it('generates checks for new site', () => {
    const checks = generateReleaseChecklist({ pages_count: 0 });
    expect(checks.some(c => c.label === 'Pages created' && c.status === 'fail')).toBe(true);
  });

  it('passes for complete site', () => {
    const checks = generateReleaseChecklist({
      pages_count: 5,
      active_theme_id: 'abc',
      custom_domain: 'example.com',
      lastPublish: { status: 'live', completed_at: '2026-01-01' },
      missingAltCount: 0,
    });
    const fails = checks.filter(c => c.status === 'fail');
    expect(fails.length).toBe(0);
  });

  it('warns for missing theme', () => {
    const checks = generateReleaseChecklist({ pages_count: 3 });
    expect(checks.some(c => c.label === 'Theme configured' && c.status === 'warn')).toBe(true);
  });
});
