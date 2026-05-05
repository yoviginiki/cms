import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { SelectField } from '@/components/editor/fields/SelectField';
import { TextField } from '@/components/editor/fields/TextField';
import { ColorField } from '@/components/editor/fields/ColorField';

export const DividerEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    style: string;
    color: string;
    thickness: string;
    width: string;
    alignment: string;
  };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <SelectField
        label="Style"
        value={data.style || 'solid'}
        onChange={(v) => update('style', v)}
        options={[
          { value: 'solid', label: 'Solid' },
          { value: 'dashed', label: 'Dashed' },
          { value: 'dotted', label: 'Dotted' },
        ]}
      />
      <ColorField
        label="Color"
        value={data.color || ''}
        onChange={(v) => update('color', v)}
      />
      <TextField
        label="Thickness"
        value={data.thickness || '1px'}
        onChange={(v) => update('thickness', v)}
        placeholder="1px"
      />
      <SelectField
        label="Width"
        value={data.width || '100%'}
        onChange={(v) => update('width', v)}
        options={[
          { value: '100%', label: '100%' },
          { value: '75%', label: '75%' },
          { value: '50%', label: '50%' },
          { value: '25%', label: '25%' },
        ]}
      />
      <SelectField
        label="Alignment"
        value={data.alignment || 'center'}
        onChange={(v) => update('alignment', v)}
        options={[
          { value: 'left', label: 'Left' },
          { value: 'center', label: 'Center' },
          { value: 'right', label: 'Right' },
        ]}
      />
    </div>
  );
};
