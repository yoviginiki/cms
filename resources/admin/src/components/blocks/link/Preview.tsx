import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { LinkIcon, LINK_ICON_BRAND, resolveLinkIcon } from './icons';

/** Same markup/look as resources/views/blocks/link.blade.php (not clickable while editing). */
export const LinkPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const d = block.data as Record<string, string | boolean | undefined>;
  const url = (d.linkUrl as string) || '';
  const text = ((d.text as string) || '').trim() || url || 'Link';
  const icon = resolveLinkIcon((d.icon as string) || 'auto', url);
  const iconColor = d.brandColor && icon ? LINK_ICON_BRAND[icon] : undefined;
  const underline = (d.underline as string) || 'hover';
  const right = d.iconPosition === 'right';
  const iconEl = icon ? <LinkIcon name={icon} color={iconColor} /> : null;

  return (
    <div style={{ textAlign: ((d.align as string) || 'left') as React.CSSProperties['textAlign'] }}>
      <span
        className={`inline-flex items-center gap-[.45em] ${underline === 'hover' ? 'hover:[&>span]:underline' : ''}`}
        style={{
          color: (d.color as string) || 'var(--color-link,var(--color-primary,#3b82f6))',
          ...(d.fontSize ? { fontSize: d.fontSize as string } : {}),
          ...(d.fontWeight ? { fontWeight: Number(d.fontWeight) } : {}),
        }}
      >
        {!right && iconEl}
        <span style={underline === 'always' ? { textDecoration: 'underline', textUnderlineOffset: '.2em' } : undefined}>{text}</span>
        {right && iconEl}
      </span>
      {!url && <div className="text-[10px] text-base-content/40 italic mt-1">No link set — choose one in the panel</div>}
    </div>
  );
};
