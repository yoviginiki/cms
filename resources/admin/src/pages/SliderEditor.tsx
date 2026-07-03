import { useEffect, useMemo, useRef, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft, Save, Loader2, Plus, Copy, Trash2, ChevronLeft, ChevronRight,
  Rocket, Type, Image as ImageIcon, Square, MousePointerClick, Video, Music, Group,
  Play,
} from 'lucide-react';
import { sliders } from '@/lib/api';
import { AssetField } from '@/components/ui/AssetPicker';
import { loadMotionRuntime } from '@/lib/motionRuntime';

/** minimal surface of a gsap timeline the preview controls need */
interface PreviewTimeline {
  kill(): void;
  play(): void;
  pause(): void;
  progress(value?: number): number;
  eventCallback(name: string, cb: (() => void) | null): void;
}
import { useEditorStore } from '@/stores/editorStore';
import { blockRegistry } from '@/components/blocks/registry';
import { BlockSettings } from '@/components/editor/BlockSettings';
import { useToast } from '@/components/ui/Toast';
import type { BlockData } from '@/types/blocks';

/**
 * Full-canvas slider editor. The slider's block tree (slider → slides →
 * layers) lives in the SAME editorStore the page builder uses; layers are the
 * EXISTING block primitives with data.layout, edited through the SHARED
 * BlockSettings inspector (Transform panel shows because parent is a slide).
 * No slider-private styling controls.
 */

const LAYER_TYPES: { type: string; label: string; icon: React.ComponentType<{ size?: number }>; defaults: Record<string, unknown> }[] = [
  { type: 'text', label: 'Text', icon: Type, defaults: { content: 'New text layer' } },
  { type: 'image', label: 'Image', icon: ImageIcon, defaults: {} },
  { type: 'button', label: 'Button', icon: MousePointerClick, defaults: { text: 'Button', url: '#' } },
  { type: 'shape', label: 'Shape', icon: Square, defaults: { color: '#E63B2E' } },
  { type: 'video', label: 'Video', icon: Video, defaults: { muted: true } },
  { type: 'audio', label: 'Audio', icon: Music, defaults: {} },
  { type: 'group', label: 'Group', icon: Group, defaults: {} },
];

const DEVICE_WIDTHS = { desktop: 1280, tablet: 834, mobile: 390 } as const;
/** nominal viewport heights: vh-based slider heights resolve against these */
const DEVICE_VIEWPORT_H = { desktop: 720, tablet: 1112, mobile: 844 } as const;

