import { useState } from 'react';
import {
  DndContext, PointerSensor, closestCenter, useSensor, useSensors, type DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext, arrayMove, rectSortingStrategy, useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
  Check, GripVertical, Loader2, MessageSquare, RefreshCw, Sparkles, X,
} from 'lucide-react';
import { useStudioStore } from './store';
import SpreadSketch from './SpreadSketch';
import { SECTION_LABELS, type Brief, type FlatplanSpread } from './types';

/**
 * The flatplan: schematic spread thumbnails in reading order. Reorder by
 * drag (cover and closer are pinned), revise any slot conversationally,
 * regenerate the whole plan, or approve to lock it.
 */
export default function FlatplanBoard() {
  const { session, planning, generateFlatplan, reorder, approveFlatplan } = useStudioStore();
  const [confirmApprove, setConfirmApprove] = useState(false);
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

  if (!session) return null;
  const flatplan = session.flatplan;
  const locked = (flatplan?.approved ?? false) || session.status !== 'flatplanning';

  if (!flatplan) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-center border border-base-300">
        <Sparkles className="h-10 w-10 text-base-content/10 mb-3" strokeWidth={1} />
        <h3 className="text-sm font-medium text-base-content/60 mb-1">The brief is ready</h3>
        <p className="text-[14px] text-base-content/35 mb-5 max-w-md">
          Next the editorial director plans the whole issue — a visual map of every spread,
          sized honestly from your material. You approve it before anything is designed.
        </p>
        <button onClick={() => void generateFlatplan()} disabled={planning} className="btn btn-primary btn-sm text-[14px] gap-2">
          {planning ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
          {planning ? 'Planning the issue…' : 'Draw up the flatplan'}
        </button>
      </div>
    );
  }

  const spreads = flatplan.spreads;
  const pageCount = 1 + (spreads.length - 1) * 2;

  const onDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const positions = spreads.map((s) => s.position);
    const from = positions.indexOf(Number(active.id));
    const to = positions.indexOf(Number(over.id));
    // cover stays first, closer stays last
    if (from <= 0 || to <= 0 || from >= spreads.length - 1 || to >= spreads.length - 1) return;
    void reorder(arrayMove(positions, from, to));
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <div>
          <h2 className="text-[15px] font-medium text-base-content/80">
            The flatplan — {spreads.length - 1} spreads + cover ({pageCount} pages)
          </h2>
          <p className="text-[13px] text-base-content/40">
            {locked
              ? 'Approved and locked. Spreads are queued for generation.'
              : 'Drag to reorder, click the chat icon on a spread to revise it, approve when it feels right.'}
          </p>
        </div>
        {!locked && (
          <div className="flex items-center gap-2">
            <button onClick={() => void generateFlatplan()} disabled={planning} className="btn btn-ghost btn-sm text-[13px] gap-1.5">
              {planning ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <RefreshCw className="h-3.5 w-3.5" />}
              Rethink whole plan
            </button>
            {confirmApprove ? (
              <>
                <button onClick={() => { void approveFlatplan(); setConfirmApprove(false); }} className="btn btn-primary btn-sm text-[13px] gap-1.5">
                  <Check className="h-3.5 w-3.5" /> Yes, lock it in
                </button>
                <button onClick={() => setConfirmApprove(false)} className="btn btn-ghost btn-sm text-[13px]">
                  Not yet
                </button>
              </>
            ) : (
              <button onClick={() => setConfirmApprove(true)} className="btn btn-primary btn-sm text-[13px] gap-1.5">
                <Check className="h-3.5 w-3.5" /> Approve flatplan
              </button>
            )}
          </div>
        )}
      </div>

      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
        <SortableContext items={spreads.map((s) => s.position)} strategy={rectSortingStrategy}>
          <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            {spreads.map((spread) => (
              <SpreadCard
                key={spread.position}
                spread={spread}
                brief={session.brief}
                locked={locked}
                pinned={spread.position === 0 || spread.position === spreads.length - 1}
              />
            ))}
          </div>
        </SortableContext>
      </DndContext>
    </div>
  );
}

function SpreadCard({ spread, brief, locked, pinned }: {
  spread: FlatplanSpread;
  brief: Brief;
  locked: boolean;
  pinned: boolean;
}) {
  const { revisingPosition, reviseSpread } = useStudioStore();
  const [revising, setRevising] = useState(false);
  const [instruction, setInstruction] = useState('');
  const busy = revisingPosition === spread.position;

  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: spread.position,
    disabled: locked || pinned,
  });

  const materialTitles = spread.materials.map(
    (id) => brief.materials.find((m) => m.id === id)?.title ?? id,
  );

  const submitRevision = () => {
    const text = instruction.trim();
    if (!text) return;
    setRevising(false);
    setInstruction('');
    void reviseSpread(spread.position, text);
  };

  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={`border border-base-300 bg-base-100 ${isDragging ? 'opacity-60 z-10 relative' : ''} ${busy ? 'opacity-70' : ''}`}
    >
      <div className="flex items-center gap-1.5 px-2.5 py-1.5 border-b border-base-300">
        {!locked && !pinned && (
          <span {...attributes} {...listeners} className="cursor-grab text-base-content/25 hover:text-base-content/60" title="Drag to reorder">
            <GripVertical className="h-4 w-4" />
          </span>
        )}
        <span className="text-[11px] font-medium text-base-content/40 w-6">
          {spread.position === 0 ? 'C' : spread.position}
        </span>
        <span className="text-[11px] uppercase tracking-wide text-base-content/40">
          {spread.section ? SECTION_LABELS[spread.section] : ''}
        </span>
        <span className="ml-auto text-[11px] text-primary/80">{spread.pattern}</span>
        {!locked && (
          <button
            onClick={() => setRevising((v) => !v)}
            className="text-base-content/30 hover:text-primary"
            title="Revise this spread"
            disabled={busy}
          >
            {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <MessageSquare className="h-3.5 w-3.5" />}
          </button>
        )}
      </div>

      <SpreadSketch pattern={spread.pattern} className="w-full block" />

      <div className="px-3 py-2 border-t border-base-300">
        <div className="text-[13px] font-medium text-base-content/80 truncate">{spread.working_title}</div>
        <div className="text-[12px] text-base-content/45 leading-snug mt-0.5">{spread.intent}</div>
        {materialTitles.length > 0 && (
          <div className="flex flex-wrap gap-1 mt-1.5">
            {materialTitles.map((t, i) => (
              <span key={i} className="text-[11px] px-1.5 py-0.5 bg-base-200 text-base-content/55 truncate max-w-[140px]">
                {t}
              </span>
            ))}
          </div>
        )}
      </div>

      {revising && !locked && (
        <div className="px-3 py-2 border-t border-base-300 flex items-center gap-2">
          <input
            autoFocus
            value={instruction}
            onChange={(e) => setInstruction(e.target.value)}
            onKeyDown={(e) => { if (e.key === 'Enter') submitRevision(); if (e.key === 'Escape') setRevising(false); }}
            placeholder='e.g. "make this image-led" or "quieter"'
            className="input input-bordered input-xs flex-1 text-[13px] rounded-none"
          />
          <button onClick={submitRevision} className="btn btn-primary btn-xs" disabled={!instruction.trim()}>
            Go
          </button>
          <button onClick={() => setRevising(false)} className="btn btn-ghost btn-xs px-1">
            <X className="h-3 w-3" />
          </button>
        </div>
      )}
    </div>
  );
}
