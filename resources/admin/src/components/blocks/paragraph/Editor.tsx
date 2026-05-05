import React, { useState } from 'react';
import type { BlockEditorProps } from '@/types/blocks';

export const ParagraphEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const content = (block.data.content as string) || '';
  const [showSource, setShowSource] = useState(false);

  return (
    <div className="space-y-3">
      <p className="text-xs text-base-content/40">Edit directly in the block preview above.</p>
      <label className="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" checked={showSource} onChange={e => setShowSource(e.target.checked)}
          className="checkbox checkbox-xs" />
        <span className="text-xs text-base-content/40">Show HTML source</span>
      </label>
      {showSource && (
        <textarea value={content}
          onChange={e => onUpdate({ ...block.data, content: e.target.value })}
          className="textarea textarea-bordered textarea-sm w-full text-[11px] font-mono h-32" />
      )}
    </div>
  );
};