export default function SliderEditor() {
  const { siteId = '', sliderId = '' } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const blocks = useEditorStore(s => s.blocks);
  const setBlocks = useEditorStore(s => s.setBlocks);
  const updateBlock = useEditorStore(s => s.updateBlock);
  const removeBlock = useEditorStore(s => s.removeBlock);
  const duplicateBlock = useEditorStore(s => s.duplicateBlock);
  const selectBlock = useEditorStore(s => s.selectBlock);
  const selectedBlockId = useEditorStore(s => s.selectedBlockId);
  const isDirty = useEditorStore(s => s.isDirty);
  const setDirty = useEditorStore(s => s.setDirty);
  const canvasDevice = useEditorStore(s => s.canvasDevice);
  const setCanvasDevice = useEditorStore(s => s.setCanvasDevice);

  const [activeSlideId, setActiveSlideId] = useState<string | null>(null);
  const [name, setName] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['slider', siteId, sliderId],
    queryFn: () => sliders.get(siteId, sliderId).then(r => r.data.data),
  });

  useEffect(() => {
    if (data) {
      setBlocks(data.blocks || []);
      setDirty(false);
      setName(data.slider?.name || '');
      const root = (data.blocks || [])[0];
      const firstSlide = root?.children?.find((c: BlockData) => c.type === 'slide');
      setActiveSlideId(firstSlide?.id ?? null);
    }
    return () => { setBlocks([]); selectBlock(null); };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data]);

  const root = blocks[0];
  const slides = useMemo(() => (root?.children ?? []).filter((c: BlockData) => c.type === 'slide'), [root]);
  const activeSlide = slides.find(s => s.id === activeSlideId) ?? slides[0];
  const rootData = (root?.data ?? {}) as Record<string, any>;
  const heights = rootData.height ?? {};
  const canvasHeightRatio = (() => {
    const h = String(heights[canvasDevice] ?? '70vh');
    const n = parseFloat(h) || 70;
    return h.endsWith('vh') ? n / 100 : 0.7;
  })();

  const saveMutation = useMutation({
    mutationFn: async () => {
      await sliders.update(siteId, sliderId, { name });
      return sliders.syncBlocks(siteId, sliderId, blocks);
    },
    onSuccess: () => {
      setDirty(false);
      queryClient.invalidateQueries({ queryKey: ['slider', siteId, sliderId] });
      toast({ type: 'success', message: 'Slider saved (draft — publish to update pages)' });
    },
    onError: (e: any) => toast({ type: 'error', message: e?.response?.data?.message || 'Save failed' }),
  });

  const publishMutation = useMutation({
    mutationFn: async () => {
      if (isDirty) { await sliders.update(siteId, sliderId, { name }); await sliders.syncBlocks(siteId, sliderId, blocks); setDirty(false); }
      return sliders.publish(siteId, sliderId);
    },
    onSuccess: (r) => {
      queryClient.invalidateQueries({ queryKey: ['slider', siteId, sliderId] });
      queryClient.invalidateQueries({ queryKey: ['stale-count', siteId] });
      const stale = r.data.meta?.stale;
      const n = (stale?.pages ?? 0) + (stale?.posts ?? 0);
      toast({ type: 'info', message: n > 0
        ? `Published — ${n} page(s) affected. Review & republish from “Stale pages”.`
        : 'Published. Embed it into a page with the Slider block.' });
    },
    onError: (e: any) => toast({ type: 'error', message: e?.response?.data?.message || 'Publish failed' }),
  });

  /* ── slide rail operations (store-level; dnd reorder is a TODO) ── */
  const addSlide = () => {
    if (!root) return;
    const newSlide: BlockData = {
      id: crypto.randomUUID(), type: 'slide', level: 'row',
      data: { background: { type: 'color', color: '#1A1817' } },
      order: slides.length, children: [],
    } as unknown as BlockData;
    updateBlockChildren(root.id, [...(root.children ?? []), newSlide]);
    setActiveSlideId(newSlide.id);
  };
  const updateBlockChildren = (blockId: string, children: BlockData[]) => {
    // children live on the tree; easiest correct path: rebuild via setBlocks
    const clone = structuredClone(blocks);
    const patch = (nodes: BlockData[]): boolean => {
      for (const n of nodes) {
        if (n.id === blockId) { n.children = children.map((c, i) => ({ ...c, order: i })); return true; }
        if (patch(n.children ?? [])) return true;
      }
      return false;
    };
    patch(clone);
    setBlocks(clone);
    setDirty(true);
  };
  const moveSlide = (id: string, dir: -1 | 1) => {
    if (!root) return;
    const list = [...(root.children ?? [])];
    const i = list.findIndex(s => s.id === id);
    const j = i + dir;
    if (i < 0 || j < 0 || j >= list.length) return;
    [list[i], list[j]] = [list[j], list[i]];
    updateBlockChildren(root.id, list);
  };
  const duplicateSlide = (id: string) => { duplicateBlock(id); };
  const removeSlide = (id: string) => {
    if (slides.length <= 1) { toast({ type: 'error', message: 'A slider needs at least one slide' }); return; }
    removeBlock(id);
    if (activeSlideId === id) setActiveSlideId(slides.find(s => s.id !== id)?.id ?? null);
  };

  const addLayer = (type: string, defaults: Record<string, unknown>) => {
    if (!activeSlide) return;
    const layer: BlockData = {
      id: crypto.randomUUID(), type, level: 'module',
      data: {
        ...defaults,
        layout: { x: '40%', y: '40%', zIndex: 2, ...(type === 'shape' ? { widthPct: 30, heightPct: 8 } : {}) },
        animation: { in: { preset: 'fadeUp', delay: 0.2, duration: 0.6 } },
      },
      order: (activeSlide.children ?? []).length, children: [],
    } as unknown as BlockData;
    updateBlockChildren(activeSlide.id, [...(activeSlide.children ?? []), layer]);
    selectBlock(layer.id);
  };

  /* ── "Play this slide": the SAME buildSlideTimeline the published runtime
        uses (loaded from resources/js/motion-runtime.js — one builder). ── */
  const tlRef = useRef<PreviewTimeline | null>(null);
  const [playProgress, setPlayProgress] = useState(0);
  const [playingPhase, setPlayingPhase] = useState<'in' | 'out' | null>(null);
  const [previewNonce, setPreviewNonce] = useState(0);

  const stopPreview = () => {
    tlRef.current?.kill();
    tlRef.current = null;
    setPlayingPhase(null);
    setPlayProgress(0);
    // split() mutates preview DOM; remounting the layer wrappers restores
    // React-owned markup at authored final state (the runtime's reset rule)
    setPreviewNonce(n => n + 1);
  };

  const playPhase = async (phase: 'in' | 'out') => {
    if (!activeSlide || !canvasRef.current) return;
    stopPreview();
    const rt = await loadMotionRuntime();
    // config shape = prototype/runtime contract: layers by id + animation
    const conf = {
      id: activeSlide.id,
      layers: (activeSlide.children ?? []).map((l: BlockData) => ({
        id: l.id, type: l.type,
        animation: (l.data as any)?.animation ?? null,
      })),
    };
    const tl = rt.buildSlideTimeline(canvasRef.current, conf, phase);
    tlRef.current = tl;
    setPlayingPhase(phase);
    tl.eventCallback('onUpdate', () => setPlayProgress(tl.progress()));
    tl.eventCallback('onComplete', () => setPlayingPhase(null));
    tl.play();
  };

  const scrubTo = (p: number) => {
    const tl = tlRef.current;
    if (!tl) return;
    tl.pause();
    tl.progress(p);
    setPlayProgress(p);
  };

  /* ── canvas drag: pointer-move updates data.layout x/y in % ── */
  const canvasRef = useRef<HTMLDivElement>(null);
  const dragState = useRef<{ id: string; startX: number; startY: number; origX: number; origY: number } | null>(null);

  const pctOf = (v: string | undefined, fallback: number) => {
    const n = parseFloat(String(v ?? ''));
    return Number.isFinite(n) ? n : fallback;
  };

  /** effective layout for the active canvas device (base + bp override) */
  const effectiveLayout = (layer: BlockData): Record<string, any> => {
    const base = ((layer.data as any)?.layout ?? {}) as Record<string, any>;
    if (canvasDevice === 'desktop') return base;
    const override = ((layer.data as any)?.responsiveLayout?.[canvasDevice] ?? {}) as Record<string, any>;
    return { ...base, ...override };
  };

  const onLayerPointerDown = (e: React.PointerEvent, layer: BlockData) => {
    e.stopPropagation();
    selectBlock(layer.id);
    const layout = effectiveLayout(layer);
    dragState.current = {
      id: layer.id, startX: e.clientX, startY: e.clientY,
      origX: pctOf(layout.x as string, 40), origY: pctOf(layout.y as string, 40),
    };
    (e.target as HTMLElement).setPointerCapture(e.pointerId);
  };
  const onPointerMove = (e: React.PointerEvent) => {
    const d = dragState.current;
    const rect = canvasRef.current?.getBoundingClientRect();
    if (!d || !rect) return;
    const layer = findBlock(blocks, d.id);
    if (!layer) return;
    const nx = Math.max(0, Math.min(96, d.origX + ((e.clientX - d.startX) / rect.width) * 100));
    const ny = Math.max(0, Math.min(96, d.origY + ((e.clientY - d.startY) / rect.height) * 100));
    const pos = { x: `${nx.toFixed(1)}%`, y: `${ny.toFixed(1)}%` };
    if (canvasDevice === 'desktop') {
      updateBlock(d.id, { layout: { ...(((layer.data as any)?.layout ?? {}) as object), ...pos } });
    } else {
      // dragging on tablet/mobile writes that device's override
      const resp = ((layer.data as any)?.responsiveLayout ?? {}) as Record<string, object>;
      updateBlock(d.id, { responsiveLayout: { ...resp, [canvasDevice]: { ...(resp[canvasDevice] ?? {}), ...pos } } });
    }
  };
  const onPointerUp = () => { dragState.current = null; };

  if (isLoading || !root) {
    return <div className="flex items-center justify-center h-screen"><Loader2 className="h-8 w-8 animate-spin text-base-content/30" /></div>;
  }

  const slideData = (activeSlide?.data ?? {}) as Record<string, any>;
  const bg = slideData.background ?? {};
  /** asset-backed backgrounds resolve through the serve endpoint (same-origin, authed) */
  const bgUrl = bg.assetId ? `/api/v1/sites/${siteId}/assets/${bg.assetId}/serve` : (bg.src || '');

  return (
    <div className="flex flex-col h-screen bg-base-200" data-theme="cms-admin">
      {/* ── top bar ── */}
      <div className="flex items-center justify-between px-3 py-2 bg-base-100 border-b border-base-300">
        <div className="flex items-center gap-2">
          <button onClick={() => navigate(`/sites/${siteId}/sliders`)} className="btn btn-ghost btn-xs"><ArrowLeft size={14} /></button>
          <input value={name} onChange={e => { setName(e.target.value); setDirty(true); }}
            className="input input-ghost input-sm font-medium text-[14px] w-64" />
        </div>
        <div className="flex items-center gap-1.5">
          {(['desktop', 'tablet', 'mobile'] as const).map(d => (
            <button key={d} onClick={() => setCanvasDevice(d)}
              className={`btn btn-xs ${canvasDevice === d ? 'btn-primary' : 'btn-ghost'}`}>{d}</button>
          ))}
          <div className="w-px h-5 bg-base-300 mx-1" />
          <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !isDirty}
            className="btn btn-sm btn-outline gap-1.5">
            {saveMutation.isPending ? <Loader2 size={13} className="animate-spin" /> : <Save size={13} />} Save
          </button>
          <button onClick={() => publishMutation.mutate()} disabled={publishMutation.isPending}
            className="btn btn-sm btn-primary gap-1.5">
            {publishMutation.isPending ? <Loader2 size={13} className="animate-spin" /> : <Rocket size={13} />} Publish
          </button>
        </div>
      </div>

      <div className="flex flex-1 min-h-0">
        {/* ── left: slider settings + layer palette ── */}
        <div className="w-56 bg-base-100 border-r border-base-300 overflow-y-auto p-3 space-y-4">
          <div>
            <p className="text-[10px] font-medium text-base-content/40 uppercase tracking-wider mb-1.5">Add layer</p>
            <div className="grid grid-cols-2 gap-1">
              {LAYER_TYPES.map(({ type, label, icon: Icon, defaults }) => (
                <button key={type} onClick={() => addLayer(type, defaults)}
                  className="flex items-center gap-1.5 px-2 py-1.5 text-[11px] border border-base-300 hover:border-primary hover:text-primary">
                  <Icon size={12} /> {label}
                </button>
              ))}
            </div>
          </div>

          <div>
            <p className="text-[10px] font-medium text-base-content/40 uppercase tracking-wider mb-1.5">Slider settings</p>
            {(['desktop', 'tablet', 'mobile'] as const).map(d => (
              <div key={d} className="flex items-center gap-1.5 mb-1">
                <span className="text-[10px] text-base-content/40 w-14 capitalize">{d}</span>
                <input value={heights[d] ?? ''} placeholder={{ desktop: '70vh', tablet: '60vh', mobile: '80vh' }[d]}
                  onChange={e => updateBlock(root.id, { height: { ...heights, [d]: e.target.value || undefined } })}
                  className="input input-bordered input-xs flex-1 text-[11px]" />
              </div>
            ))}
            <div className="space-y-1 mt-2">
              {([['autoplay', 'Autoplay'], ['loop', 'Loop'], ['navigation', 'Arrows'], ['pagination', 'Bullets'], ['keyboard', 'Keyboard'], ['pauseOnHover', 'Pause on hover']] as const).map(([key, label]) => (
                <label key={key} className="flex items-center justify-between text-[11px] text-base-content/60 cursor-pointer">
                  {label}
                  <input type="checkbox" className="toggle toggle-xs"
                    checked={(rootData.swiper?.[key] ?? (key === 'autoplay' ? false : true)) === true}
                    onChange={e => updateBlock(root.id, { swiper: { ...(rootData.swiper ?? {}), [key]: e.target.checked } })} />
                </label>
              ))}
              <div className="flex items-center gap-1.5">
                <span className="text-[10px] text-base-content/40 flex-1">Autoplay delay ms</span>
                <input type="number" min={1000} max={30000} step={500}
                  value={rootData.swiper?.autoplayDelay ?? 6000}
                  onChange={e => updateBlock(root.id, { swiper: { ...(rootData.swiper ?? {}), autoplayDelay: Number(e.target.value) } })}
                  className="input input-bordered input-xs w-20 text-[11px]" />
              </div>
              <div className="flex items-center gap-1.5">
                <span className="text-[10px] text-base-content/40 flex-1">Effect</span>
                <select value={rootData.swiper?.effect ?? 'slide'}
                  onChange={e => updateBlock(root.id, { swiper: { ...(rootData.swiper ?? {}), effect: e.target.value } })}
                  className="select select-bordered select-xs text-[11px]">
                  <option value="slide">Slide</option><option value="fade">Fade</option>
                </select>
              </div>
            </div>
          </div>

          {activeSlide && (
            <div>
              <p className="text-[10px] font-medium text-base-content/40 uppercase tracking-wider mb-1.5">Slide background</p>
              <select value={bg.type ?? 'color'}
                onChange={e => updateBlock(activeSlide.id, { background: { ...bg, type: e.target.value } })}
                className="select select-bordered select-xs w-full text-[11px] mb-1.5">
                <option value="color">Color</option><option value="image">Image</option><option value="video">Video</option>
              </select>
              {bg.type === 'color' || !bg.type ? (
                <input value={bg.color ?? ''} placeholder="#1A1817"
                  onChange={e => updateBlock(activeSlide.id, { background: { ...bg, color: e.target.value } })}
                  className="input input-bordered input-xs w-full text-[11px]" />
              ) : (
                <>
                  <AssetField label="" value={bgUrl}
                    accept={bg.type === 'video' ? 'video' : 'image'}
                    onChange={v => {
                      // AssetField returns a serve URL — persist the asset id (tracked
                      // as a uses_asset edge; publish staticizes it)
                      const m = String(v || '').match(/assets\/([0-9a-f-]{36})\/serve/);
                      updateBlock(activeSlide.id, { background: { ...bg, assetId: m ? m[1] : undefined, src: m ? undefined : (v || undefined) } });
                    }} />
                  <input value={bg.assetId ? '' : (bg.src ?? '')} placeholder="…or external URL"
                    onChange={e => updateBlock(activeSlide.id, { background: { ...bg, src: e.target.value || undefined, assetId: undefined } })}
                    className="input input-bordered input-xs w-full text-[11px] mt-1" />
                </>
              )}
              <input value={bg.overlay ?? ''} placeholder="overlay: rgba(0,0,0,.4) / gradient"
                onChange={e => updateBlock(activeSlide.id, { background: { ...bg, overlay: e.target.value || undefined } })}
                className="input input-bordered input-xs w-full text-[11px] mt-1.5" />
              <div className="flex items-center justify-between mt-1.5 text-[11px] text-base-content/60">
                Ken Burns
                <input type="checkbox" className="toggle toggle-xs" checked={!!bg.kenBurns}
                  onChange={e => updateBlock(activeSlide.id, { background: { ...bg, kenBurns: e.target.checked } })} />
              </div>
              <div className="flex items-center gap-1.5 mt-1.5">
                <span className="text-[10px] text-base-content/40 flex-1">Duration ms</span>
                <input type="number" min={1000} max={30000} step={500} value={slideData.duration ?? ''}
                  placeholder="6000"
                  onChange={e => updateBlock(activeSlide.id, { duration: e.target.value ? Number(e.target.value) : undefined })}
                  className="input input-bordered input-xs w-20 text-[11px]" />
              </div>
            </div>
          )}
        </div>

        {/* ── center: canvas + slide rail ── */}
        <div className="flex-1 flex flex-col min-w-0">
          <div className="flex-1 overflow-auto flex items-center justify-center p-6">
            <div ref={canvasRef}
              onPointerMove={onPointerMove} onPointerUp={onPointerUp}
              onPointerDown={() => selectBlock(null)}
              className="relative bg-neutral-900 overflow-hidden shadow-lg shrink-0"
              style={{ width: DEVICE_WIDTHS[canvasDevice], height: Math.round(DEVICE_VIEWPORT_H[canvasDevice] * canvasHeightRatio) }}>
              {/* slide background (assetId resolves via serve endpoint) */}
              {bg.type === 'image' && bgUrl && <img src={bgUrl} alt="" className="absolute inset-0 w-full h-full object-cover" />}
              {bg.type === 'video' && bgUrl && <video src={bgUrl} muted loop autoPlay playsInline className="absolute inset-0 w-full h-full object-cover" />}
              {(!bg.type || bg.type === 'color') && <div className="absolute inset-0" style={{ background: bg.color || '#1A1817' }} />}
              {bg.overlay && <div className="absolute inset-0" style={{ background: bg.overlay }} />}

              {/* layers at FINAL state (canvas edits layout; motion previews come from the shared runtime later) */}
              {(activeSlide?.children ?? []).map((layer: BlockData) => {
                const layout = effectiveLayout(layer);
                if (layout.hidden) return null; // hidden on this device
                const reg = blockRegistry.get(layer.type);
                const selected = selectedBlockId === layer.id;
                return (
                  <div key={`${layer.id}-${previewNonce}`}
                    data-layer-id={layer.id}
                    onPointerDown={e => { stopPreview(); onLayerPointerDown(e, layer); }}
                    className={`absolute cursor-move ${selected ? 'ring-2 ring-primary ring-offset-1' : 'hover:ring-1 hover:ring-primary/40'}`}
                    style={{
                      left: layout.x ?? '40%', top: layout.y ?? '40%',
                      width: layout.widthPct != null ? `${layout.widthPct}%` : undefined,
                      height: layout.heightPct != null ? `${layout.heightPct}%` : undefined,
                      zIndex: layout.zIndex ?? 2,
                      transform: layout.rotation ? `rotate(${layout.rotation}deg)` : undefined,
                    }}>
                    {reg ? <reg.Preview block={layer} isSelected={selected} onSelect={() => selectBlock(layer.id)} onUpdate={d => updateBlock(layer.id, d)} />
                      : <div className="text-xs text-white/60 p-2">{layer.type}</div>}
                  </div>
                );
              })}
            </div>
          </div>

          {/* playback controls + timeline strip */}
          <div className="bg-base-100 border-t border-base-300 px-3 py-1.5">
            <div className="flex items-center gap-2">
              <button onClick={() => playPhase('in')} className="btn btn-xs btn-primary gap-1" title="Play this slide's IN scene">
                <Play size={11} /> IN
              </button>
              <button onClick={() => playPhase('out')} className="btn btn-xs btn-outline gap-1" title="Play the OUT scene">
                <Play size={11} /> OUT
              </button>
              <button onClick={stopPreview} className="btn btn-xs btn-ghost gap-1" title="Stop & reset to final state">
                <Square size={10} /> Reset
              </button>
              <input type="range" min={0} max={1} step={0.001} value={playProgress}
                onChange={e => scrubTo(Number(e.target.value))}
                disabled={!tlRef.current}
                className="range range-xs flex-1" title="Scrub the current timeline" />
              <span className="text-[10px] text-base-content/40 w-16 text-right">
                {playingPhase ? `${playingPhase} ▸` : ''} {(playProgress * 100).toFixed(0)}%
              </span>
            </div>
            {/* horizontal timeline strip: delay→duration bars per layer (IN blue / OUT amber) */}
            <div className="mt-1 space-y-0.5">
              {(activeSlide?.children ?? []).map((layer: BlockData) => {
                const anim = ((layer.data as any)?.animation ?? {}) as Record<string, any>;
                const bar = (scene: any, color: string) => {
                  if (!scene) return null;
                  const delay = Number(scene.delay ?? scene.tracks?.[0]?.delay ?? 0);
                  const dur = Number(scene.duration ?? scene.tracks?.[0]?.duration ?? 0.6);
                  return <div className={`absolute h-1.5 ${color}`}
                    style={{ left: `${Math.min(95, delay * 18)}%`, width: `${Math.max(1.5, dur * 18)}%` }} />;
                };
                return (
                  <div key={layer.id}
                    className={`flex items-center gap-2 cursor-pointer ${selectedBlockId === layer.id ? 'opacity-100' : 'opacity-60 hover:opacity-90'}`}
                    onClick={() => selectBlock(layer.id)}>
                    <span className="text-[9px] text-base-content/40 w-16 truncate shrink-0">{layer.type}</span>
                    <div className="relative flex-1 h-1.5 bg-base-200">
                      {bar(anim.in, 'bg-primary')}
                      {bar(anim.out, 'bg-warning')}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* slide rail */}
          <div className="bg-base-100 border-t border-base-300 px-3 py-2 flex items-center gap-2 overflow-x-auto">
            {slides.map((s, i) => (
              <div key={s.id}
                className={`group relative shrink-0 w-24 h-14 border-2 cursor-pointer flex items-center justify-center text-[10px] ${s.id === activeSlide?.id ? 'border-primary' : 'border-base-300 hover:border-base-content/40'}`}
                style={{ background: ((s.data as any)?.background?.color) || '#1A1817', color: '#fff' }}
                onClick={() => { setActiveSlideId(s.id); selectBlock(null); }}>
                <span className="opacity-70">Slide {i + 1}</span>
                <span className="opacity-40 absolute bottom-0.5 right-1">{(s.children ?? []).length}</span>
                <div className="absolute -top-2 right-0 hidden group-hover:flex gap-0.5 bg-base-100 border border-base-300 p-0.5">
                  <button title="Move left" onClick={e => { e.stopPropagation(); moveSlide(s.id, -1); }} className="p-0.5 hover:text-primary"><ChevronLeft size={10} /></button>
                  <button title="Move right" onClick={e => { e.stopPropagation(); moveSlide(s.id, 1); }} className="p-0.5 hover:text-primary"><ChevronRight size={10} /></button>
                  <button title="Duplicate" onClick={e => { e.stopPropagation(); duplicateSlide(s.id); }} className="p-0.5 hover:text-green-600"><Copy size={10} /></button>
                  <button title="Delete" onClick={e => { e.stopPropagation(); removeSlide(s.id); }} className="p-0.5 hover:text-red-600"><Trash2 size={10} /></button>
                </div>
              </div>
            ))}
            <button onClick={addSlide} className="shrink-0 w-24 h-14 border-2 border-dashed border-base-300 hover:border-primary hover:text-primary flex items-center justify-center">
              <Plus size={16} />
            </button>
          </div>
        </div>

        {/* ── right: SHARED inspector (BlockSettings incl. Transform panel) ── */}
        <div className="w-80 bg-base-100 border-l border-base-300 overflow-y-auto">
          <BlockSettings />
        </div>
      </div>
    </div>
  );
}

function findBlock(blocks: BlockData[], id: string): BlockData | null {
  for (const b of blocks) {
    if (b.id === id) return b;
    const f = findBlock(b.children ?? [], id);
    if (f) return f;
  }
  return null;
}
