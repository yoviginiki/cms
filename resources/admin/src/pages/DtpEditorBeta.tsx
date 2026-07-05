/**
 * MAG-P12 — Production DTP Editor using proven MagazineCanvas + magazineStore.
 *
 * Loads DTP document via MAG-P3 API, converts to MagPageData/MagElement format,
 * and renders using the full production canvas with 39 element types, undo/redo,
 * inline text editing, zoom, and all property panels.
 *
 * Feature-flagged — old magazine editor remains unchanged.
 */
import { useEffect, useRef, useState, lazy, Suspense, useCallback, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Loader2, AlertTriangle, Info, ChevronDown, ChevronUp, ExternalLink, Bug } from 'lucide-react';
import { dtpDesigner } from '@/lib/api';

// ─── Production editor components (from old MagazineEditorV2) ───
import { useMagazineStore } from '@/stores/magazineStore';
import { MagazineCanvas } from '@/components/magazine/MagazineCanvas';
import MagazineToolbar from '@/components/magazine/MagazineToolbar';
import MagLayersPanel from '@/components/magazine/MagLayersPanel';
import PageNavigator from '@/components/magazine/PageNavigator';
import StylesPanel from '@/components/magazine/StylesPanel';
import MagElementPalette from '@/components/magazine/MagElementPalette';
import TransformPanel from '@/components/magazine/properties/TransformPanel';
import MagTypographyPanel from '@/components/magazine/properties/MagTypographyPanel';
import FillStrokePanel from '@/components/magazine/properties/FillStrokePanel';
import EffectsPanel from '@/components/magazine/properties/EffectsPanel';
import TextFramePanel from '@/components/magazine/properties/TextFramePanel';
import TextWrapPanel from '@/components/magazine/properties/TextWrapPanel';
import ImagePanel from '@/components/magazine/properties/ImagePanel';
import TablePanel from '@/components/magazine/properties/TablePanel';
import AlignDistributePanel from '@/components/magazine/properties/AlignDistributePanel';
import RichTextToolbar from '@/components/magazine/properties/RichTextToolbar';
import PagePanel from '@/components/magazine/properties/PagePanel';
import { AssetPicker } from '@/components/ui/AssetPicker';
const DtpDebugPanel = lazy(() => import('@/components/magazine/DtpDebugPanel'));
import { extractEditorDoc, extractPayloadDoc, extractLoadedDoc, runConsistencyCheck } from '@/lib/dtpConsistencyChecker';
import type { ConsistencyResult } from '@/lib/dtpConsistencyChecker';
import type { MagElement, MagPageData, MagTypography, MagElementStyle, MagTextWrap, TextFrameData, ImageFrameData } from '@/types/magazine';
import { DEFAULT_ELEMENT_STYLE, DEFAULT_TEXT_WRAP, DEFAULT_TYPOGRAPHY } from '@/types/magazine';
// Threading imports removed — Pour handles content splitting directly
import { dtpApiToPages, pagesToDtpApi, normalizeMasterPages } from '@/lib/dtpAdapters';
import { runPreflight } from '@/lib/magazinePreflight';
import { findMatches, replaceInHtml } from '@/lib/magazineFindReplace';
import { mapHeadingsToStyles, wordCount } from '@/lib/clipboardNormalizer';
import { MagSwatchContext } from '@/components/magazine/SwatchPicker';
import { AssetField } from '@/components/ui/AssetPicker';
import { extractColorSwatches, DEFAULT_SWATCHES } from '@/lib/themeSwatches';
import { themeEngine, assets as assetsApi, api } from '@/lib/api';

