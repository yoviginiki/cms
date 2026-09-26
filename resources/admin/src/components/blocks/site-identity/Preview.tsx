import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { useSiteBranding } from './useSiteBranding';

export const SiteIdentityPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const d = block.data as { show?: string; showTagline?: boolean; size?: string; align?: string };
  const b = useSiteBranding();
  const justify = { left: 'flex-start', center: 'center', right: 'flex-end' }[(block.data as any).align as string] || 'flex-start';
  let show = d.show || 'auto';
  if (show === 'auto') show = b.logoUrl ? (b.logoShowName ? 'both' : 'logo') : 'name';
  if (!b.logoUrl && show === 'logo') show = 'name';
  const size = d.size || 'md';
  const logoH = ({ sm: 32, md: 44, lg: 60 } as Record<string, number>)[size] || 44;
  const nameSize = ({ sm: '1.1rem', md: '1.4rem', lg: '1.9rem' } as Record<string, string>)[size] || '1.4rem';

  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', justifyContent: justify }}>
      {(show === 'logo' || show === 'both') && b.logoUrl && <img src={b.logoUrl} alt="" style={{ height: logoH, width: 'auto' }} />}
      {(show === 'name' || show === 'both') && (
        <span style={{ display: 'flex', flexDirection: 'column', lineHeight: 1.2 }}>
          <span style={{ fontFamily: 'var(--font-heading, inherit)', fontWeight: 700, fontSize: nameSize }}>{b.name}</span>
          {d.showTagline && b.tagline && <span style={{ fontSize: '0.85rem', opacity: 0.75 }}>{b.tagline}</span>}
        </span>
      )}
    </div>
  );
};
