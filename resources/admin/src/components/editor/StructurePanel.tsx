import React, { useState } from 'react';
import { ChevronRight, ChevronDown, Eye, EyeOff, GripVertical } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';
import { blockRegistry } from '@/components/blocks/registry';
import { BlockIcon } from './BlockIcon';
import { structureLabel, dropZone, type DropZone } from '@/lib/structureHelpers';
import type { BlockData } from '@/types/blocks';

/** Human label for a tree row — a custom name wins, else content, else type. */
function deriveLabel(block: BlockData): string {
  return structureLabel(block) ?? (blockRegistry.get(block.type)?.definition.label || block.type);
}

const isHidden = (b: BlockData) => (b.style as any)?.layout?.display === 'none';

/**
 * P5 Structure panel — a nested, collapsible tree of the block hierarchy with
 * drag-reorder, inline rename, and visibility toggles. Complements (does not
 * replace) the flat z-index LayersPanel used by the canvas editor. All mutations
 * go through the shared editorStore, so undo/redo and dirty-tracking work for free.
 */
export function StructurePanel() {
  const blocks = useEditorStore((s) => s.blocks);
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const selectBlock = useEditorStore((s) => s.selectBlock);
  const updateBlock = useEditorStore((s) => s.updateBlock);
  const moveBlock = useEditorStore((s) => s.moveBlock);

  const [collapsed, setCollapsed] = useState<Set<string>>(new Set());
  const [editingId, setEditingId] = useState<string | null>(null);
  const [draft, setDraft] = useState('');
  const [dragId, setDragId] = useState<string | null>(null);
  const [drop, setDrop] = useState<{ id: string; zone: DropZone } | null>(null);

  const toggleCollapse = (id: string) =>
    setCollapsed((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });

  const toggleVisibility = (b: BlockData) => {
    const style = (b.style || {}) as any;
    const layout = style.layout || {};
    updateBlock(b.id, {
      __style: { ...style, layout: { ...layout, display: isHidden(b) ? 'block' : 'none' } },
    } as any);
  };

  const startRename = (b: BlockData) => {
    setEditingId(b.id);
    setDraft((b.data?.__label as string) || '');
  };
  const commitRename = () => {
    if (editingId) updateBlock(editingId, { __label: draft.trim() });
    setEditingId(null);
    setDraft('');
  };

  const allowsChildren = (b: BlockData) =>
    blockRegistry.get(b.type)?.definition.allowsChildren ?? false;

  const onDragOverRow = (e: React.DragEvent, b: BlockData) => {
    if (!dragId || dragId === b.id) return;
    e.preventDefault();
    const rect = e.currentTarget.getBoundingClientRect();
    const zone: DropZone = dropZone(e.clientY - rect.top, rect.height, allowsChildren(b));
    if (!drop || drop.id !== b.id || drop.zone !== zone) setDrop({ id: b.id, zone });
  };

  const onDropRow = (e: React.DragEvent, b: BlockData) => {
    e.preventDefault();
    if (dragId && dragId !== b.id && drop) moveBlock(dragId, b.id, drop.zone);
    setDragId(null);
    setDrop(null);
  };

  if (blocks.length === 0) {
    return (
      <div className="flex items-center justify-center h-full text-[12px] text-base-content/25 p-6 text-center">
        No blocks yet — add a section to start building.
      </div>
    );
  }

  const renderRow = (block: BlockData, depth: number): React.ReactNode => {
    const children = block.children ?? [];
    const hasChildren = children.length > 0;
    const isOpen = !collapsed.has(block.id);
    const isSel = block.id === selectedBlockId;
    const hidden = isHidden(block);
    const icon = blockRegistry.get(block.type)?.definition.icon || 'Box';
    const dropHere = drop?.id === block.id ? drop.zone : null;

    return (
      <div key={block.id}>
        <div
          draggable={editingId !== block.id}
          onDragStart={(e) => {
            setDragId(block.id);
            e.dataTransfer.effectAllowed = 'move';
          }}
          onDragOver={(e) => onDragOverRow(e, block)}
          onDrop={(e) => onDropRow(e, block)}
          onDragEnd={() => {
            setDragId(null);
            setDrop(null);
          }}
          onClick={() => selectBlock(block.id)}
          className={`group relative flex items-center gap-1 pr-1.5 py-1 text-[11px] cursor-pointer border-l-2 transition-colors ${
            isSel
              ? 'border-l-primary bg-primary/5 text-base-content/90'
              : 'border-l-transparent hover:bg-base-300/10 text-base-content/55'
          } ${hidden ? 'opacity-40' : ''} ${dragId === block.id ? 'opacity-30' : ''}`}
          style={{ paddingLeft: 6 + depth * 12 }}
        >
          {/* drop indicators */}
          {dropHere === 'before' && <span className="absolute left-0 right-0 -top-px h-0.5 bg-primary" />}
          {dropHere === 'after' && <span className="absolute left-0 right-0 -bottom-px h-0.5 bg-primary" />}
          {dropHere === 'inside' && <span className="absolute inset-0 ring-1 ring-inset ring-primary pointer-events-none" />}

          <GripVertical size={11} className="text-base-content/15 shrink-0 cursor-grab opacity-0 group-hover:opacity-100" />

          {/* collapse chevron (only when there are children) */}
          {hasChildren ? (
            <button
              onClick={(e) => { e.stopPropagation(); toggleCollapse(block.id); }}
              className="shrink-0 text-base-content/40 hover:text-base-content/80"
              title={isOpen ? 'Collapse' : 'Expand'}
            >
              {isOpen ? <ChevronDown size={12} /> : <ChevronRight size={12} />}
            </button>
          ) : (
            <span className="w-3 shrink-0" />
          )}

          <BlockIcon icon={icon} size={12} className="shrink-0 text-base-content/35" />

          {/* label / inline rename */}
          {editingId === block.id ? (
            <input
              autoFocus
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
              onClick={(e) => e.stopPropagation()}
              onBlur={commitRename}
              onKeyDown={(e) => {
                if (e.key === 'Enter') commitRename();
                if (e.key === 'Escape') { setEditingId(null); setDraft(''); }
              }}
              className="flex-1 min-w-0 bg-base-100 border border-primary/40 px-1 py-0.5 text-[11px] outline-none"
            />
          ) : (
            <span
              className="flex-1 truncate min-w-0 select-none"
              onDoubleClick={(e) => { e.stopPropagation(); startRename(block); }}
              title="Double-click to rename"
            >
              {deriveLabel(block)}
            </span>
          )}

          <button
            onClick={(e) => { e.stopPropagation(); toggleVisibility(block); }}
            className="btn btn-ghost btn-xs btn-square opacity-0 group-hover:opacity-60 hover:!opacity-100"
            title={hidden ? 'Show' : 'Hide'}
          >
            {hidden ? <EyeOff size={11} /> : <Eye size={11} />}
          </button>
        </div>

        {hasChildren && isOpen && children.map((c) => renderRow(c, depth + 1))}
      </div>
    );
  };

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center justify-between p-3 border-b border-base-300/20 shrink-0">
        <h3 className="text-[12px] font-medium text-base-content/60">Structure</h3>
        <span className="text-[10px] text-base-content/30">{blocks.length}</span>
      </div>
      <div className="flex-1 overflow-y-auto py-1">{blocks.map((b) => renderRow(b, 0))}</div>
    </div>
  );
}
