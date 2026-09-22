import { describe, it, expect } from 'vitest';
import { fitZoom } from './CanvasEditor';

describe('fitZoom', () => {
  it('shrinks the design width into the pane with a gutter, rounded down to whole percents', () => {
    expect(fitZoom(1000, 1200)).toBe(0.79);   // (1000-48)/1200 = 0.793
    expect(fitZoom(700, 1200)).toBe(0.54);
  });
  it('never enlarges past 1:1 and never below the minimum zoom', () => {
    expect(fitZoom(3000, 1200)).toBe(1);
    expect(fitZoom(100, 1200)).toBe(0.25);
  });
  it('leaves zoom alone when the pane has no size yet (jsdom / not mounted)', () => {
    expect(fitZoom(0, 1200)).toBe(1);
  });
});
