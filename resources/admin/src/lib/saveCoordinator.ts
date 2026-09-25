/**
 * Save coordinator — the ONE way editor content reaches the server
 * (audit 2026-09-22, F11/F13).
 *
 * Before this, Save, autosave, Ctrl+S and the canvas collab leader each
 * built their own request from whichever store they happened to read, and
 * every one of them cleared the dirty flag when its response came back —
 * even if a newer edit had been made in the meantime, even if the response
 * belonged to a page that was no longer open, even if it was for the wrong
 * builder's tree. This module:
 *
 *  - serializes according to the session's builder (canvas tree vs block
 *    tree) and sends raw_html only for pages, from the hydrated store;
 *  - refuses to save before the session is hydrated (F12);
 *  - sends the server content revision (expected_version) and stores the
 *    new one from the response; a 409 marks the session as conflicted and
 *    keeps the local edit;
 *  - runs saves one at a time and coalesces calls made while one is in
 *    flight into a single follow-up save;
 *  - clears "dirty" only when the state that was sent is still the current
 *    state (reference identity), and ignores responses for a session that
 *    is no longer open.
 */
import { useEditorStore } from '@/stores/editorStore';
import { useCanvasStore } from '@/stores/canvasStore';
import { blocks as blocksApi } from '@/lib/api';

export type ContentType = 'pages' | 'posts' | 'templates' | 'global-sections';

export interface SaveTarget {
  siteId: string;
  type: ContentType;
  id: string;
}

export interface SaveOptions {
  /** Ask the server to snapshot a draft version with this save. */
  createSnapshot?: boolean;
}

export type SaveOutcome = 'saved' | 'skipped' | 'conflict' | 'error';

export interface SaveResult {
  outcome: SaveOutcome;
  version?: string | null;
  error?: unknown;
}

export class SaveConflictError extends Error {
  constructor(public readonly currentVersion: string | null) {
    super('These blocks were modified by someone else since you loaded them. Reload to get the latest version.');
    this.name = 'SaveConflictError';
  }
}

export function sessionKeyFor(target: SaveTarget): string {
  return `${target.siteId}/${target.type}/${target.id}`;
}

let inFlight: Promise<SaveResult> | null = null;
let queued: Promise<SaveResult> | null = null;

/**
 * Save the current editor session's content. When nothing is in flight the
 * request starts synchronously (the state snapshot is taken at call time).
 * Calls made while a save is in flight share ONE follow-up save (coalesced)
 * that starts after it finishes.
 */
export function saveContent(target: SaveTarget, opts: SaveOptions = {}): Promise<SaveResult> {
  if (inFlight) {
    if (!queued) {
      queued = inFlight.catch(() => undefined).then(() => {
        queued = null;
        return start(target, opts);
      });
    }
    return queued;
  }
  return start(target, opts);
}

function start(target: SaveTarget, opts: SaveOptions): Promise<SaveResult> {
  const p = performSave(target, opts);
  inFlight = p;
  p.catch(() => undefined).finally(() => {
    if (inFlight === p) inFlight = null;
  });
  return p;
}

async function performSave(target: SaveTarget, opts: SaveOptions): Promise<SaveResult> {
  const key = sessionKeyFor(target);
  const store = useEditorStore.getState();
  if (store.sessionKey !== key || !store.hydrated) {
    return { outcome: 'skipped' };
  }

  const canvas = store.editorMode === 'canvas';
  const canvasState = useCanvasStore.getState();

  // Snapshot what we send — by reference, so a later edit is detectable.
  const blocksRef = store.blocks;
  const sectionsRef = canvasState.sections;
  const rawHtmlRef = store.rawHtml;
  const expected = store.serverVersion;
  const payload = canvas ? canvasState.toBlocks() : store.blocks;
  const rawHtml = target.type === 'pages' ? rawHtmlRef : undefined;

  store.setSaving(true);
  try {
    const res = await blocksApi.sync(target.siteId, target.type, target.id, payload, rawHtml, {
      expected_version: expected,
      ...(opts.createSnapshot ? { create_snapshot: true } : {}),
    });

    const now = useEditorStore.getState();
    if (now.sessionKey !== key) {
      // The editor moved on to other content — never touch its state.
      return { outcome: 'saved', version: res.data?.version ?? null };
    }
    const version = (res.data?.version ?? null) as string | null;
    now.setServerVersion(version);
    now.setConflict(false);

    const unchanged = canvas
      ? useCanvasStore.getState().sections === sectionsRef && now.blocks === blocksRef && now.rawHtml === rawHtmlRef
      : now.blocks === blocksRef && now.rawHtml === rawHtmlRef;
    if (unchanged) {
      now.setDirty(false);
      if (canvas) useCanvasStore.getState().markClean();
    }
    return { outcome: 'saved', version };
  } catch (err: unknown) {
    const status = (err as { response?: { status?: number; data?: { current_version?: string } } })?.response?.status;
    const now = useEditorStore.getState();
    if (now.sessionKey === key && status === 409) {
      now.setConflict(true);
      const current = (err as { response?: { data?: { current_version?: string } } })?.response?.data?.current_version ?? null;
      throw new SaveConflictError(current);
    }
    throw err;
  } finally {
    const now = useEditorStore.getState();
    if (now.sessionKey === key) now.setSaving(false);
  }
}

/**
 * Discard local content and re-hydrate the session from the server (used
 * after a conflict, and after a version restore). Keeps the session key.
 */
export async function reloadSessionFromServer(target: SaveTarget, canvasMeta?: { pageType?: 'website' | 'single'; width?: number; mobileWidth?: number; fit?: string }): Promise<void> {
  const key = sessionKeyFor(target);
  const res = await blocksApi.get(target.siteId, target.type, target.id);
  const store = useEditorStore.getState();
  if (store.sessionKey !== key) return;
  const tree = (res.data?.data ?? []) as Parameters<typeof store.setBlocks>[0];
  store.setBlocks(tree);
  if (store.editorMode === 'canvas') {
    useCanvasStore.getState().loadFromBlocks(tree, canvasMeta ?? { pageType: 'website' });
  }
  store.markHydrated((res.data?.version ?? null) as string | null);
  store.setDirty(false);
}

/** Test hook: forget any queued/in-flight bookkeeping. */
export function __resetSaveCoordinator(): void {
  inFlight = null;
  queued = null;
}
