/**
 * Shared block style helpers.
 *
 * Converts block.style, block.animation, and block.advanced into
 * inline CSS and class names for editor Preview wrappers.
 *
 * Mirrors the logic in app/Domain/Publishing/Services/BlockStyleResolver.php
 * so that preview and published output stay in sync.
 */

import type { BlockStyleProps, AnimationProps, AdvancedProps } from '@/types/blocks';
import type React from 'react';

/** Sanitize a raw CSS dimension value — keep only safe characters. */
function safeDim(v: unknown): string | undefined {
  if (!v) return undefined;
  const s = String(v).trim();
  return /^-?\d+(\.\d+)?(px|rem|em|%|vh|vw|auto|0)$/i.test(s) ? s : undefined;
}

/** Sanitize a CSS color value — hex, rgb, oklch, named. */
function safeColor(v: unknown): string | undefined {
  if (!v) return undefined;
  const s = String(v).trim();
  if (/^#[0-9a-fA-F]{3,8}$/.test(s)) return s;
  if (/^(rgb|rgba|oklch|hsl|hsla)\([\d\s,.\/%]+\)$/i.test(s)) return s;
  if (/^[a-zA-Z]{3,20}$/.test(s)) return s; // named colors
  return undefined;
}

const SHADOW_MAP: Record<string, string> = {
  sm: '0 1px 2px rgba(0,0,0,0.04)',
  md: '0 4px 12px rgba(0,0,0,0.06)',
  lg: '0 12px 32px rgba(0,0,0,0.10)',
};

/**
 * Build inline CSS from block.style for the preview wrapper.
 */
export function buildBlockWrapperStyle(style?: BlockStyleProps): React.CSSProperties {
  if (!style) return {};
  const css: React.CSSProperties = {};

  // Spacing
  const sp = style.spacing;
  if (sp) {
    if (safeDim(sp.paddingTop)) css.paddingTop = safeDim(sp.paddingTop);
    if (safeDim(sp.paddingRight)) css.paddingRight = safeDim(sp.paddingRight);
    if (safeDim(sp.paddingBottom)) css.paddingBottom = safeDim(sp.paddingBottom);
    if (safeDim(sp.paddingLeft)) css.paddingLeft = safeDim(sp.paddingLeft);
    if (safeDim(sp.marginTop)) css.marginTop = safeDim(sp.marginTop);
    if (safeDim(sp.marginRight)) css.marginRight = safeDim(sp.marginRight);
    if (safeDim(sp.marginBottom)) css.marginBottom = safeDim(sp.marginBottom);
    if (safeDim(sp.marginLeft)) css.marginLeft = safeDim(sp.marginLeft);
  }

  // Visual
  const vis = style.visual;
  if (vis) {
    if (vis.borderWidth && vis.borderColor) {
      const bw = safeDim(vis.borderWidth);
      const bc = safeColor(vis.borderColor);
      const bs = ['solid', 'dashed', 'dotted'].includes(vis.borderStyle || '') ? vis.borderStyle : 'solid';
      if (bw && bc) css.border = `${bw} ${bs} ${bc}`;
    }
    if (safeDim(vis.borderRadius)) css.borderRadius = safeDim(vis.borderRadius);
    if (vis.boxShadow && vis.boxShadow !== 'none') {
      css.boxShadow = SHADOW_MAP[vis.boxShadow] || undefined;
    }
    if (vis.opacity !== undefined && vis.opacity < 1) {
      css.opacity = Math.max(0, Math.min(1, vis.opacity));
    }
  }

  return css;
}

const ANIMATION_NAMES: Record<string, string> = {
  fade: 'block-fade',
  'slide-up': 'block-slide-up',
  'slide-left': 'block-slide-left',
  'slide-right': 'block-slide-right',
  zoom: 'block-zoom',
};

/**
 * Build animation inline style from block.animation.
 */
export function buildAnimationStyle(animation?: AnimationProps): React.CSSProperties {
  if (!animation?.entrance || animation.entrance === 'none') return {};
  const name = ANIMATION_NAMES[animation.entrance];
  if (!name) return {};
  const dur = Math.max(50, Math.min(3000, animation.duration ?? 400));
  const del = Math.max(0, Math.min(5000, animation.delay ?? 0));
  return {
    animationName: name,
    animationDuration: `${dur}ms`,
    animationDelay: `${del}ms`,
    animationFillMode: 'both' as const,
  };
}

/**
 * Build CSS class names from block.advanced.
 */
export function buildBlockClasses(advanced?: AdvancedProps): string {
  if (!advanced?.customClass) return '';
  // Only allow safe class tokens
  return advanced.customClass.replace(/[^a-zA-Z0-9_\-\s]/g, '').trim();
}
