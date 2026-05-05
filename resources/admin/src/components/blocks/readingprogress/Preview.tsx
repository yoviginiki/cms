import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const styleLabels: Record<string, string> = {
  'top-bar': 'Top Bar',
  'circular': 'Circular',
  'side-bar': 'Side Bar',
};

export const ReadingprogressPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as {
    style: string;
    color: string;
    height: string;
  };

  const barColor = data.color || '#3b82f6';

  return (
    <div className="py-2">
      <div className="flex items-center gap-3">
        <div
          className="flex-1 rounded-full overflow-hidden"
          style={{ height: data.height || '3px', backgroundColor: '#e5e7eb' }}
        >
          <div
            className="h-full rounded-full"
            style={{ width: '65%', backgroundColor: barColor, transition: 'width 0.3s' }}
          />
        </div>
        <span className="text-[10px] text-gray-400">
          {styleLabels[data.style] || 'Top Bar'}
        </span>
      </div>
      <p className="text-[10px] text-gray-300 mt-1 italic">
        Reading progress indicator ({data.height || '3px'})
      </p>
    </div>
  );
};
