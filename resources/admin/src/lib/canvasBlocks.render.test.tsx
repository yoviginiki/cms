import { describe, it, expect, afterEach } from 'vitest';
import { createElement } from 'react';
import { render, cleanup } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { blockRegistry } from '@/components/blocks/registry';
import '@/components/blocks';
import { CANVAS_BLOCKS } from './canvasBlocks';
import type { BlockData } from '@/types/blocks';

// Every block the canvas palette offers must be registered and render both its
// canvas Preview and its inspector Editor from default data without throwing.
const wrap = (el: React.ReactElement) => createElement(MemoryRouter, { initialEntries: ['/admin/sites/s1/pages/p1/edit'] },
  createElement(QueryClientProvider, { client: new QueryClient() }, el));

describe('canvas palette blocks', () => {
  afterEach(cleanup);

  it.each(CANVAS_BLOCKS)('%s renders its Preview and Editor', (type) => {
    const reg = blockRegistry.get(type);
    expect(reg, `${type} is not a registered block`).toBeDefined();
    const block: BlockData = { id: 'b', type, data: JSON.parse(JSON.stringify(reg!.definition.defaultData ?? {})), children: [], order: 0, style: {} };
    expect(() => { render(wrap(createElement(reg!.Preview, { block, isSelected: true, onUpdate: () => {}, onSelect: () => {} }))); cleanup(); }).not.toThrow();
    expect(() => { render(wrap(createElement(reg!.Editor, { block, isSelected: true, onUpdate: () => {}, onSelect: () => {} }))); cleanup(); }).not.toThrow();
  });
});
