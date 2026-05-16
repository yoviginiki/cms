import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

interface TimelineItem {
  date: string;
  title: string;
  description: string;
}

export const TimelinePreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { items: TimelineItem[]; layout: string; lineStyle: string };
  const items = data.items || [];

  return (
    <div className="relative pl-6 border-l-2 border-gray-200 space-y-6">
      {(items || []).map((item, i) => (
        <div key={i} className="relative">
          <div className="absolute -left-[25px] w-3 h-3 rounded-full bg-blue-500 border-2 border-white" />
          <div className="text-xs text-gray-400 mb-1">{item.date}</div>
          <div className="text-sm font-semibold">{item.title}</div>
          <div className="text-xs text-gray-500">{item.description}</div>
        </div>
      ))}
    </div>
  );
};
