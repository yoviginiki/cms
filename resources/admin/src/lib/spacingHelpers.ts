/**
 * Helpers for resolving per-side (padding/margin) and per-corner (border-radius) values.
 * Supports backward compatibility with legacy single-string values.
 */

const safeDim = (v: string) =>
  /^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/.test(v.trim()) ? v.trim() : '';

/** Per-side spacing value */
export interface BoxSides {
  top?: string;
  right?: string;
  bottom?: string;
  left?: string;
}

/** Per-corner radius value */
export interface CornerRadii {
  topLeft?: string;
  topRight?: string;
  bottomRight?: string;
  bottomLeft?: string;
}

/**
 * Resolve a padding/margin value that may be:
 * - a per-side object { top, right, bottom, left }
 * - a legacy single string "2rem"
 * - undefined/null
 *
 * Returns a CSS padding/margin string with safe values.
 */
export function resolveBoxSpacing(val: unknown, fallback = ''): string {
  if (!val) return safeDim(fallback) || '';

  // Per-side object
  if (typeof val === 'object' && val !== null) {
    const obj = val as BoxSides;
    const t = safeDim(obj.top || '') || safeDim(fallback);
    const r = safeDim(obj.right || '') || safeDim(fallback);
    const b = safeDim(obj.bottom || '') || safeDim(fallback);
    const l = safeDim(obj.left || '') || safeDim(fallback);
    if (!t && !r && !b && !l) return '';
    // If all sides are equal, use shorthand
    if (t === r && r === b && b === l) return t;
    return `${t || '0'} ${r || '0'} ${b || '0'} ${l || '0'}`;
  }

  // Legacy single string
  if (typeof val === 'string') return safeDim(val) || safeDim(fallback) || '';

  return safeDim(fallback) || '';
}

/**
 * Resolve a border-radius value that may be:
 * - a per-corner object { topLeft, topRight, bottomRight, bottomLeft }
 * - a legacy single string "0.75rem"
 * - undefined/null
 *
 * Returns a CSS border-radius string with safe values.
 */
export function resolveCornerRadius(val: unknown, fallback = ''): string {
  if (!val) return safeDim(fallback) || '';

  // Per-corner object
  if (typeof val === 'object' && val !== null) {
    const obj = val as CornerRadii;
    const tl = safeDim(obj.topLeft || '') || safeDim(fallback);
    const tr = safeDim(obj.topRight || '') || safeDim(fallback);
    const br = safeDim(obj.bottomRight || '') || safeDim(fallback);
    const bl = safeDim(obj.bottomLeft || '') || safeDim(fallback);
    if (!tl && !tr && !br && !bl) return '';
    // If all corners are equal, use shorthand
    if (tl === tr && tr === br && br === bl) return tl;
    return `${tl || '0'} ${tr || '0'} ${br || '0'} ${bl || '0'}`;
  }

  // Legacy single string
  if (typeof val === 'string') return safeDim(val) || safeDim(fallback) || '';

  return safeDim(fallback) || '';
}
