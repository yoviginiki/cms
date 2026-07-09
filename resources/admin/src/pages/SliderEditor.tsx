import { useEffect, useMemo, useRef, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft, Save, Loader2, Plus, Copy, Trash2, ChevronLeft, ChevronRight,
  Rocket, Type, Image as ImageIcon, Square, MousePointerClick, Video, Music, Group,
  Play, ChevronUp, ChevronDown,
} from 'lucide-react';
import { sliders } from '@/lib/api';
import { AssetField } from '@/components/ui/AssetPicker';
import { loadMotionRuntime } from '@/lib/motionRuntime';
import {
  DndContext, PointerSensor, useSensor, useSensors, closestCenter, type DragEndEvent,
} from '@dnd-kit/core';
import { SortableContext, horizontalListSortingStrategy, useSortable, arrayMove } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

/** minimal surface of a gsap timeline the preview controls need */
interface PreviewTimeline {
  kill(): void;
  play(): void;
  pause(): void;
  progress(value?: number): number;
  eventCallback(name: string, cb: (() => void) | null): void;
}
import { useEditorStore } from '@/stores/editorStore';
// side-effect import: populates blockRegistry (each block folder registers on
// import) — without it, a direct visit to this route has an EMPTY registry
import '@/components/blocks';
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

const LAYER_TYPES: { type: string; label: string; icon: React.ComponentType<{ size?: number }>; defaults: Record<string, unknown>; size?: { widthPct?: number; heightPct?: number } }[] = [
  { type: 'text', label: 'Text', icon: Type, defaults: { content: 'New text layer', textColor: '#FBFAF7', fontSize: '28px' } },
  { type: 'image', label: 'Image', icon: ImageIcon, defaults: {}, size: { widthPct: 30 } },
  { type: 'button', label: 'Button', icon: MousePointerClick, defaults: { text: 'Button', url: '#' } },
  { type: 'shape', label: 'Shape', icon: Square, defaults: { color: '#E63B2E' }, size: { widthPct: 30, heightPct: 8 } },
  { type: 'video', label: 'Video', icon: Video, defaults: { muted: true, playsinline: true, controls: false, loop: true }, size: { widthPct: 40, heightPct: 34 } },
  { type: 'audio', label: 'Audio', icon: Music, defaults: {}, size: { widthPct: 32 } },
  { type: 'group', label: 'Group', icon: Group, defaults: {}, size: { widthPct: 40, heightPct: 30 } },
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
  const [zoom, setZoom] = useState(0.7);
  const scrollWrapRef = useRef<HTMLDivElement>(null);

  const zoomBy = (delta: number) => setZoom(z => Math.min(1.5, Math.max(0.25, Math.round((z + delta) * 20) / 20)));
  const zoomFit = () => {
    const wrap = scrollWrapRef.current;
    if (!wrap) return;
    setZoom(Math.min(1.5, Math.max(0.25, (wrap.clientWidth - 48) / DEVICE_WIDTHS[canvasDevice])));
  };

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

  // auto-fit whenever the canvas dimensions change (first mount + device
  // switch) — keyed on the root block: the scroll wrap only exists once the
  // tree has loaded past the spinner early-return
  const rootId = blocks[0]?.id;
  useEffect(() => { zoomFit(); }, [canvasDevice, rootId]); // eslint-disable-line react-hooks/exhaustive-deps

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

  const addLayer = (type: string, defaults: Record<string, unknown>, size?: { widthPct?: number; heightPct?: number }) => {
    if (!activeSlide) return;
    const layer: BlockData = {
      id: crypto.randomUUID(), type, level: 'module',
      data: {
        ...defaults,
        layout: { x: '30%', y: '35%', zIndex: 2, ...(size ?? {}) },
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

  /* ── interactive timeline ──
     bars are draggable: body = start time (delay), right edge = duration.
     Values live in data.animation.{in,out} (or its tracks[0] in tracks mode). */
  const [tlReadout, setTlReadout] = useState<string | null>(null);
  const tlDrag = useRef<{
    layerId: string; phase: 'in' | 'out'; mode: 'move' | 'dur';
    startX: number; origDelay: number; origDur: number; trackW: number;
  } | null>(null);

  const sceneTiming = (scene: any): { delay: number; dur: number } => ({
    delay: Number(scene?.delay ?? scene?.tracks?.[0]?.delay ?? 0),
    dur: Number(scene?.duration ?? scene?.tracks?.[0]?.duration ?? 0.6),
  });

  const writeTiming = (layerId: string, phase: 'in' | 'out', delay: number, dur: number) => {
    const layer = findBlock(blocks, layerId);
    if (!layer) return;
    const anim = ((layer.data as any)?.animation ?? {}) as Record<string, any>;
    const scene = { ...(anim[phase] ?? {}) };
    if (Array.isArray(scene.tracks) && scene.tracks.length) {
      scene.tracks = scene.tracks.map((t: any, i: number) => (i === 0 ? { ...t, delay, duration: dur } : t));
    } else {
      scene.delay = delay;
      scene.duration = dur;
    }
    updateBlock(layerId, { animation: { ...anim, [phase]: scene } });
  };

  const onTlBarPointerDown = (
    e: React.PointerEvent, layerId: string, phase: 'in' | 'out', mode: 'move' | 'dur', trackEl: HTMLElement,
  ) => {
    e.stopPropagation();
    e.preventDefault();
    const layer = findBlock(blocks, layerId);
    const anim = ((layer?.data as any)?.animation ?? {}) as Record<string, any>;
    const { delay, dur } = sceneTiming(anim[phase]);
    tlDrag.current = {
      layerId, phase, mode, startX: e.clientX,
      origDelay: delay, origDur: dur, trackW: trackEl.getBoundingClientRect().width,
    };
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
  };
  const onTlPointerMove = (e: React.PointerEvent, totalSec: number) => {
    const d = tlDrag.current;
    if (!d) return;
    const dSec = ((e.clientX - d.startX) / d.trackW) * totalSec;
    const snap = (v: number) => Math.round(v * 20) / 20; // 0.05s grid
    if (d.mode === 'move') {
      const delay = snap(Math.max(0, Math.min(10, d.origDelay + dSec)));
      writeTiming(d.layerId, d.phase, delay, d.origDur);
      setTlReadout(`${d.phase.toUpperCase()} starts ${delay.toFixed(2)}s · ${d.origDur.toFixed(2)}s long`);
    } else {
      const dur = snap(Math.max(0.1, Math.min(10, d.origDur + dSec)));
      writeTiming(d.layerId, d.phase, d.origDelay, dur);
      setTlReadout(`${d.phase.toUpperCase()} starts ${d.origDelay.toFixed(2)}s · ${dur.toFixed(2)}s long`);
    }
  };
  const onTlPointerUp = () => { tlDrag.current = null; setTlReadout(null); };

  /* ── z-order (stacking): normalize every layer to sequential zIndex, then
     swap the target with its neighbor — predictable "bring forward/backward" */
  const nudgeZ = (layerId: string, dir: 1 | -1) => {
    if (!activeSlide) return;
    const layers = [...(activeSlide.children ?? [])];
    const zOf = (l: BlockData) => Number(((l.data as any)?.layout?.zIndex ?? 2));
    layers.sort((a, b) => zOf(a) - zOf(b) || (a.order ?? 0) - (b.order ?? 0));
    const idx = layers.findIndex(l => l.id === layerId);
    const swapWith = idx + dir;
    if (idx < 0 || swapWith < 0 || swapWith >= layers.length) return;
    [layers[idx], layers[swapWith]] = [layers[swapWith], layers[idx]];
    layers.forEach((l, i) => {
      const layout = ((l.data as any)?.layout ?? {}) as Record<string, unknown>;
      if (Number(layout.zIndex ?? 2) !== i + 1) {
        updateBlock(l.id, { layout: { ...layout, zIndex: i + 1 } });
      }
    });
  };
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
  const dragState = useRef<{
    id: string; startX: number; startY: number; origX: number; origY: number;
    moved: boolean; pointerId: number; target: HTMLElement;
  } | null>(null);

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

  /** write layout fields to the active device (base layout vs bp override) */
  const writeLayout = (layerId: string, patch: Record<string, unknown>) => {
    const layer = findBlock(blocks, layerId);
    if (!layer) return;
    if (canvasDevice === 'desktop') {
      updateBlock(layerId, { layout: { ...(((layer.data as any)?.layout ?? {}) as object), ...patch } });
    } else {
      const resp = ((layer.data as any)?.responsiveLayout ?? {}) as Record<string, object>;
      updateBlock(layerId, { responsiveLayout: { ...resp, [canvasDevice]: { ...(resp[canvasDevice] ?? {}), ...patch } } });
    }
  };

  /** double-click enters inline-edit mode: drag is disabled so contenteditable,
      media-replace buttons etc. inside the Preview receive events */
  const [editingLayerId, setEditingLayerId] = useState<string | null>(null);
  type ResizeMode = 'e' | 'w' | 's' | 'n' | 'se' | 'sw' | 'ne' | 'nw';
  const resizeState = useRef<{
    id: string; mode: ResizeMode; startX: number; startY: number;
    origX: number; origY: number; origW: number; origH: number; aspect: number;
  } | null>(null);

  /* ── snap guides: while dragging, magnetize to the canvas center and to
     sibling layers' edges (Alt bypasses); guide lines render over the canvas ── */
  const [snapGuides, setSnapGuides] = useState<{ v?: number; h?: number } | null>(null);

  /* ── rotate handle: drag rotates around the layer's center (desktop-only —
     rotation lives on base layout, not in responsiveLayout) ── */
  const rotateState = useRef<{
    id: string; cx: number; cy: number; startAngle: number; origRot: number;
  } | null>(null);
  const [rotReadout, setRotReadout] = useState<{ id: string; deg: number } | null>(null);

  const onRotatePointerDown = (e: React.PointerEvent, layer: BlockData) => {
    e.stopPropagation();
    e.preventDefault();
    const wrapper = (e.currentTarget as HTMLElement).closest('[data-layer-id]') as HTMLElement | null;
    if (!wrapper) return;
    // rotation is about the center, which the bounding box preserves even
    // when the wrapper is already rotated
    const box = wrapper.getBoundingClientRect();
    const cx = box.left + box.width / 2;
    const cy = box.top + box.height / 2;
    rotateState.current = {
      id: layer.id, cx, cy,
      startAngle: (Math.atan2(e.clientY - cy, e.clientX - cx) * 180) / Math.PI,
      origRot: Number(effectiveLayout(layer).rotation ?? 0),
    };
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
  };

  const onLayerPointerDown = (e: React.PointerEvent, layer: BlockData) => {
    if (editingLayerId === layer.id) {
      // editing: keep events inside the layer (bubbling to the canvas would
      // exit edit mode mid-click), but don't drag
      e.stopPropagation();
      return;
    }
    e.stopPropagation();
    selectBlock(layer.id);
    // interrupt playback if running (full reset incl. remount is fine there);
    // a plain click must NOT remount, or inner interactions die
    if (tlRef.current) stopPreview();
    const layout = effectiveLayout(layer);
    // freeze the current width before moving: an auto-width absolute layer
    // shrinks-to-fit against the canvas right edge, so dragging LEFT would
    // grow it (the "image resizes while moving" bug)
    if (layout.widthPct == null) {
      const rect = canvasRef.current?.getBoundingClientRect();
      const box = (e.currentTarget as HTMLElement).getBoundingClientRect();
      if (rect && box.width > 0) {
        writeLayout(layer.id, { widthPct: +((box.width / rect.width) * 100).toFixed(1) });
      }
    }
    dragState.current = {
      id: layer.id, startX: e.clientX, startY: e.clientY,
      origX: pctOf(layout.x as string, 40), origY: pctOf(layout.y as string, 40),
      moved: false, pointerId: e.pointerId, target: e.currentTarget as HTMLElement,
    };
  };
  const onResizePointerDown = (e: React.PointerEvent, layer: BlockData, mode: ResizeMode) => {
    e.stopPropagation();
    const rect = canvasRef.current?.getBoundingClientRect();
    const el = (e.currentTarget as HTMLElement).parentElement!; // the layer wrapper
    if (!rect) return;
    const layout = effectiveLayout(layer);
    const box = el.getBoundingClientRect();
    const origW = layout.widthPct ?? (box.width / rect.width) * 100;
    const origH = layout.heightPct ?? (box.height / rect.height) * 100;
    resizeState.current = {
      id: layer.id, mode, startX: e.clientX, startY: e.clientY,
      origX: pctOf(layout.x as string, 0), origY: pctOf(layout.y as string, 0),
      origW, origH,
      aspect: origW > 0 ? origH / origW : 1,
    };
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
  };
  const onPointerMove = (e: React.PointerEvent) => {
    const rect = canvasRef.current?.getBoundingClientRect();
    if (!rect) return;

    const rot = rotateState.current;
    if (rot) {
      const angle = (Math.atan2(e.clientY - rot.cy, e.clientX - rot.cx) * 180) / Math.PI;
      let deg = rot.origRot + (angle - rot.startAngle);
      deg = ((deg % 360) + 540) % 360 - 180; // normalize to -180..180
      if (e.shiftKey) {
        deg = Math.round(deg / 15) * 15; // Shift = 15° steps
      } else {
        const near = [-180, -90, 0, 90, 180].find(t => Math.abs(deg - t) <= 3);
        deg = near !== undefined ? (near === -180 ? 180 : near) : Math.round(deg);
      }
      writeLayout(rot.id, { rotation: deg === 0 ? undefined : deg });
      setRotReadout({ id: rot.id, deg });
      return;
    }

    const r = resizeState.current;
    if (r) {
      const dxPct = ((e.clientX - r.startX) / rect.width) * 100;
      const dyPct = ((e.clientY - r.startY) / rect.height) * 100;
      const clamp = (v: number) => Math.max(2, Math.min(100, v));
      const cx = (v: number) => `${Math.max(0, Math.min(96, v)).toFixed(1)}%`;
      // corners resize proportionally (aspect kept), anchored to the opposite
      // corner; edges resize one axis (w/n also shift x/y so the far edge stays)
      switch (r.mode) {
        case 'e': writeLayout(r.id, { widthPct: +clamp(r.origW + dxPct).toFixed(1) }); break;
        case 'w': writeLayout(r.id, { widthPct: +clamp(r.origW - dxPct).toFixed(1), x: cx(r.origX + dxPct) }); break;
        case 's': writeLayout(r.id, { heightPct: +clamp(r.origH + dyPct).toFixed(1) }); break;
        case 'n': writeLayout(r.id, { heightPct: +clamp(r.origH - dyPct).toFixed(1), y: cx(r.origY + dyPct) }); break;
        case 'se': {
          const w = clamp(r.origW + dxPct);
          writeLayout(r.id, { widthPct: +w.toFixed(1), heightPct: +clamp(w * r.aspect).toFixed(1) });
          break;
        }
        case 'sw': {
          const w = clamp(r.origW - dxPct);
          writeLayout(r.id, { widthPct: +w.toFixed(1), heightPct: +clamp(w * r.aspect).toFixed(1), x: cx(r.origX + (r.origW - w)) });
          break;
        }
        case 'ne': {
          const w = clamp(r.origW + dxPct);
          const h = clamp(w * r.aspect);
          writeLayout(r.id, { widthPct: +w.toFixed(1), heightPct: +h.toFixed(1), y: cx(r.origY + (r.origH - h)) });
          break;
        }
        case 'nw': {
          const w = clamp(r.origW - dxPct);
          const h = clamp(w * r.aspect);
          writeLayout(r.id, { widthPct: +w.toFixed(1), heightPct: +h.toFixed(1), x: cx(r.origX + (r.origW - w)), y: cx(r.origY + (r.origH - h)) });
          break;
        }
      }
      return;
    }

    const d = dragState.current;
    if (!d) return;
    // drag threshold: a plain click (no movement) must stay a click so the
    // Preview's own controls remain usable
    if (!d.moved) {
      if (Math.abs(e.clientX - d.startX) < 4 && Math.abs(e.clientY - d.startY) < 4) return;
      d.moved = true;
      d.target.setPointerCapture(d.pointerId);
    }
    let nx = Math.max(0, Math.min(96, d.origX + ((e.clientX - d.startX) / rect.width) * 100));
    let ny = Math.max(0, Math.min(96, d.origY + ((e.clientY - d.startY) / rect.height) * 100));
    const g: { v?: number; h?: number } = {};
    if (!e.altKey) {
      const TH = 1.2; // snap threshold in canvas %
      const box = d.target.getBoundingClientRect();
      const wPct = (box.width / rect.width) * 100;
      const hPct = (box.height / rect.height) * 100;
      // canvas center (layer CENTER magnetizes to 50%)
      if (Math.abs(nx + wPct / 2 - 50) < TH) { nx = 50 - wPct / 2; g.v = 50; }
      if (Math.abs(ny + hPct / 2 - 50) < TH) { ny = 50 - hPct / 2; g.h = 50; }
      // edge alignment with sibling layers (left/top edges)
      for (const other of activeSlide?.children ?? []) {
        if (other.id === d.id) continue;
        const ol = effectiveLayout(other);
        const ox = parseFloat(String(ol.x ?? ''));
        const oy = parseFloat(String(ol.y ?? ''));
        if (g.v === undefined && Number.isFinite(ox) && Math.abs(nx - ox) < TH) { nx = ox; g.v = ox; }
        if (g.h === undefined && Number.isFinite(oy) && Math.abs(ny - oy) < TH) { ny = oy; g.h = oy; }
      }
    }
    setSnapGuides(g.v !== undefined || g.h !== undefined ? g : null);
    writeLayout(d.id, { x: `${nx.toFixed(1)}%`, y: `${ny.toFixed(1)}%` });
  };
  const onPointerUp = () => {
    dragState.current = null;
    resizeState.current = null;
    rotateState.current = null;
    setRotReadout(null);
    setSnapGuides(null);
  };

  /* ── layer duplication: clone with fresh ids, offset +2% so it's visible ── */
  const cloneWithNewIds = (b: BlockData): BlockData => ({
    ...structuredClone(b), id: crypto.randomUUID(),
    children: (b.children ?? []).map(cloneWithNewIds),
  });
  const duplicateLayer = (layer: BlockData) => {
    if (!activeSlide) return;
    const copy = cloneWithNewIds(layer);
    const layout = (((copy.data as any)?.layout ?? {}) as Record<string, any>);
    copy.data = {
      ...(copy.data as any),
      layout: {
        ...layout,
        x: `${Math.min(96, pctOf(layout.x, 40) + 2).toFixed(1)}%`,
        y: `${Math.min(96, pctOf(layout.y, 40) + 2).toFixed(1)}%`,
      },
    } as any;
    const kids = [...(activeSlide.children ?? [])];
    const idx = kids.findIndex(k => k.id === layer.id);
    kids.splice(idx + 1, 0, copy);
    updateBlockChildren(activeSlide.id, kids);
    selectBlock(copy.id);
  };

  /* ── keyboard editing: arrows nudge 0.5% (Shift = 2%), Delete removes,
     Ctrl/Cmd+D duplicates, Escape deselects. Skipped while typing in any
     input/contenteditable (inspector fields, inline text editing). ── */
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const tgt = e.target as HTMLElement;
      if (tgt.closest('input, textarea, select')) return;
      // typing in place (text layers) or in the inspector's rich-text editor:
      // keys belong to the caret — except Escape, which finishes typing on a
      // canvas layer so the arrows/Delete/Ctrl+D below become available
      const editable = tgt.closest('[contenteditable="true"]') as HTMLElement | null;
      if (editable) {
        if (e.key === 'Escape' && editable.closest('[data-layer-id]')) {
          e.preventDefault();
          editable.blur();
        }
        return;
      }
      if (editingLayerId) {
        if (e.key === 'Escape') setEditingLayerId(null);
        return;
      }
      if (!selectedBlockId) return;
      const layer = (activeSlide?.children ?? []).find((l: BlockData) => l.id === selectedBlockId);
      if (!layer) return;
      const step = e.shiftKey ? 2 : 0.5;
      const layout = effectiveLayout(layer);
      const cx2 = pctOf(layout.x as string, 40);
      const cy2 = pctOf(layout.y as string, 40);
      const move = (dx: number, dy: number) => {
        e.preventDefault();
        writeLayout(layer.id, {
          x: `${Math.max(0, Math.min(96, cx2 + dx)).toFixed(1)}%`,
          y: `${Math.max(0, Math.min(96, cy2 + dy)).toFixed(1)}%`,
        });
      };
      switch (e.key) {
        case 'ArrowLeft': move(-step, 0); break;
        case 'ArrowRight': move(step, 0); break;
        case 'ArrowUp': move(0, -step); break;
        case 'ArrowDown': move(0, step); break;
        case 'Delete': case 'Backspace':
          e.preventDefault(); removeBlock(layer.id); selectBlock(null); break;
        case 'Escape': selectBlock(null); break;
        case 'd': case 'D':
          if (e.ctrlKey || e.metaKey) { e.preventDefault(); duplicateLayer(layer); }
          break;
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  });

  /* ── slide rail drag-reorder (dnd-kit; arrows kept as keyboard fallback) ── */
  const railSensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));
  const onSlideDragEnd = (ev: DragEndEvent) => {
    const { active, over } = ev;
    if (!root || !over || active.id === over.id) return;
    const list = [...(root.children ?? [])];
    const from = list.findIndex(s => s.id === active.id);
    const to = list.findIndex(s => s.id === over.id);
    if (from < 0 || to < 0) return;
    updateBlockChildren(root.id, arrayMove(list, from, to));
  };

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
          <button onClick={() => zoomBy(-0.1)} className="btn btn-xs btn-ghost" title="Zoom out">−</button>
          <button onClick={() => setZoom(1)} className="btn btn-xs btn-ghost w-12 tabular-nums" title="Reset to 100%">
            {Math.round(zoom * 100)}%
          </button>
          <button onClick={() => zoomBy(0.1)} className="btn btn-xs btn-ghost" title="Zoom in">+</button>
          <button onClick={zoomFit} className="btn btn-xs btn-ghost" title="Fit canvas to view">Fit</button>
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
              {LAYER_TYPES.map(({ type, label, icon: Icon, defaults, size }) => (
                <button key={type} onClick={() => addLayer(type, defaults, size)}
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
          {/* NOTE: no flex-centering here — centering an overflowing flex child
              makes its left side unreachable by scrolling. A block wrapper with
              margin:auto centers when it fits and scrolls fully when it doesn't. */}
          <div ref={scrollWrapRef} className="flex-1 overflow-auto p-6"
            onPointerDown={e => {
              // clicking anywhere outside a layer (gray area OR canvas bg)
              // deselects and exits inline editing
              if (!(e.target as HTMLElement).closest('[data-layer-id]')) {
                selectBlock(null);
                setEditingLayerId(null);
              }
            }}>
            <div style={{
              width: Math.round(DEVICE_WIDTHS[canvasDevice] * zoom),
              height: Math.round(DEVICE_VIEWPORT_H[canvasDevice] * canvasHeightRatio * zoom),
              margin: '0 auto',
            }}>
            <div ref={canvasRef}
              onPointerMove={onPointerMove} onPointerUp={onPointerUp}
              onPointerDown={() => { selectBlock(null); setEditingLayerId(null); }}
              className="relative bg-neutral-900 overflow-hidden shadow-lg shrink-0"
              style={{
                width: DEVICE_WIDTHS[canvasDevice],
                height: Math.round(DEVICE_VIEWPORT_H[canvasDevice] * canvasHeightRatio),
                transform: `scale(${zoom})`,
                transformOrigin: 'top left',
              }}>
              {/* slide background (assetId resolves via serve endpoint) */}
              {bg.type === 'image' && bgUrl && <img src={bgUrl} alt="" className="absolute inset-0 w-full h-full object-cover" />}
              {bg.type === 'video' && bgUrl && <video src={bgUrl} muted loop autoPlay playsInline className="absolute inset-0 w-full h-full object-cover" />}
              {(!bg.type || bg.type === 'color') && <div className="absolute inset-0" style={{ background: bg.color || '#1A1817' }} />}
              {bg.overlay && <div className="absolute inset-0" style={{ background: bg.overlay }} />}

              {/* snap guide lines (drag only; Alt disables snapping) */}
              {snapGuides?.v !== undefined && (
                <div className="absolute top-0 bottom-0 w-px bg-secondary pointer-events-none" style={{ left: `${snapGuides.v}%`, zIndex: 999 }} />
              )}
              {snapGuides?.h !== undefined && (
                <div className="absolute left-0 right-0 h-px bg-secondary pointer-events-none" style={{ top: `${snapGuides.h}%`, zIndex: 999 }} />
              )}

              {/* layers at FINAL state (canvas edits layout; motion previews come from the shared runtime later) */}
              {(activeSlide?.children ?? []).map((layer: BlockData) => {
                const layout = effectiveLayout(layer);
                if (layout.hidden) return null; // hidden on this device
                const reg = blockRegistry.get(layer.type);
                const selected = selectedBlockId === layer.id;
                const editing = editingLayerId === layer.id;
                const handle = (mode: ResizeMode, cls: string, title: string) => (
                  <div onPointerDown={e => onResizePointerDown(e, layer, mode)} title={title}
                    className={`absolute w-2.5 h-2.5 bg-primary border border-white z-10 ${cls}`} />
                );
                return (
                  <div key={`${layer.id}-${previewNonce}`}
                    data-layer-id={layer.id}
                    onPointerDown={e => onLayerPointerDown(e, layer)}
                    onDoubleClick={e => { e.stopPropagation(); setEditingLayerId(layer.id); selectBlock(layer.id); }}
                    className={`absolute sp-edit-layer ${editing ? 'ring-2 ring-warning cursor-text' : selected ? 'ring-2 ring-primary ring-offset-1 cursor-move' : 'cursor-move hover:ring-1 hover:ring-primary/40'}`}
                    style={{
                      left: layout.x ?? '40%', top: layout.y ?? '40%',
                      width: layout.widthPct != null ? `${layout.widthPct}%` : undefined,
                      height: layout.heightPct != null ? `${layout.heightPct}%` : undefined,
                      zIndex: layout.zIndex ?? 2,
                      transform: layout.rotation ? `rotate(${layout.rotation}deg)` : undefined,
                    }}>
                    {reg ? <reg.Preview block={layer} isSelected={selected} onSelect={() => selectBlock(layer.id)} onUpdate={d => updateBlock(layer.id, d)} />
                      : <div className="text-xs text-white/60 p-2">{layer.type}</div>}
                    {selected && !editing && canvasDevice === 'desktop' && (
                      <div
                        className="absolute left-1/2 -translate-x-1/2 -top-8 z-10 flex flex-col items-center cursor-grab active:cursor-grabbing"
                        title="Drag to rotate — Shift = 15° steps · double-click resets to 0°"
                        onPointerDown={e => onRotatePointerDown(e, layer)}
                        onDoubleClick={e => { e.stopPropagation(); writeLayout(layer.id, { rotation: undefined }); }}>
                        <div className="w-3 h-3 rounded-full bg-primary border border-white" />
                        <div className="w-px h-3.5 bg-primary/70" />
                      </div>
                    )}
                    {rotReadout?.id === layer.id && (
                      <div className="absolute -top-14 left-1/2 -translate-x-1/2 bg-neutral-800 text-white text-[9px] px-1.5 py-0.5 rounded pointer-events-none whitespace-nowrap z-10">
                        {rotReadout.deg}°
                      </div>
                    )}
                    {selected && !editing && (
                      <>
                        {handle('nw', 'left-0 top-0 -translate-x-1/2 -translate-y-1/2 cursor-nwse-resize', 'Resize proportionally (top-left)')}
                        {handle('n', 'top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 cursor-ns-resize', 'Resize height (top)')}
                        {handle('ne', 'right-0 top-0 translate-x-1/2 -translate-y-1/2 cursor-nesw-resize', 'Resize proportionally (top-right)')}
                        {handle('w', 'left-0 top-1/2 -translate-x-1/2 -translate-y-1/2 cursor-ew-resize', 'Resize width (left)')}
                        {handle('e', 'right-0 top-1/2 translate-x-1/2 -translate-y-1/2 cursor-ew-resize', 'Resize width (right)')}
                        {handle('sw', 'left-0 bottom-0 -translate-x-1/2 translate-y-1/2 cursor-nesw-resize', 'Resize proportionally (bottom-left)')}
                        {handle('s', 'bottom-0 left-1/2 -translate-x-1/2 translate-y-1/2 cursor-ns-resize', 'Resize height (bottom)')}
                        {handle('se', 'bottom-0 right-0 translate-x-1/2 translate-y-1/2 cursor-nwse-resize', 'Resize proportionally (bottom-right)')}
                      </>
                    )}
                    {editing && (
                      <div className="absolute -top-6 left-0 bg-warning text-warning-content text-[9px] px-1.5 py-0.5 whitespace-nowrap">
                        editing — click outside to finish
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
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
            {/* interactive timeline: ruler in seconds; drag a bar to change its
                start time, drag its right edge to change duration; ▲▼ = stacking */}
            {(() => {
              const layers = (activeSlide?.children ?? []) as BlockData[];
              if (!layers.length) return null;
              // scale: fit the longest scene, min 3s
              let maxEnd = 0;
              for (const l of layers) {
                const anim = ((l.data as any)?.animation ?? {}) as Record<string, any>;
                for (const ph of ['in', 'out'] as const) {
                  if (!anim[ph]) continue;
                  const { delay, dur } = sceneTiming(anim[ph]);
                  maxEnd = Math.max(maxEnd, delay + dur);
                }
              }
              const totalSec = Math.max(3, Math.ceil((maxEnd + 0.4) * 2) / 2);
              const ticks = Array.from({ length: Math.floor(totalSec / 0.5) + 1 }, (_, i) => i * 0.5);
              const zOf = (l: BlockData) => Number(((l.data as any)?.layout?.zIndex ?? 2));
              const zSorted = [...layers].sort((a, b) => zOf(a) - zOf(b) || (a.order ?? 0) - (b.order ?? 0));
              const zRank = new Map(zSorted.map((l, i) => [l.id, i]));

              const bar = (layer: BlockData, phase: 'in' | 'out', color: string) => {
                const anim = ((layer.data as any)?.animation ?? {}) as Record<string, any>;
                if (!anim[phase]) return null;
                const { delay, dur } = sceneTiming(anim[phase]);
                const leftPct = Math.min(98, (delay / totalSec) * 100);
                const widthPct = Math.max(2, (dur / totalSec) * 100);
                return (
                  <div
                    className={`absolute top-1/2 -translate-y-1/2 h-3 ${color} cursor-grab active:cursor-grabbing group/bar`}
                    style={{ left: `${leftPct}%`, width: `${widthPct}%` }}
                    title={`${phase.toUpperCase()}: starts ${delay.toFixed(2)}s, ${dur.toFixed(2)}s long — drag to move, drag right edge for duration`}
                    onPointerDown={e => onTlBarPointerDown(e, layer.id, phase, 'move', (e.currentTarget as HTMLElement).parentElement!)}>
                    <span className="absolute inset-0 flex items-center justify-center text-[8px] text-white/90 font-medium overflow-hidden whitespace-nowrap pointer-events-none">
                      {dur >= 0.35 * (totalSec / 6) ? `${delay.toFixed(1)}s +${dur.toFixed(1)}s` : ''}
                    </span>
                    <span
                      className="absolute right-0 top-0 h-full w-1.5 bg-black/30 cursor-ew-resize"
                      title="Drag to change duration"
                      onPointerDown={e => onTlBarPointerDown(e, layer.id, phase, 'dur', (e.currentTarget as HTMLElement).parentElement!.parentElement!)} />
                  </div>
                );
              };

              return (
                <div className="mt-1" onPointerMove={e => onTlPointerMove(e, totalSec)} onPointerUp={onTlPointerUp}>
                  {/* ruler */}
                  <div className="flex items-center gap-2">
                    <span className="w-28 shrink-0 text-right text-[8px] text-base-content/30 pr-1">
                      {tlReadout ?? 'seconds →'}
                    </span>
                    <div className="relative flex-1 h-3.5">
                      {ticks.map(t => (
                        <div key={t} className="absolute top-0 h-full border-l border-base-300"
                          style={{ left: `${(t / totalSec) * 100}%` }}>
                          {t % 1 === 0 && <span className="absolute top-0 left-0.5 text-[8px] text-base-content/40">{t}s</span>}
                        </div>
                      ))}
                    </div>
                  </div>
                  {/* layer rows */}
                  <div className="space-y-px">
                    {layers.map((layer: BlockData) => {
                      const label = String((layer.data as any)?.content ?? (layer.data as any)?.text ?? layer.type)
                        .replace(/<[^>]*>/g, '').slice(0, 14) || layer.type;
                      const rank = zRank.get(layer.id) ?? 0;
                      return (
                        <div key={layer.id}
                          className={`flex items-center gap-2 ${selectedBlockId === layer.id ? 'opacity-100 bg-base-200/60' : 'opacity-70 hover:opacity-100'}`}
                          onClick={() => selectBlock(layer.id)}>
                          <span className="w-28 shrink-0 flex items-center justify-end gap-0.5">
                            <span className="text-[9px] text-base-content/50 truncate">{label}</span>
                            <button title="Bring forward (stack above)" className="p-px hover:text-primary"
                              onClick={e => { e.stopPropagation(); nudgeZ(layer.id, 1); }}
                              disabled={rank === layers.length - 1}>
                              <ChevronUp size={9} />
                            </button>
                            <button title="Send backward (stack below)" className="p-px hover:text-primary"
                              onClick={e => { e.stopPropagation(); nudgeZ(layer.id, -1); }}
                              disabled={rank === 0}>
                              <ChevronDown size={9} />
                            </button>
                            <span className="text-[8px] text-base-content/30 w-4 text-center tabular-nums"
                              title={`Stacking: ${rank + 1} of ${layers.length} (${rank === layers.length - 1 ? 'front' : rank === 0 ? 'back' : 'middle'})`}>
                              {rank + 1}
                            </span>
                          </span>
                          <div className="relative flex-1 h-4 bg-base-200"
                            style={{ backgroundImage: `repeating-linear-gradient(to right, transparent, transparent calc(${100 / (totalSec * 2)}% - 1px), color-mix(in srgb, currentColor 7%, transparent) calc(${100 / (totalSec * 2)}% - 1px), color-mix(in srgb, currentColor 7%, transparent) ${100 / (totalSec * 2)}%)` }}>
                            {bar(layer, 'in', 'bg-primary')}
                            {bar(layer, 'out', 'bg-warning')}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              );
            })()}
          </div>

          {/* slide rail (drag a thumbnail to reorder; arrows kept as fallback) */}
          <div className="bg-base-100 border-t border-base-300 px-3 py-2 flex items-center gap-2 overflow-x-auto">
            <DndContext sensors={railSensors} collisionDetection={closestCenter} onDragEnd={onSlideDragEnd}>
              <SortableContext items={slides.map(s => s.id)} strategy={horizontalListSortingStrategy}>
                {slides.map((s, i) => (
                  <SlideThumb key={s.id} slide={s} index={i}
                    isActive={s.id === activeSlide?.id}
                    onSelect={() => { setActiveSlideId(s.id); selectBlock(null); }}
                    onMove={dir => moveSlide(s.id, dir)}
                    onDuplicate={() => duplicateSlide(s.id)}
                    onRemove={() => removeSlide(s.id)} />
                ))}
              </SortableContext>
            </DndContext>
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

/** sortable slide thumbnail — drag anywhere on it to reorder (6px threshold
    keeps plain clicks working for selection and the hover buttons) */
function SlideThumb({ slide, index, isActive, onSelect, onMove, onDuplicate, onRemove }: {
  slide: BlockData; index: number; isActive: boolean;
  onSelect: () => void; onMove: (dir: -1 | 1) => void; onDuplicate: () => void; onRemove: () => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: slide.id });
  return (
    <div ref={setNodeRef} {...attributes} {...listeners}
      className={`group relative shrink-0 w-24 h-14 border-2 flex items-center justify-center text-[10px] ${isDragging ? 'opacity-60 z-10 cursor-grabbing' : 'cursor-grab'} ${isActive ? 'border-primary' : 'border-base-300 hover:border-base-content/40'}`}
      style={{
        transform: CSS.Transform.toString(transform), transition,
        background: ((slide.data as any)?.background?.color) || '#1A1817', color: '#fff',
      }}
      onClick={onSelect}>
      <span className="opacity-70">Slide {index + 1}</span>
      <span className="opacity-40 absolute bottom-0.5 right-1">{(slide.children ?? []).length}</span>
      <div className="absolute -top-2 right-0 hidden group-hover:flex gap-0.5 bg-base-100 border border-base-300 p-0.5 text-base-content">
        <button title="Move left" onClick={e => { e.stopPropagation(); onMove(-1); }} className="p-0.5 hover:text-primary"><ChevronLeft size={10} /></button>
        <button title="Move right" onClick={e => { e.stopPropagation(); onMove(1); }} className="p-0.5 hover:text-primary"><ChevronRight size={10} /></button>
        <button title="Duplicate" onClick={e => { e.stopPropagation(); onDuplicate(); }} className="p-0.5 hover:text-green-600"><Copy size={10} /></button>
        <button title="Delete" onClick={e => { e.stopPropagation(); onRemove(); }} className="p-0.5 hover:text-red-600"><Trash2 size={10} /></button>
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
