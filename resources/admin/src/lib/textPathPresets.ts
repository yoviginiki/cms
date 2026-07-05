// ═══════════════════════════════════════════════════════════════════════════
// Text-on-path ([pro]): SVG path presets in a normalized element box.
// Same d-strings are generated server-side (DtpRenderService) for publish.
// ═══════════════════════════════════════════════════════════════════════════

export type TextPathPreset = 'arc-up' | 'arc-down' | 'circle' | 'wave';

export function textPathD(preset: TextPathPreset, w: number, h: number): string {
  const W = Math.max(1, w);
  const H = Math.max(1, h);
  switch (preset) {
    case 'arc-down':
      return `M 0 ${0.2 * H} Q ${0.5 * W} ${1.4 * H} ${W} ${0.2 * H}`;
    case 'circle': {
      const r = Math.min(W, H) / 2 - 2;
      const cx = W / 2;
      const cy = H / 2;
      return `M ${cx} ${cy - r} A ${r} ${r} 0 1 1 ${cx - 0.01} ${cy - r}`;
    }
    case 'wave':
      return `M 0 ${0.5 * H} C ${0.25 * W} ${0.1 * H} ${0.75 * W} ${0.9 * H} ${W} ${0.5 * H}`;
    case 'arc-up':
    default:
      return `M 0 ${0.8 * H} Q ${0.5 * W} ${-0.4 * H} ${W} ${0.8 * H}`;
  }
}
