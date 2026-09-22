import { useMemo, useState } from 'react';
import { Search } from 'lucide-react';
import { blockRegistry } from '@/components/blocks/registry';
import '@/components/blocks';
import { BlockIcon } from '@/components/editor/BlockIcon';
import { useCanvasStore } from '@/stores/canvasStore';
import { CANVAS_BLOCK_GROUPS, CANVAS_DRAG_MIME, defaultSize } from '@/lib/canvasBlocks';

/**
 * Always-visible block palette on the left of the canvas editor. Each tile can
 * be **dragged onto any section** (it lands centred under the pointer) or
 * **clicked** to drop it near the top-left of the active section — creating a
 * first section when the page is still empty.
 */
export function CanvasBlockPalette() {
  const [q, setQ] = useState('');
  const groups = useMemo(() => CANVAS_BLOCK_GROUPS.map(g => ({
    title: g.title,
    items: g.types
      .map(type => ({ type, reg: blockRegistry.get(type)! }))
      .filter(x => x.reg && (x.type + ' ' + x.reg.definition.label).toLowerCase().includes(q.toLowerCase())),
  })).filter(g => g.items.length > 0), [q]);

  const place = (blockType: string) => {
    const st = useCanvasStore.getState();
    let sectionId: string | null = st.activeSectionId ?? st.sections[0]?.id ?? null;
    if (!sectionId) { st.addSection(); sectionId = useCanvasStore.getState().activeSectionId; }
    if (!sectionId) return;
    const target: string = sectionId;
    const section = useCanvasStore.getState().sections.find(s => s.id === target);
    const n = section?.elements.length ?? 0;
    const { width, height } = defaultSize(blockType);
    st.addElement(target, blockType, 40 + (n % 5) * 24, 40 + (n % 5) * 24, width, height);
  };

  return (
    <aside className="w-40 shrink-0 border-r border-base-200 bg-base-100 flex flex-col overflow-hidden" data-testid="canvas-block-palette" aria-label="Blocks">
      <div className="p-2 border-b border-base-300/20">
        <label className="input input-xs input-bordered flex items-center gap-1">
          <Search size={11} className="text-base-content/40" />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Find a block" className="grow min-w-0 text-[11px]" aria-label="Find a block" />
        </label>
      </div>
      <p className="px-2 pt-2 text-[9px] uppercase tracking-wider text-base-content/40">Drag onto the page, or click</p>
      <div className="flex-1 overflow-y-auto p-2 space-y-3">
        {groups.map(g => (
          <section key={g.title} aria-label={g.title}>
            <h4 className="text-[9px] font-semibold uppercase tracking-wider text-base-content/35 mb-1">{g.title}</h4>
            <div className="grid grid-cols-2 gap-1">
              {g.items.map(({ type, reg }) => (
                <button
                  key={type}
                  type="button"
                  draggable
                  onDragStart={(e) => {
                    e.dataTransfer.setData(CANVAS_DRAG_MIME, type);
                    e.dataTransfer.setData('text/plain', type);   // Firefox needs a text payload to start a drag
                    e.dataTransfer.effectAllowed = 'copy';
                  }}
                  onClick={() => place(type)}
                  title={`${reg.definition.label} — drag onto the page or click to add`}
                  className="flex flex-col items-center gap-1 rounded-md border border-base-300/40 bg-base-100 hover:bg-base-200 hover:border-primary/40 cursor-grab active:cursor-grabbing px-1 py-2 text-[10px] text-base-content/70 leading-tight"
                  data-block-type={type}
                >
                  <BlockIcon icon={reg.definition.icon ?? 'Box'} size={16} className="text-primary/70" />
                  <span className="truncate w-full text-center">{reg.definition.label}</span>
                </button>
              ))}
            </div>
          </section>
        ))}
        {groups.length === 0 && <p className="text-[11px] text-base-content/40 p-2">No blocks match.</p>}
      </div>
    </aside>
  );
}
