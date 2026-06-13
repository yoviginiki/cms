import type { BlockData } from '@/types/blocks';
import type { PresetDefinition } from './index';

function uid(): string { return crypto.randomUUID(); }

export const contactPreset: PresetDefinition = {
  type: 'preset_contact',
  label: 'Contact Section',
  icon: 'Mail',
  description: 'Contact info with heading and details',
  build: (): BlockData => ({
    id: uid(),
    type: 'section',
    level: 'section',
    order: 0,
    data: { padding_top: '60px', padding_bottom: '60px', max_width: '1000px', bg_type: 'color', bg_color: '#f8fafc' },
    children: [{
      id: uid(),
      type: 'row',
      level: 'row',
      order: 0,
      data: { layout: '1/2+1/2', gap: '48px', vertical_align: 'start' },
      children: [
        {
          id: uid(), type: 'column', level: 'column', order: 0, data: {},
          children: [
            { id: uid(), type: 'heading', level: 'module', order: 0, data: { text: 'Get in Touch', level: 'h2', fontSize: '2rem' }, children: [] },
            { id: uid(), type: 'paragraph', level: 'module', order: 1, data: { content: '<p style="color:#6b7280;">We\'d love to hear from you. Send us a message and we\'ll respond as soon as possible.</p>' }, children: [] },
            { id: uid(), type: 'paragraph', level: 'module', order: 2, data: { content: '<p><strong>Email:</strong> hello@example.com<br/><strong>Phone:</strong> +1 (555) 123-4567<br/><strong>Address:</strong> 123 Main St, City, Country</p>' }, children: [] },
          ],
        },
        {
          id: uid(), type: 'column', level: 'column', order: 1, data: {},
          children: [
            { id: uid(), type: 'contact-form', level: 'module', order: 0, data: { recipientEmail: '', successMessage: 'Thank you! We\'ll be in touch.' }, children: [] },
          ],
        },
      ],
    }],
  }),
};
