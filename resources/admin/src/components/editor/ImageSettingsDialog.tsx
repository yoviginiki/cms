import { useEffect, useState } from 'react';
import { X, Upload } from 'lucide-react';
import { AssetPicker } from '@/components/ui/AssetPicker';
import type { RichImageAttrs, RichImageAlign } from './extensions/RichImage';

interface ImageSettingsDialogProps {
  open: boolean;
  initial: RichImageAttrs;
  isNew: boolean;
  onSave: (attrs: RichImageAttrs) => void;
  onClose: () => void;
}

const ALIGN_OPTIONS: { value: RichImageAlign; label: string }[] = [
  { value: 'center', label: 'Center' },
  { value: 'left', label: 'Left' },
  { value: 'right', label: 'Right' },
  { value: 'full', label: 'Full width' },
  { value: 'float-left', label: 'Float left (text wraps)' },
  { value: 'float-right', label: 'Float right (text wraps)' },
];

// Width presets in px (capped at 100% of the column by CSS). Legacy "%" values
// and any other px value show up as "Custom" with the number editable.
const WIDTH_PRESETS: { value: string; label: string }[] = [
  { value: '', label: 'Original (auto)' },
  { value: '240px', label: 'S — 240 px' },
  { value: '480px', label: 'M — 480 px' },
  { value: '720px', label: 'L — 720 px' },
  { value: '100%', label: 'XL — full width' },
];
const CUSTOM = '__custom__';

function widthPreset(width: string | null | undefined): string {
  if (!width) return '';
  return WIDTH_PRESETS.some(o => o.value === width) ? width : CUSTOM;
}
function widthPx(width: string | null | undefined): string {
  const m = /^(\d+)px$/.exec(width || '');
  return m ? m[1] : '';
}

/**
 * Settings sheet for an image inside rich text: swap the file via the media
 * library, alt text, caption, link, alignment and width. Used both right after
 * picking a new image and when editing an existing one (toolbar / double-click).
 */
