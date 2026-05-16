import { useState } from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { ToggleField } from '@/components/editor/fields';

export const ParagraphEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const content = (data.content as string) || '';
  const [showSource, setShowSource] = useState(false);

  return (
    <div className="space-y-3">
      <p className="text-xs text-base-content/40">Edit directly in the block preview above.</p>
      <ToggleField label="Show HTML source" value={showSource} onChange={setShowSource} />
      {showSource && (
        <textarea value={content}
          onChange={e => onUpdate({ ...block.data, content: e.target.value })}
          className="textarea textarea-bordered textarea-sm w-full text-[11px] font-mono h-32" />
      )}
    </div>
  );
};
