import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { SelectField } from '@/components/editor/fields/SelectField';

export const GroupEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { tag: string };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <SelectField
        label="HTML Tag"
        value={data.tag || 'div'}
        onChange={(v) => update('tag', v)}
        options={[
          { value: 'div', label: '<div>' },
          { value: 'section', label: '<section>' },
          { value: 'article', label: '<article>' },
        ]}
      />
    </div>
  );
};
