// Magazine formatting utilities (Wave 2).

/** page-number formatting — the roman converter the audit found missing */
export function formatPageNumber(
  n: number,
  format: 'decimal' | 'roman-lower' | 'roman-upper' | 'alpha-lower' | 'alpha-upper' = 'decimal',
): string {
  if (!Number.isFinite(n) || n < 1) return String(n);
  switch (format) {
    case 'roman-lower':
      return toRoman(n).toLowerCase();
    case 'roman-upper':
      return toRoman(n);
    case 'alpha-lower':
      return toAlpha(n).toLowerCase();
    case 'alpha-upper':
      return toAlpha(n);
    default:
      return String(n);
  }
}

function toRoman(n: number): string {
  const table: Array<[number, string]> = [
    [1000, 'M'], [900, 'CM'], [500, 'D'], [400, 'CD'],
    [100, 'C'], [90, 'XC'], [50, 'L'], [40, 'XL'],
    [10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I'],
  ];
  let out = '';
  let v = Math.floor(n);
  for (const [val, sym] of table) {
    while (v >= val) {
      out += sym;
      v -= val;
    }
  }
  return out;
}

function toAlpha(n: number): string {
  // 1→A … 26→Z, 27→AA (spreadsheet style)
  let v = Math.floor(n);
  let out = '';
  while (v > 0) {
    v--;
    out = String.fromCharCode(65 + (v % 26)) + out;
    v = Math.floor(v / 26);
  }
  return out;
}

/**
 * numeric-field math entry (W2-7): "+10", "-5", "*2", "/4" apply relative to
 * the current value; plain numbers replace it. Returns null when unparseable.
 */
export function evalNumericEntry(input: string, current: number): number | null {
  const s = input.trim().replace(',', '.');
  if (s === '') return null;
  // '+', '*', '/' are relative; '-' stays ABSOLUTE (negative coordinates are
  // legal). Subtract via '+-5'.
  const rel = s.match(/^([+*/])\s*(-?\d+(?:\.\d+)?)$/);
  if (rel) {
    const v = parseFloat(rel[2]);
    switch (rel[1]) {
      case '+': return current + v;
      case '*': return current * v;
      case '/': return v === 0 ? null : current / v;
    }
  }
  const abs = s.match(/^-?\d+(?:\.\d+)?$/);
  return abs ? parseFloat(s) : null;
}
