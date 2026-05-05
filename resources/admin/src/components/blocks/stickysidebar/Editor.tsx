import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { SelectField } from '@/components/editor/fields/SelectField';
import { TextField } from '@/components/editor/fields/TextField';

export const StickySidebarEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    sidebarSide: string;
    sidebarWidth: string;
    gap: string;
    stickyOffset: string;
  };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <SelectField
        label="Sidebar Side"
        value={data.sidebarSide || 'right'}
        onChange={(v) => update('sidebarSide', v)}
        options={[
          { value: 'left', label: 'Left' },
          { value: 'right', label: 'Right' },
        ]}
      />
      <TextField
        label="Sidebar Width"
        value={data.sidebarWidth || '300px'}
        onChange={(v) => update('sidebarWidth', v)}
        placeholder="300px"
      />
      <TextField
        label="Gap"
        value={data.gap || '32px'}
        onChange={(v) => update('gap', v)}
        placeholder="32px"
      />
      <TextField
        label="Sticky Offset (top)"
        value={data.stickyOffset || '80px'}
        onChange={(v) => update('stickyOffset', v)}
        placeholder="80px"
      />
    </div>
  );
};
