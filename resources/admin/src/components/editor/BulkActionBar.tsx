import { CopyPlus, Trash2, ClipboardPaste, X } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';

/**
 * Floating bulk-action bar (P4 multi-select). Appears when 2+ blocks are
 * selected (shift/⌘-click on the canvas). Acts on the whole selection.
 */
export function BulkActionBar() {
  const selectedBlockIds = useEditorStore((s) => s.selectedBlockIds);
  const styleClipboard = useEditorStore((s) => s.styleClipboard);
  const duplicateSelected = useEditorStore((s) => s.duplicateSelected);
  const removeSelected = useEditorStore((s) => s.removeSelected);
  const pasteStyleToSelected = useEditorStore((s) => s.pasteStyleToSelected);
  const clearSelection = useEditorStore((s) => s.clearSelection);

  const count = selectedBlockIds.length;
  if (count < 2) return null;

  return (
    <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 flex items-center gap-1 bg-base-100 border border-base-300 shadow-xl px-2 py-1.5 text-[12px]">
      <span className="px-2 font-medium text-base-content/70">{count} selected</span>
      <div className="w-px h-5 bg-base-300" />
      <button onClick={duplicateSelected} className="btn btn-ghost btn-xs gap-1"><CopyPlus size={13} /> Duplicate</button>
      <button onClick={() => pasteStyleToSelected('all')} disabled={!styleClipboard}
        className="btn btn-ghost btn-xs gap-1 disabled:opacity-40" title={styleClipboard ? 'Paste copied style to all selected' : 'Copy a block style first'}>
        <ClipboardPaste size={13} /> Paste style
      </button>
      <button onClick={() => { if (confirm(`Delete ${count} blocks?`)) removeSelected(); }}
        className="btn btn-ghost btn-xs gap-1 text-error hover:bg-error/10"><Trash2 size={13} /> Delete</button>
      <div className="w-px h-5 bg-base-300" />
      <button onClick={clearSelection} className="btn btn-ghost btn-xs btn-square" title="Clear selection"><X size={13} /></button>
    </div>
  );
}
