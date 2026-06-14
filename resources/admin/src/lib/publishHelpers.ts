/**
 * Sprint 7 — Publishing helpers for admin UI.
 */

export type PublishStatus = 'never' | 'in_progress' | 'success' | 'failed' | 'warnings' | 'unpublished_changes';

export interface PublishLogEntry {
  id: string;
  status: string;
  type: string;
  startedAt: string | null;
  completedAt: string | null;
  duration: number | null;
  message: string;
  warningsCount: number;
  errorsCount: number;
  pagesTotal: number;
  pagesBuilt: number;
  triggeredBy: string | null;
}

/** Derive overall publish status from deployment data */
export function derivePublishStatus(
  lastDeployment: { status: string; completed_at?: string | null } | null,
  hasDirtyPages: boolean,
): PublishStatus {
  if (!lastDeployment) return 'never';
  if (['queued', 'building', 'deploying'].includes(lastDeployment.status)) return 'in_progress';
  if (lastDeployment.status === 'failed') return 'failed';
  if (lastDeployment.status === 'rolled_back') return 'failed';
  if (lastDeployment.status === 'live' && hasDirtyPages) return 'unpublished_changes';
  if (lastDeployment.status === 'live') return 'success';
  return 'never';
}

/** Format deployment into a publish log entry */
export function formatPublishLog(dep: any): PublishLogEntry {
  const meta = dep.metadata || {};
  const started = dep.started_at ? new Date(dep.started_at).getTime() : null;
  const completed = dep.completed_at ? new Date(dep.completed_at).getTime() : null;
  const duration = started && completed ? Math.round((completed - started) / 1000) : null;

  return {
    id: dep.id,
    status: dep.status || 'unknown',
    type: dep.type || 'full',
    startedAt: dep.started_at || null,
    completedAt: dep.completed_at || null,
    duration,
    message: dep.error_log || (dep.status === 'live' ? 'Published successfully' : ''),
    warningsCount: meta.lighthouse_checks?.warnings?.length || 0,
    errorsCount: meta.lighthouse_checks?.errors?.length || 0,
    pagesTotal: meta.pages_total || 0,
    pagesBuilt: meta.pages_built || 0,
    triggeredBy: dep.triggered_by || null,
  };
}

/** Format duration in seconds to human-readable */
export function formatDuration(seconds: number | null): string {
  if (seconds === null) return '';
  if (seconds < 60) return `${seconds}s`;
  const min = Math.floor(seconds / 60);
  const sec = seconds % 60;
  return sec > 0 ? `${min}m ${sec}s` : `${min}m`;
}

/** Validate domain format */
export function validateDomainFormat(domain: string): string[] {
  const errors: string[] = [];
  if (!domain) {
    errors.push('Domain is required');
    return errors;
  }
  if (domain.length > 253) errors.push('Domain too long (max 253 characters)');
  if (!/^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)*$/.test(domain)) {
    errors.push('Invalid domain format');
  }
  if (!domain.includes('.')) errors.push('Domain must have at least one dot');
  if (domain.startsWith('-') || domain.endsWith('-')) errors.push('Domain cannot start or end with a hyphen');
  return errors;
}

/** Generate verification checklist items from deployment metadata */
export function generateVerificationChecklist(meta: any): { label: string; status: 'pass' | 'fail' | 'warn' | 'skip'; detail?: string }[] {
  const checks: { label: string; status: 'pass' | 'fail' | 'warn' | 'skip'; detail?: string }[] = [];

  const pagesTotal = meta?.pages_total || 0;
  const pagesBuilt = meta?.pages_built || 0;
  checks.push({
    label: 'Pages generated',
    status: pagesBuilt > 0 && pagesBuilt >= pagesTotal ? 'pass' : pagesBuilt > 0 ? 'warn' : 'fail',
    detail: `${pagesBuilt}/${pagesTotal}`,
  });

  const lh = meta?.lighthouse_checks;
  if (lh) {
    checks.push({
      label: 'HTML validation',
      status: lh.passed ? 'pass' : 'warn',
      detail: lh.warnings?.length ? `${lh.warnings.length} warnings` : 'OK',
    });
  }

  const hasSitemap = meta?.sitemap_generated !== false;
  checks.push({ label: 'Sitemap generated', status: hasSitemap ? 'pass' : 'skip' });

  const hasRobots = meta?.robots_generated !== false;
  checks.push({ label: 'Robots.txt generated', status: hasRobots ? 'pass' : 'skip' });

  const hasFeed = meta?.feed_generated !== false;
  checks.push({ label: 'RSS feed generated', status: hasFeed ? 'pass' : 'skip' });

  return checks;
}
