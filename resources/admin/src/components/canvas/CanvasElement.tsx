import { useRef } from 'react';
import { blockRegistry } from '@/components/blocks/registry';
import '@/components/blocks';
import type { BlockData } from '@/types/blocks';
import type { CanvasElement as El } from '@/types/canvas';
import { useCanvasStore } from '@/stores/canvasStore';
import type { ResizeHandle } from './useCanvasSelection';

const HANDLES: ResizeHandle[] = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'];
const CURSORS: Record<ResizeHandle, string> = {
  nw: 'nwse-resize', n: 'ns-resize', ne: 'nesw-resize', e: 'ew-resize',
  se: 'nwse-resize', s: 'ns-resize', sw: 'nesw-resize', w: 'ew-resize',
};

// Handle positions expressed in terms of a half-size offset `o` (= half the
// handle, negated) so they can be counter-scaled by the canvas zoom.
const posFor = (o: number): Record<ResizeHandle, React.CSSProperties> => ({
  nw: { left: o, top: o }, n: { left: '50%', top: o, marginLeft: o }, ne: { right: o, top: o },
  e: { right: o, top: '50%', marginTop: o }, se: { right: o, bottom: o }, s: { left: '50%', bottom: o, marginLeft: o },
  sw: { left: o, bottom: o }, w: { left: o, top: '50%', marginTop: o },
});

interface Props {
  el: El;
  selected: boolean;
  zoom: number;
  onPointerDown: (e: React.PointerEvent, id: string) => void;
  onResizeDown: (e: React.PointerEvent, id: string, handle: ResizeHandle) => void;
  onRotateDown: (e: React.PointerEvent, id: string, center: { cx: number; cy: number }) => void;
}

export function CanvasElement({ el, selected, zoom, onPointerDown, onResizeDown, onRotateDown }: Props) {
  const ref = useRef<HTMLDivElement>(null);
  const updateElement = useCanvasStore(s => s.updateElement);
  const reg = blockRegistry.get(el.blockType);

  // Counter-scale editing chrome by 1/zoom so handles/outlines stay a constant
  // on-screen size regardless of the canvas scale.
  const z = zoom || 1;
  const hSize = 10 / z;          // resize handle
  const hBorder = 1.5 / z;
  const posMap = posFor(-(hSize / 2));
  const rSize = 12 / z;          // rotate handle
  const outlineW = (selected ? 1.5 : 1) / z;

  const block: BlockData = { id: el.id, type: el.blockType, data: el.data, children: [], order: 0, style: el.style };

  const rotateDown = (e: React.PointerEvent) => {
    const r = ref.current?.getBoundingClientRect();
    if (!r) return;
    onRotateDown(e, el.id, { cx: r.left + r.width / 2, cy: r.top + r.height / 2 });
  };

  return (
    <div
      ref={ref}
      className="cv-editor-el"
      onPointerDown={(e) => onPointerDown(e, el.id)}
      style={{
        position: 'absolute',
        left: el.x, top: el.y, width: el.width, height: el.height,
        transform: el.rotation ? `rotate(${el.rotation}deg)` : undefined,
        zIndex: el.zIndex,
        outline: selected ? `${outlineW}px solid #2563eb` : `${outlineW}px dashed rgba(37,99,235,0.25)`,
        cursor: el.locked ? 'default' : 'move',
        boxSizing: 'border-box',
      }}
    >
      <div style={{ width: '100%', height: '100%', overflow: 'hidden', pointerEvents: 'none' }}>
        {reg ? (
          <reg.Preview
            block={block}
            isSelected={selected}
            onUpdate={(data) => updateElement(el.id, { data })}
            onSelect={() => { /* selection handled by wrapper pointerdown */ }}
          />
        ) : (
          <div style={{ padding: 8, fontSize: 12, color: '#64748b', background: '#f8fafc' }}>Unknown block: {el.blockType}</div>
        )}
      </div>

      {selected && !el.locked && (
        <>
          {/* rotate handle */}
          <div
            onPointerDown={rotateDown}
            title="Rotate"
            style={{ position: 'absolute', left: '50%', top: -26 / z, marginLeft: -(rSize / 2), width: rSize, height: rSize, borderRadius: '50%', background: '#2563eb', cursor: 'grab', border: `${2 / z}px solid #fff` }}
          />
          {/* resize handles */}
          {HANDLES.map(h => (
            <div
              key={h}
              onPointerDown={(e) => onResizeDown(e, el.id, h)}
              style={{ position: 'absolute', width: hSize, height: hSize, background: '#fff', border: `${hBorder}px solid #2563eb`, borderRadius: 2 / z, cursor: CURSORS[h], ...posMap[h] }}
            />
          ))}
        </>
      )}
    </div>
  );
}
