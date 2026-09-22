import { useEffect } from 'react';
import { useEditorStore } from '@/stores/editorStore';
import { saveContent, type ContentType } from '@/lib/saveCoordinator';

/**
 * @param onSave  F11: Ctrl/⌘+S must do exactly what the Save button does.
 *                Editors pass their handleSave; without it the content-only
 *                coordinator save is used (same serializer/revision rules).
 */
export function useEditorShortcuts(
  siteId: string,
  blockableType: ContentType,
  blockableId: string,
  onSave?: () => void | Promise<unknown>,
) {
  const undo = useEditorStore((s) => s.undo);
  const redo = useEditorStore((s) => s.redo);
  const selectBlock = useEditorStore((s) => s.selectBlock);
  const copyBlock = useEditorStore((s) => s.copyBlock);
  const pasteBlock = useEditorStore((s) => s.pasteBlock);
  const copyStyle = useEditorStore((s) => s.copyStyle);
  const removeSelected = useEditorStore((s) => s.removeSelected);
  const duplicateSelected = useEditorStore((s) => s.duplicateSelected);
  const pasteStyleToSelected = useEditorStore((s) => s.pasteStyleToSelected);

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
          pasteStyleToSelected('all'); // all selected when multi, else the one
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

      // Ctrl/⌘+S: save — the SAME action as the Save button (F11)
      if (mod && (e.key === 's' || e.key === 'S')) {
        e.preventDefault();
        if (state.isDirty && !state.isSaving) {
          const p = onSave ? onSave() : saveContent({ siteId, type: blockableType, id: blockableId });
          Promise.resolve(p).catch((err) => console.error('Save failed:', err));
        }
        return;
      }

      // Ctrl/⌘+D: duplicate (all selected when multi, else the one)
      if (mod && (e.key === 'd' || e.key === 'D')) {
        e.preventDefault();
        if (state.selectedBlockId) duplicateSelected();
        return;
      }

      // Delete/Backspace: remove selection (only if not in an input or contentEditable)
      if (
        (e.key === 'Delete' || e.key === 'Backspace') &&
        state.selectedBlockId
      ) {
        if (inEditable(e.target as HTMLElement)) return;
        e.preventDefault();
        removeSelected();
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
  }, [siteId, blockableType, blockableId, onSave, undo, redo, selectBlock, copyBlock, pasteBlock, copyStyle, removeSelected, duplicateSelected, pasteStyleToSelected]);
}
