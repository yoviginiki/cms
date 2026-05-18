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
import { Monitor, Tablet, Smartphone, LayoutList, Eye, PanelTop, Plus, Code } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';
import { SortableBlock } from './SortableBlock';
import { WireframeBlock } from './WireframeBlock';
import { DragOverlay } from './DragOverlay';
import { BlockIcon } from './BlockIcon';
import { presets as presetsList } from '@/presets';
import type { Active } from '@dnd-kit/core';

/**
 * HTML editor with two sub-tabs:
 * - "Raw HTML" — custom scripts/embeds preserved on publish
 * - "Block JSON" — view/export/import the current block tree
 */
function HtmlEditor() {
  const rawHtml = useEditorStore((s) => s.rawHtml);
  const setRawHtml = useEditorStore((s) => s.setRawHtml);
  const blocks = useEditorStore((s) => s.blocks);
  const setBlocks = useEditorStore((s) => s.setBlocks);
  const [subTab, setSubTab] = useState<'raw' | 'json'>('json');
  const [jsonText, setJsonText] = useState('');
  const [jsonError, setJsonError] = useState('');

  // Sync blocks to JSON text when switching to json tab
  useEffect(() => {
    if (subTab === 'json') {
      setJsonText(JSON.stringify(blocks, null, 2));
      setJsonError('');
    }
  }, [subTab, blocks]);

  const handleJsonApply = () => {
    try {
      const parsed = JSON.parse(jsonText);
      if (!Array.isArray(parsed)) { setJsonError('Must be a JSON array'); return; }
      setBlocks(parsed);
      useEditorStore.setState({ isDirty: true });
      setJsonError('');
    } catch (e: any) {
      setJsonError(e.message);
    }
  };

  return (
    <div className="flex flex-col h-full min-h-[60vh]">
      {/* Sub-tabs */}
      <div className="flex items-center bg-gray-800 rounded-t-lg">
        <button
          onClick={() => setSubTab('json')}
          className={`flex items-center gap-1.5 px-4 py-2 text-xs font-medium transition-colors ${
            subTab === 'json' ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-gray-200'
          }`}
        >
          <Code size={12} /> Block JSON
        </button>
        <button
          onClick={() => setSubTab('raw')}
          className={`flex items-center gap-1.5 px-4 py-2 text-xs font-medium transition-colors ${
            subTab === 'raw' ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-gray-200'
          }`}
        >
          <Code size={12} /> Raw HTML
        </button>
        <span className="text-[10px] text-gray-500 ml-auto pr-3">
          {subTab === 'json' ? 'Edit blocks as JSON — click Apply to save' : 'Content preserved exactly on publish'}
        </span>
      </div>

      {subTab === 'json' ? (
        <>
          <textarea
            value={jsonText}
            onChange={(e) => setJsonText(e.target.value)}
            className="flex-1 w-full p-4 font-mono text-xs bg-gray-900 text-blue-300 border-0 resize-none focus:outline-none"
            spellCheck={false}
          />
          <div className="flex items-center gap-2 px-3 py-2 bg-gray-800 rounded-b-lg">
            {jsonError && <span className="text-[10px] text-red-400 flex-1">{jsonError}</span>}
            <button
              onClick={handleJsonApply}
              className="ml-auto px-3 py-1 text-xs font-medium bg-blue-600 text-white rounded hover:bg-blue-700"
            >
              Apply JSON
            </button>
          </div>
        </>
      ) : (
        <textarea
          value={rawHtml}
          onChange={(e) => setRawHtml(e.target.value)}
          className="flex-1 w-full p-4 font-mono text-sm bg-gray-900 text-green-300 border-0 rounded-b-lg resize-none focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder={`<!-- Paste your HTML, scripts, embeds here -->\n<div class="custom-section">\n  <h1>Hello World</h1>\n  <script>console.log('works!')</script>\n</div>`}
          spellCheck={false}
        />
      )}
    </div>
  );
}

/**
 * Shared DnD provider — wraps both canvas and sidebar so drag from panel works.
 */
export function BuilderDndProvider({ children }: { children: React.ReactNode }) {
  const moveBlock = useEditorStore((s) => s.moveBlock);
  const addBlock = useEditorStore((s) => s.addBlock);
  const selectBlock = useEditorStore((s) => s.selectBlock);
  const [activeItem, setActiveItem] = useState<Active | null>(null);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
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
      if (overId.endsWith('-children')) {
        // Dropped on a container's children zone
        const parentId = overId.replace('-children', '');
        addBlock(blockType, parentId);
      } else {
        // Dropped on an existing block — if it's a container, add inside it
        const overData = over.data.current;
        if (overData?.type === 'block' && overData.block) {
          const overBlock = overData.block as { id: string; level?: string; children?: unknown[] };
          const isContainer = overBlock.level === 'section' || overBlock.level === 'row' || overBlock.level === 'column';
          if (isContainer) {
            addBlock(blockType, overBlock.id);
          } else {
            // Module-level target: let store auto-resolve parent from selection
            selectBlock(overBlock.id);
            addBlock(blockType);
          }
        } else {
          addBlock(blockType);
        }
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
      {children}
      <DragOverlay active={activeItem} />
    </DndContext>
  );
}

