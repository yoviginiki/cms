import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { SelectField } from '@/components/editor/fields/SelectField';
import { RowLayoutPicker } from './RowLayoutPicker';

export const RowEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <RowLayoutPicker
        value={(data.layout as string) || '1/2+1/2'}
        onChange={(v) => update('layout', v)}
      />
      <TextField
        label="Gap"
        value={(data.gap as string) || '16px'}
        onChange={(v) => update('gap', v)}
        placeholder="16px"
      />
      <TextField
        label="Max Width"
        value={(data.max_width as string) || ''}
        onChange={(v) => update('max_width', v)}
        placeholder="e.g. 1000px"
      />
      <SelectField
        label="Vertical Align"
        value={(data.vertical_align as string) || 'stretch'}
        onChange={(v) => update('vertical_align', v)}
        options={[
          { value: 'start', label: 'Top' },
          { value: 'center', label: 'Center' },
          { value: 'end', label: 'Bottom' },
          { value: 'stretch', label: 'Stretch' },
        ]}
      />
    </div>
  );
};
