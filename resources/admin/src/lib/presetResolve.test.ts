import { describe, it, expect } from 'vitest';
import { resolvePresetStyle } from './presetResolve';
import type { BlockData } from '@/types/blocks';
import type { StylePreset } from '@/lib/api';

const preset = (id: string, style: any, kind: 'element' | 'group' = 'element'): StylePreset => ({
  id, site_id: 's', block_type: 'text', kind, name: id, style, is_default: false, sort: 0, is_system: false,
});

const block = (data: any, style: any = undefined): BlockData => ({
  id: 'b', type: 'text', level: 'module', data, children: [], order: 0, style,
} as BlockData);

describe('resolvePresetStyle (canvas mirror of PHP StylePresetResolver)', () => {
  it('returns local style unchanged (same ref) when no preset is linked', () => {
    const b = block({ content: 'x' }, { spacing: { paddingTop: '4px' } });
    expect(resolvePresetStyle(b, [])).toBe(b.style); // identity → no re-render churn
  });

  it('applies the element preset, local overrides winning', () => {
    const presets = [preset('el', { visual: { backgroundColor: '#f00' }, spacing: { paddingTop: '20px' } })];
    const b = block({ __stylePreset: 'el' }, { spacing: { paddingTop: '99px' } });
    const r = resolvePresetStyle(b, presets);
    expect(r.visual?.backgroundColor).toBe('#f00'); // from preset
    expect(r.spacing?.paddingTop).toBe('99px');      // local wins
  });

  it('stacks option-group presets under local', () => {
    const presets = [
      preset('g1', { spacing: { paddingTop: '10px', paddingBottom: '10px' } }, 'group'),
      preset('g2', { typography: { fontWeight: '700' } }, 'group'),
    ];
    const b = block({ __presetGroups: ['g1', 'g2'] }, { spacing: { paddingBottom: '40px' } });
    const r = resolvePresetStyle(b, presets);
    expect(r.spacing?.paddingTop).toBe('10px');    // from g1
    expect((r.spacing as any)?.paddingBottom).toBe('40px'); // local wins over g1
    expect((r.typography as any)?.fontWeight).toBe('700');  // from g2
  });

  it('keeps token refs intact ($color.accent stays for the sanitizer to compile)', () => {
    const presets = [preset('el', { visual: { backgroundColor: '$color.accent' } })];
    const r = resolvePresetStyle(block({ __stylePreset: 'el' }), presets);
    expect(r.visual?.backgroundColor).toBe('$color.accent');
  });
});
