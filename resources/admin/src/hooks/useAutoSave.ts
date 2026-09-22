import { useEffect, useRef } from 'react';
import { useEditorStore } from '@/stores/editorStore';
import { saveContent, type ContentType } from '@/lib/saveCoordinator';

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
 * Auto-save content after a configurable idle delay when dirty (default 5 min,
 * set in Site Settings → General → Editor autosave; 0 disables it). A draft
 * version snapshot is created every 5th save.
 *
 * F11: goes through the save coordinator — same serializer as Save (canvas
 * tree in canvas mode, raw_html for pages), same revision handling, and the
 * dirty flag is only cleared when nothing changed while the request was out.
 */
export function useAutoSave(siteId: string, blockableType: ContentType, blockableId: string) {
  const isDirty = useEditorStore((s) => s.isDirty);
  const blocks = useEditorStore((s) => s.blocks);
  const rawHtml = useEditorStore((s) => s.rawHtml);
  const conflict = useEditorStore((s) => s.conflict);
  const timerRef = useRef<ReturnType<typeof setTimeout>>(undefined);
  const saveCountRef = useRef(0);

  useEffect(() => {
    if (!isDirty || conflict) return; // a conflict needs a human decision, not a retry loop
    const intervalMs = getAutosaveIntervalMs();
    if (intervalMs <= 0) return; // autosave disabled — rely on manual Save

    if (timerRef.current) clearTimeout(timerRef.current);

    timerRef.current = setTimeout(async () => {
      saveCountRef.current++;
      try {
        await saveContent(
          { siteId, type: blockableType, id: blockableId },
          { createSnapshot: saveCountRef.current % SNAPSHOT_INTERVAL === 0 },
        );
      } catch (err) {
        console.error('Auto-save failed:', err);
      }
    }, intervalMs);

    return () => {
      if (timerRef.current) clearTimeout(timerRef.current);
    };
  }, [isDirty, conflict, blocks, rawHtml, siteId, blockableType, blockableId]);
}
