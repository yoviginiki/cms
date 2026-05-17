import type { BlockData } from '@/types/blocks';
import type { PresetDefinition } from './index';

function uid(): string { return crypto.randomUUID(); }

export const heroPreset: PresetDefinition = {
  type: 'preset_hero',
  label: 'Hero Section',
  icon: 'Layout',
  description: 'Full-width hero with heading, text, button, and image',
  build: (): BlockData => ({
    id: uid(),
    type: 'section',
    level: 'section',
    order: 0,
    data: {
      background_color: '#1e293b',
      padding_top: '80px',
      padding_bottom: '80px',
      max_width: '1200px',
      bg_type: 'color',
      bg_color: '#1e293b',
    },
    children: [{
      id: uid(),
      type: 'row',
      level: 'row',
      order: 0,
      data: { layout: '1/2+1/2', gap: '48px', vertical_align: 'center' },
      children: [
        {
          id: uid(),
          type: 'column',
          level: 'column',
          order: 0,
          data: { padding: '0', vertical_align: 'center' },
          children: [
            {
              id: uid(),
              type: 'heading',
              level: 'module',
              order: 0,
              data: { text: 'Build Something Amazing', level: 'h1', color: '#ffffff', fontSize: '3rem' },
              children: [],
            },
            {
              id: uid(),
              type: 'paragraph',
              level: 'module',
              order: 1,
              data: { content: '<p style="color:#94a3b8;font-size:1.125rem;">Create stunning websites with our visual builder. No coding required — just drag, drop, and customize.</p>' },
              children: [],
            },
            {
              id: uid(),
              type: 'button',
              level: 'module',
              order: 2,
              data: { text: 'Get Started', url: '#', style: 'primary', size: 'lg', target: '_self' },
              children: [],
            },
          ],
        },
        {
          id: uid(),
          type: 'column',
          level: 'column',
          order: 1,
          data: { padding: '0', vertical_align: 'center' },
          children: [{
            id: uid(),
            type: 'image',
            level: 'module',
            order: 0,
            data: { src: '', alt: 'Hero image', caption: '' },
            children: [],
          }],
        },
      ],
    }],
  }),
};
