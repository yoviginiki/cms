import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, cleanup, fireEvent, act } from '@testing-library/react';
import { CanvasEditor } from './CanvasEditor';
import { useCanvasStore } from '@/stores/canvasStore';
import type { BlockData } from '@/types/blocks';

const section = (id: string): BlockData => ({
  id, type: 'section', level: 'section', order: 0, data: { canvas: { height: 400, bleed: false, background: '' } }, children: [],
} as unknown as BlockData);

const arrow = () => fireEvent.keyDown(window, { key: 'ArrowRight' });
const el = () => useCanvasStore.getState().sections[0].elements[0];

describe('CanvasEditor keyboard nudge batching', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    useCanvasStore.getState().loadFromBlocks([section('s1')], { pageType: 'website', width: 1200 });
    useCanvasStore.getState().addElement('s1', 'text', 100, 100, 200, 100); // selected
  });
  afterEach(() => { cleanup(); vi.useRealTimers(); });

  it('collapses a run of nudges into one undo entry, then starts a new group after the idle gap', () => {
    render(<CanvasEditor siteId="s" pageId="p" />);
    const base = useCanvasStore.getState().undoStack.length;

    // three quick nudges → one snapshot, element moves 3px
    act(() => { arrow(); arrow(); arrow(); });
    expect(el().x).toBe(103);
    expect(useCanvasStore.getState().undoStack.length).toBe(base + 1);

    // idle past the gap → next nudge is a fresh undo group
    act(() => { vi.advanceTimersByTime(700); });
    act(() => { arrow(); });
    expect(el().x).toBe(104);
    expect(useCanvasStore.getState().undoStack.length).toBe(base + 2);

    // undo steps back the whole second group (one press), then the first (three)
    act(() => { useCanvasStore.getState().undo(); });
    expect(el().x).toBe(103);
    act(() => { useCanvasStore.getState().undo(); });
    expect(el().x).toBe(100);
  });
});
