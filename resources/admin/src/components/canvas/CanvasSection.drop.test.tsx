import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { createElement } from 'react';
import { render, cleanup, fireEvent } from '@testing-library/react';
import { CanvasSection } from './CanvasSection';
import { useCanvasStore } from '@/stores/canvasStore';
import { CANVAS_DRAG_MIME } from '@/lib/canvasBlocks';
import type { BlockData } from '@/types/blocks';

const sectionBlock = (id: string): BlockData => ({
  id, type: 'section', level: 'section', order: 0, data: { canvas: { height: 400, bleed: false, background: '' } }, children: [],
} as unknown as BlockData);

const dt = (type: string) => ({
  types: [CANVAS_DRAG_MIME, 'text/plain'],
  getData: (mime: string) => (mime === CANVAS_DRAG_MIME || mime === 'text/plain' ? type : ''),
  dropEffect: 'none', effectAllowed: 'copy',
});

// jsdom has no DragEvent (testing-library's fireEvent.drop then drops clientX/Y),
// so build a MouseEvent with coordinates and attach the dataTransfer by hand.
const dragEvent = (name: string, dataTransfer: unknown, clientX: number, clientY: number) => {
  const ev = new MouseEvent(name, { bubbles: true, cancelable: true, clientX, clientY });
  Object.defineProperty(ev, 'dataTransfer', { value: dataTransfer });
  return ev;
};

describe('CanvasSection palette drop', () => {
  beforeEach(() => {
    useCanvasStore.getState().loadFromBlocks([sectionBlock('s1')], { pageType: 'website', width: 1200 });
    useCanvasStore.setState({ zoom: 1 });
  });
  afterEach(cleanup);

  it('adds the dragged block centred under the pointer and selects it', () => {
    const section = useCanvasStore.getState().sections[0];
    const { getByTestId } = render(createElement(CanvasSection, {
      section, width: 1200, zoom: 1, isActive: true, canMoveUp: false, canMoveDown: false, singleMode: false,
    }));
    const target = getByTestId('canvas-drop-target');
    // jsdom has no layout: the canvas rect is 0,0 — so pointer coords are canvas coords
    fireEvent(target, dragEvent('dragover', dt('image'), 400, 300));
    fireEvent(target, dragEvent('drop', dt('image'), 400, 300));

    const els = useCanvasStore.getState().sections[0].elements;
    expect(els).toHaveLength(1);
    expect(els[0].blockType).toBe('image');
    expect([els[0].x, els[0].y, els[0].width, els[0].height]).toEqual([240, 180, 320, 240]); // 400-160, 300-120
    expect(useCanvasStore.getState().selectedIds).toEqual([els[0].id]);
  });

  it('ignores drops that are not palette blocks', () => {
    const section = useCanvasStore.getState().sections[0];
    const { getByTestId } = render(createElement(CanvasSection, {
      section, width: 1200, zoom: 1, isActive: true, canMoveUp: false, canMoveDown: false, singleMode: false,
    }));
    fireEvent(getByTestId('canvas-drop-target'), dragEvent('drop', { types: ['Files'], getData: () => '' }, 10, 10));
    expect(useCanvasStore.getState().sections[0].elements).toHaveLength(0);
  });
});
