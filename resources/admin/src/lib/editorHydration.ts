/**
 * Editor session hydration (audit 2026-09-22, F12).
 *
 * Metadata (page/post/template) and blocks arrive from two independent
 * requests. The old effects loaded the blocks as soon as THEY arrived and
 * never ran again — so when the metadata came second, a canvas page stayed
 * empty (canvas mode unknown at load time) and raw_html was never put in the
 * store (a later save then wiped it). Hydration now happens exactly once,
 * only when both responses for the SAME content id are present.
 */
import { useEditorStore } from '@/stores/editorStore';
import { useCanvasStore } from '@/stores/canvasStore';
import type { BlockData } from '@/types/blocks';

export interface ContentMeta {
  id: string;
  editor_mode?: string | null;
  raw_html?: string | null;
  seo_meta?: { canvas?: { page_type?: string; width?: number; mobile_width?: number; fit?: string } } | null;
}

export interface HydrationInput {
  /** The session key the editor is currently showing (siteId/type/id). */
  sessionKey: string;
  /** The content id the editor is showing. */
  contentId: string;
  meta: ContentMeta | null | undefined;
  blocks: BlockData[] | null | undefined;
  blocksVersion: string | null | undefined;
}

/** True when both halves are present AND belong to the open content. */
export function canHydrate(input: HydrationInput): boolean {
  const s = useEditorStore.getState();
  if (s.sessionKey !== input.sessionKey || s.hydrated) return false;
  if (!input.meta || input.meta.id !== input.contentId) return false;
  if (input.blocks === undefined || input.blocks === null) return false;
  return true;
}

/**
 * Put metadata + blocks into the editor (and canvas) stores and mark the
 * session hydrated with the server content revision. Returns false when the
 * input is not (yet) hydratable — callers simply try again when data changes.
 */
export function hydrateEditorSession(input: HydrationInput): boolean {
  if (!canHydrate(input)) return false;
  const store = useEditorStore.getState();
  const meta = input.meta!;
  const tree = input.blocks ?? [];

  const mode = meta.editor_mode;
  if (mode === 'simple' || mode === 'block' || mode === 'magazine' || mode === 'canvas') {
    store.setEditorMode(mode);
  }
  store.setBlocks(tree);
  useEditorStore.setState({ rawHtml: meta.raw_html ?? '' });

  if (mode === 'canvas') {
    const cv = meta.seo_meta?.canvas;
    useCanvasStore.getState().loadFromBlocks(tree, {
      pageType: cv?.page_type === 'single' ? 'single' : 'website',
      width: cv?.width,
      mobileWidth: cv?.mobile_width,
      fit: cv?.fit,
    });
  }

  store.markHydrated(input.blocksVersion ?? null);
  return true;
}
