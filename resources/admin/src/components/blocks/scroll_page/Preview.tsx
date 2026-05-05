import React, { useState } from 'react';
import { Layers, Plus, Trash2, ChevronDown, ChevronRight, ArrowUp, ArrowDown, Copy } from 'lucide-react';
import type { BlockComponentProps } from '@/types/blocks';

const PAGE_TYPES = ['cover', 'quote', 'editorial_title', 'editorial_body', 'pull_quote', 'closing', 'footer'];
const PAGE_LABELS: Record<string, string> = {
  cover: 'Cover', quote: 'Quote', editorial_title: 'Editorial Title',
  editorial_body: 'Editorial Body', pull_quote: 'Pull Quote',
  closing: 'Closing', footer: 'Footer',
};

const PAGE_DEFAULTS: Record<string, any> = {
  cover: { eyebrow: '', masthead: '', mastheadMeta: '', divider: '· · ·', subtitle: '', hook: '', showScrollHint: true },
  quote: { lines: [{ text: '', emphasis: [] }], showMark: false },
  editorial_title: { chapterLabel: '', chapterTitle: '', showDotMark: false },
  editorial_body: { paragraphs: [{ text: '', isLead: false, emphasis: [] }], centered: false, maxWidth: 'reading', showMark: false },
  pull_quote: { text: '', emphasis: [], showLines: true },
  closing: { line: '', emphasis: [] },
  footer: { mark: '· · ·', lines: [{ text: '', emphasis: [] }], meta: '' },
};

