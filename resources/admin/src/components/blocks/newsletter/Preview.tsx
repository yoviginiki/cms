import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

export const NewsletterPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { heading: string; description: string; buttonText: string; endpoint: string; style: string };
  const isCard = data.style === 'card';
  const isFull = data.style === 'full-width';

  return (
    <div className={`rounded-lg p-4 ${isCard ? 'border border-gray-200 text-center' : isFull ? 'bg-blue-50 text-center' : ''}`}>
      <div className="font-semibold text-sm mb-1">{data.heading}</div>
      <div className="text-xs text-gray-500 mb-3">{data.description}</div>
      <div className={`flex gap-2 ${isCard || isFull ? 'justify-center' : ''}`}>
        <div className="h-8 bg-gray-100 border border-gray-200 rounded-md flex-1 max-w-xs" />
        <button type="button" className="bg-blue-600 text-white px-4 py-1 rounded-md text-xs font-medium cursor-default">
          {data.buttonText}
        </button>
      </div>
    </div>
  );
};
