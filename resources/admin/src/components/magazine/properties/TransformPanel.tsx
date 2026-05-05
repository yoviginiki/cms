import { useState } from 'react';

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
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">X</label>
          <input
            type="number"
            step={1}
            value={x}
            onChange={(e) => onChange({ x: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Y</label>
          <input
            type="number"
            step={1}
            value={y}
            onChange={(e) => onChange({ y: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
      </div>

      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Width</label>
          <input
            type="number"
            value={width}
            onChange={(e) => handleWidth(Number(e.target.value))}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Height</label>
          <input
            type="number"
            value={height}
            onChange={(e) => handleHeight(Number(e.target.value))}
            className="input input-bordered input-xs w-full"
          />
        </div>
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

      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Rotation</label>
        <input
          type="number"
          min={0}
          max={360}
          value={rotation}
          onChange={(e) => onChange({ rotation: Number(e.target.value) })}
          className="input input-bordered input-xs w-full"
        />
      </div>

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