// ─── Helper: create a frame for master pages ───
function makeFrame(type: string, name: string, x: number, y: number, w: number, h: number, data: Record<string, unknown>, pageNumber = 1): MagElement {
  const isText = ['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'running_header'].includes(type);
  return {
    id: crypto.randomUUID(), type: type as any, name, data,
    x, y, width: w, height: h, rotation: 0, scaleX: 1, scaleY: 1, zIndex: 0,
    locked: false, visible: true, layerName: null,
    style: { ...DEFAULT_ELEMENT_STYLE }, typography: isText ? { ...DEFAULT_TYPOGRAPHY, fontSize: 10 } : null,
    textWrap: { ...DEFAULT_TEXT_WRAP }, threadId: null, threadOrder: null,
    pageNumber, onMaster: true, positionMode: 'free' as const, spanMode: 'page' as const, parentId: null, children: [], responsiveOverrides: {},
  };
}

// ─── Editor Component ───

type RightTab = 'add' | 'properties' | 'layers' | 'styles' | 'issue';

export default function DtpEditorBeta() {
  const { siteId = '', issueId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const store = useMagazineStore();

  const [rightTab, setRightTab] = useState<RightTab>('properties');
  const [showStatusPanel, setShowStatusPanel] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [apiLayers, setApiLayers] = useState<any[]>([]);
  const [apiAssetRefs, setApiAssetRefs] = useState<any[]>([]);
  const [autoOpenImagePicker, setAutoOpenImagePicker] = useState(false);
  const [inlineImagePickerOpen, setInlineImagePickerOpen] = useState(false);
  const [alignToPage, setAlignToPage] = useState(false);
  const [activeThreadId, setActiveThreadId] = useState<string | null>(null);
  const [canvasEditingId, setCanvasEditingId] = useState<string | null>(null);
  const [startEditingRequest, setStartEditingRequest] = useState<string | null>(null);
  const [issueStatus, setIssueStatus] = useState<string>('draft');
  const [selectedInlineImg, setSelectedInlineImg] = useState<HTMLImageElement | null>(null);
  const [viewerSettings, setViewerSettings] = useState<Record<string, any>>({
    display_mode: 'spread',
    bg_color: '#0a0a0a',
    show_thumbnails: true,
    show_page_numbers: true,
    auto_hide_ui: true,
  });
  const initializedRef = useRef(false);

  // Debug mode
  const isDebugMode = localStorage.getItem('dtp-debug') === '1';
  const [debugOpen, setDebugOpen] = useState(false);
  const [lastSavePayload, setLastSavePayload] = useState<any>(null);
  const [lastLoadPayload, setLastLoadPayload] = useState<any>(null);
  const [consistencyResult, setConsistencyResult] = useState<ConsistencyResult | null>(null);

  const runConsistency = useCallback(() => {
    if (!isDebugMode) return;
    const freshState = useMagazineStore.getState();
    const editorDoc = extractEditorDoc(freshState.pages, freshState.issueSettings, {
      showGrid: freshState.showGrid, showGuides: freshState.showGuides, snapEnabled: freshState.snapEnabled,
    });
    const payloadDoc = extractPayloadDoc(lastSavePayload, freshState.issueSettings);
    const loadedDoc = extractLoadedDoc(lastLoadPayload);
    const result = runConsistencyCheck(editorDoc, payloadDoc, loadedDoc, null);
    setConsistencyResult(result);
    store.pushDebugLog('consistency:check', 'checker', {
      status: result.status,
      failures: result.summary.failures,
      warnings: result.summary.warnings,
      lostFields: result.summary.lostFields,
      checkedPaths: result.summary.checkedPaths,
    });
  }, [isDebugMode, lastSavePayload, lastLoadPayload]);

  // Load DTP document from API
  const { data: apiData, isLoading, error: loadError } = useQuery({
    queryKey: ['dtp-document', siteId, issueId],
    queryFn: () => dtpDesigner.loadDocument(siteId, issueId).then((r: any) => r.data.data),
    retry: 1,
    refetchOnWindowFocus: false,
  });

  // DTP rollout status
  const { data: rolloutData, refetch: refetchRollout } = useQuery({
    queryKey: ['dtp-rollout', siteId, issueId],
    queryFn: () => dtpDesigner.getRolloutStatus(siteId, issueId).then((r: any) => r.data.data),
    retry: 1,
    refetchOnWindowFocus: false,
  });

  // Initialize store from API data (only once — not after save-triggered refetch)
  useEffect(() => {
    if (!apiData) return;
    if (initializedRef.current) return;
    initializedRef.current = true;

    if (isDebugMode) {
      setLastLoadPayload(structuredClone(apiData));
      store.pushDebugLog('load:start', 'editor', { pageCount: (apiData.pages || []).length, frameCount: (apiData.frames || []).length });
    }

    const pages = dtpApiToPages(apiData);
    setApiLayers(apiData.layers || []);
    setApiAssetRefs(apiData.asset_references || []);
    // Restore persisted masters (audit W0-6: they were editor-only before —
    // filtered from every save and regenerated with fresh IDs on every load,
    // which left page.master_page_id dangling)
    const savedMasters = normalizeMasterPages(apiData.meta?.masterPages || []);
    const allPages = savedMasters.length > 0 ? [...pages, ...savedMasters] : pages;
    // Restore persisted paragraph/character styles (audit defect #5)
    const savedStyles = apiData.meta?.styles || [];
    store.setDocument(allPages, savedStyles);

    // Restore issue settings from API metadata
    const savedSettings = apiData.meta?.issueSettings;
    if (savedSettings) {
      store.setIssueSettings(savedSettings);
      if (savedSettings.layoutMode === 'book') store.setViewMode('spread');
    }
    // Restore viewer settings
    const savedViewer = apiData.meta?.viewerSettings;
    if (savedViewer) setViewerSettings(prev => ({ ...prev, ...savedViewer }));

    // Create default master pages if none exist
    if (!allPages.some(p => p.isMaster)) {
      store.addMasterPage('A — Standard');
      store.addMasterPage('B — Editorial');
      // Add default elements to master A
      const masters = store.getMasterPages();
      const masterA = masters.find(m => ((m as any)._masterName || '').includes('Standard'));
      if (masterA) {
        const ps = masterA.pageSize;
        const mg = masterA.margins;
        store.updatePage(masterA.pageNumber, {
          elements: [
            makeFrame('page_number', 'Page Number', ps.width / 2 - 20, ps.height - mg.bottom + 10, 40, 20,
              { format: 'decimal', prefix: '', suffix: '', startAt: 1 }, masterA.pageNumber),
            makeFrame('decorative_rule', 'Footer Line', mg.left, ps.height - mg.bottom, ps.width - mg.left - mg.right, 2,
              { strokeColor: '#ccc', strokeWidth: 1, strokeStyle: 'solid', ornament: 'none' }, masterA.pageNumber),
          ],
        } as any);
      }
      const masterB = masters.find(m => ((m as any)._masterName || '').includes('Editorial'));
      if (masterB) {
        const ps = masterB.pageSize;
        const mg = masterB.margins;
        store.updatePage(masterB.pageNumber, {
          elements: [
            makeFrame('page_number', 'Page Number', ps.width / 2 - 20, ps.height - mg.bottom + 10, 40, 20,
              { format: 'decimal', prefix: '', suffix: '', startAt: 1 }, masterB.pageNumber),
            makeFrame('running_header', 'Header', mg.left, mg.top - 24, ps.width - mg.left - mg.right, 20,
              { source: 'custom', customText: 'Magazine Title' }, masterB.pageNumber),
            makeFrame('decorative_rule', 'Footer Line', mg.left, ps.height - mg.bottom, ps.width - mg.left - mg.right, 2,
              { strokeColor: '#999', strokeWidth: 1, strokeStyle: 'solid', ornament: 'none' }, masterB.pageNumber),
          ],
        } as any);
      }
    }
  }, [apiData]);

  // Load issue status from rollout data
  useEffect(() => {
    if (rolloutData?.issueStatus) setIssueStatus(rolloutData.issueStatus);
  }, [rolloutData]);

  // Verify flow on load: once fonts are ready, re-run the engine WITHOUT
  // pagination. Frames whose persisted _flowHash still matches keep their
  // slices byte-identical; mismatches (font/geometry drift) are re-sliced.
  // markDirty:false — verification alone must not flag unsaved changes.
  useEffect(() => {
    if (!apiData) return;
    let cancelled = false;
    const run = () => {
      if (!cancelled) useMagazineStore.getState().runFlow({ paginate: false, markDirty: false });
    };
    if (document.fonts?.ready) document.fonts.ready.then(run);
    else setTimeout(run, 300);
    return () => { cancelled = true; };
  }, [apiData]);

  // Status change mutation
  // Autosave (W3): every 30s while dirty; skipped mid-inline-edit and
  // while a save is already in flight. Reuses the exact manual-save path.
  const autosaveRef = useRef<() => void>(() => {});
  useEffect(() => {
    const t = setInterval(() => autosaveRef.current(), 30000);
    return () => clearInterval(t);
  }, []);
  const [lastAutosave, setLastAutosave] = useState<string | null>(null);
  const [findOpen, setFindOpen] = useState(false);
  const [shortcutsOpen, setShortcutsOpen] = useState(false);

  // Session E: pasted/dropped images go through the asset pipeline (server
  // does WebP variants) and land as image frames — not ignored, not base64.
  const handleImageFile = useCallback(async (file: File, x = 60, y = 60) => {
    try {
      const res = await assetsApi.upload(siteId!, file);
      const asset = res.data?.data || res.data;
      if (!asset?.id) return;
      const st = useMagazineStore.getState();
      const id = st.addElement('image_frame', x, y, 280, 200);
      const el = useMagazineStore.getState().pages.flatMap(p => p.elements).find(e2 => e2.id === id);
      useMagazineStore.getState().updateElement(id, {
        data: { ...(el?.data || {}), src: `/api/v1/sites/${siteId}/assets/${asset.id}/serve`, alt: file.name.replace(/\.[a-z0-9]+$/i, ''), fit: 'cover' },
      } as any);
    } catch (err) {
      console.error('image upload failed', err);
    }
  }, [siteId]);

  useEffect(() => {
    const onPaste = (e: ClipboardEvent) => {
      if (document.querySelector('[data-editing-id]')) return; // inline editor owns paste while typing
      const file = Array.from(e.clipboardData?.files || []).find(f => f.type.startsWith('image/'));
      if (file) {
        e.preventDefault();
        handleImageFile(file);
      }
    };
    window.addEventListener('paste', onPaste);
    return () => window.removeEventListener('paste', onPaste);
  }, [handleImageFile]);
  useEffect(() => {
    const open = () => setShortcutsOpen(true);
    window.addEventListener('mag:shortcuts', open);
    return () => window.removeEventListener('mag:shortcuts', open);
  }, []);
  const [largePaste, setLargePaste] = useState<{ html: string; elementId: string } | null>(null);
  useEffect(() => {
    const onLarge = (e: Event) => setLargePaste((e as CustomEvent).detail);
    window.addEventListener('mag:large-paste', onLarge);
    return () => window.removeEventListener('mag:large-paste', onLarge);
  }, []);
  // theme-token swatches (W3): the site's real palette for every color field
  const { data: themesData } = useQuery({
    queryKey: ['theme-swatches', siteId],
    queryFn: () => themeEngine.list(siteId!).then((r: any) => r.data),
    staleTime: 300000,
  });
  const swatches = useMemo(() => {
    const themes: any[] = themesData?.data || themesData || [];
    const active = themes.find?.((t: any) => t.is_active) || themes[0];
    const sw = extractColorSwatches(active?.config);
    return sw.length ? sw : DEFAULT_SWATCHES;
  }, [themesData]);
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f' && !e.shiftKey) {
        e.preventDefault();
        setFindOpen(true);
      }
      if (e.key === 'Escape') setFindOpen(false);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const statusMutation = useMutation({
    mutationFn: async (newStatus: string) => {
      await dtpDesigner.updateIssue(siteId, issueId, { status: newStatus });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['dtp-rollout', siteId, issueId] });
    },
  });

  // Save mutation
  const saveMutation = useMutation({
    mutationFn: async () => {
      setSaveError(null);
      if (isDebugMode) store.pushDebugLog('save:start', 'editor');
      // Flush any active contentEditable before save
      const editableEl = document.querySelector('[data-editing-id]') as HTMLElement;
      if (editableEl) {
        const editId = editableEl.getAttribute('data-editing-id');
        if (editId) {
          const DOMPurify = (await import('dompurify')).default;
          const html = DOMPurify.sanitize(editableEl.innerHTML, { ALLOWED_TAGS: ['p','br','b','i','u','em','strong','span','a','h1','h2','h3','h4','h5','h6','ul','ol','li','blockquote','sub','sup','hr','div','img'], ALLOWED_ATTR: ['href','target','rel','class','style','src','alt','width','height'], ALLOW_DATA_ATTR: false });
          const el = useMagazineStore.getState().pages.flatMap(p => p.elements).find(e => e.id === editId);
          if (el) store.updateElement(editId, { data: { ...el.data, content: html } } as any);
        }
      }
      // Auto-flow overflowing text to next pages before save
      store.autoFlowText();
      // Read fresh state after autoFlowText modified pages
      const freshState = useMagazineStore.getState();
      // Filter out master pages — they're editor-only, not saved to API
      const contentPages = freshState.pages.filter(p => !p.isMaster);
      const payload = pagesToDtpApi(contentPages, apiLayers, apiAssetRefs, freshState.issueSettings, viewerSettings, {
        styles: freshState.styles,
        masterPages: freshState.pages.filter(p => p.isMaster),
      });
      if (isDebugMode) {
        setLastSavePayload(structuredClone(payload));
        store.pushDebugLog('save:payload', 'editor', {
          pageCount: (payload.pages as any[])?.length,
          frameCount: (payload.frames as any[])?.length,
          spreadCount: (payload.spreads as any[])?.length,
        });
      }
      await dtpDesigner.saveDocument(siteId, issueId, payload);
    },
    onSuccess: () => {
      store.setDirty(false);
      if (isDebugMode) {
        store.pushDebugLog('save:success', 'editor');
        // Auto-run consistency check after successful save
        setTimeout(() => runConsistency(), 100);
      }
      queryClient.invalidateQueries({ queryKey: ['dtp-document', siteId, issueId] });
      queryClient.invalidateQueries({ queryKey: ['dtp-rollout', siteId, issueId] });
    },
    onError: (err: any) => {
      const msg = err?.response?.data?.message || err?.message || 'Save failed';
      setSaveError(msg);
      if (isDebugMode) store.pushDebugLog('save:fail', 'editor', { error: msg }, 'error');
    },
  });

  useEffect(() => {
    autosaveRef.current = () => {
      const st = useMagazineStore.getState();
      if (!st.isDirty || saveMutation.isPending) return;
      if (document.querySelector('[data-editing-id]')) return; // typing — stay out of the way
      saveMutation.mutate(undefined, {
        onSuccess: () => setLastAutosave(new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })),
      });
    };
  });

  // Current page & selection
  const rawPage = store.pages.find(p => p.pageNumber === store.currentPageNumber) || store.pages[0];
  const currentPage: MagPageData | null = rawPage ? {
    ...rawPage,
    pageSize: rawPage.pageSize || { width: 595, height: 842 },
    margins: rawPage.margins || { top: 36, right: 36, bottom: 36, left: 36 },
    bleed: rawPage.bleed || { top: 9, right: 9, bottom: 9, left: 9 },
    columns: rawPage.columns || { count: 1, gutter: 12 },
    baselineGrid: rawPage.baselineGrid || { increment: 14, start: 36 },
    elements: rawPage.elements || [],
  } : null;
  const currentElements = currentPage?.elements || [];
  const selectedEl = currentElements.find(e => store.selectedIds.includes(e.id)) || null;

  // Thread helpers
  const allElements = store.pages.flatMap(p => p.elements || []);
  const getThreadFrameCount = (tid: string) => allElements.filter(e => e.threadId === tid).length;

  const handleStartThread = () => {
    if (!selectedEl) return;
    const tid = crypto.randomUUID();
    store.updateElement(selectedEl.id, { threadId: tid, threadOrder: 0 } as any);
    setActiveThreadId(tid);
  };

  const handleContinueThread = () => {
    if (!selectedEl || !activeThreadId) return;
    const order = allElements.filter(e => e.threadId === activeThreadId).length;
    store.updateElement(selectedEl.id, { threadId: activeThreadId, threadOrder: order } as any);
  };

  const handleUnthread = () => {
    if (!selectedEl || !selectedEl.threadId) return;
    const tid = selectedEl.threadId;
    store.updateElement(selectedEl.id, { threadId: null, threadOrder: null } as any);
    const remaining = allElements
      .filter(e => e.threadId === tid && e.id !== selectedEl.id)
      .sort((a, b) => (a.threadOrder ?? 0) - (b.threadOrder ?? 0));
    remaining.forEach((e, i) => {
      if ((e.threadOrder ?? 0) !== i) store.updateElement(e.id, { threadOrder: i } as any);
    });
  };

  // Auto-switch tab on selection
  useEffect(() => {
    if (store.selectedIds.length > 0) setRightTab('properties');
  }, [store.selectedIds]);

  const IMAGE_TYPES = ['image_frame', 'circular_image', 'polygon_image', 'fullbleed_image', 'gallery_frame', 'background_image'];

  const handleAddElement = (type: string, x: number, y: number, w: number, h: number) => {
    store.addElement(type, x, y, w, h);
    if (IMAGE_TYPES.includes(type)) setAutoOpenImagePicker(true);
  };

  // ─── Loading / Error states ───
  // Listen for clicks on inline images inside contentEditable (only when editing)
  useEffect(() => {
    if (!canvasEditingId) return;
    const handler = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      if (target.tagName === 'IMG' && target.closest('[data-editing-id]')) {
        setSelectedInlineImg(target as HTMLImageElement);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [canvasEditingId]);

  // Clear inline image selection when exiting edit mode or clicking elsewhere
  useEffect(() => {
    if (!canvasEditingId) { setSelectedInlineImg(null); return; }
    const handler = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      if (selectedInlineImg && target.tagName !== 'IMG' && !target.closest('.inline-img-panel')) {
        setSelectedInlineImg(null);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [canvasEditingId, selectedInlineImg]);

  // Helper to update inline image style and persist to store (debounced)
  const inlineImgPersistTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  useEffect(() => {
    return () => { if (inlineImgPersistTimer.current) clearTimeout(inlineImgPersistTimer.current); };
  }, []);
  const updateInlineImgStyle = (updates: Record<string, string>) => {
    if (!selectedInlineImg || !selectedInlineImg.isConnected) return;
    Object.entries(updates).forEach(([k, v]) => { selectedInlineImg.style[k as any] = v; });
    store.setDirty(true);
    // Debounce persist to avoid store thrashing on slider drag
    if (inlineImgPersistTimer.current) clearTimeout(inlineImgPersistTimer.current);
    inlineImgPersistTimer.current = setTimeout(() => {
      const editable = selectedInlineImg?.closest('[data-editing-id]') as HTMLElement;
      if (editable && canvasEditingId) {
        const html = editable.innerHTML;
        const el = useMagazineStore.getState().pages.flatMap(p => p.elements).find(e => e.id === canvasEditingId);
        if (el) store.updateElement(canvasEditingId, { data: { ...el.data, content: html } } as any);
      }
    }, 300);
  };

  // Add selection outline to selected inline image
  useEffect(() => {
    if (selectedInlineImg && selectedInlineImg.isConnected) {
      selectedInlineImg.style.outline = '2px solid #3b82f6';
      selectedInlineImg.style.outlineOffset = '2px';
      return () => {
        try { selectedInlineImg.style.outline = ''; selectedInlineImg.style.outlineOffset = ''; } catch(_) {}
      };
    }
  }, [selectedInlineImg]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-screen bg-base-200">
        <Loader2 className="h-8 w-8 animate-spin text-base-content/20" />
        <span className="ml-3 text-base-content/40">Loading DTP document...</span>
      </div>
    );
  }

  if (loadError) {
    const is404 = (loadError as any)?.response?.status === 404;
    return (
      <div className="flex flex-col items-center justify-center h-screen bg-base-200 text-base-content">
        <AlertTriangle className="h-12 w-12 text-warning mb-4" />
        <h2 className="text-lg font-semibold mb-2">{is404 ? 'DTP Designer Not Available' : 'Failed to Load'}</h2>
        <p className="text-sm text-base-content/40 mb-4">
          {is404 ? 'The DTP Designer feature is not enabled for this site.' : (loadError as Error).message}
        </p>
        <button onClick={() => navigate(-1)} className="btn btn-sm btn-ghost">Go Back</button>
      </div>
    );
  }

  if (!currentPage || store.pages.length === 0) {
    return <div className="flex items-center justify-center h-screen bg-base-200"><span className="loading loading-spinner loading-sm text-base-content/20" /></div>;
  }

  const adminTheme = localStorage.getItem('admin-theme') || 'cms-admin';

  return (
    <MagSwatchContext.Provider value={swatches}>
    <div className="flex flex-col h-screen bg-base-200" data-theme={adminTheme}>
      {/* ─── Toolbar ─── */}
      <MagazineToolbar
        onBack={() => navigate(`/sites/${siteId}/magazines`)}
        activeTool={store.activeTool}
        onSetTool={(t) => store.setTool(t as any)}
        zoom={store.zoom}
        onZoomChange={store.setZoom}
        currentPage={store.currentPageNumber}
        totalPages={store.pages.length}
        onChangePage={store.setCurrentPage}
        showGrid={store.showGrid}
        showGuides={store.showGuides}
        showBaseline={store.showBaseline}
        onToggleGrid={store.toggleGrid}
        onToggleGuides={store.toggleGuides}
        onToggleBaseline={store.toggleBaseline}
        onUndo={store.undo}
        onRedo={store.redo}
        canUndo={store.undoStack.length > 0}
        canRedo={store.redoStack.length > 0}
        onSave={() => saveMutation.mutate()}
        lastAutosave={lastAutosave}
        isDirty={store.isDirty}
        isSaving={saveMutation.isPending}
        status={issueStatus}
        onStatusChange={(s) => { setIssueStatus(s); statusMutation.mutate(s); }}
        viewUrl={(() => {
          const domain = rolloutData?.links?.publicDomain;
          const path = `/magazine/dtp/${issueId}`;
          return domain ? `https://${domain}${path}` : path;
        })()}
        pdfUrl={`/api/v1/sites/${siteId}/magazine-issues/${issueId}/dtp-pdf`}
        zipUrl={`/api/v1/sites/${siteId}/magazine-issues/${issueId}/dtp-zip`}
      />
      {findOpen && <FindReplacePanel onClose={() => setFindOpen(false)} />}
      {shortcutsOpen && <ShortcutSheet onClose={() => setShortcutsOpen(false)} />}
      {largePaste && <LargePasteDialog paste={largePaste} onClose={() => setLargePaste(null)} />}

      {/* ─── DTP Status + Save error ─── */}
      {saveError && (
        <div className="flex items-center gap-2 px-4 py-2 bg-error/10 border-b border-error/20 text-error text-[12px]">
          <span className="font-medium">Save failed:</span> {saveError}
          <button onClick={() => setSaveError(null)} className="ml-auto btn btn-ghost btn-xs text-error">Dismiss</button>
        </div>
      )}

      {showStatusPanel && rolloutData && (
        <div className="bg-base-300/30 border-b border-base-300/20 px-4 py-2 shrink-0">
          <div className="flex flex-wrap gap-4 text-[11px]">
            <div className="flex items-center gap-1.5">
              <span className="text-base-content/40">Rollout:</span>
              <span className={`px-1.5 py-0.5 rounded font-medium ${
                rolloutData.status === 'dtp_ready' ? 'bg-success/10 text-success' :
                rolloutData.status === 'dtp_beta' ? 'bg-warning/10 text-warning' :
                'bg-base-200 text-base-content/40'
              }`}>
                {rolloutData.status === 'dtp_ready' ? 'Ready' : rolloutData.status === 'dtp_beta' ? 'Beta' : 'Legacy'}
              </span>
            </div>
            <div className="flex items-center gap-1.5">
              <span className="text-base-content/40">Preview:</span>
              {rolloutData.capabilities?.previewLinkAvailable && rolloutData.links?.dtpPreview ? (
                <a href={rolloutData.links.dtpPreview} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline flex items-center gap-1">Available <ExternalLink size={9} /></a>
              ) : <span className="text-base-content/30">Not available</span>}
            </div>
            <div className="flex items-center gap-1.5">
              <span className="text-base-content/40">Render:</span>
              <span className={rolloutData.capabilities?.previewRenderable ? 'text-success' : 'text-warning'}>
                {rolloutData.capabilities?.previewRenderable ? 'OK' : 'Not ready'}
              </span>
            </div>
            {rolloutData.preflight && (
              <div className="flex items-center gap-1.5">
                <span className="text-base-content/40">Preflight:</span>
                <span className={rolloutData.preflight.status === 'pass' ? 'text-success' : rolloutData.preflight.status === 'warning' ? 'text-warning' : 'text-error'}>
                  {rolloutData.preflight.status === 'pass' ? 'Pass' : rolloutData.preflight.status === 'warning' ? 'Warnings' : 'Errors'} ({rolloutData.preflight.score}/100)
                </span>
              </div>
            )}
            {rolloutData.blockingReasons?.length > 0 && (
              <span className="text-error text-[10px]">{rolloutData.blockingReasons.join(' ')}</span>
            )}
            <button onClick={() => refetchRollout()} className="text-base-content/30 hover:text-base-content text-[10px]">↻ Refresh</button>
          </div>
        </div>
      )}

      <div className="flex flex-1 overflow-hidden">
        {/* ─── LEFT: Page navigator ─── */}
        <PageNavigator
          pages={store.pages.filter(p => !p.isMaster)}
          currentPage={store.currentPageNumber}
          onChangePage={store.setCurrentPage}
          onAddPage={() => store.addPage(store.currentPageNumber)}
          onDeletePage={(n) => store.deletePage(n)}
          onDuplicatePage={(n) => store.duplicatePage(n)}
          onReorderPages={(from, to) => store.reorderPages(from, to)}
          onApplyTemplate={(pageNumber, frames) => {
            store.pushSnapshot();
            const page = store.pages.find(p => p.pageNumber === pageNumber);
            if (!page) return;
            store.updatePage(pageNumber, { elements: [...page.elements, ...frames] } as any);
          }}
          masterPages={store.pages.filter(p => p.isMaster)}
          onAssignMaster={(pageNumber, masterPageId) => store.assignMaster(pageNumber, masterPageId)}
          onAssignMasterToAll={(masterPageId) => store.assignMasterToAll(masterPageId)}
          onEditMaster={(masterPageId) => store.setEditingMaster(masterPageId)}
          onSetMasterApplies={(masterPageId, v) => {
            const master = store.pages.find(p => p.isMaster && p.id === masterPageId);
            if (master) store.updatePage(master.pageNumber, { _appliesTo: v } as any);
          }}
          onDetachMaster={(pageNumber) => store.detachMaster(pageNumber)}
          editingMasterId={store.editingMasterId}
        />

        {/* ─── CENTER: Canvas ─── */}
        <MagazineCanvas
          page={currentPage}
          allPages={store.pages}
          viewMode={store.viewMode}
          gridColumns={store.gridColumns}
          elements={currentElements}
          zoom={store.zoom}
          onZoomChange={store.setZoom}
          onUpdateElement={(id, updates) => store.updateElement(id, updates)}
          onAddElement={handleAddElement}
          onDeleteElements={(ids) => store.deleteElements(ids)}
          onDuplicateElements={(ids) => store.duplicateElements(ids)}
          onSelectElement={(id) => id ? store.selectElement(id) : store.clearSelection()}
          onEditingChange={(id) => { setCanvasEditingId(id); if (!id) setStartEditingRequest(null); }}
          startEditingId={startEditingRequest}
          layoutMode={store.issueSettings.layoutMode}
          coverMode={store.issueSettings.coverMode}
          onToggleFixed={(id, mode) => store.updateElement(id, { positionMode: mode } as any)}
          onToggleSpan={(id, mode) => store.updateElement(id, { spanMode: mode } as any)}
          onContinueText={(elementId) => store.continueTextToNextPage(elementId)}
          oversetThreads={store.oversetThreads}
          onNavigateThread={(pageNumber, frameId) => { store.setCurrentPage(pageNumber); store.selectElement(frameId); }}
          onImageDrop={(file, x, y) => handleImageFile(file, x, y)}
          onMoveToPage={(elementId, direction, newX, newY) => {
            const currentPageNum = store.currentPageNumber;
            const contentPages = store.pages.filter(p => !p.isMaster).sort((a, b) => a.pageNumber - b.pageNumber);
            const currentIdx = contentPages.findIndex(p => p.pageNumber === currentPageNum);
            const targetIdx = direction === 'next' ? currentIdx + 1 : currentIdx - 1;
            if (targetIdx >= 0 && targetIdx < contentPages.length) {
              store.moveElementToPage(elementId, currentPageNum, contentPages[targetIdx].pageNumber, newX, newY);
            }
          }}
          onPageClick={(n) => {
            if (n === -1) store.setViewMode('single');
            else if (n === -2) store.setViewMode('spread');
            else if (n === -3) store.setViewMode('grid');
            else if (n <= -10) store.setGridColumns(-(n + 10));
            else store.setCurrentPage(n);
          }}
        />

        {/* ─── RIGHT: Properties / Add / Layers / Styles ─── */}
        <div className="w-72 bg-base-100 border-l border-base-300/30 flex flex-col shrink-0">
          <div className="flex border-b border-base-300/20 shrink-0">
            {([
              { key: 'add' as RightTab, label: '+ Add' },
              { key: 'properties' as RightTab, label: 'Props' },
              { key: 'layers' as RightTab, label: 'Layers' },
              { key: 'styles' as RightTab, label: 'Styles' },
              { key: 'issue' as RightTab, label: 'Issue' },
            ]).map(tab => (
              <button key={tab.key} onClick={() => setRightTab(tab.key)}
                className={`flex-1 px-2 py-2 text-[11px] font-medium transition-colors ${rightTab === tab.key ? 'border-b-2 border-primary text-primary' : 'text-base-content/40'}`}>
                {tab.label}
              </button>
            ))}
          </div>

          <div className="flex-1 overflow-y-auto">
            {rightTab === 'add' && <MagElementPalette onAddElement={handleAddElement} />}

            {rightTab === 'properties' && (
              <div className="p-3 space-y-4">
                {selectedEl ? (
                  <>
                    <div className="flex items-center justify-between">
                      <div className="text-[10px] text-base-content/30 uppercase tracking-wider">{selectedEl.type.replace(/_/g, ' ')}</div>
                      {['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'].includes(selectedEl.type) && (
                        <button
                          onClick={() => {
                            if (canvasEditingId === selectedEl.id) {
                              // Done — tell canvas to exit editing via sentinel value
                              setStartEditingRequest('__exit__');
                            } else {
                              setStartEditingRequest(selectedEl.id);
                            }
                          }}
                          className={`btn btn-xs gap-1 ${canvasEditingId === selectedEl.id ? 'btn-success' : 'btn-primary'}`}
                        >
                          {canvasEditingId === selectedEl.id ? '✓ Done' : '✎ Edit'}
                        </button>
                      )}
                    </div>
                    {/* Text flow controls */}
                    {['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'].includes(selectedEl.type) && (
                      <div className="flex gap-1">
                        <button
                          onClick={() => store.continueTextToNextPage(selectedEl.id)}
                          className="btn btn-sm btn-primary flex-1 gap-1"
                        >
                          Pour →
                        </button>
                        <button
                          onClick={() => { store.autoFlowText(); }}
                          className="btn btn-sm btn-secondary flex-1 gap-1"
                          title="Auto-flow all overflowing text to next pages"
                        >
                          Auto-flow
                        </button>
                      </div>
                    )}
                    {/* Fix/Unfix + Spread controls for image frames */}
                    {IMAGE_TYPES.includes(selectedEl.type) && !selectedEl.locked && (
                      <div className="flex gap-1">
                        <button
                          onClick={() => store.updateElement(selectedEl.id, { positionMode: selectedEl.positionMode === 'fixed' ? 'free' : 'fixed' } as any)}
                          className={`btn btn-xs flex-1 gap-1 ${selectedEl.positionMode === 'fixed' ? 'btn-warning' : 'btn-ghost'}`}
                          title={selectedEl.positionMode === 'fixed' ? 'Unfix — allow text to overlap' : 'Fix — text flows around this image'}
                        >
                          {selectedEl.positionMode === 'fixed' ? 'Unfix' : 'Fix Position'}
                        </button>
                        <button
                          onClick={() => store.updateElement(selectedEl.id, { spanMode: selectedEl.spanMode === 'spread' ? 'page' : 'spread' } as any)}
                          className={`btn btn-xs flex-1 gap-1 ${selectedEl.spanMode === 'spread' ? 'btn-secondary' : 'btn-ghost'}`}
                          title={selectedEl.spanMode === 'spread' ? 'Single page' : 'Span across spread'}
                        >
                          {selectedEl.spanMode === 'spread' ? 'Single Page' : 'Spread'}
                        </button>
                      </div>
                    )}
                    <TransformPanel x={selectedEl.x} y={selectedEl.y} width={selectedEl.width} height={selectedEl.height} rotation={selectedEl.rotation}
                      onChange={(updates) => store.updateElement(selectedEl.id, updates as Partial<MagElement>)} />
                    {['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'].includes(selectedEl.type) && (
                      <RichTextToolbar
                        isEditing={canvasEditingId === selectedEl.id}
                        onStartEditing={() => setStartEditingRequest(selectedEl.id)}
                        elementId={selectedEl.id}
                        onFormatText={(command, value) => {
                          const editable = document.querySelector('[data-editing-id]') as HTMLElement
                            ?? document.querySelector('[contenteditable="true"]') as HTMLElement;
                          if (!editable) return;

                          // Restore saved selection inside the contentEditable
                          editable.focus();
                          const savedSel = (window as any).__dtpSavedSelection as Range | null;
                          if (savedSel) {
                            try {
                              const sel = window.getSelection();
                              if (sel) {
                                sel.removeAllRanges();
                                sel.addRange(savedSel);
                              }
                            } catch (_) {}
                          }
                          document.execCommand(command, false, value);

                          // Re-save selection after formatting
                          try {
                            const sel = window.getSelection();
                            if (sel && sel.rangeCount > 0) {
                              (window as any).__dtpSavedSelection = sel.getRangeAt(0).cloneRange();
                            }
                          } catch (_) {}
                        }}
                        onInsertImage={() => setInlineImagePickerOpen(true)}
                      />
                    )}
                    {/* Inline image properties panel */}
                    {selectedInlineImg && canvasEditingId && (
                      <div className="inline-img-panel space-y-3 p-3 bg-base-200/50 rounded border border-base-300/30">
                        <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Inline Image</h3>

                        {/* Size */}
                        <div>
                          <label className="text-[10px] text-base-content/40 mb-1 block">Width</label>
                          <div className="flex gap-1 items-center">
                            <input name="mag-dtpeditorbeta-1" type="range" min="10" max="100" step="5"
                              value={parseInt(selectedInlineImg.style.width) || 40}
                              onChange={(e) => updateInlineImgStyle({ width: e.target.value + '%' })}
                              className="range range-xs range-primary flex-1" />
                            <span className="text-[10px] text-base-content/50 w-10 text-right">{parseInt(selectedInlineImg.style.width) || 40}%</span>
                          </div>
                        </div>

                        {/* Float position */}
                        <div>
                          <label className="text-[10px] text-base-content/40 mb-1 block">Position</label>
                          <div className="flex gap-1">
                            {(['left', 'right', 'none'] as const).map(pos => (
                              <button key={pos} onClick={() => updateInlineImgStyle({
                                float: pos,
                                margin: pos === 'left' ? '0 12px 8px 0' : pos === 'right' ? '0 0 8px 12px' : '8px auto',
                                display: pos === 'none' ? 'block' : '',
                              })}
                                className={`btn btn-xs flex-1 ${(selectedInlineImg.style.float || 'left') === pos ? 'btn-primary' : 'btn-ghost'}`}>
                                {pos === 'left' ? 'Left' : pos === 'right' ? 'Right' : 'Center'}
                              </button>
                            ))}
                          </div>
                        </div>

                        {/* Border radius */}
                        <div>
                          <label className="text-[10px] text-base-content/40 mb-1 block">Border Radius</label>
                          <div className="flex gap-1 items-center">
                            <input name="mag-dtpeditorbeta-2" type="range" min="0" max="50" step="2"
                              value={parseInt(selectedInlineImg.style.borderRadius) || 0}
                              onChange={(e) => updateInlineImgStyle({ borderRadius: e.target.value + 'px' })}
                              className="range range-xs flex-1" />
                            <span className="text-[10px] text-base-content/50 w-10 text-right">{parseInt(selectedInlineImg.style.borderRadius) || 0}px</span>
                          </div>
                        </div>

                        {/* Border */}
                        <div>
                          <label className="text-[10px] text-base-content/40 mb-1 block">Border Width</label>
                          <div className="flex gap-1 items-center">
                            <input name="mag-dtpeditorbeta-3" type="range" min="0" max="10" step="1"
                              value={parseInt(selectedInlineImg.style.borderWidth) || 0}
                              onChange={(e) => {
                                const w = e.target.value;
                                updateInlineImgStyle({
                                  borderWidth: w + 'px',
                                  borderStyle: parseInt(w) > 0 ? 'solid' : 'none',
                                  borderColor: selectedInlineImg.style.borderColor || '#000000',
                                });
                              }}
                              className="range range-xs flex-1" />
                            <span className="text-[10px] text-base-content/50 w-10 text-right">{parseInt(selectedInlineImg.style.borderWidth) || 0}px</span>
                          </div>
                        </div>

                        {/* Border color */}
                        {(parseInt(selectedInlineImg.style.borderWidth) || 0) > 0 && (
                          <div>
                            <label className="text-[10px] text-base-content/40 mb-1 block">Border Color</label>
                            <input name="mag-dtpeditorbeta-4" type="color"
                              value={selectedInlineImg.style.borderColor || '#000000'}
                              onChange={(e) => updateInlineImgStyle({ borderColor: e.target.value })}
                              className="w-8 h-8 rounded cursor-pointer border border-base-300/30" />
                          </div>
                        )}

                        {/* Padding (space between border and image) */}
                        <div>
                          <label className="text-[10px] text-base-content/40 mb-1 block">Inner Padding</label>
                          <div className="flex gap-1 items-center">
                            <input name="mag-dtpeditorbeta-5" type="range" min="0" max="20" step="2"
                              value={parseInt(selectedInlineImg.style.padding) || 0}
                              onChange={(e) => updateInlineImgStyle({ padding: e.target.value + 'px' })}
                              className="range range-xs flex-1" />
                            <span className="text-[10px] text-base-content/50 w-10 text-right">{parseInt(selectedInlineImg.style.padding) || 0}px</span>
                          </div>
                        </div>

                        {/* Delete */}
                        <button onClick={() => {
                          const editable = selectedInlineImg.closest('[data-editing-id]') as HTMLElement;
                          selectedInlineImg.remove();
                          setSelectedInlineImg(null);
                          // Persist removal to store
                          if (editable && canvasEditingId) {
                            const el = store.pages.flatMap(p => p.elements).find(e => e.id === canvasEditingId);
                            if (el) store.updateElement(canvasEditingId, { data: { ...el.data, content: editable.innerHTML } } as any);
                          }
                          store.setDirty(true);
                        }}
                          className="btn btn-xs btn-ghost text-error w-full">Remove Image</button>
                      </div>
                    )}
                    {['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame'].includes(selectedEl.type) && selectedEl.typography && (
                      <MagTypographyPanel value={selectedEl.typography} onChange={(v) => store.updateElement(selectedEl.id, { typography: { ...selectedEl.typography!, ...v } as MagTypography })} />
                    )}
                    {['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame'].includes(selectedEl.type) && (
                      <TextFramePanel
                        data={(selectedEl.data || {}) as unknown as TextFrameData}
                        onChange={(v) => store.updateElement(selectedEl.id, { data: { ...selectedEl.data, ...v } })}
                        threadId={selectedEl.threadId}
                        threadInfo={selectedEl.threadId ? {
                          position: allElements.filter(e => e.threadId === selectedEl.threadId && (e.threadOrder ?? 0) <= (selectedEl.threadOrder ?? 0)).length,
                          total: getThreadFrameCount(selectedEl.threadId),
                        } : undefined}
                        onStartThread={handleStartThread}
                        onContinueThread={handleContinueThread}
                        onUnthread={handleUnthread}
                        availableThreadId={activeThreadId}
                      />
                    )}
                    {store.editingMasterId && ['text_frame'].includes(selectedEl.type) && (
                      <div className="px-3 py-2 border-t border-base-300/20">
                        <label className="flex items-center gap-1.5 text-[11px] text-base-content/60 cursor-pointer"
                          title="New pages created from this master get an editable copy of this frame as their body text frame">
                          <input name="mag-dtpeditorbeta-6" type="checkbox" className="checkbox checkbox-xs"
                            checked={!!(selectedEl.data as any)?._primaryFlow}
                            onChange={(e) => store.updateElement(selectedEl.id, { data: { ...selectedEl.data, _primaryFlow: e.target.checked || undefined } })} />
                          Primary text frame (body on new pages)
                        </label>
                      </div>
                    )}
                    {selectedEl.type === 'table_frame' && (
                      <TablePanel
                        data={(selectedEl.data || {}) as any}
                        onChange={(v) => store.updateElement(selectedEl.id, { data: { ...selectedEl.data, ...v } })}
                      />
                    )}
                    {IMAGE_TYPES.includes(selectedEl.type) && (
                      <ImagePanel
                        data={(selectedEl.data || {}) as unknown as ImageFrameData}
                        onChange={(v) => store.updateElement(selectedEl.id, { data: { ...selectedEl.data, ...v } })}
                        autoOpen={autoOpenImagePicker}
                        onAutoOpenDone={() => setAutoOpenImagePicker(false)}
                      />
                    )}
                    {/* Step and repeat (W2-6) */}
                    <div className="px-3 py-2 border-t border-base-300/20">
                      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-1.5">Step and repeat</h3>
                      <div className="flex items-end gap-1.5">
                        <div><label htmlFor="sr-count" className="text-[9px] text-base-content/40 block">Count</label>
                          <input id="sr-count" type="number" min={1} max={50} defaultValue={3} className="input input-bordered input-xs w-14" /></div>
                        <div><label htmlFor="sr-dx" className="text-[9px] text-base-content/40 block">ΔX</label>
                          <input id="sr-dx" type="number" defaultValue={20} className="input input-bordered input-xs w-14" /></div>
                        <div><label htmlFor="sr-dy" className="text-[9px] text-base-content/40 block">ΔY</label>
                          <input id="sr-dy" type="number" defaultValue={0} className="input input-bordered input-xs w-14" /></div>
                        <button className="btn btn-xs btn-ghost" onClick={() => {
                          const g = (id: string) => Number((document.getElementById(id) as HTMLInputElement)?.value) || 0;
                          store.stepAndRepeat(store.selectedIds, g('sr-count'), g('sr-dx'), g('sr-dy'));
                        }}>Apply</button>
                      </div>
                    </div>
                    <FillStrokePanel style={selectedEl.style} onChange={(v) => store.updateElement(selectedEl.id, { style: { ...selectedEl.style, ...v } as MagElementStyle })} />
                    <EffectsPanel style={selectedEl.style} onChange={(v) => store.updateElement(selectedEl.id, { style: { ...selectedEl.style, ...v } as MagElementStyle })} />
                    {selectedEl.textWrap && (
                      <TextWrapPanel value={selectedEl.textWrap} onChange={(v) => store.updateElement(selectedEl.id, { textWrap: { ...selectedEl.textWrap, ...v } as MagTextWrap })} />
                    )}
                    {store.selectedIds.length > 1 && (
                      <AlignDistributePanel
                        onAlign={(_type: string) => {
                          // Align handled by MagazineCanvas internally
                        }}
                        onDistribute={(_type: string) => {
                          // Distribute handled by MagazineCanvas internally
                        }}
                        alignToPage={alignToPage}
                        onToggleAlignToPage={() => setAlignToPage(!alignToPage)}
                      />
                    )}
                  </>
                ) : (
                  <div className="py-8 text-center">
                    <PagePanel page={currentPage} onChange={(updates: Partial<MagPageData>) => store.updatePage(store.currentPageNumber, updates)} />
                  </div>
                )}
              </div>
            )}

            {rightTab === 'layers' && (
              <MagLayersPanel
                elements={currentElements}
                selectedIds={store.selectedIds}
                onSelect={(id: string) => store.selectElement(id)}
                onToggleVisibility={(id: string) => {
                  const el = currentElements.find(e => e.id === id);
                  if (el) store.updateElement(id, { visible: !el.visible });
                }}
                onToggleLock={(id: string) => {
                  const el = currentElements.find(e => e.id === id);
                  if (el) store.updateElement(id, { locked: !el.locked });
                }}
                onReorderZ={(id: string, direction: 'up' | 'down') => {
                  // ONE-step arrange (W2-5) — buttons previously jumped to front/back
                  if (direction === 'up') store.bringForward(id);
                  else store.sendBackward(id);
                }}
              />
            )}

            {rightTab === 'styles' && (
              <StylesPanel
                styles={store.styles}
                selectedElementId={store.selectedIds[0] || null}
                onApplyStyle={(styleId: string) => {
                  if (selectedEl) {
                    const style = store.styles.find(s => s.id === styleId);
                    if (style && selectedEl.typography) {
                      store.updateElement(selectedEl.id, { typography: { ...selectedEl.typography, ...style.properties, paragraphStyleId: styleId } as MagTypography });
                    }
                  }
                }}
                onCreateStyle={(type: 'paragraph' | 'character') => {
                  // W1-7: create captures the SELECTED frame's typography
                  // ("redefine from selection" light) — styles persist via
                  // layout_final meta since W0-6
                  store.addStyle({
                    id: crypto.randomUUID(),
                    name: `${type === 'paragraph' ? 'Paragraph' : 'Character'} style ${store.styles.length + 1}`,
                    type,
                    properties: selectedEl?.typography ? { ...selectedEl.typography } : {},
                    basedOnId: null,
                    nextStyleId: null,
                    isDefault: false,
                  });
                }}
                onDeleteStyle={(id: string) => store.deleteStyle(id)}
              />
            )}

            {rightTab === 'issue' && (
              <div className="p-3 space-y-4">
                <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Issue Settings</h3>

                <div>
                  <label className="text-[10px] text-base-content/40 mb-1 block">Layout Mode</label>
                  <div className="flex flex-col gap-1">
                    {([
                      { value: 'single' as const, label: 'Single Page', desc: 'Pages stacked vertically' },
                      { value: 'book' as const, label: 'Magazine / Book', desc: 'Cover alone, inside pages side-by-side' },
                      { value: 'presentation' as const, label: 'Presentation', desc: 'One slide at a time' },
                    ]).map(opt => (
                      <button key={opt.value}
                        onClick={() => {
                          store.setIssueSettings({ layoutMode: opt.value });
                          // Auto-switch view mode to match
                          if (opt.value === 'book') store.setViewMode('spread');
                          else store.setViewMode('single');
                        }}
                        className={`text-left px-3 py-2 rounded border text-[11px] transition-colors ${
                          store.issueSettings.layoutMode === opt.value
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-base-300/30 text-base-content/60 hover:border-base-300/50'
                        }`}>
                        <div className="font-medium">{opt.label}</div>
                        <div className="text-[9px] opacity-60">{opt.desc}</div>
                      </button>
                    ))}
                  </div>
                </div>

                {store.issueSettings.layoutMode === 'book' && (
                  <div>
                    <label className="text-[10px] text-base-content/40 mb-1 block">Cover Page</label>
                    <div className="flex gap-1">
                      <button onClick={() => store.setIssueSettings({ coverMode: 'standalone' })}
                        className={`btn btn-xs flex-1 ${store.issueSettings.coverMode === 'standalone' ? 'btn-primary' : 'btn-ghost'}`}>
                        Cover Alone
                      </button>
                      <button onClick={() => store.setIssueSettings({ coverMode: 'spread' })}
                        className={`btn btn-xs flex-1 ${store.issueSettings.coverMode === 'spread' ? 'btn-primary' : 'btn-ghost'}`}>
                        Cover in Spread
                      </button>
                    </div>
                  </div>
                )}

                <div className="text-[9px] text-base-content/30 bg-base-200/50 rounded p-2">
                  {store.issueSettings.layoutMode === 'single' && 'Use Single view in the toolbar for vertical page layout.'}
                  {store.issueSettings.layoutMode === 'book' && 'Use Spread view in the toolbar to see side-by-side pages.'}
                  {store.issueSettings.layoutMode === 'presentation' && 'Use Single view for slide-by-slide navigation.'}
                </div>

                {/* ─── Preflight v2 (W3): clickable checks, jump-to-issue ─── */}
                {(() => {
                  const issues = runPreflight(store.pages, store.oversetThreads);
                  return (
                    <div className="pb-4 border-b border-base-300/20">
                      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">
                        Preflight {issues.length === 0
                          ? <span className="text-success normal-case">— all clear ✓</span>
                          : <span className={issues.some(i => i.severity === 'error') ? 'text-error normal-case' : 'text-warning normal-case'}>— {issues.length} issue{issues.length > 1 ? 's' : ''}</span>}
                      </h3>
                      <div className="space-y-1 max-h-56 overflow-y-auto">
                        {issues.map((iss, n) => (
                          <button key={n}
                            className="w-full text-left flex items-start gap-1.5 px-1.5 py-1 rounded hover:bg-base-300/20 transition-colors"
                            title="Click to jump to this issue"
                            onClick={() => {
                              store.setCurrentPage(iss.pageNumber);
                              if (iss.elementId) store.selectElement(iss.elementId);
                              setRightTab('properties');
                            }}>
                            <span className={`mt-0.5 w-2 h-2 rounded-full shrink-0 ${iss.severity === 'error' ? 'bg-error' : 'bg-warning'}`} />
                            <span className="text-[10px] text-base-content/60 leading-tight">{iss.message}</span>
                          </button>
                        ))}
                      </div>
                    </div>
                  );
                })()}

                {/* ─── Versions (W3): snapshot trail, restore any ─── */}
                <VersionsSection siteId={siteId!} issueId={issueId!} />

                {/* ─── Viewer Settings ─── */}
                <div className="border-t border-base-300/20 pt-4">
                  <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-3">Display Mode</h3>
                  <div className="space-y-1.5">
                    {([
                      { value: 'book', label: 'Book', desc: 'Two-page spreads with a page-flip effect — like a real magazine' },
                      { value: 'scroll', label: 'Vertical scroll', desc: 'All pages stacked, scroll to read' },
                      { value: 'presentation', label: 'Presentation', desc: 'One spread at a time, fade transitions, fullscreen-friendly' },
                    ]).map(opt => (
                      <label key={opt.value} className="flex items-start gap-2 cursor-pointer group">
                        <input type="radio" name="viewer_display_mode" value={opt.value}
                          checked={viewerSettings.display_mode === opt.value}
                          onChange={() => { setViewerSettings(s => ({ ...s, display_mode: opt.value })); store.setDirty(true); }}
                          className="radio radio-xs radio-primary mt-0.5" />
                        <div>
                          <span className="text-[11px] text-base-content/70 group-hover:text-base-content/90">{opt.label}</span>
                          <p className="text-[9px] text-base-content/30">{opt.desc}</p>
                        </div>
                      </label>
                    ))}
                  </div>
                </div>

                <div className="border-t border-base-300/20 pt-4">
                  <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-3">Viewer Background</h3>
                  <div className="flex gap-2 flex-wrap">
                    {([
                      { value: '#0a0a0a', label: 'Black' },
                      { value: '#1a1a2e', label: 'Navy' },
                      { value: '#2d2d2d', label: 'Charcoal' },
                      { value: '#f5f3ef', label: 'Warm' },
                      { value: '#f0f0f0', label: 'Light' },
                      { value: '#ffffff', label: 'White' },
                    ]).map(c => (
                      <button key={c.value}
                        onClick={() => { setViewerSettings(s => ({ ...s, bg_color: c.value })); store.setDirty(true); }}
                        className={`w-7 h-7 rounded-full border-2 transition-all ${
                          viewerSettings.bg_color === c.value
                            ? 'border-primary scale-110' : 'border-base-300/30 hover:border-base-300/60'
                        }`}
                        style={{ backgroundColor: c.value }}
                        title={c.label} />
                    ))}
                  </div>
                  <div className="flex gap-1.5 mt-2">
                    <input name="mag-dtpeditorbeta-7" type="color" value={viewerSettings.bg_color || '#0a0a0a'}
                      onChange={(e) => { setViewerSettings(s => ({ ...s, bg_color: e.target.value })); store.setDirty(true); }}
                      className="w-7 h-7 rounded cursor-pointer border border-base-300/30" />
                    <input name="mag-dtpeditorbeta-8" type="text" value={viewerSettings.bg_color || '#0a0a0a'}
                      onChange={(e) => { setViewerSettings(s => ({ ...s, bg_color: e.target.value })); store.setDirty(true); }}
                      className="input input-bordered input-xs flex-1 text-[10px] font-mono" placeholder="#hex" />
                  </div>
                </div>

                {/* ─── Arrow / controls color ─── */}
                <div className="border-t border-base-300/20 pt-4">
                  <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Controls Color</h3>
                  <div className="flex gap-1.5">
                    <input name="vs-arrow-color" type="color" value={viewerSettings.arrow_color || '#E63B2E'}
                      onChange={(e) => { setViewerSettings(s => ({ ...s, arrow_color: e.target.value })); store.setDirty(true); }}
                      className="w-7 h-7 rounded cursor-pointer border border-base-300/30" />
                    <input name="vs-arrow-hex" type="text" value={viewerSettings.arrow_color || '#E63B2E'}
                      onChange={(e) => { setViewerSettings(s => ({ ...s, arrow_color: e.target.value })); store.setDirty(true); }}
                      className="input input-bordered input-xs flex-1 text-[10px] font-mono" placeholder="#hex" />
                  </div>
                  <p className="text-[9px] text-base-content/30 mt-1">Arrows, mode buttons and player accents in the reader.</p>
                </div>

                {/* ─── Side banners (branding / paid ads) ─── */}
                <div className="border-t border-base-300/20 pt-4 space-y-2">
                  <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Side Banners</h3>
                  {((viewerSettings.side_banners || []) as any[]).map((b, i) => (
                    <div key={i} className="space-y-1 border border-base-300/20 p-2 rounded">
                      <div className="flex gap-1.5 items-center">
                        <select name={`vs-banner-side-${i}`} value={b.side || 'right'} className="select select-bordered select-xs w-20"
                          onChange={(e) => { const nx = [...viewerSettings.side_banners]; nx[i] = { ...b, side: e.target.value }; setViewerSettings(s => ({ ...s, side_banners: nx })); store.setDirty(true); }}>
                          <option value="left">Left</option><option value="right">Right</option>
                        </select>
                        <button className="btn btn-ghost btn-xs ml-auto text-error" onClick={() => { const nx = viewerSettings.side_banners.filter((_: any, j: number) => j !== i); setViewerSettings(s => ({ ...s, side_banners: nx })); store.setDirty(true); }}>×</button>
                      </div>
                      <AssetField label="" accept="image" value={b.src || ''}
                        onChange={(url) => { const nx = [...viewerSettings.side_banners]; nx[i] = { ...b, src: url }; setViewerSettings(s => ({ ...s, side_banners: nx })); store.setDirty(true); }} />
                      <input name={`vs-banner-href-${i}`} type="text" value={b.href || ''} placeholder="Click-through link (https://… — for paid ads)"
                        onChange={(e) => { const nx = [...viewerSettings.side_banners]; nx[i] = { ...b, href: e.target.value }; setViewerSettings(s => ({ ...s, side_banners: nx })); store.setDirty(true); }}
                        className="input input-bordered input-xs w-full text-[10px]" />
                    </div>
                  ))}
                  <button className="btn btn-ghost btn-xs" onClick={() => { setViewerSettings(s => ({ ...s, side_banners: [...(s.side_banners || []), { side: 'right', src: '', href: '' }] })); store.setDirty(true); }}>+ Add banner</button>
                  <p className="text-[9px] text-base-content/30">Shown beside the magazine on wide screens; links open in a new tab (rel=sponsored).</p>
                  {((viewerSettings.side_banners || []) as any[]).some((b) => b.href) && (
                    <AdClicksReport siteId={siteId!} issueId={issueId!} />
                  )}
                </div>

                {/* ─── Background audio (reader playlist) ─── */}
                <div className="border-t border-base-300/20 pt-4 space-y-2">
                  <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Audio Player</h3>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input name="vs-audio-enabled" type="checkbox" checked={!!viewerSettings.audio?.enabled}
                      onChange={(e) => { setViewerSettings(s => ({ ...s, audio: { ...(s.audio || {}), enabled: e.target.checked, tracks: s.audio?.tracks || [] } })); store.setDirty(true); }}
                      className="checkbox checkbox-xs checkbox-primary" />
                    <span className="text-[11px] text-base-content/60">Offer an audio player to readers</span>
                  </label>
                  {!!viewerSettings.audio?.enabled && (
                    <>
                      {((viewerSettings.audio?.tracks || []) as any[]).map((t, i) => (
                        <div key={i} className="flex gap-1.5 items-center">
                          <input name={`vs-track-title-${i}`} type="text" value={t.title || ''} placeholder="Title"
                            onChange={(e) => { const nx = [...viewerSettings.audio.tracks]; nx[i] = { ...t, title: e.target.value }; setViewerSettings(s => ({ ...s, audio: { ...s.audio, tracks: nx } })); store.setDirty(true); }}
                            className="input input-bordered input-xs w-24 text-[10px]" />
                          <div className="flex-1 min-w-0"><AssetField label="" accept="audio" value={t.src || ''}
                            onChange={(url) => { const nx = [...viewerSettings.audio.tracks]; nx[i] = { ...t, src: url }; setViewerSettings(s => ({ ...s, audio: { ...s.audio, tracks: nx } })); store.setDirty(true); }} /></div>
                          <button className="btn btn-ghost btn-xs text-error" onClick={() => { const nx = viewerSettings.audio.tracks.filter((_: any, j: number) => j !== i); setViewerSettings(s => ({ ...s, audio: { ...s.audio, tracks: nx } })); store.setDirty(true); }}>×</button>
                        </div>
                      ))}
                      <button className="btn btn-ghost btn-xs" onClick={() => { setViewerSettings(s => ({ ...s, audio: { ...s.audio, tracks: [...(s.audio?.tracks || []), { title: '', src: '' }] } })); store.setDirty(true); }}>+ Add track</button>
                      <p className="text-[9px] text-base-content/30">Playlist controls appear bottom-left in the reader. Playback never autostarts.</p>
                    </>
                  )}
                </div>

                <div className="border-t border-base-300/20 pt-4 space-y-2">
                  <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium">Viewer Options</h3>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input name="mag-dtpeditorbeta-9" type="checkbox" checked={viewerSettings.show_page_numbers !== false}
                      onChange={(e) => { setViewerSettings(s => ({ ...s, show_page_numbers: e.target.checked })); store.setDirty(true); }}
                      className="checkbox checkbox-xs checkbox-primary" />
                    <span className="text-[11px] text-base-content/60">Show page numbers</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input name="mag-dtpeditorbeta-10" type="checkbox" checked={viewerSettings.show_thumbnails !== false}
                      onChange={(e) => { setViewerSettings(s => ({ ...s, show_thumbnails: e.target.checked })); store.setDirty(true); }}
                      className="checkbox checkbox-xs checkbox-primary" />
                    <span className="text-[11px] text-base-content/60">Show thumbnails</span>
                  </label>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input name="mag-dtpeditorbeta-11" type="checkbox" checked={viewerSettings.auto_hide_ui !== false}
                      onChange={(e) => { setViewerSettings(s => ({ ...s, auto_hide_ui: e.target.checked })); store.setDirty(true); }}
                      className="checkbox checkbox-xs checkbox-primary" />
                    <span className="text-[11px] text-base-content/60">Auto-hide UI</span>
                  </label>
                </div>
              </div>
            )}
          </div>

          {/* ─── Status toggle ─── */}
          <div className="border-t border-base-300/20 px-2 py-1 flex items-center justify-between shrink-0">
            <div className="flex items-center gap-2 text-[10px] text-base-content/30">
              <span className="text-[9px] bg-primary/10 text-primary px-1.5 py-0.5 rounded font-medium">DTP BETA</span>
              {rolloutData && (
                <span className={`px-1 py-0.5 rounded text-[9px] ${
                  rolloutData.status === 'dtp_ready' ? 'bg-success/10 text-success' :
                  rolloutData.status === 'dtp_beta' ? 'bg-warning/10 text-warning' :
                  'bg-base-200 text-base-content/30'
                }`}>
                  {rolloutData.status === 'dtp_ready' ? 'Ready' : rolloutData.status === 'dtp_beta' ? 'Beta' : 'Legacy'}
                </span>
              )}
            </div>
            <button onClick={() => setShowStatusPanel(p => !p)}
              className={`flex items-center gap-1 px-1.5 py-0.5 text-[10px] rounded ${showStatusPanel ? 'bg-primary/10 text-primary' : 'text-base-content/30 hover:text-base-content/60'}`}>
              <Info size={10} /> Status {showStatusPanel ? <ChevronUp size={8} /> : <ChevronDown size={8} />}
            </button>
            {isDebugMode && (
              <button onClick={() => setDebugOpen(p => !p)}
                className={`flex items-center gap-1 px-1.5 py-0.5 text-[10px] rounded ${debugOpen ? 'bg-warning/20 text-warning' : 'text-base-content/30 hover:text-warning/60'}`}>
                <Bug size={10} /> Debug
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Debug panel */}
      {isDebugMode && debugOpen && (
        <Suspense fallback={null}>
          <DtpDebugPanel
            lastSavePayload={lastSavePayload}
            lastLoadPayload={lastLoadPayload}
            consistencyResult={consistencyResult}
            onRunConsistencyCheck={runConsistency}
            onSelectFrame={(id) => store.selectElement(id)}
            onSelectPage={(n) => store.setCurrentPage(n)}
            onClose={() => setDebugOpen(false)}
          />
        </Suspense>
      )}

      {/* Inline image picker for text frames */}
      <AssetPicker
        open={inlineImagePickerOpen}
        onClose={() => setInlineImagePickerOpen(false)}
        onSelect={(asset) => {
          setInlineImagePickerOpen(false);
          // Delay insertion to next frame — lets React close the picker first
          // and return focus to the contentEditable before mutating DOM
          requestAnimationFrame(() => {
            const editable = document.querySelector('[data-editing-id]') as HTMLElement
              ?? document.querySelector('[contenteditable="true"]') as HTMLElement;
            if (!editable) return;
            editable.focus();
            // Restore saved selection
            const savedSel = (window as any).__dtpSavedSelection as Range | null;
            if (savedSel) {
              try {
                const sel = window.getSelection();
                if (sel) { sel.removeAllRanges(); sel.addRange(savedSel); }
              } catch (_) {}
            }
            // Insert image — use DOM API for safe attribute escaping
            // W1-11: anchored image = figure + editable figcaption; the flow
            // engine treats <figure> as an atomic block that travels with the
            // story, and figcaption survives publish (purifyMagazine profile)
            const fig = document.createElement('figure');
            fig.style.cssText = 'float:left;width:40%;max-width:100%;margin:0 12px 8px 0;';
            const img = document.createElement('img');
            img.src = asset.url;
            img.alt = asset.filename || '';
            img.style.cssText = 'width:100%;height:auto;display:block;';
            const cap = document.createElement('figcaption');
            cap.textContent = (asset as any).alt_text || asset.filename || 'Caption';
            cap.style.cssText = 'font-size:10px;opacity:0.7;margin-top:4px;';
            fig.appendChild(img);
            fig.appendChild(cap);
            document.execCommand('insertHTML', false, fig.outerHTML);
            // Persist immediately to store — don't wait for blur
            const editId = editable.getAttribute('data-editing-id');
            if (editId) {
              const el = useMagazineStore.getState().pages.flatMap(p => p.elements).find(e => e.id === editId);
              if (el) {
                store.updateElement(editId, { data: { ...el.data, content: editable.innerHTML } } as any);
              }
            }
          });
        }}
        accept="image"
        currentUrl=""
      />
    </div>
    </MagSwatchContext.Provider>
  );
}


/** W3 versions: every save snapshots the previous state server-side (cap 20). */
function VersionsSection({ siteId, issueId }: { siteId: string; issueId: string }) {
  const { data, refetch, isFetching } = useQuery({
    queryKey: ['dtp-versions', siteId, issueId],
    queryFn: () => dtpDesigner.listVersions(siteId, issueId),
    staleTime: 15000,
  });
  const versions: any[] = data?.data || [];
  const restore = async (v: any) => {
    const when = new Date(v.created_at).toLocaleString();
    if (!window.confirm(`Restore the document as it was at ${when}?\n\nThe current state is snapshotted first, so this is reversible.`)) return;
    await dtpDesigner.restoreVersion(siteId, issueId, v.id);
    window.location.reload();
  };
  return (
    <div className="pb-4 border-b border-base-300/20">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2 flex items-center justify-between">
        Versions
        <button className="btn btn-ghost btn-xs h-5 min-h-0 px-1 normal-case" onClick={() => refetch()} disabled={isFetching}>↻</button>
      </h3>
      {versions.length === 0 && <p className="text-[9px] text-base-content/30">Snapshots appear here after each save.</p>}
      <div className="space-y-1 max-h-44 overflow-y-auto">
        {versions.map((v) => (
          <div key={v.id} className="flex items-center gap-1.5 px-1.5 py-1 rounded hover:bg-base-300/20">
            <div className="flex-1 min-w-0">
              <div className="text-[10px] text-base-content/60 truncate">{new Date(v.created_at).toLocaleString()}</div>
              <div className="text-[9px] text-base-content/30">{v.page_count} pages · {v.frame_count} frames{v.label ? ` · ${v.label}` : ''}</div>
            </div>
            <button className="btn btn-ghost btn-xs text-primary" onClick={() => restore(v)}>Restore</button>
          </div>
        ))}
      </div>
    </div>
  );
}

/** W3 find & replace: HTML-safe, jumps to matches, undo-friendly replaces. */
function FindReplacePanel({ onClose }: { onClose: () => void }) {
  const store = useMagazineStore();
  const [query, setQuery] = useState('');
  const [replacement, setReplacement] = useState('');
  const [matchCase, setMatchCase] = useState(false);
  const [cursor, setCursor] = useState(0);
  const matches = useMemo(
    () => findMatches(store.pages, query, { matchCase }),
    [store.pages, query, matchCase],
  );
  const current = matches.length ? matches[Math.min(cursor, matches.length - 1)] : null;

  const jump = (m: { pageNumber: number; elementId: string } | null) => {
    if (!m) return;
    store.setCurrentPage(m.pageNumber);
    store.selectElement(m.elementId);
  };
  const next = () => {
    if (!matches.length) return;
    const n = (cursor + 1) % matches.length;
    setCursor(n);
    jump(matches[n]);
  };
  const replaceCurrent = () => {
    if (!current) return;
    const el = store.pages.flatMap((p) => p.elements).find((e) => e.id === current.elementId);
    if (!el) return;
    const r = replaceInHtml(String((el.data as any)?.content || ''), query, replacement, { matchCase, occurrence: current.occurrence });
    if (r.replaced) store.updateElement(el.id, { data: { ...el.data, content: r.html } } as any);
  };
  const replaceAll = () => {
    if (!matches.length) return;
    const byEl: string[] = [...new Set(matches.map((m) => m.elementId))];
    let total = 0;
    byEl.forEach((id: string) => {
      const el = store.pages.flatMap((p) => p.elements).find((e) => e.id === id);
      if (!el) return;
      const r = replaceInHtml(String((el.data as any)?.content || ''), query, replacement, { matchCase });
      if (r.replaced) {
        store.updateElement(id, { data: { ...el.data, content: r.html } } as any);
        total += r.replaced;
      }
    });
    setCursor(0);
    if (total) window.setTimeout(() => window.alert(`Replaced ${total} occurrence${total > 1 ? 's' : ''}.`), 50);
  };

  return (
    <div className="fixed top-16 right-4 z-[10002] bg-base-100 border border-base-300 shadow-xl rounded-lg p-3 w-72 space-y-2">
      <div className="flex items-center justify-between">
        <span className="text-[10px] uppercase tracking-wider text-base-content/40 font-medium">Find &amp; Replace</span>
        <button className="btn btn-ghost btn-xs" onClick={onClose}>×</button>
      </div>
      <input autoFocus name="fr-query" type="text" placeholder="Find…" value={query}
        onChange={(e) => { setQuery(e.target.value); setCursor(0); }}
        onKeyDown={(e) => { if (e.key === 'Enter') next(); }}
        className="input input-bordered input-sm w-full text-xs" />
      <input name="fr-replacement" type="text" placeholder="Replace with…" value={replacement}
        onChange={(e) => setReplacement(e.target.value)}
        className="input input-bordered input-sm w-full text-xs" />
      <label className="flex items-center gap-1.5 text-[10px] text-base-content/50 cursor-pointer">
        <input name="fr-case" type="checkbox" className="checkbox checkbox-xs" checked={matchCase}
          onChange={(e) => { setMatchCase(e.target.checked); setCursor(0); }} />
        Match case
      </label>
      <div className="text-[10px] text-base-content/40 min-h-4">
        {query && (matches.length ? `${Math.min(cursor + 1, matches.length)} of ${matches.length}` : 'No matches')}
        {current && <div className="truncate text-base-content/30" title={current.preview}>{current.preview}</div>}
      </div>
      <div className="flex gap-1.5">
        <button className="btn btn-xs btn-ghost" disabled={!matches.length} onClick={next}>Next</button>
        <button className="btn btn-xs btn-ghost" disabled={!current} onClick={replaceCurrent}>Replace</button>
        <button className="btn btn-xs btn-primary btn-outline ml-auto" disabled={!matches.length} onClick={replaceAll}>Replace all</button>
      </div>
    </div>
  );
}

const SHORTCUTS: Array<[string, Array<[string, string]>]> = [
  ['Tools', [['V', 'Select'], ['T', 'Text'], ['I', 'Image'], ['R', 'Rectangle'], ['E', 'Ellipse'], ['L', 'Line'], ['W', 'Preview mode']]],
  ['Editing', [['Ctrl+Z / Ctrl+Y', 'Undo / redo'], ['Ctrl+C / X / V', 'Copy / cut / paste'], ['Ctrl+D', 'Duplicate'], ['Ctrl+A', 'Select all'], ['Del', 'Delete'], ['Esc', 'Deselect / close'], ['Arrows (+Shift)', 'Nudge 1pt (10pt)']]],
  ['Arrange', [['Ctrl+G', 'Group'], ['Ctrl+Shift+G', 'Ungroup'], ['Alt+drag', 'Duplicate while dragging'], ['Alt+click', 'Select element behind'], ['Right-click', 'Context menu']]],
  ['Document', [['Ctrl+F', 'Find & replace'], ['Ctrl+S', 'Save'], ['Drag from ruler', 'Create a guide'], ['?', 'This cheat-sheet']]],
];

function ShortcutSheet({ onClose }: { onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-[10006] bg-black/50 flex items-center justify-center" onClick={onClose}>
      <div className="bg-base-100 border border-base-300 rounded-lg shadow-2xl p-5 w-[560px] max-h-[80vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-sm font-semibold">Keyboard shortcuts</h2>
          <button className="btn btn-ghost btn-xs" onClick={onClose}>×</button>
        </div>
        <div className="grid grid-cols-2 gap-x-6 gap-y-4">
          {SHORTCUTS.map(([group, rows]) => (
            <div key={group}>
              <h3 className="text-[10px] uppercase tracking-wider text-base-content/40 font-medium mb-1.5">{group}</h3>
              {rows.map(([keys, what]) => (
                <div key={keys} className="flex items-center justify-between py-0.5">
                  <span className="text-[11px] text-base-content/60">{what}</span>
                  <kbd className="text-[10px] font-mono bg-base-300/40 rounded px-1.5 py-0.5 text-base-content/70">{keys}</kbd>
                </div>
              ))}
            </div>
          ))}
        </div>
        <p className="text-[9px] text-base-content/30 mt-4">In the reader: ← → turn pages, F fullscreen. Press ? anytime to reopen this sheet.</p>
      </div>
    </div>
  );
}

/** Session E: big pastes get options instead of a raw dump. */
function LargePasteDialog({ paste, onClose }: { paste: { html: string; elementId: string }; onClose: () => void }) {
  const store = useMagazineStore();
  const paraStyles = store.styles.filter((st) => st.type === 'paragraph');
  const [columns, setColumns] = useState<number>(0); // 0 = keep frame setting
  const [map, setMap] = useState<Record<string, string>>({ h1: '', h2: '', h3: '' });
  const words = useMemo(() => wordCount(paste.html), [paste.html]);

  const insert = () => {
    // close any inline editing FIRST — a later blur must never commit a
    // stale pre-paste snapshot over the inserted story (Session F root cause)
    (document.activeElement as HTMLElement | null)?.blur?.();
    useMagazineStore.setState({ editingElementId: null });
    const el = store.pages.flatMap((p) => p.elements).find((e) => e.id === paste.elementId);
    if (!el) return onClose();
    const mapping: Record<string, any> = {};
    for (const lvl of ['h1', 'h2', 'h3']) {
      const st = paraStyles.find((ps) => ps.id === map[lvl]);
      if (st) mapping[lvl] = st;
    }
    const mapped = Object.keys(mapping).length ? mapHeadingsToStyles(paste.html, mapping) : paste.html;
    const data: any = { ...el.data, content: String((el.data as any)?.content || '') + mapped };
    if (columns > 0) data.columnsInFrame = columns;
    store.updateElement(el.id, { data } as any);
    onClose();
  };

  return (
    <div className="fixed inset-0 z-[10006] bg-black/50 flex items-center justify-center" onClick={onClose}>
      <div className="bg-base-100 border border-base-300 rounded-lg shadow-2xl p-5 w-[420px]" onClick={(e) => e.stopPropagation()}>
        <h2 className="text-sm font-semibold mb-1">Large paste — {words.toLocaleString()} words</h2>
        <p className="text-[10px] text-base-content/40 mb-3">The story will flow through the frame and auto-paginate onto new pages.</p>
        <div className="space-y-2">
          <div>
            <label htmlFor="lp-columns" className="text-[10px] text-base-content/40 block mb-0.5">Columns in this frame</label>
            <select id="lp-columns" name="lp-columns" className="select select-bordered select-xs w-40"
              value={columns} onChange={(e) => setColumns(Number(e.target.value))}>
              <option value={0}>Keep current</option>
              <option value={1}>1 column</option>
              <option value={2}>2 columns</option>
              <option value={3}>3 columns</option>
            </select>
          </div>
          {paraStyles.length > 0 && (['h1', 'h2', 'h3'] as const).map((lvl) => (
            <div key={lvl}>
              <label htmlFor={`lp-map-${lvl}`} className="text-[10px] text-base-content/40 block mb-0.5">Map {lvl.toUpperCase()} headings to</label>
              <select id={`lp-map-${lvl}`} name={`lp-map-${lvl}`} className="select select-bordered select-xs w-40"
                value={map[lvl]} onChange={(e) => setMap((m) => ({ ...m, [lvl]: e.target.value }))}>
                <option value="">Keep as-is</option>
                {paraStyles.map((ps) => <option key={ps.id} value={ps.id}>{ps.name}</option>)}
              </select>
            </div>
          ))}
        </div>
        <div className="flex justify-end gap-2 mt-4">
          <button className="btn btn-ghost btn-xs" onClick={onClose}>Cancel</button>
          <button className="btn btn-primary btn-xs" onClick={insert}>Insert &amp; flow</button>
        </div>
      </div>
    </div>
  );
}

/** paid-banner click counts (fed by the public viewer beacon) */
function AdClicksReport({ siteId, issueId }: { siteId: string; issueId: string }) {
  const { data } = useQuery({
    queryKey: ['dtp-ad-clicks', siteId, issueId],
    queryFn: () => api.get(`/sites/${siteId}/magazine-issues/${issueId}/dtp-ad-clicks`).then((r) => r.data),
    staleTime: 60000,
  });
  const rows: any[] = data?.data || [];
  if (!rows.length) return <p className="text-[9px] text-base-content/25">No banner clicks recorded yet.</p>;
  return (
    <div className="mt-1 space-y-0.5">
      <h4 className="text-[9px] text-base-content/40 uppercase tracking-wider">Banner clicks</h4>
      {rows.map((r) => (
        <div key={r.href} className="flex items-center gap-1.5 text-[10px]">
          <span className="font-mono text-primary shrink-0">{r.clicks}×</span>
          <span className="truncate text-base-content/50" title={r.href}>{r.href.replace(/^https?:\/\//, '')}</span>
        </div>
      ))}
    </div>
  );
}