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
import { buildShadowCss } from '@/lib/shadowStyles';
import type { ShadowCustom } from '@/lib/shadowStyles';
import { resolveCornerRadius } from '@/lib/spacingHelpers';

/** Sanitize a raw CSS dimension value — keep only safe characters. */
export function safeDim(v: unknown): string | undefined {
  if (!v) return undefined;
  const s = String(v).trim();
  return /^-?\d+(\.\d+)?(px|rem|em|%|vh|vw|auto|0)$/i.test(s) ? s : undefined;
}

/** Sanitize a CSS color value — hex, rgb, oklch, named. */
export function safeColor(v: unknown): string | undefined {
  if (!v) return undefined;
  const s = String(v).trim();
  if (/^#[0-9a-fA-F]{3,8}$/.test(s)) return s;
  if (/^(rgb|rgba|oklch|hsl|hsla)\([\d\s,.\/%]+\)$/i.test(s)) return s;
  if (/^[a-zA-Z]{3,20}$/.test(s)) return s; // named colors
  return undefined;
}

/** Text shadow presets — shared between block-level (VisualPanel) and element-level (heading, pullquote, etc.) */
export const TEXT_SHADOW_PRESETS: Record<string, string> = {
  sm: '0 1px 2px rgba(0,0,0,0.15)',
  md: '0 2px 4px rgba(0,0,0,0.25)',
  lg: '0 4px 8px rgba(0,0,0,0.4)',
  outline: '-1px -1px 0 rgba(0,0,0,0.3), 1px -1px 0 rgba(0,0,0,0.3), -1px 1px 0 rgba(0,0,0,0.3), 1px 1px 0 rgba(0,0,0,0.3)',
  glow: '0 0 10px rgba(255,255,255,0.8), 0 0 20px rgba(255,255,255,0.4)',
};

/** Resolve a text shadow preset key to a CSS value. Returns undefined for empty/none. */
export function resolveTextShadow(key: unknown): string | undefined {
  if (!key || key === 'none') return undefined;
  const s = String(key);
  return TEXT_SHADOW_PRESETS[s] || undefined;
}

const SHADOW_MAP: Record<string, string> = {
  sm: '0 1px 2px rgba(0,0,0,0.04)',
  md: '0 4px 12px rgba(0,0,0,0.06)',
  lg: '0 12px 32px rgba(0,0,0,0.10)',
  subtle: '0 1px 3px rgba(0,0,0,0.12)',
  medium: '0 8px 24px rgba(0,0,0,0.18)',
  large: '0 20px 40px rgba(0,0,0,0.24)',
  glow: '0 0 30px rgba(255,255,255,0.35)',
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
    if (safeDim(sp.gap)) css.gap = safeDim(sp.gap);
  }

  // Visual
  const vis = style.visual;
  if (vis) {
    // Background
    if (vis.backgroundGradient) {
      css.background = vis.backgroundGradient;
    } else if (safeColor(vis.backgroundColor)) {
      css.backgroundColor = safeColor(vis.backgroundColor);
    }
    if (vis.backgroundImage) {
      css.backgroundImage = `url(${vis.backgroundImage})`;
      css.backgroundSize = 'cover';
      css.backgroundPosition = 'center';
    }

    // Border
    if (vis.borderWidth && vis.borderColor) {
      const bw = safeDim(vis.borderWidth);
      const bc = safeColor(vis.borderColor);
      const bs = ['solid', 'dashed', 'dotted'].includes(vis.borderStyle || '') ? vis.borderStyle : 'solid';
      if (bw && bc) css.border = `${bw} ${bs} ${bc}`;
    }

    // Border radius — string (legacy) or per-corner object
    const resolvedRadius = resolveCornerRadius(vis.borderRadius);
    if (resolvedRadius) {
      css.borderRadius = resolvedRadius;
      css.overflow = 'hidden';
    }

    // Shadow — preset or custom
    if (vis.shadowMode === 'custom' && vis.shadowCustom) {
      const shadowCss = buildShadowCss('custom', '', vis.shadowCustom as ShadowCustom);
      if (shadowCss) css.boxShadow = shadowCss;
    } else if (vis.boxShadow && vis.boxShadow !== 'none') {
      const shadowCss = buildShadowCss('preset', vis.boxShadow, undefined);
      if (shadowCss) css.boxShadow = shadowCss;
    }

    // Overflow
    if (vis.overflow && vis.overflow !== 'visible') {
      css.overflow = vis.overflow;
    }

    // Text shadow
    if (vis.textShadow && vis.textShadow !== 'none') {
      if (vis.textShadow === 'custom') {
        const x = safeDim(vis.textShadowX) || '0px';
        const y = safeDim(vis.textShadowY) || '2px';
        const blur = safeDim(vis.textShadowBlur) || '4px';
        const color = safeColor(vis.textShadowColor) || 'rgba(0,0,0,0.3)';
        css.textShadow = `${x} ${y} ${blur} ${color}`;
      } else {
        css.textShadow = TEXT_SHADOW_PRESETS[vis.textShadow] || 'none';
      }
    }
  }

  // Typography
  const typo = style.typography;
  if (typo) {
    if (typo.fontFamily) css.fontFamily = typo.fontFamily;
    if (typo.fontSize) css.fontSize = typo.fontSize;
    if (typo.fontWeight) css.fontWeight = typo.fontWeight;
    if (typo.fontStyle) css.fontStyle = typo.fontStyle as any;
    if (typo.lineHeight) css.lineHeight = typo.lineHeight;
    if (typo.letterSpacing) css.letterSpacing = typo.letterSpacing;
    if (typo.wordSpacing) css.wordSpacing = typo.wordSpacing;
    if (typo.textAlign) css.textAlign = typo.textAlign;
    if (typo.textTransform) css.textTransform = typo.textTransform;
    if (typo.textColor) css.color = typo.textColor;
  }

  // Layout
  const lay = style.layout;
  if (lay) {
    if (safeDim(lay.width)) css.width = safeDim(lay.width);
    if (safeDim(lay.height)) css.height = safeDim(lay.height);
    if (safeDim(lay.minWidth)) css.minWidth = safeDim(lay.minWidth);
    if (safeDim(lay.minHeight)) css.minHeight = safeDim(lay.minHeight);
    if (safeDim(lay.maxWidth)) css.maxWidth = safeDim(lay.maxWidth);
    if (safeDim(lay.maxHeight)) css.maxHeight = safeDim(lay.maxHeight);
    if (lay.overflow && lay.overflow !== 'visible') css.overflow = lay.overflow;
    if (lay.zIndex !== undefined && lay.zIndex !== null) {
      const z = Math.max(-100, Math.min(9999, Math.round(Number(lay.zIndex))));
      if (Number.isFinite(z)) css.zIndex = z;
    }
    // Alignment margins only apply when no explicit spacing margins are set
    if (lay.alignment === 'center' && !sp?.marginLeft && !sp?.marginRight) { css.marginLeft = 'auto'; css.marginRight = 'auto'; }
    else if (lay.alignment === 'right' && !sp?.marginLeft) { css.marginLeft = 'auto'; css.marginRight = '0'; }
    else if (lay.alignment === 'left' && !sp?.marginRight) { css.marginLeft = '0'; css.marginRight = 'auto'; }
    if (lay.display && ['flex', 'grid', 'none'].includes(lay.display)) {
      css.display = lay.display;
      if (lay.display === 'flex') {
        if (lay.flexDirection && ['row', 'column'].includes(lay.flexDirection)) {
          css.flexDirection = lay.flexDirection as React.CSSProperties['flexDirection'];
        }
        if (lay.justifyContent && ['flex-start', 'center', 'flex-end', 'space-between'].includes(lay.justifyContent)) {
          css.justifyContent = lay.justifyContent;
        }
      }
    }
  }

  return css;
}

