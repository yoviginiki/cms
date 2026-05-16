import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const SectionPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;

  const paddingTop = (data.padding_top as string) || '40px';
  const paddingBottom = (data.padding_bottom as string) || '40px';
  const maxWidth = (data.max_width as string) || '1200px';
  const anchorId = (data.anchor_id as string) || '';

  // New px fields take priority; legacy padding preset as fallback only
  const legacyPadding = data.padding as string | undefined;
  const legacyPaddingMap: Record<string, string> = {
    none: '0', sm: '1rem', md: '2rem', lg: '3rem', xl: '4rem',
  };
  const effectivePadTop = data.padding_top ? paddingTop : (legacyPadding ? (legacyPaddingMap[legacyPadding] ?? '40px') : paddingTop);
  const effectivePadBottom = data.padding_bottom ? paddingBottom : (legacyPadding ? (legacyPaddingMap[legacyPadding] ?? '40px') : paddingBottom);

  // Outer section style: padding + background (matches Blade <section> element)
  const outerStyle: React.CSSProperties = {
    paddingTop: effectivePadTop,
    paddingBottom: effectivePadBottom,
    position: 'relative',
  };

  // Background: match Blade logic — bg_type gates which system is used
  const bgType = (data.bg_type as string) || 'none';
  const legacyBgColor = (data.background_color as string) || '';
  const legacyBgImage = (data.background_image as string) || '';

  if (bgType === 'color' && data.bg_color) {
    outerStyle.backgroundColor = data.bg_color as string;
  } else if (bgType === 'image' && data.bg_image) {
    outerStyle.backgroundImage = `url(${data.bg_image as string})`;
    outerStyle.backgroundSize = (data.bg_image_size as string) || 'cover';
    outerStyle.backgroundPosition = (data.bg_image_position as string) || 'center center';
    outerStyle.backgroundRepeat = (data.bg_image_repeat as string) || 'no-repeat';
    if (data.bg_scroll_effect === 'fixed') outerStyle.backgroundAttachment = 'fixed';
  } else if (legacyBgColor) {
    // Legacy fallback
    outerStyle.backgroundColor = legacyBgColor;
    if (legacyBgImage) {
      outerStyle.backgroundImage = `url(${legacyBgImage})`;
      outerStyle.backgroundSize = 'cover';
      outerStyle.backgroundPosition = 'center';
    }
  }

  // Inner wrapper: max-width + centered (matches Blade inner div)
  const innerStyle: React.CSSProperties = {
    maxWidth,
    margin: '0 auto',
    position: 'relative',
    zIndex: 1,
  };

  return (
    <div
      className="rounded border border-dashed border-blue-300/50 min-h-[60px]"
      style={outerStyle}
    >
      <div style={innerStyle}>
        {block.children.length === 0 && (
          <div className="text-xs text-blue-400/60 uppercase tracking-wide text-center py-2">
            Section{anchorId ? ` #${anchorId}` : ''} — drop rows here
          </div>
        )}
      </div>
    </div>
  );
};
