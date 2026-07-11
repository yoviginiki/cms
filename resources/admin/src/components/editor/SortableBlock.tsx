import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import type { BlockData } from '@/types/blocks';
import { blockRegistry } from '@/components/blocks/registry';
import { useEditorStore } from '@/stores/editorStore';
import { BlockToolbar } from './BlockToolbar';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { useDroppable } from '@dnd-kit/core';
import { buildBlockWrapperStyle, buildAnimationStyle, buildBlockClasses, buildBackgroundFromData, buildOverlayFromData, safeDim } from '@/lib/blockStyles';
import type { Breakpoint } from '@/lib/breakpoints';
import { LAYOUT_GRID, type RowLayout } from '@/components/blocks/row/definition';
import { ModulePicker } from './ModulePicker';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { BlockContextMenu } from './BlockContextMenu';

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
        isOver ? 'border-2 border-dashed border-primary/40 bg-primary/5' : ''
      }`}
    >
      {children}
    </div>
  );
}

// Level-aware border accents
const LEVEL_ACCENTS: Record<string, string> = {
  section: 'border-l-blue-400',
  row: 'border-l-emerald-400',
  column: 'border-l-purple-400',
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
  const canvasDevice = useEditorStore((s) => s.canvasDevice) as Breakpoint;

  const [pickerOpen, setPickerOpen] = useState(false);
  const [menu, setMenu] = useState<{ x: number; y: number } | null>(null);
  const { siteId = '' } = useParams();

  const isSelected = selectedBlockId === block.id;
  const registration = blockRegistry.get(block.type);
  const hideOn = (block.responsive?.hideOn as string[]) || [];
  const isHiddenAtDevice = hideOn.includes(canvasDevice);

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
  const level = block.level || 'module';
  const levelAccent = LEVEL_ACCENTS[level] || '';
  const children = block.children ?? [];

  // Row children render in CSS grid matching layout preset
  const isRow = block.type === 'row';
  const blockData = block.data ?? {};
  const rowLayout = isRow ? ((blockData.layout as RowLayout) || '1/2+1/2') : undefined;
  const rowGap = isRow ? (safeDim(blockData.gap) || '16px') : undefined;
  const isMobileStack = isRow && canvasDevice === 'mobile';
  const childrenStyle: React.CSSProperties = isRow
    ? { display: 'grid', gridTemplateColumns: isMobileStack ? '1fr' : (LAYOUT_GRID[rowLayout!] || '1fr 1fr'), gap: rowGap }
    : {};

  // Determine what the "+" button does for this level
  const handleAddChild = () => {
    if (level === 'section') {
      addBlock('row', block.id);
    } else if (level === 'row') {
      addBlock('column', block.id);
    } else if (level === 'column') {
      setPickerOpen(true);
    }
  };

  const addLabel = level === 'section' ? 'Row' : level === 'row' ? 'Column' : 'Module';
  const addColor = level === 'section'
    ? 'text-emerald-600 hover:bg-emerald-50 hover:border-emerald-200'
    : level === 'row'
    ? 'text-purple-600 hover:bg-purple-50 hover:border-purple-200'
    : 'text-blue-600 hover:bg-blue-50 hover:border-blue-200';

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`relative group transition-all ${
        allowsChildren && levelAccent ? `border-l-2 ${levelAccent}` : ''
      } ${
        isSelected
          ? 'ring-2 ring-blue-500 ring-offset-2 rounded-lg'
          : 'hover:outline hover:outline-1 hover:outline-blue-200 hover:outline-offset-2 rounded-lg'
      } ${isHiddenAtDevice ? 'opacity-25 outline-dashed outline-1 outline-warning/50' : ''}`}
      onClick={(e) => {
        e.stopPropagation();
        selectBlock(block.id);
      }}
      onContextMenu={(e) => {
        e.preventDefault();
        e.stopPropagation();
        selectBlock(block.id);
        setMenu({ x: e.clientX, y: e.clientY });
      }}
    >
      {menu && (
        <BlockContextMenu block={block} x={menu.x} y={menu.y} siteId={siteId} onClose={() => setMenu(null)} />
      )}
      {isHiddenAtDevice && (
        <div className="absolute top-1 right-1 z-10 text-[8px] bg-warning/20 text-warning px-1.5 py-0.5 rounded font-medium">
          Hidden on {canvasDevice}
        </div>
      )}
      {isSelected && (
        <BlockToolbar
          block={block}
          dragHandleProps={{ ...attributes, ...listeners }}
        />
      )}

      <div
        className={`relative ${buildBlockClasses(block.advanced, block.animation)}`}
        style={{
          ...buildBlockWrapperStyle(block.style, block.responsive, canvasDevice),
          ...buildBackgroundFromData(block.data),
          ...buildAnimationStyle(block.animation),
        }}
        {...(block.advanced?.htmlId ? { id: block.advanced.htmlId.replace(/[^a-zA-Z0-9_-]/g, '') } : {})}
        {...(block.advanced?.ariaLabel ? { 'aria-label': block.advanced.ariaLabel } : {})}
      >
        {/* Color overlay for background images */}
        {(() => {
          const overlayStyle = buildOverlayFromData(block.data);
          return overlayStyle ? <div style={overlayStyle} /> : null;
        })()}

        {Array.isArray(block.responsive?.hideOn) && block.responsive.hideOn.length > 0 && (
          <div className="absolute top-1 right-1 z-20 flex gap-0.5">
            {block.responsive.hideOn.map((device) => (
              <span key={device} className="px-1 py-0.5 bg-warning/80 text-warning-content text-[9px] rounded font-medium">
                Hidden on {device}
              </span>
            ))}
          </div>
        )}

        {/* Content layer — sits above overlay */}
        <div className="relative z-[1]">
          {/* Render Preview — for containers, only when empty */}
          {(!allowsChildren || children.length === 0) && (
            <Preview
              block={block}
              isSelected={isSelected}
              onUpdate={(data) => updateBlock(block.id, data)}
              onSelect={() => selectBlock(block.id)}
            />
          )}

          {allowsChildren && (
            <DroppableZone id={`${block.id}-children`}>
              <SortableContext
                items={children.map((c) => c.id)}
                strategy={verticalListSortingStrategy}
                id={`sortable-${block.id}`}
              >
                <div style={childrenStyle} className={isRow ? 'min-h-[40px]' : 'space-y-1'}>
                  {children.length === 0 && (
                    <div className="text-center py-6 col-span-full">
                      <p className="text-xs text-base-content/30 mb-2">
                        {level === 'section' ? 'Add rows to build your layout' :
                         level === 'row' ? 'Add columns to this row' :
                         level === 'column' ? 'Add modules to this column' :
                         'Drop blocks here'}
                      </p>
                    </div>
                  )}

                  {children.map((child) => (
                    <SortableBlock key={child.id} block={child} depth={depth + 1} />
                  ))}

                  {/* Persistent "+" button — always visible */}
                  <div className="flex justify-center py-1 col-span-full">
                    <button
                      onClick={(e) => { e.stopPropagation(); handleAddChild(); }}
                      className={`inline-flex items-center gap-1 px-3 py-1.5 text-xs border border-transparent rounded-lg transition-colors ${addColor}`}
                    >
                      <Plus size={14} />
                      {addLabel}
                    </button>
                  </div>
                </div>
              </SortableContext>
            </DroppableZone>
          )}
        </div>
      </div>

      {/* Module picker modal for columns */}
      {pickerOpen && (
        <ModulePicker
          parentId={block.id}
          onClose={() => setPickerOpen(false)}
        />
      )}

      {/* Drag handle (visible on hover when not selected) */}
      {!isSelected && (
        <div
          className="absolute top-2 left-2 opacity-0 group-hover:opacity-100 cursor-grab p-1 bg-base-100 rounded shadow text-base-content/30 hover:text-base-content/60 transition-opacity z-10"
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
