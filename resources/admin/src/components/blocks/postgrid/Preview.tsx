import React, { useState } from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { normalizeCardEffects, buildCardBaseStyle, buildCardHoverStyle, buildImageFilterCss, buildOverlayStyle, isRevealEnabled } from '@/lib/blockEffects';

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
  const revealMode = effects.imageHoverReveal?.mode || 'fade';
  const revealDuration = effects.imageHoverReveal?.duration ?? 500;
  const revealEasing = effects.imageHoverReveal?.easing ?? 'ease-out';
  const [hoveredCard, setHoveredCard] = useState<number | null>(null);

  // Responsive clamp
  const imgH = `clamp(${Math.round(imageHeight * 0.4)}px, ${(imageHeight / 10).toFixed(1)}vw, ${imageHeight}px)`;
  const gapVal = `clamp(${Math.round(gap * 0.4)}px, ${(gap / 10).toFixed(1)}vw, ${gap}px)`;

  // Reveal clip-path values for JS-driven hover
  const CLIP_DEFAULT: Record<string, string> = {
    'reveal-left': 'inset(0 0 0 0)', 'reveal-right': 'inset(0 0 0 0)',
    'reveal-top': 'inset(0 0 0 0)', 'reveal-bottom': 'inset(0 0 0 0)',
    'circle': 'circle(100% at 50% 50%)', 'diagonal': 'polygon(0 0, 100% 0, 100% 100%, 0 100%)',
  };
  const CLIP_HOVER: Record<string, string> = {
    'reveal-left': 'inset(0 100% 0 0)', 'reveal-right': 'inset(0 0 0 100%)',
    'reveal-top': 'inset(100% 0 0 0)', 'reveal-bottom': 'inset(0 0 100% 0)',
    'circle': 'circle(0% at 50% 50%)', 'diagonal': 'polygon(0 0, 0 0, 0 100%, 0 100%)',
  };

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
              ...(isHorizontal ? { display: 'flex' } : {}),
            }}>
            {showImage && (
              <div style={{
                background: revealActive
                  ? 'linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #ec4899 100%)'
                  : 'linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%)',
                width: isHorizontal ? '33%' : imageWidth,
                height: imgH,
                ...(imageWidth !== '100%' && !isHorizontal ? { margin: '0 auto' } : {}),
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: revealActive ? '#fff' : '#d1d5db',
                position: 'relative',
                overflow: 'hidden',
                borderRadius: 'inherit',
                ...(!revealActive && imageFilterCss ? { filter: imageFilterCss } : {}),
              }}>
                {overlayStyles && <div style={overlayStyles as React.CSSProperties} />}
                {/* Filtered overlay for reveal — pure inline styles driven by JS hover */}
                {revealActive && (() => {
                  const isHovered = hoveredCard === i;
                  const filteredStyle: React.CSSProperties = {
                    position: 'absolute', inset: 0, width: '100%', height: '100%',
                    pointerEvents: 'none', zIndex: 1,
                    background: imageFilterCss.includes('grayscale') ? '#444'
                      : imageFilterCss.includes('sepia') ? '#8b7355' : '#666',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    transition: revealMode === 'fade'
                      ? `opacity ${revealDuration}ms ${revealEasing}`
                      : `clip-path ${revealDuration}ms ${revealEasing}`,
                  };
                  // Apply hover state
                  if (revealMode === 'fade') {
                    filteredStyle.opacity = isHovered ? 0 : 1;
                  } else {
                    filteredStyle.clipPath = isHovered
                      ? (CLIP_HOVER[revealMode] || 'inset(0 100% 0 0)')
                      : (CLIP_DEFAULT[revealMode] || 'inset(0 0 0 0)');
                  }
                  return (
                    <div style={filteredStyle}>
                      {!isHovered && (
                        <span style={{ color: '#fff', fontSize: '0.6rem', opacity: 0.7 }}>
                          {effects.imageFilter?.preset || 'filtered'}
                        </span>
                      )}
                    </div>
                  );
                })()}
                {/* Original image indicator */}
                {revealActive ? (
                  <span style={{ fontSize: '0.6rem', opacity: 0.8, zIndex: 0 }}>original</span>
                ) : (
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <circle cx="8.5" cy="8.5" r="1.5" />
                    <path d="m21 15-5-5L5 21" />
                  </svg>
                )}
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
        {effects.enabled && <span style={{ color: '#3b82f6' }}>fx:on</span>}
        {revealActive && <span style={{ color: '#8b5cf6' }}>reveal:{effects.imageHoverReveal?.mode}</span>}
      </div>
    </div>
  );
};
