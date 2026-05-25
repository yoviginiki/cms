import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

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
          <div key={i} style={{
            borderRadius: '0.75rem',
            border: '1px solid #e5e7eb',
            overflow: 'hidden',
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
              }}>
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
