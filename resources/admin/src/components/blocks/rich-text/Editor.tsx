import React, { useState } from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const RichTextEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const content = (block.data.content as string) || '';
  const [showSource, setShowSource] = useState(false);

  return (
    <div className="space-y-3">
      <p className="text-xs text-gray-500">
        Edit the content directly in the block preview (click the block). Use the toolbar for formatting.
      </p>

      <label className="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" checked={showSource} onChange={e => setShowSource(e.target.checked)}
          className="checkbox checkbox-xs" />
        <span className="text-xs text-gray-500">Show HTML source</span>
      </label>

      {showSource && (
        <textarea value={content}
          onChange={e => onUpdate({ ...block.data, content: e.target.value })}
          className="w-full h-40 font-mono text-xs p-2 border border-gray-200 rounded bg-gray-50 resize-y"
          placeholder="<p>HTML source code</p>" />
      )}
    </div>
  );
};
