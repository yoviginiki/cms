import { describe, it, expect, afterEach } from 'vitest';
import { createElement } from 'react';
import { render, cleanup } from '@testing-library/react';
import { CanvasSection } from './CanvasSection';
import { useCanvasStore } from '@/stores/canvasStore';
import type { BlockData } from '@/types/blocks';

const sectionBlock = (): BlockData => ({
  id: 's1', type: 'section', level: 'section', order: 0, data: { canvas: { height: 400, bleed: false, background: '' } }, children: [],
} as unknown as BlockData);

describe('CanvasElement shared block style', () => {
  afterEach(cleanup);

  it('previews background / border / shadow / spacing / typography from the inspector on the content box', () => {
    useCanvasStore.getState().loadFromBlocks([sectionBlock()], { pageType: 'website', width: 1200 });
    const id = useCanvasStore.getState().addElement('s1', 'text', 10, 10, 200, 100);
    useCanvasStore.getState().updateElementData(id, { __style: {
      visual: { backgroundColor: '#ff0000', borderWidth: '2px', borderColor: '#000000', borderRadius: '8px', boxShadow: 'md' },
      spacing: { paddingTop: '12px' },
      typography: { fontSize: '20px', textAlign: 'center' },
    } });
    const section = useCanvasStore.getState().sections[0];
    const { container } = render(createElement(CanvasSection, {
      section, width: 1200, zoom: 1, isActive: true, canMoveUp: false, canMoveDown: false, singleMode: false,
    }));
    const box = container.querySelector('.cv-fill') as HTMLElement;
    expect(box.style.backgroundColor).toBe('rgb(255, 0, 0)');
    expect(box.style.borderRadius).toBe('8px');
    expect(box.style.border).toContain('2px solid');
    expect(box.style.boxShadow).not.toBe('');
    expect(box.style.paddingTop).toBe('12px');
    expect(box.style.fontSize).toBe('20px');
    expect(box.style.textAlign).toBe('center');
    // the canvas position never leaks in as CSS size — the box stays 100% of the element
    expect(box.style.width).toBe('100%');
    expect(box.style.height).toBe('100%');
  });
});
