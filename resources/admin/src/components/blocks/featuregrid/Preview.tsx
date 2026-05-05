import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

interface FeatureItem {
  icon: string;
  title: string;
  description: string;
}

export const FeaturegridPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { items: FeatureItem[]; columns: number; style: string };
  const items = data.items || [];
  const cols = data.columns || 3;

  return (
    <div className="grid gap-4" style={{ gridTemplateColumns: `repeat(${cols}, 1fr)` }}>
      {items.map((item, i) => (
        <div key={i} className="rounded-lg border border-gray-200 p-4 text-center">
          <div className="text-2xl mb-2">{item.icon}</div>
          <div className="font-semibold text-sm mb-1">{item.title}</div>
          <div className="text-xs text-gray-500">{item.description}</div>
        </div>
      ))}
    </div>
  );
};
