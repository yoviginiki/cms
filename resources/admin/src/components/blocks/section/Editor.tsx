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

  const widthMode = ['contained', 'wide', 'full'].includes(data.width_mode as string)
    ? (data.width_mode as string)
    : 'contained';

  return (
    <div className="space-y-3">
      <BackgroundEditor data={data} onChange={(updates) => onUpdate(updates)} />

      <div>
        <label className="text-[11px] text-base-content/50 mb-1.5 block">Content Width</label>
        <div className="grid grid-cols-3 gap-0 border border-base-300/30" role="radiogroup" aria-label="Content width">
          {([
            { value: 'contained', label: 'Contained' },
            { value: 'wide', label: 'Wide' },
            { value: 'full', label: 'Full-bleed' },
          ] as const).map((opt, i) => (
            <button
              key={opt.value}
              type="button"
              role="radio"
              aria-checked={widthMode === opt.value}
              onClick={() => update('width_mode', opt.value)}
              className={`text-[11px] py-1.5 transition-colors outline-none focus-visible:ring-1 focus-visible:ring-primary ${i > 0 ? 'border-l border-base-300/30' : ''} ${
                widthMode === opt.value
                  ? 'bg-primary/10 text-primary font-medium'
                  : 'text-base-content/50 hover:bg-base-300/10'
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>
        <p className="text-[10px] text-base-content/40 mt-1">
          {widthMode === 'contained'
            ? 'Centered to the Max Width below.'
            : widthMode === 'wide'
            ? 'Centered to a wide 1440px container.'
            : 'Content spans the full section, edge to edge.'}
        </p>
      </div>

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

      {widthMode === 'contained' && (
        <TextField
          label="Max Width"
          value={(data.max_width as string) || '1200px'}
          onChange={(v) => update('max_width', v)}
          placeholder="1200px"
        />
      )}
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

      {/* ─── Scene Preset (Cinematic Experience Mode) ─── */}
      <div className="border-t border-base-300/20 pt-3">
        <div className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Motion Scene</div>
        <p className="text-[10px] text-base-content/40 mb-2">Active when the page uses Cinematic experience mode. Each section becomes a scene with its own choreography.</p>
        <div>
          <label className="text-[11px] text-base-content/50 mb-1 block">Scene Preset</label>
          <select className="select select-bordered select-sm w-full text-[12px]"
            value={(data.scene as string) || 'fade-through'}
            onChange={e => update('scene', e.target.value)}>
            <option value="fade-through">Fade Through (calm default)</option>
            <option value="pinned-statement">Pinned Statement (content builds on scroll)</option>
            <option value="scroll-gallery">Scroll Gallery (crossfade children on scroll)</option>
            <option value="reveal">Reveal (split-text + staggered entrance)</option>
            <option value="parallax-split">Parallax Split (counter-motion columns)</option>
          </select>
        </div>
      </div>
    </div>
  );
};
