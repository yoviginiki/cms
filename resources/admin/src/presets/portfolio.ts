import type { BlockData } from '@/types/blocks';
import type { PresetDefinition } from './index';

function uid(): string { return crypto.randomUUID(); }

export const portfolioPreset: PresetDefinition = {
  type: 'preset_portfolio',
  label: 'Portfolio Grid',
  icon: 'Image',
  description: 'Image gallery grid for showcasing work',
  build: (): BlockData => ({
    id: uid(),
    type: 'section',
    level: 'section',
    order: 0,
    data: { padding_top: '60px', padding_bottom: '60px', max_width: '1200px' },
    children: [
      {
        id: uid(), type: 'row', level: 'row', order: 0,
        data: { layout: '1', gap: '16px' },
        children: [{
          id: uid(), type: 'column', level: 'column', order: 0, data: {},
          children: [
            { id: uid(), type: 'heading', level: 'module', order: 0, data: { text: 'Our Work', level: 'h2', fontSize: '2rem' }, children: [] },
            { id: uid(), type: 'paragraph', level: 'module', order: 1, data: { content: '<p style="color:#6b7280;text-align:center;">A selection of our recent projects.</p>' }, children: [] },
          ],
        }],
      },
      {
        id: uid(), type: 'row', level: 'row', order: 1,
        data: { layout: '1', gap: '16px' },
        children: [{
          id: uid(), type: 'column', level: 'column', order: 0, data: {},
          children: [
            { id: uid(), type: 'gallery', level: 'module', order: 0, data: { layout: 'grid', columns: 3, gap: '16px', images: [] }, children: [] },
          ],
        }],
      },
    ],
  }),
};
