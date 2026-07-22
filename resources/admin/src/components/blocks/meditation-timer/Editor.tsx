import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { TextArea } from '@/components/editor/fields/TextArea';
import { NumberField } from '@/components/editor/fields/NumberField';
import { ToggleField } from '@/components/editor/fields/ToggleField';

type Journeys = Record<string, number[]>;

// Journeys edit as one "Name: 5, 10, 15" line each — friendlier than raw JSON.
const journeysToText = (j: Journeys): string =>
  Object.entries(j || {}).map(([name, days]) => `${name}: ${(days || []).join(', ')}`).join('\n');

const textToJourneys = (text: string): Journeys => {
  const out: Journeys = {};
  text.split('\n').forEach((line) => {
    const idx = line.indexOf(':');
    if (idx <= 0) return;
    const name = line.slice(0, idx).trim();
    const days = line.slice(idx + 1).split(',').map((s) => parseInt(s.trim(), 10)).filter((n) => n > 0);
    if (name && days.length) out[name] = days;
  });
  return out;
};

export const MeditationTimerEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    eyebrow?: string; title?: string; presets?: number[]; defaultMinutes?: number;
    showJourneys?: boolean; storeKey?: string; journeys?: Journeys;
  };
  const set = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-3">
      <TextField label="Eyebrow" value={data.eyebrow || ''} onChange={(v) => set('eyebrow', v)} />
      <TextField label="Title" value={data.title || ''} onChange={(v) => set('title', v)} />
      <TextField
        label="Preset minutes (comma separated)"
        value={(data.presets || []).join(', ')}
        onChange={(v) => set('presets', v.split(',').map((s) => parseInt(s.trim(), 10)).filter((n) => n > 0))}
      />
      <NumberField label="Default minutes" value={data.defaultMinutes ?? 5} onChange={(v) => set('defaultMinutes', v)} min={1} max={180} />
      <ToggleField label="Show day journeys" value={data.showJourneys !== false} onChange={(v) => set('showJourneys', v)} />
      {data.showJourneys !== false && (
        <TextArea
          label="Journeys (one per line — Name: 5, 10, 15)"
          value={journeysToText(data.journeys || {})}
          onChange={(v) => set('journeys', textToJourneys(v))}
          rows={4}
        />
      )}
      <TextField
        label="Progress storage key (unique per timer)"
        value={data.storeKey || ''}
        onChange={(v) => set('storeKey', v)}
        placeholder="rr-med"
      />
    </div>
  );
};
