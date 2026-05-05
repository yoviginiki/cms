import type { BlockDefinition } from '@/types/blocks';

export const postgridDefinition: BlockDefinition = {
  type: 'postgrid',
  category: 'blog',
  label: 'Post Grid',
  icon: 'LayoutGrid',
  defaultData: {
    categoryId: '',
    limit: 6,
    columns: 3,
    cardStyle: 'vertical',
    showExcerpt: true,
  },
  allowsChildren: false,
};
