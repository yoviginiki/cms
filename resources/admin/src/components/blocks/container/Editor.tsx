import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { SelectField } from '@/components/editor/fields/SelectField';
import { TextField } from '@/components/editor/fields/TextField';
import { ToggleField } from '@/components/editor/fields/ToggleField';
import BackgroundEditor from '@/components/editor/BackgroundEditor';

export const ContainerEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;
  const maxWidth = (data.maxWidth as string) || '1200';
  const isCustom = !['960', '1080', '1200', '1440'].includes(maxWidth);

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <BackgroundEditor data={data} onChange={(updates) => onUpdate(updates)} />

      <SelectField
        label="Max Width"
        value={isCustom ? 'custom' : maxWidth}
        onChange={(v) => update('maxWidth', v === 'custom' ? (data.customMaxWidth as string || '1000') : v)}
        options={[
          { value: '960', label: '960px' },
          { value: '1080', label: '1080px' },
          { value: '1200', label: '1200px' },
          { value: '1440', label: '1440px' },
          { value: 'custom', label: 'Custom' },
        ]}
      />
      {isCustom && (
        <TextField label="Custom Max Width (px)" value={maxWidth} onChange={v => update('maxWidth', v)} placeholder="1000" />
      )}
      <ToggleField label="Centered" value={(data.centered as boolean) ?? true} onChange={v => update('centered', v)} />
    </div>
  );
};
