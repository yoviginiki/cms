import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { SelectField } from '@/components/editor/fields/SelectField';

export const ColumnEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <TextField
        label="Padding"
        value={(data.padding as string) || ''}
        onChange={(v) => update('padding', v)}
        placeholder="e.g. 16px"
      />
      <SelectField
        label="Vertical Align"
        value={(data.vertical_align as string) || 'start'}
        onChange={(v) => update('vertical_align', v)}
        options={[
          { value: 'start', label: 'Top' },
          { value: 'center', label: 'Center' },
          { value: 'end', label: 'Bottom' },
          { value: 'stretch', label: 'Stretch' },
        ]}
      />
      <TextField
        label="Background Color"
        value={(data.background_color as string) || ''}
        onChange={(v) => update('background_color', v)}
        placeholder="#ffffff"
      />
    </div>
  );
};
