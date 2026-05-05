import type { MagElement } from '@/types/magazine';

export interface WrapExclusion {
  elementId: string;
  bounds: { x: number; y: number; width: number; height: number };
  shape: 'rect' | 'ellipse' | 'custom';
  customPath?: string;
}

/**
 * Calculate CSS shape-outside or clip regions for text wrapping.
 */
export function calculateTextWrapExclusions(
  textFrame: MagElement,
  allElements: MagElement[],
): WrapExclusion[] {
  const exclusions: WrapExclusion[] = [];
  const tf = { x: textFrame.x, y: textFrame.y, w: textFrame.width, h: textFrame.height };

  for (const el of allElements) {
    if (el.id === textFrame.id) continue;
    if (!el.textWrap || el.textWrap.type === 'none') continue;
    if (!el.visible) continue;

    // Check overlap with text frame
    const elBounds = { x: el.x, y: el.y, w: el.width, h: el.height };
    const overlaps =
      elBounds.x < tf.x + tf.w &&
      elBounds.x + elBounds.w > tf.x &&
      elBounds.y < tf.y + tf.h &&
      elBounds.y + elBounds.h > tf.y;

    if (!overlaps) continue;

    const wrap = el.textWrap;
    const offset = wrap.offset || { top: 0, right: 0, bottom: 0, left: 0 };

    exclusions.push({
      elementId: el.id,
      bounds: {
        x: el.x - offset.left - tf.x,
        y: el.y - offset.top - tf.y,
        width: el.width + offset.left + offset.right,
        height: el.height + offset.top + offset.bottom,
      },
      shape: wrap.type === 'object-shape' ? (el.type === 'ellipse' || el.type === 'circular_image' ? 'ellipse' : 'rect') : 'rect',
      customPath: wrap.customPath || undefined,
    });
  }

  return exclusions;
}

/**
 * Generate CSS for text wrapping exclusions.
 * Returns inline style to apply to the text frame content div.
 */
export function generateWrapCSS(exclusions: WrapExclusion[], frameWidth: number, frameHeight: number): string {
  if (exclusions.length === 0) return '';

  // For simple cases, use float-based exclusion zones
  // Complex cases would need CSS Shapes Level 2 or manual text splitting
  return exclusions.map(ex => {
    const relX = (ex.bounds.x / frameWidth) * 100;
    const relY = (ex.bounds.y / frameHeight) * 100;
    const relW = (ex.bounds.width / frameWidth) * 100;
    const relH = (ex.bounds.height / frameHeight) * 100;

    if (ex.shape === 'ellipse') {
      return `/* wrap around ${ex.elementId} */ `;
    }
    return `/* wrap rect ${ex.elementId}: ${relX.toFixed(1)}% ${relY.toFixed(1)}% ${relW.toFixed(1)}%x${relH.toFixed(1)}% */`;
  }).join('\n');
}
