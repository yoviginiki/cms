/**
 * P5 — Enhanced Wireframe Block with drag-to-resize, insert points, size indicators.
 *
 * Renders blocks as labeled boxes showing hierarchy.
 * Rows show columns in grid with draggable resize handles.
 * Insert points between blocks for quick add.
 */

import { useEditorStore } from '@/stores/editorStore';
import { blockRegistry } from '@/components/blocks/registry';
import type { BlockData } from '@/types/blocks';
import { ChevronRight, ChevronDown, Plus, GripVertical, Trash2, Copy } from 'lucide-react';
import { BlockIcon } from './BlockIcon';
import { ModulePicker } from './ModulePicker';
import { LAYOUT_GRID, LAYOUT_LABELS, type RowLayout } from '@/components/blocks/row/definition';
import { useState, useRef, useCallback } from 'react';
import { safeDim } from '@/lib/blockStyles';

const typeColors: Record<string, string> = {
  section: 'border-blue-300 bg-blue-50/50',
  row: 'border-green-300 bg-green-50/30',
  column: 'border-purple-300 bg-purple-50/30',
  columns: 'border-purple-300 bg-purple-50/30',
  container: 'border-green-300 bg-green-50/30',
  heading: 'border-gray-300 bg-white',
  paragraph: 'border-gray-300 bg-white',
  text: 'border-gray-300 bg-white',
  'rich-text': 'border-gray-300 bg-white',
  image: 'border-amber-300 bg-amber-50/30',
  button: 'border-indigo-300 bg-indigo-50/30',
  hero: 'border-rose-300 bg-rose-50/30',
  postgrid: 'border-orange-300 bg-orange-50/30',
  gallery: 'border-amber-300 bg-amber-50/30',
  menu: 'border-teal-300 bg-teal-50/30',
  video: 'border-red-300 bg-red-50/30',
};

const levelColors: Record<string, string> = {
  section: 'bg-blue-100 text-blue-700',
  row: 'bg-green-100 text-green-700',
  column: 'bg-purple-100 text-purple-700',
  module: 'bg-gray-100 text-gray-600',
};

function getBlockLabel(block: BlockData): string {
  const reg = blockRegistry.get(block.type);
  const label = reg?.definition.label || block.type;
  const data = block.data ?? {};
  const title = (data.title as string) || (data.text as string) || (data.heading as string) || (data.content as string) || '';
  if (title) {
    const clean = title.replace(/<[^>]*>/g, '').trim();
    if (clean) return `${label}: "${clean.slice(0, 30)}${clean.length > 30 ? '…' : ''}"`;
  }
  if (block.type === 'row' && data.layout) return `${label} (${LAYOUT_LABELS[data.layout as RowLayout] || data.layout})`;
  return label;
}

function getSizeInfo(block: BlockData): string {
  const sp = block.style?.spacing;
  if (!sp) return '';
  const parts: string[] = [];
  const pt = safeDim(sp.paddingTop); const pb = safeDim(sp.paddingBottom);
  const mt = safeDim(sp.marginTop); const mb = safeDim(sp.marginBottom);
  if (pt || pb) parts.push(`P: ${pt || '0'}/${pb || '0'}`);
  if (mt || mb) parts.push(`M: ${mt || '0'}/${mb || '0'}`);
  const w = block.style?.layout?.width; const mw = block.style?.layout?.maxWidth;
  if (w) parts.push(`W:${w}`);
  if (mw) parts.push(`MW:${mw}`);
  return parts.join(' · ');
}

const quickAddChild: Record<string, { type: string; label: string; color: string }> = {
  section: { type: 'row', label: 'Row', color: 'text-green-600 hover:bg-green-50' },
  row: { type: 'column', label: 'Column', color: 'text-purple-600 hover:bg-purple-50' },
  column: { type: 'heading', label: 'Module', color: 'text-blue-600 hover:bg-blue-50' },
};

