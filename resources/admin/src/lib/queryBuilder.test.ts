import { describe, expect, it } from 'vitest';
import type { Collection } from '@/lib/api';
import { fieldPathOptions, operatorsForType, pruneGroup } from './queryBuilder';

const products = {
  id: 'p1',
  name: 'Products',
  schema: {
    fields: [
      { key: 'title', label: 'Title', type: 'text' },
      { key: 'price', label: 'Price', type: 'price' },
      { key: 'artist', label: 'Artist', type: 'relation', relation: { collection_id: 'a1', mode: 'one' } },
    ],
  },
} as unknown as Collection;

const artists = {
  id: 'a1',
  name: 'Artists',
  schema: {
    fields: [
      { key: 'name', label: 'Name', type: 'text' },
      { key: 'country', label: 'Country', type: 'select', options: ['NL', 'JP'] },
      { key: 'friend', label: 'Friend', type: 'relation', relation: { collection_id: 'a1', mode: 'one' } },
    ],
  },
} as unknown as Collection;

describe('fieldPathOptions', () => {
  it('lists local fields and one relation hop, excluding relation-of-relation', () => {
    const paths = fieldPathOptions(products, [products, artists]).map((p) => p.path);
    expect(paths).toContain('title');
    expect(paths).toContain('price');
    expect(paths).toContain('artist.name');
    expect(paths).toContain('artist.country');
    expect(paths).not.toContain('artist'); // bare relation is not filterable
    expect(paths).not.toContain('artist.friend'); // depth-2 wall
  });

  it('carries select options through the hop', () => {
    const country = fieldPathOptions(products, [products, artists]).find((p) => p.path === 'artist.country');
    expect(country?.type).toBe('select');
    expect(country?.options).toEqual(['NL', 'JP']);
  });
});

describe('operatorsForType', () => {
  it('matches the server matrix per type', () => {
    const price = operatorsForType('price').map((o) => o.value);
    expect(price).toEqual(expect.arrayContaining(['eq', 'gt', 'between', 'is_empty']));
    expect(price).not.toContain('contains');

    const multi = operatorsForType('multi_select').map((o) => o.value);
    expect(multi).toContain('has_any');
    expect(multi).not.toContain('eq');
  });
});

describe('pruneGroup', () => {
  it('drops blank conditions and collapses empty groups', () => {
    const pruned = pruneGroup({
      op: 'and',
      children: [
        { field: 'price', operator: 'lt', value: 500 },
        { field: '', operator: 'eq', value: '' },
        { op: 'or', children: [{ field: '', operator: 'eq' }] },
      ],
    });
    expect(pruned).toEqual({ op: 'and', children: [{ field: 'price', operator: 'lt', value: 500 }] });
  });

  it('returns null when nothing survives', () => {
    expect(pruneGroup({ op: 'and', children: [{ field: '', operator: '' }] })).toBeNull();
  });
});
