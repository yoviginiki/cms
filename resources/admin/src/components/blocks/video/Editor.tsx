import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { ToggleField } from '@/components/editor/fields/ToggleField';
import { AssetField } from '@/components/ui/AssetPicker';

export const VideoEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    url: string;
    autoplay: boolean;
    muted: boolean;
    poster: string;
  };

  const update = (field: string, value: string | boolean) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">
      <TextField
        label="Video URL"
        value={data.url || ''}
        onChange={(v) => update('url', v)}
        placeholder="https://youtube.com/watch?v=... or video file URL"
      />
      <ToggleField label="Autoplay" value={!!data.autoplay} onChange={(v) => update('autoplay', v)} />
      <ToggleField label="Muted" value={!!data.muted} onChange={(v) => update('muted', v)} />
      <AssetField label="Poster image" value={data.poster || ''} onChange={(v) => update('poster', v)} accept="image" />
    </div>
  );
};
