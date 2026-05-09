import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { buildBackgroundStyle, buildOverlayStyle } from '@/components/editor/BackgroundEditor';

export const HeroPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const title = (data.title as string) || '';
  const subtitle = (data.subtitle as string) || '';
  const ctaText = (data.ctaText as string) || '';

  // Configurable fields with sensible defaults matching previous hardcoded values
  const headlineTag = (data.headlineTag as string) || 'h1';
  const textAlignment = (data.textAlignment as string) || 'center';
  const verticalPosition = (data.verticalPosition as string) || 'center';
  const sectionHeight = (data.sectionHeight as string) || 'md';
  const contentMaxWidth = (data.contentMaxWidth as string) || '800px';
  const headlineSize = (data.headlineSize as string) || '2.5rem';
  const headlineWeight = (data.headlineWeight as string) || '700';
  const headlineColor = (data.headlineColor as string) || '';
  const subheadlineSize = (data.subheadlineSize as string) || '1.25rem';
  const adaptiveTextColor = data.adaptiveTextColor !== false;

  // Map sectionHeight to minHeight
  const heightMap: Record<string, number | string> = {
    auto: 'auto',
    sm: 300,
    md: 400,
    lg: 600,
    fullscreen: '100vh',
  };
  const minHeight = heightMap[sectionHeight] ?? 400;

  // Map verticalPosition to alignItems
  const alignMap: Record<string, string> = { top: 'flex-start', center: 'center', bottom: 'flex-end' };

  // Dynamic heading tag
  const HeadingTag = headlineTag as 'h1' | 'h2' | 'h3';

  // Legacy fallback: old hero blocks saved backgroundImage instead of bg_image
  const legacyImage = (data.backgroundImage as string) || '';
  const hasBgImage = !!(data.bg_image as string);
  const effectiveData = (!hasBgImage && legacyImage)
    ? { ...data, bg_type: 'image', bg_image: legacyImage }
    : data;

  const bgStyle = buildBackgroundStyle(effectiveData);
  const overlayStyle = buildOverlayStyle(effectiveData);
  const effectiveBgType = (effectiveData.bg_type as string) || 'none';
  const hasBg = effectiveBgType !== 'none';

  // Derive text colors
  const resolvedHeadlineColor = headlineColor
    || (adaptiveTextColor && hasBg ? 'white' : 'inherit');
  const resolvedSubtitleColor = adaptiveTextColor && hasBg
    ? 'rgba(255,255,255,0.85)'
    : 'inherit';

  // Empty state
  if (!title && !subtitle) {
    return (
      <div className="border-2 border-dashed border-base-300 rounded-lg p-12 text-center">
        <p className="text-base-content/40 text-sm">Hero Section — click to configure</p>
      </div>
    );
  }

  return (
    <div
      className="relative rounded-lg overflow-hidden"
      style={{
        minHeight,
        display: 'flex',
        alignItems: alignMap[verticalPosition] || 'center',
        justifyContent: 'center',
        ...bgStyle,
        ...(!hasBg ? { backgroundColor: 'oklch(var(--b3))' } : {}),
      }}
    >
      {overlayStyle && <div style={overlayStyle} />}

      <div
        className="relative z-10 px-6 py-8"
        style={{ textAlign: textAlignment as React.CSSProperties['textAlign'], maxWidth: contentMaxWidth }}
      >
        <HeadingTag
          className={`mb-2 ${hasBg ? 'drop-shadow-sm' : ''} ${!headlineColor && !adaptiveTextColor ? 'text-base-content' : ''}`}
          style={{
            fontSize: headlineSize,
            fontWeight: headlineWeight,
            color: resolvedHeadlineColor || undefined,
          }}
        >
          {title}
        </HeadingTag>
        {subtitle && (
          <p
            className={`mb-5 ${hasBg ? 'drop-shadow-sm' : ''} ${!adaptiveTextColor && !hasBg ? 'text-base-content/70' : ''}`}
            style={{
              fontSize: subheadlineSize,
              color: resolvedSubtitleColor || undefined,
            }}
          >
            {subtitle}
          </p>
        )}
        {ctaText && (
          <span className={`inline-block px-5 py-2.5 rounded-lg text-sm font-semibold ${hasBg ? 'bg-white/20 text-white border-2 border-white/60 backdrop-blur-sm' : 'bg-primary text-primary-content'}`}>
            {ctaText}
          </span>
        )}
      </div>
    </div>
  );
};
