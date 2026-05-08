import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import BackgroundEditor from '@/components/editor/BackgroundEditor';
import { TextField } from '@/components/editor/fields';

export const HeroEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as Record<string, unknown>;

  const update = (field: string, value: unknown) => {
    onUpdate({ ...block.data, [field]: value });
  };

  return (
    <div className="space-y-3">

      {/* ── Content Fields ── */}
      <TextField
        label="Title"
        value={(data.title as string) || ''}
        onChange={(v) => update('title', v)}
        placeholder="Add hero title"
        helperText="Main heading displayed prominently in the hero"
      />
      <TextField
        label="Subtitle"
        value={(data.subtitle as string) || ''}
        onChange={(v) => update('subtitle', v)}
        placeholder="Add subtitle or tagline"
      />

      {/* ── Background (settings panel — managed by BackgroundEditor) ── */}
      <BackgroundEditor data={data} onChange={(updates) => onUpdate(updates)} />

      {/* ── CTA / Link Fields ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Call to Action</div>
      <TextField
        label="Button Text"
        value={(data.ctaText as string) || ''}
        onChange={(v) => update('ctaText', v)}
        placeholder="e.g. Learn More"
      />
      <TextField
        label="Button URL"
        value={(data.ctaUrl as string) || ''}
        onChange={(v) => update('ctaUrl', v)}
        placeholder="https://..."
        helperText="Leave empty to hide the button"
      />

      {/* ── Accessibility Fields ── */}
      <div className="divider text-[10px] text-base-content/40 my-1">Accessibility</div>
      <TextField
        label="Background Alt Text"
        value={(data.alt as string) || ''}
        onChange={(v) => update('alt', v)}
        placeholder="Describe the background image for screen readers"
        helperText="Required when using a background image"
      />

    </div>
  );
};
