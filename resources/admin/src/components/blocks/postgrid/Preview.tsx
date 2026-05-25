import React, { useState } from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { normalizeCardEffects, buildCardBaseStyle, buildCardHoverStyle, buildImageFilterCss, buildOverlayStyle } from '@/lib/blockEffects';

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
  const headingPosition = data.headingPosition || 'below';
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
  const revealEnabled = !!effects.enabled && !!effects.imageHoverReveal?.enabled && !!effects.imageFilter?.enabled;
  const revealDuration = effects.imageHoverReveal?.duration ?? 500;
  const revealEasing = effects.imageHoverReveal?.easing ?? 'ease-out';
  const [hoveredCard, setHoveredCard] = useState<number | null>(null);

  // Heading renderers
  const renderHeading = (idx: number) => React.createElement(headingTag, {
    style: { fontSize: `${headingSize}px`, fontFamily: headingFont, textAlign: headingAlign as any, padding: headingPadding, margin: headingMargin, fontWeight: 600 },
  }, React.createElement('span', {
    style: { display: 'inline-block', height: `${Math.max(10, headingSize * 0.7)}px`, background: '#e5e7eb', borderRadius: '0.25rem', width: `${60 + (idx % 3) * 12}%`, verticalAlign: 'middle' },
  }));

  const headingVerticalDir = data.headingVerticalDir || 'up';
  const renderVerticalHeading = (idx: number) => {
    // up/down use vertical writing mode, left/right use transform rotate fallback
    const isStacked = headingVerticalDir === 'stacked';
    const isRotate = headingVerticalDir === 'left' || headingVerticalDir === 'right';
    const writingMode = isStacked ? 'vertical-rl' : isRotate ? undefined : (headingVerticalDir === 'down' ? 'vertical-rl' : 'vertical-lr');
    const textOrientation = isStacked ? 'upright' : 'mixed';
    const transform = headingVerticalDir === 'left' ? 'rotate(180deg)' : undefined;
    return (
      <div style={{
        ...(writingMode ? { writingMode: writingMode as any, textOrientation } : { writingMode: 'vertical-rl' as any, transform, textOrientation }),
        padding: '0.5rem 0.25rem', display: 'flex', alignItems: 'center', justifyContent: 'center', minWidth: `${headingSize + 8}px`,
        ...(isStacked ? { letterSpacing: '0.1em' } : {}),
      }}>
        {React.createElement(headingTag, {
          style: { fontSize: `${headingSize}px`, fontFamily: headingFont, fontWeight: 600, margin: 0, whiteSpace: 'nowrap' as const },
        }, React.createElement('span', {
          style: { display: 'inline-block', width: `${Math.max(10, headingSize * 0.7)}px`, height: `${40 + (idx % 3) * 10}px`, background: '#e5e7eb', borderRadius: '0.25rem', verticalAlign: 'middle' },
        }))}
      </div>
    );
  };

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
              ...(headingPosition === 'vertical-left' || headingPosition === 'vertical-right' ? { display: 'flex', flexDirection: 'row' as const } : {}),
            }}>
            {/* Heading ABOVE image */}
            {showHeading && headingPosition === 'above' && renderHeading(i)}

            {/* Vertical heading LEFT */}
            {showHeading && headingPosition === 'vertical-left' && renderVerticalHeading(i)}

            {showImage && (() => {
              const isHovered = hoveredCard === i;
              const showFiltered = imageFilterCss && !(revealEnabled && isHovered);
              const bgColor = imageFilterCss
                ? `linear-gradient(135deg, #e74c3c ${(i * 13) % 20}%, #3498db ${50 + (i * 7) % 20}%, #2ecc71 100%)`
                : 'linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%)';
              return (
                <div style={{
                  background: bgColor,
                  width: isHorizontal ? '33%' : imageWidth,
                  height: imgH,
                  ...(imageWidth !== '100%' && !isHorizontal ? { margin: '0 auto' } : {}),
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  color: showFiltered ? '#999' : '#fff', fontSize: '0.6rem', fontWeight: 600,
                  position: 'relative', overflow: 'hidden', borderRadius: 'inherit',
                  filter: showFiltered ? imageFilterCss : undefined,
                  transition: revealEnabled ? `filter ${revealDuration}ms ${revealEasing}` : undefined,
                  flex: (headingPosition === 'vertical-left' || headingPosition === 'vertical-right') ? '1' : undefined,
                }}>
                  {overlayStyles && <div style={overlayStyles as React.CSSProperties} />}
                  {revealEnabled && isHovered ? 'COLOR' : showFiltered ? (effects.imageFilter?.preset || 'filtered') : ''}
                  {!imageFilterCss && (
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                      <rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="m21 15-5-5L5 21" />
                    </svg>
                  )}
                </div>
              );
            })()}

            {/* Vertical heading RIGHT */}
            {showHeading && headingPosition === 'vertical-right' && renderVerticalHeading(i)}

            {/* Content below (heading below + excerpt) */}
            {(showHeading && headingPosition === 'below') || showExcerpt ? (
              <div style={{ padding: '0.75rem', flex: isHorizontal ? '1' : undefined }}>
                {showHeading && headingPosition === 'below' && renderHeading(i)}
                {showExcerpt && (
                  <div style={{ fontSize: `${excerptSize}px`, fontFamily: excerptFont, textAlign: excerptAlign as any, padding: excerptPadding, margin: excerptMargin }}>
                    <span style={{ display: 'block', height: `${Math.max(8, excerptSize * 0.5)}px`, background: '#f3f4f6', borderRadius: '0.25rem', width: '100%', marginBottom: '0.2rem' }} />
                    <span style={{ display: 'block', height: `${Math.max(8, excerptSize * 0.5)}px`, background: '#f3f4f6', borderRadius: '0.25rem', width: '70%' }} />
                  </div>
                )}
              </div>
            ) : null}
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
        {effects.enabled && <span style={{ color: '#3b82f6' }}>fx:on</span>}
        {revealEnabled && <span style={{ color: '#8b5cf6' }}>reveal:{effects.imageHoverReveal?.mode}</span>}
      </div>
    </div>
  );
};
