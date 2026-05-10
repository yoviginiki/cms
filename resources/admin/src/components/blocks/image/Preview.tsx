import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { InlineMediaReplace } from '@/components/editor/fields';

export const ImagePreview: React.FC<BlockComponentProps> = ({ block, isSelected, onUpdate }) => {
  const { url, alt, caption } = block.data as {
    url: string;
    alt: string;
    caption: string;
  };

  const handleImageChange = (newUrl: string, assetId?: string) => {
    onUpdate({
      ...block.data,
      url: newUrl,
      ...(assetId ? { assetId } : {}),
    });
  };

  if (!url) {
    return (
      <div className="relative bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg p-12 flex items-center justify-center">
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
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
          </svg>
          <p className="text-sm">No image selected</p>
        </div>
        <InlineMediaReplace
          value=""
          onChange={handleImageChange}
          accept="image"
          label="image"
          overlay
        />
      </div>
    );
  }

  return (
    <figure className="relative">
      <img src={url} alt={alt || ''} className="rounded-lg w-full" />
      {isSelected && (
        <InlineMediaReplace
          value={url}
          onChange={handleImageChange}
          accept="image"
          label="image"
          overlay
        />
      )}
      {caption && (
        <figcaption className="text-sm text-gray-500 mt-2 text-center">
          {caption}
        </figcaption>
      )}
    </figure>
  );
};
