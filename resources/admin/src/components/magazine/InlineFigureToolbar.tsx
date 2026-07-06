import { useEffect, useRef, useState } from 'react';
import { AlignStartVertical, AlignEndVertical, AlignCenterHorizontal, Columns2, Trash2 } from 'lucide-react';
import { useMagazineStore } from '@/stores/magazineStore';

type Flow = 'left' | 'right' | 'center' | 'span';

const WIDTHS = [25, 33, 50, 75, 100];

function currentFlow(fig: HTMLElement): Flow {
  if (fig.style.columnSpan === 'all') return 'span';
  if (fig.style.float === 'right') return 'right';
  if (fig.style.float === 'left') return 'left';
  return 'center';
}

/**
 * Floating toolbar for inline images inside a text frame being edited.
 * Previously an inserted figure was frozen: hardcoded float-left at 40%,
 * a filename caption nobody asked for, and no UI to change any of it.
 * Click the image to set width, text flow (left/right/centered/across
 * all columns), edit or remove the caption, or delete the figure.
 */
export default function InlineFigureToolbar() {
  const [fig, setFig] = useState<HTMLElement | null>(null);
  const [pos, setPos] = useState<{ top: number; left: number }>({ top: 0, left: 0 });
  const [caption, setCaption] = useState('');
  const [, setTick] = useState(0);
  const barRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      const t = e.target as HTMLElement;
      if (barRef.current && barRef.current.contains(t)) return;
      const editable = t.closest('[data-editing-id]');
      const figure = editable ? (t.closest('figure') as HTMLElement | null) : null;
      if (figure && editable) {
        const r = figure.getBoundingClientRect();
        setPos({ top: Math.max(8, r.top - 44), left: Math.max(8, r.left) });
        setCaption(figure.querySelector('figcaption')?.textContent || '');
        setFig(figure);
      } else {
        setFig(null);
      }
    };
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setFig(null); };
    document.addEventListener('click', onClick, true);
    document.addEventListener('keydown', onKey);
    return () => { document.removeEventListener('click', onClick, true); document.removeEventListener('keydown', onKey); };
  }, []);

  // The figure disappears when editing ends (blur re-renders the frame) — drop the toolbar with it.
  useEffect(() => {
    if (!fig) return;
    const iv = setInterval(() => { if (!fig.isConnected) setFig(null); }, 400);
    return () => clearInterval(iv);
  }, [fig]);

  if (!fig) return null;

  const persist = () => {
    const editable = fig.closest('[data-editing-id]') as HTMLElement | null;
    const editId = editable?.getAttribute('data-editing-id');
    if (!editable || !editId) return;
    const store = useMagazineStore.getState();
    const el = store.pages.flatMap((p) => p.elements).find((x) => x.id === editId);
    if (el) store.updateElement(editId, { data: { ...el.data, content: editable.innerHTML } } as never);
  };

  const setWidth = (pct: number) => {
    fig.style.width = `${pct}%`;
    if (currentFlow(fig) === 'center') fig.style.margin = pct === 100 ? '8px 0' : '8px auto';
    setTick((t) => t + 1);
    persist();
  };

  const setFlow = (flow: Flow) => {
    fig.style.columnSpan = flow === 'span' ? 'all' : '';
    fig.style.float = flow === 'left' ? 'left' : flow === 'right' ? 'right' : 'none';
    fig.style.margin = flow === 'left' ? '0 12px 8px 0'
      : flow === 'right' ? '0 0 8px 12px'
      : flow === 'span' ? '8px 0'
      : (parseInt(fig.style.width) || 100) < 100 ? '8px auto' : '8px 0';
    if (flow === 'span') fig.style.width = '100%';
    setTick((t) => t + 1);
    persist();
  };

  const applyCaption = (text: string) => {
    setCaption(text);
    let cap = fig.querySelector('figcaption');
    if (text.trim() === '') {
      cap?.remove();
    } else {
      if (!cap) {
        cap = document.createElement('figcaption');
        cap.setAttribute('style', 'font-size:10px;opacity:0.7;margin-top:4px;');
        fig.appendChild(cap);
      }
      cap.textContent = text;
    }
    persist();
  };

  const remove = () => {
    const editable = fig.closest('[data-editing-id]') as HTMLElement | null;
    fig.remove();
    setFig(null);
    if (editable) {
      const editId = editable.getAttribute('data-editing-id');
      const store = useMagazineStore.getState();
      const el = editId ? store.pages.flatMap((p) => p.elements).find((x) => x.id === editId) : null;
      if (el && editId) store.updateElement(editId, { data: { ...el.data, content: editable.innerHTML } } as never);
    }
  };

  const flow = currentFlow(fig);
  const width = parseInt(fig.style.width) || 100;

  const flowBtn = (f: Flow, Icon: typeof AlignStartVertical, title: string) => (
    <button type="button" title={title} onMouseDown={(e) => e.preventDefault()} onClick={() => setFlow(f)}
      className={`p-1 rounded ${flow === f ? 'bg-primary text-primary-content' : 'text-base-content/60 hover:bg-base-300/40'}`}>
      <Icon size={12} />
    </button>
  );

  return (
    <div ref={barRef}
      className="fixed z-[10000] bg-base-100 border border-base-300 rounded-lg shadow-xl px-2 py-1.5 flex items-center gap-2"
      style={{ top: pos.top, left: pos.left }}>
      <div className="flex items-center gap-0.5">
        {WIDTHS.map((w) => (
          <button key={w} type="button" onMouseDown={(e) => e.preventDefault()} onClick={() => setWidth(w)}
            className={`px-1 py-0.5 rounded text-[9px] font-medium ${width === w ? 'bg-primary text-primary-content' : 'text-base-content/60 hover:bg-base-300/40'}`}>
            {w}%
          </button>
        ))}
      </div>
      <div className="w-px h-4 bg-base-300" />
      <div className="flex items-center gap-0.5">
        {flowBtn('left', AlignStartVertical, 'Image left, text wraps right')}
        {flowBtn('right', AlignEndVertical, 'Image right, text wraps left')}
        {flowBtn('center', AlignCenterHorizontal, 'In text flow (no wrap)')}
        {flowBtn('span', Columns2, 'Across all columns')}
      </div>
      <div className="w-px h-4 bg-base-300" />
      <input type="text" value={caption} placeholder="Caption (empty = none)"
        onChange={(e) => applyCaption(e.target.value)}
        onMouseDown={(e) => e.stopPropagation()}
        className="input input-bordered input-xs w-36 text-[10px]" />
      <button type="button" title="Remove image" onMouseDown={(e) => e.preventDefault()} onClick={remove}
        className="p-1 rounded text-error/70 hover:text-error hover:bg-error/10">
        <Trash2 size={12} />
      </button>
    </div>
  );
}
