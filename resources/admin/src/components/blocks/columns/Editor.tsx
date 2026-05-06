import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const ColumnsEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { columnCount, gap } = block.data as {
    columnCount: number;
    gap: string;
  };

  const update = (field: string, value: string | number) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Column Count</label>
        <select
          value={columnCount}
          onChange={(e) => update('columnCount', parseInt(e.target.value, 10))}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        >
          <option value={1}>1</option>
          <option value={2}>2</option>
          <option value={3}>3</option>
          <option value={4}>4</option>
          <option value={5}>5</option>
          <option value={6}>6</option>
        </select>
      </div>
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Gap</label>
        <select
          value={gap || 'medium'}
          onChange={(e) => update('gap', e.target.value)}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        >
          <option value="none">None</option>
          <option value="small">Small</option>
          <option value="medium">Medium</option>
          <option value="large">Large</option>
        </select>
      </div>
    </div>
  );
};
