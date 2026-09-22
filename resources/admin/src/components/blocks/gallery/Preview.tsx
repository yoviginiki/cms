import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { normalizeGalleryImages, ASPECT_RATIO, type GalleryAspect } from './types';

export const GalleryPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as Record<string, unknown>;
  const imgList = normalizeGalleryImages(data.images);
  const cols = (data.columns as number) || 3;
  const gap = (data.gap as string) || '8px';
  const aspect = ASPECT_RATIO[((data.aspect as GalleryAspect) || 'square')];
  const captions = data.captions === true;

  if (imgList.length === 0) {
    return (
      <div className="bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg p-12 flex items-center justify-center">
        <div className="text-center text-gray-400">
          <svg className="mx-auto h-12 w-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
              d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
          </svg>
          <p className="text-sm">Gallery — no images</p>
          <p className="text-xs mt-1">Add images from the settings panel</p>
        </div>
      </div>
    );
  }

  return (
    <div style={{ display: 'grid', gridTemplateColumns: `repeat(${cols}, 1fr)`, gap }}>
      {imgList.map((img, i) => (
        <figure key={(img.id || img.src) + i} className="m-0 min-w-0">
          <img src={img.src} alt={img.alt || ''} className="rounded w-full object-cover block"
            style={aspect ? { aspectRatio: aspect } : { height: 'auto' }} />
          {captions && img.caption && (
            <figcaption className="text-xs text-gray-500 mt-1 text-center">{img.caption}</figcaption>
          )}
        </figure>
      ))}
    </div>
  );
};
