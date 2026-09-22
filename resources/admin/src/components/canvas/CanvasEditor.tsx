import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Plus, Undo2, Redo2, Magnet, ZoomIn, ZoomOut, Maximize2, Monitor, Smartphone, Eye, RefreshCw } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { useCanvasStore } from '@/stores/canvasStore';
import { saveContent } from '@/lib/saveCoordinator';
import { pages as pagesApi, posts as postsApi, auth, blocks as blocksApi } from '@/lib/api';
import { colorForId } from '@/lib/collabColor';
import { CanvasSection } from './CanvasSection';
import { CanvasInspector } from './CanvasInspector';
import { CanvasBlockPalette } from './CanvasBlockPalette';
import { useCanvasCollab, type PeerCursor } from './useCanvasCollab';
import { isCollabEnabled } from '@/lib/echo';
import { effectiveLayout } from '@/types/canvas';
import type { CanvasFit, CanvasPageType } from '@/types/canvas';

const NO_CURSORS: PeerCursor[] = [];   // stable empty ref so cursorless sections skip re-render
const NUDGE_IDLE_MS = 600;             // arrow-key nudges within this gap = one undo entry
const ZOOM_MIN = 0.25;
const ZOOM_MAX = 2;
const FIT_GUTTER = 48;                 // px of pane left free either side when zooming to fit

