import type { BlockData } from '@/types/blocks';
import type { PresetDefinition } from './index';

function uid(): string { return crypto.randomUUID(); }

export const imageTextPreset: PresetDefinition = {
  type: 'preset_image_text',
  label: 'Image + Text',
  icon: 'Columns',
  description: 'Split layout with image and text side by side',
  build: (): BlockData => ({
    id: uid(),
    type: 'section',
    level: 'section',
    order: 0,
    data: {
      padding_top: '60px',
      padding_bottom: '60px',
      max_width: '1100px',
    },
    children: [{
      id: uid(),
      type: 'row',
      level: 'row',
      order: 0,
      data: { layout: '1/2+1/2', gap: '40px' },
      children: [
        {
          id: uid(),
          type: 'column',
          level: 'column',
          order: 0,
          data: { vertical_align: 'center' },
          children: [
            {
              id: uid(),
              type: 'image',
              level: 'module',
              order: 0,
              data: { src: '', alt: 'Feature image', width: '100%', height: 'auto', borderRadius: '0.75rem' },
              children: [],
            },
          ],
        },
        {
          id: uid(),
          type: 'column',
          level: 'column',
          order: 1,
          data: { vertical_align: 'center' },
          children: [
            {
              id: uid(),
              type: 'heading',
              level: 'module',
              order: 0,
              data: { text: 'Built for Creators', level: 'h2', fontSize: '1.75rem' },
              children: [],
            },
            {
              id: uid(),
              type: 'paragraph',
              level: 'module',
              order: 1,
              data: { content: '<p>Our platform gives you all the tools you need to build stunning websites without writing code. Drag, drop, and publish in minutes.</p>' },
              children: [],
            },
            {
              id: uid(),
              type: 'button',
              level: 'module',
              order: 2,
              data: { text: 'Learn More', url: '#', style: 'primary', size: 'md' },
              children: [],
            },
          ],
        },
      ],
    }],
  }),
};
