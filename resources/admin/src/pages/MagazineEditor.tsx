import { useState, useEffect, useRef, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft, Save, Loader2, Plus, Trash2, Type, ImageIcon, Video, MousePointer,
  ZoomIn, ZoomOut, Copy, ChevronUp, ChevronDown, Eye,
} from 'lucide-react';
import { magazines } from '@/lib/api';
import { blockRegistry } from '@/components/blocks/registry';
import '@/components/blocks';

// ─── Types ───
interface MagElement {
  id: string;
  type: 'text' | 'image' | 'video' | 'hotspot' | 'shape' | 'block';
  content: Record<string, unknown>;
  x: number; y: number; width: number; height: number;
  rotation: number; z_index: number; style: Record<string, unknown>;
}

interface MagPage {
  id: string | null;
  title: string;
  sort_order: number;
  background_color: string;
  background_image: string | null;
  elements: MagElement[];
}

// ─── Helpers ───
const uid = () => crypto.randomUUID();
const clamp = (v: number, min: number, max: number) => Math.min(Math.max(v, min), max);

export default function MagazineEditor() {
  const { siteId = '', magazineId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const canvasRef = useRef<HTMLDivElement>(null);

  // State
  const [title, setTitle] = useState('');
  const [slug, setSlug] = useState('');
  const [status, setStatus] = useState('draft');
  const [pageWidth, setPageWidth] = useState(210);
  const [pageHeight, setPageHeight] = useState(297);
  const [pages, setPages] = useState<MagPage[]>([]);
  const [activePageIdx, setActivePageIdx] = useState(0);
  const [selectedElId, setSelectedElId] = useState<string | null>(null);
  const [zoom, setZoom] = useState(0.6);
  const [isDirty, setIsDirty] = useState(false);
  const [rightTab, setRightTab] = useState<'element' | 'page' | 'magazine' | 'blocks'>('page');
  const [settings, setSettings] = useState<Record<string, unknown>>({});

  // Drag state
  const [dragging, setDragging] = useState<{ elId: string; startX: number; startY: number; origX: number; origY: number } | null>(null);
  const [resizing, setResizing] = useState<{ elId: string; handle: string; startX: number; startY: number; origX: number; origY: number; origW: number; origH: number } | null>(null);

  // Load
  const { data: magData, isLoading } = useQuery({
    queryKey: ['magazine', siteId, magazineId],
    queryFn: () => magazines.get(siteId, magazineId).then((r: any) => r.data.data),
  });

  useEffect(() => {
    if (magData) {
      setTitle(magData.title);
      setSlug(magData.slug);
      setStatus(magData.status);
      setPageWidth(magData.page_width);
      setPageHeight(magData.page_height);
      setSettings(magData.settings || {});
      const loadedPages: MagPage[] = (magData.pages || []).map((p: any) => ({
        id: p.id,
        title: p.title || '',
        sort_order: p.sort_order,
        background_color: p.background_color || '#ffffff',
        background_image: p.background_image || null,
        elements: (p.elements || []).map((e: any) => ({
          id: e.id || uid(),
          type: e.type,
          content: e.content || {},
          x: parseFloat(e.x), y: parseFloat(e.y),
          width: parseFloat(e.width), height: parseFloat(e.height),
          rotation: parseFloat(e.rotation || 0),
          z_index: e.z_index || 0,
          style: e.style || {},
        })),
      }));
      setPages(loadedPages.length > 0 ? loadedPages : [{ id: null, title: 'Cover', sort_order: 0, background_color: '#ffffff', background_image: null, elements: [] }]);
    }
  }, [magData]);

  const setSetting = (key: string, value: unknown) => { setSettings(s => ({ ...s, [key]: value })); dirty(); };
  const getSetting = (key: string, def: unknown = '') => settings[key] ?? def;
  const activePage = pages[activePageIdx] || null;
  const selectedEl = activePage?.elements.find(e => e.id === selectedElId) || null;
  const dirty = () => setIsDirty(true);

  // ─── Page operations ───
  const addPage = () => {
    const newPage: MagPage = { id: null, title: '', sort_order: pages.length, background_color: '#ffffff', background_image: null, elements: [] };
    setPages([...pages, newPage]);
    setActivePageIdx(pages.length);
    setSelectedElId(null);
    dirty();
  };

  const deletePage = (idx: number) => {
    if (pages.length <= 1) return;
    const next = pages.filter((_, i) => i !== idx).map((p, i) => ({ ...p, sort_order: i }));
    setPages(next);
    setActivePageIdx(Math.min(idx, next.length - 1));
    setSelectedElId(null);
    dirty();
  };

  const movePage = (idx: number, dir: -1 | 1) => {
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= pages.length) return;
    const arr = [...pages];
    [arr[idx], arr[newIdx]] = [arr[newIdx], arr[idx]];
    setPages(arr.map((p, i) => ({ ...p, sort_order: i })));
    setActivePageIdx(newIdx);
    dirty();
  };

  const updatePage = (updates: Partial<MagPage>) => {
    setPages(pages.map((p, i) => i === activePageIdx ? { ...p, ...updates } : p));
    dirty();
  };

  // ─── Element operations ───
  const addElement = (type: MagElement['type']) => {
    const defaults: Record<string, Partial<MagElement>> = {
      text: { width: 60, height: 15, content: { html: '<p style="font-size:24px;font-family:serif;">Your text here</p>' } },
      image: { width: 50, height: 40, content: { src: '', alt: '', objectFit: 'cover' } },
      video: { width: 60, height: 35, content: { provider: 'youtube', videoId: '', autoplay: false } },
      hotspot: { width: 15, height: 15, content: { url: '#', tooltip: 'Click me', shape: 'circle' } },
      shape: { width: 30, height: 30, content: { shapeType: 'rectangle', fill: '#000000', stroke: 'none' } },
    };

    const el: MagElement = {
      id: uid(),
      type,
      content: (defaults[type]?.content as Record<string, unknown>) || {},
      x: 20, y: 20,
      width: defaults[type]?.width || 40,
      height: defaults[type]?.height || 30,
      rotation: 0,
      z_index: (activePage?.elements.length || 0) + 1,
      style: {},
    };

    updatePage({ elements: [...(activePage?.elements || []), el] });
    setSelectedElId(el.id);
    setRightTab('element');
  };

  // Add a block from blockRegistry as a magazine element
  const addBlockElement = (blockType: string) => {
    const reg = blockRegistry.get(blockType);
    if (!reg) return;
    const def = reg.definition;

    const sizeMap: Record<string, { w: number; h: number }> = {
      hero: { w: 90, h: 50 },
      section: { w: 90, h: 40 },
      columns: { w: 80, h: 35 },
      container: { w: 80, h: 30 },
      fullbleed: { w: 90, h: 50 },
      gallery: { w: 80, h: 40 },
    };
    const size = sizeMap[blockType] || { w: 60, h: 20 };
    const existingCount = activePage?.elements.length || 0;

    const el: MagElement = {
      id: uid(),
      type: 'block',
      content: {
        blockType,
        blockData: { ...def.defaultData },
        blockLabel: def.label,
      },
      x: 5 + (existingCount % 4) * 5,
      y: 5 + (existingCount % 4) * 5,
      width: size.w,
      height: size.h,
      rotation: 0,
      z_index: existingCount + 1,
      style: {},
    };

    updatePage({ elements: [...(activePage?.elements || []), el] });
    setSelectedElId(el.id);
    setRightTab('element');
  };

  const updateElement = (elId: string, updates: Partial<MagElement>) => {
    if (!activePage) return;
    updatePage({
      elements: activePage.elements.map(e => e.id === elId ? { ...e, ...updates } : e),
    });
  };

  const deleteElement = (elId: string) => {
    if (!activePage) return;
    updatePage({ elements: activePage.elements.filter(e => e.id !== elId) });
    if (selectedElId === elId) setSelectedElId(null);
  };

  const duplicateElement = (elId: string) => {
    const el = activePage?.elements.find(e => e.id === elId);
    if (!el) return;
    const dup = { ...el, id: uid(), x: el.x + 3, y: el.y + 3, z_index: (activePage?.elements.length || 0) + 1 };
    updatePage({ elements: [...(activePage?.elements || []), dup] });
    setSelectedElId(dup.id);
    dirty();
  };

  // ─── Canvas drag/resize ───
  const handleCanvasPointerDown = (e: React.PointerEvent, elId: string) => {
    e.stopPropagation();
    e.preventDefault();
    setSelectedElId(elId);
    setRightTab('element');
    const el = activePage?.elements.find(el => el.id === elId);
    if (!el) return;
    setDragging({ elId, startX: e.clientX, startY: e.clientY, origX: el.x, origY: el.y });
  };

  const handleResizePointerDown = (e: React.PointerEvent, elId: string, handle: string) => {
    e.stopPropagation();
    e.preventDefault();
    const el = activePage?.elements.find(el => el.id === elId);
    if (!el) return;
    setResizing({ elId, handle, startX: e.clientX, startY: e.clientY, origX: el.x, origY: el.y, origW: el.width, origH: el.height });
  };

  const handlePointerMove = useCallback((e: PointerEvent) => {
    if (!canvasRef.current) return;
    const canvasRect = canvasRef.current.getBoundingClientRect();
    const pageW = canvasRect.width;
    const pageH = canvasRect.height;

    if (dragging) {
      const dx = ((e.clientX - dragging.startX) / pageW) * 100;
      const dy = ((e.clientY - dragging.startY) / pageH) * 100;
      updateElement(dragging.elId, {
        x: clamp(dragging.origX + dx, 0, 95),
        y: clamp(dragging.origY + dy, 0, 95),
      });
    }

    if (resizing) {
      const dx = ((e.clientX - resizing.startX) / pageW) * 100;
      const dy = ((e.clientY - resizing.startY) / pageH) * 100;
      const h = resizing.handle;
      let newX = resizing.origX, newY = resizing.origY, newW = resizing.origW, newH = resizing.origH;

      if (h.includes('e')) newW = Math.max(5, resizing.origW + dx);
      if (h.includes('s')) newH = Math.max(5, resizing.origH + dy);
      if (h.includes('w')) { newW = Math.max(5, resizing.origW - dx); newX = resizing.origX + dx; }
      if (h.includes('n')) { newH = Math.max(5, resizing.origH - dy); newY = resizing.origY + dy; }

      updateElement(resizing.elId, { x: newX, y: newY, width: newW, height: newH });
    }
  }, [dragging, resizing]);

  const handlePointerUp = useCallback(() => {
    if (dragging || resizing) dirty();
    setDragging(null);
    setResizing(null);
  }, [dragging, resizing]);

  useEffect(() => {
    window.addEventListener('pointermove', handlePointerMove);
    window.addEventListener('pointerup', handlePointerUp);
    return () => { window.removeEventListener('pointermove', handlePointerMove); window.removeEventListener('pointerup', handlePointerUp); };
  }, [handlePointerMove, handlePointerUp]);

  // ─── Save ───
  const saveMutation = useMutation({
    mutationFn: async () => {
      await magazines.update(siteId, magazineId, { title, slug, status, settings, page_width: pageWidth, page_height: pageHeight });
      await magazines.savePages(siteId, magazineId, pages);
    },
    onSuccess: () => {
      setIsDirty(false);
      queryClient.invalidateQueries({ queryKey: ['magazine', siteId, magazineId] });
    },
  });

  const publishMutation = useMutation({
    mutationFn: async () => {
      await magazines.update(siteId, magazineId, { title, slug, status: 'published', settings, page_width: pageWidth, page_height: pageHeight });
      await magazines.savePages(siteId, magazineId, pages);
    },
    onSuccess: () => {
      setStatus('published');
      setIsDirty(false);
      queryClient.invalidateQueries({ queryKey: ['magazine', siteId, magazineId] });
    },
  });

  if (isLoading) return <div className="flex items-center justify-center h-screen bg-base-200"><span className="loading loading-spinner loading-sm text-base-content/20" /></div>;

  return (
    <div className="flex flex-col h-screen bg-base-200 select-none" data-theme={localStorage.getItem('admin-theme') || 'cms-admin'}>
      {/* ─── Toolbar ─── */}
      <div className="flex items-center justify-between h-12 px-4 bg-base-100 border-b border-base-300/30 shrink-0">
        <div className="flex items-center gap-3">
          <button onClick={() => navigate(`/sites/${siteId}/magazines`)} className="btn btn-ghost btn-xs btn-square"><ArrowLeft size={16} /></button>
          <input value={title} onChange={e => { setTitle(e.target.value); dirty(); }}
            className="text-sm font-medium bg-transparent border-none outline-none text-base-content/90 w-48" />
          {isDirty && <span className="text-[11px] text-warning font-medium">unsaved</span>}
        </div>
        <div className="flex items-center gap-2">
          {/* Element tools */}
          <div className="flex items-center gap-0.5 border-r border-base-300/30 pr-2 mr-1">
            <button onClick={() => addElement('text')} className="btn btn-ghost btn-xs gap-1 text-[11px]" title="Add text"><Type size={14} /> Text</button>
            <button onClick={() => addElement('image')} className="btn btn-ghost btn-xs gap-1 text-[11px]" title="Add image"><ImageIcon size={14} /> Image</button>
            <button onClick={() => addElement('video')} className="btn btn-ghost btn-xs gap-1 text-[11px]" title="Add video"><Video size={14} /> Video</button>
          </div>
          {/* Zoom */}
          <div className="flex items-center gap-1 text-[11px] text-base-content/40">
            <button onClick={() => setZoom(z => Math.max(0.2, z - 0.1))} className="btn btn-ghost btn-xs btn-square"><ZoomOut size={13} /></button>
            <span className="w-10 text-center">{Math.round(zoom * 100)}%</span>
            <button onClick={() => setZoom(z => Math.min(2, z + 0.1))} className="btn btn-ghost btn-xs btn-square"><ZoomIn size={13} /></button>
          </div>
          <div className="h-5 w-px bg-base-300/30" />
          <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !isDirty}
            className="btn btn-sm btn-ghost text-[12px] gap-1">
            {saveMutation.isPending ? <Loader2 size={13} className="animate-spin" /> : <Save size={13} />} Save
          </button>
          <button onClick={() => publishMutation.mutate()} disabled={publishMutation.isPending}
            className="btn btn-sm btn-primary text-[12px] gap-1">
            {publishMutation.isPending ? <Loader2 size={13} className="animate-spin" /> : <Eye size={13} />} Publish
          </button>
        </div>
      </div>

      <div className="flex flex-1 overflow-hidden">
        {/* ─── LEFT: Page thumbnails ─── */}
        <div className="w-40 bg-base-100 border-r border-base-300/30 overflow-y-auto p-2 space-y-1.5 shrink-0">
          {pages.map((page, idx) => (
            <div key={idx} onClick={() => { setActivePageIdx(idx); setSelectedElId(null); }}
              className={`relative rounded-md overflow-hidden cursor-pointer border-2 transition-all ${
                idx === activePageIdx ? 'border-primary' : 'border-base-300/30 hover:border-base-300/60'
              }`}>
              <div className="aspect-[210/297] relative" style={{ backgroundColor: page.background_color }}>
                {page.background_image && <img src={page.background_image} className="absolute inset-0 w-full h-full object-cover" />}
                {/* Mini element previews */}
                {page.elements.map(el => (
                  <div key={el.id} className="absolute" style={{
                    left: `${el.x}%`, top: `${el.y}%`, width: `${el.width}%`, height: `${el.height}%`,
                    backgroundColor: el.type === 'text' ? 'rgba(0,0,0,0.05)' : el.type === 'image' ? 'rgba(0,0,0,0.1)' : 'rgba(0,0,0,0.08)',
                  }} />
                ))}
              </div>
              <div className="absolute bottom-0 left-0 right-0 bg-base-100/80 backdrop-blur px-1.5 py-0.5 text-[9px] text-base-content/50 flex justify-between">
                <span>{idx + 1}</span>
                <span>{page.title || 'untitled'}</span>
              </div>
            </div>
          ))}
          <button onClick={addPage} className="w-full aspect-[210/297] border-2 border-dashed border-base-300/40 rounded-md flex items-center justify-center hover:border-base-300/70 transition-colors">
            <Plus size={16} className="text-base-content/20" />
          </button>
        </div>

        {/* ─── CENTER: Canvas ─── */}
        <div className="flex-1 overflow-auto flex items-center justify-center p-8 bg-base-200"
          onClick={() => setSelectedElId(null)}>
          {activePage && (
            <div ref={canvasRef}
              className="relative shadow-lg"
              style={{
                width: `${pageWidth * zoom * 3}px`,
                height: `${pageHeight * zoom * 3}px`,
                backgroundColor: activePage.background_color,
                backgroundImage: activePage.background_image ? `url(${activePage.background_image})` : undefined,
                backgroundSize: 'cover',
                backgroundPosition: 'center',
              }}>
              {/* Elements */}
              {activePage.elements.map(el => (
                <div key={el.id}
                  className={`absolute group ${selectedElId === el.id ? 'ring-2 ring-primary ring-offset-1' : 'hover:ring-1 hover:ring-primary/40'}`}
                  style={{
                    left: `${el.x}%`, top: `${el.y}%`, width: `${el.width}%`, height: `${el.height}%`,
                    zIndex: el.z_index,
                    transform: el.rotation ? `rotate(${el.rotation}deg)` : undefined,
                    cursor: dragging?.elId === el.id ? 'grabbing' : 'grab',
                  }}
                  onPointerDown={e => handleCanvasPointerDown(e, el.id)}
                  onClick={e => e.stopPropagation()}>

                  {/* Render element content */}
                  {el.type === 'text' && (
                    <div className="w-full h-full overflow-hidden p-1"
                      contentEditable suppressContentEditableWarning
                      dangerouslySetInnerHTML={{ __html: (el.content.html as string) || '' }}
                      onBlur={e => updateElement(el.id, { content: { ...el.content, html: e.currentTarget.innerHTML } })}
                      style={{ cursor: 'text', outline: 'none' }}
                    />
                  )}
                  {el.type === 'image' && (
                    (el.content.src as string) ? (
                      <img src={el.content.src as string} alt={el.content.alt as string || ''} className="w-full h-full" style={{ objectFit: ((el.content.objectFit as string) || 'cover') as React.CSSProperties['objectFit'] }} />
                    ) : (
                      <div className="w-full h-full bg-base-300/30 flex items-center justify-center">
                        <ImageIcon size={24} className="text-base-content/20" />
                      </div>
                    )
                  )}
                  {el.type === 'video' && (
                    <div className="w-full h-full bg-neutral/10 flex items-center justify-center">
                      <Video size={24} className="text-base-content/30" />
                      <span className="ml-2 text-xs text-base-content/30">{(el.content.videoId as string) || 'No video'}</span>
                    </div>
                  )}
                  {el.type === 'shape' && (
                    <div className="w-full h-full" style={{
                      backgroundColor: el.content.fill as string || '#000',
                      borderRadius: el.content.shapeType === 'circle' ? '50%' : '0',
                    }} />
                  )}
                  {el.type === 'block' && (() => {
                    const reg = blockRegistry.get(el.content.blockType as string);
                    if (!reg) return <div className="w-full h-full bg-base-300/20 flex items-center justify-center text-xs text-base-content/30">Unknown block</div>;
                    const { Preview } = reg;
                    const fakeBlock = {
                      id: el.id,
                      type: el.content.blockType as string,
                      data: (el.content.blockData as Record<string, unknown>) || {},
                      children: [],
                      order: 0,
                    };
                    return (
                      <div className="w-full h-full overflow-hidden">
                        <Preview block={fakeBlock} isSelected={false} onUpdate={() => {}} onSelect={() => {}} />
                      </div>
                    );
                  })()}

                  {/* Resize handles */}
                  {selectedElId === el.id && (
                    <>
                      {['nw','ne','sw','se'].map(h => (
                        <div key={h}
                          className="absolute w-2.5 h-2.5 bg-primary border border-primary-content rounded-sm"
                          style={{
                            top: h.includes('n') ? -5 : undefined, bottom: h.includes('s') ? -5 : undefined,
                            left: h.includes('w') ? -5 : undefined, right: h.includes('e') ? -5 : undefined,
                            cursor: `${h}-resize`, zIndex: 999,
                          }}
                          onPointerDown={e => handleResizePointerDown(e, el.id, h)}
                        />
                      ))}
                    </>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>

        {/* ─── RIGHT: Properties ─── */}
        <div className="w-72 bg-base-100 border-l border-base-300/30 overflow-y-auto shrink-0">
          {/* Tabs */}
          <div className="flex border-b border-base-300/30 sticky top-0 bg-base-100 z-10">
            <button onClick={() => setRightTab('blocks')} className={`flex-1 px-2 py-2 text-[11px] font-medium ${rightTab === 'blocks' ? 'border-b-2 border-primary text-primary' : 'text-base-content/40'}`}>+ Add</button>
            <button onClick={() => setRightTab('element')} className={`flex-1 px-2 py-2 text-[11px] font-medium ${rightTab === 'element' ? 'border-b-2 border-primary text-primary' : 'text-base-content/40'}`}>Element</button>
            <button onClick={() => setRightTab('page')} className={`flex-1 px-2 py-2 text-[11px] font-medium ${rightTab === 'page' ? 'border-b-2 border-primary text-primary' : 'text-base-content/40'}`}>Page</button>
            <button onClick={() => setRightTab('magazine')} className={`flex-1 px-2 py-2 text-[11px] font-medium ${rightTab === 'magazine' ? 'border-b-2 border-primary text-primary' : 'text-base-content/40'}`}>Settings</button>
          </div>

          <div className="p-3 space-y-3 overflow-y-auto" style={{ maxHeight: 'calc(100vh - 160px)' }}>
            {/* ── Blocks picker ── */}
            {rightTab === 'blocks' && (() => {
              const allBlocks = blockRegistry.getAll();
              const categories = new Map<string, Array<{ type: string; label: string }>>();
              const categoryOrder = ['typography', 'content', 'layout', 'media', 'blog', 'interactive', 'data', 'commerce', 'forms', 'embed'];

              for (const [, reg] of allBlocks) {
                const cat = reg.definition.category;
                if (!categories.has(cat)) categories.set(cat, []);
                categories.get(cat)!.push({ type: reg.definition.type, label: reg.definition.label });
              }

              return (
                <div className="space-y-4">
                  {/* Quick add native elements */}
                  <div>
                    <div className="text-[10px] font-medium uppercase tracking-wider text-base-content/30 mb-1.5">Native elements</div>
                    <div className="grid grid-cols-2 gap-1">
                      {([
                        { type: 'text' as const, label: 'Text', icon: '✎' },
                        { type: 'image' as const, label: 'Image', icon: '🖼' },
                        { type: 'video' as const, label: 'Video', icon: '▶' },
                        { type: 'shape' as const, label: 'Shape', icon: '■' },
                      ]).map(item => (
                        <button key={item.type} onClick={() => addElement(item.type)}
                          className="btn btn-ghost btn-xs justify-start text-[11px] gap-1">
                          <span className="opacity-50">{item.icon}</span> {item.label}
                        </button>
                      ))}
                    </div>
                  </div>

                  <div className="border-t border-base-300/20 pt-2">
                    <div className="text-[10px] font-medium uppercase tracking-wider text-base-content/30 mb-1.5">All blocks ({allBlocks.size})</div>
                  </div>

                  {categoryOrder.map(cat => {
                    const items = categories.get(cat);
                    if (!items || items.length === 0) return null;
                    return (
                      <div key={cat}>
                        <div className="text-[9px] font-medium uppercase tracking-wider text-base-content/20 mb-1">{cat} ({items.length})</div>
                        <div className="space-y-0">
                          {items.map(item => (
                            <button key={item.type} onClick={() => addBlockElement(item.type)}
                              className="flex w-full items-center gap-2 px-2 py-1 rounded text-left text-[11px] text-base-content/60 hover:bg-base-300/20 hover:text-base-content/80 transition-colors">
                              {item.label}
                            </button>
                          ))}
                        </div>
                      </div>
                    );
                  })}

                  {/* Catch any uncategorized */}
                  {Array.from(categories.entries())
                    .filter(([cat]) => !categoryOrder.includes(cat))
                    .map(([cat, items]) => (
                      <div key={cat}>
                        <div className="text-[9px] font-medium uppercase tracking-wider text-base-content/20 mb-1">{cat} ({items.length})</div>
                        <div className="space-y-0">
                          {items.map(item => (
                            <button key={item.type} onClick={() => addBlockElement(item.type)}
                              className="flex w-full items-center gap-2 px-2 py-1 rounded text-left text-[11px] text-base-content/60 hover:bg-base-300/20 transition-colors">
                              {item.label}
                            </button>
                          ))}
                        </div>
                      </div>
                    ))}
                </div>
              );
            })()}

            {/* ── Element properties ── */}
            {rightTab === 'element' && selectedEl && (
              <>
                <div className="flex items-center justify-between">
                  <span className="text-xs font-medium text-base-content/60 uppercase">{selectedEl.type}</span>
                  <div className="flex gap-0.5">
                    <button onClick={() => duplicateElement(selectedEl.id)} className="btn btn-ghost btn-xs btn-square" title="Duplicate"><Copy size={12} /></button>
                    <button onClick={() => deleteElement(selectedEl.id)} className="btn btn-ghost btn-xs btn-square text-error" title="Delete"><Trash2 size={12} /></button>
                  </div>
                </div>

                {/* Position */}
                <div className="grid grid-cols-2 gap-2">
                  {(['x', 'y', 'width', 'height'] as const).map(f => (
                    <div key={f}>
                      <label className="text-[10px] text-base-content/40 uppercase">{f}</label>
                      <input type="number" step="0.5" value={selectedEl[f]}
                        onChange={e => updateElement(selectedEl.id, { [f]: parseFloat(e.target.value) || 0 })}
                        className="input input-bordered input-xs w-full text-[11px]" />
                    </div>
                  ))}
                </div>

                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Z-index</label>
                  <input type="number" value={selectedEl.z_index}
                    onChange={e => updateElement(selectedEl.id, { z_index: parseInt(e.target.value) || 0 })}
                    className="input input-bordered input-xs w-full text-[11px]" />
                </div>

                {/* Type-specific */}
                {selectedEl.type === 'text' && (
                  <div className="space-y-2">
                    <label className="text-[10px] text-base-content/40 uppercase">Text HTML</label>
                    <textarea value={(selectedEl.content.html as string) || ''} rows={4}
                      onChange={e => updateElement(selectedEl.id, { content: { ...selectedEl.content, html: e.target.value } })}
                      className="textarea textarea-bordered textarea-xs w-full text-[11px] font-mono" />
                  </div>
                )}

                {selectedEl.type === 'image' && (
                  <div className="space-y-2">
                    <div>
                      <label className="text-[10px] text-base-content/40 uppercase">Image URL</label>
                      <input value={(selectedEl.content.src as string) || ''}
                        onChange={e => updateElement(selectedEl.id, { content: { ...selectedEl.content, src: e.target.value } })}
                        className="input input-bordered input-xs w-full text-[11px]" placeholder="https://..." />
                    </div>
                    <div>
                      <label className="text-[10px] text-base-content/40 uppercase">Fit</label>
                      <select value={(selectedEl.content.objectFit as string) || 'cover'}
                        onChange={e => updateElement(selectedEl.id, { content: { ...selectedEl.content, objectFit: e.target.value } })}
                        className="select select-bordered select-xs w-full text-[11px]">
                        <option value="cover">Cover</option>
                        <option value="contain">Contain</option>
                        <option value="fill">Fill</option>
                      </select>
                    </div>
                  </div>
                )}

                {selectedEl.type === 'video' && (
                  <div className="space-y-2">
                    <div>
                      <label className="text-[10px] text-base-content/40 uppercase">YouTube / Vimeo ID</label>
                      <input value={(selectedEl.content.videoId as string) || ''}
                        onChange={e => updateElement(selectedEl.id, { content: { ...selectedEl.content, videoId: e.target.value } })}
                        className="input input-bordered input-xs w-full text-[11px]" placeholder="dQw4w9WgXcQ" />
                    </div>
                  </div>
                )}

                {selectedEl.type === 'shape' && (
                  <div className="space-y-2">
                    <div>
                      <label className="text-[10px] text-base-content/40 uppercase">Shape</label>
                      <select value={(selectedEl.content.shapeType as string) || 'rectangle'}
                        onChange={e => updateElement(selectedEl.id, { content: { ...selectedEl.content, shapeType: e.target.value } })}
                        className="select select-bordered select-xs w-full text-[11px]">
                        <option value="rectangle">Rectangle</option>
                        <option value="circle">Circle</option>
                      </select>
                    </div>
                    <div>
                      <label className="text-[10px] text-base-content/40 uppercase">Fill color</label>
                      <input type="color" value={(selectedEl.content.fill as string) || '#000000'}
                        onChange={e => updateElement(selectedEl.id, { content: { ...selectedEl.content, fill: e.target.value } })}
                        className="w-full h-8 rounded cursor-pointer" />
                    </div>
                  </div>
                )}

                {/* Block element — renders the block's Editor component */}
                {selectedEl.type === 'block' && (() => {
                  const bType = selectedEl.content.blockType as string;
                  const reg = blockRegistry.get(bType);
                  if (!reg) return <div className="text-xs text-base-content/30">Unknown block type: {bType}</div>;
                  const { Editor } = reg;
                  const blockData = (selectedEl.content.blockData as Record<string, unknown>) || {};

                  return (
                    <div className="space-y-2">
                      <div className="text-[10px] text-base-content/30 uppercase tracking-wider">Block: {reg.definition.label}</div>
                      <Editor
                        block={{
                          id: selectedEl.id,
                          type: bType,
                          data: blockData,
                          children: [],
                          order: 0,
                        }}
                        isSelected={true}
                        onUpdate={(newData) => {
                          updateElement(selectedEl.id, {
                            content: {
                              ...selectedEl.content,
                              blockData: { ...blockData, ...newData },
                            },
                          });
                        }}
                        onSelect={() => {}}
                      />
                    </div>
                  );
                })()}
              </>
            )}

            {rightTab === 'element' && !selectedEl && (
              <div className="text-center py-12 text-base-content/20">
                <MousePointer className="mx-auto mb-2" size={20} />
                <p className="text-[12px]">Select an element on the canvas</p>
              </div>
            )}

            {/* ── Page properties ── */}
            {rightTab === 'page' && activePage && (
              <>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Page title (TOC)</label>
                  <input value={activePage.title} onChange={e => updatePage({ title: e.target.value })}
                    className="input input-bordered input-xs w-full text-[11px]" placeholder="Page title for table of contents" />
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Background color</label>
                  <div className="flex gap-2">
                    <input type="color" value={activePage.background_color} onChange={e => updatePage({ background_color: e.target.value })}
                      className="w-8 h-8 rounded cursor-pointer border" />
                    <input value={activePage.background_color} onChange={e => updatePage({ background_color: e.target.value })}
                      className="input input-bordered input-xs flex-1 text-[11px]" />
                  </div>
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Background image</label>
                  <input value={activePage.background_image || ''} onChange={e => updatePage({ background_image: e.target.value || null })}
                    className="input input-bordered input-xs w-full text-[11px]" placeholder="https://..." />
                </div>
                <div className="border-t border-base-300/20 pt-3 space-y-1">
                  <p className="text-[10px] text-base-content/40 uppercase mb-2">Page actions</p>
                  <div className="flex gap-1">
                    <button onClick={() => movePage(activePageIdx, -1)} disabled={activePageIdx === 0}
                      className="btn btn-ghost btn-xs gap-1 flex-1 text-[11px]"><ChevronUp size={12} /> Move up</button>
                    <button onClick={() => movePage(activePageIdx, 1)} disabled={activePageIdx === pages.length - 1}
                      className="btn btn-ghost btn-xs gap-1 flex-1 text-[11px]"><ChevronDown size={12} /> Move down</button>
                  </div>
                  <button onClick={() => deletePage(activePageIdx)} disabled={pages.length <= 1}
                    className="btn btn-ghost btn-xs w-full text-error text-[11px]"><Trash2 size={12} /> Delete page</button>
                </div>
                <div className="text-[10px] text-base-content/30">Page {activePageIdx + 1} of {pages.length}</div>
              </>
            )}

            {/* ── Magazine properties ── */}
            {rightTab === 'magazine' && (
              <div className="space-y-4">
                {/* ── General ── */}
                <div className="text-[10px] text-base-content/30 uppercase font-medium tracking-wider">General</div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Title</label>
                  <input value={title} onChange={e => { setTitle(e.target.value); dirty(); }}
                    className="input input-bordered input-xs w-full text-[11px]" />
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Slug</label>
                  <input value={slug} onChange={e => { setSlug(e.target.value); dirty(); }}
                    className="input input-bordered input-xs w-full text-[11px]" />
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Status</label>
                  <select value={status} onChange={e => { setStatus(e.target.value); dirty(); }}
                    className="select select-bordered select-xs w-full text-[11px]">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                  </select>
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="text-[10px] text-base-content/40 uppercase">Width (mm)</label>
                    <input type="number" value={pageWidth} onChange={e => { setPageWidth(parseInt(e.target.value) || 210); dirty(); }}
                      className="input input-bordered input-xs w-full text-[11px]" />
                  </div>
                  <div>
                    <label className="text-[10px] text-base-content/40 uppercase">Height (mm)</label>
                    <input type="number" value={pageHeight} onChange={e => { setPageHeight(parseInt(e.target.value) || 297); dirty(); }}
                      className="input input-bordered input-xs w-full text-[11px]" />
                  </div>
                </div>

                {/* ── Viewer display ── */}
                <div className="border-t border-base-300/20 pt-3">
                  <div className="text-[10px] text-base-content/30 uppercase font-medium tracking-wider mb-2">Viewer display</div>
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Page fit</label>
                  <select value={getSetting('page_fit', 'fill') as string} onChange={e => setSetting('page_fit', e.target.value)}
                    className="select select-bordered select-xs w-full text-[11px]">
                    <option value="fill">Fill — edge to edge, use all space</option>
                    <option value="fit">Fit — maintain ratio with padding</option>
                    <option value="cover">Cover — fill and crop</option>
                  </select>
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Spread mode</label>
                  <select value={getSetting('spread_mode', 'auto') as string} onChange={e => setSetting('spread_mode', e.target.value)}
                    className="select select-bordered select-xs w-full text-[11px]">
                    <option value="auto">Auto — spread on desktop, single on mobile</option>
                    <option value="single">Single page always</option>
                    <option value="spread">Two-page spread always</option>
                  </select>
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Max width</label>
                  <input value={getSetting('max_width', '100%') as string} onChange={e => setSetting('max_width', e.target.value)}
                    className="input input-bordered input-xs w-full text-[11px]" placeholder="100%, 1400px, 90vw" />
                  <p className="text-[9px] text-base-content/25 mt-0.5">100% = full width, 1400px = fixed max, 90vw = viewport %</p>
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Max height</label>
                  <input value={getSetting('max_height', '100vh') as string} onChange={e => setSetting('max_height', e.target.value)}
                    className="input input-bordered input-xs w-full text-[11px]" placeholder="100vh, 800px" />
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Viewport padding</label>
                  <input value={getSetting('padding', '0') as string} onChange={e => setSetting('padding', e.target.value)}
                    className="input input-bordered input-xs w-full text-[11px]" placeholder="0, 20px, 2vh 4vw" />
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Gap between pages (px)</label>
                  <input type="number" min={0} max={40} value={getSetting('page_gap', 0) as number} onChange={e => setSetting('page_gap', parseInt(e.target.value) || 0)}
                    className="input input-bordered input-xs w-full text-[11px]" />
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Border radius</label>
                  <input value={getSetting('border_radius', '0') as string} onChange={e => setSetting('border_radius', e.target.value)}
                    className="input input-bordered input-xs w-full text-[11px]" placeholder="0, 8px, 12px" />
                </div>

                {/* ── Background ── */}
                <div className="border-t border-base-300/20 pt-3">
                  <div className="text-[10px] text-base-content/30 uppercase font-medium tracking-wider mb-2">Background</div>
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Background color</label>
                  <div className="flex gap-2">
                    <input type="color" value={getSetting('bg_color', '#0a0a0a') as string}
                      onChange={e => setSetting('bg_color', e.target.value)}
                      className="w-8 h-7 rounded cursor-pointer border border-base-300/30" />
                    <input value={getSetting('bg_color', '#0a0a0a') as string} onChange={e => setSetting('bg_color', e.target.value)}
                      className="input input-bordered input-xs flex-1 text-[11px]" />
                  </div>
                </div>

                {/* ── Transitions ── */}
                <div className="border-t border-base-300/20 pt-3">
                  <div className="text-[10px] text-base-content/30 uppercase font-medium tracking-wider mb-2">Transitions</div>
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Page transition</label>
                  <select value={getSetting('page_transition', 'slide') as string} onChange={e => setSetting('page_transition', e.target.value)}
                    className="select select-bordered select-xs w-full text-[11px]">
                    <option value="slide">Slide</option>
                    <option value="fade">Fade</option>
                    <option value="flip">3D Flip</option>
                    <option value="none">None (instant)</option>
                  </select>
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Transition speed (ms)</label>
                  <input type="number" min={100} max={1500} step={50} value={getSetting('transition_speed', 400) as number}
                    onChange={e => setSetting('transition_speed', parseInt(e.target.value) || 400)}
                    className="input input-bordered input-xs w-full text-[11px]" />
                </div>

                {/* ── UI controls ── */}
                <div className="border-t border-base-300/20 pt-3">
                  <div className="text-[10px] text-base-content/30 uppercase font-medium tracking-wider mb-2">UI controls</div>
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">UI theme</label>
                  <select value={getSetting('ui_theme', 'dark') as string} onChange={e => setSetting('ui_theme', e.target.value)}
                    className="select select-bordered select-xs w-full text-[11px]">
                    <option value="dark">Dark</option>
                    <option value="light">Light</option>
                  </select>
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 uppercase">Mobile breakpoint (px)</label>
                  <input type="number" min={320} max={1200} value={getSetting('mobile_breakpoint', 768) as number}
                    onChange={e => setSetting('mobile_breakpoint', parseInt(e.target.value) || 768)}
                    className="input input-bordered input-xs w-full text-[11px]" />
                </div>
                <div className="space-y-1.5">
                  {[
                    { key: 'show_header', label: 'Show header bar', def: true },
                    { key: 'show_controls', label: 'Show navigation controls', def: true },
                    { key: 'show_thumbnails', label: 'Show thumbnail strip', def: true },
                    { key: 'show_toc', label: 'Show table of contents', def: true },
                    { key: 'show_page_numbers', label: 'Show page numbers', def: true },
                    { key: 'page_shadow', label: 'Page shadow', def: true },
                    { key: 'auto_hide_ui', label: 'Auto-hide UI after 3 seconds', def: true },
                  ].map(opt => (
                    <label key={opt.key} className="flex items-center gap-2 text-[11px] text-base-content/60 cursor-pointer">
                      <input type="checkbox" checked={getSetting(opt.key, opt.def) as boolean}
                        onChange={e => setSetting(opt.key, e.target.checked)}
                        className="checkbox checkbox-xs" />
                      {opt.label}
                    </label>
                  ))}
                </div>

                {/* ── Info ── */}
                <div className="border-t border-base-300/20 pt-3">
                  <div className="text-[10px] text-base-content/30 pt-1">{pages.length} pages · {pages.reduce((n, p) => n + p.elements.length, 0)} elements</div>
                  {status === 'published' && slug && (
                    <a href={`/magazine/${slug}`} target="_blank" rel="noopener" className="btn btn-ghost btn-xs w-full text-[11px] gap-1 text-primary mt-2">
                      <Eye size={12} /> View published
                    </a>
                  )}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
