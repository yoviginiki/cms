import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const ImagecaptionPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { src, alt, caption, captionPosition } = block.data as {
    src: string;
    alt: string;
    caption: string;
    captionPosition: string;
  };

  if (!src) {
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
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
          </svg>
          <p className="text-sm">Image with caption</p>
        </div>
      </div>
    );
  }

  const pos = captionPosition || 'below';

  if (pos === 'side-right' || pos === 'side-left') {
    return (
      <figure className={`flex gap-4 ${pos === 'side-left' ? 'flex-row-reverse' : 'flex-row'}`}>
        <img src={src} alt={alt || ''} className="rounded-lg w-1/2 object-cover" />
        {caption && (
          <figcaption className="text-sm text-gray-500 flex items-center w-1/2">
            {caption}
          </figcaption>
        )}
      </figure>
    );
  }

  return (
    <figure className="relative">
      <img src={src} alt={alt || ''} className="rounded-lg w-full" />
      {caption && pos === 'overlay-bottom' && (
        <figcaption className="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-sm p-2 rounded-b-lg">
          {caption}
        </figcaption>
      )}
      {caption && pos === 'below' && (
        <figcaption className="text-sm text-gray-500 mt-2 text-center">
          {caption}
        </figcaption>
      )}
    </figure>
  );
};
