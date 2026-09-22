import { describe, it, expect, beforeEach } from 'vitest';
import { useEditorStore } from '@/stores/editorStore';
import { useCanvasStore } from '@/stores/canvasStore';
import { hydrateEditorSession, canHydrate } from './editorHydration';
import type { BlockData } from '@/types/blocks';

const key = 's1/pages/p1';
const section: BlockData = { id: 'sec', type: 'section', level: 'section', order: 0, data: { canvas: { height: 300, bleed: false, background: '' } }, children: [] };
const canvasMeta = { id: 'p1', editor_mode: 'canvas', raw_html: null, seo_meta: { canvas: { page_type: 'single', width: 900 } } };
const blockMeta = { id: 'p1', editor_mode: 'block', raw_html: '<main>trusted</main>', seo_meta: null };

describe('editor hydration (F12)', () => {
  beforeEach(() => {
    useEditorStore.getState().beginSession(key);
    useCanvasStore.getState().loadFromBlocks([], { pageType: 'website' });
  });

  it('waits for BOTH metadata and blocks (blocks first)', () => {
    expect(hydrateEditorSession({ sessionKey: key, contentId: 'p1', meta: undefined, blocks: [section], blocksVersion: '4' })).toBe(false);
    expect(useEditorStore.getState().hydrated).toBe(false);
    expect(hydrateEditorSession({ sessionKey: key, contentId: 'p1', meta: canvasMeta, blocks: [section], blocksVersion: '4' })).toBe(true);
    const s = useEditorStore.getState();
    expect(s.hydrated).toBe(true);
    expect(s.serverVersion).toBe('4');
    expect(s.editorMode).toBe('canvas');
    // the canvas store got the tree — the old code left it empty in this order
    expect(useCanvasStore.getState().sections).toHaveLength(1);
    expect(useCanvasStore.getState().width).toBe(900);
    expect(useCanvasStore.getState().pageType).toBe('single');
  });

  it('waits for BOTH metadata and blocks (metadata first) and loads raw_html', () => {
    expect(hydrateEditorSession({ sessionKey: key, contentId: 'p1', meta: blockMeta, blocks: undefined, blocksVersion: undefined })).toBe(false);
    expect(hydrateEditorSession({ sessionKey: key, contentId: 'p1', meta: blockMeta, blocks: [], blocksVersion: '0' })).toBe(true);
    expect(useEditorStore.getState().rawHtml).toBe('<main>trusted</main>');
    expect(useEditorStore.getState().editorMode).toBe('block');
  });

  it('ignores metadata for a different content id and a different session', () => {
    expect(canHydrate({ sessionKey: key, contentId: 'p1', meta: { ...blockMeta, id: 'OTHER' }, blocks: [], blocksVersion: '0' })).toBe(false);
    expect(canHydrate({ sessionKey: 'someone/else', contentId: 'p1', meta: blockMeta, blocks: [], blocksVersion: '0' })).toBe(false);
  });

  it('hydrates exactly once — a refetch never overwrites local edits', () => {
    const text: BlockData = { id: 't1', type: 'text', order: 0, data: { content: 'server' }, children: [] };
    hydrateEditorSession({ sessionKey: key, contentId: 'p1', meta: blockMeta, blocks: [text], blocksVersion: '0' });
    useEditorStore.getState().updateBlock('t1', { content: 'local edit' });
    expect(useEditorStore.getState().isDirty).toBe(true);
    expect(hydrateEditorSession({ sessionKey: key, contentId: 'p1', meta: blockMeta, blocks: [text], blocksVersion: '1' })).toBe(false);
    expect(useEditorStore.getState().isDirty).toBe(true);
    expect(useEditorStore.getState().blocks[0].data.content).toBe('local edit');
  });

  it('navigating to another document resets everything', () => {
    hydrateEditorSession({ sessionKey: key, contentId: 'p1', meta: canvasMeta, blocks: [section], blocksVersion: '4' });
    useEditorStore.getState().beginSession('s1/pages/p2');
    const s = useEditorStore.getState();
    expect(s.hydrated).toBe(false);
    expect(s.blocks).toEqual([]);
    expect(s.rawHtml).toBe('');
    expect(s.serverVersion).toBeNull();
    expect(s.isDirty).toBe(false);
  });
});
