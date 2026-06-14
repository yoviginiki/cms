/**
 * Sprint 10 — Agency/client workflow helpers.
 * Section templates, export packs, activity log, permissions, backup, release checklist.
 */

// ═══════════════════════════════════════
// Section Template Validation
// ═══════════════════════════════════════

export interface SectionTemplate {
  name: string;
  description?: string;
  category: string;
  blocks_data: any[];
  is_system?: boolean;
}

/** Validate a section template for saving */
export function validateSectionTemplate(tpl: any): { valid: boolean; errors: string[] } {
  const errors: string[] = [];
  if (!tpl || typeof tpl !== 'object') {
    return { valid: false, errors: ['Template must be an object'] };
  }
  if (!tpl.name || typeof tpl.name !== 'string' || tpl.name.trim().length === 0) {
    errors.push('Template name is required');
  }
  if (tpl.name && tpl.name.length > 100) {
    errors.push('Template name too long (max 100 characters)');
  }
  if (!Array.isArray(tpl.blocks_data) || tpl.blocks_data.length === 0) {
    errors.push('Template must contain at least one block');
  }
  if (tpl.blocks_data && tpl.blocks_data.length > 50) {
    errors.push('Template has too many blocks (max 50)');
  }
  return { valid: errors.length === 0, errors };
}

/** Validate insertion payload from a section template */
export function validateInsertionPayload(blocks: any[]): { valid: boolean; errors: string[] } {
  const errors: string[] = [];
  if (!Array.isArray(blocks)) {
    return { valid: false, errors: ['Payload must be an array'] };
  }
  for (let i = 0; i < blocks.length; i++) {
    const b = blocks[i];
    if (!b || typeof b !== 'object') { errors.push(`Block ${i}: invalid`); continue; }
    if (!b.type || typeof b.type !== 'string') errors.push(`Block ${i}: missing type`);
    if (b.children && !Array.isArray(b.children)) errors.push(`Block ${i}: children must be array`);
  }
  return { valid: errors.length === 0, errors };
}

// ═══════════════════════════════════════
// Export Pack Validation
// ════════════════════════════════════��══

export interface ExportPackManifest {
  version: string;
  exportedAt: string;
  site: { name: string; slug: string };
  theme?: { name: string; slug: string; tokens: any };
  starterTemplate?: string;
  sectionPresets?: string[];
  pageCount?: number;
  postCount?: number;
}

/** Validate an export pack manifest */
export function validateExportManifest(manifest: any): { valid: boolean; errors: string[] } {
  const errors: string[] = [];
  if (!manifest || typeof manifest !== 'object') {
    return { valid: false, errors: ['Manifest must be an object'] };
  }
  if (!manifest.version) errors.push('Missing version');
  if (!manifest.exportedAt) errors.push('Missing exportedAt timestamp');
  if (!manifest.site?.name) errors.push('Missing site name');
  if (!manifest.site?.slug) errors.push('Missing site slug');
  // Check for forbidden content
  if (JSON.stringify(manifest).includes('password')) errors.push('Manifest may contain sensitive data');
  if (JSON.stringify(manifest).includes('api_key')) errors.push('Manifest may contain API keys');
  return { valid: errors.length === 0, errors };
}

// ═══════════════════════════════════════
// Preview Token Validation
// ═══════════════════════════════════════

/** Validate preview token format */
export function validatePreviewToken(token: string): boolean {
  if (!token || typeof token !== 'string') return false;
  // UUID format or base64 encoded token
  return /^[a-f0-9-]{36}$/.test(token) || /^[A-Za-z0-9+/=]{20,}$/.test(token);
}

// ═══════════════════════════════════════
// Activity Log Helpers
// ═══════════════════════════════════════

export type ActivityAction =
  | 'site.created' | 'site.deleted'
  | 'page.created' | 'page.updated' | 'page.deleted'
  | 'post.created' | 'post.updated' | 'post.deleted'
  | 'theme.applied' | 'template.applied'
  | 'publish.started' | 'publish.succeeded' | 'publish.failed'
  | 'section.saved' | 'section.deleted'
  | 'media.uploaded' | 'media.deleted'
  | 'ai.suggestion_applied';

export interface ActivityEntry {
  action: ActivityAction;
  subjectType: string;
  subjectId?: string;
  siteId?: string;
  actorId?: string;
  metadata?: Record<string, unknown>;
  timestamp: string;
}

/** Format an activity entry for display */
export function formatActivityEntry(entry: ActivityEntry): { label: string; icon: string; color: string } {
  const map: Record<string, { label: string; icon: string; color: string }> = {
    'site.created': { label: 'Site created', icon: 'Globe', color: 'text-success' },
    'page.created': { label: 'Page created', icon: 'FileText', color: 'text-info' },
    'page.updated': { label: 'Page updated', icon: 'FileText', color: 'text-base-content/60' },
    'page.deleted': { label: 'Page deleted', icon: 'Trash2', color: 'text-error' },
    'post.created': { label: 'Post created', icon: 'Newspaper', color: 'text-info' },
    'post.updated': { label: 'Post updated', icon: 'Newspaper', color: 'text-base-content/60' },
    'theme.applied': { label: 'Theme applied', icon: 'Palette', color: 'text-primary' },
    'publish.started': { label: 'Publish started', icon: 'Upload', color: 'text-warning' },
    'publish.succeeded': { label: 'Published', icon: 'CheckCircle', color: 'text-success' },
    'publish.failed': { label: 'Publish failed', icon: 'XCircle', color: 'text-error' },
    'section.saved': { label: 'Section saved', icon: 'BookMarked', color: 'text-primary' },
    'media.uploaded': { label: 'Media uploaded', icon: 'Image', color: 'text-info' },
    'ai.suggestion_applied': { label: 'AI suggestion applied', icon: 'Sparkles', color: 'text-primary' },
  };
  return map[entry.action] || { label: entry.action, icon: 'Activity', color: 'text-base-content/40' };
}

