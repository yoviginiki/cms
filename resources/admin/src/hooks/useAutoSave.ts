import { useEffect, useRef } from 'react';
import { useEditorStore } from '@/stores/editorStore';
import { api } from '@/lib/api';

const SNAPSHOT_INTERVAL = 5; // Create a draft snapshot every N saves

// Autosave delay is a per-browser preference (Site Settings → Editing).
// Value is milliseconds; 0 disables autosave (manual Save/Publish only).
export const AUTOSAVE_PREF_KEY = 'admin.autosaveIntervalMs';
export const AUTOSAVE_DEFAULT_MS = 300000; // 5 min — was a fixed 3s

export function getAutosaveIntervalMs(): number {
  try {
    const raw = localStorage.getItem(AUTOSAVE_PREF_KEY);
    if (raw == null) return AUTOSAVE_DEFAULT_MS;
    const n = parseInt(raw, 10);
    return Number.isFinite(n) && n >= 0 ? n : AUTOSAVE_DEFAULT_MS;
  } catch {
    return AUTOSAVE_DEFAULT_MS;
  }
}

/**
 * Auto-save blocks after a configurable idle delay when dirty (default 5 min,
 * set in Site Settings → General → Editor autosave; 0 disables it). A draft
 * version snapshot is
 * created every 5th save.
 */
export function useAutoSave(siteId: string, blockableType: 'pages' | 'posts' | 'templates', blockableId: string) {
  const isDirty = useEditorStore((s) => s.isDirty);
  const blocks = useEditorStore((s) => s.blocks);
  const rawHtml = useEditorStore((s) => s.rawHtml);
  const setDirty = useEditorStore((s) => s.setDirty);
  const setSaving = useEditorStore((s) => s.setSaving);
  const timerRef = useRef<ReturnType<typeof setTimeout>>(undefined);
  const saveCountRef = useRef(0);

  useEffect(() => {
    if (!isDirty) return;
    const intervalMs = getAutosaveIntervalMs();
    if (intervalMs <= 0) return; // autosave disabled — rely on manual Save

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
    }, intervalMs);

    return () => {
      if (timerRef.current) clearTimeout(timerRef.current);
    };
  }, [isDirty, blocks, rawHtml, siteId, blockableType, blockableId, setDirty, setSaving]);
}
