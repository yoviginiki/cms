import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { SOCIAL_NETWORKS } from './definition';
import { SOCIAL_ICONS } from './icons';

type Link = { network: string; url: string; label?: string };

export const SocialLinksPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const d = block.data as { links?: Link[]; style?: string; size?: string; color?: string; showLabels?: boolean; align?: string };
  const justify = { left: 'flex-start', center: 'center', right: 'flex-end' }[(block.data as any).align as string] || 'flex-start';
  const px = ({ sm: 16, md: 20, lg: 26 } as Record<string, number>)[d.size || 'md'] || 20;
  const style = d.style || 'circle';
  const showLabels = style === 'text' || !!d.showLabels;
  const links = d.links || [];
  if (!links.length) return <div className="text-xs text-base-content/40 p-2">Social Links — add networks in the block settings</div>;

  return (
    <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexWrap: 'wrap', gap: '0.5rem', alignItems: 'center', justifyContent: justify }}>
      {links.map((l, i) => {
        const Icon = SOCIAL_ICONS[l.network] || SOCIAL_ICONS.website;
        const box = px + 16;
        const shape: React.CSSProperties = style === 'circle' ? { width: box, height: box, borderRadius: '50%', background: 'var(--color-bg-alt,#f1f5f9)' }
          : style === 'square' ? { width: box, height: box, borderRadius: 6, background: 'var(--color-bg-alt,#f1f5f9)' } : { minHeight: 36 };
        return (
          <li key={i} title={l.url ? l.url : 'No URL yet — will not be published'} style={{ opacity: l.url ? 1 : 0.4 }}>
            <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '0.4rem', color: d.color || 'inherit', ...(style === 'text' ? { minHeight: 36 } : shape) }}>
              {style !== 'text' && <Icon size={px} strokeWidth={2} aria-hidden />}
              {showLabels && <span style={{ fontSize: '0.9rem' }}>{l.label || SOCIAL_NETWORKS[l.network] || 'Link'}</span>}
            </span>
          </li>
        );
      })}
    </ul>
  );
};
