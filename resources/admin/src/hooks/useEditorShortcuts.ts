import { useEffect } from 'react';
import { useEditorStore } from '@/stores/editorStore';
import { api } from '@/lib/api';

export function useEditorShortcuts(
  siteId: string,
  blockableType: 'pages' | 'posts',
  blockableId: string,
) {
  const undo = useEditorStore((s) => s.undo);
  const redo = useEditorStore((s) => s.redo);
  const removeBlock = useEditorStore((s) => s.removeBlock);
  const duplicateBlock = useEditorStore((s) => s.duplicateBlock);
  const selectBlock = useEditorStore((s) => s.selectBlock);

  useEffect(() => {
    function handler(e: KeyboardEvent) {
      const state = useEditorStore.getState();

      // Ctrl+Z: undo
      if (e.ctrlKey && !e.shiftKey && e.key === 'z') {
        e.preventDefault();
        undo();
        return;
      }

      // Ctrl+Shift+Z: redo
      if (e.ctrlKey && e.shiftKey && e.key === 'Z') {
        e.preventDefault();
        redo();
        return;
      }

      // Ctrl+S: force save
      if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        if (state.isDirty) {
          useEditorStore.getState().setSaving(true);
          api
            .put(`/sites/${siteId}/${blockableType}/${blockableId}/blocks`, {
              blocks: state.blocks,
            })
            .then(() => {
              useEditorStore.getState().setDirty(false);
            })
            .finally(() => {
              useEditorStore.getState().setSaving(false);
            });
        }
        return;
      }

      // Ctrl+D: duplicate
      if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        if (state.selectedBlockId) duplicateBlock(state.selectedBlockId);
        return;
      }

      // Delete/Backspace: remove selected (only if not in an input or contentEditable)
      if (
        (e.key === 'Delete' || e.key === 'Backspace') &&
        state.selectedBlockId
      ) {
        const target = e.target as HTMLElement;
        if (target.isContentEditable || target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT') return;
        e.preventDefault();
        removeBlock(state.selectedBlockId);
        return;
      }

      // Escape: deselect
      if (e.key === 'Escape') {
        selectBlock(null);
        return;
      }
    }

    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [siteId, blockableType, blockableId, undo, redo, removeBlock, duplicateBlock, selectBlock]);
}
