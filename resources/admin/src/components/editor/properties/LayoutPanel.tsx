import type { LayoutProps } from '@/types/blocks';

interface Props {
  value: LayoutProps;
  onChange: (v: LayoutProps) => void;
}

export function LayoutPanel({ value, onChange }: Props) {
  const update = (key: keyof LayoutProps, v: unknown) => onChange({ ...value, [key]: v || undefined });

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40">Width</label>
          <input value={value.width || ''} onChange={e => update('width', e.target.value)}
            className="input input-bordered input-xs w-full text-[11px]" placeholder="auto" />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40">Max width</label>
          <input value={value.maxWidth || ''} onChange={e => update('maxWidth', e.target.value)}
            className="input input-bordered input-xs w-full text-[11px]" placeholder="100%" />
        </div>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40">Min height</label>
        <input value={value.minHeight || ''} onChange={e => update('minHeight', e.target.value)}
          className="input input-bordered input-xs w-full text-[11px]" placeholder="auto" />
      </div>

      <div>
        <label className="text-[10px] text-base-content/40 mb-1 block">Alignment</label>
        <div className="flex gap-0.5">
          {(['left', 'center', 'right', 'stretch'] as const).map(a => (
            <button key={a} onClick={() => update('alignment', value.alignment === a ? undefined : a)}
              className={`btn btn-xs flex-1 text-[10px] ${value.alignment === a ? 'btn-primary' : 'btn-ghost'}`}>
              {a[0].toUpperCase() + a.slice(1)}
            </button>
          ))}
        </div>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40">Display</label>
        <select value={value.display || 'block'} onChange={e => update('display', e.target.value === 'block' ? undefined : e.target.value)}
          className="select select-bordered select-xs w-full text-[11px]">
          <option value="block">Block</option>
          <option value="flex">Flex</option>
          <option value="grid">Grid</option>
          <option value="none">Hidden</option>
        </select>
      </div>

      {value.display === 'flex' && (
        <div className="grid grid-cols-2 gap-2">
          <div>
            <label className="text-[10px] text-base-content/40">Direction</label>
            <select value={value.flexDirection || 'row'} onChange={e => update('flexDirection', e.target.value)}
              className="select select-bordered select-xs w-full text-[11px]">
              <option value="row">Row</option>
              <option value="column">Column</option>
            </select>
          </div>
          <div>
            <label className="text-[10px] text-base-content/40">Justify</label>
            <select value={value.justifyContent || ''} onChange={e => update('justifyContent', e.target.value)}
              className="select select-bordered select-xs w-full text-[11px]">
              <option value="">Default</option>
              <option value="flex-start">Start</option>
              <option value="center">Center</option>
              <option value="flex-end">End</option>
              <option value="space-between">Space between</option>
            </select>
          </div>
        </div>
      )}

      <div>
        <label className="text-[10px] text-base-content/40">Z-index</label>
        <input type="number" value={value.zIndex ?? ''} onChange={e => update('zIndex', e.target.value ? Number(e.target.value) : undefined)}
          className="input input-bordered input-xs w-full text-[11px]" placeholder="auto" />
      </div>
    </div>
  );
}
