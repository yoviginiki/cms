import type { BlockData } from '@/types/blocks';
import type { PresetDefinition } from './index';

function uid(): string { return crypto.randomUUID(); }

function testimonialCol(quote: string, author: string, role: string, order: number): BlockData {
  return {
    id: uid(),
    type: 'column',
    level: 'column',
    order,
    data: { padding: '24px', vertical_align: 'start', background_color: '#f8fafc' },
    children: [
      { id: uid(), type: 'paragraph', level: 'module', order: 0, data: { content: `<p style="font-style:italic;color:#475569;">"${quote}"</p>` }, children: [] },
      { id: uid(), type: 'paragraph', level: 'module', order: 1, data: { content: `<p><strong>${author}</strong><br><span style="color:#64748b;">${role}</span></p>` }, children: [] },
    ],
  };
}

export const testimonialsPreset: PresetDefinition = {
  type: 'preset_testimonials',
  label: 'Testimonials',
  icon: 'MessageSquareQuote',
  description: '3-column customer testimonials',
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
            data: { text: 'What Our Customers Say', level: 'h2', fontSize: '2rem' },
            children: [],
          }],
        }],
      },
      {
        id: uid(),
        type: 'row',
        level: 'row',
        order: 1,
        data: { layout: '1/3+1/3+1/3', gap: '24px' },
        children: [
          testimonialCol('This product transformed how we build websites. Incredibly intuitive.', 'Sarah Johnson', 'CEO, TechStart', 0),
          testimonialCol('The best page builder I have ever used. Period.', 'Mark Chen', 'Designer, CreativeHub', 1),
          testimonialCol('Our team productivity doubled after switching to this platform.', 'Emma Wilson', 'CTO, DataFlow', 2),
        ],
      },
    ],
  }),
};
