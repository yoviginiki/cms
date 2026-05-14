import { useState } from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { ToggleField } from '@/components/editor/fields';

export const RichTextEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const content = (block.data.content as string) || '';
  const [showSource, setShowSource] = useState(false);

  return (
    <div className="space-y-3">
      <p className="text-xs text-base-content/40">
        Edit the content directly in the block preview. Use the toolbar for formatting.
      </p>
      <ToggleField label="Show HTML source" value={showSource} onChange={setShowSource} />
      {showSource && (
        <textarea value={content}
          onChange={e => onUpdate({ ...block.data, content: e.target.value })}
          className="textarea textarea-bordered textarea-sm w-full text-[11px] font-mono h-40" />
      )}
    </div>
  );
};
