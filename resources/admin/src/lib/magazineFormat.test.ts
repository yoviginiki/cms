import { describe, it, expect } from 'vitest';
import { formatPageNumber, evalNumericEntry } from './magazineFormat';
import { applyExtraSnaps } from './smartGuides';
import { computeDisplayNumbers } from './magazineFormat';

describe('formatPageNumber (W2-11)', () => {
  it('roman', () => {
    expect(formatPageNumber(4, 'roman-upper')).toBe('IV');
    expect(formatPageNumber(9, 'roman-lower')).toBe('ix');
    expect(formatPageNumber(1988, 'roman-upper')).toBe('MCMLXXXVIII');
    expect(formatPageNumber(14, 'roman-lower')).toBe('xiv');
  });
  it('alpha', () => {
    expect(formatPageNumber(1, 'alpha-upper')).toBe('A');
    expect(formatPageNumber(26, 'alpha-lower')).toBe('z');
    expect(formatPageNumber(27, 'alpha-upper')).toBe('AA');
  });
  it('decimal + edge', () => {
    expect(formatPageNumber(7)).toBe('7');
    expect(formatPageNumber(0, 'roman-upper')).toBe('0');
  });
});

describe('evalNumericEntry (W2-7)', () => {
  it('relative ops', () => {
    expect(evalNumericEntry('+10', 100)).toBe(110);
    expect(evalNumericEntry('+-5', 100)).toBe(95); // subtract via +-
    expect(evalNumericEntry('*2', 50)).toBe(100);
    expect(evalNumericEntry('/4', 100)).toBe(25);
    expect(evalNumericEntry('+ 2.5', 10)).toBe(12.5);
  });
  it('absolute + invalid', () => {
    expect(evalNumericEntry('42', 100)).toBe(42);
    expect(evalNumericEntry('-12', 100)).toBe(-12); // '-' is absolute (negative coords are legal)
    expect(evalNumericEntry('abc', 100)).toBeNull();
    expect(evalNumericEntry('/0', 100)).toBeNull();
    expect(evalNumericEntry('', 100)).toBeNull();
  });
});

describe('applyExtraSnaps (W2-1/2/3)', () => {
  const base = { guidesV: [100], guidesH: [200], baselineIncrement: 14, baselineStart: 36, snapGuides: true, snapBaseline: false };
  it('snaps frame edges and centers to guides', () => {
    expect(applyExtraSnaps(97, 50, 40, 40, base).x).toBe(100);
    expect(applyExtraSnaps(78, 50, 40, 40, base).x).toBe(80);
    expect(applyExtraSnaps(50, 158, 40, 40, base).y).toBe(160);
    expect(applyExtraSnaps(50, 50, 40, 40, base).x).toBe(50);
  });
  it('snaps to the baseline grid when enabled', () => {
    const o = { ...base, snapGuides: false, snapBaseline: true };
    expect(applyExtraSnaps(10, 51, 40, 40, o).y).toBe(50);
    expect(applyExtraSnaps(10, 44, 40, 40, o).y).toBe(44);
  });
});

describe('computeDisplayNumbers (W2-11 sections)', () => {
  it('front matter roman, body restarts at 1', () => {
    const pages = [
      { pageNumber: 1, _section: { startAt: 1, format: 'roman-lower' } },
      { pageNumber: 2 },
      { pageNumber: 3, _section: { startAt: 1, format: 'decimal' } },
      { pageNumber: 4 },
      { pageNumber: 5 },
    ];
    const d = computeDisplayNumbers(pages as any);
    expect(d[1]).toEqual({ n: 1, format: 'roman-lower' });
    expect(d[2]).toEqual({ n: 2, format: 'roman-lower' });
    expect(d[3]).toEqual({ n: 1, format: 'decimal' });
    expect(d[5]).toEqual({ n: 3, format: 'decimal' });
  });
  it('no sections = plain sequence', () => {
    const d = computeDisplayNumbers([{ pageNumber: 1 }, { pageNumber: 2 }] as any);
    expect(d[2].n).toBe(2);
  });
});
