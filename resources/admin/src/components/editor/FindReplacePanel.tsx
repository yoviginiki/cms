import { useMemo, useState } from 'react';
import { Replace, X, Loader2 } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';
import { useToast } from '@/components/ui/Toast';

// Common token targets offered for "convert this color to a token" (P3 syntax).
const TOKEN_SUGGESTIONS = ['$color.accent', '$color.primary', '$color.text', '$color.heading', '$color.bg', '$color.border'];

/** A swatch preview for a value ($token → shows the resolved var, else the color). */
function swatch(v: string): string {
  if (v.startsWith('$')) return `var(--${v.slice(1).replace(/\./g, '-')})`;
  return v;
}

/**
 * Find & Replace design values (P4). Lists every distinct color used on the page
 * (with counts), and replaces one across every block — including converting a raw
 * color to a design token ($color.accent). Page-scoped + undo-tracked.
 */
export function FindReplacePanel({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { toast } = useToast();
  const blocks = useEditorStore((s) => s.blocks);
  const findStyleValues = useEditorStore((s) => s.findStyleValues);
  const replaceStyleValue = useEditorStore((s) => s.replaceStyleValue);

  const values = useMemo(() => findStyleValues(), [blocks, findStyleValues, open]);
  const [find, setFind] = useState('');
  const [replace, setReplace] = useState('');
  const [busy, setBusy] = useState(false);

  if (!open) return null;

  const doReplace = () => {
    if (!find.trim() || !replace.trim()) return;
    setBusy(true);
    const n = replaceStyleValue(find.trim(), replace.trim());
    setBusy(false);
    toast({ type: n > 0 ? 'success' : 'error', message: n > 0 ? `Replaced ${n} occurrence(s) of ${find}.` : `No occurrences of ${find} found.` });
    if (n > 0) { setFind(''); setReplace(''); }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="bg-base-100 border border-base-300 w-full max-w-lg shadow-xl flex flex-col max-h-[80vh]" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between px-4 py-3 border-b border-base-300">
          <div className="flex items-center gap-2"><Replace size={15} className="text-primary" /><h3 className="text-sm font-semibold">Find & Replace colors</h3></div>
          <button onClick={onClose} className="btn btn-ghost btn-xs btn-square"><X size={14} /></button>
        </div>

        <div className="p-4 space-y-4 overflow-y-auto">
          {/* colors in use */}
          <div>
            <label className="text-xs font-medium text-base-content/60 mb-1.5 block">Colors used on this page ({values.length})</label>
            {values.length === 0 ? (
              <p className="text-[12px] text-base-content/40">No colors found in the block styles yet.</p>
            ) : (
              <div className="flex flex-wrap gap-1.5">
                {values.map((v) => (
                  <button key={v.value} onClick={() => setFind(v.value)}
                    className={`flex items-center gap-1.5 pl-1 pr-2 py-1 border text-[11px] ${find === v.value ? 'border-primary bg-primary/5' : 'border-base-300 hover:border-primary/40'}`}>
                    <span className="w-4 h-4 border border-base-300 shrink-0" style={{ background: swatch(v.value) }} />
                    <span className="font-mono">{v.value}</span>
                    <span className="text-base-content/35">×{v.count}</span>
                  </button>
                ))}
              </div>
            )}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-xs font-medium text-base-content/60 mb-1 block">Find</label>
              <input value={find} onChange={(e) => setFind(e.target.value)} placeholder="#1a1a1a"
                className="input input-bordered input-sm w-full text-[12px] font-mono" />
            </div>
            <div>
              <label className="text-xs font-medium text-base-content/60 mb-1 block">Replace with</label>
              <div className="flex gap-1">
                <input value={replace} onChange={(e) => setReplace(e.target.value)} placeholder="$color.accent or #fff"
                  className="input input-bordered input-sm flex-1 text-[12px] font-mono" />
                <input type="color" value={/^#[0-9a-fA-F]{6}$/.test(replace) ? replace : '#000000'}
                  onChange={(e) => setReplace(e.target.value)} className="w-8 h-8 border border-base-300 cursor-pointer p-0" title="Pick a color" />
              </div>
            </div>
          </div>

          <div>
            <label className="text-xs font-medium text-base-content/60 mb-1 block">Convert to a token</label>
            <div className="flex flex-wrap gap-1">
              {TOKEN_SUGGESTIONS.map((t) => (
                <button key={t} onClick={() => setReplace(t)}
                  className={`flex items-center gap-1 pl-1 pr-1.5 py-0.5 border text-[10px] font-mono ${replace === t ? 'border-primary text-primary' : 'border-base-300 text-base-content/60 hover:border-primary/40'}`}>
                  <span className="w-3 h-3 border border-base-300" style={{ background: swatch(t) }} />{t}
                </button>
              ))}
            </div>
            <p className="text-[10px] text-base-content/40 mt-1">Tokens resolve to the theme at publish — restyle everywhere by editing the theme.</p>
          </div>
        </div>

        <div className="flex justify-end gap-2 px-4 py-3 border-t border-base-300">
          <button onClick={onClose} className="btn btn-ghost btn-sm">Close</button>
          <button onClick={doReplace} disabled={!find.trim() || !replace.trim() || busy} className="btn btn-primary btn-sm gap-1.5">
            {busy ? <Loader2 size={14} className="animate-spin" /> : <Replace size={14} />} Replace all
          </button>
        </div>
      </div>
    </div>
  );
}
