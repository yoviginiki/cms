import type { BlockData } from '@/types/blocks';
import type { PresetDefinition } from './index';

function uid(): string { return crypto.randomUUID(); }

export const faqPreset: PresetDefinition = {
  type: 'preset_faq',
  label: 'FAQ Section',
  icon: 'HelpCircle',
  description: 'Frequently asked questions with accordion',
  build: (): BlockData => ({
    id: uid(),
    type: 'section',
    level: 'section',
    order: 0,
    data: { padding_top: '60px', padding_bottom: '60px', max_width: '800px' },
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
        data: {},
        children: [
          {
            id: uid(), type: 'heading', level: 'module', order: 0,
            data: { text: 'Frequently Asked Questions', level: 'h2', fontSize: '2rem' },
            children: [],
          },
          {
            id: uid(), type: 'paragraph', level: 'module', order: 1,
            data: { content: '<p style="color:#6b7280;text-align:center;">Find answers to common questions below.</p>' },
            children: [],
          },
          {
            id: uid(), type: 'accordion', level: 'module', order: 2,
            data: {
              items: [
                { title: 'What is this product?', content: 'A brief description of your product or service goes here.' },
                { title: 'How does pricing work?', content: 'Explain your pricing model clearly and concisely.' },
                { title: 'Can I cancel anytime?', content: 'Yes, you can cancel your subscription at any time with no fees.' },
                { title: 'Do you offer support?', content: 'We offer 24/7 support via email and live chat.' },
              ],
            },
            children: [],
          },
        ],
      }],
    }],
  }),
};
