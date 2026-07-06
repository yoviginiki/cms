import { AssetField } from '@/components/ui/AssetPicker';

/**
 * Properties for the data/collection elements that previously had no panel
 * at all (their content could not be entered and died on save): gallery,
 * chart, stat number and progress bar.
 */

export function GalleryPanel({ data, onChange }: {
  data: Record<string, any>;
  onChange: (patch: Record<string, unknown>) => void;
}) {
  const images: Array<{ assetId?: string | null; src?: string; alt?: string; caption?: string }> =
    Array.isArray(data.images) ? data.images : [];

  const setImage = (i: number, patch: Record<string, unknown>) => {
    const next = images.map((im, k) => (k === i ? { ...im, ...patch } : im));
    onChange({ images: next });
  };

  return (
    <div className="px-3 py-2 border-t border-base-300/20 space-y-2">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Gallery</h3>
      <div className="flex items-center gap-2">
        <label htmlFor="gal-cols" className="text-[9px] text-base-content/40">Columns</label>
        <input id="gal-cols" type="number" min={1} max={6} value={data.columns || 2}
          onChange={(e) => onChange({ columns: Math.min(6, Math.max(1, Number(e.target.value) || 2)) })}
          className="input input-bordered input-xs w-14" />
        <label className="flex items-center gap-1 text-[9px] text-base-content/40 ml-2">
          <input type="checkbox" checked={data.showCaptions !== false}
            onChange={(e) => onChange({ showCaptions: e.target.checked })} className="checkbox checkbox-xs" />
          Captions
        </label>
      </div>

      {images.map((im, i) => (
        <div key={i} className="border border-base-300/30 rounded p-1.5 space-y-1">
          <div className="flex items-center gap-1.5">
            {im.src ? <img src={im.src} alt="" className="w-8 h-8 object-cover rounded" /> : null}
            <input type="text" value={im.caption || ''} placeholder="Caption"
              onChange={(e) => setImage(i, { caption: e.target.value })}
              className="input input-bordered input-xs flex-1 text-[10px]" />
            <button type="button" onClick={() => onChange({ images: images.filter((_, k) => k !== i) })}
              className="text-[10px] text-error/70 hover:text-error px-1">✕</button>
          </div>
          <input type="text" value={im.alt || ''} placeholder="Alt text"
            onChange={(e) => setImage(i, { alt: e.target.value })}
            className="input input-bordered input-xs w-full text-[10px]" />
        </div>
      ))}

      <AssetField label="Add image" value=""
        onChange={(url, assetId) => url && onChange({ images: [...images, { src: url, assetId: assetId || null, alt: '', caption: '' }] })} />
    </div>
  );
}

export function ChartPanel({ data, onChange }: {
  data: Record<string, any>;
  onChange: (patch: Record<string, unknown>) => void;
}) {
  const rows: Array<{ label: string; value: number; color: string | null }> =
    Array.isArray(data.data) ? data.data : [];
  const setRow = (i: number, patch: Record<string, unknown>) =>
    onChange({ data: rows.map((r, k) => (k === i ? { ...r, ...patch } : r)) });

  return (
    <div className="px-3 py-2 border-t border-base-300/20 space-y-2">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Chart</h3>
      <div className="flex items-center gap-2">
        <select value={data.chartType || 'bar'} onChange={(e) => onChange({ chartType: e.target.value })}
          className="select select-bordered select-xs text-[10px]">
          <option value="bar">Bars</option>
          <option value="line">Line</option>
          <option value="pie">Pie</option>
          <option value="donut">Donut</option>
        </select>
        <label className="flex items-center gap-1 text-[9px] text-base-content/40">
          <input type="checkbox" checked={data.showValues !== false}
            onChange={(e) => onChange({ showValues: e.target.checked })} className="checkbox checkbox-xs" />
          Values
        </label>
      </div>
      {rows.map((r, i) => (
        <div key={i} className="flex items-center gap-1">
          <input type="text" value={r.label} placeholder="Label"
            onChange={(e) => setRow(i, { label: e.target.value })}
            className="input input-bordered input-xs flex-1 text-[10px]" />
          <input type="number" value={r.value} onChange={(e) => setRow(i, { value: Number(e.target.value) || 0 })}
            className="input input-bordered input-xs w-16 text-[10px]" />
          <input type="color" value={r.color || '#3b82f6'} onChange={(e) => setRow(i, { color: e.target.value })}
            className="w-6 h-6 p-0 border border-base-300 rounded cursor-pointer" title="Color" />
          <button type="button" onClick={() => onChange({ data: rows.filter((_, k) => k !== i) })}
            className="text-[10px] text-error/70 hover:text-error px-1">✕</button>
        </div>
      ))}
      <button type="button"
        onClick={() => onChange({ data: [...rows, { label: `Item ${rows.length + 1}`, value: 10, color: null }] })}
        className="btn btn-ghost btn-xs text-[10px]">+ Add row</button>
    </div>
  );
}

