import type { BlockDefinition } from '@/types/blocks';

export const richTextDefinition: BlockDefinition = {
  type: 'rich-text',
  category: 'content',
  label: 'Rich Text',
  icon: 'FileEdit',
  defaultData: {
    content: '<p></p>',
  },
  allowsChildren: false,
};
