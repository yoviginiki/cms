import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const BeforeafterPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { beforeSrc, afterSrc, beforeLabel, afterLabel, initialPosition } = block.data as {
    beforeSrc: string;
    afterSrc: string;
    beforeLabel: string;
    afterLabel: string;
    initialPosition: number;
  };

  const pos = initialPosition ?? 50;

  if (!beforeSrc && !afterSrc) {
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
              d="M9 17V7m0 10l-3-3m3 3l3-3M15 7v10m0-10l3 3m-3-3l-3 3"
            />
          </svg>
          <p className="text-sm">Before / After comparison</p>
        </div>
      </div>
    );
  }

  return (
    <div className="relative rounded-lg overflow-hidden" style={{ height: '300px' }}>
      {afterSrc && (
        <img src={afterSrc} alt={afterLabel || 'After'} className="absolute inset-0 w-full h-full object-cover" />
      )}
      {beforeSrc && (
        <div className="absolute inset-0 overflow-hidden" style={{ width: `${pos}%` }}>
          <img src={beforeSrc} alt={beforeLabel || 'Before'} className="w-full h-full object-cover" style={{ minWidth: '100%' }} />
        </div>
      )}
      <div className="absolute top-0 bottom-0" style={{ left: `${pos}%`, transform: 'translateX(-50%)' }}>
        <div className="h-full w-0.5 bg-white shadow" />
      </div>
      <span className="absolute top-2 left-2 bg-black/50 text-white text-xs px-2 py-1 rounded">
        {beforeLabel || 'Before'}
      </span>
      <span className="absolute top-2 right-2 bg-black/50 text-white text-xs px-2 py-1 rounded">
        {afterLabel || 'After'}
      </span>
    </div>
  );
};
