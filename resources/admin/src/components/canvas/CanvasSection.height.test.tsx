import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { createElement } from 'react';
import { render, cleanup, fireEvent, act } from '@testing-library/react';
import { CanvasSection } from './CanvasSection';
import { useCanvasStore } from '@/stores/canvasStore';
import type { BlockData } from '@/types/blocks';

const sectionBlock = (id: string, height: number | 'auto'): BlockData => ({
  id, type: 'section', level: 'section', order: 0, data: { canvas: { height, bleed: false, background: '' } }, children: [],
} as unknown as BlockData);

const renderSection = (zoom = 1) => {
  const section = useCanvasStore.getState().sections[0];
  return render(createElement(CanvasSection, {
    section, width: 1200, zoom, isActive: true, canMoveUp: false, canMoveDown: false, singleMode: false,
  }));
};
const winMove = (clientY: number) => window.dispatchEvent(new MouseEvent('pointermove', { clientX: 0, clientY }));
const winUp = () => window.dispatchEvent(new MouseEvent('pointerup', {}));
const height = () => useCanvasStore.getState().sections[0].settings.height;

describe('CanvasSection height handle', () => {
  afterEach(cleanup);

  it('dragging the bottom bar resizes a fixed section (undoable)', () => {
    useCanvasStore.getState().loadFromBlocks([sectionBlock('s1', 480)], { pageType: 'website', width: 1200 });
    useCanvasStore.setState({ zoom: 1 });
    const { getByTestId } = renderSection();
    const before = useCanvasStore.getState().undoStack.length;
    act(() => { fireEvent.pointerDown(getByTestId('section-height-handle'), { clientX: 0, clientY: 500 }); });
    act(() => { winMove(650); });
    expect(height()).toBe(630);
    act(() => { winMove(900); winUp(); });
    expect(height()).toBe(880);
    expect(useCanvasStore.getState().undoStack.length).toBe(before + 1);
    act(() => { useCanvasStore.getState().undo(); });
    expect(height()).toBe(480);
  });

  it('dragging the bar of an auto section turns it into a fixed height, scaled by zoom', () => {
    useCanvasStore.getState().loadFromBlocks([sectionBlock('s1', 'auto')], { pageType: 'website', width: 1200 });
    const { getByTestId } = renderSection(0.5);
    // auto with no elements shows at the editor minimum (480); +100 screen px at zoom .5 = +200
    act(() => { fireEvent.pointerDown(getByTestId('section-height-handle'), { clientX: 0, clientY: 100 }); });
    act(() => { winMove(200); winUp(); });
    expect(height()).toBe(680);
  });

  it('never shrinks below the minimum', () => {
    useCanvasStore.getState().loadFromBlocks([sectionBlock('s1', 480)], { pageType: 'website', width: 1200 });
    useCanvasStore.setState({ zoom: 1 });
    const { getByTestId } = renderSection();
    act(() => { fireEvent.pointerDown(getByTestId('section-height-handle'), { clientX: 0, clientY: 500 }); });
    act(() => { winMove(-2000); winUp(); });
    expect(height()).toBe(100);
  });

  it('new sections are auto height and the canvas grows past the lowest element', () => {
    useCanvasStore.getState().loadFromBlocks([], { pageType: 'website', width: 1200 });
    useCanvasStore.getState().addSection();
    expect(height()).toBe('auto');
    const id = useCanvasStore.getState().sections[0].id;
    useCanvasStore.getState().addElement(id, 'text', 0, 900, 200, 100); // bottom at 1000 > 480
    const { getByTestId } = renderSection();
    const canvas = getByTestId('canvas-drop-target') as HTMLElement;
    expect(parseInt(canvas.style.height, 10)).toBe(1120); // 1000 + 120 room below
  });
});
