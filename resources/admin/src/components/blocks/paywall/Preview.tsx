import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const PaywallPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    previewLines: number;
    blurIntensity: number;
    heading: string;
    ctaText: string;
    ctaUrl: string;
  };

  return (
    <div className="relative rounded border border-gray-200 overflow-hidden">
      <div className="p-4">
        <div className="text-xs text-gray-400 text-center py-2 border border-dashed border-gray-300 rounded mb-3">
          Children content (visible above paywall)
        </div>
      </div>
      <div
        className="relative p-6 text-center"
        style={{
          background: 'linear-gradient(to bottom, rgba(255,255,255,0.3), rgba(255,255,255,0.95) 40%)',
        }}
      >
        <div className="text-sm font-semibold text-gray-700 mb-2">
          {data.heading || 'Subscribe to continue reading'}
        </div>
        <button
          type="button"
          className="bg-blue-600 text-white px-4 py-1.5 rounded-md text-sm font-medium cursor-default"
        >
          {data.ctaText || 'Subscribe'}
        </button>
        <div className="text-xs text-gray-400 mt-2">
          Blur: {data.blurIntensity || 8}px | Preview lines: {data.previewLines || 3}
        </div>
      </div>
    </div>
  );
};
