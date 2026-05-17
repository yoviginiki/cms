import type { VisualProps } from '@/types/blocks';
import { ShadowField } from '@/components/editor/fields/ShadowField';
import { CornerRadiusField } from '@/components/editor/fields/CornerRadiusField';
import type { ShadowCustom } from '@/lib/shadowStyles';

interface Props {
  value: VisualProps;
  onChange: (v: VisualProps) => void;
  hideBg?: boolean;
}

export function VisualPanel({ value, onChange, hideBg }: Props) {
  const update = (key: string, v: unknown) => onChange({ ...value, [key]: v || undefined });

  return (
    <div className="space-y-3">
      {!hideBg && (
        <>
          {/* ── Background (legacy — use BackgroundEditor section instead) ── */}
          <div>
            <label className="text-[10px] text-base-content/40">Background color</label>
            <div className="flex gap-2">
              <input type="color" value={value.backgroundColor || '#ffffff'} onChange={e => update('backgroundColor', e.target.value)}
                className="w-8 h-7 rounded cursor-pointer border border-base-300/30" />
              <input value={value.backgroundColor || ''} onChange={e => update('backgroundColor', e.target.value)}
                className="input input-bordered input-xs flex-1 text-[11px]" placeholder="transparent" />
            </div>
          </div>
          <div>
            <label className="text-[10px] text-base-content/40">Background image</label>
            <input value={value.backgroundImage || ''} onChange={e => update('backgroundImage', e.target.value)}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="url(https://...)" />
          </div>
          <div>
            <label className="text-[10px] text-base-content/40">Gradient</label>
            <input value={value.backgroundGradient || ''} onChange={e => update('backgroundGradient', e.target.value)}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="linear-gradient(135deg, #667eea, #764ba2)" />
          </div>
        </>
      )}

      {/* ── Border ── */}
      <div className="grid grid-cols-3 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40">Border width</label>
          <input value={value.borderWidth || ''} onChange={e => update('borderWidth', e.target.value)}
            className="input input-bordered input-xs w-full text-[11px]" placeholder="0" />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40">Color</label>
          <input value={value.borderColor || ''} onChange={e => update('borderColor', e.target.value)}
            className="input input-bordered input-xs w-full text-[11px]" placeholder="#e5e7eb" />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40">Style</label>
          <select value={value.borderStyle || 'none'} onChange={e => update('borderStyle', e.target.value)}
            className="select select-bordered select-xs w-full text-[11px]">
            <option value="none">None</option>
            <option value="solid">Solid</option>
            <option value="dashed">Dashed</option>
            <option value="dotted">Dotted</option>
          </select>
        </div>
      </div>

      {/* ── Border Radius (per-corner) ── */}
      <CornerRadiusField
        label="Border Radius"
        value={typeof value.borderRadius === 'object' ? value.borderRadius : {}}
        onChange={(v) => update('borderRadius', v)}
        helperText="Per-corner radius. 50% creates a pill/circle."
      />

      {/* ── Shadow (preset + custom) ── */}
      <ShadowField
        label="Shadow"
        mode={(value.shadowMode as string) || 'preset'}
        preset={(value.boxShadow as string) || ''}
        custom={(value.shadowCustom as ShadowCustom) || {}}
        onChangeMode={(v) => update('shadowMode', v)}
        onChangePreset={(v) => update('boxShadow', v)}
        onChangeCustom={(v) => update('shadowCustom', { ...((value.shadowCustom as ShadowCustom) || {}), ...v })}
      />

      {/* ── Opacity ── */}
      <div>
        <label className="text-[10px] text-base-content/40">Block Opacity</label>
        <input type="range" min={0} max={100} value={(value.opacity ?? 1) * 100}
          onChange={e => update('opacity', Number(e.target.value) / 100)}
          className="range range-xs w-full" />
        <span className="text-[10px] text-base-content/30">{Math.round((value.opacity ?? 1) * 100)}%</span>
        <p className="text-[9px] text-warning/60 mt-0.5">Affects entire block including text. For background-only opacity, use the block&apos;s own overlay controls.</p>
      </div>

      {/* ── Overflow ── */}
      <div>
        <label className="text-[10px] text-base-content/40">Overflow</label>
        <select value={value.overflow || 'visible'} onChange={e => update('overflow', e.target.value === 'visible' ? undefined : e.target.value)}
          className="select select-bordered select-xs w-full text-[11px]">
          <option value="visible">Visible</option>
          <option value="hidden">Hidden</option>
          <option value="scroll">Scroll</option>
        </select>
      </div>
    </div>
  );
}
