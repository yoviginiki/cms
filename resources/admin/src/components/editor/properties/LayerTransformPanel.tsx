/**
 * Transform panel for absolutely-positioned LAYER blocks (blocks inside a
 * slide / free canvas). Binds to block.data.layout — validated by
 * SliderAnimation::validationRules() and emitted by SliderRender::wrapLayer,
 * so every control here is end-to-end by construction.
 */
interface LayerLayout {
  x?: string;
  y?: string;
  widthPct?: number;
  heightPct?: number;
  rotation?: number;
  zIndex?: number;
}

interface LayerTransformPanelProps {
  value: LayerLayout;
  onChange: (layout: LayerLayout) => void;
}

/** 9-point anchor presets: canonical starting positions on the slide canvas */
const ANCHORS: { x: string; y: string; label: string }[] = [
  { x: '8%', y: '10%', label: 'top left' }, { x: '40%', y: '10%', label: 'top center' }, { x: '72%', y: '10%', label: 'top right' },
  { x: '8%', y: '40%', label: 'middle left' }, { x: '40%', y: '40%', label: 'middle center' }, { x: '72%', y: '40%', label: 'middle right' },
  { x: '8%', y: '70%', label: 'bottom left' }, { x: '40%', y: '70%', label: 'bottom center' }, { x: '72%', y: '70%', label: 'bottom right' },
];

export function LayerTransformPanel({ value, onChange }: LayerTransformPanelProps) {
  const update = (patch: Partial<LayerLayout>) => onChange({ ...value, ...patch });
  const num = (v: string, min: number, max: number): number | undefined => {
    if (v === '') return undefined;
    const n = Number(v);
    return Number.isFinite(n) ? Math.max(min, Math.min(max, n)) : undefined;
  };

  return (
    <div className="space-y-2">
      <div className="flex items-start gap-3">
        <div>
          <label className="text-[10px] text-base-content/40 block mb-0.5">Anchor</label>
          <div className="grid grid-cols-3 gap-0.5 w-fit">
            {ANCHORS.map(a => (
              <button key={a.label} type="button" title={a.label}
                onClick={() => update({ x: a.x, y: a.y })}
                className={`w-4 h-4 border ${value.x === a.x && value.y === a.y
                  ? 'bg-primary border-primary'
                  : 'border-base-300 hover:border-base-content/40'}`} />
            ))}
          </div>
        </div>
        <div className="grid grid-cols-2 gap-1.5 flex-1">
          <div>
            <label className="text-[10px] text-base-content/40">X</label>
            <input value={value.x ?? ''} onChange={e => update({ x: e.target.value || undefined })}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="8% / 120px" />
          </div>
          <div>
            <label className="text-[10px] text-base-content/40">Y</label>
            <input value={value.y ?? ''} onChange={e => update({ y: e.target.value || undefined })}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="32% / 40px" />
          </div>
          <div>
            <label className="text-[10px] text-base-content/40">Width %</label>
            <input type="number" min={0} max={100} value={value.widthPct ?? ''}
              onChange={e => update({ widthPct: num(e.target.value, 0, 100) })}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="auto" />
          </div>
          <div>
            <label className="text-[10px] text-base-content/40">Height %</label>
            <input type="number" min={0} max={100} value={value.heightPct ?? ''}
              onChange={e => update({ heightPct: num(e.target.value, 0, 100) })}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="auto" />
          </div>
        </div>
      </div>
      <div className="grid grid-cols-2 gap-1.5">
        <div>
          <label className="text-[10px] text-base-content/40">Rotation °</label>
          <input type="number" min={-360} max={360} value={value.rotation ?? ''}
            onChange={e => update({ rotation: num(e.target.value, -360, 360) })}
            className="input input-bordered input-xs w-full text-[11px]" placeholder="0" />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40">Z-index</label>
          <input type="number" min={0} max={99} value={value.zIndex ?? ''}
            onChange={e => update({ zIndex: num(e.target.value, 0, 99) })}
            className="input input-bordered input-xs w-full text-[11px]" placeholder="2" />
        </div>
      </div>
    </div>
  );
}
