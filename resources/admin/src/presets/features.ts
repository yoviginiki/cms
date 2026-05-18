import type { BlockData } from '@/types/blocks';
import type { PresetDefinition } from './index';

function uid(): string { return crypto.randomUUID(); }

function featureCol(icon: string, title: string, desc: string, order: number): BlockData {
  return {
    id: uid(),
    type: 'column',
    level: 'column',
    order,
    data: { padding: '16px', vertical_align: 'start' },
    children: [
      { id: uid(), type: 'icon', level: 'module', order: 0, data: { name: icon, size: '32', color: '#3b82f6' }, children: [] },
      { id: uid(), type: 'heading', level: 'module', order: 1, data: { text: title, level: 'h3', fontSize: '1.25rem' }, children: [] },
      { id: uid(), type: 'paragraph', level: 'module', order: 2, data: { content: `<p style="color:#64748b;">${desc}</p>` }, children: [] },
    ],
  };
}

export const featuresPreset: PresetDefinition = {
  type: 'preset_features',
  label: 'Features Grid',
  icon: 'LayoutGrid',
  description: '3-column feature cards with icons',
  build: (): BlockData => ({
    id: uid(),
    type: 'section',
    level: 'section',
    order: 0,
    data: { padding_top: '60px', padding_bottom: '60px', max_width: '1200px' },
    children: [
      {
        id: uid(),
        type: 'row',
        level: 'row',
        order: 0,
        data: { layout: '1', gap: '16px' },
        children: [{
          id: uid(),
          type: 'column',
          level: 'column',
          order: 0,
          data: { padding: '0', vertical_align: 'center' },
          children: [{
            id: uid(),
            type: 'heading',
            level: 'module',
            order: 0,
            data: { text: 'Why Choose Us', level: 'h2', fontSize: '2rem' },
            children: [],
          }],
        }],
      },
      {
        id: uid(),
        type: 'row',
        level: 'row',
        order: 1,
        data: { layout: '1/3+1/3+1/3', gap: '32px' },
        children: [
          featureCol('Zap', 'Lightning Fast', 'Optimized for speed with lazy loading and critical CSS.', 0),
          featureCol('Shield', 'Secure by Default', 'Built-in protection against XSS, CSRF, and SQL injection.', 1),
          featureCol('Palette', 'Fully Customizable', 'Theme engine with CSS variables and live preview.', 2),
        ],
      },
    ],
  }),
};
