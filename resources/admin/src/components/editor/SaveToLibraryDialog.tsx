import { useState } from 'react';
import { BookMarked, Loader2, X } from 'lucide-react';
import { library, LIBRARY_KINDS } from '@/lib/api';
import { useToast } from '@/components/ui/Toast';
import type { BlockData } from '@/types/blocks';

/**
 * Save the current block selection to the Library (Builder Experience P1).
 * Collects name / kind / category / tags instead of the old bare prompt().
 * The block tree is saved as a detached copy — no reference back to the source.
 */
export function SaveToLibraryDialog({
  open, onClose, siteId, blocks, defaultName = '',
}: {
  open: boolean;
  onClose: () => void;
  siteId: string;
  blocks: BlockData[];
  defaultName?: string;
}) {
  const { toast } = useToast();
  const [name, setName] = useState(defaultName);
  const [kind, setKind] = useState<string>(inferKind(blocks));
  const [category, setCategory] = useState('custom');
  const [tagsText, setTagsText] = useState('');
  const [saving, setSaving] = useState(false);

  if (!open) return null;

  const submit = async () => {
    const trimmed = name.trim();
    if (!trimmed || saving) return;
    setSaving(true);
    try {
      const tags = tagsText.split(',').map((t) => t.trim()).filter(Boolean).slice(0, 12);
      await library.save(siteId, {
        name: trimmed,
        kind,
        category: category.trim() || 'custom',
        tags,
        blocks_data: blocks,
      });
      toast({ type: 'success', message: `Saved “${trimmed}” to the Library.` });
      onClose();
    } catch (e: any) {
      toast({ type: 'error', message: e?.response?.data?.error || e?.response?.data?.message || 'Could not save to the Library.' });
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="bg-base-100 border border-base-300 w-full max-w-md shadow-xl" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between px-4 py-3 border-b border-base-300">
          <div className="flex items-center gap-2">
            <BookMarked size={15} className="text-primary" />
            <h3 className="text-sm font-semibold text-base-content">Save to Library</h3>
          </div>
          <button onClick={onClose} className="btn btn-ghost btn-xs btn-square"><X size={14} /></button>
        </div>

        <div className="p-4 space-y-3">
          <div>
            <label className="text-xs font-medium text-base-content/60 mb-1 block">Name</label>
            <input autoFocus value={name} onChange={(e) => setName(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && submit()}
              placeholder="e.g. Dark hero with CTA"
              className="input input-bordered input-sm w-full text-[13px]" />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-xs font-medium text-base-content/60 mb-1 block">Kind</label>
              <select value={kind} onChange={(e) => setKind(e.target.value)} className="select select-bordered select-sm w-full text-[13px]">
                {LIBRARY_KINDS.map((k) => <option key={k} value={k}>{k}</option>)}
              </select>
            </div>
            <div>
              <label className="text-xs font-medium text-base-content/60 mb-1 block">Category</label>
              <input value={category} onChange={(e) => setCategory(e.target.value)}
                placeholder="heroes, cta, footers…" className="input input-bordered input-sm w-full text-[13px]" />
            </div>
          </div>

          <div>
            <label className="text-xs font-medium text-base-content/60 mb-1 block">Tags <span className="opacity-50">(comma-separated)</span></label>
            <input value={tagsText} onChange={(e) => setTagsText(e.target.value)}
              placeholder="marketing, dark, wide" className="input input-bordered input-sm w-full text-[13px]" />
          </div>
        </div>

        <div className="flex justify-end gap-2 px-4 py-3 border-t border-base-300">
          <button onClick={onClose} className="btn btn-ghost btn-sm">Cancel</button>
          <button onClick={submit} disabled={!name.trim() || saving} className="btn btn-primary btn-sm gap-1.5">
            {saving ? <Loader2 size={14} className="animate-spin" /> : <BookMarked size={14} />}
            Save
          </button>
        </div>
      </div>
    </div>
  );
}

function inferKind(blocks: BlockData[]): string {
  if (blocks.length === 1) {
    const b = blocks[0] as any;
    if (b.level === 'section' || b.level === 'row') return b.level;
    return b.children?.length ? 'block-composition' : 'module';
  }
  return 'block-composition';
}
