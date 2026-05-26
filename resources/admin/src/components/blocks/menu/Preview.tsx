import { useState } from 'react';
import { Menu, X } from 'lucide-react';
import type { BlockComponentProps } from '@/types/blocks';

interface CustomItem { label: string; url: string; target: string }

export const MenuPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const [hamburgerOpen, setHamburgerOpen] = useState(false);

  const source = (data.source as string) || 'system';
  const style = (data.style as string) || 'horizontal';
  const showLogo = data.showLogo === true;
  const sticky = data.sticky === true;
  const customItems = (data.customItems as CustomItem[]) || [];

  // Styling
  const bgColor = (data.bgColor as string) || '';
  const textColor = (data.textColor as string) || '';
  const borderColor = (data.borderColor as string) || '';
  const fontSize = (data.fontSize as string) || '0.875rem';
  const fontWeight = (data.fontWeight as string) || '';
  const padding = (data.padding as string) || '0.75rem 1.5rem';
  const itemGap = (data.itemGap as string) || '1.5rem';
  const borderRadius = (data.borderRadius as string) || '';
  const logoSize = (data.logoSize as string) || '1.1rem';

  const sampleItems = source === 'custom' && customItems.length > 0
    ? customItems
    : [{ label: 'Home', url: '#' }, { label: 'About', url: '#' }, { label: 'Blog', url: '#' }, { label: 'Contact', url: '#' }];

  const isHamburger = style === 'hamburger';
  const isVertical = style === 'vertical';

  const navStyle: React.CSSProperties = {
    background: bgColor || 'var(--color-bg, #fff)',
    borderBottom: `1px solid ${borderColor || 'var(--color-border, #e5e7eb)'}`,
    padding,
    borderRadius: borderRadius || undefined,
    position: sticky ? 'sticky' : undefined,
    top: sticky ? 0 : undefined,
    zIndex: sticky ? 100 : undefined,
  };

  const linkStyle: React.CSSProperties = {
    fontSize,
    fontWeight: fontWeight || undefined,
    color: textColor || 'var(--color-text-muted, #64748b)',
    textDecoration: 'none',
    transition: 'color 0.2s',
  };

  return (
    <nav style={navStyle}>
      <div style={{ display: 'flex', alignItems: isVertical ? 'flex-start' : 'center', flexDirection: isVertical ? 'column' : 'row', gap: isVertical ? '0.5rem' : itemGap, justifyContent: (data.alignment as string) || ({'left': 'flex-start', 'center': 'center', 'right': 'flex-end'} as Record<string, string>)[((data.__style as any)?.typography?.textAlign || (block.style as any)?.typography?.textAlign)] || 'space-between' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: itemGap, flexDirection: isVertical ? 'column' : 'row' }}>
          {showLogo && (
            <span style={{ fontWeight: 700, fontSize: logoSize, color: textColor || 'var(--color-text, #1e293b)' }}>Logo</span>
          )}

          {/* Desktop links — hidden when hamburger */}
          {!isHamburger && (sampleItems || []).map((item, i) => (
            <span key={i} style={linkStyle} className="cursor-default">
              {item.label}
            </span>
          ))}
        </div>

        {/* Hamburger toggle */}
        {(isHamburger || true) && (
          <button
            onClick={() => setHamburgerOpen(!hamburgerOpen)}
            style={{ display: isHamburger ? 'flex' : 'none', alignItems: 'center', padding: '0.25rem', background: 'none', border: 'none', cursor: 'pointer', color: textColor || 'var(--color-text, #1e293b)' }}
          >
            {hamburgerOpen ? <X size={20} /> : <Menu size={20} />}
          </button>
        )}
      </div>

      {/* Hamburger dropdown */}
      {isHamburger && hamburgerOpen && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem', paddingTop: '0.75rem', borderTop: `1px solid ${borderColor || '#e5e7eb'}`, marginTop: '0.75rem' }}>
          {(sampleItems || []).map((item, i) => (
            <span key={i} style={{ ...linkStyle, padding: '0.5rem 0', display: 'block' }} className="cursor-default">
              {item.label}
            </span>
          ))}
        </div>
      )}

      {/* Badges */}
      <div style={{ display: 'flex', gap: '0.25rem', marginTop: '0.5rem' }}>
        <span className="text-[9px] bg-base-200 text-base-content/50 rounded px-1 py-0.5">
          {source === 'custom' ? 'Custom' : 'System'}
        </span>
        <span className="text-[9px] bg-base-200 text-base-content/50 rounded px-1 py-0.5">{style}</span>
        {sticky && <span className="text-[9px] bg-blue-100 text-blue-600 rounded px-1 py-0.5">Sticky</span>}
      </div>
    </nav>
  );
};
