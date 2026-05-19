/**
 * MAG-P4 — Beta DTP Editor connected to real Save/Load API.
 *
 * Loads/saves DTP document via MAG-P3 endpoints.
 * Feature-flagged — old magazine editor remains unchanged.
 */
import { useState, useCallback, useEffect, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Save, Loader2, AlertTriangle } from 'lucide-react';
import { dtpDesigner } from '@/lib/api';

// Reuse prototype components
import { SpreadCanvas } from '@/components/magazine/prototypes/dtp/SpreadCanvas';
import { PropertiesPanel } from '@/components/magazine/prototypes/dtp/PropertiesPanel';
import { LayersPanel } from '@/components/magazine/prototypes/dtp/LayersPanel';
import { PreflightPanel } from '@/components/magazine/prototypes/dtp/PreflightPanel';
import { ExportPanel } from '@/components/magazine/prototypes/dtp/ExportPanel';
import { runPreflight } from '@/components/magazine/prototypes/dtp/preflight';
import type { DtpFrame, DtpDocument, DtpSpread, DtpPage } from '@/components/magazine/prototypes/dtp/mockDocument';

const MIN_SIZE = 20;

/** Convert API response to DtpDocument for the prototype canvas */
function apiToDocument(data: any): DtpDocument {
  const spreads: DtpSpread[] = [];
  const apiSpreads = data.spreads || [];
  const apiPages = data.pages || [];
  const apiFrames = data.frames || [];

  if (apiSpreads.length === 0) {
    // Empty document — create starter spread with one page
    spreads.push({
      id: 'starter-spread',
      label: 'Spread 1',
      pages: [{
        id: 'starter-page', pageNumber: 1, width: 595, height: 842,
        margins: { top: 36, right: 36, bottom: 36, left: 36 }, backgroundColor: '#ffffff',
      }],
      frames: [],
    });
  } else {
    for (const apiSpread of apiSpreads) {
      const spreadPages = apiPages
        .filter((p: any) => p.spread_id === apiSpread.id)
        .sort((a: any, b: any) => a.page_index - b.page_index);

      const pages: DtpPage[] = spreadPages.map((p: any) => ({
        id: p.id,
        pageNumber: p.page_index + 1,
        width: p.width || 595,
        height: p.height || 842,
        margins: p.margins || { top: 36, right: 36, bottom: 36, left: 36 },
        backgroundColor: p.background?.color || '#ffffff',
        masterPageId: p.master_page_id || undefined,
      }));

      const frames: DtpFrame[] = apiFrames
        .filter((f: any) => spreadPages.some((p: any) => p.id === f.page_id))
        .map((f: any) => {
          const pageIdx = spreadPages.findIndex((p: any) => p.id === f.page_id);
          return {
            id: f.id,
            type: mapFrameType(f.frame_type),
            pageIndex: Math.max(0, pageIdx),
            x: f.x || 0,
            y: f.y || 0,
            width: f.width || 200,
            height: f.height || 100,
            rotation: f.rotation || 0,
            zIndex: f.z_index || 0,
            content: f.content?.html || f.content?.text || '',
            label: f.name || f.frame_type,
            visible: f.visible !== false,
            locked: f.locked === true,
            isMasterObject: f.metadata?.onMaster === true,
            image: f.frame_type === 'image' ? {
              src: f.content?.src || '',
              alt: f.content?.alt || '',
              caption: f.content?.caption || '',
              fitMode: f.content?.fitMode || 'fill',
              focalPoint: f.content?.focalPoint || { x: 50, y: 50 },
              opacity: f.content?.opacity ?? 100,
            } : undefined,
          } as DtpFrame;
        });

      spreads.push({
        id: apiSpread.id,
        label: apiSpread.name || `Spread ${apiSpread.spread_index + 1}`,
        pages,
        frames,
      });
    }
  }

  return {
    title: data.issue?.title || 'Untitled Issue',
    subtitle: data.issue?.subtitle || '',
    pageSize: { width: 595, height: 842 },
    spreads,
  };
}

function mapFrameType(apiType: string): DtpFrame['type'] {
  const map: Record<string, DtpFrame['type']> = {
    text: 'text', image: 'image', quote: 'quote', pageNumber: 'pageNumber',
    shape: 'text', line: 'text', articleReference: 'text', decorative: 'text',
  };
  return map[apiType] || 'text';
}

