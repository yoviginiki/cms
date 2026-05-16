import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import type { BlockData } from '@/types/blocks';
import { blockRegistry } from '@/components/blocks/registry';
import { useEditorStore } from '@/stores/editorStore';
import { BlockToolbar } from './BlockToolbar';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { useDroppable } from '@dnd-kit/core';
import { buildBlockWrapperStyle, buildAnimationStyle, buildBlockClasses } from '@/lib/blockStyles';

interface SortableBlockProps {
  block: BlockData;
  depth?: number;
}

function DroppableZone({ id, children }: { id: string; children: React.ReactNode }) {
  const { setNodeRef, isOver } = useDroppable({ id });
  return (
    <div
      ref={setNodeRef}
      className={`min-h-[40px] rounded border-2 border-dashed transition-colors ${
        isOver ? 'border-blue-400 bg-blue-50' : 'border-gray-200'
      }`}
    >
      {children}
    </div>
  );
}

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

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`relative group rounded-lg transition-all ${
        isSelected
          ? 'ring-2 ring-blue-500 ring-offset-2'
          : 'hover:ring-1 hover:ring-gray-300'
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
        <Preview
          block={block}
          isSelected={isSelected}
          onUpdate={(data) => updateBlock(block.id, data)}
          onSelect={() => selectBlock(block.id)}
        />
      </div>

      {allowsChildren && (
        <DroppableZone id={`${block.id}-children`}>
          <SortableContext
            items={block.children.map((c) => c.id)}
            strategy={verticalListSortingStrategy}
            id={`sortable-${block.id}`}
          >
            <div className="p-2 space-y-2">
              {block.children.length === 0 ? (
                <div className="text-center py-4">
                  <p className="text-sm text-gray-400 mb-2">Drop blocks here</p>
                  {block.level === 'section' && (
                    <button
                      onClick={(e) => { e.stopPropagation(); addBlock('row', block.id); }}
                      className="px-3 py-1 text-xs bg-green-50 text-green-600 border border-green-200 rounded hover:bg-green-100 transition-colors"
                    >
                      + Add Row
                    </button>
                  )}
                  {block.level === 'row' && (
                    <button
                      onClick={(e) => { e.stopPropagation(); addBlock('column', block.id); }}
                      className="px-3 py-1 text-xs bg-purple-50 text-purple-600 border border-purple-200 rounded hover:bg-purple-100 transition-colors"
                    >
                      + Add Column
                    </button>
                  )}
                  {block.level === 'column' && (
                    <button
                      onClick={(e) => { e.stopPropagation(); addBlock('heading', block.id); }}
                      className="px-3 py-1 text-xs bg-blue-50 text-blue-600 border border-blue-200 rounded hover:bg-blue-100 transition-colors"
                    >
                      + Add Heading
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

      {!isSelected && (
        <div
          className="absolute top-2 left-2 opacity-0 group-hover:opacity-100 cursor-grab p-1 bg-white rounded shadow text-gray-400 hover:text-gray-600 transition-opacity"
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
