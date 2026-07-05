// ═══════════════════════════════════════════════════════════════════════════
// Preflight v2 (W3): publisher checks with jump-to. Pure function over store
// state — the panel calls runPreflight(pages, oversetThreads) and every issue
// carries pageNumber (+elementId) so a click can navigate straight to it.
// ═══════════════════════════════════════════════════════════════════════════
import type { MagPageData } from '@/types/magazine';

export interface PreflightIssue {
  severity: 'error' | 'warning';
  code: string;
  message: string;
  pageNumber: number;
  elementId?: string;
}

const TEXT_TYPES = new Set(['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame']);
const IMAGE_TYPES = new Set(['image_frame', 'circular_image', 'polygon_image', 'fullbleed_image', 'background_image']);

const textOf = (html: string) => String(html).replace(/<[^>]+>/g, ' ').replace(/&nbsp;/g, ' ').trim();

export function runPreflight(
  pages: MagPageData[],
  oversetThreads: Record<string, boolean>,
): PreflightIssue[] {
  const issues: PreflightIssue[] = [];
  const content = pages.filter((p) => !p.isMaster).sort((a, b) => a.pageNumber - b.pageNumber);

  // overset: badge the LAST frame of each overset chain
  const oversetIds = Object.keys(oversetThreads).filter((k) => oversetThreads[k]);
  for (const tid of oversetIds) {
    let last: { pageNumber: number; id: string; order: number } | null = null;
    for (const p of content) {
      for (const e of p.elements) {
        if (e.threadId === tid && (!last || (e.threadOrder ?? 0) > last.order)) {
          last = { pageNumber: p.pageNumber, id: e.id, order: e.threadOrder ?? 0 };
        }
      }
    }
    if (last) {
      issues.push({
        severity: 'error',
        code: 'overset',
        message: `Overset text — the story does not fit its frames (p.${last.pageNumber})`,
        pageNumber: last.pageNumber,
        elementId: last.id,
      });
    }
  }

  for (const p of content) {
    const pw = p.pageSize?.width || 595;
    const ph = p.pageSize?.height || 842;
    for (const e of p.elements) {
      if (e.visible === false) continue;
      const data = e.data as Record<string, any>;

      if (TEXT_TYPES.has(e.type) && !(data?._autoFlow)) {
        const words = textOf(data?.content || '');
        const hasImg = /<img/i.test(String(data?.content || ''));
        if (!words && !hasImg) {
          issues.push({ severity: 'warning', code: 'empty-text', message: `Empty ${e.type.replace(/_/g, ' ')} (p.${p.pageNumber})`, pageNumber: p.pageNumber, elementId: e.id });
        }
      }
      if (IMAGE_TYPES.has(e.type)) {
        if (!data?.src) {
          issues.push({ severity: 'error', code: 'no-image', message: `Image frame has no image (p.${p.pageNumber})`, pageNumber: p.pageNumber, elementId: e.id });
        } else if (!data?.alt) {
          issues.push({ severity: 'warning', code: 'no-alt', message: `Image without alt text (p.${p.pageNumber})`, pageNumber: p.pageNumber, elementId: e.id });
        }
      }
      if (e.type === 'video_frame' && !data?.url) {
        issues.push({ severity: 'warning', code: 'no-video', message: `Video frame has no URL (p.${p.pageNumber})`, pageNumber: p.pageNumber, elementId: e.id });
      }
      if (e.type === 'audio_player' && !data?.url) {
        issues.push({ severity: 'warning', code: 'no-audio', message: `Audio player has no track (p.${p.pageNumber})`, pageNumber: p.pageNumber, elementId: e.id });
      }
      // fully off-page = pasteboard staging: readers never see it
      if (e.x >= pw || e.y >= ph || e.x + e.width <= 0 || e.y + e.height <= 0) {
        issues.push({ severity: 'warning', code: 'pasteboard', message: `"${e.name || e.type}" is parked on the pasteboard and will not publish (p.${p.pageNumber})`, pageNumber: p.pageNumber, elementId: e.id });
      }
    }
  }

  const order = { error: 0, warning: 1 } as const;
  return issues.sort((a, b) => order[a.severity] - order[b.severity] || a.pageNumber - b.pageNumber);
}