export function StatPanel({ data, onChange }: {
  data: Record<string, any>;
  onChange: (patch: Record<string, unknown>) => void;
}) {
  return (
    <div className="px-3 py-2 border-t border-base-300/20 space-y-1.5">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Stat number</h3>
      <div className="flex items-center gap-1">
        <input type="text" value={data.prefix || ''} placeholder="Prefix"
          onChange={(e) => onChange({ prefix: e.target.value })} className="input input-bordered input-xs w-14 text-[10px]" />
        <input type="text" value={data.value ?? '0'} placeholder="Value"
          onChange={(e) => onChange({ value: e.target.value })} className="input input-bordered input-xs flex-1 text-[10px]" />
        <input type="text" value={data.suffix || ''} placeholder="Suffix"
          onChange={(e) => onChange({ suffix: e.target.value })} className="input input-bordered input-xs w-14 text-[10px]" />
      </div>
      <input type="text" value={data.label || ''} placeholder="Label under the number"
        onChange={(e) => onChange({ label: e.target.value })} className="input input-bordered input-xs w-full text-[10px]" />
      <div className="flex items-center gap-2">
        <label className="text-[9px] text-base-content/40">Color</label>
        <input type="color" value={data.color || '#111827'} onChange={(e) => onChange({ color: e.target.value })}
          className="w-6 h-6 p-0 border border-base-300 rounded cursor-pointer" />
      </div>
      <p className="text-[9px] text-base-content/30">Number size and font come from the Typography panel.</p>
    </div>
  );
}

export function ProgressPanel({ data, onChange }: {
  data: Record<string, any>;
  onChange: (patch: Record<string, unknown>) => void;
}) {
  return (
    <div className="px-3 py-2 border-t border-base-300/20 space-y-1.5">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Progress</h3>
      <div className="flex items-center gap-1">
        <input type="number" value={data.value ?? 0} onChange={(e) => onChange({ value: Number(e.target.value) || 0 })}
          className="input input-bordered input-xs w-16 text-[10px]" />
        <span className="text-[9px] text-base-content/40">of</span>
        <input type="number" value={data.max ?? 100} onChange={(e) => onChange({ max: Math.max(1, Number(e.target.value) || 100) })}
          className="input input-bordered input-xs w-16 text-[10px]" />
        <input type="color" value={data.color || '#3b82f6'} onChange={(e) => onChange({ color: e.target.value })}
          className="w-6 h-6 p-0 border border-base-300 rounded cursor-pointer ml-1" title="Bar color" />
      </div>
      <input type="text" value={data.label || ''} placeholder="Label"
        onChange={(e) => onChange({ label: e.target.value })} className="input input-bordered input-xs w-full text-[10px]" />
      <label className="flex items-center gap-1 text-[9px] text-base-content/40">
        <input type="checkbox" checked={data.showLabel !== false}
          onChange={(e) => onChange({ showLabel: e.target.checked })} className="checkbox checkbox-xs" />
        Show label + percentage
      </label>
    </div>
  );
}
