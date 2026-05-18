/**
 * M1 DTP Canvas Prototype — Shell Layout
 *
 * InDesign-inspired layout: toolbar, page navigator, canvas, properties panel, status bar.
 * Uses mocked data only. No database, no API.
 */
import { useState, useCallback } from 'react';
import { ArrowLeft, ZoomIn, ZoomOut, Maximize2, MousePointer, Type, ImageIcon, Quote } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import { MOCK_DOCUMENT } from './mockDocument';
import { SpreadCanvas } from './SpreadCanvas';
import { PropertiesPanel } from './PropertiesPanel';

const ZOOM_STEPS = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2];

export default function DtpPrototypeShell() {
  const navigate = useNavigate();
  const { siteId } = useParams();
  const [activeSpreadIdx, setActiveSpreadIdx] = useState(0);
  const [selectedFrameId, setSelectedFrameId] = useState<string | null>(null);
  const [zoom, setZoom] = useState(0.5);
  const [activeTool, setActiveTool] = useState<'select' | 'text' | 'image' | 'quote'>('select');

  const doc = MOCK_DOCUMENT;
  const activeSpread = doc.spreads[activeSpreadIdx];
  const selectedFrame = activeSpread?.frames.find(f => f.id === selectedFrameId) ?? null;

  const handleZoomIn = () => {
    const idx = ZOOM_STEPS.findIndex(z => z >= zoom);
    if (idx < ZOOM_STEPS.length - 1) setZoom(ZOOM_STEPS[idx + 1]);
  };
  const handleZoomOut = () => {
    const idx = ZOOM_STEPS.findIndex(z => z >= zoom);
    if (idx > 0) setZoom(ZOOM_STEPS[idx - 1]);
  };
  const handleFitSpread = () => setZoom(0.5);

  const handleSelectFrame = useCallback((id: string | null) => {
    setSelectedFrameId(id);
  }, []);

  return (
    <div className="flex flex-col h-screen bg-neutral-800 text-neutral-200" data-theme="cms-admin">
      {/* ─── Top Toolbar ─── */}
      <div className="flex items-center justify-between h-10 px-3 bg-neutral-900 border-b border-neutral-700 shrink-0">
        <div className="flex items-center gap-2">
          <button onClick={() => navigate(`/sites/${siteId}/magazines`)}
            className="p-1 text-neutral-400 hover:text-white" title="Back to magazines">
            <ArrowLeft size={16} />
          </button>
          <span className="text-[11px] font-medium text-neutral-300">{doc.title}</span>
          <span className="text-[9px] bg-amber-500/20 text-amber-300 px-1.5 py-0.5 rounded font-medium">M1 PROTOTYPE</span>
        </div>

        {/* Tools */}
        <div className="flex items-center gap-1">
          <div className="flex bg-neutral-700 rounded p-0.5 gap-0.5">
            {([
              { tool: 'select' as const, Icon: MousePointer, label: 'Select (V)' },
              { tool: 'text' as const, Icon: Type, label: 'Text Frame (T)' },
              { tool: 'image' as const, Icon: ImageIcon, label: 'Image Frame (I)' },
              { tool: 'quote' as const, Icon: Quote, label: 'Quote Frame (Q)' },
            ]).map(({ tool, Icon, label }) => (
              <button key={tool} onClick={() => setActiveTool(tool)}
                className={`p-1.5 rounded transition-colors ${activeTool === tool ? 'bg-blue-600 text-white' : 'text-neutral-400 hover:text-white'}`}
                title={label}>
                <Icon size={14} />
              </button>
            ))}
          </div>

          <div className="w-px h-5 bg-neutral-600 mx-1" />

          {/* Zoom */}
          <button onClick={handleZoomOut} className="p-1 text-neutral-400 hover:text-white" title="Zoom out">
            <ZoomOut size={14} />
          </button>
          <span className="text-[11px] text-neutral-400 w-10 text-center font-mono">{Math.round(zoom * 100)}%</span>
          <button onClick={handleZoomIn} className="p-1 text-neutral-400 hover:text-white" title="Zoom in">
            <ZoomIn size={14} />
          </button>
          <button onClick={handleFitSpread} className="p-1 text-neutral-400 hover:text-white" title="Fit spread">
            <Maximize2 size={14} />
          </button>
        </div>
      </div>

      {/* ─── Main Area ─── */}
      <div className="flex flex-1 overflow-hidden">
        {/* Left: Spread Navigator */}
        <div className="w-20 bg-neutral-850 border-r border-neutral-700 overflow-y-auto shrink-0"
          style={{ backgroundColor: '#1a1a1a' }}>
          <div className="p-1.5 space-y-1.5">
            <div className="text-[8px] text-neutral-500 uppercase tracking-wider px-1 py-1">Spreads</div>
            {doc.spreads.map((spread, idx) => (
              <button key={spread.id} onClick={() => { setActiveSpreadIdx(idx); setSelectedFrameId(null); }}
                className={`w-full rounded overflow-hidden border transition-colors ${
                  idx === activeSpreadIdx ? 'border-blue-500' : 'border-neutral-600 hover:border-neutral-400'
                }`}>
                {/* Mini spread thumbnail */}
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
            selectedFrameId={selectedFrameId}
            onSelectFrame={handleSelectFrame}
          />
        </div>

        {/* Right: Properties Panel */}
        <div className="w-72 bg-neutral-800 border-l border-neutral-700 overflow-y-auto shrink-0">
          <PropertiesPanel
            spread={activeSpread}
            selectedFrame={selectedFrame}
            document={doc}
          />
        </div>
      </div>

      {/* ─── Bottom Status Bar ─── */}
      <div className="flex items-center justify-between h-7 px-3 bg-neutral-900 border-t border-neutral-700 shrink-0">
        <div className="flex items-center gap-3 text-[10px] text-neutral-400">
          <span>Spread {activeSpreadIdx + 1}/{doc.spreads.length}</span>
          <span>Pages {activeSpread.pages.map(p => p.pageNumber).join('-')}</span>
          <span>{activeSpread.frames.length} frames</span>
        </div>
        <div className="flex items-center gap-3 text-[10px] text-neutral-400">
          {selectedFrame && (
            <span className="text-blue-400">{selectedFrame.label || selectedFrame.type} selected</span>
          )}
          <span>Zoom {Math.round(zoom * 100)}%</span>
          <span>Tool: {activeTool}</span>
        </div>
      </div>
    </div>
  );
}
