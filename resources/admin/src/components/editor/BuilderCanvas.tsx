import {
  DndContext,
  closestCenter,
  PointerSensor,
  TouchSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { useParams } from 'react-router-dom';
import { Monitor, Tablet, Smartphone, LayoutList, Eye, Plus, Code, FileText, Sparkles, Replace } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';
import { SortableBlock } from './SortableBlock';
import { WireframeBlock } from './WireframeBlock';
import { DragOverlay } from './DragOverlay';
import { BlockIcon } from './BlockIcon';
import { PresetBrowser } from './PresetBrowser';
import { BulkActionBar } from './BulkActionBar';
import { FindReplacePanel } from './FindReplacePanel';
import { useStylePresets } from '@/lib/presetResolve';
import WysiwygEditor from './WysiwygEditor';
import { blockRegistry } from '@/components/blocks/registry';
import '@/components/blocks';
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
    useSensor(TouchSensor, { activationConstraint: { delay: 200, tolerance: 5 } }),
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

import { CANVAS_WIDTHS, type Breakpoint } from '@/lib/breakpoints';
type CanvasDevice = Breakpoint;

export function BuilderCanvas({ pageStyle }: { pageStyle?: Record<string, any> }) {
  const { siteId } = useParams<{ siteId: string }>();
  const blocks = useEditorStore((s) => s.blocks);
  const addBlock = useEditorStore((s) => s.addBlock);
  const addPresetAction = useEditorStore((s) => s.addPreset);
  const selectBlock = useEditorStore((s) => s.selectBlock);
  const removeBlock = useEditorStore((s) => s.removeBlock);
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const undo = useEditorStore((s) => s.undo);
  const redo = useEditorStore((s) => s.redo);
  const undoCount = useEditorStore((s) => s.undoStack.length);
  const redoCount = useEditorStore((s) => s.redoStack.length);
  const [findOpen, setFindOpen] = useState(false);

  // P3: feed per-block-type default presets into the store so new blocks auto-link them.
  const { siteId: siteIdForPresets = '' } = useParams();
  const stylePresetList = useStylePresets(siteIdForPresets);
  const setDefaultPresets = useEditorStore((s) => s.setDefaultPresets);
  useEffect(() => {
    const map: Record<string, string> = {};
    for (const p of stylePresetList) {
      if (p.kind === 'element' && p.is_default && p.block_type !== '*') map[p.block_type] = p.id;
    }
    setDefaultPresets(map);
  }, [stylePresetList, setDefaultPresets]);
  const copyBlock = useEditorStore((s) => s.copyBlock);
  const pasteBlock = useEditorStore((s) => s.pasteBlock);
  const clipboard = useEditorStore((s) => s.clipboard);

  const canvasDevice = useEditorStore((s) => s.canvasDevice);
  const setCanvasDevice = useEditorStore((s) => s.setCanvasDevice);
  const canvasMode = useEditorStore((s) => s.canvasMode);
  const setCanvasMode = useEditorStore((s) => s.setCanvasMode);

  const [showAddPopup, setShowAddPopup] = useState(false);
  const [presetBrowserOpen, setPresetBrowserOpen] = useState(false);
  const [showShortcuts, setShowShortcuts] = useState(false);

  // Simple editor mode — stores content as HTML, converts to rich-text block when leaving simple mode
  const [simpleContent, setSimpleContent] = useState(() => {
    // Initialize from first rich-text block if one exists
    const firstRichText = blocks.flatMap(function findRichText(b: any): any[] {
      if (b.type === 'rich-text') return [b];
      return (b.children || []).flatMap(findRichText);
    })[0];
    return (firstRichText?.data?.content as string) || '';
  });

  // When switching away from simple mode, save content as a rich-text block
  const prevCanvasModeRef = useRef(canvasMode);
  useEffect(() => {
    if (prevCanvasModeRef.current === 'simple' && canvasMode !== 'simple' && simpleContent && simpleContent !== '<p></p>') {
      // Find existing rich-text block or create one
      const findRichText = (bs: any[]): any => {
        for (const b of bs) {
          if (b.type === 'rich-text') return b;
          const found = findRichText(b.children || []);
          if (found) return found;
        }
        return null;
      };
      const existing = findRichText(blocks);
      if (existing) {
        // Update existing rich-text block
        const { updateBlock } = useEditorStore.getState();
        if (updateBlock) updateBlock(existing.id, { content: simpleContent });
      } else {
        // Create a new rich-text block with the content
        addBlock('rich-text');
        // After creation, update the newest rich-text block
        setTimeout(() => {
          const state = useEditorStore.getState();
          const newest = findRichText(state.blocks);
          if (newest && state.updateBlock) {
            state.updateBlock(newest.id, { content: simpleContent });
          }
        }, 50);
      }
    }
    prevCanvasModeRef.current = canvasMode;
  }, [canvasMode]);

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
    // Ctrl+C — Copy block
    if (e.ctrlKey && !e.shiftKey && e.key === 'c' && selectedBlockId) {
      e.preventDefault();
      copyBlock(selectedBlockId);
      return;
    }
    // Ctrl+V — Paste block (no shift — shift+V is visual mode)
    if (e.ctrlKey && !e.shiftKey && e.key === 'v' && clipboard) {
      e.preventDefault();
      pasteBlock(selectedBlockId || undefined);
      return;
    }
    // ? — Show shortcuts help
    if (e.key === '?' && !e.ctrlKey) {
      e.preventDefault();
      setShowShortcuts(s => !s);
      return;
    }
    // Delete or Backspace — remove selected block
    if ((e.key === 'Delete' || e.key === 'Backspace') && selectedBlockId) {
      e.preventDefault();
      removeBlock(selectedBlockId);
      return;
    }
  }, [setCanvasMode, undo, redo, removeBlock, selectedBlockId, canvasDevice, copyBlock, pasteBlock, clipboard]);

  useEffect(() => {
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [handleKeyDown]);

  return (
    <>
      <BulkActionBar />
      <FindReplacePanel open={findOpen} onClose={() => setFindOpen(false)} />
      <div
        className="flex-1 overflow-y-auto bg-base-200/50"
        onClick={() => selectBlock(null)}
      >
        {/* Toolbar: Mode toggle + Responsive device toggle */}
        <div className="flex items-center justify-between py-2 px-4 bg-base-200/50 border-b border-base-300/30 sticky top-0 z-20">
          {/* Left: Editor mode toggle */}
          <div className="flex items-center gap-1 bg-base-300/30 rounded-lg p-0.5">
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); setCanvasMode('visual'); }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
                canvasMode === 'visual'
                  ? 'bg-base-100 text-base-content shadow-sm'
                  : 'text-base-content/40 hover:text-base-content/70'
              }`}
              title="Visual Mode — live rendered preview"
            >
              <Eye size={14} />
              <span className="hidden sm:inline">Visual</span>
            </button>
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); setCanvasMode('wireframe'); }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
                canvasMode === 'wireframe'
                  ? 'bg-base-100 text-base-content shadow-sm'
                  : 'text-base-content/40 hover:text-base-content/70'
              }`}
              title="Wireframe Mode — structural outline"
            >
              <LayoutList size={14} />
              <span className="hidden sm:inline">Wireframe</span>
            </button>
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); setCanvasMode('html'); }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
                canvasMode === 'html'
                  ? 'bg-base-100 text-base-content shadow-sm'
                  : 'text-base-content/40 hover:text-base-content/70'
              }`}
              title="HTML Mode — raw code editor"
            >
              <Code size={14} />
              <span className="hidden sm:inline">HTML</span>
            </button>
            <button
              type="button"
              onClick={(e) => { e.stopPropagation(); setCanvasMode('simple'); }}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
                canvasMode === 'simple'
                  ? 'bg-base-100 text-base-content shadow-sm'
                  : 'text-base-content/40 hover:text-base-content/70'
              }`}
              title="Simple Editor — just type, like a classic editor"
            >
              <FileText size={14} />
              <span className="hidden sm:inline">Simple</span>
            </button>
          </div>

          {/* Undo / Redo */}
          <div className="flex items-center gap-0.5">
            <button onClick={undo} disabled={undoCount === 0} className="flex items-center gap-0.5 px-1.5 py-1.5 rounded-md text-xs text-base-content/40 hover:text-base-content/70 hover:bg-base-300/30 disabled:opacity-30" title={`Undo (${undoCount})`}>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
              {undoCount > 0 && <span className="text-[9px] text-base-content/30">{undoCount}</span>}
            </button>
            <button onClick={redo} disabled={redoCount === 0} className="flex items-center gap-0.5 px-1.5 py-1.5 rounded-md text-xs text-base-content/40 hover:text-base-content/70 hover:bg-base-300/30 disabled:opacity-30" title={`Redo (${redoCount})`}>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3L21 13"/></svg>
              {redoCount > 0 && <span className="text-[9px] text-base-content/30">{redoCount}</span>}
            </button>
            <button onClick={(e) => { e.stopPropagation(); setFindOpen(true); }} className="flex items-center gap-0.5 px-1.5 py-1.5 rounded-md text-xs text-base-content/40 hover:text-base-content/70 hover:bg-base-300/30" title="Find & Replace colors">
              <Replace size={14} />
            </button>
          </div>

          {/* Right: Responsive device toggle (Visual mode only) */}
          <div className={`flex items-center gap-1 ${canvasMode !== 'visual' ? 'opacity-40 pointer-events-none' : ''}`}>
            {([
              { device: 'desktop' as CanvasDevice, Icon: Monitor, label: 'Desktop' },
              { device: 'tablet' as CanvasDevice, Icon: Tablet, label: 'Tablet (768px)' },
              { device: 'mobile' as CanvasDevice, Icon: Smartphone, label: 'Mobile (390px)' },
            ]).map(({ device, Icon, label }) => (
              <button
                key={device}
                type="button"
                onClick={(e) => { e.stopPropagation(); setCanvasDevice(device); }}
                className={`flex items-center gap-1 px-2 py-1.5 rounded-md text-xs font-medium transition-colors ${
                  canvasDevice === device
                    ? 'bg-primary text-primary-content shadow-sm'
                    : 'text-base-content/40 hover:text-base-content/70 hover:bg-base-300/30'
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
            <Eye size={12} /> Editing at {canvasDevice === 'tablet' ? '768px' : '390px'} width
          </div>
        )}
        <div className="p-2 sm:p-4 lg:p-6">
        <div
          className={`mx-auto bg-base-100 rounded-xl shadow-sm border min-h-[60vh] p-3 sm:p-4 lg:p-6 editor-canvas-light ${
            canvasDevice !== 'desktop' ? 'border-info/30' : 'border-base-300/30'
          }`}
          style={{
            // Page style applied first, device maxWidth overrides if smaller
            ...(pageStyle?.layout?.width ? { width: pageStyle.layout.width } : {}),
            ...(pageStyle?.layout?.minWidth ? { minWidth: pageStyle.layout.minWidth } : {}),
            ...(pageStyle?.layout?.height ? { minHeight: pageStyle.layout.height } : {}),
            ...(pageStyle?.layout?.overflow ? { overflow: pageStyle.layout.overflow } : {}),
            ...(pageStyle?.spacing?.paddingTop ? { paddingTop: pageStyle.spacing.paddingTop } : {}),
            ...(pageStyle?.spacing?.paddingBottom ? { paddingBottom: pageStyle.spacing.paddingBottom } : {}),
            ...(pageStyle?.spacing?.paddingLeft ? { paddingLeft: pageStyle.spacing.paddingLeft } : {}),
            ...(pageStyle?.spacing?.paddingRight ? { paddingRight: pageStyle.spacing.paddingRight } : {}),
            // Device maxWidth always wins over page maxWidth
            maxWidth: CANVAS_WIDTHS[canvasDevice],
            transition: 'max-width 0.3s ease',
          }}
        >
          {canvasMode === 'simple' ? (
            /* ── Simple Mode: just type like a classic editor ── */
            <WysiwygEditor
              content={simpleContent}
              onChange={setSimpleContent}
              minHeight={400}
              placeholder="Just start typing... headings, paragraphs, lists, links — everything works. When you switch to Visual or Wireframe mode, your content becomes a rich-text block."
            />
          ) : canvasMode === 'html' ? (
            /* ── HTML Mode: raw code editor ── */
            <HtmlEditor />
          ) : canvasMode === 'wireframe' ? (
            /* ── Wireframe Mode: structural outline ── */
            <div className="space-y-1">
              {blocks.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-16 text-base-content/30">
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
                  <div className="flex flex-col items-center justify-center py-16 text-base-content/30">
                    <div className="w-16 h-16 rounded-2xl bg-base-200/80 flex items-center justify-center mb-4">
                      <Plus size={28} className="text-base-content/20" />
                    </div>
                    <p className="text-base font-medium text-base-content/50 mb-1">Add your first section</p>
                    <p className="text-xs text-base-content/30 mb-5">Start building your page with a pre-built section or blank block</p>
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); setPresetBrowserOpen(true); }}
                        className="btn btn-primary btn-sm gap-1.5"
                      >
                        <Sparkles size={14} /> Section Library
                      </button>
                      <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); addBlock('section'); }}
                        className="btn btn-ghost btn-sm gap-1.5 text-base-content/50"
                      >
                        <Plus size={14} /> Blank Section
                      </button>
                    </div>
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
                              className="flex items-center gap-1 px-3 py-1.5 text-xs text-base-content/30 hover:text-primary border border-dashed border-base-300/40 hover:border-primary/40 rounded-lg transition-colors"
                              title="Insert section"
                            >
                              <Plus size={12} /> Section
                            </button>
                          </div>
                        )}
                      </div>
                    ))}
                    {/* Add section at end */}
                    <div className="flex justify-center py-4">
                      <button
                        onClick={(e) => { e.stopPropagation(); setPresetBrowserOpen(true); }}
                        className="flex items-center gap-2 px-6 py-3 text-sm text-primary hover:text-primary border-2 border-dashed border-primary/20 hover:border-primary/40 rounded-xl transition-all hover:bg-primary/5 hover:shadow-md"
                      >
                        <Plus size={18} />
                        Add Section
                      </button>
                    </div>
                    <PresetBrowser
                      open={presetBrowserOpen}
                      onClose={() => setPresetBrowserOpen(false)}
                      onSelectPreset={(type) => addPresetAction(type)}
                      onAddBlank={() => addBlock('section')}
                      siteId={siteId}
                    />
                  </>
                )}
              </div>
            </SortableContext>
          )}
        </div>
        </div>
      </div>

      {/* Floating + button to add blocks (visible when blocks exist too) */}
      {!showAddPopup && (
        <button
          type="button"
          onClick={(e) => { e.stopPropagation(); setShowAddPopup(true); }}
          className="fixed bottom-20 right-4 z-30 w-12 h-12 rounded-full bg-primary text-primary-content shadow-lg flex items-center justify-center text-2xl hover:scale-110 transition-transform lg:bottom-4"
          title="Add block"
        >
          +
        </button>
      )}

      {/* Add block popup */}
      {showAddPopup && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" onClick={() => setShowAddPopup(false)}>
          <div className="absolute inset-0 bg-black/30" />
          <div className="relative bg-base-100 rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between px-4 py-3 border-b border-base-300/20">
              <h3 className="font-medium text-base-content">Add Block</h3>
              <button onClick={() => setShowAddPopup(false)} className="text-base-content/40 hover:text-base-content text-xl px-2">✕</button>
            </div>

            {/* Quick add */}
            <div className="px-4 py-3 border-b border-base-300/10 flex flex-wrap gap-2">
              <button onClick={() => { addBlock('rich-text'); setShowAddPopup(false); }}
                className="btn btn-sm btn-outline gap-1">📝 Simple Text</button>
              <button onClick={() => { addBlock('hero'); setShowAddPopup(false); }}
                className="btn btn-sm btn-outline gap-1">🖼 Hero</button>
              <button onClick={() => { addBlock('image'); setShowAddPopup(false); }}
                className="btn btn-sm btn-outline gap-1">📷 Image</button>
              <button onClick={() => { addBlock('section'); setShowAddPopup(false); }}
                className="btn btn-sm btn-outline gap-1">📦 Section</button>
              <button onClick={() => { addBlock('heading'); setShowAddPopup(false); }}
                className="btn btn-sm btn-outline gap-1">H Heading</button>
            </div>

            {/* Full block picker */}
            <div className="flex-1 overflow-y-auto">
              <BlockPickerClickable onAdd={(type) => { addBlock(type); setShowAddPopup(false); }} />
            </div>
          </div>
        </div>
      )}
      {/* Keyboard shortcuts help */}
      {showShortcuts && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={() => setShowShortcuts(false)}>
          <div className="bg-base-100 rounded-xl shadow-2xl w-80 p-5" onClick={e => e.stopPropagation()}>
            <h3 className="text-sm font-semibold mb-3">Keyboard Shortcuts</h3>
            <div className="space-y-1.5 text-xs">
              {[
                ['Ctrl+Z', 'Undo'], ['Ctrl+Shift+Z', 'Redo'],
                ['Ctrl+C', 'Copy block'], ['Ctrl+V', 'Paste block'],
                ['Delete', 'Remove block'], ['Ctrl+Shift+W', 'Wireframe mode'],
                ['Ctrl+Shift+V', 'Visual mode'], ['?', 'Show this help'],
              ].map(([key, desc]) => (
                <div key={key} className="flex justify-between">
                  <kbd className="px-1.5 py-0.5 bg-base-200 rounded text-[10px] font-mono">{key}</kbd>
                  <span className="text-base-content/60">{desc}</span>
                </div>
              ))}
            </div>
            <button onClick={() => setShowShortcuts(false)} className="btn btn-sm btn-ghost w-full mt-3 text-xs">Close</button>
          </div>
        </div>
      )}
    </>
  );
}

/** Click-only block picker for popup — no drag, just tap to add */
function BlockPickerClickable({ onAdd }: { onAdd: (type: string) => void }) {
  const [search, setSearch] = useState('');
  const allBlocks = useMemo(() => {
    const result: Array<{ type: string; label: string; category: string; description: string; icon: string }> = [];
    for (const [, reg] of blockRegistry.getAll()) {
      const d = reg.definition;
      result.push({ type: d.type, label: d.label, category: d.category, description: d.description || '', icon: d.icon });
    }
    return result;
  }, []);

  const filtered = search
    ? allBlocks.filter(b => b.label.toLowerCase().includes(search.toLowerCase()) || b.type.includes(search.toLowerCase()))
    : allBlocks;

  const grouped = useMemo(() => {
    const map = new Map<string, typeof filtered>();
    for (const b of filtered) {
      if (!map.has(b.category)) map.set(b.category, []);
      map.get(b.category)!.push(b);
    }
    return map;
  }, [filtered]);

  return (
    <div className="p-3 space-y-3">
      <input
        type="text"
        value={search}
        onChange={e => setSearch(e.target.value)}
        placeholder="Search blocks..."
        className="input input-bordered input-sm w-full"
        autoFocus
      />
      {Array.from(grouped.entries()).map(([cat, blocks]) => (
        <div key={cat}>
          <div className="text-[9px] font-semibold uppercase tracking-wider text-base-content/30 mb-1">{cat}</div>
          <div className="grid grid-cols-3 gap-1.5">
            {blocks.map(b => (
              <button key={b.type} onClick={() => onAdd(b.type)}
                className="flex flex-col items-center gap-1 px-2 py-2 rounded-lg text-center hover:bg-primary/10 hover:text-primary transition-colors border border-transparent hover:border-primary/20">
                <BlockIcon icon={b.icon} size={20} className="text-base-content/40" />
                <span className="text-[10px] font-medium text-base-content/70 leading-tight">{b.label}</span>
              </button>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
