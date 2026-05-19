/**
 * M3 DTP Canvas Prototype — Shell Layout
 *
 * Adds: multi-select, align/distribute, snap toggle, guide toggle, rulers.
 * Uses mocked data in local React state only. No database, no API.
 */
import { useState, useCallback, useEffect, useMemo } from 'react';
import {
  ArrowLeft, ZoomIn, ZoomOut, Maximize2, MousePointer,
  Magnet, Ruler, AlignStartVertical, AlignCenterVertical, AlignEndVertical,
  AlignStartHorizontal, AlignCenterHorizontal, AlignEndHorizontal,
  ArrowRightLeft, ArrowUpDown, CheckCircle, AlertTriangle, XCircle,
} from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import { MOCK_DOCUMENT, type DtpFrame, type DtpDocument } from './mockDocument';
import { SpreadCanvas } from './SpreadCanvas';
import { PropertiesPanel } from './PropertiesPanel';
import { PreflightPanel } from './PreflightPanel';
import { LayersPanel } from './LayersPanel';
import { TemplateGallery } from './TemplateGallery';
import { ExportPanel } from './ExportPanel';
import { MOCK_MASTER_PAGES, type DtpTemplate } from './mockDocument';
import { alignFrames, distributeFrames } from './snapEngine';
import { runPreflight } from './preflight';

const ZOOM_STEPS = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2];
const MIN_SIZE = 20;

function deepClone<T>(obj: T): T { return JSON.parse(JSON.stringify(obj)); }

