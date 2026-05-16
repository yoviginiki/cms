import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import BackgroundEditor from '@/components/editor/BackgroundEditor';

export const SectionEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <BackgroundEditor data={data} onChange={(updates) => onUpdate(updates)} />

      <div className="grid grid-cols-2 gap-2">
        <TextField
          label="Padding Top"
          value={(data.padding_top as string) || '40px'}
          onChange={(v) => update('padding_top', v)}
          placeholder="40px"
        />
        <TextField
          label="Padding Bottom"
          value={(data.padding_bottom as string) || '40px'}
          onChange={(v) => update('padding_bottom', v)}
          placeholder="40px"
        />
      </div>

      <TextField
        label="Max Width"
        value={(data.max_width as string) || '1200px'}
        onChange={(v) => update('max_width', v)}
        placeholder="1200px"
      />
      <TextField
        label="Anchor ID"
        value={(data.anchor_id as string) || ''}
        onChange={(v) => update('anchor_id', v)}
        placeholder="my-section"
      />
    </div>
  );
};
