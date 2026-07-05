import React, { useState, useRef } from 'react';
import { Plus, Copy, Trash2, LayoutTemplate } from 'lucide-react';
import type { MagPageData, MagElement } from '@/types/magazine';
import { DEFAULT_ELEMENT_STYLE, DEFAULT_TEXT_WRAP, DEFAULT_TYPOGRAPHY } from '@/types/magazine';

// ─── Page templates ───

interface PageTemplate {
  id: string;
  label: string;
  description: string;
  createFrames: (pageSize: { width: number; height: number }, margins: { top: number; right: number; bottom: number; left: number }) => MagElement[];
}

/**
 * Create a template frame. Content HTML uses only DOMPurify-safe tags
 * (p, h1, h2, strong, em) — sanitized at render by MagElementRenderer.
 */
function makeFrame(type: string, name: string, x: number, y: number, w: number, h: number, data: Record<string, unknown>, pageNumber = 1): MagElement {
  const isText = ['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame'].includes(type);
  return {
    id: crypto.randomUUID(), type: type as any, name, data,
    x, y, width: w, height: h, rotation: 0, scaleX: 1, scaleY: 1, zIndex: 0,
    locked: false, visible: true, layerName: null,
    style: { ...DEFAULT_ELEMENT_STYLE }, typography: isText ? { ...DEFAULT_TYPOGRAPHY } : null,
    textWrap: { ...DEFAULT_TEXT_WRAP }, threadId: null, threadOrder: null,
    pageNumber, onMaster: false, parentId: null, children: [], responsiveOverrides: {},
  };
}

