import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { SelectField } from '@/components/editor/fields/SelectField';

export const GridEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    templateColumns: string;
    templateRows: string;
    gap: string;
    autoFlow: string;
  };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <TextField
        label="Grid Template Columns"
        value={data.templateColumns || '1fr 1fr'}
        onChange={(v) => update('templateColumns', v)}
        placeholder="1fr 1fr 1fr"
      />
      <TextField
        label="Grid Template Rows"
        value={data.templateRows || 'auto'}
        onChange={(v) => update('templateRows', v)}
        placeholder="auto"
      />
      <TextField
        label="Gap"
        value={data.gap || '16px'}
        onChange={(v) => update('gap', v)}
        placeholder="16px"
      />
      <SelectField
        label="Auto Flow"
        value={data.autoFlow || 'row'}
        onChange={(v) => update('autoFlow', v)}
        options={[
          { value: 'row', label: 'Row' },
          { value: 'column', label: 'Column' },
          { value: 'dense', label: 'Dense' },
          { value: 'row dense', label: 'Row Dense' },
          { value: 'column dense', label: 'Column Dense' },
        ]}
      />
    </div>
  );
};