// ─── Insert Point ───
function InsertPoint({ parentId, index, level }: { parentId?: string; index: number; level: string }) {
  const addBlock = useEditorStore((s) => s.addBlock);
  const [hovered, setHovered] = useState(false);
  const childType = level === 'section' ? 'row' : level === 'row' ? 'column' : 'heading';

  return (
    <div
      className="relative flex items-center justify-center"
      style={{ height: hovered ? 32 : 8, transition: 'height 0.15s ease' }}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
    >
      {hovered && (
        <button
          onClick={(e) => { e.stopPropagation(); addBlock(childType, parentId, index); }}
          className="absolute z-10 flex items-center gap-1 px-2 py-0.5 bg-blue-500 text-white text-[9px] rounded-full shadow-sm hover:bg-blue-600 transition-colors"
        >
          <Plus size={10} /> Insert {childType}
        </button>
      )}
      <div className={`w-full border-t transition-colors ${hovered ? 'border-blue-400' : 'border-transparent'}`} />
    </div>
  );
}

// ─── Column Resize Handle ───
function ColumnResizeHandle({ onResize }: { onResize: (delta: number) => void }) {
  const dragRef = useRef<{ startX: number } | null>(null);

  const onMouseDown = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    dragRef.current = { startX: e.clientX };
    const onMove = (ev: MouseEvent) => {
      if (!dragRef.current) return;
      const delta = ev.clientX - dragRef.current.startX;
      if (Math.abs(delta) > 20) {
        onResize(delta > 0 ? 1 : -1);
        dragRef.current.startX = ev.clientX;
      }
    };
    const onUp = () => {
      dragRef.current = null;
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
    };
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  }, [onResize]);

  return (
    <div
      className="flex items-center justify-center cursor-col-resize hover:bg-blue-100 transition-colors rounded"
      style={{ width: 12, minHeight: 40 }}
      onMouseDown={onMouseDown}
      title="Drag to resize columns"
    >
      <GripVertical size={10} className="text-gray-300" />
    </div>
  );
}

// ─── Layout options for cycle ───
const LAYOUT_CYCLE: RowLayout[] = ['1/2+1/2', '1/3+2/3', '2/3+1/3', '1/3+1/3+1/3', '1/4+1/4+1/4+1/4', '1/4+3/4', '3/4+1/4'];

// ─── Main WireframeBlock ───
interface WireframeBlockProps {
  block: BlockData;
  depth?: number;
}

