import type { BlockDefinition } from '@/types/blocks';

export const categorylistDefinition: BlockDefinition = {
  type: 'categorylist',
  category: 'blog',
  label: 'Category List',
  icon: 'FolderTree',
  defaultData: {
    style: 'links',
    showCount: true,
    parentOnly: false,
  },
  allowsChildren: false,
};
