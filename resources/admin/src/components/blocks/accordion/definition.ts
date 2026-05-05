import type { BlockDefinition } from '@/types/blocks';

export const accordionDefinition: BlockDefinition = {
  type: 'accordion',
  category: 'interactive',
  label: 'Accordion',
  icon: '🪗',
  defaultData: {
    items: [{ title: 'Question', content: '<p>Answer</p>' }],
    multiOpen: false,
    iconStyle: 'arrow',
  },
  allowsChildren: false,
};