/** Convert DtpDocument back to API save payload */
function documentToApi(doc: DtpDocument): Record<string, unknown> {
  const spreads: any[] = [];
  const pages: any[] = [];
  const frames: any[] = [];

  doc.spreads.forEach((spread, si) => {
    const spreadId = spread.id || crypto.randomUUID();
    spreads.push({ id: spreadId, spread_index: si, name: spread.label });

    spread.pages.forEach((page, pi) => {
      const pageId = page.id || crypto.randomUUID();
      pages.push({
        id: pageId, spread_id: spreadId, page_index: si * 2 + pi,
        side: spread.pages.length === 1 ? 'single' : pi === 0 ? 'left' : 'right',
        width: page.width, height: page.height,
        margins: page.margins, background: { color: page.backgroundColor },
        master_page_id: page.masterPageId || null,
      });

      spread.frames
        .filter(f => f.pageIndex === pi)
        .forEach(f => {
          frames.push({
            id: f.id || crypto.randomUUID(),
            page_id: pageId, spread_id: spreadId,
            frame_type: f.type === 'quote' ? 'quote' : f.type === 'pageNumber' ? 'pageNumber' : f.type,
            name: f.label || null,
            x: f.x, y: f.y, width: f.width, height: f.height,
            rotation: f.rotation, z_index: f.zIndex,
            visible: f.visible !== false, locked: f.locked === true,
            content: f.type === 'image' && f.image ? {
              src: f.image.src, alt: f.image.alt, caption: f.image.caption,
              fitMode: f.image.fitMode, focalPoint: f.image.focalPoint, opacity: f.image.opacity,
            } : f.content ? { html: f.content } : {},
            style: {}, metadata: { onMaster: f.isMasterObject || false },
          });
        });
    });
  });

  return { spreads, pages, layers: [], frames, asset_references: [] };
}

