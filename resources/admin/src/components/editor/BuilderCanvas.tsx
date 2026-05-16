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
import { useState, useEffect, useCallback } from 'react';
import { Monitor, Tablet, Smartphone, LayoutList, Eye } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';
import { SortableBlock } from './SortableBlock';
import { WireframeBlock } from './WireframeBlock';
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
  const removeBlock = useEditorStore((s) => s.removeBlock);
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const undo = useEditorStore((s) => s.undo);
  const redo = useEditorStore((s) => s.redo);

  const [activeItem, setActiveItem] = useState<Active | null>(null);
  const [canvasDevice, setCanvasDevice] = useState<CanvasDevice>('desktop');
  const canvasMode = useEditorStore((s) => s.canvasMode);
  const setCanvasMode = useEditorStore((s) => s.setCanvasMode);

  // Keyboard shortcuts
  const handleKeyDown = useCallback((e: KeyboardEvent) => {
    // Skip when typing in input/textarea/contenteditable
    const target = e.target as HTMLElement;
    if (target.isContentEditable || target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT') return;

    // Ctrl+Shift+W — Wireframe mode
    if (e.ctrlKey && e.shiftKey && e.key === 'W') {
      e.preventDefault();
      setCanvasMode('wireframe');
      return;
    }
    // Ctrl+Shift+V — Visual mode
    if (e.ctrlKey && e.shiftKey && e.key === 'V') {
      e.preventDefault();
      setCanvasMode('visual');
      return;
    }
    // Ctrl+Z — Undo
    if (e.ctrlKey && !e.shiftKey && e.key === 'z') {
      e.preventDefault();
      undo();
      return;
    }
    // Ctrl+Shift+Z or Ctrl+Y — Redo
    if ((e.ctrlKey && e.shiftKey && e.key === 'Z') || (e.ctrlKey && e.key === 'y')) {
      e.preventDefault();
      redo();
      return;
    }
    // Delete or Backspace — remove selected block
    if ((e.key === 'Delete' || e.key === 'Backspace') && selectedBlockId) {
      e.preventDefault();
      removeBlock(selectedBlockId);
      return;
    }
  }, [setCanvasMode, undo, redo, removeBlock, selectedBlockId]);

  useEffect(() => {
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [handleKeyDown]);

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
        {/* Toolbar: Mode toggle + Responsive device toggle */}
        <div className="flex items-center justify-between py-2 px-4 bg-gray-50 border-b border-gray-200 sticky top-0 z-20">
          {/* Left: Editor mode toggle */}
          <div className="flex items-center gap-1 bg-gray-100 rounded-lg p-0.5">
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); setCanvasMode('visual'); }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
                canvasMode === 'visual'
                  ? 'bg-white text-gray-900 shadow-sm'
                  : 'text-gray-500 hover:text-gray-700'
              }`}
              title="Visual Mode — live rendered preview"
            >
              <Eye size={14} />
              <span>Visual</span>
            </button>
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); setCanvasMode('wireframe'); }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
                canvasMode === 'wireframe'
                  ? 'bg-white text-gray-900 shadow-sm'
                  : 'text-gray-500 hover:text-gray-700'
              }`}
              title="Wireframe Mode — structural outline"
            >
              <LayoutList size={14} />
              <span>Wireframe</span>
            </button>
          </div>

          {/* Right: Responsive device toggle (Visual mode only) */}
          <div className={`flex items-center gap-1 ${canvasMode === 'wireframe' ? 'opacity-40 pointer-events-none' : ''}`}>
            {([
              { device: 'desktop' as CanvasDevice, Icon: Monitor, label: 'Desktop' },
              { device: 'tablet' as CanvasDevice, Icon: Tablet, label: 'Tablet (768px)' },
              { device: 'mobile' as CanvasDevice, Icon: Smartphone, label: 'Mobile (375px)' },
            ]).map(({ device, Icon, label }) => (
              <button
                key={device}
                type="button"
                onClick={(e) => { e.stopPropagation(); setCanvasDevice(device); }}
                className={`flex items-center gap-1 px-2 py-1.5 rounded-md text-xs font-medium transition-colors ${
                  canvasDevice === device
                    ? 'bg-primary text-primary-content shadow-sm'
                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
                }`}
                title={label}
              >
                <Icon size={14} />
                <span className="hidden lg:inline">{label}</span>
              </button>
            ))}
          </div>
        </div>
        <div className="p-6">
        <div
          className="mx-auto bg-white rounded-xl shadow-sm border border-gray-200 min-h-[60vh] p-6 editor-canvas-light"
          style={{
            maxWidth: canvasWidths[canvasDevice],
            transition: 'max-width 0.3s ease',
          }}
        >
          {canvasMode === 'wireframe' ? (
            /* ── Wireframe Mode: structural outline ── */
            <div className="space-y-1">
              {blocks.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-16 text-gray-400">
                  <LayoutList className="w-12 h-12 mb-3 opacity-30" />
                  <p className="text-sm font-medium">No blocks yet</p>
                  <p className="text-xs mt-1">Add blocks from the sidebar</p>
                </div>
              ) : (
                blocks.map((block) => (
                  <WireframeBlock key={block.id} block={block} />
                ))
              )}
            </div>
          ) : (
            /* ── Visual Mode: live rendered preview ── */
            <SortableContext
              items={blocks.map((b) => b.id)}
              strategy={verticalListSortingStrategy}
            >
              <div className="space-y-3">
                {blocks.length === 0 ? (
                  <div className="flex flex-col items-center justify-center py-20 text-gray-400">
                    <svg className="w-16 h-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    <p className="text-lg font-medium">No blocks yet</p>
                    <p className="text-sm mt-1">Drag blocks from the sidebar or click to add</p>
                  </div>
                ) : (
                  <>
                    {blocks.map((block, i) => (
                      <div key={block.id}>
                        <SortableBlock block={block} />
                        {/* Insert point between sections */}
                        {i < blocks.length - 1 && (
                          <div className="flex justify-center py-1 group/insert">
                            <button
                              onClick={(e) => { e.stopPropagation(); addBlock('section', undefined, i + 1); }}
                              className="opacity-0 group-hover/insert:opacity-100 flex items-center gap-1 px-2 py-0.5 text-[10px] text-gray-400 hover:text-blue-500 border border-transparent hover:border-blue-200 rounded transition-all"
                              title="Insert section"
                            >
                              <span>+</span> Section
                            </button>
                          </div>
                        )}
                      </div>
                    ))}
                    {/* Add section at end */}
                    <div className="flex justify-center py-2">
                      <button
                        onClick={(e) => { e.stopPropagation(); addBlock('section'); }}
                        className="flex items-center gap-1 px-3 py-1.5 text-xs text-gray-400 hover:text-blue-500 border border-dashed border-gray-300 hover:border-blue-300 rounded-lg transition-all"
                      >
                        + Add Section
                      </button>
                    </div>
                  </>
                )}
              </div>
            </SortableContext>
          )}
        </div>
        </div>
      </div>

      <DragOverlay active={activeItem} />
    </DndContext>
  );
}
