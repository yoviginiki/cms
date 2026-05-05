import React from 'react';
import type { BlockComponentProps } from '@/types/blocks';

interface StatItem {
  value: string;
  label: string;
  prefix: string;
  suffix: string;
}

export const StatsPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const data = block.data as { items: StatItem[]; columns: number };
  const items = data.items || [];
  const cols = data.columns || 3;

  return (
    <div className="grid gap-4" style={{ gridTemplateColumns: `repeat(${cols}, 1fr)` }}>
      {items.map((item, i) => (
        <div key={i} className="text-center p-4 rounded-lg border border-gray-200">
          <div className="text-2xl font-bold">
            {item.prefix}{item.value}{item.suffix}
          </div>
          <div className="text-xs text-gray-500 mt-1">{item.label}</div>
        </div>
      ))}
    </div>
  );
};
