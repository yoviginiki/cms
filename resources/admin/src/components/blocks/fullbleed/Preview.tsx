import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const FullbleedPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const { src, overlayText, overlayPosition, scrimOpacity, minHeight } = block.data as {
    src: string;
    overlayText: string;
    overlayPosition: string;
    scrimOpacity: number;
    minHeight: string;
  };

  const positionClasses: Record<string, string> = {
    center: 'items-center justify-center text-center',
    'bottom-left': 'items-end justify-start text-left',
    'bottom-right': 'items-end justify-end text-right',
  };

  if (!src) {
    return (
      <div
        className="bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center"
        style={{ minHeight: minHeight || '60vh' }}
      >
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
              d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"
            />
          </svg>
          <p className="text-sm">Full bleed image</p>
        </div>
      </div>
    );
  }

  return (
    <div
      className="relative rounded-lg overflow-hidden flex"
      style={{
        minHeight: minHeight || '60vh',
        backgroundImage: `url(${src})`,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
      }}
    >
      <div
        className="absolute inset-0"
        style={{ backgroundColor: `rgba(0,0,0,${scrimOpacity ?? 0.4})` }}
      />
      <div className={`relative z-10 flex w-full p-8 ${positionClasses[overlayPosition] || positionClasses.center}`}>
        {overlayText && (
          <p className="text-white text-2xl font-bold max-w-2xl">{overlayText}</p>
        )}
      </div>
    </div>
  );
};
