import { describe, it, expect, beforeEach, vi } from 'vitest';

vi.mock('@/lib/api', () => ({
  blocks: { sync: vi.fn(), get: vi.fn() },
}));

import { blocks as blocksApi } from '@/lib/api';
import { useEditorStore } from '@/stores/editorStore';
import { useCanvasStore } from '@/stores/canvasStore';
import { saveContent, reloadSessionFromServer, sessionKeyFor, SaveConflictError, __resetSaveCoordinator } from './saveCoordinator';
import type { BlockData } from '@/types/blocks';

const target = { siteId: 's1', type: 'pages' as const, id: 'p1' };
const key = sessionKeyFor(target);
const sync = blocksApi.sync as unknown as ReturnType<typeof vi.fn>;

function deferred<T>() {
  let resolve!: (v: T) => void;
  let reject!: (e: unknown) => void;
  const promise = new Promise<T>((res, rej) => { resolve = res; reject = rej; });
  return { promise, resolve, reject };
}

const text = (id: string, content: string): BlockData => ({ id, type: 'text', order: 0, data: { content }, children: [] });

function openSession(mode: 'block' | 'canvas' = 'block', version = '3') {
  const s = useEditorStore.getState();
  s.beginSession(key);
  s.setEditorMode(mode);
  s.setBlocks([text('b1', 'hello')]);
  useEditorStore.setState({ rawHtml: '<main>raw</main>' });
  s.markHydrated(version);
}

