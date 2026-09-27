import type React from 'react';
import type { BlockDefinition } from '@/types/blocks';

export const SOCIAL_NETWORKS: Record<string, string> = {
  facebook: 'Facebook', instagram: 'Instagram', x: 'X (Twitter)', linkedin: 'LinkedIn', youtube: 'YouTube',
  tiktok: 'TikTok', github: 'GitHub', whatsapp: 'WhatsApp', telegram: 'Telegram', contact: 'Contact', email: 'Email', phone: 'Phone', website: 'Website',
};

/** Icon size in px: explicit iconSize wins over the sm/md/lg preset (same rule as social-links.blade.php). */
export function socialIconPx(d: Record<string, unknown>): number {
  const n = Number(d.iconSize);
  if (Number.isFinite(n) && n >= 10 && n <= 160) return Math.round(n);
  return ({ sm: 16, md: 20, lg: 26 } as Record<string, number>)[d.size as string] || 20;
}

export type SocialTitle = {
  show?: boolean; text?: string; position?: 'above' | 'below' | 'before' | 'after'; tag?: string;
  fontSize?: number | null; fontFamily?: string; fontWeight?: string; fontStyle?: string; textTransform?: string;
  letterSpacing?: number | null; color?: string; padding?: { top?: number; right?: number; bottom?: number; left?: number }; gap?: number | null;
};

export const SHADOWS: Record<string, [string, string]> = {
  // [box-shadow for a background shape, drop-shadow filter for a bare icon] — same as SocialIcons::SHADOWS
  sm: ['0 1px 3px rgba(0,0,0,.18)', 'drop-shadow(0 1px 2px rgba(0,0,0,.25))'],
  md: ['0 4px 10px rgba(0,0,0,.22)', 'drop-shadow(0 3px 5px rgba(0,0,0,.3))'],
  lg: ['0 10px 24px rgba(0,0,0,.28)', 'drop-shadow(0 8px 12px rgba(0,0,0,.35))'],
};

/** Inline style of the title — mirrors SocialIcons::title() in PHP. */
export function socialTitleStyle(t: SocialTitle): React.CSSProperties {
  const p = t.padding || {};
  const css: React.CSSProperties = { margin: 0 };
  if (t.fontSize) css.fontSize = t.fontSize;
  if (t.fontFamily) css.fontFamily = t.fontFamily;
  if (t.fontWeight) css.fontWeight = Number(t.fontWeight);
  if (t.fontStyle) css.fontStyle = t.fontStyle;
  if (t.textTransform) css.textTransform = t.textTransform as React.CSSProperties['textTransform'];
  if (typeof t.letterSpacing === 'number') css.letterSpacing = t.letterSpacing;
  if (t.color) css.color = t.color;
  if ((p.top || 0) + (p.right || 0) + (p.bottom || 0) + (p.left || 0)) css.padding = `${p.top || 0}px ${p.right || 0}px ${p.bottom || 0}px ${p.left || 0}px`;
  return css;
}

export const socialLinksDefinition: BlockDefinition = {
  type: 'social-links',
  category: 'navigation',
  label: 'Social Links',
  icon: 'Share2',
  defaultData: {
    links: [
      { network: 'facebook', url: '' },
      { network: 'instagram', url: '' },
    ],
    style: 'circle',
    size: 'md',
    color: '',
    showLabels: false,
    align: 'left',
  },
  allowsChildren: false,
};