export const ScrollPagePreview: React.FC<BlockComponentProps> = ({ block, onUpdate }) => {
  const data = block.data as any;
  const pages: any[] = data.pages || [];
  const palette = data.palette || {};
  const preset = data.mouseEffect?.preset || 'just-clouds';
  const [expandedPage, setExpandedPage] = useState<number | null>(null);
  const [showAddMenu, setShowAddMenu] = useState(false);

  const updatePages = (newPages: any[]) => {
    onUpdate({ ...data, pages: newPages });
  };

  const addPage = (type: string) => {
    const newPage = { id: crypto.randomUUID(), type, tall: false, data: { ...PAGE_DEFAULTS[type] } };
    // Insert after expanded page, or at end
    const idx = expandedPage !== null ? expandedPage + 1 : pages.length;
    const newPages = [...pages];
    newPages.splice(idx, 0, newPage);
    updatePages(newPages);
    setExpandedPage(idx);
    setShowAddMenu(false);
  };

  const removePage = (idx: number) => {
    const newPages = pages.filter((_: any, i: number) => i !== idx);
    updatePages(newPages);
    if (expandedPage === idx) setExpandedPage(null);
    else if (expandedPage !== null && expandedPage > idx) setExpandedPage(expandedPage - 1);
  };

  const movePage = (idx: number, dir: -1 | 1) => {
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= pages.length) return;
    const newPages = [...pages];
    [newPages[idx], newPages[newIdx]] = [newPages[newIdx], newPages[idx]];
    updatePages(newPages);
    if (expandedPage === idx) setExpandedPage(newIdx);
  };

  const duplicatePage = (idx: number) => {
    const newPage = JSON.parse(JSON.stringify(pages[idx]));
    newPage.id = crypto.randomUUID();
    const newPages = [...pages];
    newPages.splice(idx + 1, 0, newPage);
    updatePages(newPages);
    setExpandedPage(idx + 1);
  };

  const updatePageField = (idx: number, field: string, value: any) => {
    const newPages = JSON.parse(JSON.stringify(pages));
    const keys = field.split('.');
    let obj = newPages[idx];
    for (let i = 0; i < keys.length - 1; i++) {
      if (!obj[keys[i]]) obj[keys[i]] = {};
      obj = obj[keys[i]];
    }
    obj[keys[keys.length - 1]] = value;
    updatePages(newPages);
  };

  const updatePageType = (idx: number, newType: string) => {
    const newPages = JSON.parse(JSON.stringify(pages));
    newPages[idx].type = newType;
    newPages[idx].data = { ...PAGE_DEFAULTS[newType] };
    updatePages(newPages);
  };

  return (
    <div className="border border-base-300/30 rounded-lg overflow-hidden sl-page-editor" style={{ background: palette.paper || '#EFE7D5' }}>
      <style>{`
        .sl-page-editor input, .sl-page-editor textarea, .sl-page-editor select {
          background: #fff !important; color: #1a1a1a !important; border-color: #d1d5db !important;
        }
        .sl-page-editor input::placeholder, .sl-page-editor textarea::placeholder {
          color: #9ca3af !important;
        }
        .sl-page-editor input[type="checkbox"] {
          background: transparent !important;
        }
        .sl-page-editor option { background: #fff !important; color: #1a1a1a !important; }
      `}</style>
      {/* Header */}
      <div className="flex items-center gap-2 px-3 py-2 border-b border-base-300/20" style={{ background: palette.paperDeep || '#E6DCC6' }}>
        <Layers size={14} style={{ color: palette.rust || '#9B5A3E' }} />
        <span className="text-[12px] font-medium" style={{ color: palette.ink || '#2A2117' }}>Scroll Page</span>
        <span className="text-[10px]" style={{ color: palette.inkSoft || '#4A3F32' }}>{pages.length} pages · {preset}</span>
        <div className="flex-1" />
        <div className="relative">
          <button onClick={() => setShowAddMenu(!showAddMenu)}
            className="flex items-center gap-1 text-[10px] px-2 py-1 rounded font-medium hover:opacity-80"
            style={{ background: 'rgba(155,90,62,0.15)', color: palette.rust || '#9B5A3E' }}>
            <Plus size={12} /> Add Page
          </button>
          {showAddMenu && (
            <div className="absolute right-0 top-full mt-1 z-30 bg-white rounded-lg shadow-lg border border-gray-200 py-1 min-w-[160px]">
              {PAGE_TYPES.map(type => (
                <button key={type} onClick={() => addPage(type)}
                  className="w-full text-left px-3 py-1.5 text-[11px] hover:bg-gray-50 text-gray-700">
                  {PAGE_LABELS[type]}
                </button>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Page list */}
      <div className="p-2 space-y-1">
        {pages.map((page: any, i: number) => {
          const isExpanded = expandedPage === i;
          const firstText = getPagePreviewText(page);
          return (
            <div key={page.id || i} className="rounded" style={{ background: isExpanded ? 'rgba(0,0,0,0.06)' : 'rgba(0,0,0,0.02)' }}>
              {/* Page row */}
              <div className="flex items-center gap-1.5 px-2 py-1.5 cursor-pointer select-none"
                onClick={() => setExpandedPage(isExpanded ? null : i)}>
                {isExpanded ? <ChevronDown size={12} style={{ color: palette.rust }} /> : <ChevronRight size={12} style={{ color: palette.ochre }} />}
                <span className="text-[10px] font-mono w-4 shrink-0" style={{ color: palette.ochre || '#C8A97E' }}>{i + 1}</span>
                <span className="text-[10px] font-medium shrink-0 px-1.5 py-0.5 rounded"
                  style={{ color: palette.rust, background: 'rgba(155,90,62,0.1)' }}>
                  {PAGE_LABELS[page.type] || page.type}
                </span>
                <span className="text-[10px] truncate flex-1" style={{ color: palette.inkSoft || '#4A3F32' }}>
                  {firstText}
                </span>
                {page.tall && <span className="text-[9px] px-1 rounded" style={{ color: palette.ochre, background: 'rgba(200,169,126,0.15)' }}>tall</span>}
                {/* Action buttons */}
                <div className="flex items-center gap-0.5 ml-1" onClick={e => e.stopPropagation()}>
                  <button onClick={() => movePage(i, -1)} disabled={i === 0} className="p-0.5 rounded hover:bg-black/5 disabled:opacity-20" title="Move up">
                    <ArrowUp size={10} style={{ color: palette.inkSoft }} />
                  </button>
                  <button onClick={() => movePage(i, 1)} disabled={i === pages.length - 1} className="p-0.5 rounded hover:bg-black/5 disabled:opacity-20" title="Move down">
                    <ArrowDown size={10} style={{ color: palette.inkSoft }} />
                  </button>
                  <button onClick={() => duplicatePage(i)} className="p-0.5 rounded hover:bg-black/5" title="Duplicate">
                    <Copy size={10} style={{ color: palette.inkSoft }} />
                  </button>
                  <button onClick={() => removePage(i)} className="p-0.5 rounded hover:bg-red-100" title="Delete">
                    <Trash2 size={10} className="text-red-400" />
                  </button>
                </div>
              </div>

              {/* Expanded editor */}
              {isExpanded && (
                <div className="px-3 pb-3 pt-1 space-y-2 border-t" style={{ borderColor: 'rgba(0,0,0,0.06)' }}>
                  {/* Type + tall */}
                  <div className="flex gap-2 items-center">
                    <select value={page.type} onChange={e => updatePageType(i, e.target.value)}
                      className="select select-bordered select-xs text-[11px] flex-1"
                      style={{ background: '#fff', color: '#1a1a1a', borderColor: '#d1d5db' }}>
                      {PAGE_TYPES.map(t => <option key={t} value={t}>{PAGE_LABELS[t]}</option>)}
                    </select>
                    <label className="flex items-center gap-1 text-[10px]" style={{ color: palette.inkSoft }}>
                      <input type="checkbox" checked={page.tall ?? false}
                        onChange={e => updatePageField(i, 'tall', e.target.checked)}
                        className="checkbox checkbox-xs" />
                      Tall
                    </label>
                  </div>

                  {/* Type-specific fields */}
                  <PageFieldEditor page={page} idx={i} updatePageField={updatePageField} palette={palette} />
                </div>
              )}
            </div>
          );
        })}

        {pages.length === 0 && (
          <div className="text-center py-4">
            <p className="text-[11px]" style={{ color: palette.inkSoft }}>No pages yet</p>
            <button onClick={() => setShowAddMenu(true)}
              className="text-[11px] mt-1 px-3 py-1 rounded font-medium"
              style={{ background: 'rgba(155,90,62,0.15)', color: palette.rust }}>
              Add your first page
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

/* ── Per-type field editors ── */
const PageFieldEditor: React.FC<{
  page: any; idx: number;
  updatePageField: (idx: number, field: string, value: any) => void;
  palette: any;
}> = ({ page, idx, updatePageField, palette }) => {
  const d = page.data || {};
  const fieldStyle = { background: '#fff', color: '#1a1a1a', borderColor: '#d1d5db' } as React.CSSProperties;
  const inputCls = "input input-bordered input-xs w-full text-[11px]";
  const labelCls = "text-[10px] block mb-0.5";
  const labelStyle = { color: palette.inkSoft || '#4A3F32' };
  const textareaCls = "textarea textarea-bordered textarea-xs w-full text-[11px] min-h-[60px] leading-relaxed";

  switch (page.type) {
    case 'cover':
      return (
        <div className="space-y-1.5">
          <div><label className={labelCls} style={labelStyle}>Eyebrow</label>
            <input className={inputCls} style={fieldStyle} value={d.eyebrow || ''} onChange={e => updatePageField(idx, 'data.eyebrow', e.target.value)} placeholder="e.g. A magazine for what does not shout" /></div>
          <div><label className={labelCls} style={labelStyle}>Masthead</label>
            <input className={inputCls} style={fieldStyle} value={d.masthead || ''} onChange={e => updatePageField(idx, 'data.masthead', e.target.value)} placeholder="e.g. ENSŌDŌ" style={{ fontSize: '14px', fontWeight: 500 }} /></div>
          <div><label className={labelCls} style={labelStyle}>Masthead Meta</label>
            <input className={inputCls} style={fieldStyle} value={d.mastheadMeta || ''} onChange={e => updatePageField(idx, 'data.mastheadMeta', e.target.value)} placeholder="e.g. Issue No. 01" /></div>
          <div><label className={labelCls} style={labelStyle}>Divider</label>
            <input className={inputCls} style={fieldStyle} value={d.divider || ''} onChange={e => updatePageField(idx, 'data.divider', e.target.value)} placeholder="· · ·" /></div>
          <div><label className={labelCls} style={labelStyle}>Subtitle</label>
            <input className={inputCls} style={fieldStyle} value={d.subtitle || ''} onChange={e => updatePageField(idx, 'data.subtitle', e.target.value)} placeholder="e.g. The Signal Within" /></div>
          <div><label className={labelCls} style={labelStyle}>Hook</label>
            <input className={inputCls} style={fieldStyle} value={d.hook || ''} onChange={e => updatePageField(idx, 'data.hook', e.target.value)} placeholder="e.g. Come in quietly." /></div>
          <label className="flex items-center gap-1 text-[10px]" style={labelStyle}>
            <input type="checkbox" checked={d.showScrollHint ?? true}
              onChange={e => updatePageField(idx, 'data.showScrollHint', e.target.checked)}
              className="checkbox checkbox-xs" />
            Show scroll hint
          </label>
        </div>
      );

    case 'quote':
      return (
        <div className="space-y-1.5">
          <label className={labelCls} style={labelStyle}>Quote Lines</label>
          {(d.lines || []).map((line: any, li: number) => (
            <div key={li} className="flex gap-1 items-start">
              <textarea className={textareaCls} style={fieldStyle} value={line.text || ''}
                onChange={e => {
                  const lines = [...(d.lines || [])];
                  lines[li] = { ...lines[li], text: e.target.value };
                  updatePageField(idx, 'data.lines', lines);
                }}
                placeholder={`Line ${li + 1}`} />
              <button onClick={() => {
                const lines = (d.lines || []).filter((_: any, j: number) => j !== li);
                updatePageField(idx, 'data.lines', lines);
              }} className="p-1 rounded hover:bg-red-100 shrink-0 mt-1">
                <Trash2 size={10} className="text-red-400" />
              </button>
            </div>
          ))}
          <button onClick={() => updatePageField(idx, 'data.lines', [...(d.lines || []), { text: '', emphasis: [] }])}
            className="text-[10px] px-2 py-1 rounded" style={{ background: 'rgba(155,90,62,0.1)', color: palette.rust }}>
            + Add Line
          </button>
          <label className="flex items-center gap-1 text-[10px]" style={labelStyle}>
            <input type="checkbox" checked={d.showMark ?? false}
              onChange={e => updatePageField(idx, 'data.showMark', e.target.checked)} className="checkbox checkbox-xs" />
            Show mark (·)
          </label>
        </div>
      );

    case 'editorial_title':
      return (
        <div className="space-y-1.5">
          <div><label className={labelCls} style={labelStyle}>Chapter Label</label>
            <input className={inputCls} style={fieldStyle} value={d.chapterLabel || ''} onChange={e => updatePageField(idx, 'data.chapterLabel', e.target.value)} placeholder="e.g. Editorial" /></div>
          <div><label className={labelCls} style={labelStyle}>Chapter Title</label>
            <input className={inputCls} style={fieldStyle} value={d.chapterTitle || ''} onChange={e => updatePageField(idx, 'data.chapterTitle', e.target.value)} placeholder="e.g. The First Echo" style={{ fontSize: '14px', fontWeight: 500 }} /></div>
          <label className="flex items-center gap-1 text-[10px]" style={labelStyle}>
            <input type="checkbox" checked={d.showDotMark ?? false}
              onChange={e => updatePageField(idx, 'data.showDotMark', e.target.checked)} className="checkbox checkbox-xs" />
            Show dot mark
          </label>
        </div>
      );

    case 'editorial_body':
      return (
        <div className="space-y-1.5">
          <label className={labelCls} style={labelStyle}>Paragraphs</label>
          {(d.paragraphs || []).map((para: any, pi: number) => (
            <div key={pi} className="space-y-1 p-1.5 rounded" style={{ background: 'rgba(0,0,0,0.03)' }}>
              <div className="flex gap-1 items-start">
                <textarea className={textareaCls} style={fieldStyle} value={para.text || ''}
                  onChange={e => {
                    const paras = JSON.parse(JSON.stringify(d.paragraphs || []));
                    paras[pi].text = e.target.value;
                    updatePageField(idx, 'data.paragraphs', paras);
                  }}
                  placeholder={`Paragraph ${pi + 1}`} />
                <button onClick={() => {
                  const paras = (d.paragraphs || []).filter((_: any, j: number) => j !== pi);
                  updatePageField(idx, 'data.paragraphs', paras);
                }} className="p-1 rounded hover:bg-red-100 shrink-0 mt-1">
                  <Trash2 size={10} className="text-red-400" />
                </button>
              </div>
              <label className="flex items-center gap-1 text-[10px]" style={labelStyle}>
                <input type="checkbox" checked={para.isLead ?? false}
                  onChange={e => {
                    const paras = JSON.parse(JSON.stringify(d.paragraphs || []));
                    paras[pi].isLead = e.target.checked;
                    updatePageField(idx, 'data.paragraphs', paras);
                  }} className="checkbox checkbox-xs" />
                Lead paragraph (larger, italic)
              </label>
            </div>
          ))}
          <button onClick={() => updatePageField(idx, 'data.paragraphs', [...(d.paragraphs || []), { text: '', isLead: false, emphasis: [] }])}
            className="text-[10px] px-2 py-1 rounded" style={{ background: 'rgba(155,90,62,0.1)', color: palette.rust }}>
            + Add Paragraph
          </button>
          <div className="flex gap-3">
            <label className="flex items-center gap-1 text-[10px]" style={labelStyle}>
              <input type="checkbox" checked={d.centered ?? false}
                onChange={e => updatePageField(idx, 'data.centered', e.target.checked)} className="checkbox checkbox-xs" />
              Centered
            </label>
            <label className="flex items-center gap-1 text-[10px]" style={labelStyle}>
              <input type="checkbox" checked={d.showMark ?? false}
                onChange={e => updatePageField(idx, 'data.showMark', e.target.checked)} className="checkbox checkbox-xs" />
              Show mark
            </label>
          </div>
          <div>
            <label className={labelCls} style={labelStyle}>Max Width</label>
            <select value={d.maxWidth || 'reading'} onChange={e => updatePageField(idx, 'data.maxWidth', e.target.value)}
              className="select select-bordered select-xs text-[11px]"
              style={{ background: '#fff', color: '#1a1a1a', borderColor: '#d1d5db' }}>
              <option value="reading">Reading (narrow)</option>
              <option value="wide">Wide</option>
            </select>
          </div>
        </div>
      );

    case 'pull_quote':
      return (
        <div className="space-y-1.5">
          <div><label className={labelCls} style={labelStyle}>Quote Text</label>
            <textarea className={textareaCls} style={fieldStyle} value={d.text || ''}
              onChange={e => updatePageField(idx, 'data.text', e.target.value)}
              placeholder="Every sound begins in silence..." /></div>
          <label className="flex items-center gap-1 text-[10px]" style={labelStyle}>
            <input type="checkbox" checked={d.showLines ?? true}
              onChange={e => updatePageField(idx, 'data.showLines', e.target.checked)} className="checkbox checkbox-xs" />
            Show decorative lines
          </label>
        </div>
      );

    case 'closing':
      return (
        <div className="space-y-1.5">
          <div><label className={labelCls} style={labelStyle}>Closing Line</label>
            <input className={inputCls} style={fieldStyle} value={d.line || ''}
              onChange={e => updatePageField(idx, 'data.line', e.target.value)}
              placeholder="Stay with what does not shout." style={{ fontStyle: 'italic' }} /></div>
        </div>
      );

    case 'footer':
      return (
        <div className="space-y-1.5">
          <div><label className={labelCls} style={labelStyle}>Mark</label>
            <input className={inputCls} style={fieldStyle} value={d.mark || ''} onChange={e => updatePageField(idx, 'data.mark', e.target.value)} placeholder="· · ·" /></div>
          <label className={labelCls} style={labelStyle}>Lines</label>
          {(d.lines || []).map((line: any, li: number) => (
            <div key={li} className="flex gap-1 items-center">
              <input className={inputCls} style={fieldStyle} value={line.text || ''}
                onChange={e => {
                  const lines = [...(d.lines || [])];
                  lines[li] = { ...lines[li], text: e.target.value };
                  updatePageField(idx, 'data.lines', lines);
                }}
                placeholder={`Line ${li + 1}`} />
              <button onClick={() => {
                const lines = (d.lines || []).filter((_: any, j: number) => j !== li);
                updatePageField(idx, 'data.lines', lines);
              }} className="p-1 rounded hover:bg-red-100 shrink-0">
                <Trash2 size={10} className="text-red-400" />
              </button>
            </div>
          ))}
          <button onClick={() => updatePageField(idx, 'data.lines', [...(d.lines || []), { text: '', emphasis: [] }])}
            className="text-[10px] px-2 py-1 rounded" style={{ background: 'rgba(155,90,62,0.1)', color: palette.rust }}>
            + Add Line
          </button>
          <div><label className={labelCls} style={labelStyle}>Meta</label>
            <input className={inputCls} style={fieldStyle} value={d.meta || ''} onChange={e => updatePageField(idx, 'data.meta', e.target.value)} placeholder="e.g. © 2026 · Published quarterly" /></div>
        </div>
      );

    default:
      return <p className="text-[10px]" style={labelStyle}>Unknown page type: {page.type}</p>;
  }
};

function getPagePreviewText(page: any): string {
  const d = page.data || {};
  switch (page.type) {
    case 'cover': return d.masthead || d.eyebrow || '';
    case 'quote': return d.lines?.[0]?.text || '';
    case 'editorial_title': return d.chapterTitle || '';
    case 'editorial_body': return d.paragraphs?.[0]?.text || '';
    case 'pull_quote': return d.text || '';
    case 'closing': return d.line || '';
    case 'footer': return d.lines?.[0]?.text || '';
    default: return '';
  }
}