const PAGE_TEMPLATES: PageTemplate[] = [
  {
    id: 'cover',
    label: 'Cover',
    description: 'Large image with title and subtitle',
    createFrames: (ps, m) => {
      const cw = ps.width - m.left - m.right;
      return [
        makeFrame('image_frame', 'Cover Image', 0, 0, ps.width, ps.height * 0.65, { src: '', alt: '', fit: 'cover', focalPoint: { x: 0.5, y: 0.5 }, imageOffsetX: 0, imageOffsetY: 0, imageScale: 1, imageRotation: 0, clipShape: 'rectangle', clipPath: null, filters: { brightness: 100, contrast: 100, saturation: 100, grayscale: false, duotone: null } }),
        makeFrame('headline_frame', 'Title', m.left, ps.height * 0.68, cw, 80, { content: '<h1>Magazine Title</h1>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnFill: 'auto', columnRule: false, textInset: { top: 8, right: 8, bottom: 8, left: 8 }, verticalAlign: 'center' }),
        makeFrame('caption_frame', 'Subtitle', m.left, ps.height * 0.68 + 90, cw, 40, { content: '<p>Issue subtitle or date</p>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnFill: 'auto', columnRule: false, textInset: { top: 4, right: 4, bottom: 4, left: 4 }, verticalAlign: 'top' }),
      ];
    },
  },
  {
    id: 'article',
    label: 'Article',
    description: 'Heading, body text, and pull quote',
    createFrames: (ps, m) => {
      const cw = ps.width - m.left - m.right;
      return [
        makeFrame('headline_frame', 'Article Title', m.left, m.top, cw, 60, { content: '<h1>Article Title</h1>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnFill: 'auto', columnRule: false, textInset: { top: 8, right: 8, bottom: 8, left: 8 }, verticalAlign: 'center' }),
        makeFrame('text_frame', 'Body Text', m.left, m.top + 70, cw, ps.height - m.top - m.bottom - 70, { content: '<p>Start writing your article here...</p>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 2, columnGap: 16, columnFill: 'auto', columnRule: false, textInset: { top: 8, right: 8, bottom: 8, left: 8 }, verticalAlign: 'top' }),
      ];
    },
  },
  {
    id: 'gallery',
    label: 'Gallery',
    description: 'Multiple images with captions',
    createFrames: (ps, m) => {
      const cw = ps.width - m.left - m.right;
      const imgW = (cw - 12) / 2;
      const imgH = imgW * 0.75;
      const imgData = { src: '', alt: '', fit: 'cover', focalPoint: { x: 0.5, y: 0.5 }, imageOffsetX: 0, imageOffsetY: 0, imageScale: 1, imageRotation: 0, clipShape: 'rectangle', clipPath: null, filters: { brightness: 100, contrast: 100, saturation: 100, grayscale: false, duotone: null } };
      const capData = { content: '<p>Caption</p>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnFill: 'auto', columnRule: false, textInset: { top: 2, right: 4, bottom: 2, left: 4 }, verticalAlign: 'top' };
      return [
        makeFrame('headline_frame', 'Gallery Title', m.left, m.top, cw, 50, { content: '<h2>Photo Gallery</h2>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnFill: 'auto', columnRule: false, textInset: { top: 8, right: 8, bottom: 8, left: 8 }, verticalAlign: 'center' }),
        makeFrame('image_frame', 'Image 1', m.left, m.top + 60, imgW, imgH, imgData),
        makeFrame('caption_frame', 'Caption 1', m.left, m.top + 60 + imgH + 4, imgW, 24, capData),
        makeFrame('image_frame', 'Image 2', m.left + imgW + 12, m.top + 60, imgW, imgH, imgData),
        makeFrame('caption_frame', 'Caption 2', m.left + imgW + 12, m.top + 60 + imgH + 4, imgW, 24, capData),
        makeFrame('image_frame', 'Image 3', m.left, m.top + 60 + imgH + 40, imgW, imgH, imgData),
        makeFrame('caption_frame', 'Caption 3', m.left, m.top + 60 + imgH + 40 + imgH + 4, imgW, 24, capData),
        makeFrame('image_frame', 'Image 4', m.left + imgW + 12, m.top + 60 + imgH + 40, imgW, imgH, imgData),
        makeFrame('caption_frame', 'Caption 4', m.left + imgW + 12, m.top + 60 + imgH + 40 + imgH + 4, imgW, 24, capData),
      ];
    },
  },
  {
    id: 'interview',
    label: 'Interview',
    description: 'Title, portrait, and Q&A text',
    createFrames: (ps, m) => {
      const cw = ps.width - m.left - m.right;
      const imgData = { src: '', alt: '', fit: 'cover', focalPoint: { x: 0.5, y: 0.5 }, imageOffsetX: 0, imageOffsetY: 0, imageScale: 1, imageRotation: 0, clipShape: 'rectangle', clipPath: null, filters: { brightness: 100, contrast: 100, saturation: 100, grayscale: false, duotone: null } };
      return [
        makeFrame('headline_frame', 'Interview Title', m.left, m.top, cw, 60, { content: '<h1>Interview with...</h1>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnFill: 'auto', columnRule: false, textInset: { top: 8, right: 8, bottom: 8, left: 8 }, verticalAlign: 'center' }),
        makeFrame('image_frame', 'Portrait', m.left, m.top + 70, cw * 0.4, cw * 0.4, imgData),
        makeFrame('text_frame', 'Introduction', m.left + cw * 0.45, m.top + 70, cw * 0.55, cw * 0.4, { content: '<p>Brief introduction to the interviewee...</p>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 1, columnGap: 12, columnFill: 'auto', columnRule: false, textInset: { top: 8, right: 8, bottom: 8, left: 8 }, verticalAlign: 'top' }),
        makeFrame('text_frame', 'Q&A', m.left, m.top + 80 + cw * 0.4, cw, ps.height - m.top - m.bottom - 80 - cw * 0.4, { content: '<p><strong>Q: What inspired you?</strong></p><p>A: Answer here...</p><p><strong>Q: What is your process?</strong></p><p>A: Answer here...</p>', overflow: 'hidden', autoSize: 'none', columnsInFrame: 2, columnGap: 16, columnFill: 'auto', columnRule: true, textInset: { top: 8, right: 8, bottom: 8, left: 8 }, verticalAlign: 'top' }),
      ];
    },
  },
];

// ─── Component ───

interface PageNavigatorProps {
  pages: MagPageData[];
  currentPage: number;
  onChangePage: (n: number) => void;
  onAddPage: () => void;
  onDeletePage: (n: number) => void;
  onDuplicatePage?: (n: number) => void;
  onReorderPages?: (fromIndex: number, toIndex: number) => void;
  onApplyTemplate?: (pageNumber: number, frames: MagElement[]) => void;
  masterPages?: MagPageData[];
  onAssignMaster?: (pageNumber: number, masterPageId: string | null) => void;
  onAssignMasterToAll?: (masterPageId: string | null) => void;
  onEditMaster?: (masterPageId: string | null) => void;
  editingMasterId?: string | null;
  onSetMasterApplies?: (masterPageId: string, v: 'all' | 'verso' | 'recto') => void;
  onDetachMaster?: (pageNumber: number) => void;
}

export default function PageNavigator({
  pages,
  currentPage,
  onChangePage,
  onAddPage,
  onDeletePage,
  onDuplicatePage,
  onReorderPages,
  onApplyTemplate,
  masterPages = [],
  onAssignMaster,
  onAssignMasterToAll,
  onEditMaster,
  editingMasterId,
  onSetMasterApplies,
  onDetachMaster,
}: PageNavigatorProps) {
  const [confirmDelete, setConfirmDelete] = useState<number | null>(null);
  const [confirmTemplate, setConfirmTemplate] = useState<{ pageNumber: number; template: PageTemplate } | null>(null);
  const [contextMenu, setContextMenu] = useState<{ page: number; x: number; y: number } | null>(null);
  const [showTemplates, setShowTemplates] = useState<number | null>(null);
  const dragItemRef = useRef<number | null>(null);
  const dragOverRef = useRef<number | null>(null);
  const [dragOverIndex, setDragOverIndex] = useState<number | null>(null);

  const sorted = [...pages].sort((a, b) => a.pageNumber - b.pageNumber);

  const handleDragStart = (index: number) => { dragItemRef.current = index; };
  const handleDragOver = (e: React.DragEvent, index: number) => { e.preventDefault(); dragOverRef.current = index; setDragOverIndex(index); };
  const handleDragEnd = () => {
    if (dragItemRef.current !== null && dragOverRef.current !== null && dragItemRef.current !== dragOverRef.current) {
      onReorderPages?.(dragItemRef.current, dragOverRef.current);
    }
    dragItemRef.current = null; dragOverRef.current = null; setDragOverIndex(null);
  };

  const handleApplyTemplate = (pageNumber: number, template: PageTemplate) => {
    setShowTemplates(null);
    const page = sorted.find(p => p.pageNumber === pageNumber);
    if (!page) return;
    if (page.elements.length > 0) {
      setConfirmTemplate({ pageNumber, template });
    } else {
      const frames = template.createFrames(page.pageSize, page.margins);
      frames.forEach((f, i) => { f.pageNumber = pageNumber; f.zIndex = i + 1; });
      onApplyTemplate?.(pageNumber, frames);
    }
  };

  const handleConfirmTemplate = () => {
    if (!confirmTemplate) return;
    const page = sorted.find(p => p.pageNumber === confirmTemplate.pageNumber);
    if (!page) return;
    const frames = confirmTemplate.template.createFrames(page.pageSize, page.margins);
    frames.forEach((f, i) => { f.pageNumber = confirmTemplate.pageNumber; f.zIndex = i + 1; });
    onApplyTemplate?.(confirmTemplate.pageNumber, frames);
    setConfirmTemplate(null);
  };

  return (
    <div className="flex flex-col items-center gap-2 py-3 px-2 bg-base-200/50 h-full overflow-y-auto w-24 shrink-0"
      onClick={() => { setContextMenu(null); setShowTemplates(null); }}>
      {/* Master pages section */}
      {masterPages.length > 0 && (
        <div className="w-full mb-2 pb-2 border-b border-base-300/20">
          <span className="text-[8px] font-semibold uppercase tracking-wider text-base-content/30">Masters</span>
          {masterPages.map(mp => (
            <div key={mp.id} className="mt-0.5">
              <button onClick={() => onEditMaster?.(editingMasterId === mp.id ? null : mp.id)}
                className={`w-full text-left px-1.5 py-1 rounded text-[9px] transition-colors ${editingMasterId === mp.id ? 'bg-warning/20 text-warning font-medium' : 'text-base-content/50 hover:bg-base-300/20'}`}>
                {(mp as any)._masterName || `Master ${Math.abs(mp.pageNumber)}`}
                {editingMasterId === mp.id && <span className="text-[7px] ml-1 text-warning/60">editing</span>}
              </button>
              {onSetMasterApplies && (
                <select
                  value={(mp as any)._appliesTo || 'all'}
                  onChange={e => onSetMasterApplies(mp.id, e.target.value as 'all' | 'verso' | 'recto')}
                  title="Which pages this master applies to (verso = left, recto = right)"
                  className="select select-bordered select-xs w-full text-[8px] mt-0.5"
                >
                  <option value="all">All pages</option>
                  <option value="verso">Verso (left) only</option>
                  <option value="recto">Recto (right) only</option>
                </select>
              )}
            </div>
          ))}
          {editingMasterId && (
            <button onClick={() => onEditMaster?.(null)} className="w-full text-[8px] text-primary mt-1 hover:underline">
              ← Back to pages
            </button>
          )}
        </div>
      )}

      {/* Master assignment for current page */}
      {!editingMasterId && masterPages.length > 0 && (
        <div className="w-full mb-2">
          <label className="text-[8px] text-base-content/30 block mb-0.5">Master for p.{currentPage}</label>
          <select
            value={sorted.find(p => p.pageNumber === currentPage)?.masterPageId || ''}
            onChange={e => onAssignMaster?.(currentPage, e.target.value || null)}
            className="select select-bordered select-xs w-full text-[9px]"
          >
            <option value="">None</option>
            {masterPages.map(mp => (
              <option key={mp.id} value={mp.id}>{(mp as any)._masterName || `Master ${Math.abs(mp.pageNumber)}`}</option>
            ))}
          </select>
          <button onClick={() => onAssignMasterToAll?.(sorted.find(p => p.pageNumber === currentPage)?.masterPageId || null)}
            className="text-[7px] text-primary/60 hover:text-primary mt-0.5 block">
            Apply to all pages
          </button>
          {sorted.find(p => p.pageNumber === currentPage)?.masterPageId && onDetachMaster && (
            <button onClick={() => onDetachMaster(currentPage)}
              title="Copy the master's elements onto this page as editable elements and unlink the master (revert by re-assigning)"
              className="text-[7px] text-warning/70 hover:text-warning mt-0.5 block">
              Detach master to this page
            </button>
          )}
        </div>
      )}

      <span className="text-[9px] font-semibold uppercase tracking-wider text-base-content/40 mb-1">Pages</span>

      {sorted.map((page, index) => {
        const isCurrent = page.pageNumber === currentPage;
        const ratio = page.pageSize.height / page.pageSize.width;
        const thumbW = 64;
        const thumbH = Math.round(thumbW * ratio);

        return (
          <div
            key={page.id}
            className={`flex flex-col items-center gap-0.5 ${dragOverIndex === index ? 'border-t-2 border-primary pt-1' : ''}`}
            draggable
            onDragStart={() => handleDragStart(index)}
            onDragOver={(e) => handleDragOver(e, index)}
            onDragEnd={handleDragEnd}
          >
            <button
              className={`relative rounded transition-all border-2 cursor-grab active:cursor-grabbing
                ${isCurrent ? 'border-primary shadow-sm shadow-primary/20' : 'border-base-content/10 hover:border-base-content/25'}`}
              style={{ width: thumbW, height: Math.min(80, Math.max(40, thumbH)), backgroundColor: page.backgroundColor || '#ffffff' }}
              onClick={() => onChangePage(page.pageNumber)}
              onContextMenu={(e) => { e.preventDefault(); setContextMenu({ page: page.pageNumber, x: e.clientX, y: e.clientY }); }}
              title={`Page ${page.pageNumber} — drag to reorder`}
            >
              {/* Live schematic thumbnail (W2-9): elements at their TRUE positions,
                  scaled to the thumb — text = lined block, image = tinted, shape = fill */}
              {page.elements.length > 0 && (
                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                  {[...page.elements].sort((a, b) => a.zIndex - b.zIndex).slice(0, 24).map(el => {
                    if (el.visible === false) return null;
                    const sx = thumbW / (page.pageSize.width || 595);
                    const sy = Math.min(80, Math.max(40, thumbH)) / (page.pageSize.height || 842);
                    const isImg = ['image_frame', 'circular_image', 'polygon_image', 'fullbleed_image', 'gallery_frame', 'background_image'].includes(el.type);
                    const isText = ['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'].includes(el.type);
                    const fill = isImg ? 'rgba(59,130,246,0.35)'
                      : isText ? 'rgba(120,113,108,0.28)'
                      : ((el.data as any)?.fillColor || (el.style as any)?.fill?.color || 'rgba(156,163,175,0.4)');
                    return (
                      <div key={el.id} style={{
                        position: 'absolute',
                        left: el.x * sx, top: el.y * sy,
                        width: Math.max(2, el.width * sx), height: Math.max(1.5, el.height * sy),
                        background: isText
                          ? 'repeating-linear-gradient(rgba(120,113,108,0.45) 0 1px, transparent 1px 3px)'
                          : fill,
                        borderRadius: el.type === 'circular_image' ? '50%' : 0,
                      }} />
                    );
                  })}
                </div>
              )}
              {page.elements.length === 0 && (
                <span className="text-[7px] text-base-content/15 absolute inset-0 flex items-center justify-center">Empty</span>
              )}
            </button>
            <span className={`text-[10px] tabular-nums ${isCurrent ? 'text-primary font-semibold' : 'text-base-content/40'}`}>
              {page.pageNumber}
            </span>
          </div>
        );
      })}

      {/* Action buttons */}
      <div className="flex gap-1 mt-1">
        <button className="btn btn-ghost btn-xs btn-square border border-dashed border-base-content/20 hover:border-primary/50"
          onClick={onAddPage} title="Add page"><Plus size={14} className="text-base-content/40" /></button>
        {onDuplicatePage && (
          <button className="btn btn-ghost btn-xs btn-square border border-dashed border-base-content/20 hover:border-primary/50"
            onClick={() => onDuplicatePage(currentPage)} title="Duplicate current page"><Copy size={14} className="text-base-content/40" /></button>
        )}
        <button className="btn btn-ghost btn-xs btn-square border border-dashed border-base-content/20 hover:border-primary/50"
          onClick={(e) => { e.stopPropagation(); setShowTemplates(showTemplates === currentPage ? null : currentPage); }} title="Apply template"><LayoutTemplate size={14} className="text-base-content/40" /></button>
      </div>

      {/* Template picker */}
      {showTemplates !== null && (
        <div className="bg-base-100 border border-base-300/30 rounded-lg shadow-lg p-2 w-full space-y-1" onClick={e => e.stopPropagation()}>
          <span className="text-[8px] text-base-content/30 uppercase tracking-wider font-medium">Templates</span>
          {PAGE_TEMPLATES.map(tpl => (
            <button key={tpl.id} onClick={() => handleApplyTemplate(showTemplates, tpl)}
              className="w-full text-left px-2 py-1.5 rounded text-[10px] hover:bg-base-300/20 transition-colors">
              <div className="font-medium text-base-content/70">{tpl.label}</div>
              <div className="text-[8px] text-base-content/30">{tpl.description}</div>
            </button>
          ))}
        </div>
      )}

      {/* Context menu */}
      {contextMenu && (
        <div className="fixed z-50 bg-base-100 shadow-lg rounded-lg border border-base-content/10 py-1 min-w-36"
          style={{ left: contextMenu.x, top: contextMenu.y }} onClick={e => e.stopPropagation()}>
          {onDuplicatePage && (
            <button className="w-full text-left px-3 py-1.5 text-[11px] hover:bg-base-content/5"
              onClick={() => { onDuplicatePage(contextMenu.page); setContextMenu(null); }}>
              <Copy size={12} className="inline mr-1.5" /> Duplicate page {contextMenu.page}
            </button>
          )}
          <button className="w-full text-left px-3 py-1.5 text-[11px] hover:bg-base-content/5"
            onClick={() => { setShowTemplates(contextMenu.page); setContextMenu(null); }}>
            <LayoutTemplate size={12} className="inline mr-1.5" /> Apply template
          </button>
          <button className="w-full text-left px-3 py-1.5 text-[11px] hover:bg-base-content/5 text-error"
            onClick={() => { setConfirmDelete(contextMenu.page); setContextMenu(null); }}>
            <Trash2 size={12} className="inline mr-1.5" /> Delete page {contextMenu.page}
          </button>
        </div>
      )}

      {/* Delete confirmation */}
      {confirmDelete !== null && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-base-content/20" onClick={() => setConfirmDelete(null)}>
          <div className="bg-base-100 rounded-xl shadow-xl p-5 max-w-xs mx-4" onClick={e => e.stopPropagation()}>
            <h4 className="font-semibold text-base-content mb-2">Delete page {confirmDelete}?</h4>
            <p className="text-sm text-base-content/60 mb-4">
              This will permanently remove the page and all {sorted.find(p => p.pageNumber === confirmDelete)?.elements.length || 0} elements on it.
            </p>
            <div className="flex gap-2 justify-end">
              <button className="btn btn-ghost btn-sm" onClick={() => setConfirmDelete(null)}>Cancel</button>
              <button className="btn btn-error btn-sm" onClick={() => { onDeletePage(confirmDelete); setConfirmDelete(null); }}>Delete</button>
            </div>
          </div>
        </div>
      )}

      {/* Template confirmation (non-empty page) */}
      {confirmTemplate && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-base-content/20" onClick={() => setConfirmTemplate(null)}>
          <div className="bg-base-100 rounded-xl shadow-xl p-5 max-w-xs mx-4" onClick={e => e.stopPropagation()}>
            <h4 className="font-semibold text-base-content mb-2">Apply "{confirmTemplate.template.label}" template?</h4>
            <p className="text-sm text-base-content/60 mb-4">
              Page {confirmTemplate.pageNumber} has {sorted.find(p => p.pageNumber === confirmTemplate.pageNumber)?.elements.length || 0} elements. Template frames will be added alongside existing content.
            </p>
            <div className="flex gap-2 justify-end">
              <button className="btn btn-ghost btn-sm" onClick={() => setConfirmTemplate(null)}>Cancel</button>
              <button className="btn btn-primary btn-sm" onClick={handleConfirmTemplate}>Apply</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
