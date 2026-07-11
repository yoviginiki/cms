import { useMemo, useRef } from 'react';
import { useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Palette, Trash2, Pencil, Download, Upload, Loader2, Star, Lock, Copy } from 'lucide-react';
import { stylePresets, type StylePreset } from '@/lib/api';
import { useToast } from '@/components/ui/Toast';

const apiErr = (e: any) => e?.response?.data?.error || e?.response?.data?.message || 'Something went wrong.';

/**
 * Style Presets manager (Builder Experience P3). Presets are authored from the
 * editor ("save current style as preset" in a block's settings); here you
 * rename, set the default per block type, delete, and export/import the whole
 * design system as JSON. Editing a preset restyles every block that links it.
 */
export default function StylePresetsList() {
  const { siteId = '' } = useParams();
  const qc = useQueryClient();
  const { toast } = useToast();
  const fileRef = useRef<HTMLInputElement>(null);

  const { data: items = [], isLoading } = useQuery<StylePreset[]>({
    queryKey: ['style-presets', siteId, 'all'],
    queryFn: () => stylePresets.list(siteId).then((r) => r.data.data),
  });

  const invalidate = () => qc.invalidateQueries({ queryKey: ['style-presets', siteId] });

  const update = useMutation({
    mutationFn: ({ id, body }: { id: string; body: Partial<StylePreset> }) => stylePresets.update(siteId, id, body),
    onSuccess: (r: any) => {
      invalidate();
      const n = r.data?.meta?.stale?.pages ?? 0;
      if (n > 0) toast({ type: 'success', message: `Updated — ${n} page(s) flagged for republish.` });
    },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const remove = useMutation({
    mutationFn: (id: string) => stylePresets.delete(siteId, id),
    onSuccess: () => { invalidate(); toast({ type: 'success', message: 'Deleted.' }); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const adopt = useMutation({
    mutationFn: (id: string) => stylePresets.adopt(siteId, id),
    onSuccess: () => { invalidate(); toast({ type: 'success', message: 'Copied to your presets — star it to set as default.' }); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });
  const importItems = useMutation({
    mutationFn: (presets: unknown[]) => stylePresets.import(siteId, presets),
    onSuccess: (r: any) => { invalidate(); toast({ type: 'success', message: `Imported ${r.data.data.imported} preset(s).` }); },
    onError: (e) => toast({ type: 'error', message: apiErr(e) }),
  });

  const grouped = useMemo(() => {
    const m = new Map<string, StylePreset[]>();
    for (const p of items) {
      if (!m.has(p.block_type)) m.set(p.block_type, []);
      m.get(p.block_type)!.push(p);
    }
    return Array.from(m.entries()).sort(([a], [b]) => a.localeCompare(b));
  }, [items]);

  const doExport = async () => {
    const doc = (await stylePresets.export(siteId)).data.data;
    const blob = new Blob([JSON.stringify(doc, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'style-presets.json'; a.click();
    URL.revokeObjectURL(url);
  };
  const onFile = async (file: File) => {
    try {
      const parsed = JSON.parse(await file.text());
      const presets = Array.isArray(parsed) ? parsed : parsed.presets;
      if (!Array.isArray(presets)) { toast({ type: 'error', message: 'Not a presets export (no presets array).' }); return; }
      importItems.mutate(presets);
    } catch { toast({ type: 'error', message: 'Could not read that file as JSON.' }); }
  };

  return (
    <div className="max-w-4xl mx-auto py-8 px-2">
      <div className="flex items-center justify-between mb-1">
        <div className="flex items-center gap-2">
          <Palette className="h-6 w-6 text-primary" />
          <h1 className="text-2xl font-bold text-base-content">Style Presets</h1>
        </div>
        <div className="flex gap-2">
          <button onClick={doExport} className="btn btn-ghost btn-sm gap-1.5"><Download size={14} /> Export</button>
          <input ref={fileRef} type="file" accept="application/json,.json" className="hidden"
            onChange={(e) => { const f = e.target.files?.[0]; if (f) onFile(f); e.target.value = ''; }} />
          <button onClick={() => fileRef.current?.click()} disabled={importItems.isPending} className="btn btn-outline btn-sm gap-1.5">
            {importItems.isPending ? <Loader2 size={14} className="animate-spin" /> : <Upload size={14} />} Import
          </button>
        </div>
      </div>
      <p className="text-sm text-base-content/50 mb-6">
        Named style bundles blocks link to. Author them from a block’s settings (“Save current style as preset”);
        editing one restyles every block that uses it. Values can reference tokens like <code className="text-primary">$color.accent</code>.
      </p>

      {isLoading && <div className="flex items-center gap-2 text-sm text-base-content/50 py-12 justify-center"><Loader2 className="h-4 w-4 animate-spin" /> Loading…</div>}

      {!isLoading && items.length === 0 && (
        <div className="text-center py-16 text-base-content/50">
          <Palette className="h-10 w-10 mx-auto mb-3 opacity-30" />
          <p className="text-sm">No presets yet. Open a block’s settings and “Save current style as preset”.</p>
        </div>
      )}

      {grouped.map(([blockType, presets]) => (
        <div key={blockType} className="mb-5">
          <h2 className="text-[11px] uppercase tracking-wider text-base-content/40 mb-1.5">{blockType === '*' ? 'Any block' : blockType}</h2>
          <div className="border border-base-300 divide-y divide-base-300 bg-base-100">
            {presets.map((p) => (
              <div key={p.id} className="flex items-center gap-3 px-4 py-2.5">
                <button
                  disabled={p.is_system}
                  onClick={() => update.mutate({ id: p.id, body: { is_default: !p.is_default } })}
                  className={`btn btn-ghost btn-xs btn-square ${p.is_default ? 'text-amber-500' : 'text-base-content/25'}`}
                  title={p.is_system ? 'System presets can’t be a default — copy it to your presets first' : p.is_default ? 'Default for this block type' : 'Set as default'}>
                  <Star size={13} fill={p.is_default ? 'currentColor' : 'none'} />
                </button>
                <div className="min-w-0 flex-1">
                  <div className="text-[13px] font-medium text-base-content truncate">{p.name}</div>
                  <div className="flex items-center gap-1.5 mt-0.5">
                    <span className="badge badge-outline badge-xs">{p.kind}{p.group ? `·${p.group}` : ''}</span>
                    {p.is_system && <span className="badge badge-ghost badge-xs gap-1"><Lock size={9} /> system</span>}
                  </div>
                </div>
                {p.is_system ? (
                  <button onClick={() => adopt.mutate(p.id)} disabled={adopt.isPending}
                    className="btn btn-ghost btn-xs btn-square" title="Copy to your presets (so you can edit it or set it as default)"><Copy size={12} /></button>
                ) : (
                  <>
                    <button onClick={() => { const n = prompt('Rename preset:', p.name); if (n?.trim()) update.mutate({ id: p.id, body: { name: n.trim() } }); }}
                      className="btn btn-ghost btn-xs btn-square" title="Rename"><Pencil size={12} /></button>
                    <button onClick={() => { if (confirm(`Delete “${p.name}”? Blocks using it revert to local styles.`)) remove.mutate(p.id); }}
                      className="btn btn-ghost btn-xs btn-square text-error hover:bg-error/10" title="Delete"><Trash2 size={12} /></button>
                  </>
                )}
              </div>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