const ANIMATION_NAMES: Record<string, string> = {
  fade: 'block-fade',
  'slide-up': 'block-slide-up',
  'slide-down': 'block-slide-down',
  'slide-left': 'block-slide-left',
  'slide-right': 'block-slide-right',
  zoom: 'block-zoom',
  'scale-in': 'block-scale-in',
};

/**
 * Build background CSS from block.data bg_* fields (BackgroundEditor).
 */
export function buildBackgroundFromData(data?: Record<string, unknown>): React.CSSProperties {
  if (!data) return {};
  let bgType = data.bg_type as string;

  // Auto-detect bg_type if fields are set but type is missing
  if (!bgType || bgType === 'none') {
    if (data.bg_color) bgType = 'color';
    else if (data.bg_image) bgType = 'image';
    else if (data.bg_gradient_stops) bgType = 'gradient';
    else return {};
  }

  const css: React.CSSProperties = {};

  if (bgType === 'color' && data.bg_color) {
    const c = safeColor(data.bg_color as string);
    if (c) css.backgroundColor = c;
  }

  if (bgType === 'gradient' && data.bg_gradient_stops) {
    const stops = data.bg_gradient_stops as Array<{ color: string; position: number }>;
    const type = (data.bg_gradient_type as string) || 'linear';
    const angle = Number(data.bg_gradient_angle ?? 180);
    if (stops.length >= 2) {
      const stopsStr = stops.map(s => `${s.color} ${s.position}%`).join(', ');
      css.background = type === 'radial'
        ? `radial-gradient(circle, ${stopsStr})`
        : `linear-gradient(${angle}deg, ${stopsStr})`;
    }
  }

  if (bgType === 'image' && data.bg_image) {
    css.backgroundImage = `url(${data.bg_image})`;
    css.backgroundSize = (data.bg_image_size as string) || 'cover';
    css.backgroundPosition = (data.bg_image_position as string) || 'center center';
    css.backgroundRepeat = (data.bg_image_repeat as string) || 'no-repeat';
    if (data.bg_scroll_effect === 'fixed') {
      css.backgroundAttachment = 'fixed';
    }
  }

  return css;
}