export default function DtpPrototypeShell() {
  const navigate = useNavigate();
  const { siteId } = useParams();

  const [doc, setDoc] = useState<DtpDocument>(() => deepClone(MOCK_DOCUMENT));
  const [activeSpreadIdx, setActiveSpreadIdx] = useState(0);
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [zoom, setZoom] = useState(0.5);
  const [showGuides, setShowGuides] = useState(true);
  const [showRulers, setShowRulers] = useState(true);
  const [snapEnabled, setSnapEnabled] = useState(true);
  const [rightTab, setRightTab] = useState<'properties' | 'layers' | 'preflight' | 'templates' | 'export'>('properties');
  const [viewMode, setViewMode] = useState<'edit' | 'preview' | 'export'>('edit');

  // Auto-run preflight on document changes
  const preflightResult = useMemo(() => runPreflight(doc), [doc]);

  const activeSpread = doc.spreads[activeSpreadIdx];
  const selectedFrames = activeSpread?.frames.filter(f => selectedIds.includes(f.id)) ?? [];
  const selectedFrame = selectedFrames.length === 1 ? selectedFrames[0] : null;

  const handleZoomIn = () => { const i = ZOOM_STEPS.findIndex(z => z >= zoom); if (i < ZOOM_STEPS.length - 1) setZoom(ZOOM_STEPS[i + 1]); };
  const handleZoomOut = () => { const i = ZOOM_STEPS.findIndex(z => z >= zoom); if (i > 0) setZoom(ZOOM_STEPS[i - 1]); };
  const handleFitSpread = () => setZoom(0.5);

  // ─── Selection ───
  const handleSelectFrame = useCallback((id: string | null, addToSelection?: boolean) => {
    if (!id) { setSelectedIds([]); return; }
    if (addToSelection) {
      setSelectedIds(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
    } else {
      setSelectedIds([id]);
    }
  }, []);

  // ─── Frame updates ───
  const updateFrame = useCallback((frameId: string, updates: Partial<DtpFrame>) => {
    setDoc(prev => {
      const next = deepClone(prev);
      const frame = next.spreads[activeSpreadIdx]?.frames.find(f => f.id === frameId);
      if (!frame) return prev;
      if (updates.x !== undefined) frame.x = Math.round(updates.x);
      if (updates.y !== undefined) frame.y = Math.round(updates.y);
      if (updates.width !== undefined) frame.width = Math.max(MIN_SIZE, Math.round(updates.width));
      if (updates.height !== undefined) frame.height = Math.max(MIN_SIZE, Math.round(updates.height));
      if (updates.rotation !== undefined) frame.rotation = updates.rotation;
      if (updates.zIndex !== undefined) frame.zIndex = updates.zIndex;
      if (updates.visible !== undefined) frame.visible = updates.visible;
      if (updates.locked !== undefined) frame.locked = updates.locked;
      if (updates.image !== undefined) frame.image = updates.image as any;
      return next;
    });
  }, [activeSpreadIdx]);

  // ─── Template apply ───
  const handleApplyTemplate = useCallback((tpl: DtpTemplate, replace: boolean) => {
    setDoc(prev => {
      const next = deepClone(prev);
      const spread = next.spreads[activeSpreadIdx];
      if (!spread) return prev;
      if (replace) spread.frames = spread.frames.filter(f => f.isMasterObject);
      const newFrames = tpl.frames.map(f => ({ ...f, id: crypto.randomUUID() }));
      spread.frames.push(...newFrames);
      return next;
    });
  }, [activeSpreadIdx]);

  // ─── Master page assignment ───
  const handleAssignMaster = useCallback((masterPageId: string | null) => {
    setDoc(prev => {
      const next = deepClone(prev);
      const spread = next.spreads[activeSpreadIdx];
      if (!spread) return prev;
      // Remove old master objects
      spread.frames = spread.frames.filter(f => !f.isMasterObject);
      // Assign master to all pages in spread
      spread.pages.forEach(p => { p.masterPageId = masterPageId || undefined; });
      // Add master frames if assigned
      if (masterPageId) {
        const master = MOCK_MASTER_PAGES.find(m => m.id === masterPageId);
        if (master) {
          spread.pages.forEach((page, pageIdx) => {
            master.frames.forEach(mf => {
              const frame = {
                ...mf,
                id: crypto.randomUUID(),
                pageIndex: pageIdx,
                isMasterObject: true,
                masterPageId,
                locked: true,
                // Replace # with actual page number for pageNumber type
                content: mf.type === 'pageNumber' ? String(page.pageNumber) : mf.content,
              };
              spread.frames.push(frame);
            });
          });
        }
      }
      return next;
    });
  }, [activeSpreadIdx]);

  const currentMasterPageId = activeSpread?.pages[0]?.masterPageId;

  // ─── Align / Distribute ───
  const handleAlign = (dir: 'left' | 'center-h' | 'right' | 'top' | 'center-v' | 'bottom') => {
    const unlocked = selectedFrames.filter(f => !f.locked);
    if (unlocked.length < 2) return;
    const updates = alignFrames(unlocked, dir);
    unlocked.forEach((f, i) => { if (Object.keys(updates[i]).length) updateFrame(f.id, updates[i]); });
  };

  const handleDistribute = (axis: 'horizontal' | 'vertical') => {
    const unlocked = selectedFrames.filter(f => !f.locked);
    if (unlocked.length < 3) return;
    const sorted = [...unlocked].sort((a, b) => axis === 'horizontal' ? a.x - b.x : a.y - b.y);
    const updates = distributeFrames(sorted, axis);
    sorted.forEach((f, i) => { if (Object.keys(updates[i]).length) updateFrame(f.id, updates[i]); });
  };

  // ─── Keyboard ───
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement;
      if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable) return;

      if (selectedIds.length === 0) return;
      const step = e.shiftKey ? 10 : 1;
      let dx = 0, dy = 0;
      if (e.key === 'ArrowLeft') dx = -step;
      else if (e.key === 'ArrowRight') dx = step;
      else if (e.key === 'ArrowUp') dy = -step;
      else if (e.key === 'ArrowDown') dy = step;
      else return;

      e.preventDefault();
      for (const id of selectedIds) {
        const frame = doc.spreads[activeSpreadIdx]?.frames.find(f => f.id === id);
        if (frame && !frame.locked) updateFrame(id, { x: frame.x + dx, y: frame.y + dy });
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [selectedIds, activeSpreadIdx, doc, updateFrame]);

  const multiCount = selectedIds.length;

  return (
    <div className="flex flex-col h-screen bg-neutral-800 text-neutral-200" data-theme="cms-admin">
      {/* ─── Toolbar ─── */}
      <div className="flex items-center justify-between h-10 px-3 bg-neutral-900 border-b border-neutral-700 shrink-0">
        <div className="flex items-center gap-2">
          <button onClick={() => navigate(`/sites/${siteId}/magazines`)} className="p-1 text-neutral-400 hover:text-white" title="Back">
            <ArrowLeft size={16} />
          </button>
          <span className="text-[11px] font-medium text-neutral-300 truncate max-w-[160px]">{doc.title}</span>
          <span className="text-[9px] bg-amber-500/20 text-amber-300 px-1.5 py-0.5 rounded font-medium">M3</span>
        </div>

        <div className="flex items-center gap-0.5">
          {/* Tools */}
          <div className="flex bg-neutral-700 rounded p-0.5">
            <button className="p-1.5 rounded bg-blue-600 text-white" title="Select (V)"><MousePointer size={14} /></button>
          </div>

          <div className="w-px h-5 bg-neutral-600 mx-1" />

          {/* Toggles */}
          <button onClick={() => setShowRulers(!showRulers)}
            className={`p-1.5 rounded transition-colors ${showRulers ? 'bg-neutral-600 text-white' : 'text-neutral-400 hover:text-white'}`}
            title="Rulers"><Ruler size={14} /></button>
          <button onClick={() => setShowGuides(!showGuides)}
            className={`p-1.5 rounded transition-colors ${showGuides ? 'bg-neutral-600 text-white' : 'text-neutral-400 hover:text-white'}`}
            title="Guides"><span className="text-[10px] font-bold">G</span></button>
          <button onClick={() => setSnapEnabled(!snapEnabled)}
            className={`p-1.5 rounded transition-colors ${snapEnabled ? 'bg-blue-600 text-white' : 'text-neutral-400 hover:text-white'}`}
            title="Snap"><Magnet size={14} /></button>

          <div className="w-px h-5 bg-neutral-600 mx-1" />

          {/* Align */}
          <div className="flex bg-neutral-700 rounded p-0.5 gap-0.5" title={multiCount < 2 ? 'Select 2+ frames to align' : 'Align'}>
            <button onClick={() => handleAlign('left')} disabled={multiCount < 2} className="p-1 rounded text-neutral-400 hover:text-white disabled:opacity-25 disabled:cursor-not-allowed" title="Align left"><AlignStartVertical size={12} /></button>
            <button onClick={() => handleAlign('center-h')} disabled={multiCount < 2} className="p-1 rounded text-neutral-400 hover:text-white disabled:opacity-25 disabled:cursor-not-allowed" title="Align center H"><AlignCenterVertical size={12} /></button>
            <button onClick={() => handleAlign('right')} disabled={multiCount < 2} className="p-1 rounded text-neutral-400 hover:text-white disabled:opacity-25 disabled:cursor-not-allowed" title="Align right"><AlignEndVertical size={12} /></button>
            <button onClick={() => handleAlign('top')} disabled={multiCount < 2} className="p-1 rounded text-neutral-400 hover:text-white disabled:opacity-25 disabled:cursor-not-allowed" title="Align top"><AlignStartHorizontal size={12} /></button>
            <button onClick={() => handleAlign('center-v')} disabled={multiCount < 2} className="p-1 rounded text-neutral-400 hover:text-white disabled:opacity-25 disabled:cursor-not-allowed" title="Align center V"><AlignCenterHorizontal size={12} /></button>
            <button onClick={() => handleAlign('bottom')} disabled={multiCount < 2} className="p-1 rounded text-neutral-400 hover:text-white disabled:opacity-25 disabled:cursor-not-allowed" title="Align bottom"><AlignEndHorizontal size={12} /></button>
          </div>

          {/* Distribute */}
          <div className="flex bg-neutral-700 rounded p-0.5 gap-0.5" title={multiCount < 3 ? 'Select 3+ frames to distribute' : 'Distribute'}>
            <button onClick={() => handleDistribute('horizontal')} disabled={multiCount < 3} className="p-1 rounded text-neutral-400 hover:text-white disabled:opacity-25 disabled:cursor-not-allowed" title="Distribute H"><ArrowRightLeft size={12} /></button>
            <button onClick={() => handleDistribute('vertical')} disabled={multiCount < 3} className="p-1 rounded text-neutral-400 hover:text-white disabled:opacity-25 disabled:cursor-not-allowed" title="Distribute V"><ArrowUpDown size={12} /></button>
          </div>

          <div className="w-px h-5 bg-neutral-600 mx-1" />

          {/* Zoom */}
          <button onClick={handleZoomOut} className="p-1 text-neutral-400 hover:text-white"><ZoomOut size={14} /></button>
          <span className="text-[11px] text-neutral-400 w-10 text-center font-mono">{Math.round(zoom * 100)}%</span>
          <button onClick={handleZoomIn} className="p-1 text-neutral-400 hover:text-white"><ZoomIn size={14} /></button>
          <button onClick={handleFitSpread} className="p-1 text-neutral-400 hover:text-white"><Maximize2 size={14} /></button>

          <div className="w-px h-5 bg-neutral-600 mx-1" />

          {/* Preflight status */}
          <button onClick={() => setRightTab(rightTab === 'preflight' ? 'properties' : 'preflight')}
            className={`flex items-center gap-1 px-2 py-1 rounded text-[10px] font-medium transition-colors ${
              rightTab === 'preflight' ? 'bg-neutral-600 text-white' : 'text-neutral-400 hover:text-white'
            }`}
            title="Preflight">
            {preflightResult.status === 'pass' && <CheckCircle size={12} className="text-green-400" />}
            {preflightResult.status === 'warnings' && <AlertTriangle size={12} className="text-amber-400" />}
            {preflightResult.status === 'blocked' && <XCircle size={12} className="text-red-400" />}
            <span>{preflightResult.issues.length}</span>
          </button>

          <div className="w-px h-5 bg-neutral-600 mx-1" />

          {/* View mode */}
          <div className="flex bg-neutral-700 rounded p-0.5 gap-0.5">
            {(['edit', 'preview', 'export'] as const).map(mode => (
              <button key={mode} onClick={() => { setViewMode(mode); if (mode === 'export') setRightTab('export'); else if (rightTab === 'export') setRightTab('properties'); }}
                className={`px-2 py-0.5 rounded text-[9px] font-medium ${viewMode === mode ? 'bg-blue-600 text-white' : 'text-neutral-400 hover:text-white'}`}>
                {mode === 'edit' ? 'Edit' : mode === 'preview' ? 'Preview' : 'Export'}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* ─── Main ─── */}
      <div className="flex flex-1 overflow-hidden">
        {/* Left: Spreads */}
        <div className="w-20 border-r border-neutral-700 overflow-y-auto shrink-0" style={{ backgroundColor: '#1a1a1a' }}>
          <div className="p-1.5 space-y-1.5">
            <div className="text-[8px] text-neutral-500 uppercase tracking-wider px-1 py-1">Spreads</div>
            {doc.spreads.map((spread, idx) => (
              <button key={spread.id} onClick={() => { setActiveSpreadIdx(idx); setSelectedIds([]); }}
                className={`w-full rounded overflow-hidden border transition-colors ${idx === activeSpreadIdx ? 'border-blue-500' : 'border-neutral-600 hover:border-neutral-400'}`}>
                <div className="flex bg-neutral-700 p-1 gap-0.5" style={{ aspectRatio: spread.pages.length === 2 ? '2/1.4' : '1/1.4' }}>
                  {spread.pages.map(page => (
                    <div key={page.id} className="flex-1 bg-white rounded-[1px]" style={{ backgroundColor: page.backgroundColor }} />
                  ))}
                </div>
                <div className="text-[8px] text-neutral-400 text-center py-0.5 bg-neutral-800">
                  {spread.pages.map(p => p.pageNumber).join('-')}
                </div>
              </button>
            ))}
          </div>
        </div>

        {/* Center: Canvas */}
        <div className="flex-1 overflow-auto bg-neutral-700">
          <SpreadCanvas
            spread={activeSpread}
            zoom={zoom}
            selectedIds={selectedIds}
            onSelectFrame={handleSelectFrame}
            onUpdateFrame={updateFrame}
            showGuides={showGuides}
            showRulers={showRulers}
            snapEnabled={snapEnabled}
          />
        </div>

        {/* Right: Properties / Preflight */}
        <div className="w-72 bg-neutral-800 border-l border-neutral-700 overflow-y-auto shrink-0">
          {/* Tabs */}
          <div className="flex border-b border-neutral-700">
            <button onClick={() => setRightTab('properties')}
              className={`flex-1 px-1 py-1.5 text-[9px] font-medium ${rightTab === 'properties' ? 'text-white border-b-2 border-blue-500' : 'text-neutral-400'}`}>
              Props
            </button>
            <button onClick={() => setRightTab('layers')}
              className={`flex-1 px-1 py-1.5 text-[9px] font-medium ${rightTab === 'layers' ? 'text-white border-b-2 border-blue-500' : 'text-neutral-400'}`}>
              Layers
            </button>
            <button onClick={() => setRightTab('templates')}
              className={`flex-1 px-1 py-1.5 text-[9px] font-medium ${rightTab === 'templates' ? 'text-white border-b-2 border-blue-500' : 'text-neutral-400'}`}>
              Tmpl
            </button>
            <button onClick={() => setRightTab('preflight')}
              className={`flex-1 px-1 py-1.5 text-[9px] font-medium flex items-center justify-center gap-0.5 ${rightTab === 'preflight' ? 'text-white border-b-2 border-blue-500' : 'text-neutral-400'}`}>
              Check
              {preflightResult.issues.length > 0 && (
                <span className={`text-[7px] px-1 rounded-full ${preflightResult.status === 'blocked' ? 'bg-red-500' : 'bg-amber-500'} text-white`}>
                  {preflightResult.issues.length}
                </span>
              )}
            </button>
            <button onClick={() => { setRightTab('export'); setViewMode('export'); }}
              className={`flex-1 px-1 py-1.5 text-[9px] font-medium ${rightTab === 'export' ? 'text-white border-b-2 border-blue-500' : 'text-neutral-400'}`}>
              Export
            </button>
          </div>
          {rightTab === 'properties' && (
            <PropertiesPanel
              spread={activeSpread}
              selectedFrame={selectedFrame}
              selectedCount={multiCount}
              document={doc}
              onUpdateFrame={updateFrame}
            />
          )}
          {rightTab === 'layers' && (
            <LayersPanel
              spread={activeSpread}
              selectedIds={selectedIds}
              onSelectFrame={(id) => { setSelectedIds([id]); }}
              onUpdateFrame={updateFrame}
            />
          )}
          {rightTab === 'templates' && (
            <TemplateGallery
              onApplyTemplate={handleApplyTemplate}
              onAssignMaster={handleAssignMaster}
              currentMasterPageId={currentMasterPageId}
            />
          )}
          {rightTab === 'preflight' && (
            <PreflightPanel
              result={preflightResult}
              onSelectFrame={(id) => { setSelectedIds([id]); setRightTab('properties'); }}
            />
          )}
          {rightTab === 'export' && (
            <ExportPanel document={doc} preflight={preflightResult} />
          )}
        </div>
      </div>

      {/* ─── Status Bar ─── */}
      <div className="flex items-center justify-between h-7 px-3 bg-neutral-900 border-t border-neutral-700 shrink-0">
        <div className="flex items-center gap-3 text-[10px] text-neutral-400">
          <span>Spread {activeSpreadIdx + 1}/{doc.spreads.length}</span>
          <span>Pages {activeSpread.pages.map(p => p.pageNumber).join('-')}</span>
          <span>{activeSpread.frames.length} frames</span>
          {multiCount > 0 && <span className="text-blue-400">{multiCount} selected</span>}
        </div>
        <div className="flex items-center gap-3 text-[10px] text-neutral-400">
          {selectedFrame && (
            <span className="text-blue-400">{selectedFrame.label} — {selectedFrame.x},{selectedFrame.y} {selectedFrame.width}x{selectedFrame.height}</span>
          )}
          <span>Snap: {snapEnabled ? 'ON' : 'off'}</span>
          <span>Mode: {viewMode}</span>
          <span>Zoom {Math.round(zoom * 100)}%</span>
        </div>
      </div>
    </div>
  );
}
