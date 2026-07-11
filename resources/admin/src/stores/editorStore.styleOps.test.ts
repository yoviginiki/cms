import { describe, it, expect, beforeEach } from 'vitest';
import { useEditorStore } from './editorStore';
import type { BlockData } from '@/types/blocks';

// P4 ergonomics: copy/paste style granularity + extend style.
const text = (id: string, style: any = {}): BlockData => ({
  id, type: 'text', level: 'module', data: { content: id }, children: [], order: 0, style,
} as BlockData);

function section(id: string, children: BlockData[]): BlockData {
  return { id, type: 'section', level: 'section', data: {}, children, order: 0 } as BlockData;
}

const richStyle = {
  typography: { fontWeight: '700', textColor: '#111111' },
  spacing: { paddingTop: '20px' },
  visual: { backgroundColor: '#ff0000', borderColor: '#00ff00', borderWidth: '2px' },
};

describe('editorStore P4 style ops', () => {
  beforeEach(() => {
    useEditorStore.setState({ blocks: [], styleClipboard: null, undoStack: [], redoStack: [] });
  });

  it('pasteStyle copies only the requested granularity', () => {
    const s = useEditorStore.getState();
    s.setBlocks([section('sec', [text('a', richStyle), text('b')])]);
    s.copyStyle('a');
    s.pasteStyle('b', 'typography');

    const b = findById(useEditorStore.getState().blocks, 'b')!;
    expect(b.style?.typography).toEqual(richStyle.typography);
    expect(b.style?.spacing).toBeUndefined(); // not copied
    expect(b.style?.visual).toBeUndefined();
  });

  it('pasteStyle colors takes only color leaves', () => {
    const s = useEditorStore.getState();
    s.setBlocks([section('sec', [text('a', richStyle), text('b')])]);
    s.copyStyle('a');
    s.pasteStyle('b', 'colors');

    const b = findById(useEditorStore.getState().blocks, 'b')!;
    expect(b.style?.visual?.backgroundColor).toBe('#ff0000');
    expect(b.style?.visual?.borderColor).toBe('#00ff00');
    expect(b.style?.typography?.textColor).toBe('#111111');
    expect((b.style?.visual as any)?.borderWidth).toBeUndefined(); // not a color
    expect(b.style?.spacing).toBeUndefined();
  });

  it('pasteStyle merges over existing local style (does not wipe siblings)', () => {
    const s = useEditorStore.getState();
    s.setBlocks([section('sec', [text('a', richStyle), text('b', { spacing: { marginTop: '9px' } })])]);
    s.copyStyle('a');
    s.pasteStyle('b', 'spacing');

    const b = findById(useEditorStore.getState().blocks, 'b')!;
    expect(b.style?.spacing?.paddingTop).toBe('20px'); // from source
    expect((b.style?.spacing as any)?.marginTop).toBe('9px'); // preserved local leaf
  });

  it('extendStyle applies to all same-type blocks in the page and reports count', () => {
    const s = useEditorStore.getState();
    s.setBlocks([
      section('s1', [text('a', richStyle), text('b')]),
      section('s2', [text('c'), { id: 'img', type: 'image', level: 'module', data: {}, children: [], order: 0 } as BlockData]),
    ]);
    const count = s.extendStyle('a', 'page', 'all');
    expect(count).toBe(2); // b and c (not the image, not self)
    expect(findById(useEditorStore.getState().blocks, 'c')!.style?.visual?.backgroundColor).toBe('#ff0000');
  });

  it('extendStyle section scope only touches the same section', () => {
    const s = useEditorStore.getState();
    s.setBlocks([
      section('s1', [text('a', richStyle), text('b')]),
      section('s2', [text('c')]),
    ]);
    const count = s.extendStyle('a', 'section', 'all');
    expect(count).toBe(1); // just b
    expect(findById(useEditorStore.getState().blocks, 'c')!.style?.typography).toBeUndefined();
  });
});

