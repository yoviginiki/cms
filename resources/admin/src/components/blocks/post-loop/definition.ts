import type { BlockDefinition } from '@/types/blocks';

export const postLoopDefinition: BlockDefinition = {
  type: 'post-loop',
  category: 'dynamic',
  label: 'Post Loop',
  icon: 'LayoutList',
  description: 'Renders a list of posts from the current category/archive',
  level: 'module',
  defaultData: {
    layout: 'cards',       // cards, list, grid, featured
    columns: 3,
    showImage: true,
    showExcerpt: true,
    showDate: true,
    showCategory: false,
    showAuthor: false,
    imageAspectRatio: '16:9',
    excerptLines: 3,
    gap: '1.5rem',
    limit: 12,
  },
  allowsChildren: false,
};
