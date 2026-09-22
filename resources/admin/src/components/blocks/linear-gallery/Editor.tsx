import React from 'react';
import type { BlockEditorProps } from '@/types/blocks';
import { ToggleField } from '@/components/editor/fields';
import { GalleryImagesField } from '@/components/editor/fields/GalleryImagesField';
import { CardEffectsPanel } from '@/components/editor/fields/CardEffectsPanel';
import type { CardEffects } from '@/lib/blockEffects';
import { resolveOptions } from './options';

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="space-y-2.5 border-t border-base-300/20 pt-3">
      <p className="text-[10px] uppercase tracking-wider text-base-content/40">{title}</p>
      {children}
    </div>
  );
}

function Range({ label, value, min, max, step = 1, unit = '', onChange }: { label: string; value: number; min: number; max: number; step?: number; unit?: string; onChange: (v: number) => void }) {
  return (
    <div>
      <div className="flex items-center justify-between mb-0.5">
        <label className="text-[11px] text-base-content/50">{label}</label>
        <span className="text-[10px] text-base-content/40 tabular-nums">{value}{unit}</span>
      </div>
      <input type="range" min={min} max={max} step={step} value={value} onChange={e => onChange(Number(e.target.value))} className="range range-xs w-full" />
    </div>
  );
}

function NumberField({ label, value, min, max, step = 1, onChange, hint }: { label: string; value: number; min: number; max: number; step?: number; onChange: (v: number) => void; hint?: string }) {
  return (
    <div>
      <label className="text-[11px] text-base-content/50 mb-1 block">{label}</label>
      <input type="number" min={min} max={max} step={step} value={value} onChange={e => onChange(Number(e.target.value))} className="input input-bordered input-sm w-full text-[12px]" />
      {hint && <p className="text-[10px] text-base-content/35 mt-0.5">{hint}</p>}
    </div>
  );
}

function Select<T extends string>({ label, value, options, onChange }: { label: string; value: T; options: { value: T; label: string }[]; onChange: (v: T) => void }) {
  return (
    <div>
      <label className="text-[11px] text-base-content/50 mb-1 block">{label}</label>
      <select value={value} onChange={e => onChange(e.target.value as T)} className="select select-bordered select-sm w-full text-[12px]">
        {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
      </select>
    </div>
  );
}

function ColorField({ label, value, placeholder, onChange }: { label: string; value: string; placeholder: string; onChange: (v: string) => void }) {
  const isHex = /^#[0-9a-fA-F]{6}$/.test(value);
  return (
    <div>
      <label className="text-[11px] text-base-content/50 mb-1 block">{label}</label>
      <div className="flex items-center gap-1.5">
        <input type="color" value={isHex ? value : '#888888'} onChange={e => onChange(e.target.value)} className="w-8 h-8 p-0 border border-base-300/40 rounded cursor-pointer bg-transparent" title="Pick" />
        <input type="text" value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder}
          className="input input-bordered input-sm flex-1 text-[11px] font-mono" />
        {value && <button type="button" onClick={() => onChange('')} className="btn btn-ghost btn-xs text-[10px]">Reset</button>}
      </div>
    </div>
  );
}

const SHADOW_OPTS = [
  { value: 'none' as const, label: 'None' }, { value: 'soft' as const, label: 'Soft' },
  { value: 'medium' as const, label: 'Medium' }, { value: 'strong' as const, label: 'Strong' },
];

