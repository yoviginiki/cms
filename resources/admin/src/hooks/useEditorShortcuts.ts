import { useEffect } from 'react';
import { useEditorStore } from '@/stores/editorStore';
import { api } from '@/lib/api';

export function useEditorShortcuts(
  siteId: string,
  blockableType: 'pages' | 'posts' | 'templates',
  blockableId: string,
) {
  const undo = useEditorStore((s) => s.undo);
  const redo = useEditorStore((s) => s.redo);
  const removeBlock = useEditorStore((s) => s.removeBlock);
  const duplicateBlock = useEditorStore((s) => s.duplicateBlock);
  const selectBlock = useEditorStore((s) => s.selectBlock);
  const copyBlock = useEditorStore((s) => s.copyBlock);
  const pasteBlock = useEditorStore((s) => s.pasteBlock);
  const copyStyle = useEditorStore((s) => s.copyStyle);
  const pasteStyle = useEditorStore((s) => s.pasteStyle);

  useEffect(() => {
    function inEditable(t: HTMLElement) {
      return t.isContentEditable || t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT';
    }

    function handler(e: KeyboardEvent) {
      const state = useEditorStore.getState();
      const mod = e.ctrlKey || e.metaKey; // Ctrl (win/linux) or ⌘ (mac)

      // Ctrl/⌘+Z: undo
      if (mod && !e.shiftKey && (e.key === 'z' || e.key === 'Z')) {
        e.preventDefault();
        undo();
        return;
      }

      // Ctrl/⌘+Shift+Z: redo
      if (mod && e.shiftKey && (e.key === 'z' || e.key === 'Z')) {
        e.preventDefault();
        redo();
        return;
      }

      // Copy/Paste STYLE: Ctrl/⌘+Shift+C / +V (check before plain copy/paste)
      if (mod && e.shiftKey && (e.key === 'c' || e.key === 'C')) {
        if (state.selectedBlockId && !inEditable(e.target as HTMLElement)) {
          e.preventDefault();
          copyStyle(state.selectedBlockId);
        }
        return;
      }
      if (mod && e.shiftKey && (e.key === 'v' || e.key === 'V')) {
        if (state.selectedBlockId && !inEditable(e.target as HTMLElement)) {
          e.preventDefault();
          pasteStyle(state.selectedBlockId, 'all');
        }
        return;
      }

      // Copy/Paste BLOCK: Ctrl/⌘+C / +V (only when a block is selected and not
      // editing text / making a text selection — otherwise let the browser copy)
      if (mod && !e.shiftKey && (e.key === 'c' || e.key === 'C')) {
        const t = e.target as HTMLElement;
        if (state.selectedBlockId && !inEditable(t) && !window.getSelection()?.toString()) {
          e.preventDefault();
          copyBlock(state.selectedBlockId);
        }
        return;
      }
      if (mod && !e.shiftKey && (e.key === 'v' || e.key === 'V')) {
        const t = e.target as HTMLElement;
        if (state.clipboard && state.selectedBlockId && !inEditable(t)) {
          e.preventDefault();
          pasteBlock(state.selectedBlockId);
        }
        return;
      }

      // Ctrl/⌘+S: force save
      if (mod && (e.key === 's' || e.key === 'S')) {
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

      // Ctrl/⌘+D: duplicate
      if (mod && (e.key === 'd' || e.key === 'D')) {
        e.preventDefault();
        if (state.selectedBlockId) duplicateBlock(state.selectedBlockId);
        return;
      }

      // Delete/Backspace: remove selected (only if not in an input or contentEditable)
      if (
        (e.key === 'Delete' || e.key === 'Backspace') &&
        state.selectedBlockId
      ) {
        if (inEditable(e.target as HTMLElement)) return;
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
  }, [siteId, blockableType, blockableId, undo, redo, removeBlock, duplicateBlock, selectBlock, copyBlock, pasteBlock, copyStyle, pasteStyle]);
}
