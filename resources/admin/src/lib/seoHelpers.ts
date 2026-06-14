/**
 * Sprint 8 — SEO metadata helpers for admin UI.
 */

export interface SeoData {
  title: string;
  description: string;
  ogTitle: string;
  ogDescription: string;
  ogImage: string;
  canonical: string;
  noIndex: boolean;
  twitterCard: 'summary' | 'summary_large_image';
}

/** Check SEO title length and return status */
export function checkTitleLength(title: string): { status: 'good' | 'warn' | 'error'; message: string } {
  const len = (title || '').length;
  if (len === 0) return { status: 'error', message: 'Title is missing' };
  if (len < 20) return { status: 'warn', message: `Title is short (${len} chars). Aim for 50–60.` };
  if (len > 60) return { status: 'warn', message: `Title is long (${len} chars). Search engines may truncate after ~60.` };
  return { status: 'good', message: `Title length is good (${len} chars)` };
}

/** Check meta description length and return status */
export function checkDescriptionLength(description: string): { status: 'good' | 'warn' | 'error'; message: string } {
  const len = (description || '').length;
  if (len === 0) return { status: 'error', message: 'Description is missing' };
  if (len < 50) return { status: 'warn', message: `Description is short (${len} chars). Aim for 140–160.` };
  if (len > 160) return { status: 'warn', message: `Description is long (${len} chars). Search engines may truncate after ~160.` };
  return { status: 'good', message: `Description length is good (${len} chars)` };
}

/** Generate OG preview data with fallbacks */
export function generateOgPreviewData(
  pageData: { title?: string; slug?: string; seo_meta?: any; featured_image?: string },
  siteName?: string,
  siteDomain?: string,
): { title: string; description: string; image: string; url: string; siteName: string } {
  const seo = pageData.seo_meta || {};
  return {
    title: seo.og_title || seo.title || pageData.title || 'Untitled',
    description: seo.og_description || seo.description || '',
    image: seo.og_image || (pageData as any).featured_image || '',
    url: siteDomain ? `${siteDomain}/${pageData.slug || ''}` : `/${pageData.slug || ''}`,
    siteName: siteName || '',
  };
}

/** Validate OG data and return warnings */
export function validateOgData(og: { title: string; description: string; image: string }): string[] {
  const warnings: string[] = [];
  if (!og.title) warnings.push('Missing OG title — social shares will have no title');
  if (og.title && og.title.length > 70) warnings.push('OG title may be truncated on social platforms (>70 chars)');
  if (!og.description) warnings.push('Missing OG description — social shares will have no preview text');
  if (!og.image) warnings.push('Missing OG image — social shares will have no preview image');
  return warnings;
}

/** Validate redirect path/URL */
export function validateRedirect(from: string, to: string): string[] {
  const errors: string[] = [];
  if (!from) {
    errors.push('Source path is required');
  } else if (!from.startsWith('/')) {
    errors.push('Source path must start with /');
  }
  if (!to) {
    errors.push('Target URL is required');
  }
  if (from && to && from === to) {
    errors.push('Source and target cannot be the same (redirect loop)');
  }
  if (from && /[<>"{}|\\^`]/.test(from)) {
    errors.push('Source path contains invalid characters');
  }
  return errors;
}
