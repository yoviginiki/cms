import { useEffect, useRef, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Save, Loader2, LayoutList, Paintbrush, Eye, Globe, FileText } from 'lucide-react';
import { usePageData } from '@/hooks/usePageData';
import { useAutoSave } from '@/hooks/useAutoSave';
import { useEditorShortcuts } from '@/hooks/useEditorShortcuts';
import { useThemeFonts } from '@/hooks/useThemeFonts';
import DOMPurify from 'dompurify';
import { useEditorStore } from '@/stores/editorStore';
import { useMagazineStore } from '@/stores/magazineStore';
import { AssetField } from '@/components/ui/AssetPicker';
import { SpacingPanel } from '@/components/editor/properties/SpacingPanel';
import { VisualPanel } from '@/components/editor/properties/VisualPanel';
import { LayoutPanel } from '@/components/editor/properties/LayoutPanel';
import { AnimationPanel } from '@/components/editor/properties/AnimationPanel';
import { AdvancedPanel } from '@/components/editor/properties/AdvancedPanel';
import { ResponsivePanel } from '@/components/editor/properties/ResponsivePanel';
import BackgroundEditor from '@/components/editor/BackgroundEditor';
import { BuilderCanvas, BuilderDndProvider } from '@/components/editor/BuilderCanvas';
import { BlockPicker } from '@/components/editor/BlockPicker';
import { BlockSettings } from '@/components/editor/BlockSettings';
import { VersionHistory } from '@/components/editor/VersionHistory';
import { SeoAnalyzer } from '@/components/editor/SeoAnalyzer';
import { MagazineCanvas } from '@/components/magazine/MagazineCanvas';
import MagLayersPanel from '@/components/magazine/MagLayersPanel';
import PageNavigator from '@/components/magazine/PageNavigator';
import MagElementPalette from '@/components/magazine/MagElementPalette';
import { PublishButton } from '@/components/editor/PublishButton';
import TransformPanel from '@/components/magazine/properties/TransformPanel';
import FillStrokePanel from '@/components/magazine/properties/FillStrokePanel';
import EffectsPanel from '@/components/magazine/properties/EffectsPanel';
import PagePanel from '@/components/magazine/properties/PagePanel';
import TextFramePanel from '@/components/magazine/properties/TextFramePanel';
import ImagePanel from '@/components/magazine/properties/ImagePanel';
import { api, blocks as blocksApi, pages as pagesApi, magEditor, sites, themeEngine } from '@/lib/api';
import type { MagElement, MagPageData, MagElementStyle, TextFrameData, ImageFrameData } from '@/types/magazine';
import '@/components/blocks';

const TEXT_FRAME_TYPES = ['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'];
const IMAGE_TYPES = ['image_frame', 'circular_image', 'polygon_image', 'fullbleed_image', 'gallery_frame', 'background_image'];

type EditorMode = 'block' | 'magazine';
// RightTab removed — block mode uses BuilderSidebar, magazine mode uses magRightTab

