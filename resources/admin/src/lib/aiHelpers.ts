/**
 * Sprint 9 — AI assistant helpers for content generation, validation, and quality review.
 */

// ═══════════════════════════════════════
// Configuration
// ═══════════════════════════════════════

export interface AiConfig {
  enabled: boolean;
  provider: string;
  model: string;
}

/** Check if AI features are available */
export function isAiEnabled(config: AiConfig | null): boolean {
  return !!config && config.enabled === true;
}

// ═══════════════════════════════════════
// Prompt context builders
// ═══════════════════════════════════════

/** Supported block types for AI-generated pages */
export const AI_SUPPORTED_BLOCK_TYPES = [
  'section', 'row', 'column',
  'heading', 'paragraph', 'text', 'rich-text',
  'image', 'button', 'hero', 'ctabanner',
  'featuregrid', 'gallery', 'contact-form',
  'latestposts', 'postgrid', 'testimonial',
  'divider', 'spacer',
] as const;

/** Build context for page-level AI operations */
export function buildPageContext(page: {
  title?: string;
  slug?: string;
  blocks?: any[];
  seo_meta?: any;
}): { title: string; slug: string; blockCount: number; hasContent: boolean; seoTitle: string; seoDescription: string } {
  return {
    title: page.title || '',
    slug: page.slug || '',
    blockCount: page.blocks?.length || 0,
    hasContent: (page.blocks?.length || 0) > 0,
    seoTitle: page.seo_meta?.title || '',
    seoDescription: page.seo_meta?.description || '',
  };
}

/** Build context for alt text generation from filename and surrounding content */
export function buildAltTextContext(
  filename: string,
  pageTitle?: string,
  blockContext?: string,
): string {
  const parts: string[] = [];
  const cleanName = filename.replace(/\.[^.]+$/, '').replace(/[-_]/g, ' ');
  parts.push(`Image filename: ${cleanName}`);
  if (pageTitle) parts.push(`Page: ${pageTitle}`);
  if (blockContext) parts.push(`Context: ${blockContext}`);
  return parts.join('. ');
}

// ═══════════════════════════════════════
// AI output validation
// ═══════════════════════════════════════

/** Validate AI-generated text output */
export function validateAiTextOutput(output: string): { valid: boolean; sanitized: string; warnings: string[] } {
  const warnings: string[] = [];
  let text = output || '';

  // Strip script/style tags
  if (/<script[\s>]/i.test(text)) {
    text = text.replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '');
    warnings.push('Removed script tags from AI output');
  }
  if (/<style[\s>]/i.test(text)) {
    text = text.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');
    warnings.push('Removed style tags from AI output');
  }

  // Strip event handlers
  if (/\son\w+\s*=/i.test(text)) {
    text = text.replace(/\son\w+\s*=\s*"[^"]*"/gi, '');
    text = text.replace(/\son\w+\s*=\s*'[^']*'/gi, '');
    warnings.push('Removed event handlers from AI output');
  }

  if (!text.trim()) {
    return { valid: false, sanitized: '', warnings: ['AI returned empty output'] };
  }

  return { valid: true, sanitized: text.trim(), warnings };
}

/** Validate AI-generated SEO metadata */
export function validateAiSeoOutput(output: any): { valid: boolean; data: { title: string; description: string; ogTitle: string; ogDescription: string }; warnings: string[] } {
  const warnings: string[] = [];
  const data = {
    title: '',
    description: '',
    ogTitle: '',
    ogDescription: '',
  };

  if (!output || typeof output !== 'object') {
    return { valid: false, data, warnings: ['AI returned invalid SEO data'] };
  }

  data.title = typeof output.title === 'string' ? output.title.slice(0, 70) : '';
  data.description = typeof output.description === 'string' ? output.description.slice(0, 200) : '';
  data.ogTitle = typeof output.og_title === 'string' ? output.og_title.slice(0, 70) : data.title;
  data.ogDescription = typeof output.og_description === 'string' ? output.og_description.slice(0, 250) : data.description;

  if (data.title.length > 60) warnings.push('SEO title exceeds recommended 60 characters');
  if (data.description.length > 160) warnings.push('Meta description exceeds recommended 160 characters');
  if (!data.title) warnings.push('No SEO title generated');
  if (!data.description) warnings.push('No meta description generated');

  return { valid: !!data.title, data, warnings };
}

