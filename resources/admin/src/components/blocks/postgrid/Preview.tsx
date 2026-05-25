import React, { useState } from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { normalizeCardEffects, buildCardBaseStyle, buildCardHoverStyle, buildImageFilterCss, buildOverlayStyle, isRevealEnabled, buildRevealFilteredStyle, buildRevealFilteredHoverStyle } from '@/lib/blockEffects';

export const PostgridPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, any>;
  const cols = Math.max(1, Math.min(6, data.columns || 3));
  const limit = Math.max(1, Math.min(50, data.limit || 9));
  const showImage = data.showImage !== false;
  const showHeading = data.showHeading !== false;
  const showExcerpt = !!data.showExcerpt;
  const imageHeight = data.imageHeight || 160;
  const imageWidth = data.imageWidth || '100%';
  const gap = data.gap ?? 24;
  const isHorizontal = data.cardStyle === 'horizontal';

  // Heading
  const headingTag = data.headingTag || 'h3';
  const headingSize = data.headingSize || 16;
  const headingFont = data.headingFont || 'inherit';
  const headingAlign = data.headingAlign || 'left';
  const headingPadding = data.headingPadding || '0';
  const headingMargin = data.headingMargin || '0 0 0.25rem 0';

  // Excerpt
  const excerptSize = data.excerptSize || 14;
  const excerptFont = data.excerptFont || 'inherit';
  const excerptAlign = data.excerptAlign || 'left';
  const excerptPadding = data.excerptPadding || '0';
  const excerptMargin = data.excerptMargin || '0.25rem 0 0 0';

  // Card border
  const cardBorder = data.cardBorder !== false;
  const cardBorderWidth = data.cardBorderWidth ?? 1;
  const cardBorderColor = data.cardBorderColor || '#e5e7eb';
  const cardBorderStyle = data.cardBorderStyle || 'solid';
  const cardBorderRadius = data.cardBorderRadius ?? 12;
  const cardShadow = data.cardShadow || 'none';
  const cardBg = data.cardBg || '';
  const cardPadding = data.cardPadding || '0';

  const SHADOW_MAP: Record<string, string> = {
    none: 'none', sm: '0 1px 2px rgba(0,0,0,0.05)',
    md: '0 4px 6px rgba(0,0,0,0.07)', lg: '0 10px 15px rgba(0,0,0,0.1)',
    xl: '0 20px 25px rgba(0,0,0,0.15)',
  };

  // Card effects
  const effects = normalizeCardEffects(data.effects);
  const cardBaseStyles = buildCardBaseStyle(effects);
  const cardHoverStyles = buildCardHoverStyle(effects);
  const imageFilterCss = buildImageFilterCss(effects);
  const overlayStyles = buildOverlayStyle(effects);
  const revealActive = isRevealEnabled(effects);
  const revealBaseStyle = buildRevealFilteredStyle(effects);
  const revealHoverStyle = buildRevealFilteredHoverStyle(effects);
  const [hoveredCard, setHoveredCard] = useState<number | null>(null);

  // Responsive clamp
  const imgH = `clamp(${Math.round(imageHeight * 0.4)}px, ${(imageHeight / 10).toFixed(1)}vw, ${imageHeight}px)`;
  const gapVal = `clamp(${Math.round(gap * 0.4)}px, ${(gap / 10).toFixed(1)}vw, ${gap}px)`;

  return (
    <div style={{ maxWidth: '1200px', margin: '0 auto', padding: '1.5rem 0.75rem' }}>
      <div style={{
        display: 'grid',
        gridTemplateColumns: `repeat(${cols}, 1fr)`,
        gap: gapVal,
      }}>
        {Array.from({ length: limit }).map((_, i) => (
          <div key={i}
            onMouseEnter={() => setHoveredCard(i)}
            onMouseLeave={() => setHoveredCard(null)}
            style={{
              borderRadius: `${cardBorderRadius}px`,
              border: cardBorder ? `${cardBorderWidth}px ${cardBorderStyle} ${cardBorderColor}` : 'none',
              overflow: effects.enabled ? 'visible' : 'hidden',
              boxShadow: SHADOW_MAP[cardShadow] || 'none',
              backgroundColor: cardBg || undefined,
              padding: cardPadding,
              position: 'relative',
              ...cardBaseStyles,
              ...(hoveredCard === i ? cardHoverStyles : {}),
              ...(isHorizontal ? { display: 'flex' } : {}),
            }}>
            {showImage && (
              <div style={{
                background: 'linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%)',
                width: isHorizontal ? '33%' : imageWidth,
                height: imgH,
                ...(imageWidth !== '100%' && !isHorizontal ? { margin: '0 auto' } : {}),
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: '#d1d5db',
                position: 'relative',
                overflow: 'hidden',
                borderRadius: 'inherit',
                ...(!revealActive && imageFilterCss ? { filter: imageFilterCss } : {}),
              }}>
                {overlayStyles && <div style={overlayStyles as React.CSSProperties} />}
                {/* Filtered overlay for reveal effect */}
                {revealActive && (
                  <div style={{
                    ...revealBaseStyle,
                    ...(hoveredCard === i ? revealHoverStyle : {}),
                    background: 'linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%)',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                  }}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e5e7eb" strokeWidth="1.5" style={{ opacity: 0.5 }}>
                      <rect x="3" y="3" width="18" height="18" rx="2" />
                      <circle cx="8.5" cy="8.5" r="1.5" />
                      <path d="m21 15-5-5L5 21" />
                    </svg>
                  </div>
                )}
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                  <rect x="3" y="3" width="18" height="18" rx="2" />
                  <circle cx="8.5" cy="8.5" r="1.5" />
                  <path d="m21 15-5-5L5 21" />
                </svg>
              </div>
            )}
            <div style={{ padding: '0.75rem', flex: isHorizontal ? '1' : undefined }}>
              {showHeading && React.createElement(headingTag, {
                style: {
                  fontSize: `${headingSize}px`,
                  fontFamily: headingFont,
                  textAlign: headingAlign as any,
                  padding: headingPadding,
                  margin: headingMargin,
                  fontWeight: 600,
                },
              }, React.createElement('span', {
                style: {
                  display: 'inline-block',
                  height: `${Math.max(10, headingSize * 0.7)}px`,
                  background: '#e5e7eb',
                  borderRadius: '0.25rem',
                  width: `${60 + (i % 3) * 12}%`,
                  verticalAlign: 'middle',
                },
              }))}
              {showExcerpt && (
                <div style={{
                  fontSize: `${excerptSize}px`,
                  fontFamily: excerptFont,
                  textAlign: excerptAlign as any,
                  padding: excerptPadding,
                  margin: excerptMargin,
                }}>
                  <span style={{ display: 'block', height: `${Math.max(8, excerptSize * 0.5)}px`, background: '#f3f4f6', borderRadius: '0.25rem', width: '100%', marginBottom: '0.2rem' }} />
                  <span style={{ display: 'block', height: `${Math.max(8, excerptSize * 0.5)}px`, background: '#f3f4f6', borderRadius: '0.25rem', width: '70%' }} />
                </div>
              )}
            </div>
          </div>
        ))}
      </div>

      {/* Live values */}
      <div style={{ marginTop: '0.75rem', display: 'flex', gap: '0.75rem', justifyContent: 'center', flexWrap: 'wrap', fontSize: '0.6rem', color: '#9ca3af' }}>
        <span>{cols}col</span>
        <span>{limit}items</span>
        {showImage && <span>img:{imageHeight}px/{imageWidth}</span>}
        <span>gap:{gap}px</span>
        {showHeading && <span>{data.headingTag || 'h3'}:{headingSize}px</span>}
        {showExcerpt && <span>exc:{excerptSize}px</span>}
      </div>
    </div>
  );
};
