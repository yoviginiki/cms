/**
 * Text Threading Engine
 *
 * When text overflows a frame, excess content flows to the next frame
 * in the same thread (identified by threadId, ordered by threadOrder).
 *
 * Uses a hidden measurement div to calculate how much text fits in each frame,
 * then distributes content across threaded frames.
 */
import DOMPurify from 'dompurify';
import type { MagElement, MagPageData } from '@/types/magazine';

const TEXT_FRAME_TYPES = ['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'];
const SAFE_HTML_CONFIG = { ALLOWED_TAGS: ['p', 'br', 'b', 'i', 'u', 'em', 'strong', 'span', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'sub', 'sup', 'hr', 'div'], ALLOWED_ATTR: ['href', 'target', 'rel', 'class', 'style'], ALLOW_DATA_ATTR: false };

export interface ThreadedContent {
  frameId: string;
  visibleHtml: string;
  hasOverflow: boolean;
}

/**
 * Get all frames in a thread, sorted by threadOrder, across all pages.
 */
export function getThreadFrames(
  allPages: MagPageData[],
  threadId: string,
): MagElement[] {
  const frames: MagElement[] = [];
  for (const page of allPages) {
    for (const el of page.elements || []) {
      if (el.threadId === threadId && TEXT_FRAME_TYPES.includes(el.type)) {
        frames.push(el);
      }
    }
  }
  return frames.sort((a, b) => (a.threadOrder ?? 0) - (b.threadOrder ?? 0));
}

/**
 * Measure how much HTML content fits in a container of given dimensions.
 * Returns the HTML that fits and the remainder.
 *
 * Uses a hidden div to measure — works by progressively adding paragraphs/blocks
 * until overflow is detected.
 */
export function splitContentToFit(
  html: string,
  containerWidth: number,
  containerHeight: number,
  styles: {
    fontFamily?: string;
    fontSize?: number;
    fontWeight?: number;
    lineHeight?: number;
    padding?: string;
    columnCount?: number;
    columnGap?: number;
    columnFill?: string;
  },
): { fits: string; overflow: string } {
  // Sanitize HTML before any DOM manipulation
  const safeHtml = DOMPurify.sanitize(html, SAFE_HTML_CONFIG);

  const measure = document.createElement('div');
  measure.style.cssText = `
    position:fixed;top:-9999px;left:-9999px;visibility:hidden;
    width:${containerWidth}px;height:${containerHeight}px;
    overflow:hidden;
    font-family:${styles.fontFamily || 'Inter, system-ui'};
    font-size:${styles.fontSize || 14}px;
    font-weight:${styles.fontWeight || 400};
    line-height:${styles.lineHeight || 1.5};
    padding:${styles.padding || '8px'};
    column-count:${styles.columnCount || 1};
    column-gap:${styles.columnGap || 12}px;
    column-fill:${styles.columnFill || 'auto'};
  `;
  document.body.appendChild(measure);

  try {
    // Parse HTML into block-level chunks (paragraphs)
    const temp = document.createElement('div');
    temp.innerHTML = safeHtml;
    const blocks = Array.from(temp.children);

    if (blocks.length === 0) {
      measure.innerHTML = safeHtml;
      return { fits: safeHtml, overflow: '' };
    }

    let fitsHtml = '';
    let overflowStartIdx = blocks.length;

    for (let i = 0; i < blocks.length; i++) {
      const testHtml = fitsHtml + blocks[i].outerHTML;
      measure.innerHTML = testHtml;
      if (measure.scrollHeight > containerHeight + 2) {
        overflowStartIdx = i;
        break;
      }
      fitsHtml = testHtml;
    }

    const overflowHtml = blocks.slice(overflowStartIdx).map(b => b.outerHTML).join('');
    return { fits: fitsHtml, overflow: overflowHtml };
  } finally {
    measure.remove();
  }
}

/**
 * Distribute content across a thread of frames.
 * Returns map of frameId -> visible HTML content.
 */
export function distributeThreadContent(
  frames: MagElement[],
  sourceContent: string,
): Map<string, ThreadedContent> {
  const result = new Map<string, ThreadedContent>();
  let remainingContent = sourceContent;

  for (let i = 0; i < frames.length; i++) {
    const frame = frames[i];
    const data = frame.data as Record<string, any>;
    const typo = frame.typography;

    if (!remainingContent) {
      result.set(frame.id, { frameId: frame.id, visibleHtml: '', hasOverflow: false });
      continue;
    }

    const { fits, overflow } = splitContentToFit(
      remainingContent,
      frame.width,
      frame.height,
      {
        fontFamily: typo?.fontFamily,
        fontSize: typo?.fontSize,
        fontWeight: typo?.fontWeight,
        lineHeight: typo?.lineHeight,
        padding: data.textInset && typeof data.textInset === 'object'
          ? `${data.textInset.top ?? 8}px ${data.textInset.right ?? 8}px ${data.textInset.bottom ?? 8}px ${data.textInset.left ?? 8}px`
          : '8px',
        columnCount: data.columnsInFrame || 1,
        columnGap: data.columnGap || 12,
        columnFill: data.columnFill || 'auto',
      },
    );

    result.set(frame.id, {
      frameId: frame.id,
      visibleHtml: fits,  // Empty string if nothing fits (don't duplicate)
      hasOverflow: !!overflow && i === frames.length - 1,
    });

    // If nothing fit, pass everything to next frame (don't consume)
    remainingContent = fits ? overflow : remainingContent;
  }

  return result;
}
