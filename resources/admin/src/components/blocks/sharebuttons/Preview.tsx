import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

const platformLabels: Record<string, string> = {
  twitter: 'X',
  facebook: 'FB',
  linkedin: 'in',
  email: '@',
  copy: 'Link',
};

export const SharebuttonsPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { platforms: string[]; style: string; showLabels: boolean };
  const platforms = data.platforms || [];
  const isButtons = data.style === 'buttons';

  return (
    <div className="flex gap-2 flex-wrap">
      {(platforms || []).map((p) => (
        <span
          key={p}
          className={`inline-flex items-center gap-1 text-xs ${
            isButtons
              ? 'px-3 py-1.5 bg-gray-100 rounded-md border border-gray-200'
              : data.style === 'minimal'
                ? 'text-gray-500'
                : 'w-8 h-8 bg-gray-100 rounded-full justify-center'
          }`}
        >
          {platformLabels[p] || p}
          {data.showLabels && <span className="capitalize">{p}</span>}
        </span>
      ))}
    </div>
  );
};
