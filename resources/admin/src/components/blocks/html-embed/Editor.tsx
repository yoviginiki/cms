import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const HtmlEmbedEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { html: string };

  return (
    <div className="space-y-3">
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">HTML Code</label>
        <textarea
          value={data.html || ''}
          onChange={(e) => onUpdate({ ...block.data, html: e.target.value })}
          rows={8}
          className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500"
          placeholder="<div>Your HTML here...</div>"
        />
      </div>
    </div>
  );
};