/** Validate AI-generated block structure for page creation */
export function validateAiBlockPayload(blocks: any[]): { valid: boolean; errors: string[] } {
  const errors: string[] = [];

  if (!Array.isArray(blocks)) {
    return { valid: false, errors: ['Blocks must be an array'] };
  }

  for (let i = 0; i < blocks.length; i++) {
    const block = blocks[i];
    if (!block || typeof block !== 'object') {
      errors.push(`Block ${i}: must be an object`);
      continue;
    }
    if (!block.type) {
      errors.push(`Block ${i}: missing type`);
    } else if (!AI_SUPPORTED_BLOCK_TYPES.includes(block.type)) {
      errors.push(`Block ${i}: unsupported type "${block.type}"`);
    }
    if (!block.id) {
      errors.push(`Block ${i}: missing id`);
    }
  }

  return { valid: errors.length === 0, errors };
}

// ═══════════════════════════════════════
// Page quality review (non-AI checks)
// ═══════════════════════════════════════

export interface QualityCheck {
  category: string;
  label: string;
  severity: 'info' | 'warning' | 'important';
  message: string;
}

/** Run non-AI page quality checks */
export function reviewPageQuality(page: {
  title?: string;
  slug?: string;
  seo_meta?: any;
  blocks?: any[];
  status?: string;
}): QualityCheck[] {
  const checks: QualityCheck[] = [];
  const seo = page.seo_meta || {};
  const blocks = page.blocks || [];

  // Title
  if (!page.title) {
    checks.push({ category: 'structure', label: 'Page title', severity: 'important', message: 'Page has no title' });
  }

  // SEO
  if (!seo.title && !page.title) {
    checks.push({ category: 'seo', label: 'SEO title', severity: 'important', message: 'No SEO title or page title set' });
  }
  if (!seo.description) {
    checks.push({ category: 'seo', label: 'Meta description', severity: 'warning', message: 'Meta description is missing — affects search result appearance' });
  } else if (seo.description.length < 50) {
    checks.push({ category: 'seo', label: 'Meta description', severity: 'info', message: 'Meta description is short — aim for 140-160 characters' });
  }

  // OG
  if (!seo.og_image) {
    checks.push({ category: 'seo', label: 'OG image', severity: 'warning', message: 'No Open Graph image — social shares will have no preview image' });
  }

  // Content
  if (blocks.length === 0) {
    checks.push({ category: 'structure', label: 'Content', severity: 'important', message: 'Page has no content blocks' });
  }

  // H1 check
  const h1Count = countBlocksOfType(blocks, 'heading', (b: any) => b.data?.level === 'h1')
    + countBlocksOfType(blocks, 'hero');
  if (h1Count === 0) {
    checks.push({ category: 'structure', label: 'H1 heading', severity: 'warning', message: 'No H1 heading found — important for SEO' });
  } else if (h1Count > 1) {
    checks.push({ category: 'structure', label: 'H1 heading', severity: 'info', message: `Multiple H1 headings found (${h1Count}) — consider using only one` });
  }

  // Images without alt text
  const images = findBlocksOfType(blocks, 'image');
  const missingAlt = images.filter((b: any) => !b.data?.alt && !b.data?.alt_text);
  if (missingAlt.length > 0) {
    checks.push({ category: 'accessibility', label: 'Image alt text', severity: 'warning', message: `${missingAlt.length} image(s) missing alt text` });
  }

  // Empty blocks
  const emptyTexts = findBlocksOfType(blocks, 'paragraph').filter((b: any) => !b.data?.content?.trim());
  const emptyHeadings = findBlocksOfType(blocks, 'heading').filter((b: any) => !b.data?.text?.trim());
  if (emptyTexts.length + emptyHeadings.length > 0) {
    checks.push({ category: 'structure', label: 'Empty blocks', severity: 'info', message: `${emptyTexts.length + emptyHeadings.length} empty block(s) found` });
  }

  // Slug
  if (page.slug && page.slug.length > 60) {
    checks.push({ category: 'seo', label: 'URL slug', severity: 'info', message: 'URL slug is long — shorter slugs are preferred for SEO' });
  }

  return checks;
}

function findBlocksOfType(blocks: any[], type: string): any[] {
  const result: any[] = [];
  for (const b of blocks) {
    if (b.type === type) result.push(b);
    if (b.children) result.push(...findBlocksOfType(b.children, type));
  }
  return result;
}

function countBlocksOfType(blocks: any[], type: string, filter?: (b: any) => boolean): number {
  return findBlocksOfType(blocks, type).filter(filter || (() => true)).length;
}

// ═══════════════════════════════════════
// Rewrite presets
// ═══════════════════════════════════════

export const REWRITE_PRESETS = [
  { id: 'shorter', label: 'Make shorter', instruction: 'shorter' },
  { id: 'longer', label: 'Make longer', instruction: 'longer' },
  { id: 'simpler', label: 'Make simpler', instruction: 'simpler' },
  { id: 'formal', label: 'More formal', instruction: 'more formal' },
  { id: 'direct', label: 'More direct', instruction: 'more direct' },
  { id: 'grammar', label: 'Fix grammar', instruction: 'fix grammar' },
] as const;
