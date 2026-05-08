import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { buildBackgroundStyle, buildOverlayStyle } from '@/components/editor/BackgroundEditor';

export const HeroPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const title = (data.title as string) || '';
  const subtitle = (data.subtitle as string) || '';
  const ctaText = (data.ctaText as string) || '';
  const bgType = (data.bg_type as string) || 'none';

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
        minHeight: 280,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        ...bgStyle,
        ...(!hasBg ? { backgroundColor: 'oklch(var(--b3))' } : {}),
      }}
    >
      {overlayStyle && <div style={overlayStyle} />}

      <div className="relative z-10 text-center px-6 py-8 max-w-2xl">
        <h1 className={`text-3xl font-bold mb-2 ${hasBg ? 'text-white drop-shadow-sm' : 'text-base-content'}`}>
          {title}
        </h1>
        {subtitle && (
          <p className={`text-lg mb-5 ${hasBg ? 'text-white/85 drop-shadow-sm' : 'text-base-content/70'}`}>
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
