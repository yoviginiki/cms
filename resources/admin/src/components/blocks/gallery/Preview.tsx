import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const GalleryPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { images, columns, gap } = block.data as {
    images: string[];
    columns: number;
    gap: string;
  };

  const cols = columns || 3;
  const imgList = Array.isArray(images) ? images : [];

  if (imgList.length === 0) {
    return (
      <div className="bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg p-12 flex items-center justify-center">
        <div className="text-center text-gray-400">
          <svg
            className="mx-auto h-12 w-12 mb-2"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={1.5}
              d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
            />
          </svg>
          <p className="text-sm">Gallery — no images</p>
        </div>
      </div>
    );
  }

  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: `repeat(${cols}, 1fr)`,
        gap: gap || '8px',
      }}
    >
      {imgList.map((url, i) => (
        <img
          key={i}
          src={url}
          alt=""
          className="rounded w-full h-32 object-cover"
        />
      ))}
    </div>
  );
};