// ═══════════════════════════════════════
// Permission Helpers
// ══════════════════════════════��════════

export type UserRole = 'owner' | 'admin' | 'editor' | 'designer' | 'client';

const ROLE_HIERARCHY: Record<UserRole, number> = {
  owner: 100,
  admin: 80,
  editor: 60,
  designer: 50,
  client: 20,
};

/** Check if a role has minimum permission level */
export function hasMinimumRole(userRole: string, requiredRole: UserRole): boolean {
  const userLevel = ROLE_HIERARCHY[userRole as UserRole] ?? 0;
  const requiredLevel = ROLE_HIERARCHY[requiredRole] ?? 100;
  return userLevel >= requiredLevel;
}

/** Get permissions for a role */
export function getRolePermissions(role: UserRole): { canPublish: boolean; canDeleteSite: boolean; canManageThemes: boolean; canManageUsers: boolean; canRollback: boolean; canEditContent: boolean } {
  return {
    canPublish: hasMinimumRole(role, 'editor'),
    canDeleteSite: hasMinimumRole(role, 'owner'),
    canManageThemes: hasMinimumRole(role, 'admin'),
    canManageUsers: hasMinimumRole(role, 'admin'),
    canRollback: hasMinimumRole(role, 'admin'),
    canEditContent: hasMinimumRole(role, 'editor'),
  };
}

// ═══════════════════════════════════════
// Backup Manifest Validation
// ═══════════════════════════════════════

export interface BackupManifest {
  version: string;
  exportedAt: string;
  site: { name: string; slug: string; id?: string };
  pages?: number;
  posts?: number;
  menus?: number;
  redirects?: number;
  sectionTemplates?: number;
  theme?: string;
}

/** Validate a backup manifest for import */
export function validateBackupManifest(manifest: any): { valid: boolean; errors: string[] } {
  const errors: string[] = [];
  if (!manifest || typeof manifest !== 'object') {
    return { valid: false, errors: ['Manifest must be an object'] };
  }
  if (!manifest.version) errors.push('Missing version');
  if (!manifest.exportedAt) errors.push('Missing export timestamp');
  if (!manifest.site?.name) errors.push('Missing site name');
  // Security checks
  const json = JSON.stringify(manifest);
  if (json.includes('../') || json.includes('..\\\\')) errors.push('Path traversal detected');
  if (json.length > 10_000_000) errors.push('Manifest too large (>10MB)');
  return { valid: errors.length === 0, errors };
}

// ═════════════════════��═════════════════
// Release Checklist
// ═══════════════════════════════════════

export interface ReleaseCheckItem {
  label: string;
  status: 'pass' | 'fail' | 'warn' | 'manual';
  detail?: string;
}

/** Generate release readiness checklist */
export function generateReleaseChecklist(site: {
  name?: string;
  pages_count?: number;
  posts_count?: number;
  active_theme_id?: string;
  custom_domain?: string;
  settings?: any;
  lastPublish?: { status?: string; completed_at?: string } | null;
  seoMeta?: { title?: string; description?: string };
  missingAltCount?: number;
}): ReleaseCheckItem[] {
  const checks: ReleaseCheckItem[] = [];

  // Pages exist
  checks.push({
    label: 'Pages created',
    status: (site.pages_count || 0) > 0 ? 'pass' : 'fail',
    detail: `${site.pages_count || 0} pages`,
  });

  // Theme set
  checks.push({
    label: 'Theme configured',
    status: site.active_theme_id ? 'pass' : 'warn',
    detail: site.active_theme_id ? 'Active theme set' : 'Using default theme',
  });

  // Custom domain
  checks.push({
    label: 'Custom domain',
    status: site.custom_domain ? 'pass' : 'manual',
    detail: site.custom_domain || 'Not configured',
  });

  // Last publish
  if (site.lastPublish) {
    checks.push({
      label: 'Last publish',
      status: site.lastPublish.status === 'live' ? 'pass' : 'warn',
      detail: site.lastPublish.status || 'Unknown',
    });
  } else {
    checks.push({ label: 'Published', status: 'fail', detail: 'Never published' });
  }

  // Alt text
  if (site.missingAltCount !== undefined) {
    checks.push({
      label: 'Image alt text',
      status: site.missingAltCount === 0 ? 'pass' : 'warn',
      detail: site.missingAltCount === 0 ? 'All images have alt text' : `${site.missingAltCount} missing`,
    });
  }

  return checks;
}
