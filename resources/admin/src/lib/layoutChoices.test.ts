import { describe, it, expect } from 'vitest';
import { pickableLayouts } from './layoutChoices';

const all = [
  { id: 's', slug: 'standard', name: 'Standard' }, { id: 'b', slug: 'bare', name: 'Bare' },
  { id: 'l', slug: 'landing', name: 'Landing' }, { id: 'f', slug: 'full-bleed', name: 'Full-bleed' },
];

describe('pickableLayouts', () => {
  it('offers only bare + landing (Standard is the empty choice)', () => {
    expect(pickableLayouts(all).map(l => l.slug)).toEqual(['bare', 'landing']);
  });
  it('keeps a legacy layout that is already in use, flagged', () => {
    expect(pickableLayouts(all, 'f').map(l => [l.slug, !!l.legacy])).toEqual([['bare', false], ['landing', false], ['full-bleed', true]]);
  });
});
