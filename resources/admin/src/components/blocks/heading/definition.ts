import type { BlockDefinition } from '@/types/blocks';

export const headingDefinition: BlockDefinition = {
  type: 'heading',
  category: 'content',
  label: 'Heading',
  icon: 'Heading',
  defaultData: {
    text: 'Heading',
    level: 'h2',
  },
  allowsChildren: false,
};
