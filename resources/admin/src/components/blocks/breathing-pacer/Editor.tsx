import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import { NumberField } from '@/components/editor/fields/NumberField';
import { ToggleField } from '@/components/editor/fields/ToggleField';

interface Phase { label: string; value: number; min: number; max: number; step: number; locked: boolean }

export const BreathingPacerEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const data = block.data as {
    eyebrow?: string; title?: string; soundLabel?: string; soundDefault?: boolean;
    advancedAt?: number; defaultRounds?: number; roundOptions?: number[]; phases?: Phase[];
  };
  const phases: Phase[] = Array.isArray(data.phases) ? data.phases : [];
  const set = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });
  const setPhase = (i: number, key: keyof Phase, value: unknown) => {
    const next = phases.map((p, idx) => (idx === i ? { ...p, [key]: value } : p));
    set('phases', next);
  };
  const addPhase = () => set('phases', [...phases, { label: 'Phase', value: 3, min: 3, max: 60, step: 1, locked: true }]);
  const removePhase = (i: number) => set('phases', phases.filter((_, idx) => idx !== i));

  return (
    <div className="space-y-3">
      <TextField label="Eyebrow" value={data.eyebrow || ''} onChange={(v) => set('eyebrow', v)} />
      <TextField label="Title" value={data.title || ''} onChange={(v) => set('title', v)} />
      <TextField label="Sound toggle label" value={data.soundLabel || ''} onChange={(v) => set('soundLabel', v)} />
      <ToggleField label="Cues on by default" value={data.soundDefault !== false} onChange={(v) => set('soundDefault', v)} />
      <NumberField label="Default rounds" value={data.defaultRounds ?? 5} onChange={(v) => set('defaultRounds', v)} min={1} max={99} />
      <TextField
        label="Round options (comma separated)"
        value={(data.roundOptions || []).join(', ')}
        onChange={(v) => set('roundOptions', v.split(',').map((s) => parseInt(s.trim(), 10)).filter((n) => n > 0))}
      />
      <NumberField label="Expert-warning threshold (seconds, 0 = off)" value={data.advancedAt ?? 20} onChange={(v) => set('advancedAt', v)} min={0} max={600} />

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
              <div className="grid grid-cols-2 gap-2">
                <NumberField label="Default (s)" value={p.value ?? 3} onChange={(v) => setPhase(i, 'value', v)} min={0} max={600} />
                <NumberField label="Step" value={p.step ?? 1} onChange={(v) => setPhase(i, 'step', v)} min={1} max={60} />
                <NumberField label="Min (s)" value={p.min ?? 3} onChange={(v) => setPhase(i, 'min', v)} min={0} max={600} />
                <NumberField label="Max (s)" value={p.max ?? 60} onChange={(v) => setPhase(i, 'max', v)} min={0} max={600} />
              </div>
              <ToggleField label="Locked (follows first phase)" value={!!p.locked} onChange={(v) => setPhase(i, 'locked', v)} />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};
