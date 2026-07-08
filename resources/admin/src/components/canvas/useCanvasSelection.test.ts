import { describe, it, expect, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useCanvasStore } from '@/stores/canvasStore';
import { useCanvasSelection } from './useCanvasSelection';
import type { BlockData } from '@/types/blocks';

const section = (id: string): BlockData => ({
  id, type: 'section', level: 'section', order: 0, data: { canvas: { height: 400, bleed: false, background: '' } }, children: [],
} as unknown as BlockData);

// A React.PointerEvent stand-in for the element/handle handlers.
const down = (clientX: number, clientY: number, shiftKey = false) =>
  ({ clientX, clientY, shiftKey, metaKey: false, ctrlKey: false, stopPropagation() {} }) as unknown as React.PointerEvent;

const winMove = (clientX: number, clientY: number) =>
  window.dispatchEvent(new MouseEvent('pointermove', { clientX, clientY }));
const winUp = () => window.dispatchEvent(new MouseEvent('pointerup', {}));

function seed(): string {
  useCanvasStore.getState().loadFromBlocks([section('s1')], { pageType: 'website', width: 1200 });
  useCanvasStore.setState({ snapEnabled: false, zoom: 1 }); // deterministic geometry
  return useCanvasStore.getState().addElement('s1', 'heading', 100, 100, 200, 100);
}

const el = () => useCanvasStore.getState().sections[0].elements[0];

describe('useCanvasSelection', () => {
  beforeEach(() => { seed(); });

  it('drags the selected element by the pointer delta (snap off)', () => {
    const id = el().id;
    const { result } = renderHook(() => useCanvasSelection('s1', 1200, 400));
    act(() => result.current.onElementPointerDown(down(500, 500), id));
    act(() => winMove(560, 530)); // +60, +30
    expect([el().x, el().y]).toEqual([160, 130]);
    act(() => winUp());
    // after pointer-up, further moves are ignored
    act(() => winMove(999, 999));
    expect([el().x, el().y]).toEqual([160, 130]);
  });

  it('divides the delta by zoom', () => {
    const id = el().id;
    useCanvasStore.setState({ zoom: 0.5 });
    const { result } = renderHook(() => useCanvasSelection('s1', 1200, 400));
    act(() => result.current.onElementPointerDown(down(0, 0), id));
    act(() => winMove(100, 40)); // screen 100,40 ÷ 0.5 = 200,80
    expect([el().x, el().y]).toEqual([300, 180]);
    act(() => winUp());
  });

  it('resizes from the SE handle', () => {
    const id = el().id;
    const { result } = renderHook(() => useCanvasSelection('s1', 1200, 400));
    act(() => result.current.onResizePointerDown(down(0, 0), id, 'se'));
    act(() => winMove(40, 25));
    expect([el().width, el().height]).toEqual([240, 125]);
    act(() => winUp());
  });

  it('resizes from the NW handle (moves origin, shrinks size)', () => {
    const id = el().id;
    const { result } = renderHook(() => useCanvasSelection('s1', 1200, 400));
    act(() => result.current.onResizePointerDown(down(0, 0), id, 'nw'));
    act(() => winMove(30, 20)); // drag corner in → x+30,y+20, w-30,h-20
    expect([el().x, el().y, el().width, el().height]).toEqual([130, 120, 170, 80]);
    act(() => winUp());
  });

  it('takes exactly one undo snapshot per drag gesture (and none for a bare click)', () => {
    const id = el().id;
    const before = useCanvasStore.getState().undoStack.length;
    const { result } = renderHook(() => useCanvasSelection('s1', 1200, 400));

    // bare click: down + up, no move → no snapshot
    act(() => result.current.onElementPointerDown(down(10, 10), id));
    act(() => winUp());
    expect(useCanvasStore.getState().undoStack.length).toBe(before);

    // real drag → exactly one snapshot regardless of how many move events
    act(() => result.current.onElementPointerDown(down(10, 10), id));
    act(() => winMove(20, 20));
    act(() => winMove(30, 30));
    act(() => winMove(40, 40));
    act(() => winUp());
    expect(useCanvasStore.getState().undoStack.length).toBe(before + 1);
  });

  it('does not start a drag on a locked element', () => {
    const id = el().id;
    useCanvasStore.getState().updateElement(id, { locked: true });
    const { result } = renderHook(() => useCanvasSelection('s1', 1200, 400));
    act(() => result.current.onElementPointerDown(down(0, 0), id));
    act(() => winMove(200, 200));
    expect([el().x, el().y]).toEqual([100, 100]); // unmoved
    act(() => winUp());
  });
});
