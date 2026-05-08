import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { buildBackgroundStyle, buildOverlayStyle } from '@/components/editor/BackgroundEditor';

export const HeroPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const title = (data.title as string) || '';
  const subtitle = (data.subtitle as string) || '';
  const ctaText = (data.ctaText as string) || '';
  const bgType = (data.bg_type as string) || 'none';

  const bgStyle = buildBackgroundStyle(data);
  const overlayStyle = buildOverlayStyle(data);
  const hasBg = bgType !== 'none';

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
        ...(!hasBg ? { background: 'linear-gradient(135deg, oklch(var(--p)) 0%, oklch(var(--s)) 100%)' } : {}),
      }}
    >
      {overlayStyle && <div style={overlayStyle} />}

      <div className="relative z-10 text-center px-6 py-8 max-w-2xl">
        <h1 className="text-3xl font-bold mb-2 text-white drop-shadow-sm">
          {title}
        </h1>
        {subtitle && (
          <p className="text-lg text-white/85 mb-5 drop-shadow-sm">
            {subtitle}
          </p>
        )}
        {ctaText && (
          <span className="inline-block px-5 py-2.5 bg-white/20 text-white border-2 border-white/60 rounded-lg text-sm font-semibold backdrop-blur-sm">
            {ctaText}
          </span>
        )}
      </div>
    </div>
  );
};
