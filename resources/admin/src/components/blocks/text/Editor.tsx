import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const TextEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const { content } = block.data as { content: string };

  return (
    <div className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Content (HTML)</label>
        <textarea
          value={content || ''}
          onChange={(e) => onUpdate({ ...block.data, content: e.target.value })}
          rows={8}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
      </div>
    </div>
  );
};
