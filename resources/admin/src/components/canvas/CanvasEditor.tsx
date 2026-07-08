import { useEffect, useRef, useState } from 'react';
import { Plus, Undo2, Redo2, Magnet, ZoomIn, ZoomOut, Monitor, Smartphone, Eye, RefreshCw } from 'lucide-react';
import { useCanvasStore } from '@/stores/canvasStore';
import { pages as pagesApi, posts as postsApi } from '@/lib/api';
import { CanvasSection } from './CanvasSection';
import { effectiveLayout } from '@/types/canvas';
import type { CanvasPageType, CanvasAnim } from '@/types/canvas';

interface Props {
  siteId: string;
  pageId: string;                       // content id (page or post)
  contentType?: 'pages' | 'posts';
  seoMeta?: Record<string, unknown>;
  onDirty?: () => void;
}

export function CanvasEditor({ siteId, pageId, contentType = 'pages', seoMeta, onDirty }: Props) {
  const sections = useCanvasStore(s => s.sections);
  const pageType = useCanvasStore(s => s.pageType);
  const width = useCanvasStore(s => s.width);
  const zoom = useCanvasStore(s => s.zoom);
  const snapEnabled = useCanvasStore(s => s.snapEnabled);
  const activeSectionId = useCanvasStore(s => s.activeSectionId);
  const activeBreakpoint = useCanvasStore(s => s.activeBreakpoint);
  const selectedIds = useCanvasStore(s => s.selectedIds);
  const isDirty = useCanvasStore(s => s.isDirty);
  const {
    addSection, undo, redo, toggleSnap, setZoom, deleteElements, updateElements,
    duplicateElements, bringToFront, sendToBack, clearSelection, pushSnapshot,
    setPageType, setWidth, setBreakpoint, clearMobileOverride, setElementPin, setElementAnim,
  } = useCanvasStore();

  // Pin controls apply to a single selected element in a fluid section.
  const selSection = selectedIds.length === 1
    ? sections.find(s => s.elements.some(e => e.id === selectedIds[0]))
    : undefined;
  const selEl = selSection?.elements.find(e => e.id === selectedIds[0]);
  const pinnable = !!(selSection?.settings.fluid && selEl);
  const currentPin = selEl?.pinX ?? 'left';

  // Persist page-type + design width to seo_meta.canvas (merged, non-clobbering).
  const persistCanvasMeta = (patch: { page_type?: CanvasPageType; width?: number }) => {
    const prev = (seoMeta?.canvas ?? {}) as Record<string, unknown>;
    const st = useCanvasStore.getState();
    const canvas = { page_type: st.pageType, width: st.width, ...prev, ...patch };
    const apiFor = contentType === 'posts' ? postsApi : pagesApi;
    apiFor.update(siteId, pageId, { seo_meta: { ...(seoMeta ?? {}), canvas } }).catch(() => { /* soft */ });
  };

  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewMobile, setPreviewMobile] = useState(false);
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const singleMode = pageType === 'single';
  // A run of arrow-key nudges collapses into one undo entry: snapshot on the
  // first nudge, then again only after a short idle gap or another action.
  const nudgeActive = useRef(false);
  const nudgeTimer = useRef<number | null>(null);

  useEffect(() => { if (isDirty) onDirty?.(); }, [isDirty, onDirty]);

  // keyboard: nudge / delete / duplicate / undo-redo / z-order / escape
  useEffect(() => {
    const endNudge = () => {
      nudgeActive.current = false;
      if (nudgeTimer.current !== null) { clearTimeout(nudgeTimer.current); nudgeTimer.current = null; }
    };
    const onKey = (e: KeyboardEvent) => {
      const tag = (e.target as HTMLElement)?.tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || (e.target as HTMLElement)?.isContentEditable) return;
      const st = useCanvasStore.getState();
      const sel = st.selectedIds;
      const meta = e.metaKey || e.ctrlKey;

      if (meta && e.key.toLowerCase() === 'z') { e.preventDefault(); endNudge(); e.shiftKey ? redo() : undo(); return; }
      if (meta && e.key.toLowerCase() === 'y') { e.preventDefault(); endNudge(); redo(); return; }
      if (!sel.length) { if (e.key === 'Escape') { endNudge(); clearSelection(); } return; }

      if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); endNudge(); deleteElements(sel); return; }
      if (e.key === 'Escape') { endNudge(); clearSelection(); return; }
      if (meta && e.key.toLowerCase() === 'd') { e.preventDefault(); endNudge(); duplicateElements(sel); return; }
      if (meta && e.key === ']') { e.preventDefault(); endNudge(); bringToFront(sel); return; }
      if (meta && e.key === '[') { e.preventDefault(); endNudge(); sendToBack(sel); return; }

      const step = e.shiftKey ? 10 : 1;
      const delta = { ArrowUp: [0, -step], ArrowDown: [0, step], ArrowLeft: [-step, 0], ArrowRight: [step, 0] }[e.key];
      if (delta) {
        e.preventDefault();
        // Snapshot once per nudge run; keep the run alive on each keypress.
        if (!nudgeActive.current) { pushSnapshot(); nudgeActive.current = true; }
        if (nudgeTimer.current !== null) clearTimeout(nudgeTimer.current);
        nudgeTimer.current = window.setTimeout(endNudge, 600);
        const [dx, dy] = delta;
        const bp = st.activeBreakpoint;
        const els = st.sections.flatMap(s => s.elements).filter(el => sel.includes(el.id));
        els.forEach(el => { const L = effectiveLayout(el, bp); st.updateElementLayout(el.id, { x: L.x + dx, y: L.y + dy }, bp); });
      }
    };
    window.addEventListener('keydown', onKey);
    return () => { window.removeEventListener('keydown', onKey); endNudge(); };
  }, [undo, redo, clearSelection, deleteElements, duplicateElements, bringToFront, sendToBack, pushSnapshot, updateElements]);

  const previewUrl = `/api/v1/sites/${siteId}/${contentType}/${pageId}/preview`;
  const refreshPreview = () => { if (iframeRef.current) iframeRef.current.src = `${previewUrl}?t=${Date.now()}`; };

  return (
    <div className="flex flex-1 overflow-hidden">
      <div className="flex flex-col flex-1 overflow-hidden">
        {/* toolbar */}
        <div className="flex items-center gap-1 px-3 py-1.5 border-b border-base-200 bg-base-100 text-sm">
          {!singleMode && (
            <button className="btn btn-xs btn-primary gap-1" onClick={() => addSection(activeSectionId ?? undefined)}>
              <Plus size={13} /> Section
            </button>
          )}
          <div className="w-px h-4 bg-base-300 mx-1" />
          <button className="btn btn-xs btn-ghost" onClick={undo} title="Undo (Ctrl+Z)"><Undo2 size={14} /></button>
          <button className="btn btn-xs btn-ghost" onClick={redo} title="Redo (Ctrl+Shift+Z)"><Redo2 size={14} /></button>
          <button className={`btn btn-xs ${snapEnabled ? 'btn-primary' : 'btn-ghost'}`} onClick={toggleSnap} title="Snapping"><Magnet size={14} /></button>
          <div className="w-px h-4 bg-base-300 mx-1" />
          <button className="btn btn-xs btn-ghost" onClick={() => setZoom(zoom - 0.1)} title="Zoom out"><ZoomOut size={14} /></button>
          <span className="text-xs w-10 text-center">{Math.round(zoom * 100)}%</span>
          <button className="btn btn-xs btn-ghost" onClick={() => setZoom(zoom + 0.1)} title="Zoom in"><ZoomIn size={14} /></button>
          <div className="w-px h-4 bg-base-300 mx-1" />
          {/* breakpoint switcher — edit desktop base or the mobile override */}
          <div className="flex bg-base-200 rounded p-0.5">
            <button className={`btn btn-xs ${activeBreakpoint === 'desktop' ? 'btn-primary' : 'btn-ghost'}`} onClick={() => setBreakpoint('desktop')} title="Desktop layout"><Monitor size={13} /></button>
            <button className={`btn btn-xs ${activeBreakpoint === 'mobile' ? 'btn-primary' : 'btn-ghost'}`} onClick={() => setBreakpoint('mobile')} title="Mobile layout override"><Smartphone size={13} /></button>
          </div>
          {activeBreakpoint === 'mobile' && selectedIds.length === 1 && (
            <button className="btn btn-xs btn-ghost" onClick={() => clearMobileOverride(selectedIds[0])} title="Reset this element to inherit the desktop position">reset</button>
          )}
          {pinnable && (
            <>
              <div className="w-px h-4 bg-base-300 mx-1" />
              <span className="text-[10px] text-base-content/40">pin</span>
              {([['left', 'L'], ['center', 'C'], ['right', 'R'], ['stretch', '↔']] as const).map(([p, label]) => (
                <button
                  key={p}
                  className={`btn btn-xs ${currentPin === p ? 'btn-primary' : 'btn-ghost'}`}
                  title={`Pin ${p}`}
                  onClick={() => setElementPin(selectedIds[0], p)}
                >{label}</button>
              ))}
            </>
          )}
          {selEl && (
            <>
              <div className="w-px h-4 bg-base-300 mx-1" />
              <label className="flex items-center gap-1 text-[10px] text-base-content/40" title="Scroll-in animation">
                anim
                <select
                  className="select select-xs select-bordered"
                  value={selEl.anim?.type ?? 'none'}
                  onChange={(e) => setElementAnim(selectedIds[0], { ...selEl.anim, type: e.target.value as CanvasAnim['type'] })}
                >
                  {(['none', 'fade', 'slide-up', 'slide-down', 'slide-left', 'slide-right', 'zoom', 'scale-in'] as const).map(t => (
                    <option key={t} value={t}>{t}</option>
                  ))}
                </select>
              </label>
              {selEl.anim && selEl.anim.type !== 'none' && (
                <input
                  type="number"
                  className="input input-xs input-bordered w-14"
                  title="Delay (ms)"
                  min={0}
                  max={5000}
                  step={50}
                  value={selEl.anim.delay ?? 0}
                  onChange={(e) => setElementAnim(selectedIds[0], { type: selEl.anim!.type, delay: Number(e.target.value) })}
                />
              )}
            </>
          )}
          <div className="flex-1" />
          <label className="flex items-center gap-1 text-[10px] text-base-content/50" title="Page type">
            <select
              className="select select-xs select-bordered"
              value={pageType}
              onChange={(e) => { const t = e.target.value as CanvasPageType; setPageType(t); persistCanvasMeta({ page_type: t }); }}
            >
              <option value="website">Website (stack + scroll)</option>
              <option value="single">Single (one canvas)</option>
            </select>
          </label>
          <label className="flex items-center gap-1 text-[10px] text-base-content/50 mr-2" title="Design width (px)">
            W
            <input
              type="number"
              className="input input-xs input-bordered w-16"
              value={width}
              min={320}
              max={3000}
              onChange={(e) => setWidth(Number(e.target.value))}
              onBlur={(e) => persistCanvasMeta({ width: Number(e.target.value) })}
            />
          </label>
          <button className={`btn btn-xs ${previewOpen ? 'btn-primary' : 'btn-ghost'} gap-1`} onClick={() => { setPreviewOpen(v => !v); setTimeout(refreshPreview, 50); }}>
            <Eye size={14} /> Preview
          </button>
        </div>

        {/* section stack */}
        <div className="flex-1 overflow-y-auto bg-base-300/20" onPointerDown={() => clearSelection()}>
          {sections.length === 0 && (
            <div className="flex flex-col items-center justify-center h-full text-base-content/40 gap-3">
              <p>This canvas page is empty.</p>
              <button className="btn btn-sm btn-primary gap-1" onClick={() => addSection()}><Plus size={14} /> Add a section</button>
            </div>
          )}
          {sections.map((section, i) => (
            <CanvasSection
              key={section.id}
              section={section}
              width={width}
              zoom={zoom}
              isActive={section.id === activeSectionId}
              canMoveUp={i > 0}
              canMoveDown={i < sections.length - 1}
              singleMode={singleMode}
            />
          ))}
        </div>
      </div>

      {/* live preview split-pane */}
      {previewOpen && (
        <div className="w-[45%] max-w-[640px] border-l border-base-200 flex flex-col bg-base-200">
          <div className="flex items-center gap-1 px-2 py-1.5 border-b border-base-300 text-xs">
            <span className="font-medium text-base-content/60">Live preview</span>
            <div className="flex-1" />
            <button className={`btn btn-xs ${!previewMobile ? 'btn-primary' : 'btn-ghost'}`} onClick={() => setPreviewMobile(false)} title="Desktop"><Monitor size={14} /></button>
            <button className={`btn btn-xs ${previewMobile ? 'btn-primary' : 'btn-ghost'}`} onClick={() => setPreviewMobile(true)} title="Mobile"><Smartphone size={14} /></button>
            <button className="btn btn-xs btn-ghost" onClick={refreshPreview} title="Refresh (shows last saved)"><RefreshCw size={14} /></button>
          </div>
          <div className="flex-1 overflow-auto flex justify-center p-3">
            <iframe
              ref={iframeRef}
              src={previewUrl}
              title="Canvas preview"
              className="bg-white shadow"
              style={{ width: previewMobile ? 390 : '100%', height: '100%', border: 'none' }}
            />
          </div>
          <p className="text-[10px] text-base-content/40 px-2 py-1">Preview reflects the last SAVED version — save, then refresh.</p>
        </div>
      )}
    </div>
  );
}
