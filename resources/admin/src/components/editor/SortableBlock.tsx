import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import type { BlockData } from '@/types/blocks';
import { blockRegistry } from '@/components/blocks/registry';
import { useEditorStore } from '@/stores/editorStore';
import { BlockToolbar } from './BlockToolbar';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { useDroppable } from '@dnd-kit/core';
import { buildBlockWrapperStyle, buildAnimationStyle, buildBlockClasses, safeDim } from '@/lib/blockStyles';
import { LAYOUT_GRID, type RowLayout } from '@/components/blocks/row/definition';
import { Plus } from 'lucide-react';

interface SortableBlockProps {
  block: BlockData;
  depth?: number;
}

function DroppableZone({ id, children }: { id: string; children: React.ReactNode }) {
  const { setNodeRef, isOver } = useDroppable({ id });
  return (
    <div
      ref={setNodeRef}
      className={`min-h-[20px] rounded transition-colors ${
        isOver ? 'border-2 border-dashed border-blue-400 bg-blue-50' : ''
      }`}
    >
      {children}
    </div>
  );
}

// Quick-add config per level
const QUICK_ADD: Record<string, { type: string; label: string; color: string }> = {
  section: { type: 'row', label: 'Row', color: 'bg-green-50 text-green-600 border-green-200 hover:bg-green-100' },
  row: { type: 'column', label: 'Column', color: 'bg-purple-50 text-purple-600 border-purple-200 hover:bg-purple-100' },
  column: { type: 'heading', label: 'Heading', color: 'bg-blue-50 text-blue-600 border-blue-200 hover:bg-blue-100' },
};

export function SortableBlock({ block, depth = 0 }: SortableBlockProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({
    id: block.id,
    data: { type: 'block', block },
  });

  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const selectBlock = useEditorStore((s) => s.selectBlock);
  const updateBlock = useEditorStore((s) => s.updateBlock);
  const addBlock = useEditorStore((s) => s.addBlock);

  const isSelected = selectedBlockId === block.id;
  const registration = blockRegistry.get(block.type);

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.3 : 1,
  };

  if (!registration) {
    return (
      <div ref={setNodeRef} style={style} className="p-4 bg-red-50 border border-red-200 rounded">
        Unknown block type: {block.type}
      </div>
    );
  }

  const { Preview } = registration;
  const allowsChildren = registration.definition.allowsChildren;
  const quickAdd = block.level ? QUICK_ADD[block.level] : undefined;

  // Determine children layout based on parent block type
  const isRow = block.type === 'row';
  const rowLayout = isRow ? ((block.data.layout as RowLayout) || '1/2+1/2') : undefined;
  const rowGap = isRow ? (safeDim(block.data.gap) || '16px') : undefined;
  const childrenStyle: React.CSSProperties = isRow
    ? { display: 'grid', gridTemplateColumns: LAYOUT_GRID[rowLayout!] || '1fr 1fr', gap: rowGap }
    : {};

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`relative group transition-all ${
        isSelected
          ? 'ring-2 ring-blue-500 ring-offset-2 rounded-lg'
          : 'hover:outline hover:outline-1 hover:outline-blue-200 hover:outline-offset-2 rounded-lg'
      }`}
      onClick={(e) => {
        e.stopPropagation();
        selectBlock(block.id);
      }}
    >
      {isSelected && (
        <BlockToolbar
          block={block}
          dragHandleProps={{ ...attributes, ...listeners }}
        />
      )}

      <div
        className={`relative ${buildBlockClasses(block.advanced)}`}
        style={{
          ...buildBlockWrapperStyle(block.style),
          ...buildAnimationStyle(block.animation),
        }}
        {...(block.advanced?.htmlId ? { id: block.advanced.htmlId.replace(/[^a-zA-Z0-9_-]/g, '') } : {})}
        {...(block.advanced?.ariaLabel ? { 'aria-label': block.advanced.ariaLabel } : {})}
      >
        {block.responsive?.hideOn && block.responsive.hideOn.length > 0 && (
          <div className="absolute top-1 right-1 z-20 flex gap-0.5">
            {block.responsive.hideOn.map((device) => (
              <span key={device} className="px-1 py-0.5 bg-warning/80 text-warning-content text-[9px] rounded font-medium">
                Hidden on {device}
              </span>
            ))}
          </div>
        )}

        {/* Render Preview — for containers, only when empty (children render in DnD zone) */}
        {(!allowsChildren || block.children.length === 0) && (
          <Preview
            block={block}
            isSelected={isSelected}
            onUpdate={(data) => updateBlock(block.id, data)}
            onSelect={() => selectBlock(block.id)}
          />
        )}
      </div>

      {allowsChildren && (
        <DroppableZone id={`${block.id}-children`}>
          <SortableContext
            items={block.children.map((c) => c.id)}
            strategy={verticalListSortingStrategy}
            id={`sortable-${block.id}`}
          >
            <div style={childrenStyle} className={isRow ? 'min-h-[40px]' : 'space-y-1'}>
              {block.children.length === 0 ? (
                <div className="text-center py-4 col-span-full">
                  <p className="text-sm text-gray-400 mb-2">
                    {block.level === 'section' ? 'Add rows to this section' :
                     block.level === 'row' ? 'Add columns to this row' :
                     block.level === 'column' ? 'Add modules to this column' :
                     'Drop blocks here'}
                  </p>
                  {quickAdd && (
                    <button
                      onClick={(e) => { e.stopPropagation(); addBlock(quickAdd.type, block.id); }}
                      className={`inline-flex items-center gap-1 px-3 py-1 text-xs border rounded transition-colors ${quickAdd.color}`}
                    >
                      <Plus size={12} />
                      Add {quickAdd.label}
                    </button>
                  )}
                </div>
              ) : (
                block.children.map((child) => (
                  <SortableBlock key={child.id} block={child} depth={depth + 1} />
                ))
              )}
            </div>
          </SortableContext>
        </DroppableZone>
      )}

      {/* Drag handle (visible on hover when not selected) */}
      {!isSelected && (
        <div
          className="absolute top-2 left-2 opacity-0 group-hover:opacity-100 cursor-grab p-1 bg-white rounded shadow text-gray-400 hover:text-gray-600 transition-opacity z-10"
          {...attributes}
          {...listeners}
        >
          <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
            <circle cx="5" cy="4" r="1.5" /><circle cx="11" cy="4" r="1.5" />
            <circle cx="5" cy="8" r="1.5" /><circle cx="11" cy="8" r="1.5" />
            <circle cx="5" cy="12" r="1.5" /><circle cx="11" cy="12" r="1.5" />
          </svg>
        </div>
      )}
    </div>
  );
}
