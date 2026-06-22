import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { TextField } from '@/components/editor/fields/TextField';
import BackgroundEditor from '@/components/editor/BackgroundEditor';
import { CardEffectsPanel } from '@/components/editor/fields/CardEffectsPanel';
import type { CardEffects } from '@/lib/blockEffects';

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
      {/* ─── Card Effects ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <CardEffectsPanel
          value={(block.data as any).effects || {}}
          onChange={(v: CardEffects) => update('effects', v)}
        />
      </div>

      {/* ─── Experience Mode (per-section panel transitions) ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <div className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Experience Panel</div>
        <p className="text-[10px] text-base-content/40 mb-2">These settings apply when the page is in Cinematic experience mode. Each section becomes a full-viewport panel.</p>
        <div className="space-y-2">
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Panel Transition</label>
            <select className="select select-bordered select-sm w-full text-[12px]"
              value={(data.experienceTransition as string) || 'fade'}
              onChange={e => update('experienceTransition', e.target.value)}>
              <option value="fade">Fade</option>
              <option value="slide-up">Slide Up</option>
              <option value="slide-left">Slide Left</option>
              <option value="cover">Cover</option>
              <option value="mask-wipe">Mask Wipe</option>
              <option value="zoom">Zoom</option>
            </select>
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Enter Animation</label>
            <select className="select select-bordered select-sm w-full text-[12px]"
              value={(data.experienceEnter as string) || 'fade-up'}
              onChange={e => update('experienceEnter', e.target.value)}>
              <option value="none">None</option>
              <option value="fade-up">Fade Up</option>
              <option value="stagger">Stagger Children</option>
              <option value="clip">Clip Reveal</option>
            </select>
          </div>
          <label className="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" className="checkbox checkbox-xs checkbox-primary"
              checked={!!data.experiencePin}
              onChange={e => update('experiencePin', e.target.checked)} />
            <span className="text-[11px] text-base-content/50">Pin panel (hold while inner content advances)</span>
          </label>
        </div>
      </div>
    </div>
  );
};
