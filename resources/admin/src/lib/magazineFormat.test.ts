import { describe, it, expect } from 'vitest';
import { formatPageNumber, evalNumericEntry } from './magazineFormat';

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
