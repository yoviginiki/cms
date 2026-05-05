import type { BlockDefinition } from '@/types/blocks';

export const listDefinition: BlockDefinition = {
  type: 'list',
  category: 'typography',
  label: 'List',
  icon: 'List',
  description: 'Bullet, numbered, or checklist',
  defaultData: {
    items: ['Item 1', 'Item 2', 'Item 3'],
    listType: 'bullet',
  },
  allowsChildren: false,
  hasTypography: true,
};