/**
 * Build overlay style from block.data bg_overlay_* fields.
 * Returns null if no overlay needed, or a style object for an absolute overlay div.
 */
export function buildOverlayFromData(data?: Record<string, unknown>): React.CSSProperties | null {
  if (!data) return null;
  const bgType = data.bg_type as string;
  // Auto-detect image background when bg_type is missing but bg_image is set
  if (bgType !== 'image' && !data.bg_image) return null;

  const color = safeColor(data.bg_overlay_color as string);
  const opacity = Number(data.bg_overlay_opacity ?? 0);
  if (!color || opacity <= 0) return null;

  return {
    position: 'absolute',
    inset: '0',
    backgroundColor: color,
    opacity,
    pointerEvents: 'none',
    zIndex: 0,
  };
}

const VALID_EASINGS = ['linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out'];

/**
 * Build animation inline style from block.animation.
 */
export function buildAnimationStyle(animation?: AnimationProps): React.CSSProperties {
  if (!animation?.entrance || animation.entrance === 'none') return {};
  const name = ANIMATION_NAMES[animation.entrance];
  if (!name) return {};
  const rawDur = Number(animation.duration ?? 600);
  const rawDel = Number(animation.delay ?? 0);
  const dur = Number.isFinite(rawDur) ? Math.max(50, Math.min(3000, rawDur)) : 600;
  const del = Number.isFinite(rawDel) ? Math.max(0, Math.min(5000, rawDel)) : 0;
  const easing = VALID_EASINGS.includes(animation.easing || '') ? animation.easing! : 'ease-out';
  return {
    animationName: name,
    animationDuration: `${dur}ms`,
    animationDelay: `${del}ms`,
    animationTimingFunction: easing,
    animationFillMode: 'both' as const,
  };
}

/**
 * Build CSS class names from block.advanced.
 */
export function buildBlockClasses(advanced?: AdvancedProps, animation?: AnimationProps): string {
  let classes = '';
  if (advanced?.customClass) {
    classes += advanced.customClass.replace(/[^a-zA-Z0-9_\-\s]/g, '').trim();
  }
  // Hover effect CSS class
  if (animation?.hoverEffect && animation.hoverEffect !== 'none') {
    classes += ` block-hover-${animation.hoverEffect}`;
  }
  return classes.trim();
}

/** Validate and normalize a CSS dimension value. Returns empty string if invalid. */
export function normalizeDimension(v: unknown): string {
  const d = safeDim(v);
  return d ?? '';
}

/** Check if a value is a safe CSS dimension. */
export function isSafeCssDimension(v: unknown): boolean {
  return safeDim(v) !== undefined;
}

/** Check if a value is a safe CSS color. */
export function isSafeCssColor(v: unknown): boolean {
  return safeColor(v) !== undefined;
}

/** Normalize a shadow value to a CSS box-shadow string. Returns empty string if invalid. */
export function normalizeShadow(v: unknown): string {
  if (!v || v === 'none') return '';
  return SHADOW_MAP[String(v)] ?? '';
}

/** Normalize an animation entrance type. Returns empty string if not allowlisted. */
export function normalizeAnimation(v: unknown): string {
  if (!v || v === 'none') return '';
  return ANIMATION_NAMES[String(v)] ?? '';
}

/** Normalize a custom class string. Removes all unsafe characters. */
export function normalizeCustomClass(v: unknown): string {
  if (!v) return '';
  return String(v).replace(/[^a-zA-Z0-9_\-\s]/g, '').trim();
}