export const LinearGalleryEditor: React.FC<BlockEditorProps> = ({ block, onUpdate }) => {
  const o = resolveOptions(block.data as Record<string, unknown>);
  const update = (field: string, value: unknown) => onUpdate({ ...block.data, [field]: value });

  return (
    <div className="space-y-4">
      <GalleryImagesField images={o.images} onChange={(imgs) => update('images', imgs)} linkHint="optional — opens the link instead of the lightbox" />

      <Section title="Size & layout">
        <Select label="Width" value={o.width} onChange={v => update('width', v)} options={[
          { value: 'contained', label: 'Contained — inside the page column' },
          { value: 'full', label: 'Full width — edge to edge' },
        ]} />
        <div className="grid grid-cols-2 gap-2">
          <NumberField label="Image height (desktop)" value={o.height} min={80} max={1600} step={10} onChange={v => update('height', v)} />
          <NumberField label="Image height (mobile)" value={o.mobileHeight} min={60} max={1000} step={10} onChange={v => update('mobileHeight', v)} />
        </div>
        <NumberField label="Strip height (0 = automatic)" value={o.stripHeight} min={0} max={2000} step={10} onChange={v => update('stripHeight', v)}
          hint="Fixed height of the whole band; images stay vertically centred inside it." />
        <div className="grid grid-cols-2 gap-2">
          <NumberField label="Space before first image" value={o.offsetStart} min={0} max={600} step={4} onChange={v => update('offsetStart', v)} />
          <NumberField label="Space after last image" value={o.offsetEnd} min={0} max={600} step={4} onChange={v => update('offsetEnd', v)} />
        </div>
        <div className="grid grid-cols-2 gap-2">
          <Select label="Start position" value={o.align} onChange={v => update('align', v)} options={[
            { value: 'start', label: 'From the left' },
            { value: 'center', label: 'Centred (scrolled to middle)' },
          ]} />
          <NumberField label="Vertical padding" value={o.padding} min={0} max={300} step={4} onChange={v => update('padding', v)} />
        </div>
      </Section>

      <Section title="Composition">
        <Select label="Size variation" value={o.sizeVariation} onChange={v => update('sizeVariation', v)} options={[
          { value: 'none', label: 'None — all images same height' },
          { value: 'subtle', label: 'Subtle' },
          { value: 'strong', label: 'Strong (like the reference)' },
        ]} />
        <Range label="Overlap" value={o.overlap} min={0} max={60} unit="%" onChange={v => update('overlap', v)} />
        <Range label="Vertical scatter" value={o.scatter} min={0} max={60} unit="%" onChange={v => update('scatter', v)} />
        <Select label="Overlap blending" value={o.blend as 'multiply'} onChange={v => update('blend', v)} options={[
          { value: 'multiply', label: 'Multiply — see-through, darkens (reference look)' },
          { value: 'darken', label: 'Darken' },
          { value: 'screen', label: 'Screen — lightens' },
          { value: 'luminosity', label: 'Luminosity' },
          { value: 'normal', label: 'Normal — opacity only' },
        ]} />
        <Range label="Image opacity" value={o.opacity} min={30} max={100} unit="%" onChange={v => update('opacity', v)} />
        <ColorField label="Band background" value={o.background} placeholder="transparent (page background)" onChange={v => update('background', v)} />
      </Section>

      <Section title="Image frame (at rest)">
        <div className="grid grid-cols-2 gap-2">
          <NumberField label="Corner radius" value={o.radius} min={0} max={60} onChange={v => update('radius', v)} />
          <NumberField label="Border width" value={o.borderWidth} min={0} max={12} onChange={v => update('borderWidth', v)} />
        </div>
        <ColorField label="Border color" value={o.borderColor} placeholder="none" onChange={v => update('borderColor', v)} />
        <Select label="Shadow" value={o.shadow} onChange={v => update('shadow', v)} options={SHADOW_OPTS} />
      </Section>

      <Section title="Hover">
        <ColorField label="Border color" value={o.hoverBorderColor} placeholder="#ffffff" onChange={v => update('hoverBorderColor', v)} />
        <Range label="Border width" value={o.hoverBorderWidth} min={0} max={12} unit="px" onChange={v => update('hoverBorderWidth', v)} />
        <ToggleField label="Glow around the border" value={o.hoverGlow} onChange={v => update('hoverGlow', v)} />
        <Select label="Shadow" value={o.hoverShadow} onChange={v => update('hoverShadow', v)} options={SHADOW_OPTS} />
        <ToggleField label="Lift image on hover" value={o.hoverLift} onChange={v => update('hoverLift', v)} />
      </Section>

      <Section title="Arrows">
        <ToggleField label="Show arrow buttons" value={o.arrows} onChange={v => update('arrows', v)} />
        {o.arrows && (
          <>
            <div className="grid grid-cols-2 gap-2">
              <Select label="Visibility" value={o.arrowsShow} onChange={v => update('arrowsShow', v)} options={[
                { value: 'hover', label: 'On hover' }, { value: 'always', label: 'Always' },
              ]} />
              <Select label="Size" value={o.arrowsSize} onChange={v => update('arrowsSize', v)} options={[
                { value: 'sm', label: 'Small' }, { value: 'md', label: 'Medium' }, { value: 'lg', label: 'Large' },
              ]} />
            </div>
            <Select label="Position" value={o.arrowsPosition} onChange={v => update('arrowsPosition', v)} options={[
              { value: 'sides', label: 'Left & right sides' },
              { value: 'bottom-right', label: 'Bottom right' },
              { value: 'bottom-center', label: 'Bottom centre' },
              { value: 'top-right', label: 'Top right' },
            ]} />
            <ColorField label="Icon color" value={o.arrowsColor} placeholder="theme text color" onChange={v => update('arrowsColor', v)} />
            <ColorField label="Button background" value={o.arrowsBg} placeholder="translucent page background" onChange={v => update('arrowsBg', v)} />
            <ToggleField label="Also show on phones (swipe still works)" value={o.arrowsMobile} onChange={v => update('arrowsMobile', v)} />
          </>
        )}
      </Section>

      <Section title="Behaviour">
        <ToggleField label="Drag with the mouse" value={o.drag} onChange={v => update('drag', v)} />
        <ToggleField label="Show a thin scrollbar" value={o.scrollbar} onChange={v => update('scrollbar', v)} />
        <ToggleField label="Open in lightbox" value={o.lightbox} onChange={v => update('lightbox', v)} />
        {o.lightbox && (
          <Select label="Open on" value={o.openOn} onChange={v => update('openOn', v)} options={[
            { value: 'click', label: 'Single click' },
            { value: 'dblclick', label: 'Double click (single click only grabs the strip)' },
          ]} />
        )}
        <Range label="Auto-drift speed (0 = off)" value={o.autoplay} min={0} max={200} step={5} unit=" px/s" onChange={v => update('autoplay', v)} />
        <p className="text-[10px] text-base-content/40">Swipe on touch, arrow keys when the strip is focused. On phones: mobile height, snap to images, arrows hidden unless enabled above.</p>
      </Section>

      <div className="border-t border-base-300/20 pt-3">
        <CardEffectsPanel value={(block.data as any).effects || {}} onChange={(v: CardEffects) => update('effects', v)} />
      </div>
    </div>
  );
};
