import type { BlockDefinition } from '@/types/blocks';

export const latestpostsDefinition: BlockDefinition = {
  type: 'latestposts',
  category: 'blog',
  label: 'Blog Posts',
  icon: 'Newspaper',
  description: 'Display posts filtered by category, with configurable layout and columns',
  defaultData: {
    categoryId: '',
    limit: 5,
    columns: 1,
    layout: 'cards',
    orderBy: 'latest',
    showImage: true,
    showExcerpt: true,
    showDate: true,
    showCategory: true,
  },
  allowsChildren: false,
};
