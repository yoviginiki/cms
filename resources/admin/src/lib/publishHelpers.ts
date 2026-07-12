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
    // Job stores total_warnings + per-page results (legacy deployments had flat arrays)
    warningsCount: meta.lighthouse_checks?.total_warnings ?? meta.lighthouse_checks?.warnings?.length ?? 0,
    errorsCount: extractLintResults(meta).reduce((n, r) => n + r.errors.length, 0),
    pagesTotal: meta.pages_total || 0,
    pagesBuilt: meta.pages_built || 0,
    triggeredBy: dep.triggered_by || null,
  };
}

export interface LintPageResult {
  page: string;
  warnings: string[];
  errors: string[];
}

/** Per-page SEO lint findings from deployment metadata (F5) — only pages with findings. */
export function extractLintResults(meta: any): LintPageResult[] {
  const results = meta?.lighthouse_checks?.results;
  if (!results || typeof results !== 'object') return [];
  return Object.entries(results)
    .map(([page, r]: [string, any]) => ({
      page,
      warnings: Array.isArray(r?.warnings) ? r.warnings : [],
      errors: Array.isArray(r?.errors) ? r.errors : [],
    }))
    .filter((r) => r.warnings.length > 0 || r.errors.length > 0);
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
    const warnCount = lh.total_warnings ?? lh.warnings?.length ?? 0;
    checks.push({
      label: 'HTML validation',
      status: (lh.all_passed ?? lh.passed) ? (warnCount ? 'warn' : 'pass') : 'warn',
      detail: warnCount ? `${warnCount} warnings` : 'OK',
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
