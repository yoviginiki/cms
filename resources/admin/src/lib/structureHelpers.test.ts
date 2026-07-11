import { describe, it, expect } from 'vitest';
import { structureLabel, dropZone } from './structureHelpers';
import type { BlockData } from '@/types/blocks';

const mk = (type: string, data: Record<string, unknown>): BlockData =>
  ({ id: 'x', type, level: 'module', data, children: [] } as BlockData);

describe('structureLabel', () => {
  it('prefers an explicit __label', () => {
    expect(structureLabel(mk('heading', { __label: 'Hero title', text: 'Ignored' }))).toBe('Hero title');
  });

  it('trims whitespace-only custom labels back to derivation', () => {
    expect(structureLabel(mk('heading', { __label: '   ', text: 'Welcome' }))).toBe('Welcome');
  });

  it('derives heading and button labels from their fields', () => {
    expect(structureLabel(mk('heading', { text: 'Welcome' }))).toBe('Welcome');
    expect(structureLabel(mk('button', { text: 'Buy now' }))).toBe('Buy now');
  });

  it('strips HTML from text content and truncates long labels', () => {
    const long = '<p>' + 'a'.repeat(80) + '</p>';
    const label = structureLabel(mk('text', { content: long }))!;
    expect(label).toBe('a'.repeat(40));
    expect(structureLabel(mk('text', { content: '<b>Hi</b> there' }))).toBe('Hi there');
  });

  it('labels rows by layout and images generically', () => {
    expect(structureLabel(mk('row', { layout: '1/3+2/3' }))).toBe('Row · 1/3+2/3');
    expect(structureLabel(mk('image', { url: 'x.png' }))).toBe('Image');
  });

  it('returns null when nothing content-specific applies', () => {
    expect(structureLabel(mk('divider', {}))).toBeNull();
    expect(structureLabel(mk('text', { content: '<p>  </p>' }))).toBeNull();
  });
});

describe('dropZone', () => {
  it('nests into the middle band when the target allows children', () => {
    expect(dropZone(10, 20, true)).toBe('inside'); // exact middle
  });

  it('inserts before/after in the top/bottom halves', () => {
    expect(dropZone(2, 20, true)).toBe('before');
    expect(dropZone(18, 20, true)).toBe('after');
  });

  it('never nests into a childless target', () => {
    expect(dropZone(10, 20, false)).toBe('after'); // middle → falls to after (>= half)
    expect(dropZone(4, 20, false)).toBe('before');
  });
});
