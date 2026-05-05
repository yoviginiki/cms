import type { BlockDefinition } from '@/types/blocks';

export const testimonialDefinition: BlockDefinition = {
  type: 'testimonial',
  category: 'data',
  label: 'Testimonial',
  icon: 'MessageSquareQuote',
  defaultData: {
    items: [{ quote: 'Great product!', author: 'John', role: 'CEO', avatar: '' }],
    layout: 'single',
  },
  allowsChildren: false,
};
