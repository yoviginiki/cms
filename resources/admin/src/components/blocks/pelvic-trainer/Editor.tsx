import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { TextArea } from '@/components/editor/fields/TextArea';
import { NumberField } from '@/components/editor/fields/NumberField';

interface Phase { label: string; cue: string; seconds: number }

export const PelvicTrainerEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as { eyebrow?: string; rounds?: number; phases?: Phase[] };
  const phases: Phase[] = Array.isArray(data.phases) ? data.phases : [];
  const set = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });
  const setPhase = (i: number, key: keyof Phase, value: unknown) => set('phases', phases.map((p, idx) => (idx === i ? { ...p, [key]: value } : p)));
  const addPhase = () => set('phases', [...phases, { label: 'Phase', cue: '', seconds: 5 }]);
  const removePhase = (i: number) => set('phases', phases.filter((_, idx) => idx !== i));

  return (
    <div className="space-y-3">
      <TextField label="Eyebrow" value={data.eyebrow || ''} onChange={(v) => set('eyebrow', v)} />
      <NumberField label="Rounds" value={data.rounds ?? 6} onChange={(v) => set('rounds', v)} min={1} max={60} />
      <div className="pt-2 border-t border-gray-200">
        <div className="flex items-center justify-between mb-2">
          <span className="text-xs font-semibold uppercase tracking-wide text-gray-500">Phases</span>
          <button type="button" onClick={addPhase} className="text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200">+ Add phase</button>
        </div>
        <div className="space-y-3">
          {phases.map((p, i) => (
            <div key={i} className="p-2 rounded border border-gray-200 space-y-2">
              <div className="flex items-center gap-2">
                <div className="flex-1"><TextField label={`Phase ${i + 1} label`} value={p.label || ''} onChange={(v) => setPhase(i, 'label', v)} /></div>
                <button type="button" onClick={() => removePhase(i)} className="text-xs text-red-600 mt-5">Remove</button>
              </div>
              <TextArea label="Cue" value={p.cue || ''} onChange={(v) => setPhase(i, 'cue', v)} rows={2} />
              <NumberField label="Seconds" value={p.seconds ?? 5} onChange={(v) => setPhase(i, 'seconds', v)} min={1} max={120} />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};
