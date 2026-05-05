import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { SelectField } from '@/components/editor/fields/SelectField';

export const SpacerEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { height: string };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <SelectField
        label="Height"
        value={data.height || 'md'}
        onChange={(v) => update('height', v)}
        options={[
          { value: 'sm', label: 'Small (16px)' },
          { value: 'md', label: 'Medium (32px)' },
          { value: 'lg', label: 'Large (64px)' },
          { value: 'xl', label: 'Extra Large (96px)' },
          { value: 'custom', label: 'Custom' },
        ]}
      />
    </div>
  );
};