export function WireframeBlock({ block, depth = 0 }: WireframeBlockProps) {
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const selectBlock = useEditorStore((s) => s.selectBlock);
  const addBlock = useEditorStore((s) => s.addBlock);
  const updateBlock = useEditorStore((s) => s.updateBlock);
  const removeBlock = useEditorStore((s) => s.removeBlock);
  const duplicateBlock = useEditorStore((s) => s.duplicateBlock);
  const [expanded, setExpanded] = useState(true);
  const [pickerOpen, setPickerOpen] = useState(false);
  const addBtnRef = useRef<HTMLButtonElement>(null);

  const reg = blockRegistry.get(block.type);
  const isSelected = selectedBlockId === block.id;
  const children = block.children ?? [];
  const hasChildren = children.length > 0;
  const iconName = reg?.definition.icon || 'Box';
  const colorClass = typeColors[block.type] || 'border-gray-300 bg-gray-50/30';
  const level = block.level || 'module';
  const levelColor = levelColors[level] || levelColors.module;
  const childConfig = quickAddChild[level];
  const sizeInfo = getSizeInfo(block);
  const isRow = block.type === 'row';

  // Row layout cycle on resize
  const handleColumnResize = useCallback((direction: number) => {
    if (!isRow) return;
    const currentLayout = ((block.data ?? {}).layout as RowLayout) || '1/2+1/2';
    const currentIdx = LAYOUT_CYCLE.indexOf(currentLayout);
    const nextIdx = Math.max(0, Math.min(LAYOUT_CYCLE.length - 1, currentIdx + direction));
    if (LAYOUT_CYCLE[nextIdx] !== currentLayout) {
      updateBlock(block.id, { layout: LAYOUT_CYCLE[nextIdx] });
    }
  }, [isRow, block.id, block.data, updateBlock]);

  return (
    <div style={{ marginLeft: depth > 0 ? 12 : 0 }}>
      {/* Block header */}
      <div
        className={`flex items-center gap-1.5 px-2 py-1.5 rounded-md border cursor-pointer transition-all mb-0.5 ${colorClass} ${
          isSelected ? 'ring-2 ring-blue-500 ring-offset-1 border-blue-500' : 'hover:border-blue-200'
        }`}
        onClick={(e) => { e.stopPropagation(); selectBlock(block.id); }}
      >
        {/* Expand/collapse */}
        {hasChildren ? (
          <button onClick={(e) => { e.stopPropagation(); setExpanded(!expanded); }} className="p-0.5 text-gray-400 hover:text-gray-600">
            {expanded ? <ChevronDown size={12} /> : <ChevronRight size={12} />}
          </button>
        ) : <span className="w-[18px]" />}

        <BlockIcon icon={iconName} size={12} className="text-gray-400 shrink-0" />

        <span className="text-[11px] font-medium text-gray-700 truncate flex-1">{getBlockLabel(block)}</span>

        {/* Size info */}
        {sizeInfo && <span className="text-[8px] text-gray-400 shrink-0 hidden sm:inline">{sizeInfo}</span>}

        <span className={`text-[8px] uppercase tracking-wider shrink-0 rounded px-1 py-0.5 font-medium ${levelColor}`}>{level}</span>

        {hasChildren && <span className="text-[8px] bg-gray-200 text-gray-500 rounded px-1 py-0.5">{children.length}</span>}

        {/* Actions (show on selected) */}
        {isSelected && (
          <div className="flex items-center gap-0.5 shrink-0">
            <button onClick={(e) => { e.stopPropagation(); duplicateBlock(block.id); }} className="p-0.5 text-gray-400 hover:text-blue-500" title="Duplicate"><Copy size={10} /></button>
            <button onClick={(e) => { e.stopPropagation(); removeBlock(block.id); }} className="p-0.5 text-gray-400 hover:text-red-500" title="Delete"><Trash2 size={10} /></button>
          </div>
        )}

        {/* Quick-add child */}
        {childConfig && (
          <button
            ref={level === 'column' ? addBtnRef : undefined}
            onClick={(e) => {
              e.stopPropagation();
              if (level === 'column') setPickerOpen(true);
              else addBlock(childConfig.type, block.id);
            }}
            className={`p-0.5 rounded ${childConfig.color} transition-colors`}
            title={`Add ${childConfig.label}`}
          >
            <Plus size={10} />
          </button>
        )}
      </div>

      {/* Children */}
      {hasChildren && expanded && (
        isRow ? (
          // Row: columns in grid with resize handles
          <div className="ml-2 pl-1 mb-1">
            <div
              className="flex gap-0"
              style={{ display: 'grid', gridTemplateColumns: LAYOUT_GRID[(block.data ?? {}).layout as RowLayout] || `repeat(${children.length}, 1fr)` }}
            >
              {children.map((child, idx) => (
                <div key={child.id} className="flex">
                  <div className="flex-1 min-w-0">
                    <WireframeBlock block={child} depth={0} />
                  </div>
                  {idx < children.length - 1 && (
                    <ColumnResizeHandle onResize={handleColumnResize} />
                  )}
                </div>
              ))}
            </div>
            {/* Row layout indicator */}
            <div className="text-[8px] text-gray-400 text-center mt-0.5">
              {LAYOUT_LABELS[(block.data ?? {}).layout as RowLayout] || 'Custom'} — drag handles to change
            </div>
          </div>
        ) : (
          // Other: vertical list with insert points
          <div className="ml-2 border-l border-gray-200 pl-1">
            {children.map((child, idx) => (
              <div key={child.id}>
                {idx === 0 && <InsertPoint parentId={block.id} index={0} level={level} />}
                <WireframeBlock block={child} depth={depth + 1} />
                <InsertPoint parentId={block.id} index={idx + 1} level={level} />
              </div>
            ))}
          </div>
        )
      )}

      {/* Empty children placeholder */}
      {!hasChildren && childConfig && expanded && (
        <div className="ml-4 py-2 px-3 border border-dashed border-gray-200 rounded text-[10px] text-gray-400 text-center mb-1">
          No {childConfig.label.toLowerCase()}s — click + to add
        </div>
      )}

      {/* Module picker */}
      {pickerOpen && (
        <ModulePicker parentId={block.id} onClose={() => setPickerOpen(false)} anchorEl={addBtnRef.current} />
      )}
    </div>
  );
}
