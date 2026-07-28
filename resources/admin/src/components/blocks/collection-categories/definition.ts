import type { BlockDefinition } from '@/types/blocks';

export const collectionCategoriesDefinition: BlockDefinition = {
  type: 'collection-categories',
  category: 'dynamic',
  label: 'Category List',
  icon: 'FolderTree',
  description: 'Card grid of a collection\'s categories, linking to their listing pages',
  level: 'module',
  defaultData: {
    collectionId: null,
    parentNodeId: null,   // null = root level; otherwise children of that node
    layout: 'cards',      // cards | pills | list
    columns: 4,
    showCount: true,
    hideEmpty: false,
    gap: '1rem',
  },
  allowsChildren: false,
};
