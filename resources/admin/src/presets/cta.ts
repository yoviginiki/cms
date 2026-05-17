import type { BlockData } from '@/types/blocks';
import type { PresetDefinition } from './index';

function uid(): string { return crypto.randomUUID(); }

export const ctaPreset: PresetDefinition = {
  type: 'preset_cta',
  label: 'Call to Action',
  icon: 'Megaphone',
  description: 'Centered CTA with heading, text, and button',
  build: (): BlockData => ({
    id: uid(),
    type: 'section',
    level: 'section',
    order: 0,
    data: {
      padding_top: '60px',
      padding_bottom: '60px',
      max_width: '800px',
      bg_type: 'gradient',
      bg_gradient_type: 'linear',
      bg_gradient_angle: 135,
      bg_gradient_stops: [
        { color: '#3b82f6', position: 0 },
        { color: '#8b5cf6', position: 100 },
      ],
    },
    children: [{
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
        children: [
          {
            id: uid(),
            type: 'heading',
            level: 'module',
            order: 0,
            data: { text: 'Ready to get started?', level: 'h2', color: '#ffffff', fontSize: '2.25rem' },
            children: [],
          },
          {
            id: uid(),
            type: 'paragraph',
            level: 'module',
            order: 1,
            data: { content: '<p style="color:#e2e8f0;text-align:center;">Join thousands of users building beautiful websites today.</p>' },
            children: [],
          },
          {
            id: uid(),
            type: 'button',
            level: 'module',
            order: 2,
            data: { text: 'Start Free Trial', url: '#', style: 'secondary', size: 'lg', target: '_self' },
            children: [],
          },
        ],
      }],
    }],
  }),
};
