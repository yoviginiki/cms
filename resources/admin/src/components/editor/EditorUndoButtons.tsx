import { Undo2, Redo2 } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';

/** Visible Undo / Redo for the block editor (Ctrl+Z / Ctrl+Shift+Z work too). */
export function EditorUndoButtons() {
  const canUndo = useEditorStore(s => s.undoStack.length > 0);
  const canRedo = useEditorStore(s => s.redoStack.length > 0);
  const undo = useEditorStore(s => s.undo);
  const redo = useEditorStore(s => s.redo);
  return (
    <div className="flex items-center gap-0.5" data-testid="editor-undo">
      <button type="button" onClick={undo} disabled={!canUndo} title="Undo (Ctrl+Z)" aria-label="Undo"
        className="btn btn-ghost btn-sm btn-square disabled:opacity-20"><Undo2 size={15} /></button>
      <button type="button" onClick={redo} disabled={!canRedo} title="Redo (Ctrl+Shift+Z)" aria-label="Redo"
        className="btn btn-ghost btn-sm btn-square disabled:opacity-20"><Redo2 size={15} /></button>
    </div>
  );
}