describe('saveCoordinator (F11/F13)', () => {
  beforeEach(() => {
    __resetSaveCoordinator();
    sync.mockReset();
    useEditorStore.getState().beginSession('nothing');
  });

  it('refuses to save before the session is hydrated', async () => {
    useEditorStore.getState().beginSession(key);
    const r = await saveContent(target);
    expect(r.outcome).toBe('skipped');
    expect(sync).not.toHaveBeenCalled();
  });

  it('sends the block tree, raw_html (pages) and the server revision; stores the new one', async () => {
    openSession('block', '7');
    sync.mockResolvedValue({ data: { data: [], version: '8' } });
    useEditorStore.getState().setDirty(true);

    const r = await saveContent(target);

    expect(r).toEqual({ outcome: 'saved', version: '8' });
    expect(sync).toHaveBeenCalledWith('s1', 'pages', 'p1', useEditorStore.getState().blocks, '<main>raw</main>', { expected_version: '7' });
    expect(useEditorStore.getState().serverVersion).toBe('8');
    expect(useEditorStore.getState().isDirty).toBe(false);
  });

  it('does not send raw_html for posts/templates', async () => {
    const t = { siteId: 's1', type: 'posts' as const, id: 'x' };
    useEditorStore.getState().beginSession(sessionKeyFor(t));
    useEditorStore.getState().setBlocks([text('b1', 'p')]);
    useEditorStore.getState().markHydrated('1');
    sync.mockResolvedValue({ data: { version: '2' } });
    await saveContent(t);
    expect(sync.mock.calls[0][4]).toBeUndefined();
  });

  it('keeps "dirty" when a newer edit arrived while the request was in flight, then saves it', async () => {
    openSession('block', '1');
    const first = deferred<{ data: { version: string } }>();
    sync.mockReturnValueOnce(first.promise);
    useEditorStore.getState().updateBlock('b1', { content: 'edit A' });

    const saveA = saveContent(target);
    // edit B while A is out
    useEditorStore.getState().updateBlock('b1', { content: 'edit B' });
    first.resolve({ data: { version: '2' } });
    await saveA;

    expect(useEditorStore.getState().isDirty).toBe(true);
    expect(useEditorStore.getState().serverVersion).toBe('2');

    sync.mockResolvedValueOnce({ data: { version: '3' } });
    await saveContent(target);
    expect(sync).toHaveBeenCalledTimes(2);
    expect(sync.mock.calls[1][5]).toEqual({ expected_version: '2' });
    expect((sync.mock.calls[1][3] as BlockData[])[0].data.content).toBe('edit B');
    expect(useEditorStore.getState().isDirty).toBe(false);
  });

  it('a 409 keeps the local edit, marks the session conflicted and surfaces the current version', async () => {
    openSession('block', '1');
    useEditorStore.getState().updateBlock('b1', { content: 'mine' });
    sync.mockRejectedValueOnce({ response: { status: 409, data: { current_version: '5' } } });

    await expect(saveContent(target)).rejects.toBeInstanceOf(SaveConflictError);
    const s = useEditorStore.getState();
    expect(s.conflict).toBe(true);
    expect(s.isDirty).toBe(true);
    expect(s.blocks[0].data.content).toBe('mine');
    expect(s.isSaving).toBe(false);
  });

  it('a failed save never shows "saved"', async () => {
    openSession();
    useEditorStore.getState().setDirty(true);
    sync.mockRejectedValueOnce(new Error('network'));
    await expect(saveContent(target)).rejects.toThrow('network');
    expect(useEditorStore.getState().isDirty).toBe(true);
    expect(useEditorStore.getState().isSaving).toBe(false);
  });

  it('a response for a session that is no longer open does not touch the new session', async () => {
    openSession('block', '1');
    useEditorStore.getState().setDirty(true);
    const slow = deferred<{ data: { version: string } }>();
    sync.mockReturnValueOnce(slow.promise);
    const saveA = saveContent(target);

    // navigate to page 2 and edit there
    const key2 = sessionKeyFor({ ...target, id: 'p2' });
    useEditorStore.getState().beginSession(key2);
    useEditorStore.getState().setBlocks([text('c1', 'page two')]);
    useEditorStore.getState().markHydrated('9');
    useEditorStore.getState().setDirty(true);

    slow.resolve({ data: { version: '2' } });
    await saveA;

    const s = useEditorStore.getState();
    expect(s.sessionKey).toBe(key2);
    expect(s.isDirty).toBe(true);        // page 2's edit is still unsaved
    expect(s.serverVersion).toBe('9');   // page 1's response did not leak in
  });

  it('serializes the CANVAS tree in canvas mode and clears both dirty flags', async () => {
    openSession('canvas', '1');
    const section: BlockData = { id: 's1', type: 'section', level: 'section', order: 0, data: { canvas: { height: 400, bleed: false, background: '' } }, children: [] };
    useCanvasStore.getState().loadFromBlocks([section], { pageType: 'website', width: 1200 });
    useCanvasStore.getState().setWidth(1000); // canvas edit → canvas dirty
    useEditorStore.getState().setDirty(true);
    sync.mockResolvedValueOnce({ data: { version: '2' } });

    await saveContent(target);

    const sent = sync.mock.calls[0][3] as BlockData[];
    expect(JSON.stringify(sent)).toBe(JSON.stringify(useCanvasStore.getState().toBlocks()));
    expect(sent).not.toBe(useEditorStore.getState().blocks); // not the stale block-store tree
    expect(useCanvasStore.getState().isDirty).toBe(false);
    expect(useEditorStore.getState().isDirty).toBe(false);
  });

  it('runs one request at a time and coalesces calls made while one is in flight', async () => {
    openSession('block', '1');
    useEditorStore.getState().setDirty(true);
    const first = deferred<{ data: { version: string } }>();
    sync.mockReturnValueOnce(first.promise).mockResolvedValue({ data: { version: '3' } });

    const a = saveContent(target);
    const b = saveContent(target);
    const c = saveContent(target);
    expect(b).toBe(c); // coalesced into one follow-up
    expect(sync).toHaveBeenCalledTimes(1);

    first.resolve({ data: { version: '2' } });
    await Promise.all([a, b, c]);
    expect(sync).toHaveBeenCalledTimes(2);
    expect(sync.mock.calls[1][5]).toEqual({ expected_version: '2' }); // follow-up used the new revision
  });

  it('reloadSessionFromServer re-hydrates blocks and revision and clears conflict', async () => {
    openSession('block', '1');
    useEditorStore.getState().setConflict(true);
    (blocksApi.get as unknown as ReturnType<typeof vi.fn>).mockResolvedValueOnce({ data: { data: [text('z', 'server')], version: '12' } });

    await reloadSessionFromServer(target);

    const s = useEditorStore.getState();
    expect(s.blocks[0].data.content).toBe('server');
    expect(s.serverVersion).toBe('12');
    expect(s.conflict).toBe(false);
    expect(s.isDirty).toBe(false);
  });
});
