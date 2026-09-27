import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { SOCIAL_NETWORKS, socialIconPx, socialTitleStyle, SHADOWS, type SocialTitle } from './definition';
import { SOCIAL_ICONS } from './icons';

type Link = { network: string; url: string; label?: string; icon?: string };

export const SocialLinksPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const d = block.data as { links?: Link[]; style?: string; size?: string; color?: string; showLabels?: boolean; align?: string };
  const justify = { left: 'flex-start', center: 'center', right: 'flex-end' }[(block.data as any).align as string] || 'flex-start';
  const px = socialIconPx(block.data as Record<string, unknown>);
  const gap = typeof (block.data as any).gap === 'number' ? (block.data as any).gap : 8;
  const style = d.style || 'circle';
  const showLabels = style === 'text' || !!d.showLabels;
  const links = d.links || [];
  const iconBg = ((block.data as any).iconBg as string) || '';
  const shadow = SHADOWS[(block.data as any).shadow as string];
  if (!links.length) return <div className="text-xs text-base-content/40 p-2">Social Links — add networks in the block settings</div>;

  const list = (
    <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexWrap: 'wrap', gap, alignItems: 'center', justifyContent: justify }}>
      {links.map((l, i) => {
        const Icon = SOCIAL_ICONS[l.network] || SOCIAL_ICONS.website;
        const box = px + 16;
        const bg = iconBg === 'none' ? 'transparent' : (iconBg || 'var(--color-bg-alt,#f1f5f9)');
        const shape: React.CSSProperties = style === 'circle' ? { width: box, height: box, borderRadius: '50%', background: bg }
          : style === 'square' ? { width: box, height: box, borderRadius: 6, background: bg } : { minHeight: 36 };
        const hasBg = (style === 'circle' || style === 'square') && iconBg !== 'none';
        if (shadow && hasBg) shape.boxShadow = shadow[0];
        return (
          <li key={i} title={l.url ? l.url : 'No URL yet — will not be published'} style={{ opacity: l.url ? 1 : 0.4 }}>
            <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '0.4rem', color: d.color || 'inherit', ...(style === 'text' ? { minHeight: 36 } : shape) }}>
              {style !== 'text' && (l.icon
                ? <img src={l.icon} alt="" style={{ width: px, height: px, objectFit: 'contain', display: 'block', filter: shadow && !hasBg ? shadow[1] : undefined }} />
                : <Icon size={px} strokeWidth={2} aria-hidden style={{ filter: shadow && !hasBg ? shadow[1] : undefined }} />)}
              {showLabels && <span style={{ fontSize: '0.9rem' }}>{l.label || SOCIAL_NETWORKS[l.network] || 'Link'}</span>}
            </span>
          </li>
        );
      })}
    </ul>
  );

  const t = ((block.data as any).title || {}) as SocialTitle;
  if (!t.show || !(t.text || '').trim()) return list;
  const position = t.position || 'above';
  const inline = position === 'before' || position === 'after';
  const Tag = (['h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span'].includes(t.tag || '') ? t.tag : 'h3') as keyof React.JSX.IntrinsicElements;
  const textAlign = ({ 'flex-start': 'left', center: 'center', 'flex-end': 'right' } as Record<string, React.CSSProperties['textAlign']>)[justify];
  const title = <Tag style={{ ...(inline ? {} : { textAlign }), ...socialTitleStyle(t) }}>{t.text}</Tag>;
  const first = position === 'above' || position === 'before';
  return (
    <div style={{ display: 'flex', gap: t.gap ?? 8, ...(inline ? { flexWrap: 'wrap', alignItems: 'center', justifyContent: justify } : { flexDirection: 'column', alignItems: 'stretch' }) }}>
      {first && title}{list}{!first && title}
    </div>
  );
};
