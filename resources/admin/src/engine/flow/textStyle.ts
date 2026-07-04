// ═══════════════════════════════════════════════════════════════════════════
// Shared text style builder — the ONE source of truth for text-frame CSS.
//
// The editor renderer (MagElementRenderer) and the flow measurer (DomMeasurer)
// both consume this, so the measurer can never drift from what is rendered
// (audit Part 1.2b: measurer ≠ renderer drift was a root cause of bad splits).
// ═══════════════════════════════════════════════════════════════════════════

import type { CSSProperties } from 'react';
import type { MagTypography } from '@/types/magazine';

export interface TextStyleOptions {
  /** include inset padding (renderer: yes; measurer measures the inset-adjusted box: no) */
  inset?: { top: number; right: number; bottom: number; left: number } | null;
  columns?: number;
  columnGap?: number;
  columnFill?: 'auto' | 'balance';
}

/** typography-only declarations shared by renderer and measurer */
export function buildTypographyStyle(typo: MagTypography | null | undefined): CSSProperties {
  return {
    fontFamily: typo?.fontFamily || 'Inter',
    fontSize: typo?.fontSize || 14,
    fontWeight: typo?.fontWeight || 400,
    fontStyle: typo?.fontStyle || 'normal',
    lineHeight: typo?.lineHeight || 1.5,
    letterSpacing: typo?.letterSpacing ? `${typo.letterSpacing}em` : undefined,
    textAlign: (typo?.textAlign || 'left') as CSSProperties['textAlign'],
    color: typo?.textColor || '#1a1a1a',
    textTransform: (typo?.textTransform && typo.textTransform !== 'small-caps'
      ? typo.textTransform
      : undefined) as CSSProperties['textTransform'],
    fontVariant: typo?.textTransform === 'small-caps' ? 'small-caps' : undefined,
    hyphens: typo?.hyphenation ? 'auto' : undefined,
    wordBreak: 'break-word' as CSSProperties['wordBreak'],
  };
}

/** full text-frame content style (renderer variant, with inset + columns) */
export function buildTextFrameStyle(
  typo: MagTypography | null | undefined,
  opts: TextStyleOptions = {},
): CSSProperties {
  const style: CSSProperties = { ...buildTypographyStyle(typo), width: '100%' };
  const inset = opts.inset;
  style.padding = inset
    ? `${inset.top ?? 8}px ${inset.right ?? 8}px ${inset.bottom ?? 8}px ${inset.left ?? 8}px`
    : '8px';
  if ((opts.columns || 1) > 1) {
    style.columnCount = opts.columns;
    style.columnGap = opts.columnGap || 12;
    style.columnFill = opts.columnFill === 'balance' ? 'balance' : 'auto';
  }
  return style;
}

/** cssText string for the measurer host (single column, no padding) */
export function buildMeasurerCss(typo: MagTypography | null | undefined): string {
  const s = buildTypographyStyle(typo);
  const parts = [
    `font-family:${s.fontFamily}`,
    `font-size:${typeof s.fontSize === 'number' ? `${s.fontSize}px` : s.fontSize}`,
    `font-weight:${s.fontWeight}`,
    `font-style:${s.fontStyle}`,
    `line-height:${s.lineHeight}`,
    `text-align:${s.textAlign}`,
    `word-break:break-word`,
    `padding:0`,
    `margin:0`,
    `border:0`,
  ];
  if (s.letterSpacing) parts.push(`letter-spacing:${s.letterSpacing}`);
  if (s.textTransform) parts.push(`text-transform:${s.textTransform}`);
  if (s.fontVariant) parts.push(`font-variant:${s.fontVariant}`);
  if (s.hyphens) parts.push(`hyphens:auto`, `-webkit-hyphens:auto`);
  return parts.join(';');
}

export function bodyLineHeightPx(typo: MagTypography | null | undefined): number {
  const fs = typo?.fontSize || 14;
  const lh = typo?.lineHeight || 1.5;
  return fs * lh;
}
