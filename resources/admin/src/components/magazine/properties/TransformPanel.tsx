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

export default function TransformPanel({ x, y, width, height, rotation, onChange }: TransformPanelProps) {
  const [lockProportions, setLockProportions] = useState(false);
  const [referencePoint, setReferencePoint] = useState<string>('top-left');
  const aspectRatio = width / (height || 1);

  const handleWidth = (newWidth: number) => {
    if (lockProportions) {
      onChange({ width: newWidth, height: Math.round(newWidth / aspectRatio) });
    } else {
      onChange({ width: newWidth });
    }
  };

  const handleHeight = (newHeight: number) => {
    if (lockProportions) {
      onChange({ height: newHeight, width: Math.round(newHeight * aspectRatio) });
    } else {
      onChange({ height: newHeight });
    }
  };

  return (
    <div className="space-y-3">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Transform</h3>

      <div className="grid grid-cols-2 gap-2">
        <MathInput label="X" value={x} onCommit={(v) => onChange({ x: v })} />
        <MathInput label="Y" value={y} onCommit={(v) => onChange({ y: v })} />
      </div>

      <div className="grid grid-cols-2 gap-2">
        <MathInput label="Width" value={width} onCommit={(v) => handleWidth(Math.max(1, v))} />
        <MathInput label="Height" value={height} onCommit={(v) => handleHeight(Math.max(1, v))} />
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
