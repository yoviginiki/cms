import { describe, it, expect } from 'vitest';
import { blockToCanvas, canvasToBlocks, extractPassthrough } from './canvasAdapter';
import type { CanvasDoc } from '@/types/canvas';
import type { BlockData } from '@/types/blocks';

const doc: CanvasDoc = {
  pageType: 'website',
  width: 1200,
  sections: [
    {
      id: 'sec-1',
      settings: { height: 600, bleed: false, background: '#f5f5f5' },
      data: { padding_top: '2rem' },
      style: {},
      elements: [
        {
          id: 'el-1', blockType: 'heading', data: { text: 'Hi', level: 'h1' },
          x: 80, y: 40, width: 600, height: 90, rotation: -3, zIndex: 2, locked: false, style: {},
        },
        {
          id: 'el-2', blockType: 'text', data: { content: '<p>x</p>' },
          x: 100, y: 400, width: 500, height: 120, rotation: 0, zIndex: 1, locked: true, style: {},
        },
      ],
    },
    {
      id: 'sec-2',
      settings: { height: 'auto', bleed: true, background: '#0f172a' },
      data: {}, style: {}, elements: [],
    },
  ],
};

describe('canvasAdapter', () => {
  it('canvas -> blocks -> canvas is identical', () => {
    const round = blockToCanvas(canvasToBlocks(doc), { pageType: 'website', width: 1200 });
    expect(round).toEqual(doc);
  });

  it('maps elements to child section blocks with absolute style.layout', () => {
    const blocks = canvasToBlocks(doc);
    expect(blocks).toHaveLength(2);
    expect(blocks[0].type).toBe('section');
    expect((blocks[0].data as Record<string, unknown>).canvas).toEqual({ height: 600, bleed: false, background: '#f5f5f5' });
    const el = blocks[0].children[0];
    const layout = (el.style as Record<string, Record<string, unknown>>).layout;
    expect(layout).toMatchObject({ position: 'absolute', x: 80, y: 40, width: '600px', height: '90px', rotation: -3, zIndex: 2 });
  });

  it('preserves non-section top-level blocks (non-destructive mode switch)', () => {
    const blocks: BlockData[] = [
      { id: 's', type: 'section', data: { canvas: { height: 300, bleed: false, background: '' } }, order: 0, children: [] } as unknown as BlockData,
      { id: 'row1', type: 'row', data: { foo: 1 }, order: 1, children: [{ id: 'c', type: 'column', data: {}, order: 0, children: [] }] } as unknown as BlockData,
    ];
    const passthrough = extractPassthrough(blocks);
    expect(passthrough.map(b => b.id)).toEqual(['row1']);
    // canvas edit drops nothing: the row block survives the round-trip, re-appended after sections
    const doc = blockToCanvas(blocks);
    const out = canvasToBlocks(doc, passthrough);
    expect(out.map(b => b.type)).toEqual(['section', 'row']);
    expect(out.find(b => b.id === 'row1')?.data).toEqual({ foo: 1 });
  });

  it('round-trips per-breakpoint mobile overrides through style.layout.bp', () => {
    const withBp: CanvasDoc = {
      pageType: 'website', width: 1200,
      sections: [{
        id: 's', settings: { height: 500, bleed: false, background: '' }, data: {}, style: {},
        elements: [{
          id: 'e', blockType: 'heading', data: {}, x: 80, y: 40, width: 600, height: 90, rotation: 0, zIndex: 1, locked: false, style: {},
          bp: { mobile: { x: 10, y: 20, width: 340 } },
        }],
      }],
    };
    const round = blockToCanvas(canvasToBlocks(withBp), { pageType: 'website', width: 1200 });
    expect(round).toEqual(withBp);
    // and it lands under style.layout.bp in the block tree
    const layout = (canvasToBlocks(withBp)[0].children[0].style as Record<string, Record<string, unknown>>).layout;
    expect(layout.bp).toEqual({ mobile: { x: 10, y: 20, width: 340 } });
  });

  it('round-trips per-element pinX and section fluid flag', () => {
    const d: CanvasDoc = {
      pageType: 'website', width: 1200,
      sections: [{
        id: 's', settings: { height: 300, bleed: false, background: '', fluid: true }, data: {}, style: {},
        elements: [{
          id: 'e', blockType: 'text', data: {}, x: 800, y: 20, width: 300, height: 60, rotation: 0, zIndex: 1, locked: false, style: {},
          pinX: 'right',
        }],
      }],
    };
    const round = blockToCanvas(canvasToBlocks(d), { pageType: 'website', width: 1200 });
    expect(round).toEqual(d);
    // default 'left' pin is normalized away (not serialized)
    const leftDoc: CanvasDoc = { ...d, sections: [{ ...d.sections[0], settings: { height: 300, bleed: false, background: '' }, elements: [{ ...d.sections[0].elements[0], pinX: 'left' }] }] };
    const leftBlocks = canvasToBlocks(leftDoc);
    expect((leftBlocks[0].children[0].style as Record<string, Record<string, unknown>>).layout.pinX).toBeUndefined();
    expect((leftBlocks[0].data as Record<string, unknown>).canvas).not.toHaveProperty('fluid');
  });

  it('reads an existing block tree into the canvas model', () => {
    const blocks: BlockData[] = [{
      id: 's', type: 'section', data: { canvas: { height: 300, bleed: true, background: '#fff' } }, order: 0,
      children: [{
        id: 'e', type: 'button', data: { text: 'Go' }, order: 0, children: [],
        style: { layout: { x: 10, y: 20, width: '150px', height: '40px', rotation: 0, zIndex: 3 } },
      }],
    } as unknown as BlockData];
    const c = blockToCanvas(blocks);
    expect(c.sections[0].settings).toEqual({ height: 300, bleed: true, background: '#fff' });
    expect(c.sections[0].elements[0]).toMatchObject({ blockType: 'button', x: 10, y: 20, width: 150, height: 40, zIndex: 3 });
  });
});