/** Zoom that shows the whole design width inside a pane of `paneWidth` px (never above 1:1). */
export function fitZoom(paneWidth: number, designWidth: number): number {
  if (!(paneWidth > 0) || !(designWidth > 0)) return 1;
  return Math.max(ZOOM_MIN, Math.min(1, Math.floor(((paneWidth - FIT_GUTTER) / designWidth) * 100) / 100));
}

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
  const fit = useCanvasStore(s => s.fit);
  const zoom = useCanvasStore(s => s.zoom);
  const snapEnabled = useCanvasStore(s => s.snapEnabled);
  const activeSectionId = useCanvasStore(s => s.activeSectionId);
  const activeBreakpoint = useCanvasStore(s => s.activeBreakpoint);
  const mobileWidth = useCanvasStore(s => s.mobileWidth);
  const selectedIds = useCanvasStore(s => s.selectedIds);
  const isDirty = useCanvasStore(s => s.isDirty);
  const undoLen = useCanvasStore(s => s.undoStack.length);   // local-mode undo availability
  const redoLen = useCanvasStore(s => s.redoStack.length);
  // Actions are stable refs in zustand — read them once (getState) instead of
  // subscribing to the whole store, which re-rendered the editor on every op.
  const {
    addSection, undo, redo, toggleSnap, setZoom, deleteElements,
    duplicateElements, bringToFront, sendToBack, bringForward, sendBackward, clearSelection, pushSnapshot,
    setBreakpoint, setEditing,
  } = useCanvasStore.getState();

  // Persist page-type + design width to seo_meta.canvas (merged, non-clobbering).
  const persistCanvasMeta = (patch: { page_type?: CanvasPageType; width?: number; mobile_width?: number; fit?: CanvasFit }) => {
    const prev = (seoMeta?.canvas ?? {}) as Record<string, unknown>;
    const st = useCanvasStore.getState();
    const canvas = { page_type: st.pageType, width: st.width, mobile_width: st.mobileWidth, fit: st.fit, ...prev, ...patch };
    const apiFor = contentType === 'posts' ? postsApi : pagesApi;
    apiFor.update(siteId, pageId, { seo_meta: { ...(seoMeta ?? {}), canvas } }).catch(() => { /* soft */ });
  };

  const { data: me } = useQuery({
    queryKey: ['auth-me'],
    queryFn: () => auth.me().then((r) => r.data.user as { id: string; name: string }),
    staleTime: Infinity,
  });
  const [saveError, setSaveError] = useState(false);
  // Autosave path used by the collab leader (same lossless sync as manual save).
  const autosave = useCallback(() => {
    // F11: same coordinator as Save (revision-aware, dirty cleared only when
    // nothing changed meanwhile, ignored if the session moved on).
    saveContent({ siteId, type: contentType, id: pageId })
      .then(() => setSaveError(false))
      .catch(() => setSaveError(true));   // surface it — collaborators can lose work silently otherwise
  }, [siteId, contentType, pageId]);
  // Reconnect reseed: re-hydrate from the last saved tree after a dropped socket.
  const onReseed = useCallback(() => {
    blocksApi.get(siteId, contentType, pageId).then((r) => {
      const cv = (seoMeta?.canvas ?? {}) as { page_type?: string; width?: number; mobile_width?: number; fit?: string };
      useCanvasStore.getState().loadFromBlocks(r.data.data, { pageType: cv.page_type === 'single' ? 'single' : 'website', width: cv.width, mobileWidth: cv.mobile_width, fit: cv.fit });
    }).catch(() => { /* soft */ });
  }, [siteId, contentType, pageId, seoMeta]);
  const { members: presence, cursors, broadcastCursor, lockedIds, undo: collabUndo, redo: collabRedo, canUndo, canRedo } = useCanvasCollab(pageId, contentType, me?.id, autosave, onReseed);
  // Group peer cursors by section once per cursor change; each section then gets
  // a stable array ref (so a local drag doesn't churn every section's props).
  const cursorsBySection = useMemo(() => {
    const m: Record<string, PeerCursor[]> = {};
    for (const c of Object.values(cursors)) (m[c.sectionId] ??= []).push(c);
    return m;
  }, [cursors]);
  // Collab-enabled pages use per-client op-inverse undo (converges); otherwise
  // the local snapshot undo.
  const collabEnabled = isCollabEnabled();
  const doUndo = collabEnabled ? collabUndo : undo;
  const doRedo = collabEnabled ? collabRedo : redo;
  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewMobile, setPreviewMobile] = useState(false);
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const paneRef = useRef<HTMLDivElement>(null);           // the scrolling canvas pane
  const singleMode = pageType === 'single';

  // Zoom to fit: the whole design width visible in the pane. Applied once when
  // the pane first has a size (jsdom/tests have none → untouched), and on the
  // toolbar "Fit" button / Ctrl+0. Manual ± zoom is left alone after that.
  const effWidth = activeBreakpoint === 'mobile' ? mobileWidth : width;
  const zoomToFit = useCallback(() => {
    const w = paneRef.current?.clientWidth ?? 0;
    if (w > 0) setZoom(fitZoom(w, effWidth));
  }, [effWidth, setZoom]);
  const fittedOnce = useRef(false);
  useEffect(() => {
    if (fittedOnce.current) return;
    const w = paneRef.current?.clientWidth ?? 0;
    if (w > 0 && sections.length > 0) { fittedOnce.current = true; setZoom(fitZoom(w, effWidth)); }
  }, [sections.length, effWidth, setZoom]);
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

      // Per-client op-inverse undo in collab; local snapshot undo otherwise.
      if (meta && e.key.toLowerCase() === 'z') { e.preventDefault(); endNudge(); e.shiftKey ? doRedo() : doUndo(); return; }
      if (meta && e.key.toLowerCase() === 'y') { e.preventDefault(); endNudge(); doRedo(); return; }
      if (meta && e.key === '0') { e.preventDefault(); zoomToFit(); return; }
      if (meta && e.key === '1') { e.preventDefault(); setZoom(1); return; }
      if (!sel.length) { if (e.key === 'Escape') { endNudge(); clearSelection(); } return; }

      if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); endNudge(); deleteElements(sel); return; }
      // Escape steps out of in-place editing first (keeps the selection), then deselects.
      if (e.key === 'Escape') { endNudge(); if (st.editingId) setEditing(null); else clearSelection(); return; }
      if (meta && e.key.toLowerCase() === 'd') { e.preventDefault(); endNudge(); duplicateElements(sel); return; }
      // Layer order: Ctrl+] / Ctrl+[ one step; with Shift all the way.
      if (meta && (e.key === ']' || e.key === '}')) { e.preventDefault(); endNudge(); e.shiftKey ? bringToFront(sel) : bringForward(sel); return; }
      if (meta && (e.key === '[' || e.key === '{')) { e.preventDefault(); endNudge(); e.shiftKey ? sendToBack(sel) : sendBackward(sel); return; }

      const step = e.shiftKey ? 10 : 1;
      const delta = { ArrowUp: [0, -step], ArrowDown: [0, step], ArrowLeft: [-step, 0], ArrowRight: [step, 0] }[e.key];
      if (delta) {
        e.preventDefault();
        // Snapshot once per nudge run; keep the run alive on each keypress.
        if (!nudgeActive.current) { pushSnapshot(); nudgeActive.current = true; }
        if (nudgeTimer.current !== null) clearTimeout(nudgeTimer.current);
        nudgeTimer.current = window.setTimeout(endNudge, NUDGE_IDLE_MS);
        const [dx, dy] = delta;
        const bp = st.activeBreakpoint;
        const els = st.sections.flatMap(s => s.elements).filter(el => sel.includes(el.id));
        els.forEach(el => { const L = effectiveLayout(el, bp); st.updateElementLayout(el.id, { x: L.x + dx, y: L.y + dy }, bp); });
      }
    };
    window.addEventListener('keydown', onKey);
    return () => { window.removeEventListener('keydown', onKey); endNudge(); };
  }, [doUndo, doRedo, clearSelection, deleteElements, duplicateElements, bringToFront, sendToBack, bringForward, sendBackward, pushSnapshot, setEditing, zoomToFit, setZoom]);

  const previewUrl = `/api/v1/sites/${siteId}/${contentType}/${pageId}/preview`;
  const refreshPreview = () => { if (iframeRef.current) iframeRef.current.src = `${previewUrl}?t=${Date.now()}`; };

  return (
    <div className="flex flex-1 overflow-hidden">
      {/* block palette: drag tiles onto a section, or click to add */}
      <CanvasBlockPalette />

      <div className="flex flex-col flex-1 overflow-hidden">
        {/* toolbar */}
        <div className="flex items-center gap-1 px-3 py-1.5 border-b border-base-200 bg-base-100 text-sm">
          {!singleMode && (
            <button className="btn btn-xs btn-primary gap-1" onClick={() => addSection(activeSectionId ?? undefined)}>
              <Plus size={13} /> Section
            </button>
          )}
          <div className="w-px h-4 bg-base-300 mx-1" />
          <button className="btn btn-xs btn-ghost" onClick={doUndo} disabled={collabEnabled ? !canUndo : undoLen === 0} title="Undo (Ctrl+Z)" aria-label="Undo"><Undo2 size={14} /></button>
          <button className="btn btn-xs btn-ghost" onClick={doRedo} disabled={collabEnabled ? !canRedo : redoLen === 0} title="Redo (Ctrl+Shift+Z)" aria-label="Redo"><Redo2 size={14} /></button>
          <button className={`btn btn-xs ${snapEnabled ? 'btn-primary' : 'btn-ghost'}`} onClick={toggleSnap} title="Snapping" aria-label="Toggle snapping" aria-pressed={snapEnabled}><Magnet size={14} /></button>
          <div className="w-px h-4 bg-base-300 mx-1" />
          <button className="btn btn-xs btn-ghost" onClick={() => setZoom(zoom - 0.1)} disabled={zoom <= ZOOM_MIN} title="Zoom out" aria-label="Zoom out"><ZoomOut size={14} /></button>
          <span className="text-xs w-10 text-center">{Math.round(zoom * 100)}%</span>
          <button className="btn btn-xs btn-ghost" onClick={() => setZoom(zoom + 0.1)} disabled={zoom >= ZOOM_MAX} title="Zoom in" aria-label="Zoom in"><ZoomIn size={14} /></button>
          <button className="btn btn-xs btn-ghost gap-1" onClick={zoomToFit} title="Fit the whole width in view (Ctrl+0)" aria-label="Zoom to fit"><Maximize2 size={13} /> Fit</button>
          <button className="btn btn-xs btn-ghost" onClick={() => setZoom(1)} title="Actual size (Ctrl+1) — wider than the pane? scroll sideways" aria-label="Zoom 100%">1:1</button>
          <div className="w-px h-4 bg-base-300 mx-1" />
          {/* breakpoint switcher — edit desktop base or the mobile override */}
          <div className="flex bg-base-200 rounded p-0.5">
            <button className={`btn btn-xs ${activeBreakpoint === 'desktop' ? 'btn-primary' : 'btn-ghost'}`} onClick={() => setBreakpoint('desktop')} title="Desktop layout" aria-label="Edit desktop layout" aria-pressed={activeBreakpoint === 'desktop'}><Monitor size={13} /></button>
            <button className={`btn btn-xs ${activeBreakpoint === 'mobile' ? 'btn-primary' : 'btn-ghost'}`} onClick={() => setBreakpoint('mobile')} title="Mobile layout override" aria-label="Edit mobile layout override" aria-pressed={activeBreakpoint === 'mobile'}><Smartphone size={13} /></button>
          </div>
          <div className="flex-1" />
          {selectedIds.length > 0 && (
            <span className="text-[10px] text-base-content/40 mr-2 hidden xl:inline" aria-hidden>
              drag to move · click again to type · Alt = no snapping
            </span>
          )}
          {presence.length > 0 && (
            <div className="flex items-center -space-x-1.5 mr-2" title={`Editing now: ${presence.map(p => p.name).join(', ')}`}>
              {presence.slice(0, 5).map(p => (
                <span
                  key={p.id}
                  className="w-5 h-5 rounded-full border border-base-100 flex items-center justify-center text-[9px] font-bold text-white"
                  style={{ background: colorForId(p.id) }}
                >{(p.name || '?').charAt(0).toUpperCase()}</span>
              ))}
              {presence.length > 5 && <span className="text-[10px] text-base-content/40 pl-2">+{presence.length - 5}</span>}
            </div>
          )}
          {saveError && (
            <span
              className="text-[10px] text-error font-medium mr-2 whitespace-nowrap"
              role="status"
              title="Autosave failed — your latest changes are not saved. Use manual save."
            >⚠ autosave failed</span>
          )}
          <button className={`btn btn-xs ${previewOpen ? 'btn-primary' : 'btn-ghost'} gap-1`} onClick={() => { setPreviewOpen(v => !v); setTimeout(refreshPreview, 50); }} aria-label="Toggle live preview" aria-pressed={previewOpen}>
            <Eye size={14} /> Preview
          </button>
        </div>

        {/* what the visitor gets — so the centred design-width canvas isn't mistaken for the screen */}
        <div className="px-3 py-1 text-[10px] text-base-content/50 bg-base-200/40 border-b border-base-200" data-testid="canvas-fit-hint">
          {fit === 'scale'
            ? <>Design width {width}px · <b>scales to the visitor's screen</b>: the canvas edge is the screen edge, the whole design grows or shrinks with the window. Phones stack the blocks (or use the phone layout).</>
            : <>Design width {width}px · <b>centred column</b>: on wider screens there is empty space either side; below {width}px the blocks stack.</>}
        </div>

        {/* section stack — scrolls both ways, so a canvas wider than the pane is never cut off */}
        <div ref={paneRef} className="flex-1 overflow-auto bg-base-300/20" onPointerDown={() => clearSelection()}>
          {sections.length === 0 && (
            <div className="flex flex-col items-center justify-center h-full text-base-content/40 gap-3">
              <p>This canvas page is empty.</p>
              <button className="btn btn-sm btn-primary gap-1" onClick={() => addSection()}><Plus size={14} /> Add a section</button>
              <p className="text-xs">…or click a block on the left to start.</p>
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
              peerCursors={cursorsBySection[section.id] ?? NO_CURSORS}
              members={presence}
              onCursorMove={broadcastCursor}
              lockedIds={lockedIds}
            />
          ))}
        </div>
      </div>

      {/* properties panel (content, layer order, opacity, position, phone, animation) */}
      <CanvasInspector persistCanvasMeta={persistCanvasMeta} />

      {/* live preview split-pane */}
      {previewOpen && (
        <div className="w-[45%] max-w-[640px] border-l border-base-200 flex flex-col bg-base-200">
          <div className="flex items-center gap-1 px-2 py-1.5 border-b border-base-300 text-xs">
            <span className="font-medium text-base-content/60">Live preview</span>
            <div className="flex-1" />
            <button className={`btn btn-xs ${!previewMobile ? 'btn-primary' : 'btn-ghost'}`} onClick={() => setPreviewMobile(false)} title="Desktop" aria-label="Preview desktop width"><Monitor size={14} /></button>
            <button className={`btn btn-xs ${previewMobile ? 'btn-primary' : 'btn-ghost'}`} onClick={() => setPreviewMobile(true)} title="Mobile" aria-label="Preview mobile width"><Smartphone size={14} /></button>
            <button className="btn btn-xs btn-ghost" onClick={refreshPreview} title="Refresh (shows last saved)" aria-label="Refresh preview"><RefreshCw size={14} /></button>
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
