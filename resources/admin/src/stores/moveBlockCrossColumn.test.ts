import { describe, it, expect, beforeEach } from 'vitest';
import { useEditorStore } from './editorStore';

// Mirror of the "Simple Post" template: section > row(1/2+1/2) > [col0 with 6
// modules, col1 empty]. Reproduces "drag the image block to the right column".
function templateTree() {
  return [
    {
      id: 'section', type: 'section', level: 'section', data: {}, order: 0,
      children: [
        {
          id: 'row', type: 'row', level: 'row', data: { layout: '1/2+1/2' }, order: 0,
          children: [
            {
              id: 'col0', type: 'column', level: 'column', data: {}, order: 0,
              children: [
                { id: 'post-image', type: 'post-image', level: 'module', data: {}, order: 0, children: [] },
                { id: 'post-meta', type: 'post-meta', level: 'module', data: {}, order: 1, children: [] },
                { id: 'post-title', type: 'post-title', level: 'module', data: {}, order: 2, children: [] },
                { id: 'post-excerpt', type: 'post-excerpt', level: 'module', data: {}, order: 3, children: [] },
                { id: 'post-content', type: 'post-content', level: 'module', data: {}, order: 4, children: [] },
                { id: 'post-nav', type: 'post-navigation', level: 'module', data: {}, order: 5, children: [] },
              ],
            },
            { id: 'col1', type: 'column', level: 'column', data: {}, order: 1, children: [] },
          ],
        },
      ],
    },
  ];
}

const count = (blocks: any[]): number =>
  blocks.reduce((n, b) => n + 1 + count(b.children ?? []), 0);

describe('moveBlock — drag a module into another (empty) column', () => {
  beforeEach(() => {
    useEditorStore.setState({ blocks: templateTree() as any, undoStack: [], redoStack: [], selectedBlockId: null });
  });

  it('moves post-image into the right column WITHOUT losing any blocks', () => {
    const before = count(useEditorStore.getState().blocks);
    useEditorStore.getState().moveBlock('post-image', 'col1', 'inside');
    const blocks = useEditorStore.getState().blocks;

    expect(count(blocks)).toBe(before); // no blocks vanished
    const row = blocks[0].children[0];
    const [col0, col1] = row.children;
    expect(col0.children.map((c: any) => c.id)).toEqual(['post-meta', 'post-title', 'post-excerpt', 'post-content', 'post-nav']);
    expect(col1.children.map((c: any) => c.id)).toEqual(['post-image']);
  });

  it('does not wipe the tree when dropped onto the column block itself', () => {
    const before = count(useEditorStore.getState().blocks);
    // handleDragEnd routes module-onto-column to an "inside" move.
    useEditorStore.getState().moveBlock('post-image', 'col1', 'inside');
    expect(count(useEditorStore.getState().blocks)).toBe(before);
    expect(useEditorStore.getState().blocks.length).toBe(1); // section still there
  });

  it('NEVER wipes the page when a section is dropped inside its own descendant', () => {
    // Root cause of "all content disappears → Add your first section": a
    // section moved 'inside' a column (its own child) used to be removed and
    // never reinserted, emptying the top level.
    const before = count(useEditorStore.getState().blocks);
    useEditorStore.getState().moveBlock('section', 'col1', 'inside');
    expect(useEditorStore.getState().blocks.length).toBe(1);      // section still top-level
    expect(count(useEditorStore.getState().blocks)).toBe(before); // nothing lost
  });

  it('rejects a cycle: a column dropped inside its own module', () => {
    const before = count(useEditorStore.getState().blocks);
    useEditorStore.getState().moveBlock('col0', 'post-title', 'inside');
    expect(count(useEditorStore.getState().blocks)).toBe(before);
  });

  it('is a no-op when a block is dropped on itself', () => {
    const before = count(useEditorStore.getState().blocks);
    useEditorStore.getState().moveBlock('post-image', 'post-image', 'inside');
    expect(count(useEditorStore.getState().blocks)).toBe(before);
  });

  it('still reorders modules within a column (before/after)', () => {
    useEditorStore.getState().moveBlock('post-title', 'post-image', 'before');
    const col0 = useEditorStore.getState().blocks[0].children[0].children[0];
    expect(col0.children[0].id).toBe('post-title');
  });
});

describe('moveBlock — auto-wrap when dropping into an empty container', () => {
  it('wraps a module in a NEW column when dropped into an empty row', () => {
    useEditorStore.setState({
      blocks: [{
        id: 'section', type: 'section', level: 'section', data: {}, order: 0,
        children: [
          { id: 'row1', type: 'row', level: 'row', data: {}, order: 0, children: [
            { id: 'col0', type: 'column', level: 'column', data: {}, order: 0, children: [
              { id: 'm1', type: 'post-title', level: 'module', data: {}, order: 0, children: [] },
            ] },
          ] },
          { id: 'row2', type: 'row', level: 'row', data: {}, order: 1, children: [] }, // empty row
        ],
      }] as any,
      undoStack: [], redoStack: [],
    });

    useEditorStore.getState().moveBlock('m1', 'row2', 'inside');
    const row2 = useEditorStore.getState().blocks[0].children[1];
    expect(row2.children).toHaveLength(1);
    expect(row2.children[0].level).toBe('column');            // a column was created
    expect(row2.children[0].children[0].id).toBe('m1');       // module lives inside it
  });

  it('wraps a module in row → column when dropped into an empty section', () => {
    useEditorStore.setState({
      blocks: [
        { id: 'sec1', type: 'section', level: 'section', data: {}, order: 0, children: [
          { id: 'r', type: 'row', level: 'row', data: {}, order: 0, children: [
            { id: 'c', type: 'column', level: 'column', data: {}, order: 0, children: [
              { id: 'm1', type: 'post-title', level: 'module', data: {}, order: 0, children: [] },
            ] },
          ] },
        ] },
        { id: 'sec2', type: 'section', level: 'section', data: {}, order: 1, children: [] }, // empty section
      ] as any,
      undoStack: [], redoStack: [],
    });

    useEditorStore.getState().moveBlock('m1', 'sec2', 'inside');
    const sec2 = useEditorStore.getState().blocks[1];
    expect(sec2.children[0].level).toBe('row');
    expect(sec2.children[0].children[0].level).toBe('column');
    expect(sec2.children[0].children[0].children[0].id).toBe('m1');
  });
});
