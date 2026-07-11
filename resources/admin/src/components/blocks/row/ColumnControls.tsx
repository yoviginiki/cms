import React, { useRef } from 'react';
import { ChevronUp, ChevronDown, RotateCcw } from 'lucide-react';
import { GRID_UNITS, resizeSpans, orderFor } from '@/lib/columnLayout';

/* ─────────────────────────── Column width bar ─────────────────────────── */

interface ColumnWidthBarProps {
  spans: number[];
  onChange: (spans: number[]) => void;
}

/**
 * Drag the dividers to resize columns on a 12-unit grid. Each divider snaps to
 * whole units; neighbours never drop below 1, and the row always fills 12.
 */
export const ColumnWidthBar: React.FC<ColumnWidthBarProps> = ({ spans, onChange }) => {
  const trackRef = useRef<HTMLDivElement>(null);
  const drag = useRef<{ i: number; startX: number; startSpans: number[] } | null>(null);

  const onPointerMove = (e: PointerEvent) => {
    const d = drag.current;
    const track = trackRef.current;
    if (!d || !track) return;
    const unitPx = track.getBoundingClientRect().width / GRID_UNITS;
    if (unitPx <= 0) return;
    const units = Math.round((e.clientX - d.startX) / unitPx);
    onChange(resizeSpans(d.startSpans, d.i, units));
  };

  const endDrag = () => {
    drag.current = null;
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', endDrag);
  };

  const startDrag = (e: React.PointerEvent, i: number) => {
    e.preventDefault();
    drag.current = { i, startX: e.clientX, startSpans: [...spans] };
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', endDrag);
  };

  return (
    <div>
      <label className="text-[11px] text-base-content/50 mb-1.5 block">Column Widths (12-grid)</label>
      <div ref={trackRef} className="flex items-stretch h-9 select-none">
        {spans.map((span, i) => (
          <React.Fragment key={i}>
            <div
              className="flex items-center justify-center bg-base-300/20 border border-base-300/40 text-[11px] font-mono text-base-content/60 min-w-0"
              style={{ flexGrow: span, flexBasis: 0 }}
              title={`Column ${i + 1}: ${span}/12`}
            >
              {span}
            </div>
            {i < spans.length - 1 && (
              <div
                onPointerDown={(e) => startDrag(e, i)}
                className="w-2 shrink-0 cursor-col-resize flex items-center justify-center group"
                title="Drag to resize"
              >
                <div className="w-px h-full bg-base-content/20 group-hover:bg-primary group-hover:w-0.5 transition-colors" />
              </div>
            )}
          </React.Fragment>
        ))}
      </div>
      <p className="text-[10px] text-base-content/40 mt-1">Drag the dividers · each column is N of 12 units.</p>
    </div>
  );
};

/* ─────────────────────────── Mobile stack order ────────────────────────── */

interface StackOrderControlProps {
  count: number;
  stackOrder: number[] | undefined;
  onChange: (order: number[] | undefined) => void;
}

/**
 * Reorder how columns stack on mobile (they collapse to a single column below
 * 768px). The list is the display order; each entry names its original column.
 */
export const StackOrderControl: React.FC<StackOrderControlProps> = ({ count, stackOrder, onChange }) => {
  if (count < 2) return null;

  // Current display order: the permutation, or natural identity.
  const identity = Array.from({ length: count }, (_, i) => i);
  const order =
    Array.isArray(stackOrder) && stackOrder.length === count && stackOrder.every((v) => identity.includes(v))
      ? stackOrder
      : identity;

  const move = (from: number, to: number) => {
    if (to < 0 || to >= count) return;
    const next = [...order];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);
    onChange(next.every((v, idx) => v === idx) ? undefined : next);
  };

  const isCustom = order.some((v, idx) => v !== idx);

  return (
    <div>
      <div className="flex items-center justify-between mb-1.5">
        <label className="text-[11px] text-base-content/50">Mobile Stack Order</label>
        {isCustom && (
          <button
            onClick={() => onChange(undefined)}
            className="flex items-center gap-1 text-[10px] text-base-content/40 hover:text-primary"
            title="Reset to natural order"
          >
            <RotateCcw size={10} /> Reset
          </button>
        )}
      </div>
      <div className="space-y-1">
        {order.map((origIndex, pos) => (
          <div key={origIndex} className="flex items-center gap-2 border border-base-300/30 px-2 py-1 text-[11px]">
            <span className="w-4 text-base-content/30 font-mono">{pos + 1}</span>
            <span className="flex-1 text-base-content/60">Column {origIndex + 1}</span>
            <button
              onClick={() => move(pos, pos - 1)}
              disabled={pos === 0}
              className="btn btn-ghost btn-xs btn-square disabled:opacity-20"
              title="Move up"
            >
              <ChevronUp size={12} />
            </button>
            <button
              onClick={() => move(pos, pos + 1)}
              disabled={pos === count - 1}
              className="btn btn-ghost btn-xs btn-square disabled:opacity-20"
              title="Move down"
            >
              <ChevronDown size={12} />
            </button>
          </div>
        ))}
      </div>
      <p className="text-[10px] text-base-content/40 mt-1">Order columns stack in when the row collapses on phones.</p>
    </div>
  );
};

// re-export for callers that compute order in previews
export { orderFor };
