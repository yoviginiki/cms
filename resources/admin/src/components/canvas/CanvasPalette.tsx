import { useEffect, useMemo, useRef, useState } from 'react';
import { blockRegistry } from '@/components/blocks/registry';
import '@/components/blocks';

// Common leaf blocks that make sense as freeform canvas elements. (Structural
// blocks — section/row/column/grid — are not offered inside a canvas section.)
const CANVAS_BLOCKS = ['heading', 'text', 'paragraph', 'image', 'button', 'video', 'icon', 'divider', 'gallery', 'html-embed', 'pullquote', 'stats', 'code'];

interface Props {
  onPick: (blockType: string) => void;
  onClose: () => void;
}

export function CanvasPalette({ onPick, onClose }: Props) {
  const [q, setQ] = useState('');
  const rootRef = useRef<HTMLDivElement>(null);
  const items = useMemo(() => {
    const all = CANVAS_BLOCKS.filter(t => blockRegistry.get(t));
    return all.filter(t => t.toLowerCase().includes(q.toLowerCase()));
  }, [q]);

  // Dismiss on Escape (captured so it doesn't also clear the canvas selection)
  // or a pointerdown outside the palette. The document listener is deferred a
  // tick so the click that opened the palette doesn't immediately close it.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') { e.stopPropagation(); onClose(); } };
    const onDown = (e: PointerEvent) => { if (!rootRef.current?.contains(e.target as Node)) onClose(); };
    window.addEventListener('keydown', onKey, true);
    const id = window.setTimeout(() => document.addEventListener('pointerdown', onDown), 0);
    return () => {
      window.removeEventListener('keydown', onKey, true);
      window.clearTimeout(id);
      document.removeEventListener('pointerdown', onDown);
    };
  }, [onClose]);

  return (
    <div ref={rootRef} className="absolute z-50 mt-1 w-64 rounded-lg border border-base-300 bg-base-100 shadow-xl p-2" onPointerDown={(e) => e.stopPropagation()}>
      <input
        autoFocus
        value={q}
        onChange={(e) => setQ(e.target.value)}
        placeholder="Add a block…"
        className="input input-sm input-bordered w-full mb-2"
      />
      <div className="grid grid-cols-2 gap-1 max-h-64 overflow-y-auto">
        {items.map(t => (
          <button
            key={t}
            className="btn btn-xs btn-ghost justify-start capitalize"
            onClick={() => { onPick(t); onClose(); }}
          >
            {t.replace('-', ' ')}
          </button>
        ))}
        {items.length === 0 && <div className="col-span-2 text-xs text-base-content/40 p-2">No blocks match.</div>}
      </div>
    </div>
  );
}
