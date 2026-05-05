import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { SelectField } from '@/components/editor/fields/SelectField';

export const ButtonEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    text: string;
    url: string;
    style: string;
    size: string;
    target: string;
  };

  const update = (field: string, value: string) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <TextField label="Text" value={data.text || ''} onChange={(v) => update('text', v)} />
      <TextField label="URL" value={data.url || ''} onChange={(v) => update('url', v)} placeholder="https://..." />
      <SelectField
        label="Style"
        value={data.style || 'primary'}
        onChange={(v) => update('style', v)}
        options={[
          { value: 'primary', label: 'Primary' },
          { value: 'secondary', label: 'Secondary' },
          { value: 'outline', label: 'Outline' },
          { value: 'ghost', label: 'Ghost' },
        ]}
      />
      <SelectField
        label="Size"
        value={data.size || 'md'}
        onChange={(v) => update('size', v)}
        options={[
          { value: 'sm', label: 'Small' },
          { value: 'md', label: 'Medium' },
          { value: 'lg', label: 'Large' },
        ]}
      />
      <SelectField
        label="Target"
        value={data.target || '_self'}
        onChange={(v) => update('target', v)}
        options={[
          { value: '_self', label: 'Same Window' },
          { value: '_blank', label: 'New Window' },
        ]}
      />
    </div>
  );
};