export function ImageSettingsDialog({ open, initial, isNew, onSave, onClose }: ImageSettingsDialogProps) {
  const [attrs, setAttrs] = useState<RichImageAttrs>(initial);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [customPx, setCustomPx] = useState('');
  const [sizeMode, setSizeMode] = useState<string>('');

  useEffect(() => {
    if (!open) return;
    setAttrs(initial);
    setSizeMode(widthPreset(initial.width));
    setCustomPx(widthPx(initial.width));
  }, [open, initial]);

  if (!open) return null;

  const set = <K extends keyof RichImageAttrs>(key: K, value: RichImageAttrs[K]) =>
    setAttrs(prev => ({ ...prev, [key]: value }));

  const submit = () => {
    if (!attrs.src) return;
    onSave({
      src: attrs.src,
      alt: (attrs.alt || '').trim(),
      title: attrs.title || null,
      caption: (attrs.caption || '').trim(),
      href: (attrs.href || '').trim() || null,
      target: (attrs.href || '').trim() && attrs.target === '_blank' ? '_blank' : null,
      align: attrs.align || 'center',
      width: attrs.width || null,
    });
  };

  return (
    <dialog className="modal modal-open" onClick={onClose}>
      <div className="modal-box bg-base-100 max-w-lg" onClick={e => e.stopPropagation()}
        onKeyDown={e => { if (e.key === 'Enter' && (e.target as HTMLElement).tagName !== 'TEXTAREA') { e.preventDefault(); submit(); } }}>
        <div className="flex items-center justify-between mb-3">
          <h3 className="text-sm font-medium text-base-content/80">{isNew ? 'Insert image' : 'Image settings'}</h3>
          <button type="button" onClick={onClose} className="btn btn-ghost btn-xs btn-square"><X size={14} /></button>
        </div>

        {/* Preview + change */}
        <div className="flex gap-3 mb-4">
          <div className="w-28 h-28 shrink-0 rounded border border-base-300/30 bg-base-200/50 overflow-hidden flex items-center justify-center">
            {attrs.src
              ? <img src={attrs.src} alt="" className="w-full h-full object-contain" />
              : <span className="text-[11px] text-base-content/30">No image</span>}
          </div>
          <div className="flex-1 min-w-0 flex flex-col gap-1.5">
            <button type="button" onClick={() => setPickerOpen(true)} className="btn btn-outline btn-sm text-[12px] gap-1.5">
              <Upload size={12} /> {attrs.src ? 'Change image' : 'Choose image'}
            </button>
            <input value={attrs.src || ''} onChange={e => set('src', e.target.value)}
              className="input input-bordered input-xs w-full text-[10px] font-mono" placeholder="Image URL" />
          </div>
        </div>

        <div className="space-y-3">
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Alt text <span className="text-base-content/30">(accessibility &amp; SEO)</span></label>
            <input type="text" value={attrs.alt || ''} onChange={e => set('alt', e.target.value)}
              className="input input-bordered input-sm w-full text-[12px]" placeholder="Describe the image" autoFocus />
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Caption</label>
            <textarea value={attrs.caption || ''} onChange={e => set('caption', e.target.value)} rows={2}
              className="textarea textarea-bordered textarea-sm w-full text-[12px]" placeholder="Shown under the image (optional)" />
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Link URL</label>
            <div className="flex gap-2 items-center">
              <input type="text" value={attrs.href || ''} onChange={e => set('href', e.target.value)}
                className="input input-bordered input-sm flex-1 text-[12px]" placeholder="https://… or /page/ (optional)" />
              <label className="flex items-center gap-1.5 text-[11px] text-base-content/60 whitespace-nowrap">
                <input type="checkbox" className="checkbox checkbox-xs" checked={attrs.target === '_blank'}
                  onChange={e => set('target', e.target.checked ? '_blank' : null)} />
                New tab
              </label>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Alignment</label>
              <select value={attrs.align || 'center'} onChange={e => set('align', e.target.value as RichImageAlign)}
                className="select select-bordered select-sm w-full text-[12px]">
                {ALIGN_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
              </select>
            </div>
            <div>
              <label className="text-[11px] text-base-content/50 mb-1 block">Size</label>
              <select value={sizeMode}
                onChange={e => {
                  const v = e.target.value;
                  setSizeMode(v);
                  if (v === CUSTOM) {
                    const px = customPx || widthPx(attrs.width) || '400';
                    setCustomPx(px);
                    set('width', `${px}px`);
                  } else {
                    set('width', v || null);
                  }
                }}
                className="select select-bordered select-sm w-full text-[12px]">
                {WIDTH_PRESETS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                <option value={CUSTOM}>Custom (px)</option>
              </select>
              {sizeMode === CUSTOM && (
                <div className="flex items-center gap-1.5 mt-1.5">
                  <input type="number" min={20} max={4000} step={10} value={customPx}
                    onChange={e => {
                      const px = e.target.value.replace(/\D/g, '');
                      setCustomPx(px);
                      set('width', px ? `${px}px` : null);
                    }}
                    className="input input-bordered input-sm w-full text-[12px]" placeholder="e.g. 400" />
                  <span className="text-[11px] text-base-content/50">px</span>
                </div>
              )}
              {sizeMode === CUSTOM && attrs.width && !/px$/.test(attrs.width) && (
                <p className="text-[10px] text-base-content/40 mt-1">Current: {attrs.width}</p>
              )}
            </div>
          </div>
          <div>
            <label className="text-[11px] text-base-content/50 mb-1 block">Title <span className="text-base-content/30">(tooltip, optional)</span></label>
            <input type="text" value={attrs.title || ''} onChange={e => set('title', e.target.value || null)}
              className="input input-bordered input-sm w-full text-[12px]" />
          </div>
        </div>

        <div className="flex items-center justify-end gap-2 pt-4 mt-4 border-t border-base-300/20">
          <button type="button" onClick={onClose} className="btn btn-ghost btn-sm text-[12px]">Cancel</button>
          <button type="button" onClick={submit} disabled={!attrs.src} className="btn btn-primary btn-sm text-[12px]">
            {isNew ? 'Insert' : 'Apply'}
          </button>
        </div>
      </div>
      <form method="dialog" className="modal-backdrop"><button type="button" onClick={onClose}>close</button></form>

      {/* Nested picker: stop clicks bubbling to this dialog's backdrop close handler */}
      <div onClick={e => e.stopPropagation()}>
        <AssetPicker
          open={pickerOpen}
          onClose={() => setPickerOpen(false)}
          onSelect={(asset) => { set('src', asset.url); setPickerOpen(false); }}
          accept="image"
          currentUrl={attrs.src}
        />
      </div>
    </dialog>
  );
}
