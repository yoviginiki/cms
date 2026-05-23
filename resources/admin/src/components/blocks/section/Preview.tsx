import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { safeDim, safeColor } from '@/lib/blockStyles';

export const SectionPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const style = block.style || {};
  const layout = (style as any).layout || {};
  const spacing = (style as any).spacing || {};

  const paddingTop = safeDim(spacing.paddingTop) || safeDim(data.padding_top) || '2rem';
  const paddingBottom = safeDim(spacing.paddingBottom) || safeDim(data.padding_bottom) || '2rem';
  const maxWidth = safeDim(layout.maxWidth) || safeDim(data.max_width) || '1200px';
  const anchorId = (data.anchor_id as string) || '';

  // New px fields take priority; legacy padding preset as fallback only (matches Blade)
  const legacyPadding = data.padding as string | undefined;
  const legacyPaddingMap: Record<string, string> = {
    none: '0', sm: '1rem', md: '2rem', lg: '3rem', xl: '4rem',
  };
  const effectivePadTop = data.padding_top ? paddingTop : (legacyPadding ? (legacyPaddingMap[legacyPadding] ?? '2rem') : paddingTop);
  const effectivePadBottom = data.padding_bottom ? paddingBottom : (legacyPadding ? (legacyPaddingMap[legacyPadding] ?? '2rem') : paddingBottom);

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
    const c = safeColor(data.bg_color);
    if (c) outerStyle.backgroundColor = c;
  } else if (bgType === 'image' && data.bg_image) {
    outerStyle.backgroundImage = `url(${data.bg_image as string})`;
    outerStyle.backgroundSize = (data.bg_image_size as string) || 'cover';
    outerStyle.backgroundPosition = (data.bg_image_position as string) || 'center center';
    outerStyle.backgroundRepeat = (data.bg_image_repeat as string) || 'no-repeat';
    if (data.bg_scroll_effect === 'fixed') outerStyle.backgroundAttachment = 'fixed';
  } else if (legacyBgColor) {
    // Legacy fallback
    const c = safeColor(legacyBgColor);
    if (c) outerStyle.backgroundColor = c;
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

  // Apply size from layout panel (sanitized)
  if (safeDim(layout.width)) outerStyle.width = safeDim(layout.width);
  if (safeDim(layout.height)) outerStyle.height = safeDim(layout.height);
  if (safeDim(layout.minWidth)) outerStyle.minWidth = safeDim(layout.minWidth);
  if (safeDim(layout.minHeight)) outerStyle.minHeight = safeDim(layout.minHeight);
  if (safeDim(layout.maxHeight)) outerStyle.maxHeight = safeDim(layout.maxHeight);
  if (layout.overflow && layout.overflow !== 'visible') outerStyle.overflow = layout.overflow;
  // Apply spacing panel margins (sanitized)
  if (safeDim(spacing.marginTop)) outerStyle.marginTop = safeDim(spacing.marginTop);
  if (safeDim(spacing.marginBottom)) outerStyle.marginBottom = safeDim(spacing.marginBottom);
  if (safeDim(spacing.marginLeft)) outerStyle.marginLeft = safeDim(spacing.marginLeft);
  if (safeDim(spacing.marginRight)) outerStyle.marginRight = safeDim(spacing.marginRight);
  if (safeDim(spacing.paddingLeft)) outerStyle.paddingLeft = safeDim(spacing.paddingLeft);
  if (safeDim(spacing.paddingRight)) outerStyle.paddingRight = safeDim(spacing.paddingRight);

  // Apply visual (borders, shadow — sanitized)
  const visual = (style as any).visual || {};
  if (safeDim(visual.borderRadius)) outerStyle.borderRadius = safeDim(visual.borderRadius);
  if (safeDim(visual.borderWidth)) outerStyle.borderWidth = safeDim(visual.borderWidth);
  if (safeColor(visual.borderColor)) outerStyle.borderColor = safeColor(visual.borderColor);
  if (visual.borderStyle && ['solid','dashed','dotted','none'].includes(visual.borderStyle)) outerStyle.borderStyle = visual.borderStyle;

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
