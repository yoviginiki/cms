import type { BlockDefinition } from '@/types/blocks';

export const textDefinition: BlockDefinition = {
  type: 'text',
  category: 'content',
  label: 'Text Block',
  icon: 'Type',
  defaultData: {
    content: '',
  },
  allowsChildren: false,
};
