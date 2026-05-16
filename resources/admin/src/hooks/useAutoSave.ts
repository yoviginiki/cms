import { useEffect, useRef } from 'react';
import { useEditorStore } from '@/stores/editorStore';
import { api } from '@/lib/api';

const SNAPSHOT_INTERVAL = 5; // Create a draft snapshot every N saves

/**
 * Auto-save blocks after 3s of inactivity when dirty.
 * Creates a draft version snapshot every 5th save.
 */
export function useAutoSave(siteId: string, blockableType: 'pages' | 'posts', blockableId: string) {
  const isDirty = useEditorStore((s) => s.isDirty);
  const blocks = useEditorStore((s) => s.blocks);
  const rawHtml = useEditorStore((s) => s.rawHtml);
  const setDirty = useEditorStore((s) => s.setDirty);
  const setSaving = useEditorStore((s) => s.setSaving);
  const timerRef = useRef<ReturnType<typeof setTimeout>>(undefined);
  const saveCountRef = useRef(0);

  useEffect(() => {
    if (!isDirty) return;

    if (timerRef.current) clearTimeout(timerRef.current);

    timerRef.current = setTimeout(async () => {
      setSaving(true);
      try {
        saveCountRef.current++;
        const createSnapshot = saveCountRef.current % SNAPSHOT_INTERVAL === 0;

        await api.put(`/sites/${siteId}/${blockableType}/${blockableId}/blocks`, {
          blocks,
          raw_html: rawHtml || '',
          create_snapshot: createSnapshot,
        });
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
  }, [isDirty, blocks, rawHtml, siteId, blockableType, blockableId, setDirty, setSaving]);
}