describe('editorStore P4 multi-select', () => {
  beforeEach(() => {
    useEditorStore.setState({ blocks: [], selectedBlockId: null, selectedBlockIds: [], styleClipboard: null, undoStack: [], redoStack: [] });
  });

  it('selectBlock resets to single, toggle adds/removes', () => {
    const s = useEditorStore.getState();
    s.setBlocks([section('sec', [text('a'), text('b'), text('c')])]);
    s.selectBlock('a');
    expect(useEditorStore.getState().selectedBlockIds).toEqual(['a']);
    s.toggleBlockSelection('b');
    expect(useEditorStore.getState().selectedBlockIds).toEqual(['a', 'b']);
    s.toggleBlockSelection('a'); // remove primary
    expect(useEditorStore.getState().selectedBlockIds).toEqual(['b']);
    expect(useEditorStore.getState().selectedBlockId).toBe('b'); // primary reassigned
  });

  it('removeSelected deletes the whole selection', () => {
    const s = useEditorStore.getState();
    s.setBlocks([section('sec', [text('a'), text('b'), text('c')])]);
    s.selectBlock('a');
    s.toggleBlockSelection('c');
    s.removeSelected();
    const sec = findById(useEditorStore.getState().blocks, 'sec')!;
    expect(sec.children.map((c) => c.id)).toEqual(['b']);
    expect(useEditorStore.getState().selectedBlockIds).toEqual([]);
  });

  it('duplicateSelected copies each selected block with fresh ids', () => {
    const s = useEditorStore.getState();
    s.setBlocks([section('sec', [text('a'), text('b')])]);
    s.selectBlock('a');
    s.toggleBlockSelection('b');
    s.duplicateSelected();
    const sec = findById(useEditorStore.getState().blocks, 'sec')!;
    expect(sec.children.length).toBe(4); // a, a', b, b'
    const ids = sec.children.map((c) => c.id);
    expect(new Set(ids).size).toBe(4); // all unique
  });

  it('pasteStyleToSelected applies to every selected block', () => {
    const s = useEditorStore.getState();
    s.setBlocks([section('sec', [text('src', richStyle), text('a'), text('b')])]);
    s.copyStyle('src');
    s.selectBlock('a');
    s.toggleBlockSelection('b');
    s.pasteStyleToSelected('colors');
    expect(findById(useEditorStore.getState().blocks, 'a')!.style?.visual?.backgroundColor).toBe('#ff0000');
    expect(findById(useEditorStore.getState().blocks, 'b')!.style?.visual?.backgroundColor).toBe('#ff0000');
  });
});

describe('editorStore P4 find & replace design values', () => {
  beforeEach(() => {
    useEditorStore.setState({ blocks: [], undoStack: [], redoStack: [] });
  });

  it('findStyleValues collects distinct colors with counts, most-used first', () => {
    const s = useEditorStore.getState();
    s.setBlocks([section('sec', [
      text('a', { visual: { backgroundColor: '#ff0000', borderColor: '#00ff00' } }),
      text('b', { visual: { backgroundColor: '#ff0000' } }),
      text('c', { typography: { fontWeight: '700' } }), // not a color
    ])]);
    const vals = s.findStyleValues();
    expect(vals[0]).toEqual({ value: '#ff0000', count: 2 }); // most used first
    expect(vals.find((v) => v.value === '#00ff00')?.count).toBe(1);
    expect(vals.find((v) => v.value === '700')).toBeUndefined();
  });

  it('replaceStyleValue swaps every occurrence and reports count', () => {
    const s = useEditorStore.getState();
    s.setBlocks([section('sec', [
      text('a', { visual: { backgroundColor: '#111111' } }),
      text('b', { visual: { borderColor: '#111111' } }),
    ])]);
    const n = s.replaceStyleValue('#111111', '$color.accent');
    expect(n).toBe(2);
    expect(findById(useEditorStore.getState().blocks, 'a')!.style?.visual?.backgroundColor).toBe('$color.accent');
    expect(findById(useEditorStore.getState().blocks, 'b')!.style?.visual?.borderColor).toBe('$color.accent');
  });

  it('replaceStyleValue also reaches colors stored in block data (backgrounds)', () => {
    const s = useEditorStore.getState();
    const b: BlockData = { id: 'x', type: 'section', level: 'section', data: { bg_color: '#222222' }, children: [], order: 0 } as BlockData;
    s.setBlocks([b]);
    expect(s.replaceStyleValue('#222222', '#333333')).toBe(1);
    expect((findById(useEditorStore.getState().blocks, 'x')!.data as any).bg_color).toBe('#333333');
  });
});

function findById(blocks: BlockData[], id: string): BlockData | null {
  for (const b of blocks) {
    if (b.id === id) return b;
    const f = findById(b.children ?? [], id);
    if (f) return f;
  }
  return null;
}
