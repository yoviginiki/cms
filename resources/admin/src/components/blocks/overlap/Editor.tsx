import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { NumberField } from '@/components/editor/fields/NumberField';

export const OverlapEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    offsetY: string;
    offsetX: string;
    zIndex: number;
  };

  const update = (field: string, value: string | number) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <TextField
        label="Offset Y (vertical)"
        value={data.offsetY || '-40px'}
        onChange={(v) => update('offsetY', v)}
        placeholder="-40px"
      />
      <TextField
        label="Offset X (horizontal)"
        value={data.offsetX || '0'}
        onChange={(v) => update('offsetX', v)}
        placeholder="0"
      />
      <NumberField
        label="Z-Index"
        value={data.zIndex ?? 1}
        onChange={(v) => update('zIndex', v)}
        min={-10}
        max={100}
      />
    </div>
  );
};
