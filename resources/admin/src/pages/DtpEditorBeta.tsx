/**
 * MAG-P4 — Beta DTP Editor connected to real Save/Load API.
 *
 * Loads/saves DTP document via MAG-P3 endpoints.
 * Feature-flagged — old magazine editor remains unchanged.
 */
import { useState, useCallback, useEffect, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft, Save, Loader2, AlertTriangle, Eye, Info, ChevronDown, ChevronUp, ExternalLink,
  Type, Image, Square, Quote, Hash, Plus, Trash2, Copy,
  ZoomIn, ZoomOut, Maximize2,
  Magnet, Ruler,
} from 'lucide-react';
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
  const [zoom, setZoom] = useState(0.5);
  const [rightTab, setRightTab] = useState<'properties' | 'layers' | 'templates' | 'preflight' | 'export'>('properties');
  const [showGuides, setShowGuides] = useState(true);
  const [showRulers, setShowRulers] = useState(true);
  const [snapEnabled, setSnapEnabled] = useState(true);
  const [showStatusPanel, setShowStatusPanel] = useState(false);

  const ZOOM_STEPS = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2];

  // DTP rollout status (always available, not behind feature flag)
  const { data: rolloutData, refetch: refetchRollout } = useQuery({
    queryKey: ['dtp-rollout', siteId, issueId],
    queryFn: () => dtpDesigner.getRolloutStatus(siteId, issueId).then((r: any) => r.data.data),
    retry: 1,
    refetchOnWindowFocus: false,
  });

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

  // ─── Add frame ───
  const addFrame = useCallback((type: DtpFrame['type']) => {
    if (!doc) return;
    const id = crypto.randomUUID();
    const frame: DtpFrame = {
      id,
      type,
      pageIndex: 0,
      x: 40 + Math.random() * 100,
      y: 40 + Math.random() * 100,
      width: type === 'image' ? 300 : type === 'text' ? 400 : 200,
      height: type === 'image' ? 200 : type === 'text' ? 120 : 100,
      rotation: 0,
      zIndex: (doc.spreads[activeSpreadIdx]?.frames.length || 0) + 1,
      content: type === 'text' ? '<p>New text frame</p>' : type === 'quote' ? '<p>Quote text</p>' : '',
      label: type.charAt(0).toUpperCase() + type.slice(1),
      visible: true,
      locked: false,
      isMasterObject: false,
      image: type === 'image' ? { src: '', alt: '', caption: '', fitMode: 'fill' as const, focalPoint: { x: 50, y: 50 }, opacity: 100 } : undefined,
    };
    setDoc(prev => {
      if (!prev) return prev;
      const next = JSON.parse(JSON.stringify(prev));
      next.spreads[activeSpreadIdx]?.frames.push(frame);
      return next;
    });
    setSelectedIds([id]);
    setIsDirty(true);
  }, [doc, activeSpreadIdx]);

  // ─── Delete selected frames ───
  const deleteSelected = useCallback(() => {
    if (selectedIds.length === 0) return;
    setDoc(prev => {
      if (!prev) return prev;
      const next = JSON.parse(JSON.stringify(prev));
      const spread = next.spreads[activeSpreadIdx];
      if (spread) spread.frames = spread.frames.filter((f: DtpFrame) => !selectedIds.includes(f.id));
      return next;
    });
    setSelectedIds([]);
    setIsDirty(true);
  }, [selectedIds, activeSpreadIdx]);

  // ─── Duplicate selected frame ───
  const duplicateSelected = useCallback(() => {
    if (selectedIds.length !== 1 || !doc) return;
    const frame = doc.spreads[activeSpreadIdx]?.frames.find(f => f.id === selectedIds[0]);
    if (!frame) return;
    const newId = crypto.randomUUID();
    const clone = { ...JSON.parse(JSON.stringify(frame)), id: newId, x: frame.x + 20, y: frame.y + 20 };
    setDoc(prev => {
      if (!prev) return prev;
      const next = JSON.parse(JSON.stringify(prev));
      next.spreads[activeSpreadIdx]?.frames.push(clone);
      return next;
    });
    setSelectedIds([newId]);
    setIsDirty(true);
  }, [selectedIds, doc, activeSpreadIdx]);

  // ─── Add spread ───
  const addSpread = useCallback(() => {
    if (!doc) return;
    const pageNum = doc.spreads.reduce((acc, s) => acc + s.pages.length, 0) + 1;
    const newSpread: DtpSpread = {
      id: crypto.randomUUID(),
      label: `Spread ${doc.spreads.length + 1}`,
      pages: [{
        id: crypto.randomUUID(), pageNumber: pageNum, width: 595, height: 842,
        margins: { top: 36, right: 36, bottom: 36, left: 36 }, backgroundColor: '#ffffff',
      }],
      frames: [],
    };
    setDoc(prev => {
      if (!prev) return prev;
      const next = JSON.parse(JSON.stringify(prev));
      next.spreads.push(newSpread);
      return next;
    });
    setActiveSpreadIdx(doc.spreads.length);
    setSelectedIds([]);
    setIsDirty(true);
  }, [doc]);

  // ─── Keyboard shortcuts ───
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement;
      if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable) return;
      if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); deleteSelected(); }
      if ((e.key === 'd' || e.key === 'D') && (e.ctrlKey || e.metaKey)) { e.preventDefault(); duplicateSelected(); }
      if (selectedIds.length === 0 || !doc) return;
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
  }, [selectedIds, activeSpreadIdx, doc, updateFrame, deleteSelected, duplicateSelected]);

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
          <button onClick={() => navigate(`/sites/${siteId}/magazines`)} className="p-1 text-neutral-400 hover:text-white" title="Back to magazines">
            <ArrowLeft size={16} />
          </button>
          <span className="text-[11px] font-medium text-neutral-300 truncate max-w-[160px]">{doc.title}</span>
          <span className="text-[9px] bg-blue-500/20 text-blue-300 px-1.5 py-0.5 rounded font-medium">BETA</span>
          {isDirty && <span className="text-[9px] text-amber-400">Unsaved</span>}
        </div>

        <div className="flex items-center gap-0.5">
          {/* ─── Add frame tools ─── */}
          <div className="flex bg-neutral-700 rounded p-0.5 gap-0.5">
            <button onClick={() => addFrame('text')} className="p-1.5 rounded text-neutral-400 hover:text-white hover:bg-neutral-600" title="Add text frame (T)"><Type size={14} /></button>
            <button onClick={() => addFrame('image')} className="p-1.5 rounded text-neutral-400 hover:text-white hover:bg-neutral-600" title="Add image frame"><Image size={14} /></button>
            <button onClick={() => addFrame('text')} className="p-1.5 rounded text-neutral-400 hover:text-white hover:bg-neutral-600" title="Add shape (as text)"><Square size={14} /></button>
            <button onClick={() => addFrame('quote')} className="p-1.5 rounded text-neutral-400 hover:text-white hover:bg-neutral-600" title="Add quote"><Quote size={14} /></button>
            <button onClick={() => addFrame('pageNumber')} className="p-1.5 rounded text-neutral-400 hover:text-white hover:bg-neutral-600" title="Add page number"><Hash size={14} /></button>
          </div>

          <div className="w-px h-5 bg-neutral-600 mx-1" />

          {/* ─── Frame actions ─── */}
          <button onClick={duplicateSelected} disabled={selectedIds.length !== 1} className="p-1.5 rounded text-neutral-400 hover:text-white disabled:opacity-25" title="Duplicate (Ctrl+D)"><Copy size={14} /></button>
          <button onClick={deleteSelected} disabled={selectedIds.length === 0} className="p-1.5 rounded text-neutral-400 hover:text-red-400 disabled:opacity-25" title="Delete (Del)"><Trash2 size={14} /></button>

          <div className="w-px h-5 bg-neutral-600 mx-1" />

          {/* ─── Toggles ─── */}
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

          {/* ─── Zoom ─── */}
          <button onClick={() => { const i = ZOOM_STEPS.findIndex(z => z >= zoom); if (i > 0) setZoom(ZOOM_STEPS[i - 1]); }} className="p-1 text-neutral-400 hover:text-white"><ZoomOut size={14} /></button>
          <span className="text-[11px] text-neutral-400 w-10 text-center font-mono">{Math.round(zoom * 100)}%</span>
          <button onClick={() => { const i = ZOOM_STEPS.findIndex(z => z >= zoom); if (i < ZOOM_STEPS.length - 1) setZoom(ZOOM_STEPS[i + 1]); }} className="p-1 text-neutral-400 hover:text-white"><ZoomIn size={14} /></button>
          <button onClick={() => setZoom(0.5)} className="p-1 text-neutral-400 hover:text-white" title="Fit"><Maximize2 size={14} /></button>

          <div className="w-px h-5 bg-neutral-600 mx-1" />

          {/* ─── Preview / Save / Status ─── */}
          {rolloutData?.capabilities?.previewLinkAvailable && rolloutData?.links?.dtpPreview ? (
            <a href={rolloutData.links.dtpPreview} target="_blank" rel="noopener noreferrer"
              className="flex items-center gap-1 px-2 py-1 text-[11px] rounded bg-neutral-700 text-neutral-300 hover:bg-neutral-600">
              <Eye size={12} /> Preview
            </a>
          ) : rolloutData && !rolloutData?.capabilities?.previewLinkAvailable ? (
            <span className="flex items-center gap-1 px-2 py-1 text-[11px] rounded bg-neutral-700 text-neutral-500 cursor-not-allowed"
              title="Preview not available — save a DTP document first">
              <Eye size={12} /> Preview
            </span>
          ) : null}
          <button onClick={() => saveMut.mutate()} disabled={saveMut.isPending || !isDirty}
            className={`flex items-center gap-1 px-3 py-1 text-[11px] rounded ${isDirty ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-neutral-700 text-neutral-400'} disabled:opacity-40`}>
            {saveMut.isPending ? <Loader2 size={12} className="animate-spin" /> : <Save size={12} />}
            {saveMut.isPending ? 'Saving...' : 'Save'}
          </button>
          {saveMut.isError && (
            <span className="text-[10px] text-red-400">{(saveMut.error as any)?.response?.data?.message || 'Save failed'}</span>
          )}
          <button onClick={() => setShowStatusPanel(p => !p)}
            className={`flex items-center gap-1 px-2 py-1 text-[11px] rounded ${showStatusPanel ? 'bg-blue-500/20 text-blue-300' : 'bg-neutral-700 text-neutral-400 hover:text-neutral-200'}`}>
            <Info size={12} /> Status {showStatusPanel ? <ChevronUp size={10} /> : <ChevronDown size={10} />}
          </button>
        </div>
      </div>

      {/* DTP Status Panel */}
      {showStatusPanel && rolloutData && (
        <div className="bg-neutral-900 border-b border-neutral-700 px-4 py-3 shrink-0">
          <div className="flex flex-wrap gap-4 text-[11px]">
            {/* Rollout status */}
            <div className="flex items-center gap-2">
              <span className="text-neutral-500">Rollout:</span>
              <span className={`px-1.5 py-0.5 rounded font-medium ${
                rolloutData.status === 'dtp_ready' ? 'bg-green-500/20 text-green-300' :
                rolloutData.status === 'dtp_beta' ? 'bg-amber-500/20 text-amber-300' :
                'bg-neutral-600 text-neutral-300'
              }`}>
                {rolloutData.status === 'dtp_ready' ? 'Ready' :
                 rolloutData.status === 'dtp_beta' ? 'Beta' :
                 rolloutData.status === 'legacy' ? 'Legacy' : 'Unknown'}
              </span>
            </div>

            {/* DTP feature */}
            <div className="flex items-center gap-2">
              <span className="text-neutral-500">DTP Feature:</span>
              <span className={rolloutData.capabilities?.dtpFeatureEnabled ? 'text-green-400' : 'text-red-400'}>
                {rolloutData.capabilities?.dtpFeatureEnabled ? 'Enabled' : 'Disabled'}
              </span>
            </div>

            {/* Document status */}
            <div className="flex items-center gap-2">
              <span className="text-neutral-500">Document:</span>
              <span className={rolloutData.capabilities?.hasDtpDocument ? 'text-green-400' : 'text-neutral-400'}>
                {rolloutData.capabilities?.hasDtpDocument ? 'Saved' : 'Empty'}
              </span>
              {rolloutData.dtpStats && (
                <span className="text-neutral-500">
                  ({rolloutData.dtpStats.spreads} spreads, {rolloutData.dtpStats.pages} pages, {rolloutData.dtpStats.frames} frames)
                </span>
              )}
            </div>

            {/* Preview link */}
            <div className="flex items-center gap-2">
              <span className="text-neutral-500">Preview link:</span>
              {rolloutData.capabilities?.previewLinkAvailable && rolloutData.links?.dtpPreview ? (
                <a href={rolloutData.links.dtpPreview} target="_blank" rel="noopener noreferrer"
                  className="text-blue-400 hover:underline flex items-center gap-1">
                  Available <ExternalLink size={9} />
                </a>
              ) : (
                <span className="text-neutral-400">Not available</span>
              )}
            </div>

            {/* Preview render health */}
            <div className="flex items-center gap-2">
              <span className="text-neutral-500">Render health:</span>
              <span className={rolloutData.capabilities?.previewRenderable ? 'text-green-400' : 'text-amber-400'}>
                {rolloutData.capabilities?.previewRenderable ? 'Renderable' : 'Not renderable'}
              </span>
            </div>

            {/* Preflight */}
            {rolloutData.preflight && (
              <div className="flex items-center gap-2">
                <span className="text-neutral-500">Preflight:</span>
                <span className={`px-1.5 py-0.5 rounded font-medium ${
                  rolloutData.preflight.status === 'pass' ? 'bg-green-500/20 text-green-300' :
                  rolloutData.preflight.status === 'warning' ? 'bg-amber-500/20 text-amber-300' :
                  'bg-red-500/20 text-red-300'
                }`}>
                  {rolloutData.preflight.status === 'pass' ? 'Pass' :
                   rolloutData.preflight.status === 'warning' ? `Warnings (${rolloutData.preflight.counts?.warnings || 0})` :
                   `Errors (${rolloutData.preflight.counts?.blocking || 0} blocking)`}
                </span>
                <span className="text-neutral-500">Score: {rolloutData.preflight.score}/100</span>
              </div>
            )}

            {/* Promotion readiness */}
            <div className="flex items-center gap-2">
              <span className="text-neutral-500">Promote:</span>
              <span className={rolloutData.canPromote ? 'text-green-400' : 'text-neutral-400'}>
                {rolloutData.canPromote ? 'Ready' : 'Not ready'}
              </span>
            </div>

            {/* Blocking reasons */}
            {rolloutData.blockingReasons?.length > 0 && (
              <div className="flex items-center gap-2">
                <span className="text-neutral-500">Blocked:</span>
                <span className="text-red-400">{rolloutData.blockingReasons.join(' ')}</span>
              </div>
            )}

            {/* Warnings */}
            {rolloutData.warnings?.length > 0 && (
              <div className="flex items-center gap-2">
                <span className="text-neutral-500">Warnings:</span>
                <span className="text-amber-400">{rolloutData.warnings.join(' ')}</span>
              </div>
            )}

            {/* Refresh */}
            <button onClick={() => refetchRollout()} className="text-neutral-400 hover:text-white flex items-center gap-1">
              ↻ Refresh status
            </button>
          </div>
        </div>
      )}

      {/* Main area */}
      <div className="flex flex-1 overflow-hidden">
        {/* Left: Spread Navigator */}
        <div className="w-20 border-r border-neutral-700 overflow-y-auto shrink-0" style={{ backgroundColor: '#1a1a1a' }}>
          <div className="p-1.5 space-y-1.5">
            <div className="flex items-center justify-between px-1 py-1">
              <div className="text-[8px] text-neutral-500 uppercase tracking-wider">Spreads</div>
              <button onClick={addSpread} className="text-neutral-500 hover:text-white" title="Add spread"><Plus size={12} /></button>
            </div>
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
            showRulers={showRulers}
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
          {rolloutData && (
            <span className={`px-1 py-0.5 rounded text-[9px] ${
              rolloutData.status === 'dtp_ready' ? 'bg-green-500/15 text-green-400' :
              rolloutData.status === 'dtp_beta' ? 'bg-amber-500/15 text-amber-400' :
              'bg-neutral-700 text-neutral-400'
            }`}>
              {rolloutData.status === 'dtp_ready' ? 'Ready' :
               rolloutData.status === 'dtp_beta' ? 'Beta' : 'Legacy'}
            </span>
          )}
        </div>
      </div>
    </div>
  );
}