export default function DtpEditorBeta() {
  const { siteId = '', issueId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  // Load document from API
  const { data: apiData, isLoading, error: loadError } = useQuery({
    queryKey: ['dtp-document', siteId, issueId],
    queryFn: () => dtpDesigner.loadDocument(siteId, issueId).then((r: any) => r.data.data),
    retry: 1,
  });

  // Local editor state
  const [doc, setDoc] = useState<DtpDocument | null>(null);
  const [apiLayers, setApiLayers] = useState<any[]>([]);
  const [apiAssetRefs, setApiAssetRefs] = useState<any[]>([]);
  const [isDirty, setIsDirty] = useState(false);
  const [activeSpreadIdx, setActiveSpreadIdx] = useState(0);
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [zoom] = useState(0.5);
  const [rightTab, setRightTab] = useState<'properties' | 'layers' | 'templates' | 'preflight' | 'export'>('properties');
  const [showGuides] = useState(true);
  const [snapEnabled] = useState(true);

  // Initialize doc from API data (once)
  useEffect(() => {
    if (apiData && !doc) {
      setDoc(apiToDocument(apiData));
      setApiLayers(apiData.layers || []);
      setApiAssetRefs(apiData.asset_references || []);
    }
  }, [apiData, doc]);

  // Save mutation
  const saveMut = useMutation({
    mutationFn: () => {
      if (!doc) throw new Error('No document');
      const payload = documentToApi(doc);
      // Preserve layers and asset_references from API (not yet editable in beta)
      payload.layers = apiLayers;
      payload.asset_references = apiAssetRefs;
      return dtpDesigner.saveDocument(siteId, issueId, payload);
    },
    onSuccess: () => {
      setIsDirty(false);
      queryClient.invalidateQueries({ queryKey: ['dtp-document', siteId, issueId] });
    },
  });

  // Frame update
  const updateFrame = useCallback((frameId: string, updates: Partial<DtpFrame>) => {
    setDoc(prev => {
      if (!prev) return prev;
      const next = JSON.parse(JSON.stringify(prev));
      const frame = next.spreads[activeSpreadIdx]?.frames.find((f: DtpFrame) => f.id === frameId);
      if (!frame) return prev;
      if (updates.x !== undefined) frame.x = Math.round(updates.x);
      if (updates.y !== undefined) frame.y = Math.round(updates.y);
      if (updates.width !== undefined) frame.width = Math.max(MIN_SIZE, Math.round(updates.width));
      if (updates.height !== undefined) frame.height = Math.max(MIN_SIZE, Math.round(updates.height));
      if (updates.rotation !== undefined) frame.rotation = updates.rotation;
      if (updates.zIndex !== undefined) frame.zIndex = updates.zIndex;
      if (updates.visible !== undefined) frame.visible = updates.visible;
      if (updates.locked !== undefined) frame.locked = updates.locked;
      if (updates.image !== undefined) frame.image = updates.image;
      return next;
    });
    setIsDirty(true);
  }, [activeSpreadIdx]);

  const activeSpread = doc?.spreads[activeSpreadIdx];
  const selectedFrames = activeSpread?.frames.filter(f => selectedIds.includes(f.id)) ?? [];
  const selectedFrame = selectedFrames.length === 1 ? selectedFrames[0] : null;
  const preflightResult = useMemo(() => doc ? runPreflight(doc) : { status: 'pass' as const, score: 100, errors: [], warnings: [], info: [], issues: [] }, [doc]);

  // ─── Loading / Error states ───
  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen bg-neutral-800">
        <Loader2 className="h-8 w-8 animate-spin text-neutral-400" />
        <span className="ml-3 text-neutral-400">Loading DTP document...</span>
      </div>
    );
  }

  if (loadError) {
    const is404 = (loadError as any)?.response?.status === 404;
    return (
      <div className="flex flex-col items-center justify-center h-screen bg-neutral-800 text-neutral-200">
        <AlertTriangle className="h-12 w-12 text-amber-400 mb-4" />
        <h2 className="text-lg font-semibold mb-2">{is404 ? 'DTP Designer Not Available' : 'Failed to Load'}</h2>
        <p className="text-sm text-neutral-400 mb-4">
          {is404 ? 'The DTP Designer feature is not enabled for this site.' : (loadError as Error).message}
        </p>
        <button onClick={() => navigate(-1)} className="btn btn-sm btn-ghost">Go Back</button>
      </div>
    );
  }

  if (!doc || !activeSpread) return null;

  return (
    <div className="flex flex-col h-screen bg-neutral-800 text-neutral-200">
      {/* Toolbar */}
      <div className="flex items-center justify-between h-10 px-3 bg-neutral-900 border-b border-neutral-700 shrink-0">
        <div className="flex items-center gap-2">
          <button onClick={() => navigate(`/sites/${siteId}/magazines`)} className="p-1 text-neutral-400 hover:text-white">
            <ArrowLeft size={16} />
          </button>
          <span className="text-[11px] font-medium text-neutral-300 truncate max-w-[200px]">{doc.title}</span>
          <span className="text-[9px] bg-blue-500/20 text-blue-300 px-1.5 py-0.5 rounded font-medium">BETA</span>
          {isDirty && <span className="text-[9px] text-amber-400">Unsaved</span>}
        </div>
        <div className="flex items-center gap-2">
          <button onClick={() => saveMut.mutate()} disabled={saveMut.isPending || !isDirty}
            className={`flex items-center gap-1 px-3 py-1 text-[11px] rounded ${isDirty ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-neutral-700 text-neutral-400'} disabled:opacity-40`}>
            {saveMut.isPending ? <Loader2 size={12} className="animate-spin" /> : <Save size={12} />}
            {saveMut.isPending ? 'Saving...' : 'Save'}
          </button>
          {saveMut.isError && (
            <span className="text-[10px] text-red-400">{(saveMut.error as any)?.response?.data?.message || 'Save failed'}</span>
          )}
        </div>
      </div>

      {/* Main area */}
      <div className="flex flex-1 overflow-hidden">
        {/* Left: Spread Navigator */}
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
            onSelectFrame={(id, add) => {
              if (!id) { setSelectedIds([]); return; }
              if (add) { setSelectedIds(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]); }
              else { setSelectedIds([id]); }
            }}
            onUpdateFrame={(id, updates) => { updateFrame(id, updates); }}
            showGuides={showGuides}
            showRulers={true}
            snapEnabled={snapEnabled}
          />
        </div>

        {/* Right: Tabs */}
        <div className="w-72 bg-neutral-800 border-l border-neutral-700 overflow-y-auto shrink-0">
          <div className="flex border-b border-neutral-700">
            {(['properties', 'layers', 'templates', 'preflight', 'export'] as const).map(tab => (
              <button key={tab} onClick={() => setRightTab(tab)}
                className={`flex-1 px-1 py-1.5 text-[9px] font-medium ${rightTab === tab ? 'text-white border-b-2 border-blue-500' : 'text-neutral-400'}`}>
                {tab === 'properties' ? 'Props' : tab === 'templates' ? 'Tmpl' : tab === 'preflight' ? 'Check' : tab.charAt(0).toUpperCase() + tab.slice(1)}
              </button>
            ))}
          </div>
          {rightTab === 'properties' && <PropertiesPanel spread={activeSpread} selectedFrame={selectedFrame} selectedCount={selectedIds.length} document={doc} onUpdateFrame={updateFrame} />}
          {rightTab === 'layers' && <LayersPanel spread={activeSpread} selectedIds={selectedIds} onSelectFrame={id => setSelectedIds([id])} onUpdateFrame={updateFrame} />}
          {rightTab === 'preflight' && <PreflightPanel result={preflightResult} onSelectFrame={id => { setSelectedIds([id]); setRightTab('properties'); }} />}
          {rightTab === 'export' && <ExportPanel document={doc} preflight={preflightResult} />}
        </div>
      </div>

      {/* Status */}
      <div className="flex items-center justify-between h-7 px-3 bg-neutral-900 border-t border-neutral-700 shrink-0">
        <div className="flex items-center gap-3 text-[10px] text-neutral-400">
          <span>Spread {activeSpreadIdx + 1}/{doc.spreads.length}</span>
          <span>{activeSpread.frames.length} frames</span>
          {isDirty && <span className="text-amber-400">Unsaved changes</span>}
        </div>
        <div className="flex items-center gap-3 text-[10px] text-neutral-400">
          {selectedFrame && <span className="text-blue-400">{selectedFrame.label} — {selectedFrame.x},{selectedFrame.y}</span>}
          <span>Zoom {Math.round(zoom * 100)}%</span>
        </div>
      </div>
    </div>
  );
}
