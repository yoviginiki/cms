/**
 * Sprint 12 — Release helpers for activity log, backup validation, and release checks.
 */

// ═══════════════════════════════════════
// Activity Log Display
// ═══════════════════════════════════════

export interface ActivityLogEntry {
  id: string;
  action: string;
  subject_type: string | null;
  subject_id: string | null;
  user_id: string | null;
  site_id: string | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
}

/** Format activity log action for display */
export function formatActivityAction(action: string): { label: string; color: string } {
  const map: Record<string, { label: string; color: string }> = {
    'site.created': { label: 'Site created', color: 'badge-success' },
    'page.created': { label: 'Page created', color: 'badge-info' },
    'page.updated': { label: 'Page updated', color: 'badge-ghost' },
    'page.deleted': { label: 'Page deleted', color: 'badge-error' },
    'theme.applied': { label: 'Theme applied', color: 'badge-primary' },
    'template.applied': { label: 'Template applied', color: 'badge-primary' },
    'section.saved': { label: 'Section saved', color: 'badge-info' },
    'media.uploaded': { label: 'Media uploaded', color: 'badge-info' },
    'publish.started': { label: 'Publish started', color: 'badge-warning' },
    'publish.succeeded': { label: 'Published', color: 'badge-success' },
    'publish.failed': { label: 'Publish failed', color: 'badge-error' },
    'backup.exported': { label: 'Backup exported', color: 'badge-info' },
    'ai.applied': { label: 'AI applied', color: 'badge-primary' },
  };
  return map[action] || { label: action, color: 'badge-ghost' };
}

// ═══════════════════════════════════════
// Backup Manifest Validation (frontend)
// ═══════════════════════════════════════

export interface BackupManifest {
  schema_version: string;
  cms_version?: string;
  exported_at: string;
  site: { name: string; slug: string };
  pages?: any[];
  posts?: any[];
  menus?: any[];
  redirects?: any[];
  section_templates?: any[];
  theme?: { name: string; slug: string } | null;
  stats?: { pages_count: number; posts_count: number };
}

/** Validate backup manifest structure (frontend validation before sending to server) */
export function validateBackupManifestFrontend(manifest: any): { valid: boolean; errors: string[] } {
  const errors: string[] = [];
  if (!manifest || typeof manifest !== 'object') {
    return { valid: false, errors: ['Invalid manifest format'] };
  }
  if (!manifest.schema_version) errors.push('Missing schema_version');
  if (!manifest.exported_at) errors.push('Missing exported_at');
  if (!manifest.site?.name) errors.push('Missing site name');
  if (!manifest.site?.slug) errors.push('Missing site slug');

  // Security checks
  const json = JSON.stringify(manifest);
  if (json.includes('api_key')) errors.push('Manifest may contain API keys');
  if (json.includes('password')) errors.push('Manifest may contain passwords');
  if (json.includes('../')) errors.push('Path traversal detected');
  if (json.length > 50_000_000) errors.push('Manifest too large (>50MB)');

  return { valid: errors.length === 0, errors };
}

// ═══════════════════════════════════════
// Metadata Sanitization (frontend)
// ═══════════════════════════════════════

const FORBIDDEN_KEYS = ['password', 'api_key', 'secret', 'token', 'credential'];

/** Sanitize metadata object — remove sensitive keys, truncate long values */
export function sanitizeMetadata(metadata: Record<string, unknown>): Record<string, unknown> {
  const result: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(metadata)) {
    if (FORBIDDEN_KEYS.some(f => key.toLowerCase().includes(f))) continue;
    if (typeof value === 'string' && value.length > 500) {
      result[key] = value.slice(0, 500) + '...';
      continue;
    }
    result[key] = value;
  }
  return result;
}
