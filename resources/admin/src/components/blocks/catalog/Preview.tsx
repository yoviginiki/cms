import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

interface CatalogItem {
  title: string;
  subtitle: string;
  content: string;
  contentSecondary: string;
  images: string[];
}

export const CatalogPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    items: CatalogItem[];
    headerLabels: string[];
    openFirst: boolean;
    imageFilter: string;
  };
  const items = data.items || [];
  const labels = data.headerLabels || ['no.', 'title', 'subtitle', ''];
  const filter = data.imageFilter || 'grayscale';
  const filterCss = filter === 'grayscale' ? 'grayscale(100%)' : filter === 'sepia' ? 'sepia(100%)' : 'none';

  return (
    <div style={{ fontFamily: 'var(--font-body, sans-serif)' }}>
      {/* Header row */}
      <div style={{ display: 'grid', gridTemplateColumns: '50px 1fr 80px 60px', padding: '0.75rem 0', borderBottom: '2px solid var(--color-text, #201F1D)' }}>
        {labels.map((l, i) => (
          <span key={i} style={{ fontSize: '0.6rem', textTransform: 'uppercase', letterSpacing: '0.15em', color: 'var(--color-text-muted, #7D7B7A)', fontFamily: 'var(--font-heading, sans-serif)' }}>{l}</span>
        ))}
      </div>

      {items.map((item, index) => (
        <div key={index} style={{ borderBottom: '1px solid rgba(201,193,171,0.4)' }}>
          {/* Summary row */}
          <div style={{ display: 'grid', gridTemplateColumns: '50px 1fr 80px 60px', alignItems: 'center', padding: '1.2rem 0' }}>
            <span style={{ fontSize: '0.75rem', color: 'var(--color-text-muted, #7D7B7A)', fontFamily: 'var(--font-heading, sans-serif)' }}>
              {String(index + 1).padStart(2, '0')}
            </span>
            <span style={{ fontSize: '1.1rem', fontWeight: 600, letterSpacing: '0.02em' }}>
              {item.title || 'Untitled'}
            </span>
            <span style={{ fontSize: '0.95rem', color: 'var(--color-text-muted, #7D7B7A)' }}>
              {item.subtitle || ''}
            </span>
            <span style={{ fontSize: '0.65rem', textTransform: 'uppercase', textAlign: 'right', color: 'var(--color-text-muted, #7D7B7A)', letterSpacing: '0.1em', fontFamily: 'var(--font-heading, sans-serif)' }}>
              {index === 0 ? 'close' : 'open'}
            </span>
          </div>

          {/* Expanded content (show first item) */}
          {index === 0 && (
            <div style={{ padding: '0 0 2rem' }}>
              {item.images?.length > 0 && (
                <div style={{ display: 'flex', gap: '12px', marginBottom: '1.5rem', overflowX: 'auto', paddingBottom: '0.5rem' }}>
                  {item.images.map((img, imgI) => (
                    <div key={imgI} style={{ flex: '0 0 200px' }}>
                      <img
                        src={img}
                        alt=""
                        style={{ width: '100%', height: '280px', objectFit: 'cover', filter: filterCss, borderRadius: 0 }}
                      />
                    </div>
                  ))}
                </div>
              )}
              <div style={{ display: 'grid', gridTemplateColumns: item.contentSecondary ? '1fr 1fr' : '1fr', gap: '2rem' }}>
                <div style={{ fontSize: '0.9rem', lineHeight: 1.8 }} dangerouslySetInnerHTML={{ __html: item.content }} />
                {item.contentSecondary && (
                  <div style={{ fontSize: '0.85rem', lineHeight: 1.9, color: 'var(--color-text-muted, #686459)' }} dangerouslySetInnerHTML={{ __html: item.contentSecondary }} />
                )}
              </div>
            </div>
          )}
        </div>
      ))}
    </div>
  );
};
