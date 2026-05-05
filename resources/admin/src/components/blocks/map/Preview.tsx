import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const MapPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { lat: number; lng: number; zoom: number; markerLabel: string; height: string };

  return (
    <div
      className="flex items-center justify-center rounded-lg border border-gray-200 bg-gray-100 text-center"
      style={{ height: data.height || '400px' }}
    >
      <div>
        <div className="text-2xl mb-2">📍</div>
        <div className="text-sm font-medium">{data.markerLabel || 'Map Location'}</div>
        <div className="text-xs text-gray-500 mt-1">
          {data.lat}, {data.lng} (zoom: {data.zoom})
        </div>
        <a
          href={`https://maps.google.com/?q=${data.lat},${data.lng}`}
          target="_blank"
          rel="noopener noreferrer"
          className="text-xs text-blue-600 mt-2 inline-block"
        >
          View on Google Maps
        </a>
      </div>
    </div>
  );
};
