
import { useState } from 'react';
import type { MagTextWrap, MagElement } from '@/types/magazine';
import { traceImageContour } from '@/lib/contourTrace';

interface TextWrapPanelProps {
  value: MagTextWrap;
  onChange: (v: Partial<MagTextWrap>) => void;
  /** the wrapped element — needed to trace its image alpha for object-shape */
  element?: MagElement | null;
}

export default function TextWrapPanel({ value, onChange, element }: TextWrapPanelProps) {
  const [tracing, setTracing] = useState(false);
  const [traceError, setTraceError] = useState<string | null>(null);
  const imgSrc = (element?.data as any)?.src as string | undefined;
  const canTrace = !!imgSrc && !!element;
  const bands = (value.customPath as any)?.bands as any[] | undefined;

  const trace = async () => {
    if (!canTrace || !element) return;
    setTracing(true);
    setTraceError(null);
    try {
      const fit = ((element.data as any)?.fit === 'contain' ? 'contain' : (element.data as any)?.fit === 'fill' ? 'fill' : 'cover') as any;
      const traced = await traceImageContour(imgSrc!, element.width, element.height, fit);
      if (!traced.length) throw new Error('no opaque pixels found');
      onChange({ type: 'object-shape', customPath: { bands: traced } as any });
    } catch (e) {
      setTraceError('Could not trace the image (cross-origin or no alpha) — using the bounding box.');
      onChange({ type: 'object-shape', customPath: null });
    } finally {
      setTracing(false);
    }
  };

  const setType = (t: MagTextWrap['type']) => {
    if (t === 'object-shape' && canTrace && !bands) {
      onChange({ type: t });
      void trace();
    } else {
      onChange({ type: t });
    }
  };
  return (
    <div className="space-y-3">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Text Wrap</h3>

      {/* Type */}
      <div>
        <label htmlFor="textwrappanel-type-1" className="text-[10px] text-base-content/40 mb-0.5 block">Type</label>
        <select id="textwrappanel-type-1"
          value={value.type}
          onChange={(e) => setType(e.target.value as MagTextWrap['type'])}
          className="select select-bordered select-xs w-full"
        >
          <option value="none">None</option>
          <option value="bounding-box">Bounding box</option>
          <option value="object-shape">Object shape</option>
          <option value="jump">Jump</option>
        </select>
      </div>

      {value.type === 'object-shape' && (
        <div className="text-[9px] space-y-1">
          {tracing && <p className="text-info">Tracing image contour…</p>}
          {!tracing && bands && <p className="text-base-content/40">Contour traced — {bands.length} bands. Text wraps the visible shape.</p>}
          {!tracing && !bands && !traceError && canTrace && (
            <button className="btn btn-ghost btn-xs" onClick={() => void trace()}>Trace contour from image</button>
          )}
          {!tracing && bands && canTrace && (
            <button className="btn btn-ghost btn-xs" onClick={() => void trace()}>Re-trace</button>
          )}
          {traceError && <p className="text-warning">{traceError}</p>}
          {!canTrace && <p className="text-base-content/30">Object shape uses the alpha contour of image frames; this element wraps as a bounding box.</p>}
        </div>
      )}

      {/* Offset */}
      <div className="grid grid-cols-4 gap-1">
        {(['top', 'right', 'bottom', 'left'] as const).map((side) => (
          <div key={side}>
            <label className="text-[10px] text-base-content/40 mb-0.5 block">{side.charAt(0).toUpperCase() + side.slice(1)}</label>
            <input name="mag-textwrappanel-1"
              type="number"
              value={value.offset[side]}
              onChange={(e) => onChange({ offset: { ...value.offset, [side]: Number(e.target.value) } })}
              className="input input-bordered input-xs w-full"
            />
          </div>
        ))}
      </div>

      {/* Side */}
      <div>
        <label htmlFor="textwrappanel-side-2" className="text-[10px] text-base-content/40 mb-0.5 block">Side</label>
        <select id="textwrappanel-side-2"
          value={value.side}
          onChange={(e) => onChange({ side: e.target.value as MagTextWrap['side'] })}
          className="select select-bordered select-xs w-full"
        >
          <option value="both">Both</option>
          <option value="left">Left</option>
          <option value="right">Right</option>
          <option value="largest">Largest</option>
        </select>
      </div>

      {/* Invert */}
      <label className="flex items-center gap-1.5 cursor-pointer">
        <input name="mag-textwrappanel-2"
          type="checkbox"
          checked={value.invert}
          onChange={(e) => onChange({ invert: e.target.checked })}
          className="checkbox checkbox-xs"
        />
        <span className="text-[10px] text-base-content/40">Invert</span>
      </label>
    </div>
  );
}
