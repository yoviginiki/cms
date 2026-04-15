import { useEffect, useRef } from 'react';
import { useEditorStore } from '@/stores/editorStore';
import { api } from '@/lib/api';

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
    }, 2000);

    return () => {
      if (timerRef.current) clearTimeout(timerRef.current);
    };
  }, [isDirty, blocks, siteId, blockableType, blockableId, setDirty, setSaving]);
}
