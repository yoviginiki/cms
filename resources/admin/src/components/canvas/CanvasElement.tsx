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
const POS: Record<ResizeHandle, React.CSSProperties> = {
  nw: { left: -5, top: -5 }, n: { left: '50%', top: -5, marginLeft: -5 }, ne: { right: -5, top: -5 },
  e: { right: -5, top: '50%', marginTop: -5 }, se: { right: -5, bottom: -5 }, s: { left: '50%', bottom: -5, marginLeft: -5 },
  sw: { left: -5, bottom: -5 }, w: { left: -5, top: '50%', marginTop: -5 },
};

interface Props {
  el: El;
  selected: boolean;
  onPointerDown: (e: React.PointerEvent, id: string) => void;
  onResizeDown: (e: React.PointerEvent, id: string, handle: ResizeHandle) => void;
  onRotateDown: (e: React.PointerEvent, id: string, center: { cx: number; cy: number }) => void;
}

export function CanvasElement({ el, selected, onPointerDown, onResizeDown, onRotateDown }: Props) {
  const ref = useRef<HTMLDivElement>(null);
  const updateElement = useCanvasStore(s => s.updateElement);
  const reg = blockRegistry.get(el.blockType);

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
        outline: selected ? '1.5px solid #2563eb' : '1px dashed rgba(37,99,235,0.25)',
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
            style={{ position: 'absolute', left: '50%', top: -26, marginLeft: -6, width: 12, height: 12, borderRadius: '50%', background: '#2563eb', cursor: 'grab', border: '2px solid #fff' }}
          />
          {/* resize handles */}
          {HANDLES.map(h => (
            <div
              key={h}
              onPointerDown={(e) => onResizeDown(e, el.id, h)}
              style={{ position: 'absolute', width: 10, height: 10, background: '#fff', border: '1.5px solid #2563eb', borderRadius: 2, cursor: CURSORS[h], ...POS[h] }}
            />
          ))}
        </>
      )}
    </div>
  );
}
