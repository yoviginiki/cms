import type { BlockDefinition } from '@/types/blocks';

export const groupDefinition: BlockDefinition = {
  type: 'group',
  category: 'layout',
  label: 'Group',
  icon: 'Group',
  defaultData: {
    tag: 'div',
  },
  allowsChildren: true,
};
