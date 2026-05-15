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

  const style: React.CSSProperties = {
    paddingTop: effectivePadTop,
    paddingBottom: effectivePadBottom,
    maxWidth,
    margin: '0 auto',
    position: 'relative',
  };

  // Background
  const bgColor = (data.background_color as string) || (data.bg_color as string) || '';
  if (bgColor) {
    style.backgroundColor = bgColor;
  }
  const bgImage = (data.background_image as string) || (data.bg_image as string) || '';
  if (bgImage) {
    style.backgroundImage = `url(${bgImage})`;
    style.backgroundSize = 'cover';
    style.backgroundPosition = 'center';
  }

  return (
    <div
      className="rounded border border-dashed border-blue-300/50 min-h-[60px]"
      style={style}
    >
      {block.children.length === 0 && (
        <div className="text-xs text-blue-400/60 uppercase tracking-wide text-center py-2">
          Section{anchorId ? ` #${anchorId}` : ''} — drop rows here
        </div>
      )}
    </div>
  );
};
