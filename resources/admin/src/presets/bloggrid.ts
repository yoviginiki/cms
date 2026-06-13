import type { BlockData } from '@/types/blocks';
import type { PresetDefinition } from './index';

function uid(): string { return crypto.randomUUID(); }

export const blogGridPreset: PresetDefinition = {
  type: 'preset_blog_grid',
  label: 'Blog Grid',
  icon: 'Newspaper',
  description: 'Latest blog posts in a card grid',
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
            { id: uid(), type: 'heading', level: 'module', order: 0, data: { text: 'Latest Articles', level: 'h2', fontSize: '2rem' }, children: [] },
            { id: uid(), type: 'paragraph', level: 'module', order: 1, data: { content: '<p style="color:#6b7280;text-align:center;">Stay up to date with our latest news and insights.</p>' }, children: [] },
          ],
        }],
      },
      {
        id: uid(), type: 'row', level: 'row', order: 1,
        data: { layout: '1', gap: '16px' },
        children: [{
          id: uid(), type: 'column', level: 'column', order: 0, data: {},
          children: [
            { id: uid(), type: 'postgrid', level: 'module', order: 0, data: { limit: 6, columns: 3, cardStyle: 'vertical', showExcerpt: true, showImage: true, imageHeight: 180 }, children: [] },
          ],
        }],
      },
    ],
  }),
};