export default function PageEditor() {
  const { siteId = '', pageId = '' } = useParams();
  const navigate = useNavigate();
  const { page, blocks: fetchedBlocks, isLoading, error } = usePageData(siteId, pageId);
  const setBlocks = useEditorStore((s) => s.setBlocks);
  const editorBlocks = useEditorStore((s) => s.blocks);
  const isDirty = useEditorStore((s) => s.isDirty);
  const isSaving = useEditorStore((s) => s.isSaving);
  const setSaving = useEditorStore((s) => s.setSaving);
  const setDirty = useEditorStore((s) => s.setDirty);
  const setStoreEditorMode = useEditorStore((s) => s.setEditorMode);
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const pageMetaRef = useRef<Record<string, any> | null>(null);

  const { data: siteData } = useQuery<any>({
    queryKey: ['site', siteId],
    queryFn: () => sites.get(siteId).then((r: any) => r.data.data),
  });
  const publicBase = siteData?.custom_domain ? `https://${siteData.custom_domain}` : `https://${siteData?.slug || ''}.ensodo.eu`;

  // Layouts
  const { data: layoutsList } = useQuery<any[]>({
    queryKey: ['layouts', siteId],
    queryFn: () => api.get(`/sites/${siteId}/layouts`).then((r: any) => {
      const d = r.data?.data;
      return Array.isArray(d) ? d : [];
    }),
  });

  const [editorMode, setEditorMode] = useState<EditorMode>('block');
  const [magRightTab, setMagRightTab] = useState<'add' | 'properties' | 'layers' | 'viewer'>('properties');

  // Magazine store
  const magStore = useMagazineStore();

  // Load mag_elements when in magazine mode — use page data directly, not editorMode state
  const isMagazineMode = page?.editor_mode === 'magazine';
  const { data: magData } = useQuery({
    queryKey: ['mag-editor', siteId, pageId],
    queryFn: () => magEditor.get(siteId, pageId).then((r: any) => r.data.data),
    enabled: isMagazineMode,
  });

  // Initialize magazine store from API data — only on first load.
  // Subsequent React Query refetches must NOT overwrite unsaved edits.
  const magLoadedRef = useRef(false);
  useEffect(() => {
    if (!magData || !isMagazineMode || magLoadedRef.current) return;
    magLoadedRef.current = true;
    const pages: MagPageData[] = (magData.pages || []).map((p: any) => ({
      id: p.id,
      pageNumber: p.page_number,
      pageSize: p.page_size || { width: 595, height: 842 },
      margins: p.margins || { top: 36, right: 36, bottom: 36, left: 36 },
      bleed: p.bleed || { top: 9, right: 9, bottom: 9, left: 9 },
      columns: p.columns || { count: 1, gutter: 12 },
      baselineGrid: p.baseline_grid || { increment: 14, start: 36 },
      isMaster: p.is_master || false,
      masterPageId: p.master_page_id,
      spreadWith: p.spread_with,
      backgroundColor: p.background_color || '#ffffff',
      backgroundAssetId: p.background_asset_id,
      elements: [],
    }));

    // Assign elements to pages
    for (const el of (magData.elements || [])) {
      const pageIdx = pages.findIndex(p => p.pageNumber === (el.page_number || 1));
      if (pageIdx >= 0) {
        pages[pageIdx].elements.push({
          id: el.id,
          type: el.type || 'text_frame',
          name: el.name,
          data: el.data || {},
          x: parseFloat(el.x) || 0,
          y: parseFloat(el.y) || 0,
          width: parseFloat(el.width) || 200,
          height: parseFloat(el.height) || 100,
          rotation: parseFloat(el.rotation) || 0,
          scaleX: parseFloat(el.scale_x) || 1,
          scaleY: parseFloat(el.scale_y) || 1,
          zIndex: el.z_index || 0,
          locked: el.locked || false,
          visible: el.visible !== false,
          layerName: el.layer_name,
          style: el.style || { fill: { color: null, opacity: 1, gradient: null }, stroke: { color: 'transparent', width: 0, style: 'solid', alignment: 'center' }, cornerRadius: { tl: 0, tr: 0, br: 0, bl: 0 }, opacity: 1, shadow: null, innerShadow: null, blendMode: 'normal', blur: 0 },
          typography: el.typography || null,
          textWrap: el.text_wrap || { type: 'none', offset: { top: 0, right: 0, bottom: 0, left: 0 }, side: 'both', customPath: null, invert: false },
          threadId: el.thread_id,
          threadOrder: el.thread_order,
          pageNumber: el.page_number || 1,
          onMaster: el.on_master || false,
          parentId: el.parent_id,
          children: [],
          responsiveOverrides: el.responsive_overrides || {},
        } as MagElement);
      }
    }

    if (pages.length === 0) {
      pages.push({
        id: crypto.randomUUID(), pageNumber: 1,
        pageSize: { width: 595, height: 842 }, margins: { top: 36, right: 36, bottom: 36, left: 36 },
        bleed: { top: 9, right: 9, bottom: 9, left: 9 }, columns: { count: 1, gutter: 12 },
        baselineGrid: { increment: 14, start: 36 }, isMaster: false, masterPageId: null,
        spreadWith: null, backgroundColor: '#ffffff', backgroundAssetId: null, elements: [],
      });
    }

    magStore.setDocument(pages, []);
  }, [magData]);

  useAutoSave(siteId, 'pages', pageId);
  useEditorShortcuts(siteId, 'pages', pageId);
  useThemeFonts(siteId);

  // Load blocks only on initial fetch — never overwrite after user starts editing.
  // React Query refetch (e.g. window focus) must not reset the editor store.
  const blocksLoadedRef = useRef(false);
  useEffect(() => {
    if (fetchedBlocks && !blocksLoadedRef.current) {
      setBlocks(fetchedBlocks);
      // Load raw HTML from page data (without marking dirty)
      if (page?.raw_html) {
        useEditorStore.setState({ rawHtml: page.raw_html });
      }
      // Restore undo history from sessionStorage if available
      useEditorStore.getState().restoreUndoState();
      blocksLoadedRef.current = true;
    }
  }, [fetchedBlocks, setBlocks, page]);

  // Read editor_mode from page data
  useEffect(() => {
    if (page?.editor_mode) {
      setEditorMode(page.editor_mode as EditorMode);
      setStoreEditorMode(page.editor_mode as EditorMode);
    }
  }, [page]);

  // Auto-switch to settings tab when block or magazine element selected
  useEffect(() => {
    if (selectedBlockId && editorMode === 'magazine') setMagRightTab('properties');
  }, [selectedBlockId]);
  useEffect(() => {
    if (magStore.selectedIds.length > 0) setMagRightTab('properties');
  }, [magStore.selectedIds]);

  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => { if (isDirty) e.preventDefault(); };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [isDirty]);

  async function handleSave() {
    setSaving(true);
    try {
      if (editorMode === 'magazine' || page?.editor_mode === 'magazine') {
        // Save magazine/canvas data
        const pages = magStore.pages.map(p => ({
          page_number: p.pageNumber,
          page_size: p.pageSize,
          margins: p.margins,
          bleed: p.bleed,
          columns: p.columns,
          baseline_grid: p.baselineGrid,
          is_master: p.isMaster,
          master_page_id: p.masterPageId,
          background_color: p.backgroundColor,
          background_asset_id: p.backgroundAssetId,
        }));
        const elements = magStore.pages.flatMap(p =>
          p.elements.map(el => ({
            type: el.type,
            name: el.name,
            data: el.data,
            x: el.x, y: el.y, width: el.width, height: el.height,
            rotation: el.rotation,
            scale_x: el.scaleX, scale_y: el.scaleY,
            z_index: el.zIndex,
            locked: el.locked, visible: el.visible,
            layer_name: el.layerName,
            style: el.style,
            typography: el.typography,
            text_wrap: el.textWrap,
            thread_id: el.threadId, thread_order: el.threadOrder,
            page_number: el.pageNumber || p.pageNumber,
            on_master: el.onMaster,
            responsive_overrides: el.responsiveOverrides,
          }))
        );
        await magEditor.sync(siteId, pageId, { pages, elements });
        magStore.setDirty(false);
      } else {
        // Save block data + raw HTML
        const rawHtml = useEditorStore.getState().rawHtml;
        await blocksApi.sync(siteId, 'pages', pageId, editorBlocks, rawHtml);
      }
      // Also save page appearance/settings if changed
      if (pageMetaRef.current) {
        await pagesApi.update(siteId, pageId, { seo_meta: pageMetaRef.current });
      }
      setDirty(false);
    } catch (err) {
      console.error('Save failed:', err);
    } finally { setSaving(false); }
  }

  async function togglePublishStatus() {
    const newStatus = page?.status === 'published' ? 'draft' : 'published';
    try {
      await pagesApi.update(siteId, pageId, { status: newStatus });
      // Refetch page data
      window.location.reload();
    } catch { /* noop */ }
  }

  async function switchEditorMode(mode: EditorMode) {
    setEditorMode(mode);
    setStoreEditorMode(mode);
    try {
      await pagesApi.update(siteId, pageId, { editor_mode: mode });
    } catch { /* silently fail */ }
  }

  if (isLoading) {
    return <div className="flex items-center justify-center h-screen bg-base-200"><span className="loading loading-spinner loading-sm text-base-content/20" /></div>;
  }

  if (error) {
    return <div className="flex items-center justify-center h-screen bg-base-200 text-error text-[13px]">Failed to load page</div>;
  }

  const adminTheme = localStorage.getItem('admin-theme') || 'cms-admin';

  return (
    <div className="flex flex-col h-screen bg-base-200" data-theme={adminTheme}>
      {/* ─── Top toolbar ─── */}
      <div className="flex items-center justify-between h-12 px-4 bg-base-100 border-b border-base-300/30 shrink-0">
        <div className="flex items-center gap-3">
          <button onClick={() => navigate(`/sites/${siteId}/pages`)} className="btn btn-ghost btn-xs btn-square">
            <ArrowLeft size={16} />
          </button>
          <div className="min-w-0">
            <h1 className="text-sm font-medium text-base-content/90 truncate">{page?.title ?? 'Page'}</h1>
            <span className="text-[10px] text-base-content/30">/{page?.slug}</span>
          </div>
          {(isDirty || magStore.isDirty) && <span className="text-[10px] text-warning font-medium">unsaved</span>}
        </div>

        <div className="flex items-center gap-2">
          {/* Editor mode toggle */}
          <div className="flex bg-base-200/80 rounded-md p-0.5">
            <button onClick={() => switchEditorMode('block')}
              className={`flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium transition-colors ${
                editorMode === 'block' ? 'bg-base-100 text-base-content/90 shadow-sm' : 'text-base-content/40 hover:text-base-content/60'
              }`}>
              <LayoutList size={12} /> Blocks
            </button>
            <button onClick={() => switchEditorMode('magazine')}
              className={`flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-medium transition-colors ${
                editorMode === 'magazine' ? 'bg-base-100 text-base-content/90 shadow-sm' : 'text-base-content/40 hover:text-base-content/60'
              }`}>
              <Paintbrush size={12} /> Canvas
            </button>
          </div>

          <div className="w-px h-5 bg-base-300/30" />

          {/* Layout picker */}
          <select value={page?.layout_id || ''}
            onChange={async (e) => {
              try {
                await pagesApi.update(siteId, pageId, { layout_id: e.target.value || null });
                window.location.reload();
              } catch {}
            }}
            className="select select-bordered select-xs text-[11px] w-32">
            <option value="">Standard</option>
            {(layoutsList || []).map((l: any) => (
              <option key={l.id} value={l.id}>{l.name}</option>
            ))}
          </select>

          {/* Status badge */}
          <span className={`badge badge-sm text-[10px] ${page?.status === 'published' ? 'badge-success' : 'badge-ghost'}`}>
            {page?.status || 'draft'}
          </span>

          <button onClick={togglePublishStatus}
            className={`btn btn-sm text-[12px] gap-1 ${page?.status === 'published' ? 'btn-ghost' : 'btn-success'}`}>
            {page?.status === 'published' ? 'Unpublish' : 'Set Published'}
          </button>

          <a href={`/sites/${siteData?.slug || ''}/${page?.slug || ''}`} target="_blank" rel="noopener"
            className="btn btn-sm btn-ghost text-[12px] gap-1" title="Preview page (dynamic)">
            <Eye size={13} /> Preview
          </a>

          <button onClick={handleSave} disabled={isSaving || (!isDirty && !magStore.isDirty)}
            className={`btn btn-sm text-[12px] gap-1 ${(isDirty || magStore.isDirty) ? 'btn-warning' : 'btn-ghost'}`}>
            {isSaving ? <Loader2 size={13} className="animate-spin" /> : <Save size={13} />} Save
            {(isDirty || magStore.isDirty) && <span className="w-1.5 h-1.5 rounded-full bg-warning-content" />}
          </button>
          <PublishButton siteId={siteId} />
          <a href={`${publicBase}/${page?.slug || ''}`} target="_blank" rel="noopener"
            className="btn btn-sm btn-ghost text-[12px] gap-1" title="View published page">
            <Globe size={13} /> Live
          </a>
        </div>
      </div>

      {/* ─── Editor body ─── */}
      <div className="flex flex-1 overflow-hidden">
        {page?.editor_mode !== 'magazine' ? (
          <BuilderDndProvider>
            <div className="flex flex-1 overflow-x-auto overflow-y-hidden lg:overflow-x-hidden snap-x snap-mandatory">
              <div className="w-full min-w-full lg:min-w-0 lg:flex-1 snap-start overflow-y-auto">
                <BuilderCanvas />
              </div>
              <PageEditorSidebar page={page} siteId={siteId} pageId={pageId}
                layouts={layoutsList || []} publicBase={publicBase} siteSlug={siteData?.slug || ''}
                metaRef={pageMetaRef} onDirty={() => setDirty(true)} />
            </div>
          </BuilderDndProvider>
        ) : (
          <>
            {/* Magazine mode: page navigator + canvas + right sidebar */}
            <PageNavigator
              pages={magStore.pages}
              currentPage={magStore.currentPageNumber}
              onChangePage={magStore.setCurrentPage}
              onAddPage={() => magStore.addPage(magStore.currentPageNumber)}
              onDeletePage={(n) => magStore.deletePage(n)}
            />

            {(() => {
              const curPage = magStore.pages.find(p => p.pageNumber === magStore.currentPageNumber) || magStore.pages[0];
              if (!curPage) return <div className="flex-1 flex items-center justify-center text-base-content/30">No pages</div>;
              const safePage = { ...curPage, pageSize: curPage.pageSize || { width: 595, height: 842 }, margins: curPage.margins || { top: 36, right: 36, bottom: 36, left: 36 }, columns: curPage.columns || { count: 1, gutter: 12 }, baselineGrid: curPage.baselineGrid || { increment: 14, start: 36 }, elements: curPage.elements || [] };
              return (
                <MagazineCanvas
                  page={safePage}
                  allPages={magStore.pages}
                  viewMode={magStore.viewMode}
                  gridColumns={magStore.gridColumns}
                  elements={safePage.elements}
                  zoom={magStore.zoom}
                  onZoomChange={magStore.setZoom}
                  onUpdateElement={(id, updates) => magStore.updateElement(id, updates)}
                  onAddElement={(type, x, y, w, h) => magStore.addElement(type, x, y, w, h)}
                  onDeleteElements={(ids) => magStore.deleteElements(ids)}
                  onDuplicateElements={(ids) => magStore.duplicateElements(ids)}
                  onSelectElement={(id) => id ? magStore.selectElement(id) : magStore.clearSelection()}
                  onPageClick={(n) => {
                    if (n === -1) magStore.setViewMode('single');
                    else if (n === -2) magStore.setViewMode('spread');
                    else if (n === -3) magStore.setViewMode('grid');
                    else if (n <= -10) magStore.setGridColumns(-(n + 10));
                    else magStore.setCurrentPage(n);
                  }}
                />
              );
            })()}

            {/* Right sidebar — overlay on mobile */}
            <div className="w-72 bg-base-100 border-l border-base-300/30 flex flex-col shrink-0 hidden lg:flex">
              <div className="flex border-b border-base-300/20 shrink-0">
                {([
                  { key: 'add' as const, label: '+ Add' },
                  { key: 'properties' as const, label: 'Properties' },
                  { key: 'layers' as const, label: 'Layers' },
                  { key: 'viewer' as const, label: 'Viewer' },
                ]).map(tab => (
                  <button key={tab.key} onClick={() => setMagRightTab(tab.key)}
                    className={`flex-1 px-2 py-2 text-[11px] font-medium transition-colors ${magRightTab === tab.key ? 'border-b-2 border-primary text-primary' : 'text-base-content/40'}`}>
                    {tab.label}
                  </button>
                ))}
              </div>
              <div className="flex-1 overflow-y-auto">
                {magRightTab === 'add' && (
                  <MagElementPalette onAddElement={(type, x, y, w, h) => magStore.addElement(type, x, y, w, h)} />
                )}
                {magRightTab === 'properties' && (() => {
                  const curPage = magStore.pages.find(p => p.pageNumber === magStore.currentPageNumber);
                  const selEl = curPage?.elements.find(e => magStore.selectedIds.includes(e.id));
                  if (selEl) {
                    return (
                      <div className="p-3 space-y-3">
                        <div className="text-[10px] text-base-content/30 uppercase tracking-wider">{selEl.type.replace(/_/g, ' ')}</div>
                        <TransformPanel x={selEl.x} y={selEl.y} width={selEl.width} height={selEl.height} rotation={selEl.rotation}
                          onChange={(u) => magStore.updateElement(selEl.id, u as Partial<MagElement>)} />

                        {/* Text content editor for text frames */}
                        {TEXT_FRAME_TYPES.includes(selEl.type) && (
                          <div className="border-t border-base-300/20 pt-2">
                            <label className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-1 block">Content</label>
                            <textarea
                              className="textarea textarea-bordered textarea-xs w-full h-24 text-[11px] leading-relaxed"
                              value={((selEl.data as any)?.content || '').replace(/<[^>]*>/g, '')}
                              placeholder="Type text content here..."
                              onChange={(e) => {
                                const raw = '<p>' + e.target.value.split('\n').join('</p><p>') + '</p>';
                                const html = DOMPurify.sanitize(raw, { ALLOWED_TAGS: ['p', 'br', 'b', 'i', 'em', 'strong', 'span', 'a', 'h1', 'h2', 'h3', 'ul', 'ol', 'li'], ALLOWED_ATTR: ['href', 'target', 'class'], ALLOW_DATA_ATTR: false });
                                magStore.updateElement(selEl.id, { data: { ...selEl.data, content: html } } as any);
                              }}
                            />
                          </div>
                        )}

                        {/* Typography controls for text frames */}
                        {TEXT_FRAME_TYPES.includes(selEl.type) && (
                          <div className="border-t border-base-300/20 pt-2 space-y-2">
                            <label className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium block">Typography</label>
                            <div className="grid grid-cols-2 gap-2">
                              <div>
                                <label className="text-[10px] text-base-content/40 mb-0.5 block">Font</label>
                                <select
                                  value={selEl.typography?.fontFamily || 'Inter'}
                                  onChange={(e) => magStore.updateElement(selEl.id, { typography: { ...selEl.typography, fontFamily: e.target.value } } as any)}
                                  className="select select-bordered select-xs w-full"
                                >
                                  <option value="'Inter', system-ui, sans-serif">Inter</option>
                                  <option value="'Instrument Serif', Georgia, serif">Instrument Serif</option>
                                  <option value="Georgia, serif">Georgia</option>
                                  <option value="'PT Serif', serif">PT Serif</option>
                                  <option value="'PT Sans', sans-serif">PT Sans</option>
                                  <option value="'JetBrains Mono', monospace">JetBrains Mono</option>
                                  <option value="system-ui, sans-serif">System UI</option>
                                </select>
                              </div>
                              <div>
                                <label className="text-[10px] text-base-content/40 mb-0.5 block">Size</label>
                                <input type="number" min={6} max={200} value={selEl.typography?.fontSize || 14}
                                  onChange={(e) => magStore.updateElement(selEl.id, { typography: { ...selEl.typography, fontSize: Number(e.target.value) } } as any)}
                                  className="input input-bordered input-xs w-full" />
                              </div>
                            </div>
                            <div className="grid grid-cols-3 gap-2">
                              <div>
                                <label className="text-[10px] text-base-content/40 mb-0.5 block">Weight</label>
                                <select value={selEl.typography?.fontWeight || 400}
                                  onChange={(e) => magStore.updateElement(selEl.id, { typography: { ...selEl.typography, fontWeight: Number(e.target.value) } } as any)}
                                  className="select select-bordered select-xs w-full">
                                  <option value={300}>Light</option>
                                  <option value={400}>Regular</option>
                                  <option value={500}>Medium</option>
                                  <option value={600}>Semi</option>
                                  <option value={700}>Bold</option>
                                </select>
                              </div>
                              <div>
                                <label className="text-[10px] text-base-content/40 mb-0.5 block">Leading</label>
                                <input type="number" min={0.8} max={3} step={0.05} value={selEl.typography?.lineHeight || 1.5}
                                  onChange={(e) => magStore.updateElement(selEl.id, { typography: { ...selEl.typography, lineHeight: Number(e.target.value) } } as any)}
                                  className="input input-bordered input-xs w-full" />
                              </div>
                              <div>
                                <label className="text-[10px] text-base-content/40 mb-0.5 block">Align</label>
                                <select value={selEl.typography?.textAlign || 'left'}
                                  onChange={(e) => magStore.updateElement(selEl.id, { typography: { ...selEl.typography, textAlign: e.target.value } } as any)}
                                  className="select select-bordered select-xs w-full">
                                  <option value="left">Left</option>
                                  <option value="center">Center</option>
                                  <option value="right">Right</option>
                                </select>
                              </div>
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                              <div>
                                <label className="text-[10px] text-base-content/40 mb-0.5 block">Color</label>
                                <input type="color" value={selEl.typography?.textColor || '#1a1a1a'}
                                  onChange={(e) => magStore.updateElement(selEl.id, { typography: { ...selEl.typography, textColor: e.target.value } } as any)}
                                  className="w-full h-6 rounded cursor-pointer" />
                              </div>
                              <div>
                                <label className="text-[10px] text-base-content/40 mb-0.5 block">Tracking</label>
                                <input type="number" min={-0.1} max={0.5} step={0.01} value={selEl.typography?.letterSpacing || 0}
                                  onChange={(e) => magStore.updateElement(selEl.id, { typography: { ...selEl.typography, letterSpacing: Number(e.target.value) } } as any)}
                                  className="input input-bordered input-xs w-full" />
                              </div>
                            </div>
                            <TextFramePanel data={(selEl.data || {}) as unknown as TextFrameData}
                              onChange={(v) => magStore.updateElement(selEl.id, { data: { ...selEl.data, ...v } } as any)} />
                          </div>
                        )}

                        {/* Image settings for image frames */}
                        {IMAGE_TYPES.includes(selEl.type) && (
                          <ImagePanel
                            data={(selEl.data || {}) as unknown as ImageFrameData}
                            onChange={(v) => magStore.updateElement(selEl.id, { data: { ...selEl.data, ...v } } as any)}
                          />
                        )}

                        {selEl.style && <FillStrokePanel style={selEl.style} onChange={(v) => magStore.updateElement(selEl.id, { style: { ...selEl.style, ...v } as MagElementStyle })} />}
                        {selEl.style && <EffectsPanel style={selEl.style} onChange={(v) => magStore.updateElement(selEl.id, { style: { ...selEl.style, ...v } as MagElementStyle })} />}
                      </div>
                    );
                  }
                  if (curPage) {
                    return <div className="p-3"><PagePanel page={{ ...curPage, pageSize: curPage.pageSize || { width: 595, height: 842 }, margins: curPage.margins || { top: 36, right: 36, bottom: 36, left: 36 }, bleed: curPage.bleed || { top: 9, right: 9, bottom: 9, left: 9 }, columns: curPage.columns || { count: 1, gutter: 12 }, baselineGrid: curPage.baselineGrid || { increment: 14, start: 36 }, elements: curPage.elements || [] }} onChange={(v) => magStore.updatePage(magStore.currentPageNumber, v as Partial<MagPageData>)} /></div>;
                  }
                  return null;
                })()}
                {magRightTab === 'layers' && (() => {
                  const curPage = magStore.pages.find(p => p.pageNumber === magStore.currentPageNumber);
                  return (
                    <MagLayersPanel
                      elements={curPage?.elements || []}
                      selectedIds={magStore.selectedIds}
                      onSelect={(id) => magStore.selectElement(id)}
                      onToggleVisibility={(id) => { const el = curPage?.elements.find(e => e.id === id); if (el) magStore.updateElement(id, { visible: !el.visible }); }}
                      onToggleLock={(id) => { const el = curPage?.elements.find(e => e.id === id); if (el) magStore.updateElement(id, { locked: !el.locked }); }}
                      onReorderZ={(id, dir) => { if (dir === 'up') magStore.bringToFront([id]); else magStore.sendToBack([id]); }}
                    />
                  );
                })()}
                {magRightTab === 'viewer' && (() => {
                  const meta = page?.seo_meta || {};
                  const saveMeta = async (patch: Record<string, unknown>) => {
                    try { await pagesApi.update(siteId, pageId, { seo_meta: { ...meta, ...patch } }); } catch {}
                  };

                  return (
                  <div className="p-3 space-y-4">
                    <div className="text-[10px] text-base-content/30 uppercase tracking-wider">Flipbook Viewer</div>

                    <div>
                      <label className="text-[11px] text-base-content/50 mb-1 block">Page transition</label>
                      <select className="select select-bordered select-sm w-full text-[12px]"
                        value={meta.viewer_transition as string || 'turn'}
                        onChange={e => saveMeta({ viewer_transition: e.target.value })}>
                        <option value="turn">Page turn (realistic)</option>
                        <option value="curl">Page curl (3D)</option>
                        <option value="flip">Flip (simple)</option>
                        <option value="slide">Slide</option>
                        <option value="fade">Fade</option>
                        <option value="none">None (instant)</option>
                      </select>
                    </div>

                    <div>
                      <label className="text-[11px] text-base-content/50 mb-1 block">Display mode</label>
                      <div className="space-y-2 mt-1">
                        {([
                          { value: 'spread', label: 'Two-page spread', desc: 'Side-by-side pages with page turn animation' },
                          { value: 'single', label: 'Single page', desc: 'One page at a time with slide navigation' },
                          { value: 'scroll', label: 'Scroll', desc: 'All pages stacked, scroll to read' },
                          { value: 'flipbook', label: 'Flipbook', desc: 'Realistic 3D page turns with curl, shadows & swipe gestures' },
                        ] as const).map(opt => (
                          <label key={opt.value} className="flex items-start gap-2 cursor-pointer group">
                            <input
                              type="radio"
                              name="viewer_display_mode"
                              value={opt.value}
                              checked={(meta.viewer_display_mode as string ?? 'spread') === opt.value}
                              onChange={() => saveMeta({ viewer_display_mode: opt.value })}
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

                    <div>
                      <label className="text-[11px] text-base-content/50 mb-1 block">Background</label>
                      <select className="select select-bordered select-sm w-full text-[12px]"
                        value={meta.viewer_bg as string || '#0a0a0a'}
                        onChange={e => saveMeta({ viewer_bg: e.target.value })}>
                        <option value="#0a0a0a">Dark</option>
                        <option value="#1a1a2e">Navy</option>
                        <option value="#2d2d2d">Charcoal</option>
                        <option value="#f5f3ef">Warm light</option>
                        <option value="#f0f0f0">Light grey</option>
                        <option value="#ffffff">White</option>
                      </select>
                    </div>

                    <div>
                      <label className="text-[11px] text-base-content/50 mb-1 block">
                        Speed: {meta.viewer_speed as number || 500}ms
                      </label>
                      <input type="range" min={200} max={1500} step={50}
                        value={Number(meta.viewer_speed) || 500}
                        className="range range-sm range-primary"
                        onChange={e => saveMeta({ viewer_speed: Number(e.target.value) })} />
                      <div className="flex justify-between text-[9px] text-base-content/25 mt-1">
                        <span>Fast</span><span>Slow</span>
                      </div>
                    </div>

                    <div className="border-t border-base-300/20 pt-3 space-y-3">
                      <div className="text-[10px] text-base-content/30 uppercase tracking-wider">Page Numbers</div>

                      <div className="flex items-center gap-2">
                        <label className="text-[11px] text-base-content/50">Show</label>
                        <input type="checkbox" className="toggle toggle-sm toggle-primary"
                          checked={meta.viewer_pn !== false}
                          onChange={e => saveMeta({ viewer_pn: e.target.checked })} />
                      </div>

                      <div>
                        <label className="text-[11px] text-base-content/50 mb-1 block">Position</label>
                        <select className="select select-bordered select-sm w-full text-[12px]"
                          value={meta.viewer_pn_pos as string || 'bottom'}
                          onChange={e => saveMeta({ viewer_pn_pos: e.target.value })}>
                          <option value="bottom">Bottom</option>
                          <option value="top">Top</option>
                        </select>
                      </div>

                      <div>
                        <label className="text-[11px] text-base-content/50 mb-1 block">Alignment</label>
                        <select className="select select-bordered select-sm w-full text-[12px]"
                          value={meta.viewer_pn_align as string || 'outer'}
                          onChange={e => saveMeta({ viewer_pn_align: e.target.value })}>
                          <option value="outer">Outer (mirrored)</option>
                          <option value="center">Center</option>
                          <option value="left">Left</option>
                          <option value="right">Right</option>
                        </select>
                      </div>

                      <div>
                        <label className="text-[11px] text-base-content/50 mb-1 block">Font size</label>
                        <select className="select select-bordered select-sm w-full text-[12px]"
                          value={meta.viewer_pn_size as string || '9'}
                          onChange={e => saveMeta({ viewer_pn_size: e.target.value })}>
                          <option value="7">7px</option>
                          <option value="8">8px</option>
                          <option value="9">9px</option>
                          <option value="10">10px</option>
                          <option value="11">11px</option>
                          <option value="12">12px</option>
                          <option value="14">14px</option>
                        </select>
                      </div>
                    </div>

                    <div className="border-t border-base-300/20 pt-3">
                      <a href={`/issue/${page?.slug}`} target="_blank" rel="noopener"
                        className="btn btn-sm btn-primary w-full text-[12px] gap-1">
                        Preview flipbook
                      </a>
                    </div>
                  </div>
                  );
                })()}
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════
// Page Editor Sidebar — Page settings + Block settings + Add blocks
// ═══════════════════════════════════════════
function PageEditorSidebar({ page, siteId, pageId, layouts, publicBase, siteSlug, metaRef, onDirty }: {
  page: any; siteId: string; pageId: string;
  layouts: any[]; publicBase: string; siteSlug: string;
  metaRef?: React.MutableRefObject<Record<string, any> | null>;
  onDirty?: () => void;
}) {
  const selectedBlockId = useEditorStore((s) => s.selectedBlockId);
  const [activeTab, setActiveTab] = useState<'page' | 'block' | 'add' | 'seo' | 'history'>('page');

  useEffect(() => {
    if (selectedBlockId) setActiveTab('block');
  }, [selectedBlockId]);

  return (
    <div className="w-80 min-w-[320px] border-l border-gray-200 bg-white h-full overflow-y-auto flex flex-col shrink-0 snap-start">
      <div className="flex border-b border-gray-200">
        {([
          { key: 'page' as const, label: 'Page' },
          { key: 'block' as const, label: 'Block' },
          { key: 'add' as const, label: '+ Add' },
          { key: 'seo' as const, label: 'SEO' },
          { key: 'history' as const, label: 'History' },
        ]).map(tab => (
          <button key={tab.key} onClick={() => setActiveTab(tab.key)}
            className={`flex-1 px-1.5 py-2.5 text-[10px] font-medium border-b-2 transition-colors ${
              activeTab === tab.key ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}>
            {tab.label}
          </button>
        ))}
      </div>

      <div className="flex-1 overflow-y-auto">
        {activeTab === 'page' && (
          <PageSettingsPanel page={page} siteId={siteId} pageId={pageId}
            layouts={layouts} publicBase={publicBase} siteSlug={siteSlug}
            metaRef={metaRef} onDirty={onDirty} />
        )}
        {activeTab === 'block' && <BlockSettings />}
        {activeTab === 'add' && <BlockPicker />}
        {activeTab === 'seo' && (
          <SeoAnalyzer
            pageTitle={page?.title}
            seoTitle={page?.seo_meta?.title as string}
            seoDescription={page?.seo_meta?.description as string}
            slug={page?.slug}
          />
        )}
        {activeTab === 'history' && <VersionHistory siteId={siteId} pageId={pageId} type="pages" />}
      </div>
    </div>

  );
}

function PageThemePicker({ siteId, pageId }: { siteId: string; pageId: string }) {
  const queryClient = useQueryClient();
  const { data: themes } = useQuery<any[]>({
    queryKey: ['theme-engine', siteId],
    queryFn: () => themeEngine.list(siteId).then((r: any) => r.data?.data || []),
  });

  const [saving, setSaving] = useState(false);
  const [selectedTheme, setSelectedTheme] = useState<string>('');

  const assignTheme = async (themeId: string) => {
    setSaving(true);
    try {
      if (themeId) {
        // Per-page assignment only — don't change site-wide theme
        await api.post(`/sites/${siteId}/theme-engine/assign`, { theme_id: themeId, page_id: pageId });
      } else {
        // Clear per-page override
        await api.post(`/sites/${siteId}/theme-engine/assign`, { theme_id: null, page_id: pageId });
      }
      setSelectedTheme(themeId);
      queryClient.invalidateQueries({ queryKey: ['theme-engine', siteId] });
    } catch (e) {
      console.error('Theme assign failed:', e);
    } finally {
      setSaving(false);
    }
  };

  if (!themes?.length) return null;

  return (
    <div>
      <label className="text-[11px] text-gray-500 mb-1 block">Page Theme</label>
      <select
        value={selectedTheme}
        onChange={e => assignTheme(e.target.value)}
        className="select select-bordered select-sm w-full text-[12px]"
        disabled={saving}
      >
        <option value="">Site default</option>
        {themes.map((t: any) => (
          <option key={t.id} value={t.id}>
            {t.name}{t.is_system ? ' (System)' : ''}{t.is_assigned ? ' (Active)' : ''}
          </option>
        ))}
      </select>
      <p className="text-[10px] text-gray-400 mt-0.5">Override the site theme for this page only</p>
    </div>
  );
}

function PageSettingsPanel({ page, siteId, pageId, layouts, publicBase, siteSlug, metaRef, onDirty }: {
  page: any; siteId: string; pageId: string;
  layouts: any[]; publicBase: string; siteSlug: string;
  metaRef?: React.MutableRefObject<Record<string, any> | null>;
  onDirty?: () => void;
}) {
  const [saving, setSaving] = useState(false);
  const [localMeta, setLocalMeta] = useState<Record<string, any>>(page?.seo_meta || {});
  useEffect(() => {
    if (page?.seo_meta) {
      setLocalMeta(page.seo_meta);
      if (metaRef) metaRef.current = page.seo_meta;
    }
  }, [page?.seo_meta]);

  const saveSetting = async (field: string, value: unknown) => {
    if (field === 'seo_meta') {
      const meta = value as Record<string, any>;
      setLocalMeta(meta);
      // Update ref IMMEDIATELY so Save button can read it
      if (metaRef) metaRef.current = meta;
      if (onDirty) onDirty();
    }
    // Non-appearance fields save immediately
    if (field !== 'seo_meta') {
      setSaving(true);
      try {
        await pagesApi.update(siteId, pageId, { [field]: value });
      } catch (e) {
        console.error('Save failed:', e);
      } finally {
        setSaving(false);
      }
    }
  };

  return (
    <div className="p-3 space-y-4">
      {saving && <div className="text-[10px] text-primary">Saving...</div>}

      {/* Title */}
      <div>
        <label className="text-[11px] text-gray-500 mb-1 block flex items-center gap-1">
          <FileText size={11} /> Title
        </label>
        <input type="text" defaultValue={page?.title || ''} className="input input-bordered input-sm w-full text-[12px]"
          onBlur={e => { if (e.target.value !== page?.title) saveSetting('title', e.target.value); }} />
      </div>

      {/* Slug */}
      <div>
        <label className="text-[11px] text-gray-500 mb-1 block flex items-center gap-1">
          <Globe size={11} /> URL slug
        </label>
        <input type="text" defaultValue={page?.slug || ''} className="input input-bordered input-sm w-full text-[12px] font-mono"
          onBlur={e => { if (e.target.value !== page?.slug) saveSetting('slug', e.target.value); }} />
        <p className="text-[10px] text-gray-400 mt-0.5">{publicBase}/{page?.slug || ''}</p>
      </div>

      {/* Status */}
      <div>
        <label className="text-[11px] text-gray-500 mb-1 block">Status</label>
        <select defaultValue={page?.status || 'draft'} className="select select-bordered select-sm w-full text-[12px]"
          onChange={e => saveSetting('status', e.target.value)}>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
      </div>

      {/* Layout */}
      <div>
        <label className="text-[11px] text-gray-500 mb-1 block">Layout</label>
        <select defaultValue={page?.layout_id || ''} className="select select-bordered select-sm w-full text-[12px]"
          onChange={e => saveSetting('layout_id', e.target.value || null)}>
          <option value="">Standard (default)</option>
          {layouts.map((l: any) => (
            <option key={l.id} value={l.id}>{l.name}{l.is_system ? '' : ' (custom)'}</option>
          ))}
        </select>
        {page?.layout_id && (
          <button onClick={() => saveSetting('layout_id', null)} className="text-[10px] text-blue-500 mt-0.5">
            Reset to default
          </button>
        )}
      </div>

      {/* Editor Mode */}
      <div>
        <label className="text-[11px] text-gray-500 mb-1 block">Editor Mode</label>
        <select defaultValue={page?.editor_mode || 'block'} className="select select-bordered select-sm w-full text-[12px]"
          onChange={e => saveSetting('editor_mode', e.target.value)}>
          <option value="block">Block Editor</option>
          <option value="magazine">Magazine (Canvas)</option>
        </select>
      </div>

      {/* Page Appearance — same controls as blocks */}
      {(() => {
        const pageStyle = localMeta.pageStyle || {};
        const pageData = localMeta.pageData || {};
        const updatePageStyle = (key: string, val: any) => {
          const updated = { ...localMeta, pageStyle: { ...pageStyle, [key]: val } };
          saveSetting('seo_meta', updated);
        };
        const updatePageData = (updates: Record<string, any>) => {
          const updated = { ...localMeta, pageData: { ...pageData, ...updates } };
          saveSetting('seo_meta', updated);
        };
        return (
          <>
            <details className="border-t border-gray-100 pt-3">
              <summary className="text-[11px] text-gray-500 cursor-pointer font-medium">Spacing</summary>
              <div className="mt-2">
                <SpacingPanel value={pageStyle.spacing || {}} onChange={v => updatePageStyle('spacing', v)} style={pageStyle} />
              </div>
            </details>
            <details className="border-t border-gray-100 pt-3">
              <summary className="text-[11px] text-gray-500 cursor-pointer font-medium">Background</summary>
              <div className="mt-2">
                <BackgroundEditor data={pageData} onChange={updatePageData} defaultExpanded />
              </div>
            </details>
            <details className="border-t border-gray-100 pt-3">
              <summary className="text-[11px] text-gray-500 cursor-pointer font-medium">Borders & Shadow</summary>
              <div className="mt-2">
                <VisualPanel value={pageStyle.visual || {}} onChange={v => updatePageStyle('visual', v)} hideBg />
              </div>
            </details>
            <details className="border-t border-gray-100 pt-3">
              <summary className="text-[11px] text-gray-500 cursor-pointer font-medium">Size & Layout</summary>
              <div className="mt-2">
                <LayoutPanel value={pageStyle.layout || {}} onChange={v => updatePageStyle('layout', v)} style={pageStyle} />
              </div>
            </details>
            <details className="border-t border-gray-100 pt-3">
              <summary className="text-[11px] text-gray-500 cursor-pointer font-medium">Animation</summary>
              <div className="mt-2">
                <AnimationPanel value={localMeta.pageAnimation || {}} onChange={v => saveSetting('seo_meta', { ...localMeta, pageAnimation: v })} />
              </div>
            </details>
            <details className="border-t border-gray-100 pt-3">
              <summary className="text-[11px] text-gray-500 cursor-pointer font-medium">Responsive</summary>
              <div className="mt-2">
                <ResponsivePanel value={localMeta.pageResponsive || {}} onChange={v => saveSetting('seo_meta', { ...localMeta, pageResponsive: v })} />
              </div>
            </details>
            <details className="border-t border-gray-100 pt-3">
              <summary className="text-[11px] text-gray-500 cursor-pointer font-medium">Advanced</summary>
              <div className="mt-2">
                <AdvancedPanel value={localMeta.pageAdvanced || {}} onChange={v => saveSetting('seo_meta', { ...localMeta, pageAdvanced: v })} />
              </div>
            </details>
          </>
        );
      })()}

      {/* Page Theme Override */}
      <PageThemePicker siteId={siteId} pageId={pageId} />

      {/* SEO */}
      <div className="border-t border-gray-100 pt-3">
        <label className="text-[11px] text-gray-500 mb-1 block font-medium">SEO</label>
        <div className="space-y-2">
          <div>
            <label className="text-[10px] text-gray-400 mb-0.5 block">Meta Title</label>
            <input type="text" defaultValue={page?.seo_meta?.title || ''} className="input input-bordered input-sm w-full text-[12px]"
              placeholder={page?.title || 'Page title'}
              onBlur={e => saveSetting('seo_meta', { ...localMeta, title: e.target.value })} />
          </div>
          <div>
            <label className="text-[10px] text-gray-400 mb-0.5 block">Meta Description</label>
            <textarea defaultValue={page?.seo_meta?.description || ''} className="textarea textarea-bordered textarea-sm w-full text-[12px] min-h-[60px]"
              placeholder="Brief description for search engines..."
              onBlur={e => saveSetting('seo_meta', { ...localMeta, description: e.target.value })} />
          </div>
          <div>
            <AssetField
              label="OG Image"
              value={page?.seo_meta?.og_image || ''}
              onChange={(url) => saveSetting('seo_meta', { ...localMeta, og_image: url })}
              accept="image"
            />
          </div>
        </div>
      </div>

      {/* Custom Code */}
      <details className="border-t border-gray-100 pt-3">
        <summary className="text-[11px] text-gray-500 cursor-pointer font-medium">Custom Code</summary>
        <div className="space-y-2 mt-2">
          <div>
            <label className="text-[10px] text-gray-400 mb-0.5 block">Head Scripts</label>
            <textarea defaultValue={page?.seo_meta?.head_scripts || ''} className="textarea textarea-bordered textarea-xs w-full text-[10px] font-mono min-h-[50px]"
              placeholder="<script>...</script>"
              onBlur={e => saveSetting('seo_meta', { ...localMeta, head_scripts: e.target.value })} />
          </div>
          <div>
            <label className="text-[10px] text-gray-400 mb-0.5 block">Body Scripts</label>
            <textarea defaultValue={page?.seo_meta?.body_scripts || ''} className="textarea textarea-bordered textarea-xs w-full text-[10px] font-mono min-h-[50px]"
              placeholder="<script>...</script>"
              onBlur={e => saveSetting('seo_meta', { ...localMeta, body_scripts: e.target.value })} />
          </div>
          <div>
            <label className="text-[10px] text-gray-400 mb-0.5 block">Custom CSS</label>
            <textarea defaultValue={page?.seo_meta?.custom_css || ''} className="textarea textarea-bordered textarea-xs w-full text-[10px] font-mono min-h-[50px]"
              placeholder="body { ... }"
              onBlur={e => saveSetting('seo_meta', { ...localMeta, custom_css: e.target.value })} />
          </div>
        </div>
      </details>

      {/* View link */}
      <div className="border-t border-gray-100 pt-3">
        <a href={`/sites/${siteSlug}/${page?.slug || ''}`} target="_blank" rel="noopener"
          className="btn btn-ghost btn-sm w-full text-[12px] gap-1">
          <Eye size={12} /> Preview page
        </a>
      </div>
    </div>
  );
}
