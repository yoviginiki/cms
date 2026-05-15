import { useEffect, useRef } from 'react';
import { useEditorStore } from '@/stores/editorStore';
import { api } from '@/lib/api';

/**
 * Auto-save blocks after 3s of inactivity when dirty.
 *
 * Safe because:
 * - PageEditor/PostEditor load blocks only once via blocksLoadedRef
 * - refetchOnWindowFocus is disabled for blocks query
 * - No external refetch can overwrite the editor store
 */
export function useAutoSave(siteId: string, blockableType: 'pages' | 'posts', blockableId: string) {
  const isDirty = useEditorStore((s) => s.isDirty);
  const blocks = useEditorStore((s) => s.blocks);
  const setDirty = useEditorStore((s) => s.setDirty);
  const setSaving = useEditorStore((s) => s.setSaving);
  const timerRef = useRef<ReturnType<typeof setTimeout>>(undefined);

  useEffect(() => {
    if (!isDirty) return;

    if (timerRef.current) clearTimeout(timerRef.current);

    timerRef.current = setTimeout(async () => {
      setSaving(true);
      try {
        await api.put(`/sites/${siteId}/${blockableType}/${blockableId}/blocks`, { blocks });
        setDirty(false);
      } catch (err) {
        console.error('Auto-save failed:', err);
      } finally {
        setSaving(false);
      }
    }, 3000);

    return () => {
      if (timerRef.current) clearTimeout(timerRef.current);
    };
  }, [isDirty, blocks, siteId, blockableType, blockableId, setDirty, setSaving]);
}
