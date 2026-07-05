import { useEffect, useState } from 'react';
import { evalNumericEntry } from '@/lib/magazineFormat';

interface TransformPanelProps {
  x: number;
  y: number;
  width: number;
  height: number;
  rotation: number;
  onChange: (v: Partial<{ x: number; y: number; width: number; height: number; rotation: number }>) => void;
}

const REFERENCE_POINTS = [
  'top-left', 'top-center', 'top-right',
  'center-left', 'center', 'center-right',
  'bottom-left', 'bottom-center', 'bottom-right',
] as const;

/**
 * numeric field with math entry (W2-7): accepts "+10", "*2", "/4" relative to
 * the current value; plain numbers replace it. Commits on blur/Enter.
 */
function MathInput({ label, value, onCommit }: { label: string; value: number; onCommit: (v: number) => void }) {
  const [text, setText] = useState(String(Math.round(value * 100) / 100));
  useEffect(() => {
    setText(String(Math.round(value * 100) / 100));
  }, [value]);
  const commit = () => {
    const next = evalNumericEntry(text, value);
    if (next !== null && next !== value) onCommit(Math.round(next * 100) / 100);
    else setText(String(Math.round(value * 100) / 100));
  };
  return (
    <div>
      <label className="text-[10px] text-base-content/40 mb-0.5 block">{label}</label>
      <input
        type="text"
        inputMode="decimal"
        id={`mag-transform-${label.toLowerCase()}`}
        name={`mag-transform-${label.toLowerCase()}`}
        value={text}
        onChange={(e) => setText(e.target.value)}
        onBlur={commit}
        onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); commit(); (e.target as HTMLInputElement).blur(); } }}
        title='Math entry: "+10", "*2", "/4" apply to the current value'
        className="input input-bordered input-xs w-full"
      />
    </div>
  );
}

const REF_FRACTIONS: Record<string, { fx: number; fy: number }> = {
  'top-left': { fx: 0, fy: 0 }, 'top-center': { fx: 0.5, fy: 0 }, 'top-right': { fx: 1, fy: 0 },
  'center-left': { fx: 0, fy: 0.5 }, 'center': { fx: 0.5, fy: 0.5 }, 'center-right': { fx: 1, fy: 0.5 },
  'bottom-left': { fx: 0, fy: 1 }, 'bottom-center': { fx: 0.5, fy: 1 }, 'bottom-right': { fx: 1, fy: 1 },
};

export default function TransformPanel({ x, y, width, height, rotation, onChange }: TransformPanelProps) {
  const [lockProportions, setLockProportions] = useState(false);
  const [referencePoint, setReferencePoint] = useState<string>('top-left');
  const aspectRatio = width / (height || 1);
  // W2-7: the 9-point proxy is REAL now — X/Y are shown and edited relative
  // to the chosen reference point of the frame
  const { fx, fy } = REF_FRACTIONS[referencePoint] || REF_FRACTIONS['top-left'];
  const refX = x + width * fx;
  const refY = y + height * fy;

  return (
    <div className="space-y-3">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Transform</h3>

      <div className="grid grid-cols-2 gap-2">
        <MathInput label="X" value={refX} onCommit={(v) => onChange({ x: v - width * fx })} />
        <MathInput label="Y" value={refY} onCommit={(v) => onChange({ y: v - height * fy })} />
      </div>

      <div className="grid grid-cols-2 gap-2">
        <MathInput label="Width" value={width} onCommit={(v) => {
          const w2 = Math.max(1, v);
          const h2 = lockProportions ? Math.round(w2 / aspectRatio) : height;
          onChange({ width: w2, ...(lockProportions ? { height: h2 } : {}), x: refX - w2 * fx, ...(fy && lockProportions ? { y: refY - h2 * fy } : {}) });
        }} />
        <MathInput label="Height" value={height} onCommit={(v) => {
          const h2 = Math.max(1, v);
          const w2 = lockProportions ? Math.round(h2 * aspectRatio) : width;
          onChange({ height: h2, ...(lockProportions ? { width: w2 } : {}), y: refY - h2 * fy, ...(fx && lockProportions ? { x: refX - w2 * fx } : {}) });
        }} />
      </div>

      <label className="flex items-center gap-1.5 cursor-pointer">
        <input
          type="checkbox"
          checked={lockProportions}
          onChange={(e) => setLockProportions(e.target.checked)}
          className="checkbox checkbox-xs"
        />
        <span className="text-[10px] text-base-content/40">Lock proportions</span>
      </label>

      <MathInput label="Rotation" value={rotation} onCommit={(v) => onChange({ rotation: ((v % 360) + 360) % 360 })} />

      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Reference point</label>
        <div className="grid grid-cols-3 gap-0.5 w-fit">
          {REFERENCE_POINTS.map((point) => (
            <label key={point} className="cursor-pointer">
              <input
                type="radio"
                name="referencePoint"
                value={point}
                checked={referencePoint === point}
                onChange={() => setReferencePoint(point)}
                className="radio radio-xs"
              />
            </label>
          ))}
        </div>
      </div>
    </div>
  );
}
