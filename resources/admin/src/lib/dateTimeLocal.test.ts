import { describe, it, expect } from 'vitest';
import { toLocalInputValue, fromLocalInputValue } from './dateTimeLocal';

describe('datetime-local ↔ ISO (F29)', () => {
  it('shows Europe/Sofia wall-clock time in winter (UTC+2) and summer (UTC+3)', () => {
    expect(toLocalInputValue('2026-01-15T10:00:00Z', 'Europe/Sofia')).toBe('2026-01-15T12:00');
    expect(toLocalInputValue('2026-07-15T10:00:00Z', 'Europe/Sofia')).toBe('2026-07-15T13:00');
    expect(toLocalInputValue('2026-07-15T10:00:00Z', 'UTC')).toBe('2026-07-15T10:00');
  });

  it('sends an offset-aware instant back and round-trips without drift', () => {
    expect(fromLocalInputValue('2026-01-15T12:00', 'Europe/Sofia')).toBe('2026-01-15T12:00:00+02:00');
    expect(fromLocalInputValue('2026-07-15T13:00', 'Europe/Sofia')).toBe('2026-07-15T13:00:00+03:00');
    expect(new Date(fromLocalInputValue('2026-07-15T13:00', 'Europe/Sofia')!).toISOString()).toBe('2026-07-15T10:00:00.000Z');

    for (const iso of ['2026-01-15T10:00:00Z', '2026-07-15T10:00:00Z', '2026-03-29T00:30:00Z', '2026-10-25T00:30:00Z']) {
      const local = toLocalInputValue(iso, 'Europe/Sofia');
      const back = fromLocalInputValue(local, 'Europe/Sofia')!;
      expect(new Date(back).toISOString()).toBe(new Date(iso).toISOString());
      expect(toLocalInputValue(back, 'Europe/Sofia')).toBe(local); // a plain Save never shifts the clock
    }
  });

  it('handles the DST boundary deterministically', () => {
    // 2026-03-29 03:30 does not exist in Sofia (02:00 → 03:00 skip): resolves after the gap
    const gap = fromLocalInputValue('2026-03-29T03:30', 'Europe/Sofia')!;
    expect(new Date(gap).toISOString()).toBe('2026-03-29T00:30:00.000Z');
    // 2026-10-25 03:30 happens twice (04:00 → 03:00): the first (DST, +03:00) occurrence is used
    const overlap = fromLocalInputValue('2026-10-25T03:30', 'Europe/Sofia')!;
    expect(overlap.endsWith('+03:00')).toBe(true);
    expect(new Date(overlap).toISOString()).toBe('2026-10-25T00:30:00.000Z');
  });

  it('is null/empty safe', () => {
    expect(toLocalInputValue(null)).toBe('');
    expect(toLocalInputValue('garbage')).toBe('');
    expect(fromLocalInputValue('')).toBeNull();
    expect(fromLocalInputValue('not-a-date')).toBeNull();
  });
});
