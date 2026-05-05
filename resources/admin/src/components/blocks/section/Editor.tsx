import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { SelectField } from '@/components/editor/fields/SelectField';
import BackgroundEditor from '@/components/editor/BackgroundEditor';

export const SectionEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <BackgroundEditor data={data} onChange={(updates) => onUpdate(updates)} />

      <SelectField
        label="Padding"
        value={(data.padding as string) || 'md'}
        onChange={(v) => update('padding', v)}
        options={[
          { value: 'none', label: 'None' },
          { value: 'sm', label: 'Small' },
          { value: 'md', label: 'Medium' },
          { value: 'lg', label: 'Large' },
          { value: 'xl', label: 'Extra Large' },
        ]}
      />
      <TextField label="Max Width" value={(data.max_width as string) || ''} onChange={(v) => update('max_width', v)} placeholder="1200px" />
      <TextField label="Anchor ID" value={(data.anchor_id as string) || ''} onChange={(v) => update('anchor_id', v)} placeholder="my-section" />
    </div>
  );
};
