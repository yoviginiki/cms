import {
  DndContext,
  closestCenter,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { useState } from 'react';
import { Monitor, Tablet, Smartphone } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';
import { SortableBlock } from './SortableBlock';
import { DragOverlay } from './DragOverlay';
import type { Active } from '@dnd-kit/core';

type CanvasDevice = 'desktop' | 'tablet' | 'mobile';
const canvasWidths: Record<CanvasDevice, string> = {
  desktop: '100%',
  tablet: '768px',
  mobile: '375px',
};

export function BuilderCanvas() {
  const blocks = useEditorStore((s) => s.blocks);
  const moveBlock = useEditorStore((s) => s.moveBlock);
  const addBlock = useEditorStore((s) => s.addBlock);
  const selectBlock = useEditorStore((s) => s.selectBlock);

  const [activeItem, setActiveItem] = useState<Active | null>(null);
  const [canvasDevice, setCanvasDevice] = useState<CanvasDevice>('desktop');

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: { distance: 8 },
    }),
  );

  function handleDragStart(event: DragStartEvent) {
    setActiveItem(event.active);
  }

  function handleDragEnd(event: DragEndEvent) {
    setActiveItem(null);
    const { active, over } = event;
    if (!over || active.id === over.id) return;

    const activeData = active.data.current;
    const overId = over.id as string;

    // Dragging new block from sidebar
    if (activeData?.type === 'new-block') {
      const blockType = activeData.blockType as string;
      // Determine if dropping into a container
      if (overId.endsWith('-children')) {
        const parentId = overId.replace('-children', '');
        addBlock(blockType, parentId);
      } else {
        addBlock(blockType);
      }
      return;
    }

    // Reordering existing blocks
    if (activeData?.type === 'block') {
      if (overId.endsWith('-children')) {
        const parentId = overId.replace('-children', '');
        moveBlock(active.id as string, parentId, 'inside');
      } else {
        moveBlock(active.id as string, overId, 'after');
      }
    }
  }

  return (
    <DndContext
      sensors={sensors}
      collisionDetection={closestCenter}
      onDragStart={handleDragStart}
      onDragEnd={handleDragEnd}
    >
      <div
        className="flex-1 overflow-y-auto bg-gray-50"
        onClick={() => selectBlock(null)}
      >
        {/* Responsive canvas device toggle */}
        <div className="flex items-center justify-center gap-1 py-2 bg-gray-50 border-b border-gray-200 sticky top-0 z-20">
          {([
            { device: 'desktop' as CanvasDevice, Icon: Monitor, label: 'Desktop' },
            { device: 'tablet' as CanvasDevice, Icon: Tablet, label: 'Tablet (768px)' },
            { device: 'mobile' as CanvasDevice, Icon: Smartphone, label: 'Mobile (375px)' },
          ]).map(({ device, Icon, label }) => (
            <button
              key={device}
              type="button"
              onClick={(e) => { e.stopPropagation(); setCanvasDevice(device); }}
              className={`flex items-center gap-1 px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${
                canvasDevice === device
                  ? 'bg-primary text-primary-content shadow-sm'
                  : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
              }`}
              title={label}
            >
              <Icon size={14} />
              <span className="hidden sm:inline">{label}</span>
            </button>
          ))}
        </div>
        <div className="p-6">
        <div
          className="mx-auto bg-white rounded-xl shadow-sm border border-gray-200 min-h-[60vh] p-6 editor-canvas-light"
          style={{
            maxWidth: canvasWidths[canvasDevice],
            transition: 'max-width 0.3s ease',
          }}
        >
          <SortableContext
            items={blocks.map((b) => b.id)}
            strategy={verticalListSortingStrategy}
          >
            <div className="space-y-3">
              {blocks.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-20 text-gray-400">
                  <svg
                    className="w-16 h-16 mb-4"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={1}
                      d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                    />
                  </svg>
                  <p className="text-lg font-medium">No blocks yet</p>
                  <p className="text-sm mt-1">
                    Drag blocks from the sidebar or click to add
                  </p>
                </div>
              ) : (
                blocks.map((block) => (
                  <SortableBlock key={block.id} block={block} />
                ))
              )}
            </div>
          </SortableContext>
        </div>
        </div>
      </div>

      <DragOverlay active={activeItem} />
    </DndContext>
  );
}