type CanvasDevice = 'desktop' | 'tablet' | 'mobile';
const canvasWidths: Record<CanvasDevice, string> = {
  desktop: '100%',
  tablet: '768px',
  mobile: '375px',
};

export function BuilderCanvas() {
  const blocks = useEditorStore((s) => s.blocks);
  const addBlock = useEditorStore((s) => s.addBlock);
  const addPresetAction = useEditorStore((s) => s.addPreset);
  const selectBlock = useEditorStore((s) => s.selectBlock);
  const removeBlock = useEditorStore((s) => s.removeBlock);
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const undo = useEditorStore((s) => s.undo);
  const redo = useEditorStore((s) => s.redo);

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
  }, [setCanvasMode, undo, redo, removeBlock, selectedBlockId, canvasDevice]);

  useEffect(() => {
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [handleKeyDown]);

  return (
    <>
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
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); setCanvasMode('html'); }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
                canvasMode === 'html'
                  ? 'bg-white text-gray-900 shadow-sm'
                  : 'text-gray-500 hover:text-gray-700'
              }`}
              title="HTML Mode — raw code editor"
            >
              <Code size={14} />
              <span>HTML</span>
            </button>
          </div>

          {/* Right: Responsive device toggle (Visual mode only) */}
          <div className={`flex items-center gap-1 ${canvasMode !== 'visual' ? 'opacity-40 pointer-events-none' : ''}`}>
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
                <span className="hidden sm:inline text-[11px]">{device === 'desktop' ? 'Desktop' : device === 'tablet' ? 'Tablet' : 'Mobile'}</span>
              </button>
            ))}
          </div>
        </div>
        {canvasDevice !== 'desktop' && (
          <div className="flex items-center justify-center gap-2 py-1.5 bg-info/10 border-b border-info/20 text-info text-xs font-medium">
            <Eye size={12} /> Editing at {canvasDevice === 'tablet' ? '768px' : '375px'} width
          </div>
        )}
        <div className="p-6">
        <div
          className={`mx-auto bg-white rounded-xl shadow-sm border min-h-[60vh] p-6 editor-canvas-light ${
            canvasDevice !== 'desktop' ? 'border-info/30' : 'border-gray-200'
          }`}
          style={{
            maxWidth: canvasWidths[canvasDevice],
            transition: 'max-width 0.3s ease',
          }}
        >
          {canvasMode === 'html' ? (
            /* ── HTML Mode: raw code editor ── */
            <HtmlEditor />
          ) : canvasMode === 'wireframe' ? (
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
                          <div className="flex justify-center py-2">
                            <button
                              onClick={(e) => { e.stopPropagation(); addBlock('section', undefined, i + 1); }}
                              className="flex items-center gap-1 px-3 py-1.5 text-xs text-gray-400 hover:text-blue-500 border border-dashed border-gray-300 hover:border-blue-300 rounded-lg transition-colors"
                              title="Insert section"
                            >
                              <Plus size={12} /> Section
                            </button>
                          </div>
                        )}
                      </div>
                    ))}
                    {/* Add section at end — with preset options */}
                    <div className="flex flex-wrap justify-center gap-2 py-4">
                      <button
                        onClick={(e) => { e.stopPropagation(); addBlock('section'); }}
                        className="flex items-center gap-2 px-5 py-2.5 text-sm text-blue-500 hover:text-blue-600 border-2 border-dashed border-blue-200 hover:border-blue-400 rounded-xl transition-all hover:bg-blue-50"
                      >
                        <PanelTop size={16} />
                        Blank Section
                      </button>
                      {presetsList.map(p => (
                        <button
                          key={p.type}
                          onClick={(e) => { e.stopPropagation(); addPresetAction(p.type); }}
                          className="flex items-center gap-1.5 px-4 py-2.5 text-xs text-purple-500 hover:text-purple-600 border border-dashed border-purple-200 hover:border-purple-400 rounded-xl transition-all hover:bg-purple-50"
                        >
                          <BlockIcon icon={p.icon} size={14} className="text-purple-400" />
                          {p.label}
                        </button>
                      ))}
                    </div>
                  </>
                )}
              </div>
            </SortableContext>
          )}
        </div>
        </div>
      </div>
    </>
  );
}
