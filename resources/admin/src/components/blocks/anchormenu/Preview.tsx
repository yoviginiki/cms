import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

interface AnchorItem { label: string; anchor: string }

export const AnchormenuPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { items: AnchorItem[]; style: string; sticky: boolean };

  return (
    <div className="rounded border border-gray-200 p-3">
      <div className="flex items-center justify-between mb-2">
        <span className="text-[10px] text-gray-400 uppercase tracking-wide">Anchor Nav</span>
        <div className="flex gap-1">
          <span className="text-[10px] bg-gray-100 text-gray-500 rounded px-1.5 py-0.5">{data.style}</span>
          {data.sticky && <span className="text-[10px] bg-blue-100 text-blue-600 rounded px-1.5 py-0.5">Sticky</span>}
        </div>
      </div>
      <div className={`flex ${data.style === 'vertical' ? 'flex-col gap-1' : 'gap-4'}`}>
        {(data.items || []).map((item, i) => (
          <span key={i} className="text-xs text-blue-500 underline">{item.label}</span>
        ))}
      </div>
    </div>
  );
};
