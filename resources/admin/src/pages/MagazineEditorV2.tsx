import { Component, useEffect, useRef, useState } from 'react';
import type { ErrorInfo, ReactNode } from 'react';
import { useParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';

// Error boundary to show errors instead of blank screen
class MagEditorErrorBoundary extends Component<{ children: ReactNode }, { error: Error | null }> {
  state = { error: null as Error | null };
  static getDerivedStateFromError(error: Error) { return { error }; }
  componentDidCatch(error: Error, info: ErrorInfo) { console.error('MagazineEditor crash:', error, info); }
  render() {
    if (this.state.error) {
      return (
        <div className="flex items-center justify-center h-screen bg-base-200 p-8" data-theme={localStorage.getItem('admin-theme') || 'cms-admin'}>
          <div className="max-w-lg text-center">
            <h2 className="text-lg font-medium text-error mb-2">Magazine editor error</h2>
            <pre className="text-[11px] text-base-content/50 bg-base-300/20 p-4 rounded overflow-auto max-h-60 text-left">{this.state.error.message}{'\n'}{this.state.error.stack}</pre>
            <button onClick={() => { this.setState({ error: null }); window.location.reload(); }} className="btn btn-sm btn-primary mt-4">Reload</button>
          </div>
        </div>
      );
    }
    return this.props.children;
  }
}
import { magazines } from '@/lib/api';
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
import PagePanel from '@/components/magazine/properties/PagePanel';
import type { MagElement, MagPageData, MagTypography, MagElementStyle, MagTextWrap, TextFrameData, ImageFrameData } from '@/types/magazine';

type RightTab = 'add' | 'properties' | 'layers' | 'styles';

function MagazineEditorV2Inner() {
  const { siteId = '', magazineId = '' } = useParams();
  const queryClient = useQueryClient();

  const store = useMagazineStore();
  const [rightTab, setRightTab] = useState<RightTab>('properties');
  const [title, setTitle] = useState('');
  const [slug, setSlug] = useState('');
  const [magazineSettings, setMagazineSettings] = useState<Record<string, any>>({});

  const [saveError, setSaveError] = useState<string | null>(null);
  const initializedRef = useRef(false);

  // Load magazine data — disable refetch on window focus to prevent overwriting unsaved work
  const { data: magData, isLoading } = useQuery({
    queryKey: ['magazine', siteId, magazineId],
    queryFn: () => magazines.get(siteId, magazineId).then((r: any) => r.data.data),
    refetchOnWindowFocus: false,
  });

  // Initialize store from magazine data — only on first load or after explicit save
  useEffect(() => {
    if (!magData) return;
    // Skip re-initialization if store already has data and is dirty (unsaved changes)
    if (initializedRef.current && store.isDirty) return;
    initializedRef.current = true;

    setTitle(magData.title);
    setSlug(magData.slug);
    setMagazineSettings(magData.settings || {});

    // Convert magazine pages/elements to MagPageData format
    const defaultPageW = magData.page_width * 2.83;  // mm to pt
    const defaultPageH = magData.page_height * 2.83;
    const pages: MagPageData[] = (magData.pages || []).map((p: any, idx: number) => {
      const ps = p.settings || {};  // per-page V2 settings (if previously saved)
      const pageW = ps._v2pageSize?.width || defaultPageW;
      const pageH = ps._v2pageSize?.height || defaultPageH;
      return {
        id: p.id || crypto.randomUUID(),
        pageNumber: idx + 1,
        pageSize: { width: pageW, height: pageH },
        margins: ps._v2margins || { top: 36, right: 36, bottom: 36, left: 36 },
        bleed: ps._v2bleed || { top: 9, right: 9, bottom: 9, left: 9 },
        columns: ps._v2columns || { count: 1, gutter: 12 },
        baselineGrid: ps._v2baselineGrid || { increment: 14, start: 36 },
        isMaster: false,
        masterPageId: ps._v2masterPageId || null,
        spreadWith: null,
        backgroundColor: p.background_color || '#ffffff',
        backgroundAssetId: null,
        elements: (p.elements || []).map((el: any) => convertLegacyElement(el, pageW, pageH)),
      };
    });

    if (pages.length === 0) {
      pages.push({
        id: crypto.randomUUID(),
        pageNumber: 1,
        pageSize: { width: 595, height: 842 },
        margins: { top: 36, right: 36, bottom: 36, left: 36 },
        bleed: { top: 9, right: 9, bottom: 9, left: 9 },
        columns: { count: 1, gutter: 12 },
        baselineGrid: { increment: 14, start: 36 },
        isMaster: false,
        masterPageId: null,
        spreadWith: null,
        backgroundColor: '#ffffff',
        backgroundAssetId: null,
        elements: [],
      });
    }

    store.setDocument(pages, []);
  }, [magData]);

  // Get current page and selected element — with safe defaults
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
  const [autoOpenImagePicker, setAutoOpenImagePicker] = useState(false);
  const [activeThreadId, setActiveThreadId] = useState<string | null>(null);

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
    // Renumber remaining frames in the thread to close gaps
    const remaining = allElements
      .filter(e => e.threadId === tid && e.id !== selectedEl.id)
      .sort((a, b) => (a.threadOrder ?? 0) - (b.threadOrder ?? 0));
    remaining.forEach((e, i) => {
      if ((e.threadOrder ?? 0) !== i) store.updateElement(e.id, { threadOrder: i } as any);
    });
  };

  // Auto-switch to Properties tab when an element is selected
  useEffect(() => {
    if (store.selectedIds.length > 0) setRightTab('properties');
  }, [store.selectedIds]);

  // Element operations mapped to store
  const handleUpdateElement = (id: string, updates: Partial<MagElement>) => {
    store.updateElement(id, updates);
  };

  const IMAGE_TYPES = ['image_frame', 'circular_image', 'polygon_image', 'fullbleed_image', 'gallery_frame', 'background_image'];

  const handleAddElement = (type: string, x: number, y: number, w: number, h: number) => {
    store.addElement(type, x, y, w, h);
    // For image elements, auto-open the asset picker so user can immediately choose a file
    if (IMAGE_TYPES.includes(type)) {
      setAutoOpenImagePicker(true);
    }
  };

  const handleDeleteElements = (ids: string[]) => {
    store.deleteElements(ids);
  };

  const handleDuplicateElements = (ids: string[]) => {
    store.duplicateElements(ids);
  };

  // Map V2 element types back to legacy types accepted by the API
  const mapElementType = (type: string): string => {
    if (['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'].includes(type)) return 'text';
    if (['image_frame', 'circular_image', 'polygon_image', 'fullbleed_image', 'gallery_frame', 'background_image'].includes(type)) return 'image';
    if (['video_frame', 'audio_player', 'embed_frame'].includes(type)) return 'video';
    if (type === 'hotspot' || type === 'tooltip_trigger') return 'hotspot';
    // Everything else (rectangle, ellipse, line, polygon, etc.) → shape
    return 'shape';
  };

  // Save
  const saveMutation = useMutation({
    mutationFn: async () => {
      setSaveError(null);
      // Derive magazine-level page dimensions from the first page (legacy schema = single size)
      const firstPage = store.pages[0];
      const pageWidthMm = firstPage ? Math.round(firstPage.pageSize.width / 2.83) : 210;
      const pageHeightMm = firstPage ? Math.round(firstPage.pageSize.height / 2.83) : 297;

      // Convert store state back to magazine format
      const pagesData = store.pages.map((p, idx) => ({
        id: p.id,
        title: `Page ${idx + 1}`,
        sort_order: idx,
        background_color: p.backgroundColor || '#ffffff',
        background_image: p.backgroundAssetId || null,
        // Persist per-page V2 layout data in the page settings JSON
        settings: {
          _v2pageSize: p.pageSize,
          _v2margins: p.margins,
          _v2bleed: p.bleed,
          _v2columns: p.columns,
          _v2baselineGrid: p.baselineGrid,
          _v2masterPageId: p.masterPageId,
        },
        elements: p.elements.map(el => ({
          id: el.id,
          type: mapElementType(el.type),
          // Embed V2 metadata inside content so the round-trip preserves everything
          content: {
            ...el.data,
            _v2: true,
            _v2type: el.type,
            _v2typography: el.typography,
            _v2textWrap: el.textWrap,
            _v2style: el.style,
            _v2locked: el.locked,
            _v2visible: el.visible,
            _v2layerName: el.layerName,
            _v2threadId: el.threadId || null,
            _v2threadOrder: el.threadOrder ?? null,
            // Also store in legacy format for the public viewer blade
            html: (el.data as any)?.content || null,
            src: (el.data as any)?.src || null,
            alt: (el.data as any)?.alt || null,
            fit: (el.data as any)?.fit || null,
            focalPoint: (el.data as any)?.focalPoint || null,
          },
          x: (el.x / (p.pageSize.width || 595)) * 100,
          y: (el.y / (p.pageSize.height || 842)) * 100,
          width: (el.width / (p.pageSize.width || 595)) * 100,
          height: (el.height / (p.pageSize.height || 842)) * 100,
          rotation: el.rotation,
          z_index: el.zIndex,
          style: el.style,
        })),
      }));

      await magazines.update(siteId, magazineId, {
        title, slug, settings: magazineSettings,
        page_width: pageWidthMm,
        page_height: pageHeightMm,
      });
      await magazines.savePages(siteId, magazineId, pagesData);
    },
    onSuccess: () => {
      store.setDirty(false);
      queryClient.invalidateQueries({ queryKey: ['magazine', siteId, magazineId] });
    },
    onError: (err: any) => {
      const msg = err?.response?.data?.message || err?.message || 'Save failed';
      setSaveError(msg);
    },
  });

  if (isLoading || store.pages.length === 0) {
    return <div className="flex items-center justify-center h-screen bg-base-200" data-theme={localStorage.getItem('admin-theme') || 'cms-admin'}><span className="loading loading-spinner loading-sm text-base-content/20" /></div>;
  }

  const adminTheme = localStorage.getItem('admin-theme') || 'cms-admin';

  return (
    <div className="flex flex-col h-screen bg-base-200" data-theme={adminTheme}>
      {/* ─── Toolbar ─── */}
      <MagazineToolbar
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
        isDirty={store.isDirty}
        isSaving={saveMutation.isPending}
      />

      {/* ─── Save error banner ─── */}
      {saveError && (
        <div className="flex items-center gap-2 px-4 py-2 bg-error/10 border-b border-error/20 text-error text-[12px]">
          <span className="font-medium">Save failed:</span> {saveError}
          <button onClick={() => setSaveError(null)} className="ml-auto btn btn-ghost btn-xs text-error">Dismiss</button>
        </div>
      )}

      <div className="flex flex-1 overflow-hidden">
        {/* ─── LEFT: Page navigator ─── */}
        <PageNavigator
          pages={store.pages}
          currentPage={store.currentPageNumber}
          onChangePage={store.setCurrentPage}
          onAddPage={() => store.addPage(store.currentPageNumber)}
          onDeletePage={(n) => store.deletePage(n)}
        />

        {/* ─── CENTER: Canvas ─── */}
        {currentPage && (
          <MagazineCanvas
            page={currentPage}
            allPages={store.pages}
            viewMode={store.viewMode}
            gridColumns={store.gridColumns}
            elements={currentElements}
            zoom={store.zoom}
            onZoomChange={store.setZoom}
            onUpdateElement={handleUpdateElement}
            onAddElement={handleAddElement}
            onDeleteElements={handleDeleteElements}
            onDuplicateElements={handleDuplicateElements}
            onSelectElement={(id) => id ? store.selectElement(id) : store.clearSelection()}
            onPageClick={(n) => {
              if (n === -1) store.setViewMode('single');
              else if (n === -2) store.setViewMode('spread');
              else if (n === -3) store.setViewMode('grid');
              else if (n <= -10) store.setGridColumns(-(n + 10));
              else store.setCurrentPage(n);
            }}
          />
        )}

        {/* ─── RIGHT: Properties / Layers / Styles ─── */}
        <div className="w-72 bg-base-100 border-l border-base-300/30 flex flex-col shrink-0">
          {/* Tab switcher */}
          <div className="flex border-b border-base-300/20 shrink-0">
            {([
              { key: 'add' as RightTab, label: '+ Add' },
              { key: 'properties' as RightTab, label: 'Properties' },
              { key: 'layers' as RightTab, label: 'Layers' },
              { key: 'styles' as RightTab, label: 'Styles' },
            ]).map(tab => (
              <button key={tab.key} onClick={() => setRightTab(tab.key)}
                className={`flex-1 px-2 py-2 text-[11px] font-medium transition-colors ${rightTab === tab.key ? 'border-b-2 border-primary text-primary' : 'text-base-content/40'}`}>
                {tab.label}
              </button>
            ))}
          </div>

          <div className="flex-1 overflow-y-auto">
            {/* Properties tab */}
            {rightTab === 'add' && (
              <MagElementPalette onAddElement={handleAddElement} />
            )}

            {rightTab === 'properties' && (
              <div className="p-3 space-y-4">
                {selectedEl ? (
                  <>
                    <div className="text-[10px] text-base-content/30 uppercase tracking-wider">{selectedEl.type.replace(/_/g, ' ')}</div>

                    {/* Transform */}
                    <TransformPanel
                      x={selectedEl.x} y={selectedEl.y}
                      width={selectedEl.width} height={selectedEl.height}
                      rotation={selectedEl.rotation}
                      onChange={(updates) => handleUpdateElement(selectedEl.id, updates as Partial<MagElement>)}
                    />

                    {/* Typography for text frames */}
                    {['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame'].includes(selectedEl.type) && selectedEl.typography && (
                      <MagTypographyPanel
                        value={selectedEl.typography}
                        onChange={(v) => handleUpdateElement(selectedEl.id, { typography: { ...selectedEl.typography!, ...v } as MagTypography })}
                      />
                    )}

                    {/* Text frame settings */}
                    {['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame'].includes(selectedEl.type) && (
                      <TextFramePanel
                        data={(selectedEl.data || {}) as unknown as TextFrameData}
                        onChange={(v) => handleUpdateElement(selectedEl.id, { data: { ...selectedEl.data, ...v } })}
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

                    {/* Image settings */}
                    {IMAGE_TYPES.includes(selectedEl.type) && (
                      <ImagePanel
                        data={(selectedEl.data || {}) as unknown as ImageFrameData}
                        onChange={(v) => handleUpdateElement(selectedEl.id, { data: { ...selectedEl.data, ...v } })}
                        autoOpen={autoOpenImagePicker}
                        onAutoOpenDone={() => setAutoOpenImagePicker(false)}
                      />
                    )}

                    {/* Fill & Stroke */}
                    {!['line'].includes(selectedEl.type) && selectedEl.style && (
                      <FillStrokePanel
                        style={selectedEl.style}
                        onChange={(v) => handleUpdateElement(selectedEl.id, { style: { ...(selectedEl.style || {}), ...v } as MagElementStyle })}
                      />
                    )}

                    {/* Effects */}
                    {selectedEl.style && (
                      <EffectsPanel
                        style={selectedEl.style}
                        onChange={(v) => handleUpdateElement(selectedEl.id, { style: { ...(selectedEl.style || {}), ...v } as MagElementStyle })}
                      />
                    )}

                    {/* Text wrap */}
                    {selectedEl.textWrap && (
                      <TextWrapPanel
                        value={selectedEl.textWrap}
                        onChange={(v) => handleUpdateElement(selectedEl.id, { textWrap: { ...(selectedEl.textWrap || {}), ...v } as MagTextWrap })}
                      />
                    )}
                  </>
                ) : currentPage ? (
                  <>
                    {/* ─── Magazine Settings ─── */}
                    <div className="mb-4 pb-4 border-b border-base-300/20">
                      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-3">Display Mode</h3>

                      <div className="space-y-2">
                        {([
                          { value: 'spread', label: 'Two-page spread', desc: 'Side-by-side pages with page turn animation' },
                          { value: 'single', label: 'Single page', desc: 'One page at a time with slide navigation' },
                          { value: 'scroll', label: 'Scroll', desc: 'All pages stacked, scroll to read' },
                          { value: 'flipbook', label: 'Flipbook', desc: 'Realistic 3D page turns with curl, shadows & swipe gestures' },
                        ] as const).map(opt => (
                          <label key={opt.value} className="flex items-start gap-2 cursor-pointer group">
                            <input
                              type="radio"
                              name="display_mode"
                              value={opt.value}
                              checked={(magazineSettings.display_mode ?? 'spread') === opt.value}
                              onChange={() => {
                                const updated = { ...magazineSettings, display_mode: opt.value };
                                setMagazineSettings(updated);
                                store.setDirty(true);
                              }}
                              className="radio radio-xs radio-primary mt-0.5"
                            />
                            <div>
                              <span className="text-[11px] text-base-content/70 group-hover:text-base-content/90">{opt.label}</span>
                              <p className="text-[10px] text-base-content/30">{opt.desc}</p>
                            </div>
                          </label>
                        ))}
                      </div>
                    </div>

                    {/* ─── Viewer Background ─── */}
                    <div className="mb-4 pb-4 border-b border-base-300/20">
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
                            onClick={() => {
                              const updated = { ...magazineSettings, bg_color: c.value };
                              setMagazineSettings(updated);
                              store.setDirty(true);
                            }}
                            className={`w-7 h-7 rounded-full border-2 transition-all ${
                              (magazineSettings.bg_color ?? '#0a0a0a') === c.value
                                ? 'border-primary scale-110' : 'border-base-300/30 hover:border-base-300/60'
                            }`}
                            style={{ backgroundColor: c.value }}
                            title={c.label}
                          />
                        ))}
                      </div>
                      <div className="flex gap-1.5 mt-2">
                        <input
                          type="color"
                          value={magazineSettings.bg_color ?? '#0a0a0a'}
                          onChange={(e) => {
                            const updated = { ...magazineSettings, bg_color: e.target.value };
                            setMagazineSettings(updated);
                            store.setDirty(true);
                          }}
                          className="w-7 h-7 rounded cursor-pointer border border-base-300/30"
                        />
                        <input
                          type="text"
                          value={magazineSettings.bg_color ?? '#0a0a0a'}
                          onChange={(e) => {
                            const updated = { ...magazineSettings, bg_color: e.target.value };
                            setMagazineSettings(updated);
                            store.setDirty(true);
                          }}
                          className="input input-bordered input-xs flex-1 text-[10px] font-mono"
                          placeholder="#hex"
                        />
                      </div>
                    </div>

                    {/* No element selected — show page properties */}
                    <PagePanel
                      page={currentPage}
                      onChange={(v) => store.updatePage(store.currentPageNumber, v as Partial<MagPageData>)}
                    />
                  </>
                ) : null}
              </div>
            )}

            {/* Layers tab */}
            {rightTab === 'layers' && (
              <MagLayersPanel
                elements={currentElements}
                selectedIds={store.selectedIds}
                onSelect={(id) => store.selectElement(id)}
                onToggleVisibility={(id) => {
                  const el = currentElements.find(e => e.id === id);
                  if (el) handleUpdateElement(id, { visible: !el.visible });
                }}
                onToggleLock={(id) => {
                  const el = currentElements.find(e => e.id === id);
                  if (el) handleUpdateElement(id, { locked: !el.locked });
                }}
                onReorderZ={(id, dir) => {
                  if (dir === 'up') store.bringToFront([id]);
                  else store.sendToBack([id]);
                }}
              />
            )}

            {/* Styles tab */}
            {rightTab === 'styles' && (
              <StylesPanel
                styles={store.styles}
                selectedElementId={selectedEl?.id || null}
                onApplyStyle={() => {}}
                onCreateStyle={(type) => {
                  store.addStyle({
                    id: crypto.randomUUID(),
                    name: `New ${type} style`,
                    type,
                    properties: {},
                    basedOnId: null,
                    nextStyleId: null,
                    isDefault: false,
                  });
                }}
                onDeleteStyle={(id) => store.deleteStyle(id)}
              />
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ─── Convert legacy magazine element to new MagElement format ───
function convertLegacyElement(el: any, pageW: number, pageH: number): MagElement {
  const DEFAULT_STYLE: any = { fill: { color: null, opacity: 1, gradient: null }, stroke: { color: 'transparent', width: 0, style: 'solid', alignment: 'center' }, cornerRadius: { tl: 0, tr: 0, br: 0, bl: 0 }, opacity: 1, shadow: null, innerShadow: null, blendMode: 'normal', blur: 0 };
  const DEFAULT_WRAP: any = { type: 'none', offset: { top: 0, right: 0, bottom: 0, left: 0 }, side: 'both', customPath: null, invert: false };
  const DEFAULT_TYPO: any = { fontFamily: 'Inter', fontSize: 14, fontWeight: 400, fontStyle: 'normal', lineHeight: 1.5, letterSpacing: 0, wordSpacing: 0, textAlign: 'left', textAlignLast: 'auto', textTransform: 'none', textIndent: 0, textColor: '#1a1a1a', paragraphSpacingBefore: 0, paragraphSpacingAfter: 12, hyphenation: false, hangingPunctuation: false, opticalMarginAlignment: false, maxCharsPerLine: null, dropCap: { enabled: false, lines: 3, font: null, color: null }, openType: { ligatures: true, oldstyleNums: false, tabularNums: false, smallCaps: false, swashes: false }, baselineShift: 0, kerning: 'metrics', orphans: 2, widows: 2, paragraphStyleId: null, characterStyleId: null };

  const content = el.content || {};

  // ── V2 round-trip: if content has _v2 marker, restore full V2 state ──
  if (content._v2) {
    // Coordinates are stored as % (0-100), convert back to pts
    const x = (parseFloat(el.x) / 100) * pageW;
    const y = (parseFloat(el.y) / 100) * pageH;
    const w = (parseFloat(el.width) / 100) * pageW;
    const h = (parseFloat(el.height) / 100) * pageH;

    // Strip V2 metadata keys from data, keep only the actual element data
    // Note: html/src/alt/fit/focalPoint are duplicated for legacy viewer compat,
    // but they also exist in the original data — so we only strip the _v2 prefixed keys
    const { _v2, _v2type, _v2typography, _v2textWrap, _v2style, _v2locked, _v2visible, _v2layerName, html: _html, ...cleanData } = content;
    // Remove 'html' (legacy dup of data.content for text), keep src/alt/fit/focalPoint as they're real image data

    return {
      id: el.id || crypto.randomUUID(),
      type: (_v2type || 'text_frame') as any,
      name: _v2layerName || null,
      data: cleanData,
      x: isNaN(x) ? 50 : x,
      y: isNaN(y) ? 50 : y,
      width: isNaN(w) ? 200 : Math.max(w, 20),
      height: isNaN(h) ? 100 : Math.max(h, 20),
      rotation: parseFloat(el.rotation) || 0,
      scaleX: 1,
      scaleY: 1,
      zIndex: parseInt(el.z_index) || 0,
      locked: _v2locked ?? false,
      visible: _v2visible ?? true,
      layerName: _v2layerName || null,
      style: _v2style || el.style || DEFAULT_STYLE,
      typography: _v2typography || null,
      textWrap: _v2textWrap || DEFAULT_WRAP,
      threadId: el.content?._v2threadId || null,
      threadOrder: el.content?._v2threadOrder ?? null,
      pageNumber: 1,
      onMaster: false,
      parentId: null,
      children: [],
      responsiveOverrides: {},
    };
  }

  // ── Legacy format: first-time migration from old editor ──
  const isText = el.type === 'text' || el.type === 'block';
  const isImage = el.type === 'image';

  // Old system uses % for x/y/width/height (0-100 range)
  const x = (parseFloat(el.x) / 100) * pageW;
  const y = (parseFloat(el.y) / 100) * pageH;
  const w = (parseFloat(el.width) / 100) * pageW;
  const h = (parseFloat(el.height) / 100) * pageH;

  // Map old type to new type
  const typeMap: Record<string, string> = { text: 'text_frame', image: 'image_frame', shape: 'rectangle', block: 'text_frame', video: 'video_frame', hotspot: 'hotspot' };
  const newType = typeMap[el.type] || 'rectangle';

  // Build data from old content
  let data: Record<string, any> = {};
  if (isText) {
    data = { content: content.html || content.content || '<p>Text</p>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnRule: false, textInset: { top: 8, right: 8, bottom: 8, left: 8 }, verticalAlign: 'top' };
  } else if (isImage) {
    data = { src: content.src || '', alt: content.alt || '', fit: content.fit || 'cover', focalPoint: content.focalPoint || { x: 0.5, y: 0.5 }, imageOffsetX: 0, imageOffsetY: 0, imageScale: 1, imageRotation: 0, clipShape: 'rectangle', clipPath: null, filters: content.filters || { brightness: 100, contrast: 100, saturation: 100, grayscale: false, duotone: null } };
  } else if (el.type === 'video') {
    data = { url: content.url || '', posterAssetId: null, autoplay: false, aspectRatio: '16:9' };
  } else if (el.type === 'shape') {
    data = { fillColor: content.fill || null, canContainText: false, textContent: null, sides: 4, innerRadius: 0, cornerRadius: { tl: 0, tr: 0, br: 0, bl: 0 } };
  } else if (el.type === 'block') {
    data = { content: content.blockData?.content || content.html || '<p>Block content</p>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnRule: false, textInset: { top: 8, right: 8, bottom: 8, left: 8 }, verticalAlign: 'top' };
  } else {
    data = content;
  }

  return {
    id: el.id || crypto.randomUUID(),
    type: newType as any,
    name: null,
    data,
    x: isNaN(x) ? 50 : x,
    y: isNaN(y) ? 50 : y,
    width: isNaN(w) ? 200 : Math.max(w, 20),
    height: isNaN(h) ? 100 : Math.max(h, 20),
    rotation: parseFloat(el.rotation) || 0,
    scaleX: 1,
    scaleY: 1,
    zIndex: parseInt(el.z_index) || 0,
    locked: false,
    visible: true,
    layerName: null,
    style: (el.style && el.style.fill) ? el.style : DEFAULT_STYLE,
    typography: isText ? DEFAULT_TYPO : null,
    textWrap: DEFAULT_WRAP,
    threadId: null,
    threadOrder: null,
    pageNumber: 1,
    onMaster: false,
    parentId: null,
    children: [],
    responsiveOverrides: {},
  };
}

export default function MagazineEditorV2() {
  return (
    <MagEditorErrorBoundary>
      <MagazineEditorV2Inner />
    </MagEditorErrorBoundary>
  );
}
